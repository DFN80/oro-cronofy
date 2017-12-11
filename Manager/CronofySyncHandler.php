<?php

namespace Dfn\Bundle\OroCronofyBundle\Manager;

use Buzz\Message\RequestInterface;
use Dfn\Bundle\OroCronofyBundle\Async\Topics;
use Dfn\Bundle\OroCronofyBundle\Entity\CalendarOrigin;
use Dfn\Bundle\OroCronofyBundle\Entity\CronofyEvent;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectRepository;
use Oro\Bundle\CalendarBundle\Entity\CalendarEvent;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;

/**
 * Class CronofySyncHandler
 * @package Dfn\Bundle\OroCronofyBundle\Manager
 */
class CronofySyncHandler
{
    /** Amount of days to go back during initial sync */
    const DAYS_BACK = 42;

    /** Amount of days to go forward during initial sync */
    const DAYS_FORWARD = 201;

    /** Items per message when creating push messages */
    const PUSH_PER_PUSH = 30;

    /** @var ManagerRegistry */
    private $doctrine;

    /** @var CronofyAPIManager  */
    private $apiManager;

    /** @var \Doctrine\Common\Persistence\ObjectRepository */
    private $originRepo;

    /** @var \Oro\Bundle\CalendarBundle\Entity\Repository\CalendarEventRepository */
    private $eventRepo;

    /** @var MessageProducerInterface */
    private $messageProducer;

    /**
     * CronofyNotificationHandler constructor.
     * @param ManagerRegistry $doctrine
     * @param CronofyAPIManager $apiManager
     * @param MessageProducerInterface $messageProducer
     */
    public function __construct(
        ManagerRegistry $doctrine,
        CronofyAPIManager $apiManager,
        MessageProducerInterface $messageProducer
    ) {
        $this->doctrine = $doctrine;
        $this->apiManager = $apiManager;
        $this->messageProducer = $messageProducer;

        $this->originRepo = $this->doctrine->getRepository('DfnOroCronofyBundle:CalendarOrigin');
        $this->eventRepo = $this->doctrine->getRepository('OroCalendarBundle:CalendarEvent');
    }

    /**
     * @param array $message
     */
    public function processSync(array $message)
    {
        //Lookup calendar origin by channel_id
        $calendarOrigin = $this->originRepo->findOneBy(
            [
                "id" => $message['origin_id'],
                "isActive" => true
            ]
        );

        if (!$calendarOrigin) {
            //No origin found for the specified channel, we can't do anything with this message.
            //TODO log this, likely throw exception which will get logged
            return;
        }

        $action = $message['action'];
        switch ($action){
            case 'pull':
                $this->pullEvents($calendarOrigin);
                break;
            case 'push':
                //TODO
                $this->pushEvents($calendarOrigin);
                break;
        }
    }

    /**
     * @param CalendarOrigin $calendarOrigin
     */
    public function pullEvents(CalendarOrigin $calendarOrigin)
    {
        //Get events based on constants for days back and forward.
        $response = $this->apiManager->readEvents(
            $calendarOrigin,
            new \DateTime("now -".self::DAYS_BACK."days"),
            new \DateTime("now +".self::DAYS_FORWARD."days"),
            null,
            false
        );

        //Send events off to queue, the event handler will confirm if the event has been modified prior to updating it.
        $this->messageProducer->send(Topics::CREATE_EVENTS, json_encode($response['events']));

        //If there's additional pages of events get those and create more messages.
        $this->processAdditionalPages($calendarOrigin, $response);

        //Record new last pulled at datetime
        $calendarOrigin->setLastPulledAt(new \DateTime());
        $em = $this->doctrine->getManager();
        $em->persist($calendarOrigin);
        $em->flush();
    }

    /**
     * @param CalendarOrigin $calendarOrigin
     */
    public function pushEvents(CalendarOrigin $calendarOrigin)
    {
        //Get all event ids for events there's no cronofyEvent record on the users active origin.
        $events = $this->originRepo->getEventsToSync($calendarOrigin);

        //If we got results then send them as a message to the create events queue.
        if (count($events) > 0) {
            $this->messageProducer->send(Topics::PUSH_NEW_EVENTS, json_encode($events));
        }

        //Record new last pushed at datetime
        $calendarOrigin->setLastPushedAt(new \DateTime());
        $em = $this->doctrine->getManager();
        $em->persist($calendarOrigin);
        $em->flush();
    }

    /**
     * @param CalendarOrigin $calendarOrigin
     * @param $response
     */
    protected function processAdditionalPages(CalendarOrigin $calendarOrigin, $response)
    {
        while (isset($response['pages']['next_page'])) {
            //Get next page of results
            $response = $this->apiManager->doHttpRequest(
                $calendarOrigin,
                $response['pages']['next_page'],
                RequestInterface::METHOD_GET
            );

            //Send events off to queue
            $this->messageProducer->send(Topics::CREATE_EVENTS, json_encode($response['events']));
        }
    }
}
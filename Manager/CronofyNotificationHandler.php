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
 * Class CronofyNotificationHandler
 * @package Dfn\Bundle\OroCronofyBundle\Manager
 */
class CronofyNotificationHandler
{

    /** @var ManagerRegistry */
    private $doctrine;

    /** @var CronofyAPIManager  */
    private $apiManager;

    /** @var \Oro\Bundle\CalendarBundle\Entity\Repository\CalendarEventRepository */
    private $eventRepo;

    /** @var \Doctrine\Common\Persistence\ObjectRepository */
    private $originRepo;

    /** @var \Doctrine\Common\Persistence\ObjectRepository  */
    private $cronofyEventRepo;

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

        $this->eventRepo = $this->doctrine->getRepository('OroCalendarBundle:CalendarEvent');
        $this->originRepo = $this->doctrine->getRepository('DfnOroCronofyBundle:CalendarOrigin');
        $this->cronofyEventRepo = $this->doctrine->getRepository('DfnOroCronofyBundle:CronofyEvent');
    }

    /**
     * @param array $message
     */
    public function processNotification(array $message)
    {
        //Lookup calendar origin by channel_id
        $calendarOrigin = $this->originRepo->findOneBy(
            [
                "channelId" => $message['channel']['channel_id'],
                "isActive" => true
            ]
        );

        if (!$calendarOrigin) {
            //No origin found for the specified channel, we can't do anything with this message.
            //TODO log this
            return;
        }

        $type = $message['notification']['type'];
        switch ($type){
            case 'change':
                $this->processChange($message, $calendarOrigin);
                break;
            case 'profile_disconnected':
                $this->processDisconnect($message, $calendarOrigin);
                break;
            case 'profile_initial_sync_completed':
                $this->processSync($message, $calendarOrigin);
                break;
        }
    }

    /**
     * @param array $message
     * @param CalendarOrigin $calendarOrigin
     */
    protected function processChange(array $message, CalendarOrigin $calendarOrigin)
    {
        $changesSince = new \DateTime($message['notification']['changes_since']);

        //Check if we've synced since the changes_since time, if so return as we've synced more recently
        if ($changesSince < $calendarOrigin->getSynchronizedAt()) {
            return;
        }

        //Pull events based on changes since time
        $response = $this->apiManager->readEvents($calendarOrigin, null, null, $changesSince);

        //Send events off to queue
        $this->messageProducer->send(Topics::CREATE_EVENTS, json_encode($response['events']));

        //If there's additional pages of events get those and create more messages.
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

        //Record new synchronized at datetime
        $calendarOrigin->setSynchronizedAt($changesSince);
        $em = $this->doctrine->getManager();
        $em->persist($calendarOrigin);
        $em->flush();
    }
}
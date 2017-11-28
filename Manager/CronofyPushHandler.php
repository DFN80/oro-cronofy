<?php

namespace Dfn\Bundle\OroCronofyBundle\Manager;

use Dfn\Bundle\OroCronofyBundle\Entity\CronofyEvent;

use Doctrine\Common\Persistence\ManagerRegistry;

use Oro\Bundle\CalendarBundle\Entity\CalendarEvent;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;

/**
 * Class CronofyPushHandler
 * @package Dfn\Bundle\OroCronofyBundle\Manager
 */
class CronofyPushHandler
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

    /** @var ConfigManager */
    protected $configManager;

    /**
     * CronofyPushHandler constructor.
     * @param ManagerRegistry $doctrine
     * @param CronofyAPIManager $apiManager
     * @param ConfigManager $configManager
     */
    public function __construct(ManagerRegistry $doctrine, CronofyAPIManager $apiManager, ConfigManager $configManager)
    {
        $this->doctrine = $doctrine;
        $this->apiManager = $apiManager;
        $this->configManager = $configManager;

        $this->eventRepo = $this->doctrine->getRepository('OroCalendarBundle:CalendarEvent');
        $this->originRepo = $this->doctrine->getRepository('DfnOroCronofyBundle:CalendarOrigin');
        $this->cronofyEventRepo = $this->doctrine->getRepository('DfnOroCronofyBundle:CronofyEvent');
    }

    /**
     * @param $message
     */
    public function pushNewEvent($message)
    {
        $this->pushEvent($message, "create");
    }

    /**
     * @param $message
     */
    public function pushUpdatedEvent($message)
    {
        $this->pushEvent($message, "update");
    }

    /**
     * @param $message
     * @param $action
     */
    protected function pushEvent($message, $action)
    {
        //Lookup event
        $event = $this->eventRepo->find($message['id']);

        $origin = $this->getOriginByEvent($event);

        //Build the content to send Cronofy.
        $parameters = $this->eventToArray($event);

        //Add attendee changes if any specified in message or we are creating a new event
        if (isset($message['content']['attendees'])) {
            $parameters['attendees'] = $message['content']['attendees'];
        } elseif ($event->getAttendees() && $action == "create") {
            //Add invites for attendees with emails in all create messages
            foreach ($event->getAttendees() as $attendee) {
                if ($attendee->getEmail()) {
                    $parameters['attendees']['invite'][] = [
                        'email' => $attendee->getEmail(),
                        'display_name' => $attendee->getDisplayName()
                    ];
                }
            }
        }

        $cronofyEvent = $this->cronofyEventRepo->findOneBy(
            [
                'calendarOrigin' => $origin,
                'calendarEvent' => $message['id']
            ]
        );

        //Check if we already have a record of syncing this event with Cronofy. Set correct id and reminders if so.
        if ($cronofyEvent) {
            if (!$cronofyEvent->getCronofyId()) {
                $parameters['event_id'] = $cronofyEvent->getCalendarEvent()->getId();

                //Set reminders based on what's store in the cronofyEvent record.
                $parameters['reminders'] = $cronofyEvent->getReminders();
            } else {
                //External events are called with 'event_uid' instead of 'event_id'
                unset($parameters['event_id']);
                $parameters['event_uid'] = $cronofyEvent->getCronofyId();
                //TODO: is this a recurring event? return if so, no update allowed. or just check if it's editable?
            }
        } else {
            //Set reminder based of calendar user system configuration.
            $this->configManager->setScopeId($event->getCalendar()->getOwner()->getId());
            $parameters['reminders'] = [['minutes' => $this->configManager->get('dfn_oro_cronofy.reminder')]];

            //Create Cronofy Event record, this is the first time we've synced it
            $em = $this->doctrine->getManager();
            $cronofyEvent = new CronofyEvent();
            $cronofyEvent->setCalendarEvent($event);
            $cronofyEvent->setCalendarOrigin($origin);
            $cronofyEvent->setReminders($parameters['reminders']);
            $em->persist($cronofyEvent);
            $em->flush();
        }

        //Use the API manager to send the post to create/update an event
        $this->apiManager->createOrUpdateEvent($origin, $parameters);

    }

    /**
     * @param $message
     */
    public function pushDeletedEvent($message)
    {
        $origin = $this->originRepo->find($message['origin_id']);

        //Set event ID based on the event being Internal or External
        if (is_numeric($message['id'])) {
            $message['content']['event_id'] = $message['id'];
        } else {
            $message['content']['event_uid'] = $message['id'];
        }

        //Use the API manager to send the call to delete an event
        $this->apiManager->deleteEvent($origin, $message['content']);
    }

    /**
     * @param CalendarEvent $event
     * @return \Dfn\Bundle\OroCronofyBundle\Entity\CalendarOrigin|object
     * @throws \Exception
     */
    protected function getOriginByEvent(CalendarEvent $event)
    {
        //Get the active calendar origin for the event owner.
        $userOwner = $event->getCalendar()->getOwner();

        //Get users active origin
        $origin = $this->originRepo->findOneBy(['owner' => $userOwner, 'isActive' => true]);

        //error if the origin is null
        if (!$origin) {
            throw new \Exception("No active origin found.");
        }

        //Return the active origin for the user
        return $origin;
    }

    /**
     * @param CalendarEvent $event
     * @return array
     */
    protected function eventToArray(CalendarEvent $event)
    {
        return [
            'event_id' => $event->getId(),
            'summary' => $event->getTitle(),
            'description' => $event->getDescription(),
            'start' => $event->getStart()->format(CronofyAPIManager::DATE_FORMAT),
            'end' => $event->getEnd()->format(CronofyAPIManager::DATE_FORMAT),
            'location' => [
                'description' => $event->getLocation()
            ]
        ];
    }

    /**
     * Returns either a internal event id, the cronofy event id or false if we've never sent this event to Cronofy
     * @param $origin
     * @param $event_id
     * @return string
     */
    public function getCronofyEventId($origin, $event_id)
    {
        $cronofyEvent = $this->cronofyEventRepo->findOneBy(
            [
                'calendarOrigin' => $origin,
                'calendarEvent' => $event_id
            ]
        );

        if ($cronofyEvent) {
            //Return cronofy external id if one is set, otherwise send our id
            return ($cronofyEvent->getCronofyId()) ? $cronofyEvent->getCronofyId() : $event_id;
        } else {
            return false;
        }
    }
}
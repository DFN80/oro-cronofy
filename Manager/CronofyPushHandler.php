<?php

namespace Dfn\Bundle\OroCronofyBundle\Manager;

use Dfn\Bundle\OroCronofyBundle\Entity\CalendarOrigin;
use Dfn\Bundle\OroCronofyBundle\Entity\CronofyEvent;

use Doctrine\Common\Persistence\ManagerRegistry;

use Oro\Bundle\CalendarBundle\Entity\CalendarEvent;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\CalendarBundle\Model\Recurrence;

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

    /** @var Recurrence */
    private $recurrenceModel;

    /** @var array */
    private $message;

    /** @var CalendarOrigin */
    private $origin;

    /**
     * CronofyPushHandler constructor.
     * @param ManagerRegistry $doctrine
     * @param CronofyAPIManager $apiManager
     * @param ConfigManager $configManager
     * @param Recurrence $recurrenceModel
     */
    public function __construct(
        ManagerRegistry $doctrine,
        CronofyAPIManager $apiManager,
        ConfigManager $configManager,
        Recurrence $recurrenceModel
    ) {
        $this->doctrine = $doctrine;
        $this->apiManager = $apiManager;
        $this->configManager = $configManager;
        $this->recurrenceModel = $recurrenceModel;

        $this->eventRepo = $this->doctrine->getRepository('OroCalendarBundle:CalendarEvent');
        $this->originRepo = $this->doctrine->getRepository('DfnOroCronofyBundle:CalendarOrigin');
        $this->cronofyEventRepo = $this->doctrine->getRepository('DfnOroCronofyBundle:CronofyEvent');
    }

    /**
     * @param $message
     */
    public function pushNewEvent($message)
    {
        $this->message = $message;
        $this->processEvent("create");
    }

    /**
     * @param $message
     */
    public function pushUpdatedEvent($message)
    {
        $this->message = $message;
        $this->processEvent("update");
    }

    /**
     * @param $action
     */
    protected function processEvent($action)
    {
        //Lookup event
        $event = $this->eventRepo->find($this->message['id']);

        $this->origin = $this->getOriginByEvent($event);

        //Build the content to send Cronofy.
        $parameters = $this->eventToArray($event);

        //Add attendee changes if any specified in message or we are creating a new event
        if (isset($this->message['content']['attendees'])) {
            $parameters['attendees'] = $this->message['content']['attendees'];
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

        if ($event->getRecurrence()) {
            //Send to recurring handler, this is a series
            $this->pushRecurring($event, $action, $parameters);
        } elseif ($event->getRecurringEvent()) {
            //Send to recurrence handler, this is a single event in a series
            $this->pushRecurrence($event, $parameters);
        } else {
            //Send to normal event handler
            $this->pushEvent($event, $parameters);
        }

    }

    /**
     * Handles sending an entire series of events to Cronofy based of it's recurrence pattern, minus exceptions
     *
     * @param CalendarEvent $event
     * @param $action
     * @param $baseParameters
     */
    public function pushRecurring(CalendarEvent $event, $action, $baseParameters)
    {
        //If update and recurrence pattern change then delete all current recurrences
        if ($action == "update" &&
            (isset($this->message['content']['start']) ||
            isset($this->message['content']['end']) ||
            isset($this->message['content']['recurrenceChanges']))
        ) {
            //Remove all recurrences in cronofy and our tracking records
            $this->removeRecurrences($event);
        }

        //Get all future recurrences up to max sync date.
        $recurrence = $event->getRecurrence();
        $recurrences = $this->recurrenceModel->getOccurrences(
            $recurrence,
            new \DateTime('today'),
            new \DateTime('now +'.CronofySyncHandler::DAYS_FORWARD.' days')
        );

        //Get recurrence exceptions
        $exceptions = $event->getRecurringEventExceptions();

        if ($exceptions->count() > 0) {
            //Remove exceptions if any exist
            $recurrences = $this->removeExceptions($exceptions, $recurrences);
        }

        //Get the difference between the start and end time so we can set recurrence end times
        $eventInterval = $event->getStart()->diff($event->getEnd());

        $em = $this->doctrine->getManager();

        //Track remaining recurrences if needed and send to Cronofy.
        foreach ($recurrences as $recurrence) {
            //Set the parameters for recurrences based on the parent event.
            $recurrenceParameters = $baseParameters;

            //Get tracking record if we are already tracking this recurrence
            $cronofyEvent = $this->cronofyEventRepo->findOneBy(
                [
                    'calendarOrigin' => $this->origin,
                    'parentEvent' => $event,
                    'recurrenceTime' => $recurrence
                ]
            );

            if ($cronofyEvent && $action = "create") {
                //Skip sending this to cronofy
                //We continue to send create messages for recurring events so we can send future recurrences to cronofy.
                continue;
            }

            //Create a tracking record if we are not already tracking this recurrence
            if (!$cronofyEvent) {
                //Set reminder based on calendar user system configuration.
                $this->configManager->setScopeId($event->getCalendar()->getOwner()->getId());
                $recurrenceParameters['reminders'] = [
                    ['minutes' => (int)$this->configManager->get('dfn_oro_cronofy.reminder')]
                ];

                //Create Cronofy Event record, this is the first time we've synced it
                $cronofyEvent = new CronofyEvent();
                $cronofyEvent->setCalendarOrigin($this->origin);
                $cronofyEvent->setParentEvent($event);
                $cronofyEvent->setRecurrenceTime($recurrence);
                $cronofyEvent->setReminders($recurrenceParameters['reminders']);
                $em->persist($cronofyEvent);
            }

            //Change start, end and event id per each occurrence when sending to cronofy
            $recurrenceParameters['event_id'] = $event->getId() . '_' . $recurrence->getTimestamp();
            $recurrenceParameters['start'] = $recurrence->format(CronofyAPIManager::DATE_FORMAT);
            //Close the recurrence Datetime in order to adjust for end Datetime
            $recurrenceEnd = clone $recurrence;
            $recurrenceParameters['end'] = $recurrenceEnd->add($eventInterval)->format(CronofyAPIManager::DATE_FORMAT);

            //Use the API manager to send the post to create/update an event for the recurrence
            $this->apiManager->createOrUpdateEvent($this->origin, $recurrenceParameters);
        }

        $em->flush();
    }

    /**
     * Handles sending a single event update that is an exception to a recurring event.
     *
     * @param CalendarEvent $event
     * @param $parameters
     */
    private function pushRecurrence(CalendarEvent $event, $parameters)
    {
        $em = $this->doctrine->getManager();

        //Get tracking record if we are already tracking this recurrence
        $cronofyEvent = $this->cronofyEventRepo->findOneBy(
            [
                'calendarOrigin' => $this->origin,
                'parentEvent' => $event->getRecurringEvent(),
                'recurrenceTime' => $event->getOriginalStart()
            ]
        );

        //We've never tracked this event and it's canceled, move along!
        if (!$cronofyEvent && $event->isCancelled()) {
            return;
        }

        //If event is tracked and has been canceled, send to delete handler
        if ($cronofyEvent && $event->isCancelled()) {
            //event has been canceled, remove tracking
            $em->remove($cronofyEvent);

            //send to delete handler
            $event_id = $this->getCronofyEventId($this->origin->getId(), $event->getId());
            $this->pushDeletedEvent(['id' => $event_id, 'origin_id' => $this->origin->getId()]);

            $em->flush();

            return;
        }

        if ($cronofyEvent) {
            //Update tracking if it's not aware of this exception event for the recurrence
            if (!$cronofyEvent->getCalendarEvent()) {
                $cronofyEvent->setCalendarEvent($event);
            }

            //Set reminders based on what's stored in the cronofyEvent record.
            $parameters['reminders'] = $cronofyEvent->getReminders();
        } else {
            //Set reminder based on calendar user system configuration.
            $this->configManager->setScopeId($event->getCalendar()->getOwner()->getId());
            $parameters['reminders'] = [['minutes' => (int)$this->configManager->get('dfn_oro_cronofy.reminder')]];

            //Create new tracking record for this recurrence exception
            $cronofyEvent = new CronofyEvent();
            $cronofyEvent->setCalendarOrigin($this->origin);
            $cronofyEvent->setCalendarEvent($event);
            $cronofyEvent->setParentEvent($event->getRecurringEvent());
            $cronofyEvent->setRecurrenceTime($event->getOriginalStart());
            $cronofyEvent->setReminders($parameters['reminders']);
        }

        $parameters['event_id'] = $event->getRecurringEvent()->getId().'_'.$event->getOriginalStart()->getTimestamp();

        //Use the API manager to send the post to create/update an event for the recurrence
        $this->apiManager->createOrUpdateEvent($this->origin, $parameters);

        $em->persist($cronofyEvent);
        $em->flush();
    }

    /**
     * @param CalendarEvent $event
     */
    private function removeRecurrences(CalendarEvent $event)
    {
        $em = $this->doctrine->getManager();

        //Get all tracked recurrences for this event
        $cronofyEvents = $this->cronofyEventRepo->findBy(
            [
                'calendarOrigin' => $this->origin,
                'parentEvent' => $event,
            ]
        );

        //Remove each recurrences tracking record and send delete request to cronofy.
        foreach ($cronofyEvents as $cronofyEvent) {
            $em->remove($cronofyEvent);

            $message['content']['event_id'] =
                $event->getId() . '_' . $cronofyEvent->getRecurrenceTime()->getTimestamp();

            //Use the API manager to send the call to delete an event
            $this->apiManager->deleteEvent($this->origin, $message['content']);
        }

        $em->flush();
    }

    /**
     * @param CalendarEvent $event
     * @param $parameters
     */
    private function pushEvent(CalendarEvent $event, $parameters)
    {
        $em = $this->doctrine->getManager();

        //Get our cronofy tracking record if there is one
        $cronofyEvent = $this->cronofyEventRepo->findOneBy(
            [
                'calendarOrigin' => $this->origin,
                'calendarEvent' => $this->message['id']
            ]
        );

        //We've never tracked this event and it's canceled, move along!
        if (!$cronofyEvent && $event->isCancelled()) {
            return;
        }

        if ($cronofyEvent && $event->isCancelled()) {
            //event has been canceled, remove tracking
            $em->remove($cronofyEvent);

            //send to delete handler
            $this->pushDeletedEvent(['id' => $event->getId(), 'origin_id' => $this->origin->getId()]);

            $em->flush();

            return;
        }

       //Check if we already have a record of syncing this event with Cronofy. Set correct id and reminders if so.
        if ($cronofyEvent) {
            if (!$cronofyEvent->getCronofyId()) {
                $parameters['event_id'] = $cronofyEvent->getCalendarEvent()->getId();

                //Set reminders based on what's stored in the cronofyEvent record.
                $parameters['reminders'] = $cronofyEvent->getReminders();
            } else {
                //External events are called with 'event_uid' instead of 'event_id'
                unset($parameters['event_id']);
                $parameters['event_uid'] = $cronofyEvent->getCronofyId();
            }
        } else {
            //Set reminder based on calendar user system configuration.
            $this->configManager->setScopeId($event->getCalendar()->getOwner()->getId());
            $parameters['reminders'] = [['minutes' => (int)$this->configManager->get('dfn_oro_cronofy.reminder')]];

            //Create Cronofy Event record, this is the first time we've synced it
            $cronofyEvent = new CronofyEvent();
            $cronofyEvent->setCalendarEvent($event);
            $cronofyEvent->setCalendarOrigin($this->origin);
            $cronofyEvent->setReminders($parameters['reminders']);
            $em->persist($cronofyEvent);
            $em->flush();
        }

        //Use the API manager to send the post to create/update the event
        $this->apiManager->createOrUpdateEvent($this->origin, $parameters);
    }

    /**
     * @param $message
     */
    public function pushDeletedEvent($message)
    {
        $origin = $this->originRepo->find($message['origin_id']);

        //Set event ID based on the event being Internal or External
        if (is_numeric($message['id']) || preg_match('/\d+_\d+/', $message['id'])) {
            $message['content']['event_id'] = $message['id'];
        } else {
            //Tracked by External Cronofy id
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
            if ($cronofyEvent->getCronofyId()) {
                //Return cronofy external id
                return $cronofyEvent->getCronofyId();
            } elseif ($cronofyEvent->getParentEvent()) {
                //This is a recurrence exception, cronofy knows it by the recurrence id
                return $cronofyEvent->getParentEvent()->getId().'_'.$cronofyEvent->getRecurrenceTime()->getTimestamp();
            } else {
                //Normal event, cronofy knows it by the event id.
                return $event_id;
            }
        } else {
            return false;
        }
    }

    /**
     * @param $exceptions
     * @param $recurrences
     * @return array
     */
    private function removeExceptions($exceptions, $recurrences)
    {
        return array_filter($recurrences, function($recurrence) use ($exceptions) {
            $noException = true;
            foreach ($exceptions as $exception) {
                if ($recurrence == $exception->getOriginalStart()) {
                    //An exception matched a recurrence, remove the recurrence
                    $noException = false;
                }
            }
            return $noException;
        });
    }
}
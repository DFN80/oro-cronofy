<?php

namespace Dfn\Bundle\OroCronofyBundle\Manager;

use Dfn\Bundle\OroCronofyBundle\Entity\CalendarOrigin;
use Dfn\Bundle\OroCronofyBundle\Entity\CronofyEvent;
use Dfn\Bundle\OroCronofyBundle\EventListener\CalendarEventListener;

use Doctrine\Common\Persistence\ManagerRegistry;

use Oro\Bundle\CalendarBundle\Entity\Attendee;
use Oro\Bundle\CalendarBundle\Entity\CalendarEvent;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;

/**
 * Class CronofyPullHandler
 * @package Dfn\Bundle\OroCronofyBundle\Manager
 */
class CronofyEventHandler
{
    /** @var ManagerRegistry */
    private $doctrine;

    /** @var CronofyAPIManager  */
    private $apiManager;

    /** @var CalendarEventListener */
    private $calendarEventListener;

    /** @var \Oro\Bundle\CalendarBundle\Entity\Repository\CalendarEventRepository */
    private $eventRepo;

    /** @var \Doctrine\Common\Persistence\ObjectRepository */
    private $originRepo;

    /** @var \Doctrine\Common\Persistence\ObjectRepository  */
    private $cronofyEventRepo;

    /**
     *  Array mapping of Cronofy attendee statuses to Oro statuses
     */
    const STATUSES = [
            'needs_action' => Attendee::STATUS_NONE,
            'accepted' => Attendee::STATUS_ACCEPTED,
            'declined' => Attendee::STATUS_DECLINED,
            'tentative' => Attendee::STATUS_TENTATIVE,
            'unknown' => Attendee::STATUS_NONE
        ];


    /**
     * CronofyEventHandler constructor.
     * @param ManagerRegistry $doctrine
     * @param CronofyAPIManager $apiManager
     * @param CalendarEventListener $calendarEventListener
     */
    public function __construct(
        ManagerRegistry $doctrine,
        CronofyAPIManager $apiManager,
        CalendarEventListener $calendarEventListener
    ) {
        $this->doctrine = $doctrine;
        $this->apiManager = $apiManager;
        $this->calendarEventListener = $calendarEventListener;

        $this->eventRepo = $this->doctrine->getRepository('OroCalendarBundle:CalendarEvent');
        $this->originRepo = $this->doctrine->getRepository('DfnOroCronofyBundle:CalendarOrigin');
        $this->cronofyEventRepo = $this->doctrine->getRepository('DfnOroCronofyBundle:CronofyEvent');
    }


    /**
     * @param $message
     * @throws \Exception
     */
    public function createOrUpdateEvent($message)
    {
        //Stop SyncCeption, listener should not be enabled when our integration is making crud operations.
        $this->calendarEventListener->setEnabled(false);

        //Get the active origin for this calendar.
        $calendarOrigin = $this->originRepo->findOneBy(['calendarId' => $message['calendar_id'], 'isActive' => true]);

        //Throw exception if no active origin found.
        if (!$calendarOrigin) {
            throw new \Exception("No active origin found.");
        }

        //Throw exception if there's an event_id and it's not an internal event id.
        if (isset($message['event_id']) &&
            (!is_numeric($message['event_id']) || !preg_match('/\d+_\d+/', $message['event_id']))
        ) {
            throw new \Exception("Invalid internal event id.");
        }

        //Check to see if this event exist and should be updated or if we need to create it.
        $cronofyEvent = $this->cronofyEventRepo->findOneBy(['cronofyId' => $message['event_uid']]);
        if (isset($message['event_id']) || $cronofyEvent) {
            if ($message['deleted']) {
                //Delete the event and our tracking of it in the cronofy event entity.
                $this->deleteEvent($message, $calendarOrigin);
            } else {
                //Update an existing event from message.
                $this->updateEvent($message, $calendarOrigin);
            }
        } else {
            //Create a new event from message.
            $this->createEvent($message, $calendarOrigin);
        }

        $this->calendarEventListener->setEnabled(true);
    }

    /**
     * @param $message
     * @param CalendarOrigin $calendarOrigin
     */
    protected function updateEvent($message, CalendarOrigin $calendarOrigin)
    {
        $cronofyEvent = $this->getCronofyEvent($message, $calendarOrigin);

        //Check if the updated time doesn't match our cronofyEvent updated time, only process if it doesn't.
        $updatedAt = new \DateTime($message['updated']);
        if ($updatedAt == $cronofyEvent->getUpdatedAt()) {
            return;
        }

        $em = $this->doctrine->getManager();

        if (!$cronofyEvent->getCalendarEvent()) {
            //This is a recurrence we've sent to cronofy that doesn't have an internal event yet.
            //When an update comes for these create a new event as a recurrence of the parent.
            $calendarEvent = new CalendarEvent();
            $calendarEvent->setCalendar($this->getCalendarFromOrigin($calendarOrigin));
            $calendarEvent->setTitle($message['summary']);
            $calendarEvent->setDescription($message['description']);
            $calendarEvent->setStart(new \DateTime($message['start']));
            $calendarEvent->setEnd(new \DateTime($message['end']));
            $calendarEvent->setRecurringEvent($cronofyEvent->getParentEvent());
            $calendarEvent->setOriginalStart($cronofyEvent->getRecurrenceTime());
            if (isset($message['location']['description'])) {
                $calendarEvent->setLocation($message['location']['description']);
            }
            $em->persist($calendarEvent);

            $this->createAttendees($message, $calendarEvent);

            $cronofyEvent->setCalendarEvent($calendarEvent);
        } else {
            $calendarEvent = $cronofyEvent->getCalendarEvent();
            $calendarEvent->setCalendar($this->getCalendarFromOrigin($calendarOrigin));
            $calendarEvent->setTitle($message['summary']);
            $calendarEvent->setDescription($message['description']);
            $calendarEvent->setStart(new \DateTime($message['start']));
            $calendarEvent->setEnd(new \DateTime($message['end']));
            if (isset($message['location']['description'])) {
                $calendarEvent->setLocation($message['location']['description']);
            }

            //Handle attendees only if this is a parent event.
            if (!$calendarEvent->getParent()) {
                $attendees = $calendarEvent->getAttendees();

                //Remove attendees if missing from message
                foreach ($attendees as $attendee) {
                    //Filter the attendees from the message down to the current attendee from the event.
                    $check = array_filter($message['attendees'], function($v) use ($attendee) {
                        return $v['email'] == $attendee->getEmail();
                    });

                    if (!count($check)) {
                        $em->remove($attendee);
                    }
                }

                //Get the calendar owners email address.
                $ownerEmail = $calendarEvent->getCalendar()->getOwner()->getEmail();

                //Create or Update attendees provided in message
                foreach ($message['attendees'] as $attendee) {
                    //Get a matching existing attendee by email
                    $existingAttendee = $calendarEvent->getAttendeeByEmail($attendee['email']);
                    //Don't process attendee if it's the event owner, otherwise create or update the attendee
                    if ($attendee['email'] != $ownerEmail) {
                        if (!$existingAttendee) {
                            //Create new attendee
                            $newAttendee = $this->createAttendee($attendee, $calendarEvent);
                            $em->persist($newAttendee);
                        } else {
                            //Update status for existing attendee if changed.
                            $statusEnum = $this->doctrine
                                ->getRepository(ExtendHelper::buildEnumValueClassName(Attendee::STATUS_ENUM_CODE))
                                ->find(self::STATUSES[$attendee['status']]);
                            if ($existingAttendee->getStatus() != $statusEnum) {
                                $existingAttendee->setStatus($statusEnum);
                                $em->persist($existingAttendee);
                            }
                        }
                    }
                }
            }
        }

        //Set updatedAt time to match cronofy.
        $cronofyEvent->setUpdatedAt($updatedAt);
        $em->persist($cronofyEvent);
        $em->persist($calendarEvent);
        $em->flush();
    }

    /**
     * @param $message
     * @param $calendarOrigin
     */
    protected function createEvent($message, $calendarOrigin)
    {
        $em = $this->doctrine->getManager();

        $calendarEvent = new CalendarEvent();
        $calendarEvent->setCalendar($this->getCalendarFromOrigin($calendarOrigin));
        $calendarEvent->setTitle($message['summary']);
        $calendarEvent->setDescription($message['description']);
        $calendarEvent->setStart(new \DateTime($message['start']));
        $calendarEvent->setEnd(new \DateTime($message['end']));
        if (isset($message['location']['description'])) {
            $calendarEvent->setLocation($message['location']['description']);
        }
        $em->persist($calendarEvent);

        $this->createAttendees($message, $calendarEvent);

        //Create Cronofy Event record to track we've synced this event
        $cronofyEvent = new CronofyEvent();
        $cronofyEvent->setCalendarEvent($calendarEvent);
        $cronofyEvent->setCronofyId($message['event_uid']);
        $cronofyEvent->setCalendarOrigin($calendarOrigin);
        $em->persist($cronofyEvent);


        $em->flush();
    }

    /**
     * @param $message
     * @param CalendarOrigin $calendarOrigin
     */
    protected function deleteEvent($message, CalendarOrigin $calendarOrigin)
    {
        $em = $this->doctrine->getManager();

        $cronofyEvent = $this->getCronofyEvent($message, $calendarOrigin);

        $calendarEvent = $cronofyEvent->getCalendarEvent();

        //If cronofyEvent calendar event is null, create a recurrence exception that is flagged as cancelled
        if (!$cronofyEvent->getCalendarEvent()) {
            $parentEvent = $cronofyEvent->getParentEvent();
            $calendarEvent = new CalendarEvent();
            $calendarEvent->setCalendar($this->getCalendarFromOrigin($calendarOrigin));
            $calendarEvent->setTitle($parentEvent->getTitle());
            $calendarEvent->setDescription($parentEvent->getDescription());
            $calendarEvent->setStart($parentEvent->getStart());
            $calendarEvent->setEnd($parentEvent->getEnd());
            $calendarEvent->setRecurringEvent($parentEvent);
            $calendarEvent->setOriginalStart($cronofyEvent->getRecurrenceTime());
            $calendarEvent->setCancelled(true);
            $em->persist($calendarEvent);
        } elseif ($cronofyEvent->getCalendarEvent()->getRecurringEvent()) {
            //If this is a recurrence, flag as canceled instead of deleting
            $calendarEvent->setCancelled(true);
            $em->persist($calendarEvent);
        } else {
            //Remove the related calendar event
            $em->remove($calendarEvent);
        }

        //Remove record of the synchronization
        $em->remove($cronofyEvent);

        $em->flush();
    }


    /**
     * @param $attendee
     * @param $calendarEvent
     * @return Attendee
     */
    protected function createAttendee($attendee, $calendarEvent)
    {
        //Create new attendee
        $newAttendee = new Attendee();
        $newAttendee->setCalendarEvent($calendarEvent);
        $newAttendee->setEmail($attendee['email']);
        $newAttendee->setDisplayName($attendee['display_name']);
        $statusEnum = $this->doctrine
            ->getRepository(ExtendHelper::buildEnumValueClassName(Attendee::STATUS_ENUM_CODE))
            ->find(self::STATUSES[$attendee['status']]);
        $newAttendee->setStatus($statusEnum);

        return $newAttendee;
    }

    /**
     * @param $message
     * @return CronofyEvent|object
     */
    protected function getCronofyEvent($message, CalendarOrigin $calendarOrigin)
    {
        if (isset($message['event_id'])) {
            //check if this is a recurrence is and not a full event
            if (preg_match('/\d+_\d+/', $message['event_id'])) {
                //Split the string to find the record
                $pieces = explode("_", $message['event_id']);

                $cronofyEvent = $this->cronofyEventRepo->findOneBy(
                    [
                        'calendarOrigin' => $calendarOrigin,
                        'parentEvent' => $pieces[0],
                        'recurrenceTime' => new \DateTime("@$pieces[1]")
                    ]
                );
            } else {
                $cronofyEvent = $this->cronofyEventRepo->findOneBy(['calendarEvent' => $message['event_id']]);
            }
        } else {
            $cronofyEvent = $this->cronofyEventRepo->findOneBy(['cronofyId' => $message['event_uid']]);
        }

        return $cronofyEvent;
    }

    /**
     * @param CalendarOrigin $origin
     * @return null|object|\Oro\Bundle\CalendarBundle\Entity\Calendar
     */
    protected function getCalendarFromOrigin(CalendarOrigin $origin)
    {
        $calendarRepo = $this->doctrine->getRepository("OroCalendarBundle:Calendar");
        $calendar = $calendarRepo->findOneBy(
            [
                "owner" => $origin->getOwner(),
                "organization" => $origin->getOrganization()
            ]
        );

        return $calendar;
    }

    /**
     * @param $message
     * @param CalendarEvent $event
     */
    protected function createAttendees($message, CalendarEvent $event)
    {
        $em = $this->doctrine->getManager();

        //Get the calendar owners email address.
        $ownerEmail = $event->getCalendar()->getOwner()->getEmail();

        //Create new attendee for each attendee in message
        foreach ($message['attendees'] as $attendee) {
            //Don't process attendee if it's the event owner, otherwise create or update the attendee
            if ($attendee['email'] != $ownerEmail) {
                $newAttendee = $this->createAttendee($attendee, $event);
                $em->persist($newAttendee);
            }
        }
    }
}

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
     *  Array mapping of Cronofy atendee statuses to Oro statuses
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
        //Stop SyncCeption
        $this->calendarEventListener->setEnabled(false);

        //Get the active origin for this calendar.
        $calendarOrigin = $this->originRepo->findOneBy(['calendarId' => $message['calendar_id'], 'isActive' => true]);

        //Throw exception if no active origin found.
        if (!$calendarOrigin) {
            throw new \Exception("No active origin found.");
        }

        //Check to see if this event exist and should be updated or if we need to create it.
        $cronofyEvent = $this->cronofyEventRepo->findOneBy(['cronofyId' => $message['event_uid']]);
        if (isset($message['event_id']) || $cronofyEvent) {
            if ($message['deleted']) {
                //Delete the event and our tracking of it in the cronofy event entity.
                $this->deleteEvent($message);
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
        $cronofyEvent = $this->getCronofyEvent($message);

        $em = $this->doctrine->getManager();

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

            //Create or Update attendees provided in message
            foreach ($message['attendees'] as $attendee) {
                //Get a matching existing attendee by email
                $existingAttendee = $calendarEvent->getAttendeeByEmail($attendee['email']);
                if (!$existingAttendee) {
                    //Create new attendee
                    //TODO confirm the attendee is not the record owner.
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

        //Create new attendee for each attendee in message
        foreach ($message['attendees'] as $attendee) {
            //TODO confirm the attendee is not the record owner.
            $newAttendee = $this->createAttendee($attendee, $calendarEvent);
            $em->persist($newAttendee);
        }

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
     */
    protected function deleteEvent($message)
    {
        $em = $this->doctrine->getManager();

        $cronofyEvent = $this->getCronofyEvent($message);

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
    protected function getCronofyEvent($message)
    {
        if (isset($message['event_id'])) {
            $cronofyEvent = $this->cronofyEventRepo->findOneBy(['calendarEvent' => $message['event_id']]);
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
}

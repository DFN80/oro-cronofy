<?php

namespace Dfn\Bundle\OroCronofyBundle\EventListener;

use Dfn\Bundle\OroCronofyBundle\Async\Topics;
use Dfn\Bundle\OroCronofyBundle\Manager\CronofyPushHandler;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;

use Oro\Bundle\CalendarBundle\Entity\Recurrence;
use Oro\Bundle\PlatformBundle\EventListener\OptionalListenerInterface;
use Oro\Bundle\CalendarBundle\Entity\Attendee;
use Oro\Bundle\CalendarBundle\Entity\CalendarEvent;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;

/**
 * Class CalendarEventListener
 * @package AE\Bundle\CampaignBundle\EventListener
 */
class CalendarEventListener implements OptionalListenerInterface
{
    /** @var MessageProducerInterface */
    private $messageProducer;

    /** @var CronofyPushHandler */
    private $cronofyPushHandler;

    /** @var bool */
    protected $enabled = true;

    /** @var array */
    private $createdEvents = [];

    /** @var array */
    private $updatedEvents = [];

    /** @var array */
    private $deletedEvents = [];

    /**
     * CalendarEventListener constructor.
     * @param MessageProducerInterface $messageProducer
     */
    public function __construct(MessageProducerInterface $messageProducer, CronofyPushHandler $cronofyPushHandler)
    {
        $this->messageProducer = $messageProducer;
        $this->cronofyPushHandler = $cronofyPushHandler;
    }

    /**
     * @param OnFlushEventArgs $args
     */
    public function onFlush(OnFlushEventArgs $args)
    {
        if (!$this->enabled) {
            return;
        }

        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        //Get array of created events
        $createdEvents = array_filter(
            $uow->getScheduledEntityInsertions(),
            $this->getEventFilter()
        );

        //Get array of updated events
        $updatedEvents = array_filter(
            $uow->getScheduledEntityUpdates(),
            $this->getEventFilter()
        );

        //Get array of deleted events
        $deletedEvents = array_filter(
            $uow->getScheduledEntityDeletions(),
            $this->getEventFilter()
        );

        //Get array of new attendees
        $createdAttendees = array_filter(
            $uow->getScheduledEntityInsertions(),
            $this->getAttendeeFilter()
        );

        //Get array of deleted attendees
        $deletedAttendees = array_filter(
            $uow->getScheduledEntityDeletions(),
            $this->getAttendeeFilter()
        );

        //Get array of created reccurences
        $createdRecurrences = array_filter(
            $uow->getScheduledEntityInsertions(),
            $this->getRecurrenceFilter()
        );

        //Get array of updated reccurences
        $updatedRecurrences = array_filter(
            $uow->getScheduledEntityUpdates(),
            $this->getRecurrenceFilter()
        );

        //Get array of deleted reccurences
        $deletedRecurrences = array_filter(
            $uow->getScheduledEntityDeletions(),
            $this->getRecurrenceFilter()
        );

        //Build array of created events to be sent to message queue
        foreach ($createdEvents as $entity) {
            //Confirm there's an active calendar origin for created event, no need to send messages if not
            $origin = $this->checkOrigin($em, $entity);
            if (!$origin) {
                continue;
            }

            $this->createdEvents[$entity->getId()] = $entity;
        }

        //Build array of updated events to be sent to message queue
        foreach ($updatedEvents as $entity) {
            //Confirm there's an active calendar origin for updated event, no need to send messages if not
            $origin = $this->checkOrigin($em, $entity);
            if (!$origin) {
                continue;
            }

            $this->updatedEvents[$entity->getId()] = $uow->getEntityChangeSet($entity);

            //Add invites and removals of attendees
            $this->setAttendeeChanges($entity, "invite", $createdAttendees);
            $this->setAttendeeChanges($entity, "remove", $deletedAttendees);

            //Combine all recurrence changes, all we care about it that there was a change.
            $recurrences = array_merge($createdRecurrences, $updatedRecurrences, $deletedRecurrences);

            //Set recurrence change boolean, flags us to remove all tracked recurrences and recreate if needed.
            $this->setRecurrenceChanges($entity, $recurrences);
        }

        //Build array of deleted events to be sent to message queue
        foreach ($deletedEvents as $entity) {
            //Confirm there's an active calendar origin for deleted event, no need to send messages if not
            $origin = $this->checkOrigin($em, $entity);
            if (!$origin) {
                continue;
            }

            //Check if we've previously synced with Cronofy, get proper id if so and include for deleted messages.
            $event_id = $this->cronofyPushHandler->getCronofyEventId($origin, $entity->getId());
            if ($event_id) {
                $this->deletedEvents[] = [
                    'id' => $event_id,
                    'class' => get_class($entity),
                    'origin_id' => $origin->getId()
                ];
            }

            //If deleting the recurring event for a series send if there's any tracked events for it.
            //Add a delete message for each tracked recurrence
            if ($entity->getRecurrence()) {
                $cronofyEventRepo = $em->getRepository('DfnOroCronofyBundle:CronofyEvent');
                //Get all tracked recurrences for this event
                $cronofyEvents = $cronofyEventRepo->findBy(
                    [
                        'calendarOrigin' => $origin,
                        'parentEvent' => $entity,
                    ]
                );

                //Remove each recurrences tracking record and add to deletedEvents.
                foreach ($cronofyEvents as $cronofyEvent) {
                    $em->remove($cronofyEvent);

                    $event_id = $entity->getId() . '_' . $cronofyEvent->getRecurrenceTime()->getTimestamp();

                    $this->deletedEvents[] = [
                        'id' => $event_id,
                        'class' => get_class($entity),
                        'origin_id' => $origin->getId()
                    ];
                }

            }
        }
    }

    /**
     * @param EntityManagerInterface $em
     * @param CalendarEvent $entity
     * @return \Dfn\Bundle\OroCronofyBundle\Entity\CalendarOrigin|object
     */
    public function checkOrigin(EntityManagerInterface $em, CalendarEvent $entity)
    {
        $originRepo = $em->getRepository('DfnOroCronofyBundle:CalendarOrigin');

        //Get the active calendar origin for the event owner.
        $userOwner = $entity->getCalendar()->getOwner();
        $origin = $originRepo->findOneBy(['owner' => $userOwner, 'isActive' => true]);

        return $origin;
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (!$this->enabled) {
            return;
        }

        //Convert event updates to push message format.
        if ($this->updatedEvents) {
            $updatedMessages = $this->getUpdateMessages();
            if (count($updatedMessages)) {
                $this->messageProducer->send(Topics::PUSH_UPDATED_EVENTS, json_encode($updatedMessages));
            }
        }

        if ($this->createdEvents) {
            $this->sendCreateMessages();
        }

        if ($this->deletedEvents) {
            $this->sendDeletedMessages();
        }

        //Clear properties in the case of multiple flush events
        $this->createdEvents = [];
        $this->updatedEvents = [];
    }

    /**
     * @return array
     */
    protected function getUpdateMessages(): array
    {
        $supportedPropertiesMap = [
            'title' => 'summary',
            'description' => 'description',
            'start' => 'start',
            'end' => 'end',
            'location' => 'location',
            'cancelled' => 'cancelled'
        ];
        $messages = [];
        foreach ($this->updatedEvents as $id => $event) {
            $message = [];
            foreach ($event as $property => $value) {
                if (array_key_exists($property, $supportedPropertiesMap) && $value[0] != $value[1]) {
                    //Convert datetime objects to proper string format
                    if ($value[1] instanceof \DateTime) {
                        $message[$supportedPropertiesMap[$property]] = $value[1]->format('Y-m-d\TH:i:s\Z');
                    } else {
                        $message[$supportedPropertiesMap[$property]] = $value[1];
                    }
                }
            }

            //If there's attendee changes then send them along!
            if (isset($event['attendees'])) {
                $message['attendees'] = $event['attendees'];
            }

            //If there's recurrence changes then send them along too!
            if (isset($event['recurrenceChanges'])) {
                $message['recurrenceChanges'] = $event['recurrenceChanges'];
            }

            //Only set message if there's changes to supported properties
            if (count($message) > 0) {
                $messages[] = ['id' => $id, 'content' => $message];
            }

        }
        return $messages;
    }

    public function sendCreateMessages()
    {
        $messages = [];

        foreach ($this->createdEvents as $event) {
            $messages[] = [
                'class' => get_class($event),
                'id' => $event->getId()
            ];
        }

        $this->messageProducer->send(Topics::PUSH_NEW_EVENTS, json_encode($messages));
    }

    public function sendDeletedMessages()
    {

        $this->messageProducer->send(Topics::PUSH_DELETED_EVENTS, json_encode($this->deletedEvents));
    }

    /**
     * @return \Closure
     */
    protected function getEventFilter()
    {
        return function ($entity) {
            return $entity instanceof CalendarEvent;
        };
    }

    /**
     * @return \Closure
     */
    protected function getAttendeeFilter()
    {
        return function ($entity) {
            return $entity instanceof Attendee;
        };
    }

    /**
     * @return \Closure
     */
    protected function getRecurrenceFilter()
    {
        return function ($entity) {
            return $entity instanceof Recurrence;
        };
    }

    /**
     * @param CalendarEvent $entity
     * @param array $attendees
     * @param string $action
     */
    protected function setAttendeeChanges(CalendarEvent $entity, $action, $attendees = [])
    {
        foreach ($attendees as $attendee) {
            //Invite/Remove attendees if they relate to the current event and have an email address.
            if ($attendee->getCalendarEvent()->getId() == $entity->getId() && $attendee->getEmail()) {
                $this->updatedEvents[$entity->getId()]['attendees'][$action][] = [
                    'email' => $attendee->getEmail(),
                    'display_name' => $attendee->getDisplayName()
                ];
            }
        }
    }

    /**
     * @param CalendarEvent $entity
     * @param array $recurrences
     */
    protected function setRecurrenceChanges(CalendarEvent $entity, $recurrences = [])
    {
        foreach ($recurrences as $recurrence) {
            //If the recurrence was deleted or changed flag that in the updatedEvents array for the event.
            if (isset($this->updatedEvents[$entity->getId()]['recurrence']) ||
                ($entity->getRecurrence() && $entity->getRecurrence()->getId() == $recurrence->getId())
            ) {
                $this->updatedEvents[$entity->getId()]['recurrenceChanges'] = 1;
            }
        }
    }

    /**
     * @param boolean $enabled
     */
    public function setEnabled($enabled = true)
    {
        $this->enabled = $enabled;
    }
}

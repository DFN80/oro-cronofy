<?php

namespace Dfn\Bundle\OroCronofyBundle\EventListener;

use Dfn\Bundle\OroCronofyBundle\Async\Topics;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Oro\Bundle\CalendarBundle\Entity\Attendee;
use Oro\Bundle\CalendarBundle\Entity\CalendarEvent;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;

/**
 * Class CalendarEventListener
 * @package AE\Bundle\CampaignBundle\EventListener
 */
class CalendarEventListener
{
    /** @var MessageProducerInterface  */
    private $messageProducer;

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
    public function __construct(MessageProducerInterface $messageProducer)
    {
        $this->messageProducer = $messageProducer;
    }

    /**
     * @param OnFlushEventArgs $args
     */
    public function onFlush(OnFlushEventArgs $args)
    {
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

        //Build array of created events to be sent to message queue
        foreach ($createdEvents as $entity) {
            //Confirm there's an active calendar origin for created event, no need to send messages for those
            if (!$this->checkOrigin($em, $entity)) {
                continue;
            }
            $this->createdEvents[$entity->getId()] = $entity;
        }

        //Build array of updated events to be sent to message queue
        foreach ($updatedEvents as $entity) {
            //Confirm there's an active calendar origin for updated event, no need to send messages for those
            if (!$this->checkOrigin($em, $entity)) {
                continue;
            }

            $this->updatedEvents[$entity->getId()] = $uow->getEntityChangeSet($entity);
            $this->setAttendeeChanges($entity, "invite", $createdAttendees);
            $this->setAttendeeChanges($entity, "remove", $deletedAttendees);
        }

        //Build array of deleted events to be sent to message queue
        foreach ($deletedEvents as $entity) {
            //Confirm there's an active calendar origin for deleted event, no need to send messages for those
            $origin = $this->checkOrigin($em, $entity);
            if (!$origin) {
                continue;
            }

            //Check if external and set external id if so

            $this->deletedEvents[] = [
                'id' => $entity->getId(),
                'class' => get_class($entity),
                'origin_id' => $origin->getId()
            ];
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
        $bob = 1;
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
            'location' => 'location'
        ];
        $messages = [];
        foreach ($this->updatedEvents as $id => $event) {
            $message = [];
            foreach ($event as $property => $value) {
                if (in_array($property, $supportedPropertiesMap) && $value[0] != $value[1]) {
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
}

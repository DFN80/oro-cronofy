<?php

namespace Dfn\Bundle\OroCronofyBundle\Manager;

use Doctrine\Common\Persistence\ManagerRegistry;
use Oro\Bundle\CalendarBundle\Entity\CalendarEvent;

class CronofyPushHandler
{

    /** @var ManagerRegistry */
    private $doctrine;

    /** @var CronofyAPIManager  */
    private $apiManager;

    private $eventRepo;

    private $originRepo;

    public function __construct(ManagerRegistry $doctrine, CronofyAPIManager $apiManager)
    {
        $this->doctrine = $doctrine;
        $this->apiManager = $apiManager;

        $this->eventRepo = $this->doctrine->getRepository('OroCalendarBundle:CalendarEvent');
        $this->originRepo = $this->doctrine->getRepository('DfnOroCronofyBundle:CalendarOrigin');
    }

    public function pushNewEvent($message)
    {
        //Lookup event
        $event = $this->eventRepo->find($message['id']);

        $origin = $this->getOriginByEvent($event);

        //Pull in config setting for reminder time.

        //Invite attendees with emails

        //Build the content to send Cronofy.
        $parameters = [
            'event_id' => $event->getId(),
            'summary' => $event->getTitle(),
            'description' => $event->getDescription(),
            'start' => $event->getStart()->format('Y-m-d\TH:i:s\Z'),
            'end' => $event->getEnd()->format('Y-m-d\TH:i:s\Z'),
            'location' => [
                'description' => $event->getLocation()
            ]
        ];

        //Use the API manager to send the post to create an event
        $this->apiManager->createOrUpdateEvent($origin, $parameters);
    }

    public function pushUpdatedEvent($message)
    {
        $event = $this->eventRepo->find($message['id']);

        //@TODO Check if this event has been sent to cronofy previously. Send whole event if not.

        $origin = $this->getOriginByEvent($event);

        //Set event ID based on the event being Internal or External
        //@TODO use function to determine proper event_id to send.
        $message['content']['event_id'] = $message['id'];

        //Use the API manager to send the post to create an event
        $this->apiManager->createOrUpdateEvent($origin, $message['content']);
    }

    public function pushDeletedEvent($message)
    {
        $origin = $this->originRepo->find($message['origin_id']);

        //Set event ID based on the event being Internal or External
        //@TODO use function to determine proper event_id to send.
        $message['content']['event_id'] = $message['id'];

        //Use the API manager to send the post to create an event
        $this->apiManager->deleteEvent($origin, $message['content']);
    }

    //Return true if this was an event originating externally
    public function isExternal()
    {

    }

    protected function getOriginByEvent(CalendarEvent $event)
    {
        //Get the active calendar origin for the event owner.
        $userOwner = $event->getCalendar()->getOwner();

        //Return the active origin for the user
        return $this->originRepo->findOneBy(['owner' => $userOwner, 'isActive' => true]);
    }
}
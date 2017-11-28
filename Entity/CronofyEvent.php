<?php

namespace Dfn\Bundle\OroCronofyBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\CalendarBundle\Entity\CalendarEvent;

/**
 * Cronofy Event
 *
 * @ORM\Table(name="dfn_cronofy_event",
 *      uniqueConstraints={
 *          @ORM\UniqueConstraint(name="dfn_cronofy_event_origin", columns={"calendar_origin_id", "calendar_event_id"})
 *     }
 * )
 * @ORM\Entity()
 */
class CronofyEvent
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var CalendarEvent
     *
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\CalendarBundle\Entity\CalendarEvent")
     * @ORM\JoinColumn(name="calendar_event_id", onDelete="CASCADE")
     */
    protected $calendarEvent;

    /**
     * @var string
     *
     * @ORM\Column(name="cronofy_id", type="string", length=255, nullable=true)
     */
    protected $cronofyId;

    /**
     * @var CalendarOrigin
     *
     * @ORM\ManyToOne(targetEntity="Dfn\Bundle\OroCronofyBundle\Entity\CalendarOrigin")
     * @ORM\JoinColumn(name="calendar_origin_id")
     */
    protected $calendarOrigin;

    /**
     * @var array
     *
     * @ORM\Column(name="reminders", type="json_array", nullable=true)
     */
    protected $reminders;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated", type="datetime", nullable=true)
     */
    protected $updatedAt;

    /**
     * Get id
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return CalendarEvent
     */
    public function getCalendarEvent(): CalendarEvent
    {
        return $this->calendarEvent;
    }

    /**
     * @param CalendarEvent $calendarEvent
     */
    public function setCalendarEvent(CalendarEvent $calendarEvent)
    {
        $this->calendarEvent = $calendarEvent;
    }

    /**
     * @return string
     */
    public function getCronofyId()
    {
        return $this->cronofyId;
    }

    /**
     * @param string $cronofyId
     */
    public function setCronofyId(string $cronofyId)
    {
        $this->cronofyId = $cronofyId;
    }

    /**
     * @return CalendarOrigin
     */
    public function getCalendarOrigin(): CalendarOrigin
    {
        return $this->calendarOrigin;
    }

    /**
     * @param CalendarOrigin $calendarOrigin
     */
    public function setCalendarOrigin(CalendarOrigin $calendarOrigin)
    {
        $this->calendarOrigin = $calendarOrigin;
    }

    /**
     * @return array
     */
    public function getReminders()
    {
        return $this->reminders;
    }

    /**
     * @param array $reminders
     */
    public function setReminders(array $reminders)
    {
        $this->reminders = $reminders;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt(\DateTime $updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }
}

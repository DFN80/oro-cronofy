<?php

namespace Dfn\Bundle\OroCronofyBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\OrganizationBundle\Entity\OrganizationInterface;

/**
 * Calendar Origin
 *
 * @ORM\Table(name="dfn_calendar_origin",
 *     indexes={
 *          @ORM\Index(name="profile_id_idx", columns={"profile_id"}),
 *          @ORM\Index(name="channel_id_idx", columns={"channel_id"})
 *     })
 * @ORM\Entity(repositoryClass="Dfn\Bundle\OroCronofyBundle\Entity\Repository\CalendarOriginRepository")
 */
class CalendarOrigin
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var boolean
     *
     * @ORM\Column(name="isActive", type="boolean")
     */
    protected $isActive = false;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="synchronized", type="datetime", nullable=true)
     */
    protected $synchronizedAt;

    /**
     * Cronofy limits the end time when reading events so we have to track how far out we've synced.
     *
     * @var \DateTime
     *
     * @ORM\Column(name="synchronized_to", type="datetime", nullable=true)
     */
    protected $synchronizedTo;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\UserBundle\Entity\User", inversedBy="calendarOrigins")
     * @ORM\JoinColumn(name="owner_id", referencedColumnName="id", onDelete="CASCADE", nullable=false)
     */
    protected $owner;

    /**
     * @var OrganizationInterface
     *
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\OrganizationBundle\Entity\Organization")
     * @ORM\JoinColumn(name="organization_id", referencedColumnName="id", onDelete="CASCADE", nullable=false)
     */
    protected $organization;

    /**
     * @var string
     *
     * @ORM\Column(name="access_token", type="string", length=255, nullable=true)
     */
    protected $accessToken;

    /**
     * @var string
     *
     * @ORM\Column(name="refresh_token", type="string", length=255, nullable=true)
     */
    protected $refreshToken;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="access_token_expires_at", type="datetime", nullable=true)
     */
    protected $accessTokenExpiresAt;

    /**
     * @var string
     *
     * @ORM\Column(name="scope", type="string", length=255, nullable=true)
     */
    protected $scope;

    /**
     * @var string
     *
     * @ORM\Column(name="provider_name", type="string", length=255, nullable=true)
     */
    protected $providerName;

    /**
     * @var string
     *
     * @ORM\Column(name="profile_id", type="string", length=255, nullable=true)
     */
    protected $profileId;

    /**
     * @var string
     *
     * @ORM\Column(name="profile_name", type="string", length=255, nullable=true)
     */
    protected $profileName;

    /**
     * @var string
     *
     * @ORM\Column(name="calendar_name", type="string", length=255, nullable=false)
     */
    protected $calendarName;

    /**
     * @var string
     *
     * @ORM\Column(name="calendar_id", type="string", length=255, nullable=false)
     */
    protected $calendarId;

    /**
     * @var string
     *
     * @ORM\Column(name="channel_id", type="string", length=255, nullable=true)
     */
    protected $channelId;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Indicate whether this calendar origin is in active state or not
     *
     * @return boolean
     */
    public function isActive()
    {
        return $this->isActive;
    }

    /**
     * Set this calendar origin in active/inactive state
     *
     * @param boolean $isActive
     *
     * @return CalendarOrigin
     */
    public function setActive($isActive)
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * Get date/time when events from this origin were synchronized
     *
     * @return \DateTime
     */
    public function getSynchronizedAt()
    {
        return $this->synchronizedAt;
    }

    /**
     * Set date/time when events from this origin were synchronized
     *
     * @param \DateTime $synchronizedAt
     *
     * @return CalendarOrigin
     */
    public function setSynchronizedAt($synchronizedAt)
    {
        $this->synchronizedAt = $synchronizedAt;

        return $this;
    }

    /**
     * Get date/time when events from this origin were synchronized to
     *
     * @return \DateTime
     */
    public function getSynchronizedTo()
    {
        return $this->synchronizedAt;
    }

    /**
     * Set date/time when events from this origin were synchronized to
     *
     * @param \DateTime $synchronizedTo
     *
     * @return CalendarOrigin
     */
    public function setSynchronizedTo($synchronizedTo)
    {
        $this->synchronizedTo = $synchronizedTo;

        return $this;
    }

    /**
     * @return OrganizationInterface
     */
    public function getOrganization()
    {
        return $this->organization;
    }

    /**
     * @param OrganizationInterface $organization
     *
     * @return $this
     */
    public function setOrganization(OrganizationInterface $organization = null)
    {
        $this->organization = $organization;

        return $this;
    }

    /**
     * @return User
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param User $user
     *
     * @return $this
     */
    public function setOwner($user)
    {
        $this->owner = $user;

        return $this;
    }

    /**
     * @return string
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * @param string $accessToken
     *
     * @return CalendarOrigin
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * @return string
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * @param string $refreshToken
     *
     * @return CalendarOrigin
     */
    public function setRefreshToken($refreshToken)
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getAccessTokenExpiresAt()
    {
        return $this->accessTokenExpiresAt;
    }

    /**
     * @param \DateTime $datetime
     *
     * @return CalendarOrigin
     */
    public function setAccessTokenExpiresAt(\DateTime $datetime = null)
    {
        $this->accessTokenExpiresAt = $datetime;

        return $this;
    }

    /**
     * @return string
     */
    public function getScope(): string
    {
        return $this->scope;
    }

    /**
     * @param string $scope
     */
    public function setScope(string $scope)
    {
        $this->scope = $scope;
    }

    /**
     * @return string
     */
    public function getProviderName(): string
    {
        return $this->providerName;
    }

    /**
     * @param string $providerName
     */
    public function setProviderName(string $providerName)
    {
        $this->providerName = $providerName;
    }

    /**
     * @return string
     */
    public function getProfileId(): string
    {
        return $this->profileId;
    }

    /**
     * @param string $profileId
     */
    public function setProfileId(string $profileId)
    {
        $this->profileId = $profileId;
    }

    /**
     * Get profile name
     */
    public function getProfileName()
    {
        return $this->profileName;
    }

    /**
     * Set profile name
     *
     * @param string $name
     *
     * @return $this
     */
    public function setProfileName($name)
    {
        $this->profileName = $name;

        return $this;
    }

    /**
     * Get calendar name
     */
    public function getCalendarName()
    {
        return $this->calendarName;
    }

    /**
     * Set calendar name
     *
     * @param string $name
     *
     * @return $this
     */
    public function setCalendarName($name)
    {
        $this->calendarName = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getCalendarId(): string
    {
        return $this->calendarId;
    }

    /**
     * @param string $calendarId
     */
    public function setCalendarId(string $calendarId)
    {
        $this->calendarId = $calendarId;
    }

    /**
     * @return string
     */
    public function getChannelId(): string
    {
        return $this->channelId;
    }

    /**
     * @param string $channelId
     */
    public function setChannelId(string $channelId = null)
    {
        $this->channelId = $channelId;
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        $providerInfo = "";
        if ($this->providerName) {
            $providerInfo = " ($this->providerName";
            if ($this->profileName) {
                $providerInfo .= " - $this->profileName)";
            } else {
                $providerInfo = ")";
            }
        }

        return $this->calendarName . $providerInfo;
    }
}

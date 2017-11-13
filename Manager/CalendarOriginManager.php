<?php

namespace Dfn\Bundle\OroCronofyBundle\Manager;

use Dfn\Bundle\OroCronofyBundle\Entity\CalendarOrigin;

use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;

class CalendarOriginManager
{

    /** @vay CronofyOauth2Manager */
    private $oauthManager;

    /** @var CronofyAPIManager  */
    private $apiManager;

    /** @var ManagerRegistry  */
    private $doctrine;

    public function __construct(
        CronofyOauth2Manager $oauthManager,
        CronofyAPIManager $apiManager,
        ManagerRegistry $doctrine
    ) {
        $this->oauthManager = $oauthManager;
        $this->apiManager = $apiManager;
        $this->doctrine = $doctrine;
    }


    public function createOrUpdateOrigin(Request $request, $primaryId = null)
    {
        $em = $this->doctrine->getManager();

        $code = $request->query->get('code');

        //Request access and refresh token from Cronofy using returned code.
        $response = $this->oauthManager->getAccessTokenByAuthCode($code);

        //Check for existing origin with the current profile_id
        $calendarOrigin = $em->getRepository('DfnOroCronofyBundle:CalendarOrigin')
            ->findOneBy(['profileId' => $response['profile_id']]);

        if ($calendarOrigin) {
            //Re-activate existing origin
            $calendarOrigin->setActive(true);
        } else {
            //Create new origin entity
            $calendarOrigin = new CalendarOrigin();
            $calendarOrigin->setOwner($this->getUser());
            $calendarOrigin->setOrganization($this->getUser()->getOrganization());
            $calendarOrigin->setScope($response['scope']);
            $calendarOrigin->setProviderName($response['provider_name']);
            $calendarOrigin->setProfileId($response['profile_id']);
            $calendarOrigin->setProfileName($response['profile_name']);
        }

        $calendarOrigin->setAccessToken($response['access_token']);
        $calendarOrigin->setRefreshToken($response['refresh_token']);
        $calendarOrigin->setAccessTokenExpiresAt(
            new \DateTime('+'.((int)$response['expires_in'] - 5).' seconds', new \DateTimeZone('UTC'))
        );

        //Get array of calendars
        $calendars = $this->apiManager->getCalendars($calendarOrigin);
        //Get primary calendar if cronofy lists one
        $primaryCalendar = $this->apiManager->getPrimaryCalendar($calendars, $primaryId);

        if (!$primaryCalendar) {
            //TODO: If primary calendar is false then prompt user to select a calendar. and return to this route with it
        } else {
            $calendarOrigin->setCalendarName($primaryCalendar['calendar_name']);
            $calendarOrigin->setCalendarId($primaryCalendar['calendar_id']);

            $em->persist($calendarOrigin);
            $em->flush();

            //TODO: Figure out when and how we want to kick off the initial sync.

            return $calendarOrigin;
        }
    }
}








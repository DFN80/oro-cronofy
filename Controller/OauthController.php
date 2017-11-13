<?php

namespace Dfn\Bundle\OroCronofyBundle\Controller;

use Dfn\Bundle\OroCronofyBundle\Manager\CronofyOauth2Manager;
use Dfn\Bundle\OroCronofyBundle\Entity\CalendarOrigin;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\VarDumper\VarDumper;

/**
 * Class OauthController
 * @package Dfn\Bundle\OroCronofyBundle\Controller
 */
class OauthController extends Controller
{
    /**
     * @Route("/oauth/connect", name="dfn_oro_cronofy_oauth_connect")
     * @Template
     * @param $request Request
     * @return array
     */
    public function connectAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $code = $request->query->get('code');
        $origin = $request->getSchemeAndHttpHost();

        //Check that a valid csrf token was sent in the state parameter of the request.
        $csrfToken = $request->query->get('state');
        if (!$this->isCsrfTokenValid(CronofyOauth2Manager::NAME, $csrfToken)) {
            throw new HttpException("Invalid Request");
        }

        //Request access and refresh token from Cronofy using returned code.
        $cronofyOauth = $this->get('dfn_oro_cronofy.cronofy_oauth2_manager');
        $response = $cronofyOauth->getAccessTokenByAuthCode($code);

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
            $calendarOrigin->setAccessToken($response['access_token']);
            $calendarOrigin->setRefreshToken($response['refresh_token']);
            $calendarOrigin->setAccessTokenExpiresAt(
                new \DateTime('+'.((int)$response['expires_in'] - 5).' seconds', new \DateTimeZone('UTC'))
            );
            //$calendarOrigin->($results['access_token']); ACCOUNT ID
            $calendarOrigin->setAccessToken($response['access_token']);
            $calendarOrigin->setScope($response['scope']);
            $calendarOrigin->setProviderName($response['provider_name']);
            $calendarOrigin->setProfileId($response['profile_id']);
            $calendarOrigin->setProfileName($response['profile_name']);
        }

        //Get users primary calendar
        //User API service to get users primary calendar
        $cronofyApi = $this->get('dfn_oro_cronofy.cronofy_api_manager');
        //Get array of calendars
        $calendars = $cronofyApi->getCalendars($calendarOrigin);
        $primaryCalendar = $cronofyApi->getPrimaryCalendar($calendars);

        //TODO: If primary calendar is false then prompt user to select a calendar.

        $calendarOrigin->setCalendarName($primaryCalendar['calendar_name']);
        $calendarOrigin->setCalendarId($primaryCalendar['calendar_id']);

        $em->persist($calendarOrigin);
        $em->flush();

        //TODO: Figure out when and how we want to kick off the initial sync.

        return ['calendarOrigin' => $calendarOrigin, 'origin' => $origin];
    }

    /**
     * @Route("/oauth/disconnect", name="dfn_oro_cronofy_oauth_disconnect")
     * @Template
     * @param $request Request
     * @return array
     */
    public function disconnectAction(Request $request)
    {
        VarDumper::dump('test');
        die;
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('DfnOroCronofyBundle:CalendarOrigin');
        //Load active calendar origin for current user if one
        $calendarOrigin = $repo->findOneBy(
            [
                'owner' => $this->getUser(),
                'isActive' => true
            ]
        );

        //Set users currently active calendar origin to not active
        $calendarOrigin->setActive(false);
        $em->persist($calendarOrigin);
        $em->flush();

        return ['calendarOrigin' => $calendarOrigin];
    }
}

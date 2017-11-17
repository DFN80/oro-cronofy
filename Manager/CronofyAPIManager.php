<?php

namespace Dfn\Bundle\OroCronofyBundle\Manager;

use Buzz\Exception\RequestException;
use Buzz\Message\MessageInterface;
use Buzz\Client\Curl;
use Buzz\Message\Request;
use Buzz\Message\RequestInterface;
use Buzz\Message\Response;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use Dfn\Bundle\OroCronofyBundle\Entity\CalendarOrigin;

/**
 * Class CronofyAPIManager
 * @package Dfn\Bundle\OroCronofyBundle\Manager
 */
class CronofyAPIManager
{

    const ROOT_PATH = 'https://api.cronofy.com';
    const API_VERSION = 'v1';
    const CALENDAR_PATH = 'calendars';
    const EVENTS_PATH = 'events';

    /** @var Curl */
    protected $httpClient;

    /** @var ConfigManager */
    protected $configManager;

    /** @var CsrfTokenManagerInterface */
    private $csrfTokenManager;

    /** @var RouterInterface */
    private $router;

    /** @vay CronofyOauth2Manager */
    private $oauthManager;

    /**
     * @param ConfigManager $configManager
     * @param CsrfTokenManagerInterface $csrfTokenManager
     * @param RouterInterface $router
     * @param CronofyOauth2Manager $oauth2Manager
     */
    public function __construct(
        ConfigManager $configManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        RouterInterface $router,
        CronofyOauth2Manager $oauth2Manager
    ) {
        $this->httpClient = new Curl();
        $this->configManager = $configManager;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->router = $router;
        $this->oauthManager = $oauth2Manager;
    }


    /**
     * @param CalendarOrigin $origin
     *
     * @return array
     */
    public function getCalendars(CalendarOrigin $origin) : array
    {
        $path = self::ROOT_PATH . '/' . self::API_VERSION . '/' . self::CALENDAR_PATH;

        $response = $this->doHttpRequest($origin, $path, RequestInterface::METHOD_GET);

        //Filter array to calendars only for the current origins profile id.
        $profileId = $origin->getProfileId();
        $calendars = array_filter($response['calendars'], function ($calendar) use ($profileId) {
            return ($calendar['profile_id'] == $profileId);
        });

        return $calendars;
    }

    /**
     * @param array $calendars
     * @param string $primaryId
     * @return mixed
     */
    public function getPrimaryCalendar(Array $calendars, $primaryId)
    {
        if ($primaryId) {
            //Get primary calendar based on passed primaryId
            $primaryCalendar = array_filter($calendars, function ($calendar) use ($primaryId) {
                return ($calendar['calendar_id'] == $primaryId);
            });
        } else {
            //Get primary calendar based on cronofy listed primary
            $primaryCalendar = array_filter($calendars, function ($calendar) {
                return ($calendar['calendar_primary']);
            });
        }

        if (count($primaryCalendar) > 1 || count($primaryCalendar) === 0) {
            //Return false value when there's more then 1 primary or no primary, the user will need to make a selection.
            return false;
        }

        //Return the primary calendar
        return reset($primaryCalendar);

    }

    public function createOrUpdateEvent(CalendarOrigin $calendarOrigin, $parameters)
    {
        $path = self::ROOT_PATH . '/' . self::API_VERSION . '/' . self::CALENDAR_PATH . '/'
            . $calendarOrigin->getCalendarId() . '/' . self::EVENTS_PATH;

        return $this->doHttpRequest($calendarOrigin, $path, RequestInterface::METHOD_POST, $parameters);

    }

    public function deleteEvent(CalendarOrigin $calendarOrigin, $parameters)
    {
        $path = self::ROOT_PATH . '/' . self::API_VERSION . '/' . self::CALENDAR_PATH . '/'
            . $calendarOrigin->getCalendarId() . '/' . self::EVENTS_PATH;

        return $this->doHttpRequest($calendarOrigin, $path, RequestInterface::METHOD_DELETE, $parameters);

    }

    /**
     * @param CalendarOrigin $origin
     * @param string $path
     * @param string $method
     * @param array $parameters
     *
     * @return array
     */
    protected function doHttpRequest(CalendarOrigin $origin, $path, $method, $parameters = [])
    {
        $request = new Request($method, $path);
        $response = new Response();

        //Only set content and content headers for requests with parameters.
        if (!empty($parameters)) {
            $content = json_encode($parameters);
            $headers = [
                'Content-length: ' . strlen($content),
                'content-type: application/json; charset=utf-8',
            ];
            $request->setContent($content);
        }

        $headers[] = 'Authorization: Bearer '.$this->oauthManager->getAccessTokenWithCheckingExpiration($origin);

        $request->setHeaders($headers);

        $this->httpClient->send($request, $response);

        if (!$response->isSuccessful()) {
            throw new RequestException('Cronofy API Call Failed.');
        }

        return $this->getResponseContent($response);
    }

    /**
     * Get the 'parsed' content based on the response headers.
     *
     * @param MessageInterface $rawResponse
     *
     * @return array
     */
    protected function getResponseContent(MessageInterface $rawResponse)
    {
        $content = $rawResponse->getContent();
        if (!$content) {
            return [];
        }

        return json_decode($content, true);
    }
}
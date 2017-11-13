<?php

namespace Dfn\Bundle\OroCronofyBundle\Manager;

use Buzz\Message\MessageInterface;
use Buzz\Client\Curl;
use Buzz\Message\Request;
use Buzz\Message\RequestInterface;
use Buzz\Message\Response;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\VarDumper\VarDumper;

use Dfn\Bundle\OroCronofyBundle\Entity\CalendarOrigin;
use Dfn\Bundle\OroCronofyBundle\Manager\CronofyOauth2Manager;

/**
 * Class CronofyAPIManager
 * @package Dfn\Bundle\OroCronofyBundle\Manager
 */
class CronofyAPIManager
{

    const ROOT_PATH = 'https://api.cronofy.com';
    const API_VERSION = 'v1';
    const CALENDAR_PATH = 'calendars';

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

    //Put me some cronofy oauth manager here to get tokens

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
     * @return mixed
     */
    public function getPrimaryCalendar(Array $calendars)
    {
        $primaryCalendar = array_filter($calendars, function ($calendar) {
            return ($calendar['calendar_primary']);
        });

        if (count($primaryCalendar) > 1 || count($primaryCalendar) === 0) {
            //Return false value when there's more then 1 primary or no primary, the user will need to make a selection.
            VarDumper::dump('more then 1 or no primary found');
            return false;
        }

        //Return the calendar marked as primary in the calendars array
        return reset($primaryCalendar);

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

        $content = json_encode($parameters);
        $headers = [
//            'Content-length: ' . strlen($content),
//            'content-type: application/json; charset=utf-8',
            'Authorization: Bearer '.$this->oauthManager->getAccessTokenWithCheckingExpiration($origin)
        ];

        $request->setHeaders($headers);
//        $request->setContent($content);
        VarDumper::dump($request);

        $this->httpClient->send($request, $response, [CURLOPT_TIMEOUT => '10']);

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
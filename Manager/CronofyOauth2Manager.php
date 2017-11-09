<?php

namespace Dfn\Bundle\OroCronofyBundle\Manager;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManager;

use Buzz\Message\MessageInterface;
use Buzz\Client\ClientInterface;
use Buzz\Client\Curl;
use Buzz\Message\Request;
use Buzz\Message\RequestInterface;
use Buzz\Message\Response;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\ImapBundle\Entity\UserEmailOrigin;
use Oro\Bundle\ImapBundle\Exception\RefreshOAuthAccessTokenFailureException;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\VarDumper\VarDumper;

/**
 * Class CronofyOauth2Manager
 * @package Dfn\Bundle\OroCronofyBundle\Manager
 */
class CronofyOauth2Manager
{

    const OAUTH2_ACCESS_TOKEN_URL = 'https://api.cronofy.com/oauth/token';
    const OAUTH2_AUTHORIZATION_URL = '//app.cronofy.com/oauth/authorize';
    const OAUTH2_SCOPE =
        'create_calendar read_events create_event delete_event read_free_busy change_participation_status';
    const RETRY_TIMES = 3;
    const NAME = 'cronofy';

    /** @var Curl */
    protected $httpClient;

    /** @var ConfigManager */
    protected $configManager;

    /** @var ManagerRegistry */
    private $doctrine;

    /** @var CsrfTokenManagerInterface */
    private $csrfTokenManager;

    /** @var RouterInterface */
    private $router;

    /** @var string */
    protected $state;

    /** @var string */
    protected $clientId;

    /** @var string */
    protected $clientSecret;

    /**
     * @param ClientInterface $httpClient
     * @param ConfigManager $configManager
     * @param ManagerRegistry $doctrine
     * @param CsrfTokenManagerInterface $csrfTokenManager
     * @param RouterInterface $router
     */
    public function __construct(
        ClientInterface $httpClient,
        ConfigManager $configManager,
        ManagerRegistry $doctrine,
        CsrfTokenManagerInterface $csrfTokenManager,
        RouterInterface $router
    ) {
        $this->httpClient = $httpClient;
        $this->configManager = $configManager;
        $this->doctrine = $doctrine;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->router = $router;

        $this->clientId = $this->configManager->get('dfn_oro_cronofy.client_id');
        $this->clientSecret = $this->configManager->get('dfn_oro_cronofy.client_secret');
    }

    /**
     * @param array $extraParameters
     * @return string
     */
    public function getAuthorizationUrl(array $extraParameters = [])
    {
        $this->state = $this->csrfTokenManager->getToken(self::NAME)->getValue();

        $parameters = array_merge([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'scope' => self::OAUTH2_SCOPE,
            'state' => urlencode($this->state),
            'redirect_uri' =>
                $this->router->generate('dfn_oro_cronofy_oauth', [], RouterInterface::ABSOLUTE_URL),
        ], $extraParameters);

        return $this->normalizeUrl(self::OAUTH2_AUTHORIZATION_URL, $parameters);
    }

    /**
     * @param string $url
     * @param array  $parameters
     *
     * @return string
     */
    protected function normalizeUrl($url, array $parameters = [])
    {
        $normalizedUrl = $url;
        if (!empty($parameters)) {
            $normalizedUrl .= (false !== strpos($url, '?') ? '&' : '?').http_build_query($parameters, '', '&');
        }

        return $normalizedUrl;
    }

    /**
     * @param string $code
     *
     * @return array
     */
    public function getAccessTokenByAuthCode($code)
    {
        $parameters = [
            'redirect_uri' =>
                $this->router->generate('dfn_oro_cronofy_oauth', [], RouterInterface::ABSOLUTE_URL),
            'code' => $code,
            'grant_type' => 'authorization_code'
        ];

        $attemptNumber = 0;
        do {
            $attemptNumber++;
            $response = $this->doHttpRequest($parameters);
            VarDumper::dump($response);

            $result = [
                'access_token' => empty($response['access_token']) ? '' : $response['access_token'],
                'refresh_token' => empty($response['refresh_token']) ? '' : $response['refresh_token'],
                'expires_in' => empty($response['expires_in']) ? '' : $response['expires_in'],
                'scope' => empty($response['scope']) ? '' : $response['scope'],
                'account_id' => empty($response['account_id']) ? '' : $response['account_id'],
                'provider_name' =>
                    empty($response['linking_profile']['provider_name']) ? '' : $response['linking_profile']['provider_name'],
                'profile_id' =>
                    empty($response['linking_profile']['profile_id']) ? '' : $response['linking_profile']['profile_id'],
                'profile_name' =>
                    empty($response['linking_profile']['profile_name']) ? '' : $response['linking_profile']['profile_name']
            ];
        } while ($attemptNumber <= self::RETRY_TIMES && empty($result['access_token']));

        return $result;
    }

    /**
     * @param array $parameters
     *
     * @return array
     */
    protected function doHttpRequest($parameters)
    {
        $request = new Request(RequestInterface::METHOD_POST, self::OAUTH2_ACCESS_TOKEN_URL);
        $response = new Response();

        $contentParameters = [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
        ];

        $parameters = array_merge($contentParameters, $parameters);
//        $content = http_build_query($parameters, '', '&');
        $content = json_encode($parameters);
        $headers = [
            'Content-length: ' . strlen($content),
            'content-type: application/json',
            'user-agent: oro-oauth'
        ];

        $request->setHeaders($headers);
        $request->setContent($content);
        VarDumper::dump($request);

        $this->httpClient->send($request, $response);

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
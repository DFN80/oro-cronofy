<?php

namespace Dfn\Bundle\OroCronofyBundle\Controller;

use Dfn\Bundle\OroCronofyBundle\Manager\CronofyOauth2Manager;
use Dfn\Bundle\OroCronofyBundle\Async\Topics;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        //Check that a valid csrf token was sent in the state parameter of the request.
        $csrfToken = $request->query->get('state');
        if (!$this->isCsrfTokenValid(CronofyOauth2Manager::NAME, $csrfToken)) {
            throw new HttpException("Invalid Request");
        }

        $origin = $request->getSchemeAndHttpHost();
        $cronofyOauth = $this->get('dfn_oro_cronofy.calendar_origin_manager');
        $calendarOrigin = $cronofyOauth->createOrUpdateOrigin($request);

        return ['calendarOrigin' => $calendarOrigin, 'origin' => $origin];
    }

    /**
     * @Route("/oauth/elevate", name="dfn_oro_cronofy_oauth_elevate")
     * @param $request Request
     * @return Response
     */
    public function elevateAction(Request $request)
    {
        //Get Calendar Origin from id
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('DfnOroCronofyBundle:CalendarOrigin');
        $calendarOrigin = $repo->find($request->query->get('originId'));

        //Call the elevate url API
        $apiManager = $this->get('dfn_oro_cronofy.cronofy_api_manager');
        $response = $apiManager->getElevateUrl($calendarOrigin);

        if (isset($response['permissions_request']['url'])) {
            //If a URL is returned then redirect to that
            return $this->redirect($response['permissions_request']['url']);
        } elseif (isset($response['premissions_request']['accepted'])) {
            //Else if an accepted response came back forward to elevateCompleteAction.
            return $this->forward(
                "DfnOroCronofyBundle:Oauth:elevateComplete",
                [],
                ["originId" => $calendarOrigin->getId()]
            );
        }

        //Error we got something we didn't expect
        throw new HttpException("500");
    }

    /**
     * @Route("/oauth/elevate/complete", name="dfn_oro_cronofy_oauth_elevate_complete")
     * @Template
     * @param $request Request
     * @return array
     */
    public function elevateCompleteAction(Request $request)
    {
        $origin = $request->getSchemeAndHttpHost();

        if ($request->query->get('error')) {
            return ["response" => "error", 'origin' => $origin];
        }

        //Get Calendar Origin from id
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('DfnOroCronofyBundle:CalendarOrigin');
        $calendarOrigin = $repo->find($request->query->get('originId'));

        //Set calendar origin as active now that we have elevated permissions.
        $calendarOrigin->setActive(true);
        $em->persist($calendarOrigin);
        $em->flush();

        //TODO trigger initial sync job

        return ["response" => "success", 'calendarOrigin' => $calendarOrigin, 'origin' => $origin];
    }

    /**
     * @Route("/oauth/disconnect/{id}", name="dfn_oro_cronofy_oauth_disconnect")
     * @param $id integer
     * @return JsonResponse
     */
    public function disconnectAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('DfnOroCronofyBundle:CalendarOrigin');
        $calendarOrigin = $repo->find($id);

        //Close notification Channel
        $apiManager = $this->get('dfn_oro_cronofy.cronofy_api_manager');
        $apiManager->closeChannel($calendarOrigin);
        $calendarOrigin->setChannelId(null);

        //Set users currently active calendar origin to not active
        $calendarOrigin->setActive(false);
        $em->persist($calendarOrigin);
        $em->flush();

        return new JsonResponse(['identifier' => $calendarOrigin->getIdentifier()]);
    }

    /**
     * @Route("/notification", name="dfn_oro_cronofy_notification")
     *
     * @return Response
     */
    public function notificationAction(Request $request)
    {
        //Get the oro configuation service and the stored client secret
        $configManager = $this->get('oro_config.global');
        $clientSecret = $configManager->get('dfn_oro_cronofy.client_secret');

        $hash = $request->headers->get('Cronofy-HMAC-SHA256');

        $calculatedHash = base64_encode(hash_hmac("sha256", $request->getContent(), $clientSecret, true));

        //Check the Cronofy-HMAC-SHA256 value against our calculation for the same hash
        if ($hash !== $calculatedHash) {
            throw new HttpException("401");
        }

        //Get content of request from Cronofy.
        $messageProducer = $this->get('oro_message_queue.message_producer');

        //Get type of notification from content
        $type = json_decode($request->getContent(), true)['notification']['type'];

        //Send message to queue if the type if not verification.
        if ($type != 'verification') {
            $messageProducer->send(Topics::NOTIFICATION, $request->getContent());
        }

        //Send back empty response.
        return new Response();
    }
}

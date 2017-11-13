<?php

namespace Dfn\Bundle\OroCronofyBundle\Controller;

use Dfn\Bundle\OroCronofyBundle\Manager\CronofyOauth2Manager;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
     * @Route("/oauth/disconnect/{id}", name="dfn_oro_cronofy_oauth_disconnect")
     * @param $id integer
     * @return JsonResponse
     */
    public function disconnectAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('DfnOroCronofyBundle:CalendarOrigin');
        $calendarOrigin = $repo->find($id);

        //Set users currently active calendar origin to not active
        $calendarOrigin->setActive(false);
        $em->persist($calendarOrigin);
        $em->flush();

        return new JsonResponse(['identifier' => $calendarOrigin->getIdentifier()]);
    }
}

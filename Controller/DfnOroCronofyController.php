<?php

namespace Dfn\Bundle\OroCronofyBundle\Controller;

use Dfn\Bundle\OroCronofyBundle\Manager\CronofyOauth2Manager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\VarDumper\VarDumper;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class DfnOroCronofyController
 * @package Dfn\Bundle\OroCronofyBundle\Controller
 */
class DfnOroCronofyController extends Controller
{
    /**
     * @Route("/oauth", name="dfn_oro_cronofy_oauth")
     * @param $request Request
     * @return Response
     */
    public function oauthAction(Request $request)
    {
        $code = $request->query->get('code');

        //Check that a valid csrf token was sent in the state parameter of the request.
        $csrfToken = $request->query->get(CronofyOauth2Manager::NAME);
        if (!$this->isCsrfTokenValid('cronofy', $csrfToken)) {
            throw new HttpException("Invalid Request");
        }

        //Request access and refresh token from Cronofy using returned code.
        $cronofyOauth = $this->get('dfn_oro_cronofy.cronofy_oauth2_manager');
        $results = $cronofyOauth->getAccessTokenByAuthCode($code);

        //Get users primary calendar

        //Create and persist a calendar origin entity

        VarDumper::dump($request);die;


        //Send users access and refresh tokens back to the user configuration page using postMessage.
        return new Response("<script>window.opener.postMessage('$code', '$origin');window.close();</script>");
    }
}

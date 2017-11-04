<?php

namespace Dfn\Bundle\OroCronofyBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
        //Use the code to create a request for the users access and refresh tokens.
        //Need to create a API service to connect to cronofy API and use that.
        $code = $request->query->get('code');
        $state = $request->query->get('state');

        return new Response("<script>window.opener.postMessage('$code', '$state');window.close();</script>");
    }
}

<?php

namespace Dfn\Bundle\OroCronofyBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        //Check that a valid csrf token was sent in the state parameter of the request.
        $csrfToken = $request->query->get('state');
        if (!$this->isCsrfTokenValid('cronofy', $csrfToken)) {
            throw new HttpException("Invalid Request");
        }

        //Use the code to create a request for the users access and refresh tokens.
        //Need to create a API service to connect to cronofy API and use that.
        $code = $request->query->get('code');
        $origin = $request->getSchemeAndHttpHost();

        return new Response("<script>window.opener.postMessage('$code', '$origin');window.close();</script>");
    }
}

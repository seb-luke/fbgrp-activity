<?php

namespace App\Controller;

use App\Services\FacebookApiService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class BaseController extends Controller
{

    /**
     * @Route("/", name="index")
     */
    public function index()
    {
        return $this->redirectToRoute('login');
    }

    /**
     * @Route("/login", name="login")
     * @param FacebookApiService $fbService
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function login(FacebookApiService $fbService)
    {
        $helper = $fbService->getFacebookObject()->getRedirectLoginHelper();
        $loginUrl = $helper->getLoginUrl($this->generateUrl("loggedIn", [], UrlGeneratorInterface::ABSOLUTE_URL)
            , ["email", "public_profile", "user_birthday"]
        );

        return $this->render("App/index.html.twig", ["loginUrl" => $loginUrl]);
    }

    /**
     * @Route("/loggedin", name="loggedIn")
     * @param FacebookApiService $fbService
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function loggedIn(FacebookApiService $fbService)
    {
        $fbService->getAuthTokenAfterRedirect();
        return $this->redirectToRoute('warriorsIndex');
    }
}

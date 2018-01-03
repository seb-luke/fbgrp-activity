<?php

namespace App\Controller;

use App\Utils\FacebookUtils;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
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
        $helper = FacebookUtils::getFacebookObject()->getRedirectLoginHelper();
        $loginUrl = $helper->getLoginUrl($this->generateUrl("loggedIn", [], UrlGeneratorInterface::ABSOLUTE_URL));

        return $this->render("App/index.html.twig", ["loginUrl" => $loginUrl]);
    }

    /**
     * @Route("loggedin", name="loggedIn")
     */
    public function loggedin()
    {

        $fb = FacebookUtils::getFacebookObject();
        $helper = $fb->getRedirectLoginHelper();

        try {
            $accessToken = $helper->getAccessToken();
        } catch(FacebookResponseException $e) {
            // When Graph returns an error
            echo 'Graph returned an error: ' . $e->getMessage();
            exit;
        } catch(FacebookSDKException $e) {
            // When validation fails or other local issues
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            exit;
        }

        if (! isset($accessToken)) {
            if ($helper->getError()) {
                header('HTTP/1.0 401 Unauthorized');
                echo "Error: " . $helper->getError() . "\n";
                echo "Error Code: " . $helper->getErrorCode() . "\n";
                echo "Error Reason: " . $helper->getErrorReason() . "\n";
                echo "Error Description: " . $helper->getErrorDescription() . "\n";
            } else {
                header('HTTP/1.0 400 Bad Request');
                echo 'Bad request';
            }
            exit;
        }

        dump($accessToken->getValue());

        $oAuth2Client = $fb->getOAuth2Client();
        dump($oAuth2Client->debugToken($accessToken));

        die("gata");
    }
}

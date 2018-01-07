<?php
/**
 * User: Seb
 * Date: 03-Jan-18
 * Time: 23:49
 */

namespace App\Services;


use App\Entity\FacebookUser;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Yaml\Exception\RuntimeException;

class FacebookApiService
{
    private $FB_APP_ID;
    private $FB_APP_SECRET;
    private $DEFAULT_GRAPH_VERSION;

    /**
     * @var SessionInterface
     */
    private $session;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * FacebookApiService constructor.
     * @param SessionInterface $session
     * @param LoggerInterface $logger
     * @param $facebookAppId string
     * @param $facebookAppSecret string
     * @param $defaultGraphVersion string
     */
    public function __construct(SessionInterface $session, LoggerInterface $logger,
                                $facebookAppId, $facebookAppSecret, $defaultGraphVersion)
    {
        $this->session = $session;
        $this->logger = $logger;

        $this->FB_APP_ID = $facebookAppId;
        $this->FB_APP_SECRET = $facebookAppSecret;
        $this->DEFAULT_GRAPH_VERSION = $defaultGraphVersion;
    }

    /**
     * @return Facebook
     */
    public function getFacebookObject()
    {

        if (!$this->session->isStarted())
        {
            $this->session->start();
        }

        $fb = new Facebook([
            'app_id' => $this->FB_APP_ID,
            'app_secret' => $this->FB_APP_SECRET,
            'default_graph_version' => $this->DEFAULT_GRAPH_VERSION,
            'persistent_data_handler'=>'session'
        ]);

        return $fb;
    }

    /**
     * @return \Facebook\Authentication\AccessToken
     */
    public function getAuthTokenAfterRedirect()
    {
        $fb = $this->getFacebookObject();
        $helper = $fb->getRedirectLoginHelper();

        try {
            $accessToken = $helper->getAccessToken();
        } catch(FacebookSDKException $e) {
            // When validation fails or other local issues
            $this->logger->error(
                "Facebook SDK returned an error when trying to get Facebook Auth Token after redirect",
                [
                    "exception" => $e
                ]);
            throw new RuntimeException($e);
        }

        if (! isset($accessToken)) {
            if ($helper->getError()) {

                $this->logger->error(sprintf(
                    "Error: %s\nError Code: %s\nError Reason: %s\n Error Description: %s",
                    $helper->getError(),
                    $helper->getErrorCode(),
                    $helper->getErrorReason(),
                    $helper->getErrorDescription()
                ));
            } else {
                $this->logger->error("Bad Request when trying to get Auth Token after redirect");
            }

            throw new RuntimeException("Could not get Facebook Auth Token after redirect. Check logs.");
        }

        $this->session->set("fbAuthTkn", $accessToken);
        return $accessToken;
    }

    /**
     * @param $facebookToken string
     * @return null|string
     */
    public function getFacebookIdFromToken($facebookToken)
    {
        if ($facebookToken == null)
        {
            return null;
        }

        $oauth2client = $this->getFacebookObject()->getOAuth2Client();
        return $oauth2client->debugToken($facebookToken)->getUserId();
    }

    /**
     * @param $fbToken
     * @return \Facebook\GraphNodes\GraphUser
     */
    public function getGraphUserProfile($fbToken)
    {
        $fb = $this->getFacebookObject();

        try {
            $response = $fb->get("/me?fields=first_name,last_name,email,birthday", $fbToken);
            return $response->getGraphUser();

        } catch (FacebookSDKException $e) {
            $this->logger->error("Facebook Graph SDK Returned an error", ['exception' => $e]);
            throw new RuntimeException($e);
        }
    }

    /**
     * @param $fbToken string
     * @return FacebookUser
     */
    public function generateUserFromFbToken($fbToken)
    {
        $facebookGraphUser = $this->getGraphUserProfile($fbToken);

        $user = new FacebookUser($facebookGraphUser->getId());
        $user->setName($facebookGraphUser->getLastName());
        $user->setSurname($facebookGraphUser->getFirstName());
        $user->setDateOfBirth($facebookGraphUser->getBirthday());
        $user->setFacebookAuthToken($fbToken);

        return $user;
    }
}




















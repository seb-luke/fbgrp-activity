<?php
/**
 * User: Seb
 * Date: 03-Jan-18
 * Time: 23:49
 */

namespace App\Services;


use App\Entity\FacebookGroups;
use App\Entity\FacebookUser;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;
use Facebook\GraphNodes\GraphEdge;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Yaml\Exception\RuntimeException;

class FacebookApiService
{
    private static $FB_OBJECT = null;

    private $FB_APP_ID;
    private $FB_APP_SECRET;
    private $DEFAULT_GRAPH_VERSION;

    private const USER_PERMISSIONS = [
        "email",
        "public_profile",
        "user_birthday",
        "user_managed_groups",
        "manage_pages"
    ];

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
        // Facebook Object created using Singleton
        if (FacebookApiService::$FB_OBJECT === null) {
            if (!$this->session->isStarted())
            {
                $this->session->start();
            }

            FacebookApiService::$FB_OBJECT = new Facebook([
                'app_id' => $this->FB_APP_ID,
                'app_secret' => $this->FB_APP_SECRET,
                'default_graph_version' => $this->DEFAULT_GRAPH_VERSION,
                'persistent_data_handler'=>'session'
            ]);
        }

        return FacebookApiService::$FB_OBJECT;
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

    /**
     * @param $appRedirectUrl string the URL to where facebook should redirect the user after a successful login
     * @return string representing the Facebook Login Url for the user to click
     */
    public function getFacebookLoginUrl($appRedirectUrl)
    {
        $helper = $this->getFacebookObject()->getRedirectLoginHelper();
        return $helper->getLoginUrl($appRedirectUrl, FacebookApiService::USER_PERMISSIONS);
    }

    /**
     * @param $endpoint string the Facebook URL that need querying (i.e. /me/groups?fields=name,id)
     * @param $fbToken string the Facebook Authentication Token
     * @return \Facebook\FacebookResponse
     */
    private function getFromFacebookEndpoint($endpoint, $fbToken)
    {
        $fb = $this->getFacebookObject();
        try {
            $response = $fb->get($endpoint, $fbToken);
            return $response;
        } catch (FacebookSDKException $e) {
            $this->logger->error("Facebook Graph SDK Returned an error", ['exception' => $e->getTraceAsString()]);
            throw new RuntimeException($e);
        }
    }

    /**
     * @param $fbToken
     * @return \Facebook\GraphNodes\GraphUser
     */
    public function getGraphUserProfile($fbToken)
    {
        try {
            $response = $this->getFromFacebookEndpoint("/me?fields=first_name,last_name,email,birthday", $fbToken);
            return $response->getGraphUser();
        } catch (FacebookSDKException $e) {
            $this->logger->error("Could not extract Graph User from Facebook Response", ['exception' => $e->getTraceAsString()]);
            throw new RuntimeException($e);
        }
    }

    /**
     * @param $user FacebookUser
     * @return array of Facebook Groups
     */
    public function getGroupsWhereUserIsAdmin($user)
    {
        $fbToken = $user->getFacebookAuthToken();

        try {
            $response = $this->getFromFacebookEndpoint("/me/groups", $fbToken);
            return $response->getGraphEdge()->asArray();
        } catch (FacebookSDKException $e) {
            $this->logger->error("Could not extract Graph Group from Facebook Response", ['exception' => $e->getTraceAsString()]);
            throw new RuntimeException($e);
        }
    }

    /**
     * @param $grpId int
     * @param $user FacebookUser
     * @return \Facebook\GraphNodes\GraphGroup
     */
    public function getDataForGroup($grpId, $user)
    {
        $fbToken = $user->getFacebookAuthToken();

        try {
            $response = $this->getFromFacebookEndpoint("/" . $grpId, $fbToken);
            return $response->getGraphGroup();
        } catch (FacebookSDKException $e) {
            $this->logger->error("Could not generate Graph Group from Facebook Response", ['exception' => $e->getTraceAsString()]);
            throw new RuntimeException($e);
        }
    }

    /**
     * @param $user FacebookUser
     * @return array
     */
    public function getManagedPagesOfUser($user)
    {
        $fbToken = $user->getFacebookAuthToken();

        try {
            $response = $this->getFromFacebookEndpoint("/me/accounts?fields=name,id", $fbToken);
            return $response->getGraphEdge()->asArray();
        } catch (FacebookSDKException $e) {
            $this->logger->error("Could not generate Graph Edge for page from Facebook Response", ['exception' => $e->getTraceAsString()]);
            throw new RuntimeException($e);
        }
    }

    /**
     * @param $groupId
     * @param $user FacebookUser
     * @return \Facebook\GraphNodes\GraphEdge
     */
    public function getUsersOfGroup($groupId, $user)
    {
        $fbToken = $user->getFacebookAuthToken();

        try {
            $response = $this->getFromFacebookEndpoint(sprintf('/%s/members', $groupId), $fbToken);
            return $response->getGraphEdge();
        } catch (FacebookSDKException $e) {
            $this->logger->error("Could not generate Graph Edge for users of group from Facebook Response", ['exception' => $e->getTraceAsString()]);
            throw new RuntimeException($e);
        }
    }

    /**
     * @param $feedEdge GraphEdge
     * @return GraphEdge|null
     */
    public function getNextPage($feedEdge)
    {
        try {
            return $this->getFacebookObject()->next($feedEdge);
        } catch (FacebookSDKException $e) {
            $this->logger->error("Could not navigate to next edge feed page", ['exception' => $e->getTraceAsString()]);
            throw new RuntimeException($e);
        }
    }

    /**
     * @param $user FacebookUser
     * @param $group FacebookGroups
     * @param null $startTime int timestamp
     * @param null $endTime int timestamp
     * @return GraphEdge
     */
    public function getOneDayFeedOfGroup($user, $group, $startTime = null, $endTime = null)
    {
        $fbToken = $user->getFacebookAuthToken();

        $qParams = [];
        if ($startTime) {
            $qParams['since'] = $startTime;
        }

        if ($endTime) {
            $qParams['until'] = $endTime;
        }

        try {
            $response = $this->getFromFacebookEndpoint(
                sprintf("/%s/feed?%s", $group->getId(), http_build_query($qParams)),
                $fbToken
            );
            return $response->getGraphEdge();
        } catch (FacebookSDKException $e) {
            $this->logger->error("Could not generate Graph Edge for feed of group from Facebook Response", ['exception' => $e->getTraceAsString()]);
            throw new RuntimeException($e);
        }
    }

    /**
     * @param $user FacebookUser
     * @param $postId int
     * @return \Facebook\GraphNodes\GraphNode
     */
    public function getPostFromId($user, $postId)
    {
        $fbToken = $user->getFacebookAuthToken();

        try {
            $response = $this->getFromFacebookEndpoint('/'.$postId, $fbToken);
            return $response->getGraphNode();
        } catch (FacebookSDKException $e) {
            $this->logger->error("Could not generate Graph Edge for post from Facebook Response", ['exception' => $e->getTraceAsString()]);
            throw new RuntimeException($e);
        }

    }

    /**
     * @param $user FacebookUser
     * @param $pageId string
     * @return \Facebook\GraphNodes\GraphPage
     */
    public function getPageToken($user, $pageId)
    {
        $fbToken = $user->getFacebookAuthToken();

        try {
            $response = $this->getFromFacebookEndpoint(sprintf('/%s/?fields=access_token', $pageId), $fbToken);
            return $response->getGraphPage();

        } catch (FacebookSDKException $e) {
            $this->logger->error("Could not generate Graph Edge for page from Facebook Response", ['exception' => $e->getTraceAsString()]);
            throw new RuntimeException($e);
        }
    }

    /**
     * @param $pageToken string
     * @param $postID string
     * @return GraphEdge
     */
    public function getReactionsForPost($pageToken, $postID)
    {

        try {
            $response = $this->getFromFacebookEndpoint($postID.'/reactions', $pageToken);
            return $response->getGraphEdge();

        } catch (FacebookSDKException $e) {
            $this->logger->error("Could not generate Graph Edge for page from Facebook Response", ['exception' => $e->getTraceAsString()]);
            throw new RuntimeException($e);
        }
    }

    /**
     * @param $user FacebookUser
     * @param $postID string
     * @return GraphEdge
     */
    public function getLikesForPost($user, $postID)
    {
        $fbToken = $user->getFacebookAuthToken();

        try {
            $response = $this->getFromFacebookEndpoint($postID.'/likes', $fbToken);
            return $response->getGraphEdge();

        } catch (FacebookSDKException $e) {
            $this->logger->error("Could not generate Graph Edge for page from Facebook Response", ['exception' => $e->getTraceAsString()]);
            throw new RuntimeException($e);
        }
    }

    /**
     * @param $userId string
     * @param $fbToken string
     * @return \Facebook\GraphNodes\GraphUser
     */
    public function getUserProfile($userId, $fbToken)
    {
        try {
            $response = $this->getFromFacebookEndpoint('/'.$userId, $fbToken);
            return $response->getGraphUser();

        } catch (FacebookSDKException $e) {
            $this->logger->error("Could not generate Graph Edge for user profile from Facebook Response", ['exception' => $e->getTraceAsString()]);
            throw new RuntimeException($e);
        }
    }
}




















<?php
/**
 * User: Seb
 * Date: 03-Jan-18
 * Time: 23:49
 */

namespace App\Utils;


use Facebook\Facebook;
use Symfony\Component\HttpFoundation\Session\Session;

class FacebookUtils
{
    private const FB_APP_ID = "110726196397876";
    private const FB_APP_SECRET = "e719c4fa4b81872eb2d8fff72390fedd";
    private const DEFAULT_GRAPH_VERSION = "v2.11";

    /**
     * @return Facebook
     */
    public static function getFacebookObject() {

        $session = new Session();
        $session->start();

        $fb = new Facebook([
            'app_id' => FacebookUtils::FB_APP_ID,
            'app_secret' => FacebookUtils::FB_APP_SECRET,
            'default_graph_version' => FacebookUtils::DEFAULT_GRAPH_VERSION,
            'persistent_data_handler'=>'session'
        ]);

        return $fb;
    }
}
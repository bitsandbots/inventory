<?php
/*
Name	: 	Simple CSRF protection class for Core-PHP (Non-Framework).
By		: 	Banujan Balendrakumar | https://github.com/banujan6
License	: 	Free & Open
Thanks	:	http://itman.in - getRealIpAddr();
*/
namespace csrfhandler;
class csrf {

    private static function startSession()
    {
        if ( !isset($_SESSION) && session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ( !isset($_SESSION['X-CSRF-TOKEN-LIST']) ) {
            $_SESSION['X-CSRF-TOKEN-LIST'] = null; // initializing the index if not exist only
        }
    }

    private static function randomToken()
    {

        $keySet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

        for($i = 0; $i < 5; $i++){
            $keySet = str_shuffle($keySet);
        }

        $userAgent = (isset($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT'] : null;

        $clientIp = self::getRealIpAddr();

        if (phpversion() < 5.5)
        {
            $hashedToken = hash('sha256', base64_encode($keySet.$userAgent.$clientIp));
        }
        else
        {
            $hashedToken = base64_encode(password_hash(base64_encode($keySet.$userAgent.$clientIp), PASSWORD_BCRYPT));
        }

        self::setToken($hashedToken);

        return $hashedToken;

    }

    private static function getRealIpAddr()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP']))
        {
            $ip=$_SERVER['HTTP_CLIENT_IP'];
        }
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        else
        {
            $ip=$_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    private static function setToken($token)
    {
        self::startSession();

        $tokenList = unserialize($_SESSION['X-CSRF-TOKEN-LIST']);

        if (!is_array($tokenList))
        {
            $tokenList = array();
        }

        array_push($tokenList, $token);
        $_SESSION['X-CSRF-TOKEN-LIST'] = serialize($tokenList);
    }

    private static function checkToken($token)
    {
        self::startSession();

        $tokenList = unserialize($_SESSION['X-CSRF-TOKEN-LIST']);
        if(is_array($tokenList) && in_array($token, $tokenList)){
            self::removeToken($token);
            return true;
        }else{
            return false;
        }
    }

    private static function removeToken($token)
    {
        self::startSession();
        $tokenList = unserialize($_SESSION['X-CSRF-TOKEN-LIST']);
        $index = array_search($token, $tokenList);
        unset($tokenList[$index]);
        $_SESSION['X-CSRF-TOKEN-LIST'] = serialize($tokenList);
    }

    private static function authToken($arrData)
    {
        if(!empty($arrData)){

            if($arrData["method"] !== $_SERVER["REQUEST_METHOD"] && $arrData["method"] !== "ALL") {
                return true;
            }

            self::startSession();

            if(isset($arrData["token"]) && !empty($arrData["token"])){
                $token = $arrData["token"];
            } else {
                return false;
            }

            return self::checkToken($token);

        } else {
            return false;
        }
    }

    public static function token()
    {
        return self::randomToken();
    }

    public static function get()
    {
        return self::authToken(array(
            "method" => "GET",
            "token" => (isset($_GET['_token'])) ? $_GET['_token'] : null
        ));
    }

    public static function post()
    {
        return self::authToken(array(
            "method" => "POST",
            "token" => (isset($_POST['_token'])) ? $_POST['_token'] : null
        ));
    }

    public static function all()
    {
        if(isset($_POST['_token'])) {
            return self::authToken(array(
                "method" => "ALL",
                "token" => $_POST['_token']
            ));
        } else if(isset($_GET['_token'])) {
            return self::authToken(array(
                "method" => "ALL",
                "token" => $_GET['_token']
            ));
        } else {
            return self::authToken(array(
                "method" => "ALL",
                "token" => null
            ));
        }

    }

    public static function flushToken()
    {
        self::startSession();
        $_SESSION['X-CSRF-TOKEN-LIST'] = null;
    }
}


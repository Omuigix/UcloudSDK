<?php
namespace Omuigix\UcloudSDK;

require_once("ActionType.php");
require_once("Conf.php");
require_once("UCloudAuth.php");

define("NO_AUTH_CHECK", 0);
define("HEAD_FIELD_CHECK", 1);
define("QUERY_STRING_CHECK", 2);

class Digest
{
    // ----------------------------------------------------------
    public static function CanonicalizedResource($bucket, $key)
    {
        return "/" . $bucket . "/" . $key;
    }

    public static function CanonicalizedUCloudHeaders($headers)
    {

        $keys = array();
        foreach($headers as $header) {
            $header = trim($header);
            $arr = explode(':', $header);
            if (count($arr) < 2) continue;
            list($k, $v) = $arr;
            $k = strtolower($k);
            if (strncasecmp($k, "x-ucloud") === 0) {
                $keys[] = $k;
            }
        }

        $c = '';
        sort($keys, SORT_STRING);
        foreach($keys as $k) {
            $c .= $k . ":" . trim($headers[$v], " ") . "\n";
        }
        return $c;
    }


    public static function UCloud_MakeAuth($auth)
    {
        if (isset($auth)) {
            return $auth;
        }

        Conf::$UCLOUD_PUBLIC_KEY;
        Conf::$UCLOUD_PRIVATE_KEY;

        return new UCloudAuth(Conf::$UCLOUD_PUBLIC_KEY, Conf::$UCLOUD_PRIVATE_KEY);
    }

//@results: token
    public static function UCloud_SignRequest($auth, $req, $type = HEAD_FIELD_CHECK)
    {
        return Digest::UCloud_MakeAuth($auth)->SignRequest($req, $type);
    }

}

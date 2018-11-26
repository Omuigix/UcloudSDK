<?php
namespace Omuigix\UcloudSDK;

class Util
{
    public static function UCloud_UrlSafe_Encode($data)
    {
        $find = array('+', '/');
        $replace = array('-', '_');
        return str_replace($find, $replace, $data);
    }

    public static function UCloud_UrlSafe_Decode($data)
    {
        $find = array('-', '_');
        $replace = array('+', '/');
        return str_replace($find, $replace, $data);
    }

//@results: (hash, err)
    public static function UCloud_FileHash($file)
    {
        $f = fopen($file, "r");
        if (!$f) return array(null, new UCloudError(0, -1, "open $file error"));

        $fileSize = filesize($file);
        $buffer   = '';
        $sha      = '';
        $blkcnt   = $fileSize/BLKSIZE;
        if ($fileSize % BLKSIZE) $blkcnt += 1;
        $buffer .= pack("L", $blkcnt);
        if ($fileSize <= BLKSIZE) {
            $content = fread($f, BLKSIZE);
            if (!$content) {
                fclose($f);
                return array("", new UCloudError(0, -1, "read file error"));
            }
            $sha .= sha1($content, TRUE);
        } else {
            for($i=0; $i<$blkcnt; $i+=1) {
                $content = fread($f, BLKSIZE);
                if (!$content) {
                    if (feof($f)) break;
                    fclose($f);
                    return array("", new UCloudError(0, -1, "read file error"));
                }
                $sha .= sha1($content, TRUE);
            }
            $sha = sha1($sha, TRUE);
        }
        $buffer .= $sha;
        $hash = Util::UCloud_UrlSafe_Encode(base64_encode($buffer));
        fclose($f);
        return array($hash, null);
    }

//@results: (mime, err)
    public static function GetFileMimeType($filename)
    {
        $mimetype = "";
        $ext = "";
        $filename_component = explode(".", $filename);
        if (count($filename_component) >= 2) {
            $ext = "." . $filename_component[count($filename_component)-1];
        }

        $mimetype_complete_map = MimeTypes::$mimetype_complete_map;
        if (array_key_exists($ext, $mimetype_complete_map)) {
            $mimetype = $mimetype_complete_map[$ext];
        } else if (function_exists('mime_content_type')) {
            $mimetype = mime_content_type($filename);
        } else if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE); // 返回 mime 类型
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
        } else {
            return array("application/octet-stream", null);
        }
        return array($mimetype, null);
    }

    public static function CheckConfig($action) {

        Conf::$UCLOUD_PUBLIC_KEY;
        Conf::$UCLOUD_PRIVATE_KEY;
        Conf::$UCLOUD_PROXY_SUFFIX;

        switch ($action) {
            case ActionType::PUTFILE:
            case ActionType::POSTFILE:
            case ActionType::MINIT:
            case ActionType::MUPLOAD:
            case ActionType::MCANCEL:
            case ActionType::MFINISH:
            case ActionType::DELETE:
            case ActionType::UPLOADHIT:
                if (Conf::$UCLOUD_PROXY_SUFFIX == "") {
                    return new UCloudError(400, -1, "no proxy suffix found in config");
                } else if (Conf::$UCLOUD_PUBLIC_KEY == "" || strstr(Conf::$UCLOUD_PUBLIC_KEY, " ") != FALSE) {
                    return new UCloudError(400, -1, "invalid public key found in config");
                } else if (Conf::$UCLOUD_PRIVATE_KEY == "" || strstr(Conf::$UCLOUD_PRIVATE_KEY, " ") != FALSE) {
                    return new UCloudError(400, -1, "invalid private key found in config");
                }
                break;
            case ActionType::GETFILE:
                if (Conf::$UCLOUD_PROXY_SUFFIX == "") {
                    return new UCloudError(400, -1, "no proxy suffix found in config");
                }
                break;
            default:
                break;
        }
        return null;
    }

}
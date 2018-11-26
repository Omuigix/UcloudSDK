<?php
namespace Omuigix\UcloudSDK;

require_once("Conf.php");
require_once("HttpRequest.php");
require_once("Digest.php");
require_once("Util.php");
require_once("UCloudAuthHttpClient.php");

class Proxy
{
    //------------------------------普通上传------------------------------
    public function UCloud_PutFile($bucket, $key, $file)
    {
        $action_type = ActionType::PUTFILE;
        $err = Util::CheckConfig(ActionType::PUTFILE);
        if ($err != null) {
            return array(null, $err);
        }

        $f = @fopen($file, "r");
        if (!$f) {
            return array(null, new UCloudError(-1, -1, "open $file error"));
        }

        Conf::$UCLOUD_PROXY_SUFFIX;
        $host = $bucket . Conf::$UCLOUD_PROXY_SUFFIX;
        $path = $key;
        $content  = @fread($f, filesize($file));
        list($mimetype, $err) = Util::GetFileMimeType($file);
        if ($err) {
            fclose($f);
            return array("", $err);
        }
        $req = new HttpRequest('PUT', array('host' => $host, 'path' => $path), $content, $bucket, $key, $action_type);
        $req->Header['Expect'] = '';
        $req->Header['Content-Type'] = $mimetype;

        $client = new UCloudAuthHttpClient(null, $mimetype);
        list($data, $err) = UCloudClient::UCloud_Client_Call($client, $req);
        fclose($f);
        return array($data, $err);
    }

//------------------------------表单上传------------------------------
    public function UCloud_MultipartForm($bucket, $key, $file)
    {
        $action_type = ActionType::POSTFILE;
        $err = Util::CheckConfig(ActionType::POSTFILE);
        if ($err != null) {
            return array(null, $err);
        }

        $f = @fopen($file, "r");
        if (!$f) return array(null, new UCloudError(-1, -1, "open $file error"));

        Conf::$UCLOUD_PROXY_SUFFIX;
        $host = $bucket . Conf::$UCLOUD_PROXY_SUFFIX;
        $path = "";
        $fsize = filesize($file);
        $content = "";
        if ($fsize != 0) {
            $content = @fread($f, filesize($file));
            if ($content == FALSE) {
                fclose($f);
                return array(null, new UCloudError(0, -1, "read file error"));
            }
        }
        list($mimetype, $err) = Util::GetFileMimeType($file);
        if ($err) {
            fclose($f);
            return array("", $err);
        }

        $req = new HttpRequest('POST', array('host'=>$host, 'path'=>$path), $content, $bucket, $key, $action_type);
        $req->Header['Expect'] = '';
        $token = Digest::UCloud_SignRequest(null, $req, $mimetype);

        $fields = array('Authorization'=>$token, 'FileName' => $key);
        $files  = array('files'=>array('file', $file, $content, $mimetype));

        $client = new UCloudAuthHttpClient(null, NO_AUTH_CHECK);
        list($data, $err) = UCloudClient::UCloud_Client_CallWithMultipartForm($client, $req, $fields, $files);
        fclose($f);
        return array($data, $err);
    }

//------------------------------分片上传------------------------------
    public function UCloud_MInit($bucket, $key)
    {

        $err = Util::CheckConfig(ActionType::MINIT);
        if ($err != null) {
            return array(null, $err);
        }

        Conf::$UCLOUD_PROXY_SUFFIX;
        $host = $bucket . Conf::$UCLOUD_PROXY_SUFFIX;
        $path = $key;
        $querys = array(
            "uploads" => ""
        );
        $req = new HttpRequest('POST', array('host'=>$host, 'path'=>$path, 'query'=>$querys), null, $bucket, $key);
        $req->Header['Content-Type'] = 'application/x-www-form-urlencoded';

        $client = new UCloudAuthHttpClient(null);
        return UCloudClient::UCloud_Client_Call($client, $req);
    }

//@results: (tagList, err)
    public function UCloud_MUpload($bucket, $key, $file, $uploadId, $blkSize, $partNumber=0)
    {

        $err = Util::CheckConfig(ActionType::MUPLOAD);
        if ($err != null) {
            return array(null, $err);
        }

        $f = @fopen($file, "r");
        if (!$f) return array(null, new UCloudError(-1, -1, "open $file error"));

        Conf::$UCLOUD_PROXY_SUFFIX;

        $etagList = array();
        list($mimetype, $err) = Util::GetFileMimeType($file);
        if ($err) {
            fclose($f);
            return array("", $err);
        }
        $client   = new UCloudAuthHttpClient(null);
        for(;;) {
            $host = $bucket . Conf::$UCLOUD_PROXY_SUFFIX;
            $path = $key;
            if (@fseek($f, $blkSize*$partNumber, SEEK_SET) < 0) {
                fclose($f);
                return array(null, new UCloudError(0, -1, "fseek error"));
            }
            $content = @fread($f, $blkSize);
            if ($content == FALSE) {
                if (feof($f)) break;
                fclose($f);
                return array(null, new UCloudError(0, -1, "read file error"));
            }

            $querys = array(
                "uploadId" => $uploadId,
                "partNumber" => $partNumber
            );
            $req = new HttpRequest('PUT', array('host'=>$host, 'path'=>$path, 'query'=>$querys), $content, $bucket, $key);
            $req->Header['Content-Type'] = $mimetype;
            $req->Header['Expect'] = '';
            list($data, $err) = UCloudClient::UCloud_Client_Call($client, $req);
            if ($err) {
                fclose($f);
                return array(null, $err);
            }
            $etag = @$data['ETag'];
            $part = @$data['PartNumber'];
            if ($part != $partNumber) {
                fclose($f);
                return array(null, new UCloudError(0, -1, "unmatch partnumber"));
            }
            $etagList[] = $etag;
            $partNumber += 1;
        }
        fclose($f);
        return array($etagList, null);
    }

    public function UCloud_MFinish($bucket, $key, $uploadId, $etagList, $newKey = '')
    {

        $err = Util::CheckConfig(ActionType::MFINISH);
        if ($err != null) {
            return array(null, $err);
        }

        Conf::$UCLOUD_PROXY_SUFFIX;
        $host = $bucket . Conf::$UCLOUD_PROXY_SUFFIX;
        $path = $key;
        $querys = array(
            'uploadId' => $uploadId,
            'newKey' => $newKey,
        );

        $body = @implode(',', $etagList);
        $req = new HttpRequest('POST', array('host'=>$host, 'path'=>$path, 'query'=>$querys), $body, $bucket, $key);
        $req->Header['Content-Type'] = 'text/plain';

        $client = new UCloudAuthHttpClient(null);
        return UCloudClient::UCloud_Client_Call($client, $req);
    }

    public function UCloud_MCancel($bucket, $key, $uploadId)
    {

        $err = Util::CheckConfig(ActionType::MCANCEL);
        if ($err != null) {
            return array(null, $err);
        }

        Conf::$UCLOUD_PROXY_SUFFIX;
        $host = $bucket . Conf::$UCLOUD_PROXY_SUFFIX;
        $path = $key;
        $querys = array(
            'uploadId' => $uploadId
        );

        $req = new HttpRequest('DELETE', array('host'=>$host, 'path'=>$path, 'query'=>$querys), null, $bucket, $key);
        $req->Header['Content-Type'] = 'application/x-www-form-urlencoded';

        $client = new UCloudAuthHttpClient(null);
        return UCloudClient::UCloud_Client_Call($client, $req);
    }

//------------------------------秒传------------------------------
    public function UCloud_UploadHit($bucket, $key, $file)
    {

        $err = Util::CheckConfig(ActionType::UPLOADHIT);
        if ($err != null) {
            return array(null, $err);
        }

        $f = @fopen($file, "r");
        if (!$f) return array(null, new UCloudError(-1, -1, "open $file error"));

        $content = "";
        $fileSize = filesize($file);
        if ($fileSize != 0) {
            $content  = @fread($f, $fileSize);
            if ($content == FALSE) {
                fclose($f);
                return array(null, new UCloudError(0, -1, "read file error"));
            }
        }
        list($fileHash, $err) = Util::UCloud_FileHash($file);
        if ($err) {
            fclose($f);
            return array(null, $err);
        }
        fclose($f);

        Conf::$UCLOUD_PROXY_SUFFIX;
        $host = $bucket . Conf::$UCLOUD_PROXY_SUFFIX;
        $path = "uploadhit";
        $querys = array(
            'Hash' => $fileHash,
            'FileName' => $key,
            'FileSize' => $fileSize
        );

        $req = new HttpRequest('POST', array('host'=>$host, 'path'=>$path, 'query'=>$querys), null, $bucket, $key);
        $req->Header['Content-Type'] = 'application/x-www-form-urlencoded';

        $client = new UCloudAuthHttpClient(null);
        return UCloudClient::UCloud_Client_Call($client, $req);
    }

//------------------------------删除文件------------------------------
    public function UCloud_Delete($bucket, $key)
    {

        $err = Util::CheckConfig(ActionType::DELETE);
        if ($err != null) {
            return array(null, $err);
        }

        Conf::$UCLOUD_PROXY_SUFFIX;
        $host = $bucket . Conf::$UCLOUD_PROXY_SUFFIX;
        $path = "$key";

        $req = new HttpRequest('DELETE', array('host'=>$host, 'path'=>$path), null, $bucket, $key);
        $req->Header['Content-Type'] = 'application/x-www-form-urlencoded';

        $client = new UCloudAuthHttpClient(null);
        return UCloudClient::UCloud_Client_Call($client, $req);
    }

//------------------------------生成公有文件Url------------------------------
// @results: $url
    public function UCloud_MakePublicUrl($bucket, $key)
    {
        Conf::$UCLOUD_PROXY_SUFFIX;
        return $bucket . Conf::$UCLOUD_PROXY_SUFFIX . "/" . rawurlencode($key);
    }
//------------------------------生成私有文件Url------------------------------
// @results: $url
    public function UCloud_MakePrivateUrl($bucket, $key, $expires = 0)
    {

        $err = Util::CheckConfig(ActionType::GETFILE);
        if ($err != null) {
            return array(null, $err);
        }

        Conf::$UCLOUD_PUBLIC_KEY;

        $public_url = UCloud_MakePublicUrl($bucket, $key);
        $req = new HttpRequest('GET', array('path'=>$public_url), null, $bucket, $key);
        if ($expires > 0) {
            $req->Header['Expires'] = $expires;
        }

        $client = new UCloudAuthHttpClient(null);
        $temp = $client->Auth->SignRequest($req, null, QUERY_STRING_CHECK);
        $signature = substr($temp, -28, 28);
        $url = $public_url . "?UCloudPublicKey=" . rawurlencode(Conf::$UCLOUD_PUBLIC_KEY) . "&Signature=" . rawurlencode($signature);
        if ('' != $expires) {
            $url .= "&Expires=" . rawurlencode($expires);
        }
        return $url;
    }

}
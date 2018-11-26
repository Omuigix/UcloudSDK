<?php
namespace Omuigix\UcloudSDK;

class UCloudAuthHttpClient
{
    public $Auth;
    public $Type;
    public $MimeType;

    public function __construct($auth, $mimetype = null, $type = HEAD_FIELD_CHECK)
    {
        $this->Type = $type;
        $this->MimeType = $mimetype;
        $this->Auth = Digest::UCloud_MakeAuth($auth, $type);
    }

    //@results: ($resp, $error)
    public function RoundTrip($req)
    {
        if ($this->Type === HEAD_FIELD_CHECK) {
            $token = $this->Auth->SignRequest($req, $this->MimeType, $this->Type);
            $req->Header['Authorization'] = $token;
        }
        return UCloudClient::UCloud_Client_Do($req);
    }
}
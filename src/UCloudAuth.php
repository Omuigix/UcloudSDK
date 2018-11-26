<?php
namespace Omuigix\UcloudSDK;

class UCloudAuth {

    public $PublicKey;
    public $PrivateKey;

    public function __construct($publicKey, $privateKey)
    {
        $this->PublicKey = $publicKey;
        $this->PrivateKey = $privateKey;
    }

    public function Sign($data)
    {
        $sign = base64_encode(hash_hmac('sha1', $data, $this->PrivateKey, true));
        return "UCloud " . $this->PublicKey . ":" . $sign;
    }

    //@results: $token
    public function SignRequest($req, $mimetype = null, $type = HEAD_FIELD_CHECK)
    {
        $url = $req->URL;
        $url = parse_url($url['path']);
        $data = '';
        $data .= strtoupper($req->METHOD) . "\n";
        $data .= UCloudClient::UCloud_Header_Get($req->Header, 'Content-MD5') . "\n";
        if ($mimetype)
            $data .=  $mimetype . "\n";
        else
            $data .= UCloudClient::UCloud_Header_Get($req->Header, 'Content-Type') . "\n";
        if ($type === HEAD_FIELD_CHECK)
            $data .= UCloudClient::UCloud_Header_Get($req->Header, 'Date') . "\n";
        else
            $data .= UCloudClient::UCloud_Header_Get($req->Header, 'Expires') . "\n";
        $data .= Digest::CanonicalizedUCloudHeaders($req->Header);
        $data .= Digest::CanonicalizedResource($req->Bucket, $req->Key);
        return $this->Sign($data);
    }
}
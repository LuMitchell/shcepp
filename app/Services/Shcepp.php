<?php

namespace App\Services;

use App\Helpers\RsaUtil;
use Illuminate\Support\Facades\Redis;

class Shcepp
{
    private static function getAuthUrl()
    {
        return config('shcepp.auth_url');
    }

    private static function getPushingUrl()
    {
        return config('shcepp.pushing_url');
    }

    private static function getOrderUrl()
    {
        return config('shcepp.order_url');
    }

    private static function getReceiptUrl()
    {
        return config('shcepp.receipt_url');
    }

    private static function getToken()
    {
        $token = Redis::get('shcepp:token');
        if( ! empty($token)) return $token;

        $public_key = "-----BEGIN PUBLIC KEY-----\n".wordwrap(RsaUtil::url_safe_decode(config('shcepp.public_key')), 64, "\n", true)."\n-----END PUBLIC KEY-----";
        $encrypted = RsaUtil::publicEncrypt(config('shcepp.password'), $public_key);

        $json = json_encode(['userName'=>config('shcepp.username'), 'password'=>$encrypted]);

        $EData = urlencode($json);

        $client = new \GuzzleHttp\Client();
        try
        {
            $res = $client->request('POST', self::getAuthUrl(), [
                'verify' => false,
                'form_params' => [
                    'EData'=>$EData
                ]
            ]);
            $data = $res->getBody()->getContents();
        }
        catch (\GuzzleHttp\Exception\RequestException $e)
        {
            $data = null;
            var_dump($e);
        }

        $data = urldecode($data);

        $result = json_decode($data, true);

        if(empty($result) || $result['status'] != 'success')
        {
            throw new \Exception('返回错误: '.$data);
        }

        Redis::setex('shcepp:token', 900, $result['token']);

        return $result['token'];
    }

    private static function prefixEData()
    {
        return [
            'version'=>config('shcepp.version'),
            'commitTime'=>date('YmdHis'),
            'senderName'=>config('shcepp.send_name'),
            'senderCode'=>config('shcepp.send_code')
        ];
    }

    public static function orderApprove($order_request, $cargoes)
    {
        $data = self::prefixEData();

        $order_request = array_filter($order_request);
        $cargoes = array_filter($cargoes);

        $order_request['cargoes'] = $cargoes;
        $data['wOrderRequest'][] = $order_request;

        $EData = self::buildEData($data);
        $url = self::getOrderUrl();

        return self::requestHttp($url, $EData);
    }

    public static function pushData($logistics, $entry_heads, $extension)
    {
        $data = self::prefixEData();

        $data['pLogistics'] = $logistics;

        if( ! empty($data['pEntryHead']))
        {
            $entry_heads['pEntryList'] = [];
            $data['pEntryHead'] = $entry_heads;
        }

        $data['pExtension'] = $extension;

        $EData = self::buildEData($data);
        $url = self::getPushingUrl();

        return self::requestHttp($url, $EData);
    }

    public static function receiptApprove($wReceiptRequest)
    {
        $data = self::prefixEData();

        $data['wReceiptRequest'][] = $wReceiptRequest;

        $EData = self::buildEData($data);
        $url = self::getReceiptUrl();

        return self::requestHttp($url, $EData);
    }

    public static function requestHttp($url, $EData)
    {
        $token = self::getToken();

        $client = new \GuzzleHttp\Client();
        try {
            $res = $client->request('POST', $url, [
                'headers' => [
                    'token'=>$token
                ],
                'verify' => false,
                'form_params' => [
                    'EData'=>$EData
                ]
            ]);
            $result = $res->getBody()->getContents();
            \Log::channel('shcepprequestlog')->info('request: '.urldecode($EData));
        }
        catch (\GuzzleHttp\Exception\ClientException $e)
        {
            $result =  $e->getResponse()->getBody()->getContents();
        }

        $result = urldecode($result);
        $result = trim($result, '[]');
        \Log::channel('shcepprequestlog')->info($result);

        return $result;
    }

    private static function buildEData($data)
    {
        $json = json_encode($data);
        return urlencode($json);
    }
}

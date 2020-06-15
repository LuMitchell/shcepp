<?php
namespace App\Helpers;


class RsaUtil
{
    private static function getPublicKey($publicKey)
    {
        return openssl_pkey_get_public($publicKey);
    }

    private static function getPrivateKey($privateKey)
    {
        return openssl_pkey_get_private($privateKey);
    }

    public static function publicEncrypt($data, $publicKey)
    {
        if (!is_string($data)) {
            return null;
        }
        return openssl_public_encrypt($data, $encrypted, self::getPublicKey($publicKey)) ? base64_encode($encrypted) : null;
    }

    public static function publicDecrypt($encrypted, $publicKey)
    {
        if (!is_string($encrypted)) {
            return null;
        }
        return (openssl_public_decrypt(base64_decode($encrypted), $decrypted, self::getPublicKey($publicKey))) ? $decrypted : null;
    }

    public static function privateEncrypt($data, $privateKey)
    {
        if(!is_string($data)){
            return null;
        }
        return openssl_private_encrypt($data, $encrypted, self::getPrivateKey($privateKey)) ? base64_encode($encrypted) : null;
    }

    public static function privateDecrypt($encrypted, $privateKey)
    {
        if(!is_string($encrypted)){
            return null;
        }
        return (openssl_private_decrypt(base64_decode($encrypted), $decrypted, self::getPrivateKey($privateKey)))? $decrypted : null;
    }

    /**
     * @param $string
     * @return string|string[]
     * url安全解码
     */
    public static function url_safe_decode($string)
    {
        $data = str_replace(['-','_'], ['+','/'], $string);
        $mod4 = strlen($data) % 4;
        if ($mod4)
        {
            $data .= substr('====', $mod4);
        }
        return $data;
    }

    /**
     * @param $string
     * @return string|string[]
     * url安全转码
     */
    public static function url_safe_encode($string)
    {
        return str_replace(['+','/','='], ['-','_',''], $string);
    }
}

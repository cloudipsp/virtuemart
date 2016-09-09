<?php

class Fondy
{
    const ORDER_APPROVED = 'approved';
    const ORDER_DECLINED = 'declined';

    const ORDER_SEPARATOR = '#';

    const SIGNATURE_SEPARATOR = '|';

    const URL = "https://api.fondy.eu/api/checkout/redirect/";


    public static function getSignature($data, $password, $encoded = true)
    {
        $data = array_filter($data);
        ksort($data);

        $str = $password;
        foreach ($data as $k => $v) {
            $str .= self::SIGNATURE_SEPARATOR . $v;
        }

        if ($encoded) {
            return sha1($str);
        } else {
            return $str;
        }
    }

    public static function isPaymentValid($fondySettings, $response)
    {
        if ($response['order_status'] == self::ORDER_DECLINED) {
            return 'Order was declined.';
        }
		if ($response['order_status'] != self::ORDER_APPROVED) {
            return 'Order was not approv.';
        }

        if ($fondySettings['merchant_id'] != $response['merchant_id']) {
            return 'An error has occurred during payment. Merchant data is incorrect.';
        }

        $originalResponse = $response['signature'];
		if (isset($response['response_signature_string'])){
			unset($response['response_signature_string']);
		}
		if (isset($response['signature'])){
			unset($response['signature']);
		}
		if (self::getSignature($response, $fondySettings['secret_key']) != $responseSignature) {
            return 'Signature is not valid' ;
        }

        return true;
    }

    public static function getAmount($order)
    {
        return round($order['details']['BT']->order_total * 100);
    }
}

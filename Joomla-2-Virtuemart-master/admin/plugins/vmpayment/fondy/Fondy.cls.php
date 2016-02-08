<?php

class Fondy
{
    const ORDER_APPROVED = 'approved';
    const ORDER_DECLINED = 'declined';

    const ORDER_SEPARATOR = '#';

    const SIGNATURE_SEPARATOR = '|';

    const URL = "https://api.fondy.eu/api/checkout/redirect/";

    protected static $responseFields = array('rrn',
                                             'masked_card',
                                             'sender_cell_phone',
                                             'response_status',
                                             'currency',
                                             'fee',
                                             'reversal_amount',
                                             'settlement_amount',
                                             'actual_amount',
                                             'order_status',
                                             'response_description',
                                             'order_time',
                                             'actual_currency',
                                             'order_id',
                                             'tran_type',
                                             'eci',
                                             'settlement_date',
                                             'payment_system',
                                             'approval_code',
                                             'merchant_id',
                                             'settlement_currency',
                                             'payment_id',
                                             'sender_account',
                                             'card_bin',
                                             'response_code',
                                             'card_type',
                                             'amount',
                                             'sender_email');

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

        if ($fondySettings['merchant_id'] != $response['merchant_id']) {
            return 'An error has occurred during payment. Merchant data is incorrect.';
        }

        $originalResponse = $response;
        foreach ($response as $k => $v) {
            if (!in_array($k, self::$responseFields)) {
                unset($response[$k]);
            }
        }
		$strs = explode(self::SIGNATURE_SEPARATOR,$originalResponse['response_signature_string']);
        $str = (str_replace($strs[0],$fondySettings['secret_key'],$originalResponse['response_signature_string']));
        if ((sha1($str)) != $originalResponse['signature']) {
            return 'Signature is not valid' ;
        }

        return true;
    }

    public static function getAmount($order)
    {
        return round($order['details']['BT']->order_total * 100);
    }
}

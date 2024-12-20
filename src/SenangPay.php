<?php

namespace Phpanonymous\SenangPay;

use Illuminate\Support\Facades\Http;

class SenangPay
{

    public static $merchantId;
    public static $secretKey;
    protected static $detail;
    protected static $amount;
    protected static $orderId;
    protected static $name;
    protected static $email;
    protected static $phone;
    protected static $mode;


    /**-------------------------------------------------------------------------------------------------------------------/    
     *    @description function description
     *    @author      Idham Hafidz JOMos    idham@jomos.com.my
     *    @param       $request "$request object from controller"
     *    @return 
     */
    public static function pay($user_info = [], $detail, $orderId, $amount)
    {
        static::$detail = $detail;
        static::$amount = $amount;
        static::$orderId = $orderId;
        static::$name = $user_info['name'];
        static::$email = $user_info['email'];
        static::$phone = $user_info['mobile'];
        return static::processPayment();
    }


    /*--------------------------------------------------------------------------------------------------------------------/	
        *
        *	@description  This will generate hash
        *	@author		  Idham Hafidz JOMos	idham@jomos.com.my
        */
    public static function generateHash()
    {
        return md5(static::$secretKey . static::$detail . static::$amount . static::$orderId);
    }

    /*--------------------------------------------------------------------------------------------------------------------/	
        *
        *	@description	This will generate the HTTP query
        *	@author		Idham Hafidz JOMos	idham@jomos.com.my
        */
    public static function generateHttpQuery()
    {
        $httpQuery = [
            'detail' => static::$detail,
            'amount' => static::$amount,
            'hash' => static::generateHash(),
            'order_id' => static::$orderId,
            'phone' => static::$phone,
            'email' => static::$email,
            'name' => static::$name
        ];
        return $httpQuery;
    }

    /*--------------------------------------------------------------------------------------------------------------------/	
       *    @description  This will send details of payment to SenangPay
       *    @author       Idham Hafidz JOMos    idham@jomos.com.my
       *    @return 
       */
    public static function processPayment(): mixed
    {
        static::$merchantId = config('senang-pay.merchant-id');
        static::$secretKey = config('senang-pay.secret-key');
        static::$mode = config('senang-pay.mode');


        if (static::$mode == 'sandbox') {
            $url = 'https://sandbox.senangpay.my/payment/' . static::$merchantId;
        } else {
            $url = 'https://app.senangpay.my/payment/' . static::$merchantId;
        }
        // Prepare the data
        $data = static::generateHttpQuery();
        return [
            'status' => true,
            'url' => $url . '?' . http_build_query($data),
        ];
    }


    /*--------------------------------------------------------------------------------------------------------------------/	
        *
        *	@description  This will generate the return Hash to match with incoming transaction
        *	@author		  Idham Hafidz JOMos	idham@jomos.com.my
        *	@param        $request  "request object from controller"
        */
    protected static function generateReturnHash()
    {
        $returnHash = md5(static::$secretKey . '?status_id=' . request('status_id') . '&order_id=' . request('order_id') . '&transaction_id=' . request('transaction_id') . '&message=' . request('message') . '&hash=[HASH]');
        return $returnHash;
    }

    /*--------------------------------------------------------------------------------------------------------------------/	
        *
        *	@description  This will check if the parametered hash is correct and not mess by MITM (Men In The Middle)
        *	@author		  Idham Hafidz JOMos	idham@jomos.com.my
        *	@param        $request  "request object from controller"
        */
    public static function checkIfReturnHashCorrect()
    {
        $parameterHash = request('hash');
        if (static::generateReturnHash() == $parameterHash) {
            return true;
        } else {
            return false;
        }
    }

    public static function hash($id)
    {

        if (config('senang-pay.encryption_mode') == 'md5') {
            $hash = md5(config('senang-pay.merchant-id') . config('senang-pay.secret-key') . $id);
        } elseif (config('senang-pay.encryption_mode') == 'sha256') {
            $stringToHash = config('senang-pay.merchant-id') . config('senang-pay.secret-key') . $id;
            $hash = hash_hmac('sha256', $stringToHash, config('senang-pay.secret-key'));
        }
        return $hash ?? '';
    }

    public static function transaction_status($transaction_id)
    {
        $url = (config('senang-pay.mode') == 'sandbox')
            ? 'https://sandbox.senangpay.my/apiv1/query_transaction_status'
            : 'https://app.senangpay.my/apiv1/query_transaction_status';

        $hash = static::hash($transaction_id);
        // dd($hash);
        $link = $url . '?merchant_id=' . '?merchant_id=' . urlencode(config('senang-pay.merchant-id')) .
            '&transaction_reference=' . urlencode($transaction_id) .
            '&hash=' . urlencode($hash);
        $client = new \GuzzleHttp\Client();

        $response = $client->get($link, [
            'cookies' => false, // Automatically send cookies from previous requests (if any)
        ]);


        if ($response->getStatusCode() == 200) {
            return [
                'status'=>true,
                'data'=>$response->getBody()
            ]; // Return the JSON response
        }

        // Handle error
        return [
            'error' => true,
            'message' => 'Failed to query transaction status',
            'details' => $response->getBody(),
        ];
    }
}

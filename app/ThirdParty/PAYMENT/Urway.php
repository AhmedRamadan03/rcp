<?php

namespace App\ThirdParty\PAYMENT;

use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;

class Urway implements PaymentInterface
{
    public function __construct(public array $values) {}

    public function pay($payment_link)
    {
        $idorder = $payment_link->id;
        $collector_id = $payment_link->payment?->claim?->assignee?->to ?? null;
        $payment_transaction = PaymentTransaction::initiatePayment($payment_link, $idorder, $payment_link->amount, $collector_id);

        $terminalId = $this->values['terminalId'];
        $password = $this->values['password'];
        $merchant_key = $this->values['merchant_key'];
        $currency_code = 'SAR';

        $ic_email = 'info@taheiya.sa';

        $ipp = '127.0.0.1';

        // Generate Hash
        $txn_details = $idorder.'|'.$terminalId.'|'.$password.'|'.$merchant_key.'|'.$payment_link->amount.'|'.$currency_code;
        $hash = hash('sha256', $txn_details);

        $fields = [
            'trackid' => $idorder,
            'terminalId' => $terminalId,
            'customerEmail' => $ic_email,
            'action' => '1',  // action is always 1
            'merchantIp' => $ipp,
            'password' => $password,
            'currency' => $currency_code,
            'country' => 'SA',
            'amount' => $payment_link->amount,
            'udf1' => $payment_link->id,
            'udf2' => url('/api/v1/claim-info/'.$payment_link->link), // Response page URL
            'udf3' => '',
            'udf4' => '',
            'udf5' => $payment_transaction->id,

            'requestHash' => $hash,  // generated Hash
        ];

        $data = json_encode($fields);
        //        dd($data);
        $payment_transaction->saveRequest($data);

        $urway_url = config('app.env') == 'local' ? 'https://payments-dev.urway-tech.com/URWAYPGService/transaction/jsonProcess/JSONrequest' : 'https://payments.urway-tech.com/URWAYPGService/transaction/jsonProcess/JSONrequest';

        //        dd($urway_url);
        $ch = curl_init($urway_url); // Will be provided by URWAY
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [
                'Content-Type: application/json',
                'Content-Length: '.strlen($data),
            ]
        );
        curl_setopt($ch, CURLOPT_TIMEOUT, 5000);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5000);

        // execute post
        $server_output = curl_exec($ch);

        // close connection
        curl_close($ch);
        $result = json_decode($server_output);

        if (! empty($result->payid) && ! empty($result->targetUrl)) {
            $url = $result->targetUrl.'?paymentid='.$result->payid;
            $payment_transaction->transaction_ref = $result->payid;
            $payment_transaction->save();

            // header('Location: '. $url, true, 307);//Redirect to Payment Page
            return $url;


        }

        // dd($server_output);
        throw new \Exception(
            json_encode([

                'message' => 'Something went wrong, please try again',
            ])
        );

    }

    public function isSuccessful($response): bool
    {
        return $response['Result'] == 'Successful';
    }

    public function transactionRef(): string
    {
        return 'TranId';
    }

    public function transactionEnquiry(PaymentTransaction $payment_transaction): PaymentTransaction
    {
        $idorder = $payment_transaction->link_id;
        $terminalId = $this->values['terminalId'];
        $password = $this->values['password'];
        $merchant_key = $this->values['merchant_key'];
        $currency_code = 'SAR';
        $ic_email = 'info@taheiya.sa';
        $ipp = '127.0.0.1';

        $txn_details = $idorder.'|'.$terminalId.'|'.$password.'|'.$merchant_key.'|'.$payment_transaction->amount.'|'.$currency_code;
        $hash = hash('sha256', $txn_details);
        // https://payments-dev.urway-tech.com/URWAYPGService/transaction/jsonProcess/JSONrequest
        $urway_url = config('app.env') == 'testing_ggi' ? 'https://payments-dev.urway-tech.com/URWAYPGService/transaction/jsonProcess/JSONrequest' : 'https://payments.urway-tech.com/URWAYPGService/transaction/jsonProcess/JSONrequest';

        //        $fields = [
        //            'transid' => $payment_transaction->transaction_ref,
        //            'trackid' => $idorder,
        //            'terminalId' => $terminalId,
        //            'action' => "10",  // action is always 1
        //            'merchantIp' => $ipp,
        //            'password' => $password,
        //            'currency' => $currency_code,
        //            'amount' => $payment_transaction->amount,
        //            'requestHash' => $hash,
        //            'udf1' => $payment_transaction->link_id,
        //        ];
        //
        //        $data = json_encode($fields);
        //
        //        $ch = curl_init($urway_url); // Will be provided by URWAY
        //        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        //        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        //        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        //        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        //        curl_setopt(
        //            $ch,
        //            CURLOPT_HTTPHEADER,
        //            array(
        //                'Content-Type: application/json',
        //                'Content-Length: ' . strlen($data)
        //            )
        //        );
        //        curl_setopt($ch, CURLOPT_TIMEOUT, 5000);
        //        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5000);
        //
        //
        //        $server_output = curl_exec($ch);
        //
        //        //close connection
        //        curl_close($ch);
        //        $response = json_decode($server_output);
        //
        //        dd($response);

        $response = Http::withOptions([
            'verify' => false,
        ])->post($urway_url, [
            'transid' => $payment_transaction->transaction_ref,
            'trackid' => $idorder,
            'terminalId' => $terminalId,
            'action' => '10',  // action is always 1
            'merchantIp' => $ipp,
            'password' => $password,
            'currency' => $currency_code,
            'amount' => $payment_transaction->amount,
            'requestHash' => $hash,
            'udf1' => $payment_transaction->link_id,
        ])->json();

        $is_successful = $response['result'] == 'Successful';

        $payment_transaction->response = $response;
        $payment_transaction->send_status = $is_successful ? PaymentTransactionStatus::PAID->value : PaymentTransactionStatus::FAILED->value;
        $payment_transaction->save();

        return $payment_transaction;
    }
}

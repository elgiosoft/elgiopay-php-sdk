<?php 
namespace ElgioPay\SDK\Resources\Card;

use ElgioPay\SDK\ElgioPayClient;
use ElgioPay\SDK\Resources\BaseResourceClient;
use GuzzleHttp\Exception\RequestException;

class CardClient extends BaseResourceClient {


    public function initialize(){
        return $this->post('/api/v1/card-payment/init', []);
    }

    public function processPayment(array $paymentData){
        return $this->post('/api/v1/card-payment/process', $paymentData);
    }

    public function confirmPayment(array $paymentData){
       return  $this->post('/api/v1/card-payment/confirm', $paymentData);

    }
}
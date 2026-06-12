<?php
namespace ElgioPay\SDK\Resources;

use ElgioPay\SDK\BaseClient;
use ElgioPay\SDK\ElgioPayClient;
use GuzzleHttp\Exception\RequestException;

abstract class BaseResourceClient extends BaseClient {

    public function __construct(protected ElgioPayClient $baseClient)
    {
    }

    public function get(string $url){
        return $this->baseClient->client->get($url);
    }

    public function post(string $url, array $params){
         try {
            $response = $this->baseClient->client->post($url, ['json' => $params]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->catchException($e); 
        }
    }

}
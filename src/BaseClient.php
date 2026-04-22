<?php
namespace ElgioPay\SDK;
use ElgioPay\SDK\ElgioPayException;
use GuzzleHttp\Exception\RequestException;

abstract class BaseClient {

    public function __construct()
    {
        
    }

    /**
     * Get base URL based on environment
     */
    protected function getBaseUrl(string $environment): string
    {
        switch ($environment) {
            case 'sandbox':
                $sandboxUrl = $_ENV['ELGIOPAY_SANDBOX_URL'] ?? getenv('ELGIOPAY_SANDBOX_URL');
                return $sandboxUrl ?: 'https://sandbox-api.elgiopay.com';
            case 'prod':
            default:
                return 'https://api.elgiopay.com';
        }
    }

    protected function catchException(RequestException $e){
        $responseData = null;
        $message = $e->getMessage();

        if ($e->hasResponse()) {
            $body = $e->getResponse()->getBody()->getContents();
            $responseData = json_decode($body, true);

            $message =
                $responseData['message']
                ?? $responseData['error']
                ?? $message;
        }
        throw new ElgioPayException($message, $e->getCode(), $e, $responseData);
    }
}
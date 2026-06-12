<?php

namespace ElgioPay\SDK\Tests;

use ElgioPay\SDK\ElgioPayClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

/**
 * Base class for SDK tests. Boots a real ElgioPayClient but swaps its Guzzle
 * client for one backed by a MockHandler so we can:
 *   - queue canned responses with $this->queue(...)
 *   - inspect every outgoing request via $this->lastRequest() / $this->requests()
 *
 * No real network traffic.
 */
abstract class MockClientTestCase extends TestCase
{
    protected ElgioPayClient $client;
    protected MockHandler $mock;
    /** @var array<int,array{request:Request,response:?\Psr\Http\Message\ResponseInterface,error:?\Throwable,options:array}> */
    protected array $captured = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Set required env so the constructor passes validation.
        putenv('ELGIOPAY_API_KEY=pk_test_sdk');
        putenv('ELGIOPAY_ENV=sandbox');

        $this->client = new ElgioPayClient();

        $this->mock = new MockHandler();
        $stack = HandlerStack::create($this->mock);
        $this->captured = [];
        $stack->push(Middleware::history($this->captured));

        $this->client->client = new Client([
            'base_uri' => 'https://sandbox-api.elgiopay.com',
            'handler' => $stack,
            'headers' => [
                'Authorization' => 'Bearer pk_test_sdk',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        putenv('ELGIOPAY_API_KEY');
        putenv('ELGIOPAY_ENV');
        parent::tearDown();
    }

    protected function queue(int $status, array $body, array $headers = ['Content-Type' => 'application/json']): void
    {
        $this->mock->append(new \GuzzleHttp\Psr7\Response($status, $headers, json_encode($body)));
    }

    protected function queueError(int $status, array $body): void
    {
        $this->mock->append(new \GuzzleHttp\Psr7\Response($status, ['Content-Type' => 'application/json'], json_encode($body)));
    }

    protected function lastRequest(): Request
    {
        $this->assertNotEmpty($this->captured, 'No HTTP requests were captured');
        return $this->captured[count($this->captured) - 1]['request'];
    }

    /** @return array<int,Request> */
    protected function requests(): array
    {
        return array_map(fn ($e) => $e['request'], $this->captured);
    }

    protected function lastRequestBody(): array
    {
        $body = (string) $this->lastRequest()->getBody();
        return $body === '' ? [] : (json_decode($body, true) ?? []);
    }

    protected function assertLastRequest(string $method, string $path): void
    {
        $req = $this->lastRequest();
        $this->assertSame($method, $req->getMethod());
        $this->assertSame($path, $req->getUri()->getPath());
    }
}

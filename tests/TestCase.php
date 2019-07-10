<?php

namespace TomorrowIdeas\Plaid\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use TomorrowIdeas\Plaid\Plaid;
use GuzzleHttp\Client;
use \GuzzleHttp\Psr7\Request;
use \GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;

class TestCase extends PHPUnitTestCase
{
    protected function getPlaidClient(): Plaid
    {
        $httpClient = new Client([
            'handler' => new MockHandler([
                function(Request $request) {

                    $requestParams = [
                        "method" => $request->getMethod(),
                        "version" => $request->getHeaderLine("Plaid-Version"),
                        "content" => $request->getHeaderLine("Content-Type"),
                        "scheme" => $request->getUri()->getScheme(),
                        "host" => $request->getUri()->getHost(),
                        "path" => $request->getUri()->getPath(),
                        "params" => \json_decode($request->getBody()->getContents()),
                    ];

                    return new Response(200, [], \json_encode($requestParams));

                }
            ])
        ]);

        $plaid = new Plaid("client_id", "secret", "public_key");
        $plaid->setHttpClient($httpClient);

        return $plaid;
    }
}
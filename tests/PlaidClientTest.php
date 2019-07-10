<?php

namespace TomorrowIdeas\Plaid\Tests;

use TomorrowIdeas\Plaid\Plaid;
use TomorrowIdeas\Plaid\PlaidException;
use TomorrowIdeas\Plaid\PlaidRequestException;
use GuzzleHttp\Client;
use \GuzzleHttp\Psr7\Request;
use \GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;

/**
 * @covers TomorrowIdeas\Plaid\Plaid
 * @covers TomorrowIdeas\Plaid\PlaidException
 * @covers TomorrowIdeas\Plaid\PlaidRequestException
 */
class PlaidClientTest extends TestCase
{
    public function test_default_environment_is_production()
    {
        $plaid = new Plaid("client_id", "secret", "public_key");

        $this->assertEquals("production", $plaid->getEnvironment());
    }

    public function test_setting_invalid_environment_throws()
    {
        $plaid = new Plaid("client_id", "secret", "public_key");

        $this->expectException(PlaidException::class);
        $plaid->setEnvironment("foo");
    }

    public function test_production_host()
    {
        $plaidClient = $this->getPlaidClient();
        $plaidClient->setEnvironment("production");

        $response = $plaidClient->getItem("access_token");

        $this->assertEquals("https", $response->scheme);
        $this->assertEquals("production.plaid.com", $response->host);
    }

    public function test_development_host()
    {
        $plaidClient = $this->getPlaidClient();
        $plaidClient->setEnvironment("development");

        $response = $plaidClient->getItem("access_token");

        $this->assertEquals("https", $response->scheme);
        $this->assertEquals("development.plaid.com", $response->host);
    }

    public function test_sandbox_host()
    {
        $plaidClient = $this->getPlaidClient();
        $plaidClient->setEnvironment("sandbox");

        $response = $plaidClient->getItem("access_token");

        $this->assertEquals("https", $response->scheme);
        $this->assertEquals("sandbox.plaid.com", $response->host);
    }

    public function test_default_plaid_version()
    {
        $plaidClient = $this->getPlaidClient();

        $this->assertEquals("2018-05-22", $plaidClient->getVersion());
    }

    public function test_plaid_version_set_in_constructor()
    {
        $version = "2019-05-29";

        $plaid = new Plaid("client_id", "secret", "public_key", "production", $version);

        $this->assertEquals($version, $plaid->getVersion());
    }

    public function test_set_plaid_version()
    {
        $version = "2019-05-29";

        $plaidClient = $this->getPlaidClient();
        $plaidClient->setVersion($version);

        $this->assertEquals($version, $plaidClient->getVersion());
    }

    public function test_setting_invalid_version_throws()
    {
        $plaid = new Plaid("client_id", "secret", "public_key");

        $this->expectException(PlaidException::class);
        $plaid->setVersion("foo");
    }


    public function test_1xx_responses_throw_exception()
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

                    return new Response(100, [], \json_encode($requestParams));

                }
            ])
        ]);

        $plaid = new Plaid("client_id", "secret", "public_key");
        $plaid->setHttpClient($httpClient);

        $this->expectException(PlaidRequestException::class);
        $plaid->getItem("access_token");
    }

    public function test_3xx_responses_an_above_throw_exception()
    {
        $httpClient = new Client([
            'handler' => new MockHandler([
                function(Request $request) {

                    $requestParams = [
                        "display_message" => "PLAID_ERROR",
                    ];

                    return new Response(300, [], \json_encode($requestParams));

                }
            ])
        ]);

        $plaid = new Plaid("client_id", "secret", "public_key");
        $plaid->setHttpClient($httpClient);

        $this->expectException(PlaidRequestException::class);
        $plaid->getItem("access_token");
    }

    public function test_request_exception_passes_through_plaid_display_message()
    {
        $httpClient = new Client([
            'handler' => new MockHandler([
                function(Request $request) {

                    $requestParams = [
                        "display_message" => "DISPLAY MESSAGE",
                    ];

                    return new Response(300, [], \json_encode($requestParams));

                }
            ])
        ]);

        $plaid = new Plaid("client_id", "secret", "public_key");
        $plaid->setHttpClient($httpClient);

        try {
            $plaid->getItem("access_token");
        }
        catch( PlaidRequestException $plaidRequestException ){

            $this->assertEquals("DISPLAY MESSAGE", $plaidRequestException->getMessage());

        }
    }

    public function test_request_exception_passes_through_http_status_code()
    {
        $httpClient = new Client([
            'handler' => new MockHandler([
                function(Request $request) {

                    $requestParams = [
                        "display_message" => "DISPLAY MESSAGE",
                    ];

                    return new Response(300, [], \json_encode($requestParams));

                }
            ])
        ]);

        $plaid = new Plaid("client_id", "secret", "public_key");
        $plaid->setHttpClient($httpClient);

        try {
            $plaid->getItem("access_token");
        }
        catch( PlaidRequestException $plaidRequestException ){

            $this->assertEquals(300, $plaidRequestException->getCode());

        }
    }
}
<?php

namespace TomorrowIdeas\Plaid;

use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use \GuzzleHttp\Psr7\Request;

final class Plaid
{
    /**
     * Plaid API version.
     * 
     * @var string
     */
    private $version = "2018-05-22";

    /**
     * Plaid API host environment.
     *
     * @var string
     */
    private $environment = "production";

    /**
     * Plaid API host name.
     * 
     * @var array<string, string>
     */
    private $plaidHost = [
        "production" => "https://production.plaid.com/",
        "development" => "https://development.plaid.com/",
        "sandbox" => "https://sandbox.plaid.com/",
    ];

    /**
     * Plaid API versions.
     *
     * @var array<string>
     */
    private $plaidVersions = [
        "2017-03-08",
        "2018-05-22",
        "2019-05-29",
    ];

    /**
     * Plaid client Id.
     *
     * @var string
     */
    private $client_id;

    /**
     * Plaid client secret.
     *
     * @var string
     */
    private $secret;

    /**
     * Plaid public key.
     *
     * @var string
     */
    private $public_key;

    /**
     * PSR-18 ClientInterface instance.
     *
     * @var ClientInterface|null
     */
    private $httpClient;

    /**
     * Plaid client constructor.
     *
     * @param string $client_id
     * @param string $secret
     * @param string $public_key
     * @param string $environment
     * @param string $version
     * @throws PlaidException
     */
    public function __construct(string $client_id, string $secret, string $public_key, string $environment = "production", string $version = "2018-05-22")
    {
        $this->client_id = $client_id;
        $this->secret = $secret;
        $this->public_key = $public_key;

        $this->setVersion($version);
        $this->setEnvironment($environment);
    }

    /**
     * Set the Plaid API environment.
     *
     * Possible values: "production", "development", "sandbox"
     *
     * @param string $environment
     * @return void
     * @throws PlaidException
     */
    public function setEnvironment(string $environment): void
    {
        if( !\array_key_exists($environment, $this->plaidHost) ){
            throw new PlaidException("Unknown or unsupported environment \"{$environment}\".");
        }

        $this->environment = $environment;
    }

    /**
     * Get the current environment.
     *
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Set the Plaid version to use
     *
     * Possible values: "2017-03-08", "2018-05-22", "2019-05-29"
     *
     * @param string $version
     * @throws PlaidException
     */
    public function setVersion(string $version): void
    {
        if( !\in_array($version, $this->plaidVersions) ){
            throw new PlaidException("Unknown or unsupported version \"{$version}\".");
        }

        $this->version = $version;
    }

    /**
     * Get the current Plaid version
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get the specific environment host name.
     *
     * @param string $environment
     * @return string|null
     */
    private function getHostname(string $environment): ?string
    {
        return $this->plaidHost[$environment] ?? null;
    }

    /**
     * Set the HTTP client to use.
     *
     * @param ClientInterface $clientInterface
     * @return void
     */
    public function setHttpClient(ClientInterface $clientInterface): void
    {
        $this->httpClient = $clientInterface;
    }

    /**
     * Get the HTTP Client interface.
     *
     * @return ClientInterface
     */
    private function getHttpClient(): ClientInterface
    {
        if( empty($this->httpClient) ){
            $this->httpClient = new Client();
        }

        return $this->httpClient;
    }

    /**
     * Process the request and decode response as JSON.
     *
     * @param Request $request
     * @return object
     * @throws
     */
    private function doRequest(Request $request): object
    {
        $response = $this->getHttpClient()->send($request);

        if( $response->getStatusCode() < 200 || $response->getStatusCode() >= 300 ){
            throw new PlaidRequestException($response);
        }

        return \json_decode($response->getBody()->getContents());
    }

    /**
     * Build a PSR-7 Request instance.
     *
     * @param string $method
     * @param string $path
     * @param array $params
     * @return Request
     */
    private function buildRequest(string $method, string $path, array $params = []): Request
    {
        return new Request(
            $method,
            ($this->getHostname($this->environment) ?? "") . $path,
            [
                "Plaid-Version" => $this->version,
                "Content-Type" => "application/json"
            ],
            \json_encode($params)
        );
    }

    /**
     * Build request body with client credentials.
     *
     * @param array $params
     * @return array
     */
    private function clientCredentials(array $params = []): array
    {
        return \array_merge([
            "client_id" => $this->client_id,
            "secret" => $this->secret
        ], $params);
    }

    /**
     * Build request body with public credentials.
     *
     * @param array $params
     * @return array
     */
    private function publicCredentials(array $params = []): array
    {
        return \array_merge([
            "public_key" => $this->public_key
        ], $params);
    }

    /**
     * Get all Plaid categories.
     *
     * @return object
     * @throws PlaidRequestException
     */
    public function getCategories(): object
    {
        return $this->doRequest(
            $this->buildRequest("post", "categories/get")
        );
    }

    /**
     * Get Auth request.
     *
     * @param string $access_token
     * @param array $options
     * @return object
     * @throws PlaidRequestException
     */
    public function getAuth(string $access_token, array $options = []): \stdClass
    {
        return $this->doRequest(
            $this->buildRequest("post", "auth/get", $this->clientCredentials(["access_token" => $access_token, "options" => (object) $options]))
        );
    }

    /**
     * Get an Item.
     *
     * @param string $access_token
     * @return object
     * @throws PlaidRequestException
     */
    public function getItem(string $access_token): \stdClass
    {
        return $this->doRequest(
            $this->buildRequest("post", "item/get", $this->clientCredentials(["access_token" => $access_token]))
        );
    }

    /**
     * Remove an Item.
     *
     * @param string $access_token
     * @return object
     * @throws PlaidRequestException
     */
    public function removeItem(string $access_token): \stdClass
    {
        return $this->doRequest(
            $this->buildRequest("post", "item/remove", $this->clientCredentials(["access_token" => $access_token]))
        );
    }

    /**
     * Create a new Item public token.
     *
     * @param string $access_token
     * @return object
     * @throws PlaidRequestException
     */
    public function createPublicToken(string $access_token): \stdClass
    {
        return $this->doRequest(
            $this->buildRequest("post", "item/public_token/create", $this->clientCredentials(["access_token" => $access_token]))
        );
    }

    /**
     * Exchange an Item public token for an access token.
     *
     * @param string $public_token
     * @return object
     * @throws PlaidRequestException
     */
    public function exchangeToken(string $public_token): \stdClass
    {
        return $this->doRequest(
            $this->buildRequest("post", "item/public_token/exchange", $this->clientCredentials(["public_token" => $public_token]))
        );
    }

    /**
     * Rotate an Item's access token.
     *
     * @param string $access_token
     * @return object
     * @throws PlaidRequestException
     */
    public function rotateAccessToken(string $access_token): \stdClass
    {
        return $this->doRequest(
            $this->buildRequest("post", "item/access_token/invalidate", $this->clientCredentials(["access_token" => $access_token]))
        );
    }

    /**
     * Update an Item webhook.
     *
     * @param string $access_token
     * @param string $webhook
     * @return object
     * @throws PlaidRequestException
     */
    public function updateWebhook(string $access_token, string $webhook): \stdClass
    {
        return $this->doRequest(
            $this->buildRequest("post", "item/webhook/update", $this->clientCredentials(["access_token" => $access_token, "webhook" => $webhook]))
        );
    }

    /**
     * Get all Accounts.
     *
     * @param string $access_token
     * @return object
     * @throws PlaidRequestException
     */
    public function getAccounts(string $access_token): \stdClass
    {
        return $this->doRequest(
            $this->buildRequest("post", "accounts/get", $this->clientCredentials(["access_token" => $access_token]))
        );
    }

    /**
     * Get a specific Insitution.
     *
     * @param string $institution_id
     * @param array<string, string> $options
     * @return object
     * @throws PlaidRequestException
     */
    public function getInstitution(string $institution_id, array $options = []): \stdClass
    {
        $params = [
            "institution_id" => $institution_id,
            "options" => (object) $options
        ];

        return $this->doRequest(
            $this->buildRequest("post", "institutions/get_by_id", $this->publicCredentials($params))
        );
    }

    /**
     * Get all Institutions.
     *
     * @param integer $count
     * @param integer $offset
     * @param array<string, string> $options
     * @return object
     * @throws PlaidRequestException
     */
    public function getInstitutions(int $count, int $offset, array $options = []): \stdClass
    {
        $params = [
            "count" => $count,
            "offset" => $offset,
            "options" => (object) $options
        ];

        return $this->doRequest(
            $this->buildRequest("post", "institutions/get", $this->clientCredentials($params))
        );
    }

    /**
     * Find an Institution by a search query.
     *
     * @param string $query
     * @param array<string> $products
     * @param array<string, string> $options
     * @return object
     * @throws PlaidRequestException
     */
    public function findInstitution(string $query, array $products, array $options = []): \stdClass
    {
        $params = [
            "query" => $query,
            "products" => $products,
            "options" => (object) $options
        ];

        return $this->doRequest(
            $this->buildRequest("post", "institutions/search", $this->publicCredentials($params))
        );
    }

    /**
     * Get all transactions for a particular Account.
     *
     * @param string $access_token
     * @param DateTime $start_date
     * @param DateTime $end_date
     * @param array<string, string> $options
     * @return object
     * @throws PlaidRequestException
     */
    public function getTransactions(string $access_token, DateTime $start_date, DateTime $end_date, array $options = []): \stdClass
    {
        $params = [
            "access_token" => $access_token,
            "start_date" => $start_date->format("Y-m-d"),
            "end_date" => $end_date->format("Y-m-d"),
            "options" => (object) $options
        ];

        return $this->doRequest(
            $this->buildRequest("post", "transactions/get", $this->clientCredentials($params))
        );
    }

    /**
     * Get Account balance.
     *
     * @param string $access_token
     * @param array<string, string> $options
     * @return object
     * @throws PlaidRequestException
     */
    public function getBalance(string $access_token, array $options = []): \stdClass
    {
        return $this->doRequest(
            $this->buildRequest("post", "accounts/balance/get", $this->clientCredentials(["access_token" => $access_token, "options" => (object) $options]))
        );
    }

    /**
     * Get Account identity information.
     *
     * @param string $access_token
     * @return object
     * @throws PlaidRequestException
     */
    public function getIdentity(string $access_token): \stdClass
    {
        return $this->doRequest(
            $this->buildRequest("post", "identity/get", $this->clientCredentials(["access_token" => $access_token]))
        );
    }

    /**
     * Get an Item's income information.
     *
     * @param string $access_token
     * @return object
     * @throws PlaidRequestException
     */
    public function getIncome(string $access_token): \stdClass
    {
        return $this->doRequest(
            $this->buildRequest("post", "income/get", $this->clientCredentials(["access_token" => $access_token]))
        );
    }
}
<?php

namespace SteemConnect\OAuth2\Provider;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;
use SteemConnect\OAuth2\Config\Config;
use Mockery;

/**
 * Class ProviderTest.
 *
 * Unit tests for the provider method.
 */
class ProviderTest extends TestCase
{
    /**
     * @var Config instance.
     */
    protected $config;

    /**
     * @var Provider instance.
     */
    protected $provider;

    protected $accessToken;

    protected $accessTokenData = [
        'access_token' => 'mock-access-token',
        'scopes' => ['mock-scopes'],
        'expires_in' => 3600,
        'username' => 'dummy-user',
        'refresh_token' => 'mock-refresh-token',
        'token_type' => 'bearer',
    ];

    protected $accountData = [
        'account' => [
            'name' => 'dummy-name',
            'foo' => 'bar'
        ]
    ];

    /**
     * Parent setup call.
     */
    public function setUp()
    {
        // parent setup call.
        parent::setUp();

        // creates a dummy access token instance.
        $this->accessToken = new AccessToken($this->accessTokenData);

        // create a new config object.
        $this->config = new Config('hernandev.app', '4c90e2e77840b97ac001b37236be966cf73ce1373f4b4b5a');
        // setup the return point.
        $this->config->setReturnUrl('https://return-to.me/callback');

        // create a new provider instance.
        $this->provider = new Provider($this->config);
    }

    /**
     * Scope testing.
     */
    public function test_scope_parsing_on_provider()
    {
        $this->assertEquals($this->config->getScopes(), $this->provider->getDefaultScopes());
    }

    /**
     * Authorization URL testing.
     */
    public function test_authorization_url_parsing_on_provider()
    {
        $this->assertEquals($this->config->buildUrl('authorization'), $this->provider->getBaseAuthorizationUrl());
    }

    /**
     * Token URL testing.
     */
    public function test_access_token_url_parsing_on_provider()
    {
        $this->assertEquals($this->config->buildUrl('access_token'), $this->provider->getBaseAccessTokenUrl([]));
    }

    /**
     * Resource Owner URL testing.
     */
    public function test_resource_owner_url_parsing()
    {
        $this->assertEquals($this->config->buildUrl('account'), $this->provider->getResourceOwnerDetailsUrl($this->accessToken));
    }

    public function test_code_parsing_with_missing_code()
    {
        $this->assertNull($this->provider->parseReturn());
    }

    public function test_code_parsing()
    {
        // create a mock http client.
        /** @var  $client */
        $client = $this->getHttpMock($this->accessTokenData);

        // set the mock client on the provider.
        $this->provider->setHttpClient($client);

        // try the code parsing method.
        $token = $this->provider->parseReturn('mock-access-code');

        // asset the correct token was parsed.
        $this->assertEquals('mock-access-token', $token->getToken());
        // parse the token expiration validity.
        $this->assertLessThanOrEqual(time() + 3600, $token->getExpires());
        $this->assertGreaterThanOrEqual(time(), $token->getExpires());
        // assert the refresh token information.
        $this->assertEquals('mock-refresh-token', $token->getRefreshToken());
        // assert the resource owner id value.
        $this->assertEquals('dummy-user', $token->getResourceOwnerId());
    }

    public function test_code_parsing_error()
    {
        // create a mock http client.
        /** @var  $client */
        $client = $this->getHttpMock(['error' => 'invalid-access-code']);

        // set the mock client on the provider.
        $this->provider->setHttpClient($client);

        // try the code parsing method.
        try {
            $token = $this->provider->parseReturn('mock-access-code');
        } catch (\Exception $e) {
            $this->assertInstanceOf(IdentityProviderException::class, $e);
        }
//        // asset the correct token was parsed.
//        $this->assertEquals('mock-access-token', $token->getToken());
//        // parse the token expiration validity.
//        $this->assertLessThanOrEqual(time() + 3600, $token->getExpires());
//        $this->assertGreaterThanOrEqual(time(), $token->getExpires());
//        // assert the refresh token information.
//        $this->assertEquals('mock-refresh-token', $token->getRefreshToken());
//        // assert the resource owner id value.
//        $this->assertEquals('dummy-user', $token->getResourceOwnerId());
    }

    /**
     * Test resource owner parsing.
     */
    public function test_resource_owner_parsing()
    {
        // creates a custom http client for returning account data.
        /** @var  $client */
        $client = $this->getHttpMock($this->accountData);

        // setup the client as the provider http client.
        $this->provider->setHttpClient($client);

        // get the result owner, that will use the mock response.
        $resourceOwner = $this->provider->getResourceOwner($this->accessToken);

        // asset the Id is set as name on the dummy data.
        $this->assertEquals($resourceOwner->getId(), 'dummy-name');
        // asset the magic getter on the response as well.
        $this->assertEquals($resourceOwner->name, 'dummy-name');
    }

    /**
     * Create a simple http client mock for testing http responses.
     *
     * @param array $data
     *
     * @return \Mockery\MockInterface|\GuzzleHttp\ClientInterface
     */
    protected function getHttpMock(array $data = [])
    {
        // create a response mock.
        $response = Mockery::mock('Psr\Http\Message\ResponseInterface');

        // create a dummy token response.
        $response->shouldReceive('getBody')->andReturn(json_encode($data));
        // includes the response type / header.
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);

        // mock http client.
        $client = Mockery::mock('GuzzleHttp\ClientInterface');

        // set the response on the mock client.
        $client->shouldReceive('send')->times(1)->andReturn($response);

        // return the client.
        return $client;
    }
}
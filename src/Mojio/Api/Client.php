<?php

namespace Mojio\Api;

use Guzzle\Common\Exception\GuzzleException;
use Guzzle\Common\Collection;
use Guzzle\Service\Description\ServiceDescription;
use Guzzle\Common\Event;
use League\OAuth2\Client\Token\AccessToken as Token;

class Client extends \Guzzle\Service\Client
{
	const LIVE = "https://api.moj.io/v2";
	
	/**
	 * @var string Mojio App ID
	 */
	protected $appId;
	
	/**
	 * @var string Mojio App secret key
	 */
	protected $secretKey;
	
	/**
	 * @var string Service version
	 */
	protected $version;
	
	/**
	 * @var Token Token
	 */
	public $token;
	
	/**
	 * Factory method to create a new Mojio client
	 *
	 * @param array|Collection $config Configuration data. Array keys:
	 *    host - Base URL host.  Default: api.moj.io
	 *    base_url - Base URL of web service.  Default: https://{{host}}/{{version}}
	 *    app_id - Mojio App ID
	 *    secret_key - Mojio App Secret Key
	 *    token - Optional Token ID
	 *
	 * @return Client
	 */
	public static function factory($config = []) {
		$defaults = [
			'scheme'         => 'https',
			'host'           => 'api.moj.io',
			'base_url'       => self::LIVE,
			'oauth_base_url' => \Mojio\OAuth2\Provider\Mojio::LIVE,
			'app_id'         => null,
			'secret_key'     => null,
			'version'        => 'v2'
		];
		$required = ['base_url', 'app_id', 'secret_key','version','oauth_base_url'];
		$config = Collection::fromConfig($config, $defaults, $required);

		$client = new self($config->get('base_url'), $config);
		
		// Attach a service description to the client
		$description = ServiceDescription::factory(__DIR__ . '/service.json');
		$client->setDescription($description);
		
		$client->getEventDispatcher()->addListener('request.before_send', function(Event $event) {
			$request = $event['request'];
			$token   = $request->getClient()->getTokenId();
			
			if ($token) {
				$request->setHeader('Authorization', 'Bearer '. $token);
			}
		});
		
		return $client;
	}
	
	/**
	 * Get Mojio oauth2 provider
	 *
	 * @param string $redirect_uri
	 * @return \Mojio\OAuth2\Provider\Mojio
	 */
	private function getOAuthProvider($redirect_uri = '') {
		 return new \Mojio\OAuth2\Provider\Mojio ([
			'clientId'     => $this->getConfig('app_id'),
			'clientSecret' => $this->getConfig('secret_key'),
			'base_url'     => $this->expandTemplate($this->getConfig('oauth_base_url')),
			'redirectUri'  => $redirect_uri
		 ]);
	}
	
	/**
	 * Get oauth2 authorization url
	 *
	 * @param string $redirect_uri
	 * @return string
	 */
	public function getAuthorizationUrl($redirect_uri) {
		$provider = $this->getOAuthProvider($redirect_uri);
		
		return $provider->getAuthorizationUrl();
	}

	/**
	 * Request Oauth authrization
	 *
	 * @param string $redirect_uri
	 * @param string $code
	 * @return void
	 */
	public function authorize($redirect_uri, $code) {
		$provider = $this->getOAuthProvider($redirect_uri);
		
		$token = $provider->getAccessToken('authorization_code', [
			'code' => $code
		]);

		if ($token) {
			$this->token = $token;
		}
	}

	/**
	 * Login using user's credentials
	 *
	 * @param array $credentials
	 * @return void
	 */
	public function login($credentials = []) {
		$provider = $this->getOAuthProvider();
		$token = $provider->getAccessToken('password', [
			'username' => $credentials['userOrEmail'],
			'password' => $credentials['password'],
		]);

		if ($token) {
			$this->token = $token;
		}
	}

	/**
	 * Extends the token validity
	 *
	 * @return void
	 */
	public function extendToken() {
		$provider = $this->getOAuthProvider();
		$token = $provider->getAccessToken('refresh_token', [
			'refresh_token' => $this->getRefreshTokenId(),
		]);

		if ($token) {
			$this->token = $token;
		}
	}

	/**
	 * Get token
	 *
	 * @return void
	 */
	public function getTokenId() {
		return $this->token ? (string) $this->token : NULL;
	}

	/**
	 * Get refresh token
	 *
	 * @return void
	 */
	private function getRefreshTokenId() {
		return $this->token ? $this->token->refreshToken  : NULL;
	}

	/**
	 * Get if user is authenitcated
	 *
	 * @return boolean
	 */
	public function isAuthenticated() {
		return (bool) $this->getTokenId();
	}
}
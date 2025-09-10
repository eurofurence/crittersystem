<?php

declare(strict_types=1);

namespace Engelsystem\Helpers;

use Engelsystem\Config\Config;
use Engelsystem\Http\UrlGenerator;
use Engelsystem\Models\OAuth;
use Engelsystem\Models\User\User;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Log\LoggerInterface;

class OAuthHelper
{
    public function __construct(
        protected Config $config,
        protected LoggerInterface $log,
        protected UrlGenerator $url
    ) {
    }

    protected function getProviderConfig(string $provider): array
    {
        return $this->config->get('oauth')[$provider];
    }


    public function isValidProvider(string $name): bool
    {
        $config = $this->config->get('oauth');

        return isset($config[$name]);
    }

    public function getProvider(string $name): AbstractProvider
    {
        $config = $this->getProviderConfig(provider: $name);

        return new GenericProvider(
            [
                'clientId'                => $config['client_id'],
                'clientSecret'            => $config['client_secret'],
                'redirectUri'             => $this->url->to('oauth/' . $name),
                'urlAuthorize'            => $config['url_auth'],
                'urlAccessToken'          => $config['url_token'],
                'urlResourceOwnerDetails' => $config['url_info'],
                'responseResourceOwnerId' => $config['id'],
            ]
        );
    }

    public function getToken(OAuth $oauth): AccessTokenInterface
    {
        return new AccessToken(
            [
                'access_token' => $oauth->access_token,
                'refresh_token' => $oauth->refresh_token,
                'expires_in' => $oauth->expires_at->getTimestamp() - Carbon::now()->getTimestamp(),
            ]
        );
    }


    public function canUpdateBadgeNumber(string $provider): bool
    {
        if (!$this->isValidProvider($provider)) {
            return false;
        }

        return isset($this->getProviderConfig($provider)['badge_number_api']);
    }

    public function updateBadgeNumber(User $user): bool
    {
        $oauth = $user->oauthActive()->first();
        if (!$oauth || !$this->canUpdateBadgeNumber($oauth->provider)) {
            return false;
        }

        $provider = $this->getProvider($oauth->provider);
        $token = $this->getToken($oauth);
        $badge_num_api = $this->getProviderConfig($oauth->provider)['badge_number_api'];
        $api_request = $provider->getAuthenticatedRequest(AbstractProvider::METHOD_GET, $badge_num_api, $token);
        $response = $provider->getParsedResponse($api_request);

        if (false === is_array($response) || !isset($response['ids']) || !is_array($response['ids'])) {
            $this->log->error(
                'Failed to fetch registration number for {name} ({id}) via {provider}',
                [
                    'name'     => $user->name,
                    'id'       => $user->id,
                    'provider' => $oauth->provider,
                ]
            );
            return false;
        }

        $user->personalData->badge_number = $response['ids'][0];
        $user->personalData->save();

        return true;
    }
}

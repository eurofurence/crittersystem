<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Carbon\Carbon;
use Engelsystem\Config\Config;
use Engelsystem\Helpers\Authenticator;
use Engelsystem\Helpers\OAuthHelper;
use Engelsystem\Http\Exceptions\HttpNotFound;
use Engelsystem\Http\Redirector;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Http\UrlGenerator;
use Engelsystem\Models\OAuth;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface as ResourceOwner;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\Session as Session;

class OAuthController extends BaseController
{
    use HasUserNotifications;

    public function __construct(
        protected Authenticator $auth,
        protected AuthController $authController,
        protected Config $config,
        protected LoggerInterface $log,
        protected OAuth $oauth,
        protected OAuthHelper $oauthHelper,
        protected Redirector $redirect,
        protected Session $session,
        protected UrlGenerator $url
    ) {
    }

    public function connect(Request $request): Response
    {
        $providerName = $request->getAttribute('provider');

        $this->requireProvider($providerName);

        $this->session->set('oauth2_connect_provider', $providerName);

        return $this->index($request);
    }

    protected function requireProvider(string $provider): void
    {
        if (!$this->oauthHelper->isValidProvider($provider)) {
            throw new HttpNotFound('oauth.provider-not-found');
        }
    }

    public function index(Request $request): Response
    {
        $providerName = $request->getAttribute('provider');

        $provider = $this->getProvider($providerName);
        $config = $this->config->get('oauth')[$providerName];

        // Handle OAuth error response according to https://www.rfc-editor.org/rfc/rfc6749#section-4.1.2.1
        if ($request->has('error')) {
            throw new HttpNotFound('oauth.' . $request->get('error'));
        }

        // Attempt cookie-based refresh prior to redirecting to provider
        $cookies = $request->getCookieParams();
        // Hardened cookie name with __Host- prefix (requires Secure, path=/, no Domain)
        $refreshCookieKey = '__Host-oauth2_rt_' . $providerName;
        $accessToken = null;
        $resourceOwner = null;
        $resumedFromCookie = false;

        if (!$request->has('code') && !empty($cookies[$refreshCookieKey])) {
            try {
                $accessToken = $provider->getAccessToken('refresh_token', [
                    'refresh_token' => $cookies[$refreshCookieKey],
                ]);

                // Rotate refresh token cookie on every successful refresh
                $newRefresh = $accessToken->getRefreshToken();
                if ($newRefresh) {
                    $expires = $accessToken->getExpires();
                    $cookieExpire = $expires ?: 0;
                    @setcookie($refreshCookieKey, $newRefresh, [
                        'expires'  => $cookieExpire,
                        'path'     => '/',
                        'secure'   => true,
                        'httponly' => true,
                        'samesite' => 'Strict',
                    ]);
                }

                // Try to load resource owner to continue normal flow
                $resourceOwner = $provider->getResourceOwner($accessToken);
                $resumedFromCookie = true;
            } catch (IdentityProviderException $e) {
                $this->log->warning(
                    'OAuth cookie-based refresh failed for {provider}: {error}',
                    ['provider' => $providerName, 'error' => $e->getMessage()]
                );
                $accessToken = null;
                $resourceOwner = null;
                $resumedFromCookie = false;

                // Optionally clear a bad/expired refresh cookie
                @setcookie($refreshCookieKey, '', [
                    'expires'  => time() - 3600,
                    'path'     => '/',
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        }

        // Initial request redirects to provider if we did not resume from cookie
        if (!$resumedFromCookie && !$request->has('code')) {
            $authorizationUrl = $provider->getAuthorizationUrl(
                [
                    // League oauth separates scopes by comma, which is wrong, so we do it
                    // here properly by spaces. See https://www.rfc-editor.org/rfc/rfc6749#section-3.3
                    'scope' => join(' ', $config['scope'] ?? []),
                ]
            );
            $this->session->set('oauth2_state', $provider->getState());

            return $this->redirect->to($authorizationUrl);
        }

        // Redirected URL got called a second time (only validate state for auth_code flow)
        if (
            !$resumedFromCookie && (
                !$this->session->get('oauth2_state')
                || $request->get('state') !== $this->session->get('oauth2_state')
            )
        ) {
            $this->session->remove('oauth2_state');

            $this->log->warning('Invalid OAuth state - Redirect URL Recalled');

            throw new HttpNotFound('oauth.invalid-state');
        }

        // Fetch access token (authorization_code flow)
        if (!$resumedFromCookie) {
            try {
                $accessToken = $provider->getAccessToken(
                    'authorization_code',
                    [
                        'code' => $request->get('code'),
                    ]
                );
            } catch (IdentityProviderException $e) {
                $this->handleOAuthError($e, $providerName);
            }
        }

        // Load resource identifier
        if (!$resumedFromCookie) {
            try {
                $resourceOwner = $provider->getResourceOwner($accessToken);
            } catch (IdentityProviderException $e) {
                $this->handleOAuthError($e, $providerName);
            }
        }
        $resourceId = $this->getId($providerName, $resourceOwner);

        // Fetch existing oauth state
        /** @var OAuth|null $oauth */
        $oauth = $this->oauth
            ->query()
            ->where('provider', $providerName)
            ->where('identifier', $resourceId)
            ->get()
            // Explicit case-sensitive comparison using PHP as some DBMS collations are case-sensitive and some aren't
            ->where('identifier', '===', (string) $resourceId)
            ->first();

        // Update oauth state
        $expirationTime = $accessToken->getExpires();
        $expirationTime = $expirationTime ? Carbon::createFromTimestamp($expirationTime) : null;
        if ($oauth) {
            $oauth->access_token = $accessToken->getToken();
            $oauth->refresh_token = $accessToken->getRefreshToken();
            $oauth->expires_at = $expirationTime;

            $oauth->save();
        }

        // Persist only refresh token in a hardened cookie; do NOT store access tokens client-side
        if ($accessToken && $accessToken->getRefreshToken()) {
            $cookieExpire = $accessToken->getExpires() ?: 0;
            @setcookie($refreshCookieKey, $accessToken->getRefreshToken(), [
                'expires'  => $cookieExpire,
                'path'     => '/',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }

        // Load user
        $user = $this->auth->user();
        if ($oauth && $user && $user->id != $oauth->user_id) {
            throw new HttpNotFound('oauth.already-connected');
        }

        $connectProvider = $this->session->get('oauth2_connect_provider');
        $this->session->remove('oauth2_connect_provider');
        // Connect user with oauth
        if (!$oauth && $user && $connectProvider && $connectProvider == $providerName) {
            $oauth = new OAuth([
                'provider' => $providerName,
                'identifier' => $resourceId,
                'access_token' => $accessToken->getToken(),
                'refresh_token' => $accessToken->getRefreshToken(),
                'expires_at' => $expirationTime,
            ]);
            $oauth->user()
                ->associate($user)
                ->save();

            $this->log->info(
                'Connected OAuth user {user} using {provider}',
                ['provider' => $providerName, 'user' => $resourceId]
            );
            $this->addNotification('oauth.connected');
        }

        // Load user data
        $resourceData = $resourceOwner->toArray();
        if (!empty($config['nested_info'])) {
            $resourceData = Arr::dot($resourceData);
        }

        $userdata = new Collection($resourceData);
        if (!$oauth) {
            // User authenticated but has no account
            return $this->redirectRegister(
                $providerName,
                (string) $resourceId,
                $accessToken,
                $config,
                $userdata
            );
        }

        // Log the user in first to ensure permissions resolve correctly in this request
        $response = $this->authController->loginUser($oauth->user);

        // Enforce access-mode restrictions before any side effects
        $accessCheck = $this->authController->checkAccessMode($oauth->user);
        if ($accessCheck instanceof Response) {
            return $accessCheck;
        }

        if (isset($config['mark_arrived']) && $config['mark_arrived']) {
            $this->handleArrive($providerName, $oauth, $resourceOwner);
        }

        // Handle arrive if staff with user.type.staff
        if ($oauth->user->hasPermission('user.type.staff')) {
            $this->handleArrive($providerName, $oauth, $resourceOwner);
        }

        event('oauth2.login', ['provider' => $providerName, 'data' => $userdata]);

        return $response;
    }

    protected function getProvider(string $name): AbstractProvider
    {
        $this->requireProvider($name);

        return $this->oauthHelper->getProvider($name);
    }

    /**
     *
     * @throws HttpNotFound
     */
    protected function handleOAuthError(IdentityProviderException $e, string $providerName): void
    {
        $response = $e->getResponseBody();
        $response = is_array($response) ? json_encode($response) : $response;
        $this->log->error(
            '{provider} identity provider error: {error} {description}',
            [
                'provider' => $providerName,
                'error' => $e->getMessage(),
                'description' => $response,
            ]
        );

        throw new HttpNotFound('oauth.provider-error');
    }

    protected function getId(string $providerName, ResourceOwner $resourceOwner): mixed
    {
        $config = $this->config->get('oauth')[$providerName];
        if (empty($config['nested_info'])) {
            return $resourceOwner->getId();
        }

        $data = Arr::dot($resourceOwner->toArray());
        return $data[$config['id']];
    }

    protected function redirectRegister(
        string $providerName,
        string $providerUserIdentifier,
        AccessTokenInterface $accessToken,
        array $config,
        Collection $userdata
    ): Response {
        $config = array_merge(
            [
                'username' => null,
                'email' => null,
                'first_name' => null,
                'last_name' => null,
                'enable_password' => false,
                'allow_registration' => null,
                'groups' => null,
            ],
            $config
        );

        if (!$this->config->get('registration_enabled') && !$config['allow_registration']) {
            throw new HttpNotFound('oauth.not-found');
        }

        // Set registration form field data
        $this->session->set('form-data-username', $userdata->get($config['username']));
        $this->session->set('form-data-email', $userdata->get($config['email']));
        $this->session->set('form-data-firstname', $userdata->get($config['first_name']));
        $this->session->set('form-data-lastname', $userdata->get($config['last_name']));

        // Define OAuth state
        $this->session->set('oauth2_groups', $userdata->get($config['groups'], []));
        $this->session->set('oauth2_connect_provider', $providerName);
        $this->session->set('oauth2_user_id', $providerUserIdentifier);

        $expirationTime = $accessToken->getExpires();
        $expirationTime = $expirationTime ? Carbon::createFromTimestamp($expirationTime) : null;
        $this->session->set('oauth2_access_token', $accessToken->getToken());
        $this->session->set('oauth2_refresh_token', $accessToken->getRefreshToken());
        $this->session->set('oauth2_expires_at', $expirationTime);
        $this->session->set('oauth2_enable_password', $config['enable_password']);
        $this->session->set('oauth2_allow_registration', $config['allow_registration']);

        return $this->redirect->to('/register');
    }

    protected function handleArrive(
        string $providerName,
        OAuth $auth,
        ResourceOwner $resourceOwner
    ): void {
        $user = $auth->user;
        $userState = $user->state;

        if ($userState->arrived) {
            return;
        }

        $userState->arrived = true;
        $userState->arrival_date = new Carbon();
        $userState->save();

        $this->log->info(
            'Set user {name} ({id}) as arrived via {provider} user {user}',
            [
                'provider' => $providerName,
                'user' => $this->getId($providerName, $resourceOwner),
                'name' => $user->name,
                'id' => $user->id,
            ]
        );
    }

    public function disconnect(Request $request): Response
    {
        $providerName = $request->getAttribute('provider');

        $this->oauth
            ->whereUserId($this->auth->user()->id)
            ->where('provider', $providerName)
            ->delete();

        $this->log->info('Disconnected OAuth from {provider}', ['provider' => $providerName]);
        $this->addNotification('oauth.disconnected');

        return $this->redirect->back();
    }
}

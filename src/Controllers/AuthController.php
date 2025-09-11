<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Carbon\Carbon;
use Engelsystem\Config\Config;
use Engelsystem\Helpers\AccessMode;
use Engelsystem\Models\EventConfig;
use Engelsystem\Helpers\Authenticator;
use Engelsystem\Helpers\OAuthHelper;
use Engelsystem\Http\Redirector;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Models\User\User;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class AuthController extends BaseController
{
    use HasUserNotifications;

    /** @var array<string, string> */
    protected array $permissions = [
        'login'     => 'login',
        'postLogin' => 'login',
    ];

    public function __construct(
        protected Response $response,
        protected SessionInterface $session,
        protected Redirector $redirect,
        protected Config $config,
        protected Authenticator $auth,
        protected OAuthHelper $oauthHelper,
        protected EventConfig $eventConfig
    ) {
    }

    public function login(): Response
    {
        return $this->showLogin();
    }

    /**
     * Renders and returns the login page view.
     *
     * @return Response Returns the response containing the login page view.
     */
    protected function showLogin(): Response
    {
        // TODO: Remove this section or improve with better handling...
        if ($this->config->get('login_dev_warning')) {
            $this->addNotification(
                'Warning: Development instance. Features may be added, removed or changed without notice.',
                NotificationType::WARNING
            );
        }
        return $this->response->withView('pages/login');
    }

    /**
     * Handles the login request, validates user credentials, and authenticates the user.
     *
     * @param Request $request The incoming HTTP request containing login credentials.
     *
     * @return Response Returns the response for the login process. If authentication fails,
     *                  the login view is returned with an error notification. On successful
     *                  authentication, the user is logged in and a successful response is returned.
     */
    public function postLogin(Request $request): Response
    {
        $data = $this->validate($request, [
            'login'    => 'required',
            'password' => 'required',
        ]);

        $user = $this->auth->authenticate($data['login'], $data['password']);

        if (!$user instanceof User) {
            $this->addNotification('auth.not-found', NotificationType::ERROR);

            return $this->showLogin();
        }

        // Let's check the access mode, before we fully allow the user to get in
        $accessModeResult = $this->checkAccessMode($user);
        if ($accessModeResult instanceof Response) {
            return $accessModeResult;
        }

        return $this->loginUser($user);
    }

    /**
     * Logs in a user and sets up their session.
     *
     * @param User $user The user instance to be logged in.
     * @return Response A redirection response to the previous page or the home page.
     */
    public function loginUser(User $user): Response
    {
        $previousPage = $this->session->get('previous_page');

        $this->session->invalidate();
        $this->session->set('user_id', $user->id);
        $this->session->set('locale', $user->settings->language);

        if ($user->personalData->badge_number == null) {
            $this->oauthHelper->updateBadgeNumber($user);
        }

        $user->last_login_at = new Carbon();
        $user->save(['touch' => false]);

        return $this->redirect->to($previousPage ?: $this->config->get('home_site'));
    }

    /**
     * Logs the user out by invalidating the current session and redirects to the home page.
     *
     */
    public function logout(): Response
    {
        $this->session->invalidate();

        return $this->redirect->to('/');
    }

    public function checkAccessMode(User $user): ?Response
    {
        $accessMode = app(AccessMode::class);
        switch ($accessMode->getMode()) {
            case 'staff':
                if (!$this->hasStaffAccess($user)) {
                    $this->addNotification('auth.login.staff_only', NotificationType::ERROR);
//                    $this->session->invalidate();
                    return $this->showLogin();
                }
                break;

            case 'admin':
                if (!$this->hasAdminAccess($user)) {
                    $this->addNotification('auth.login.admin_only', NotificationType::ERROR);
//                    $this->session->invalidate();
                    return $this->showLogin();
                }
                break;
        }
        return null;
    }

    /**
     * Determines if the given user has staff access based on their privileges.
     *
     * @param mixed $user The user object whose access privileges are being checked.
     *
     * @return bool Returns true if the user has staff access, such as internal staff or admin privileges;
     * otherwise, false.
     */
    protected function hasStaffAccess(User $user): bool
    {
        return $user->privileges()
            ->where(function ($query): void {
                $query->where('name', 'user.type.internal_staff')
                    ->orWhere('name', 'user.type.staff')
                    ->orWhere('name', 'user.type.admin')
                    ->orWhere('name', 'admin');
            })
            ->exists();
    }

    /**
     * Checks if the given user has administrative access.
     *
     * @param mixed $user The user whose privileges are being checked.
     *
     * @return bool Returns true if the user has administrative privileges, otherwise false.
     */
    protected function hasAdminAccess(User $user): bool
    {
        return $user->privileges()
            ->where('name', 'admin')
            ->orWhere('name', 'user.type.admin')
            ->exists();
    }
}

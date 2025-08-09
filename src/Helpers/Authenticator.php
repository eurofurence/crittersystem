<?php

declare(strict_types=1);

namespace Engelsystem\Helpers;

use Carbon\Carbon;
use Engelsystem\Models\Group;
use Engelsystem\Models\User\User;
use Engelsystem\Models\User\User as UserRepository;
use Illuminate\Support\Str;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Handles user authentication and permission management.
 */
class Authenticator
{
    protected ?User $user = null;

    /** @var string[] */
    protected array $permissions = [];

    protected int|string|null $passwordAlgorithm = PASSWORD_DEFAULT;

    protected int $defaultRole = 20; // Critter

    protected int $guestRole = 10; // Guest

    public function __construct(
        protected ServerRequestInterface $request,
        protected Session $session,
        protected UserRepository $userRepository
    ) {
    }

    /**
     * Retrieves the current authenticated user, loading it from session or API if necessary.
     *
     * @return User|null Returns the authenticated user instance if available, or null otherwise.
     */
    public function user(): ?User
    {
        if ($this->user) {
            return $this->user;
        }

        $this->user = $this->userFromSession();
        if (!$this->user && $this->isApiRequest()) {
            $this->user = $this->userFromApi();
        }

        return $this->user;
    }

    /**
     * Retrieves the user instance from the current session if available.
     *
     * @return User|null Returns the User object if a valid session exists, or null if no user is found.
     */
    public function userFromSession(): ?User
    {
        if ($this->user) {
            return $this->user;
        }

        $userId = $this->session->get('user_id');
        if (!$userId) {
            return null;
        }

        $this->user = $this
            ->userRepository
            ->find($userId);

        return $this->user;
    }

    /**
     * Retrieves the user from the API by checking various sources such as headers and query parameters.
     *
     * @return User|null Returns the user object if found, or null if no user is identified.
     */
    public function userFromApi(): ?User
    {
        if ($this->user) {
            return $this->user;
        }

        $this->user = $this->userByHeaders();
        if ($this->user) {
            return $this->user;
        }

        $this->user = $this->userByQueryParam();

        return $this->user;
    }

    /**
     * Checks if the provided abilities are granted based on the current permissions.
     *
     * @param array|string $abilities The ability or list of abilities to check.
     * @return bool Returns true if all abilities are granted, false otherwise.
     */
    public function can(array|string $abilities): bool
    {
        $abilities = (array) $abilities;

        if (empty($this->permissions)) {
            $this->loadPermissions();
        }

        foreach ($abilities as $ability) {
            if (!in_array($ability, $this->permissions)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if at least one of the given abilities is granted.
     *
     * @param string[]|string $abilities The ability or list of abilities to check.
     * @return bool True if any of the abilities are granted, otherwise false.
     */
    public function canAny(array|string $abilities): bool
    {
        $abilities = (array) $abilities;

        foreach ($abilities as $ability) {
            if ($this->can($ability)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Authenticates a user using their login and password.
     *
     * @param string $login The username or email address of the user.
     * @param string $password The password associated with the user account.
     * @return User|null Returns the authenticated User object if successful, or null if authentication fails.
     */
    public function authenticate(string $login, string $password): ?User
    {
        /** @var User $user */
        $user = $this->userRepository->whereName($login)->first();
        if (!$user) {
            $user = $this->userRepository->whereEmail($login)->first();
        }

        if (!$user) {
            return null;
        }

        if (!$this->verifyPassword($user, $password)) {
            return null;
        }

        return $user;
    }

    /**
     * Verifies whether the provided password matches the stored password hash for the given user.
     *
     * @param User $user The user whose password is being verified.
     * @param string $password The plaintext password to verify.
     * @return bool Returns true if the password is valid, false otherwise.
     */
    public function verifyPassword(User $user, string $password): bool
    {
        if (!password_verify($password, $user->password)) {
            return false;
        }

        if (password_needs_rehash($user->password, $this->passwordAlgorithm)) {
            $this->setPassword($user, $password);
        }

        return true;
    }

    /**
     * Retrieves the user associated with the provided headers.
     *
     * This method extracts the user based on the 'authorization' or 'x-api-key' headers.
     * For 'authorization', a Bearer token is expected.
     *
     * @return User|null Returns the user instance if successfully resolved, or null if not found.
     */
    protected function userByHeaders(): ?User
    {
        $header = $this->request->getHeader('authorization');
        if (!empty($header) && Str::startsWith(Str::lower($header[0]), 'bearer ')) {
            return $this->userByApiKey(trim(Str::substr($header[0], 7)));
        }

        $header = $this->request->getHeader('x-api-key');
        if (!empty($header)) {
            return $this->userByApiKey($header[0]);
        }

        return null;
    }

    /**
     * Retrieves the user associated with the API key provided in the query parameters.
     *
     * @return User|null Returns the User object if a valid API key is found, or null if not found.
     */
    protected function userByQueryParam(): ?User
    {
        $params = $this->request->getQueryParams();
        if (!empty($params['key'])) {
            $this->user = $this->userByApiKey($params['key']);
        }

        return $this->user;
    }

    /**
     * Resets the API key for the given user by generating a new one.
     *
     * @param User $user The user whose API key will be reset.
     * @throws RandomException
     */
    public function resetApiKey(User $user): void
    {
        $user->api_key = bin2hex(random_bytes(32));
        $user->save();
    }

    /**
     * Retrieves a user by the provided API key.
     *
     * @param string $key The API key to search for a user.
     * @return User|null Returns the user associated with the API key, or null if not found.
     */
    protected function userByApiKey(string $key): ?User
    {
        $this->user = $this
            ->userRepository
            ->whereApiKey($key)
            ->first();

        return $this->user;
    }

    /**
     * Determines if the current request is an API request.
     *
     * @return bool Returns true if the request is identified as API accessible, false otherwise.
     */
    protected function isApiRequest(): bool
    {
        return (bool) request()->getAttribute('route-api-accessible', false);
    }

    /**
     * Sets and securely hashes the password for the given user using the configured algorithm.
     *
     * @param User $user The user object whose password needs to be set.
     * @param string $password The plain text password to be hashed and saved.
     */
    public function setPassword(User $user, string $password): void
    {
        $user->password = password_hash($password, $this->passwordAlgorithm);
        $user->save();
    }

    /**
     * Retrieves the password algorithm identifier being used.
     *
     * @return int|string|null The password algorithm as an integer, string, or null if not set.
     */
    public function getPasswordAlgorithm(): int|string|null
    {
        return $this->passwordAlgorithm;
    }

    /**
     * Sets the password algorithm to be used.
     *
     * @param int|string|null $passwordAlgorithm The password algorithm, which can be an integer, a string, or null.
     */
    public function setPasswordAlgorithm(int|string|null $passwordAlgorithm): void
    {
        $this->passwordAlgorithm = $passwordAlgorithm;
    }

    /**
     * Retrieves the default role identifier.
     *
     * @return int The identifier of the default role.
     */
    public function getDefaultRole(): int
    {
        return $this->defaultRole;
    }

    /**
     * Sets the default role for the instance.
     *
     * @param int $defaultRole The identifier of the default role to set.
     */
    public function setDefaultRole(int $defaultRole): void
    {
        $this->defaultRole = $defaultRole;
    }

    /**
     * Retrieves the role identifier for a guest user.
     *
     * @return int The role ID assigned to guest users.
     */
    public function getGuestRole(): int
    {
        return $this->guestRole;
    }

    /**
     * Sets the role ID for a guest user.
     *
     * @param int $guestRole The role identifier to assign to the guest.
     */
    public function setGuestRole(int $guestRole): void
    {
        $this->guestRole = $guestRole;
    }

    /**
     * Loads and sets the permissions for the current user based on their privileges
     * or assigns the permissions for a guest user role if no user is authenticated.
     */
    protected function loadPermissions(): void
    {
        $user = $this->user();

        if ($user) {
            $this->permissions = $user->privileges->pluck('name')->toArray();

            if ($user->last_login_at < Carbon::now()->subMinutes(5) && !$this->isApiRequest()) {
                $user->last_login_at = Carbon::now();
                $user->save(['touch' => false]);
            }
        } elseif ($this->session->get('user_id')) {
            $this->session->remove('user_id');
        }

        if (empty($this->permissions)) {
            /** @var Group $group */
            $group = Group::find($this->guestRole);
            $this->permissions = $group->privileges->pluck('name')->toArray();
        }
    }
}

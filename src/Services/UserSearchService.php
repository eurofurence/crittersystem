<?php

declare(strict_types=1);

namespace Engelsystem\Services;

use Engelsystem\Models\User\User;
use Engelsystem\Models\AngelType;
use Engelsystem\Models\DigitalIdToken;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Service for advanced user search and lookup functionality.
 *
 * This service provides sophisticated user search capabilities with filtering,
 * sorting, and pagination. It supports searching by various criteria including
 * name, email, angel types, activity status, and more.
 */
class UserSearchService
{
    /** @var array Valid sort fields */
    public const VALID_SORT_FIELDS = ['name', 'email', 'created_at', 'last_login_at', 'id'];

    /** @var array Valid sort directions */
    public const VALID_SORT_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected LoggerInterface $log
    ) {
    }

    /**
     * Search users based on provided criteria.
     *
     * @param array $criteria Search criteria
     * @return Collection Collection of User models
     */
    public function searchUsers(array $criteria = []): Collection
    {
        $this->log->info('Starting user search', [
            'criteria' => $criteria,
        ]);

        try {
            $query = $this->buildSearchQuery($criteria);
            $users = $query->get();

            $this->log->info('User search completed', [
                'criteria' => $criteria,
                'results_count' => $users->count(),
            ]);

            return $users;
        } catch (\Exception $e) {
            $this->log->error('User search failed', [
                'criteria' => $criteria,
                'error' => $e->getMessage(),
            ]);

            // Return empty collection on error rather than throwing
            return new Collection();
        }
    }

    /**
     * Search users with pagination.
     *
     * @param array $criteria Search criteria
     * @param int $perPage Number of users per page
     * @param int $page Page number (1-based)
     * @return array Paginated search results
     */
    public function searchUsersPaginated(array $criteria = [], int $perPage = 20, int $page = 1): array
    {
        $perPage = max(1, min($perPage, 100)); // Limit between 1 and 100
        $page = max(1, $page); // Must be at least 1

        $this->log->info('Starting paginated user search', [
            'criteria' => $criteria,
            'per_page' => $perPage,
            'page' => $page,
        ]);

        try {
            $query = $this->buildSearchQuery($criteria);

            // Get total count before applying pagination
            $totalCount = $query->count();
            $totalPages = max(1, ceil($totalCount / $perPage));
            $page = min($page, $totalPages); // Don't exceed max pages

            // Apply pagination
            $offset = ($page - 1) * $perPage;
            $users = $query->offset($offset)->limit($perPage)->get();

            $result = [
                'data' => $users,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalCount,
                    'last_page' => $totalPages,
                    'from' => $totalCount > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $perPage, $totalCount),
                    'has_more_pages' => $page < $totalPages,
                ],
                'search_criteria' => $criteria,
                'search_timestamp' => Carbon::now()->toDateTimeString(),
            ];

            $this->log->info('Paginated user search completed', [
                'total_results' => $totalCount,
                'page' => $page,
                'per_page' => $perPage,
                'results_on_page' => $users->count(),
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->log->error('Paginated user search failed', [
                'criteria' => $criteria,
                'error' => $e->getMessage(),
            ]);

            return [
                'data' => new Collection(),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                    'from' => 0,
                    'to' => 0,
                    'has_more_pages' => false,
                ],
                'search_criteria' => $criteria,
                'search_timestamp' => Carbon::now()->toDateTimeString(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Find users by name with fuzzy matching.
     *
     * @param string $name Name to search for
     * @param int $limit Maximum number of results
     * @return Collection Collection of User models
     */
    public function findUsersByName(string $name, int $limit = 10): Collection
    {
        if (empty(trim($name))) {
            return new Collection();
        }

        $searchTerm = trim($name);
        $limit = max(1, min($limit, 50)); // Limit between 1 and 50

        $this->log->info('Searching users by name', [
            'search_term' => $searchTerm,
            'limit' => $limit,
        ]);

        $query = User::query()
            ->where(function (Builder $q) use ($searchTerm): void {
                $q->where('name', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('first_name', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('last_name', 'LIKE', '%' . $searchTerm . '%');
            })
            ->orderByRaw('CASE 
                WHEN name = ? THEN 1
                WHEN name LIKE ? THEN 2
                WHEN name LIKE ? THEN 3
                ELSE 4
                END', [$searchTerm, $searchTerm . '%', '%' . $searchTerm . '%'])
            ->orderBy('name')
            ->limit($limit);

        $users = $query->get();

        $this->log->info('Name search completed', [
            'search_term' => $searchTerm,
            'results_count' => $users->count(),
        ]);

        return $users;
    }

    /**
     * Find users by email.
     *
     * @param string $email Email to search for
     * @param bool $exactMatch Whether to use exact match or partial match
     * @return Collection Collection of User models
     */
    public function findUsersByEmail(string $email, bool $exactMatch = false): Collection
    {
        if (empty(trim($email))) {
            return new Collection();
        }

        $searchTerm = trim($email);

        $this->log->info('Searching users by email', [
            'search_term' => $searchTerm,
            'exact_match' => $exactMatch,
        ]);

        $query = User::query();

        if ($exactMatch) {
            $query->where('email', $searchTerm);
        } else {
            $query->where('email', 'LIKE', '%' . $searchTerm . '%');
        }

        $users = $query->orderBy('email')->get();

        $this->log->info('Email search completed', [
            'search_term' => $searchTerm,
            'exact_match' => $exactMatch,
            'results_count' => $users->count(),
        ]);

        return $users;
    }

    /**
     * Find users by angel type.
     *
     * @param string|int|AngelType $angelType Angel type to search for
     * @param array $additionalCriteria Additional search criteria
     * @return Collection Collection of User models
     */
    public function findUsersByAngelType(string|int|AngelType $angelType, array $additionalCriteria = []): Collection
    {
        $this->log->info('Searching users by angel type', [
            'angel_type' => is_object($angelType) ? $angelType->name : $angelType,
            'additional_criteria' => $additionalCriteria,
        ]);

        try {
            // Resolve angel type
            $angelTypeModel = $this->resolveAngelType($angelType);

            if (!$angelTypeModel) {
                $this->log->warning('Angel type not found', [
                    'angel_type' => $angelType,
                ]);
                return new Collection();
            }

            // Get users with this angel type
            $query = $angelTypeModel->users();

            // Apply additional criteria to the user query
            if (!empty($additionalCriteria)) {
                $query = $this->applySearchCriteria($query, $additionalCriteria);
            }

            $users = $query->get();

            $this->log->info('Angel type search completed', [
                'angel_type_name' => $angelTypeModel->name,
                'results_count' => $users->count(),
            ]);

            return $users;
        } catch (\Exception $e) {
            $this->log->error('Angel type search failed', [
                'angel_type' => is_object($angelType) ? $angelType->name : $angelType,
                'error' => $e->getMessage(),
            ]);

            return new Collection();
        }
    }

    /**
     * Get users with activity filters.
     *
     * @param array $activityCriteria Activity-based search criteria
     * @return Collection Collection of User models
     */
    public function getUsersByActivity(array $activityCriteria): Collection
    {
        $this->log->info('Searching users by activity', [
            'criteria' => $activityCriteria,
        ]);

        try {
            $query = User::query();

            // Apply activity-based filters
            if (isset($activityCriteria['has_shifts'])) {
                if ($activityCriteria['has_shifts']) {
                    $query->whereHas('shiftEntries');
                } else {
                    $query->whereDoesntHave('shiftEntries');
                }
            }

            if (isset($activityCriteria['last_login_after'])) {
                $date = $this->parseDate($activityCriteria['last_login_after']);
                $query->where('last_login_at', '>', $date);
            }

            if (isset($activityCriteria['last_login_before'])) {
                $date = $this->parseDate($activityCriteria['last_login_before']);
                $query->where('last_login_at', '<', $date);
            }

            if (isset($activityCriteria['created_after'])) {
                $date = $this->parseDate($activityCriteria['created_after']);
                $query->where('created_at', '>', $date);
            }

            if (isset($activityCriteria['created_before'])) {
                $date = $this->parseDate($activityCriteria['created_before']);
                $query->where('created_at', '<', $date);
            }

            // Apply sorting
            $sortBy = $activityCriteria['sort_by'] ?? 'last_login_at';
            $sortDirection = $activityCriteria['sort_direction'] ?? 'desc';

            if (in_array($sortBy, self::VALID_SORT_FIELDS)) {
                $query->orderBy($sortBy, $sortDirection);
            } else {
                $query->orderBy('last_login_at', 'desc');
            }

            $users = $query->get();

            $this->log->info('Activity search completed', [
                'results_count' => $users->count(),
            ]);

            return $users;
        } catch (\Exception $e) {
            $this->log->error('Activity search failed', [
                'criteria' => $activityCriteria,
                'error' => $e->getMessage(),
            ]);

            return new Collection();
        }
    }

    /**
     * Build search query from criteria.
     *
     * @param array $criteria Search criteria
     * @return Builder Eloquent query builder
     */
    protected function buildSearchQuery(array $criteria): Builder
    {
        $query = User::query();

        return $this->applySearchCriteria($query, $criteria);
    }

    /**
     * Apply search criteria to a query builder.
     *
     * @param Builder $query Query builder to modify
     * @param array $criteria Search criteria to apply
     * @return Builder Modified query builder
     */
    protected function applySearchCriteria(Builder $query, array $criteria): Builder
    {
        // Name search
        if (!empty($criteria['name'])) {
            $query->where(function (Builder $q) use ($criteria): void {
                $searchTerm = $criteria['name'];
                $q->where('name', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('first_name', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('last_name', 'LIKE', '%' . $searchTerm . '%');
            });
        }

        // Email search
        if (!empty($criteria['email'])) {
            if ($criteria['email_exact_match'] ?? false) {
                $query->where('email', $criteria['email']);
            } else {
                $query->where('email', 'LIKE', '%' . $criteria['email'] . '%');
            }
        }

        // ID search
        if (!empty($criteria['user_id'])) {
            $query->where('id', $criteria['user_id']);
        }

        if (!empty($criteria['user_ids']) && is_array($criteria['user_ids'])) {
            $query->whereIn('id', $criteria['user_ids']);
        }

        // Angel type filter
        if (!empty($criteria['angel_type'])) {
            $angelType = $this->resolveAngelType($criteria['angel_type']);
            if ($angelType) {
                $query->whereHas('userAngelTypes', function (Builder $q) use ($angelType): void {
                    $q->where('angel_type_id', $angelType->id);
                });
            }
        }

        // Multiple angel types
        if (!empty($criteria['angel_types']) && is_array($criteria['angel_types'])) {
            $angelTypeIds = [];
            foreach ($criteria['angel_types'] as $angelType) {
                $resolved = $this->resolveAngelType($angelType);
                if ($resolved) {
                    $angelTypeIds[] = $resolved->id;
                }
            }

            if (!empty($angelTypeIds)) {
                $query->whereHas('userAngelTypes', function (Builder $q) use ($angelTypeIds): void {
                    $q->whereIn('angel_type_id', $angelTypeIds);
                });
            }
        }

        // Activity filters
        if (isset($criteria['has_shifts'])) {
            if ($criteria['has_shifts']) {
                $query->whereHas('shiftEntries');
            } else {
                $query->whereDoesntHave('shiftEntries');
            }
        }

        // Date filters
        if (!empty($criteria['last_login_after'])) {
            $date = $this->parseDate($criteria['last_login_after']);
            $query->where('last_login_at', '>', $date);
        }

        if (!empty($criteria['last_login_before'])) {
            $date = $this->parseDate($criteria['last_login_before']);
            $query->where('last_login_at', '<', $date);
        }

        if (!empty($criteria['created_after'])) {
            $date = $this->parseDate($criteria['created_after']);
            $query->where('created_at', '>', $date);
        }

        if (!empty($criteria['created_before'])) {
            $date = $this->parseDate($criteria['created_before']);
            $query->where('created_at', '<', $date);
        }

        // Exclude users
        if (!empty($criteria['exclude_user_ids']) && is_array($criteria['exclude_user_ids'])) {
            $query->whereNotIn('id', $criteria['exclude_user_ids']);
        }

        // Apply sorting
        $this->applySorting($query, $criteria);

        // Apply limit
        if (isset($criteria['limit']) && $criteria['limit'] > 0) {
            $query->limit(min($criteria['limit'], 1000)); // Max limit of 1000
        }

        return $query;
    }

    /**
     * Apply sorting to query.
     *
     * @param Builder $query Query to modify
     * @param array $criteria Search criteria
     */
    protected function applySorting(Builder $query, array $criteria): void
    {
        $sortBy = $criteria['sort_by'] ?? 'name';
        $sortDirection = $criteria['sort_direction'] ?? 'asc';

        // Validate sort parameters
        if (!in_array($sortBy, self::VALID_SORT_FIELDS)) {
            $sortBy = 'name';
        }

        if (!in_array($sortDirection, self::VALID_SORT_DIRECTIONS)) {
            $sortDirection = 'asc';
        }

        $query->orderBy($sortBy, $sortDirection);

        // Add secondary sort by ID for consistency
        if ($sortBy !== 'id') {
            $query->orderBy('id', $sortDirection);
        }
    }

    /**
     * Resolve angel type from various input types.
     *
     * @param mixed $angelType Angel type identifier
     * @return AngelType|null Resolved angel type model
     */
    protected function resolveAngelType(mixed $angelType): ?AngelType
    {
        if ($angelType instanceof AngelType) {
            return $angelType;
        }

        if (is_numeric($angelType)) {
            return AngelType::find($angelType);
        }

        if (is_string($angelType)) {
            return AngelType::where('name', $angelType)->first();
        }

        return null;
    }

    /**
     * Parse date from various formats.
     *
     * @param mixed $date Date to parse
     * @return \Carbon\Carbon Parsed date
     * @throws InvalidArgumentException if date cannot be parsed
     */
    protected function parseDate(mixed $date): \Carbon\Carbon
    {
        try {
            if ($date instanceof \Carbon\Carbon) {
                return $date;
            }

            if ($date instanceof \DateTime) {
                return \Carbon\Carbon::instance($date);
            }

            return \Carbon\Carbon::parse($date);
        } catch (\Exception $e) {
            throw new InvalidArgumentException('Invalid date format: ' . $date);
        }
    }

    /**
     * Validate search criteria.
     *
     * @param array $criteria Criteria to validate
     * @throws InvalidArgumentException if criteria is invalid
     */
    public function validateSearchCriteria(array $criteria): void
    {
        // Validate sort parameters
        if (isset($criteria['sort_by']) && !in_array($criteria['sort_by'], self::VALID_SORT_FIELDS)) {
            throw new InvalidArgumentException(
                'Invalid sort field: ' . $criteria['sort_by'] .
                '. Must be one of: ' . implode(', ', self::VALID_SORT_FIELDS)
            );
        }

        if (isset($criteria['sort_direction']) && !in_array($criteria['sort_direction'], self::VALID_SORT_DIRECTIONS)) {
            throw new InvalidArgumentException(
                'Invalid sort direction: ' . $criteria['sort_direction'] .
                '. Must be one of: ' . implode(', ', self::VALID_SORT_DIRECTIONS)
            );
        }

        // Validate limit
        if (isset($criteria['limit']) && (!is_numeric($criteria['limit']) || $criteria['limit'] <= 0)) {
            throw new InvalidArgumentException('Limit must be a positive number');
        }

        // Validate user IDs
        if (isset($criteria['user_ids']) && (!is_array($criteria['user_ids']) || empty($criteria['user_ids']))) {
            throw new InvalidArgumentException('user_ids must be a non-empty array');
        }

        // Validate date formats
        $dateFields = ['last_login_after', 'last_login_before', 'created_after', 'created_before'];
        foreach ($dateFields as $field) {
            if (isset($criteria[$field])) {
                try {
                    $this->parseDate($criteria[$field]);
                } catch (InvalidArgumentException $e) {
                    throw new InvalidArgumentException('Invalid date format for ' . $field . ': ' . $criteria[$field]);
                }
            }
        }
    }

    /**
     * Search users by various criteria - backstage version
     * Supports searching by username (nickname), badge ID, or digital-id token
     *
     * @param string $query The search query
     * @param int $limit Maximum number of results to return
     * @return Collection<User>
     */
    public function searchUsersBackstage(string $query, int $limit = 50): Collection
    {
        if (empty(trim($query))) {
            return new Collection();
        }

        $query = trim($query);
        $results = new Collection();

        $this->log->info('Starting backstage user search', [
            'query' => $query,
            'limit' => $limit,
        ]);

        // Search by username (nickname)
        $usernameResults = $this->searchByUsername($query, $limit);
        $results = $results->merge($usernameResults);

        // Search by badge ID if query is numeric
        if (is_numeric($query)) {
            $badgeResults = $this->searchByBadgeId((int) $query, $limit);
            $results = $results->merge($badgeResults);
        }

        // Search by digital-id token (barcode scanner)
        $tokenResults = $this->searchByDigitalIdToken($query, $limit);
        $results = $results->merge($tokenResults);

        // Remove duplicates and limit results
        $finalResults = $results->unique('id')->take($limit);

        $this->log->info('Backstage user search completed', [
            'query' => $query,
            'results_count' => $finalResults->count(),
        ]);

        return $finalResults;
    }

    /**
     * Search users by username (nickname)
     */
    public function searchByUsername(string $query, int $limit = 50): Collection
    {
        return User::query()
            ->where('name', 'LIKE', '%' . $query . '%')
            ->with(['personalData', 'state'])
            ->limit($limit)
            ->get();
    }

    /**
     * Search users by badge ID
     */
    public function searchByBadgeId(int $badgeId, int $limit = 50): Collection
    {
        return User::query()
            ->whereHas('personalData', function ($query) use ($badgeId): void {
                $query->where('badge_number', $badgeId);
            })
            ->with(['personalData', 'state'])
            ->limit($limit)
            ->get();
    }

    /**
     * Search users by digital-id token (from barcode scanner)
     * Supports both raw tokens and full digital-id URLs
     */
    public function searchByDigitalIdToken(string $tokenOrUrl, int $limit = 50): Collection
    {
        // Extract token from URL if it's a full digital-id URL
        $token = $this->extractDigitalIdToken($tokenOrUrl);

        if (empty($token)) {
            return new Collection();
        }

        $this->log->info('Searching by digital-id token', [
            'original_input' => $tokenOrUrl,
            'extracted_token' => $token,
        ]);

        // Find active token first
        $digitalIdToken = DigitalIdToken::findActiveToken($token);

        if (!$digitalIdToken) {
            $this->log->info('No active digital-id token found', [
                'token' => $token,
            ]);
            return new Collection();
        }

        // Return the user associated with the token
        $user = $digitalIdToken->user()->with(['personalData', 'state'])->first();

        if ($user) {
            $this->log->info('User found by digital-id token', [
                'token' => $token,
                'user_id' => $user->id,
                'username' => $user->name,
            ]);
        }

        return $user ? new Collection([$user]) : new Collection();
    }

    /**
     * Extract digital-id token from URL or return as-is if it's already a token
     * Supports URLs like: http://vmdev.internal/digital-id/verify/TOKEN
     */
    public function extractDigitalIdToken(string $input): ?string
    {
        $input = trim($input);

        // If input is empty, return null
        if (empty($input)) {
            return null;
        }

        // Check if it's a URL (contains http:// or https://)
        if (preg_match('/^https?:\/\//', $input)) {
            // Parse the URL to extract the path
            $parsedUrl = parse_url($input);

            if (!$parsedUrl || !isset($parsedUrl['path'])) {
                $this->log->warning('Invalid URL format for digital-id extraction', [
                    'input' => $input,
                ]);
                return null;
            }

            $path = $parsedUrl['path'];

            // Extract token from path like /digital-id/verify/TOKEN
            if (preg_match('/\/digital-id\/verify\/([a-f0-9]{64})$/i', $path, $matches)) {
                return $matches[1];
            }

            $this->log->warning('URL does not match digital-id verify pattern', [
                'input' => $input,
                'path' => $path,
            ]);
            return null;
        }

        // If it's not a URL, check if it looks like a token (64 hex characters)
        if (preg_match('/^[a-f0-9]{64}$/i', $input)) {
            return $input;
        }

        // Check if it might be a partial URL path like /digital-id/verify/TOKEN
        if (preg_match('/\/digital-id\/verify\/([a-f0-9]{64})$/i', $input, $matches)) {
            return $matches[1];
        }

        // Not a recognizable digital-id format
        return null;
    }

    /**
     * Get detailed user information for display (data protection compliant)
     */
    public function getUserDetails(User $user): array
    {
        $personalData = $user->personalData;
        $state = $user->state;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'display_name' => $user->displayName ?? $user->name,
            // email removed for data protection compliance
            'badge_number' => $personalData?->badge_number ?? null,
            'first_name' => $personalData?->first_name ?? null,
            'last_name' => $personalData?->last_name ?? null,
            'pronoun' => $personalData?->pronoun ?? null,
            'arrived' => $state?->arrived ?? false,
            'active' => $state?->active ?? false,
            'force_active' => $state?->force_active ?? false,
            'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
            'last_login_at' => $user->last_login_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Format search results for API response
     */
    public function formatSearchResults(Collection $users): array
    {
        return $users->map(function (User $user) {
            return $this->getUserDetails($user);
        })->toArray();
    }
}

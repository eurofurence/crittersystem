<?php

declare(strict_types=1);

namespace Engelsystem\Services;

use Engelsystem\Models\Certification;
use Engelsystem\Models\CertificationUser;
use Engelsystem\Models\User\User;
use Engelsystem\Models\AngelType;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;
use InvalidArgumentException;
use RuntimeException;
use Psr\Log\LoggerInterface;

class CertificationService
{
    public function __construct(
        protected LoggerInterface $log
    ) {
    }
    /**
     * Get all certifications with optional filtering.
     */
    public function getAllCertifications(array $filters = []): Collection
    {
        $query = Certification::query();

        // Apply filters
        if (isset($filters['active'])) {
            $query->where('is_active', (bool) $filters['active']);
        }

        if (isset($filters['perpetual'])) {
            if ($filters['perpetual']) {
                $query->where('is_perpetual', true);
            } else {
                $query->where('is_perpetual', false);
            }
        }

        if (isset($filters['self_confirmable'])) {
            $query->where('allow_self_confirmation', (bool) $filters['self_confirmable']);
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'LIKE', '%' . $search . '%')
                  ->orWhere('description', 'LIKE', '%' . $search . '%')
                  ->orWhere('contact_person', 'LIKE', '%' . $search . '%');
            });
        }

        return $query->orderBy('title')->get();
    }

    /**
     * Get paginated certifications with filters.
     */
    public function getPaginatedCertifications(int $perPage = 15, array $filters = []): array
    {
        $query = Certification::query();

        // Apply search filter
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'LIKE', '%' . $search . '%')
                  ->orWhere('description', 'LIKE', '%' . $search . '%')
                  ->orWhere('contact_person', 'LIKE', '%' . $search . '%');
            });
        }

        // Apply status filters
        if (isset($filters['active'])) {
            $query->where('is_active', $filters['active']);
        }

        if (isset($filters['perpetual'])) {
            $query->where('is_perpetual', $filters['perpetual']);
        }

        if (isset($filters['self_confirmable'])) {
            $query->where('allow_self_confirmation', $filters['self_confirmable']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'title';
        $sortDirection = $filters['sort_direction'] ?? 'asc';

        $allowedSortFields = ['title', 'created_at', 'updated_at', 'validity_period_days'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('title', 'asc');
        }

        // Get page from request
        $page = max(1, (int) request()->get('page', 1));

        // Get total count
        $total = $query->count();
        $pagesCount = max(1, ceil($total / $perPage));
        $page = max(1, min($page, $pagesCount));

        // Get paginated results
        $certifications = $query
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        return [
            'data' => $certifications,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $pagesCount,
            'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to' => min($page * $perPage, $total),
        ];
    }

    /**
     * Create a new certification.
     */
    public function createCertification(array $data): Certification
    {
        $this->validateCertificationData($data);

        // Handle perpetual certification logic
        if ($data['is_perpetual'] ?? false) {
            $data['validity_period_days'] = null;
        }

        $certification = Certification::create($data);

        $this->log->info('Certification created', [
            'certification_uuid' => $certification->uuid,
            'title' => $certification->title,
            'is_perpetual' => $certification->is_perpetual,
            'allow_self_confirmation' => $certification->allow_self_confirmation,
            'is_active' => $certification->is_active,
            'created_by' => auth()->user()?->name ?? 'system',
            'created_by_id' => auth()->user()?->id,
        ]);

        return $certification;
    }

    /**
     * Update an existing certification.
     */
    public function updateCertification(Certification $certification, array $data): Certification
    {
        $this->validateCertificationData($data, $certification);

        // Capture old values for audit trail
        $oldData = [
            'title' => $certification->title,
            'description' => $certification->description,
            'is_perpetual' => $certification->is_perpetual,
            'validity_period_days' => $certification->validity_period_days,
            'allow_self_confirmation' => $certification->allow_self_confirmation,
            'is_active' => $certification->is_active,
            'contact_person' => $certification->contact_person,
            'contact_email' => $certification->contact_email,
        ];

        // Handle perpetual certification logic
        if ($data['is_perpetual'] ?? false) {
            $data['validity_period_days'] = null;
        }

        $certification->update($data);
        $updatedCertification = $certification->fresh();

        // Log changes
        $changes = [];
        foreach ($oldData as $field => $oldValue) {
            $newValue = $updatedCertification->$field;
            if ($oldValue !== $newValue) {
                $changes[$field] = ['from' => $oldValue, 'to' => $newValue];
            }
        }

        $this->log->info('Certification updated', [
            'certification_uuid' => $certification->uuid,
            'title' => $certification->title,
            'changes' => $changes,
            'updated_by' => auth()->user()?->name ?? 'system',
            'updated_by_id' => auth()->user()?->id,
        ]);

        return $updatedCertification;
    }

    /**
     * Delete a certification if no user data is linked.
     */
    public function deleteCertification(Certification $certification): bool
    {
        // Check if any users have this certification
        $userCount = CertificationUser::where('certification_id', $certification->id)->count();

        if ($userCount > 0) {
            $this->log->warning('Attempted to delete certification with linked users', [
                'certification_uuid' => $certification->uuid,
                'title' => $certification->title,
                'linked_users' => $userCount,
                'attempted_by' => auth()->user()?->name ?? 'system',
                'attempted_by_id' => auth()->user()?->id,
            ]);

            throw new RuntimeException(
                'Cannot delete certification ' . $certification->title . ' as it is linked to {$userCount} user(s). ' .
                'Consider deactivating it instead.'
            );
        }

        // Check if any angel types require this certification
        $angelTypeCount = $certification->angelTypes()->count();

        if ($angelTypeCount > 0) {
            $this->log->warning('Attempted to delete certification required by angel types', [
                'certification_uuid' => $certification->uuid,
                'title' => $certification->title,
                'required_by_angel_types' => $angelTypeCount,
                'attempted_by' => auth()->user()?->name ?? 'system',
                'attempted_by_id' => auth()->user()?->id,
            ]);

            throw new RuntimeException(
                'Cannot delete certification ' . $certification->title .
                ' as it is required by ' . $angelTypeCount . ' angel type(s). ' .
                'Remove the requirements first or deactivate the certification instead.'
            );
        }

        // Log successful deletion
        $this->log->info('Certification deleted', [
            'certification_uuid' => $certification->uuid,
            'title' => $certification->title,
            'description' => $certification->description,
            'deleted_by' => auth()->user()?->name ?? 'system',
            'deleted_by_id' => auth()->user()?->id,
        ]);

        return $certification->delete();
    }

    /**
     * Deactivate a certification (safer alternative to deletion).
     */
    public function deactivateCertification(Certification $certification): Certification
    {
        $wasActive = $certification->is_active;
        $certification->update(['is_active' => false]);

        if ($wasActive) {
            $this->log->info('Certification deactivated', [
                'certification_uuid' => $certification->uuid,
                'title' => $certification->title,
                'deactivated_by' => auth()->user()?->name ?? 'system',
                'deactivated_by_id' => auth()->user()?->id,
            ]);
        }

        return $certification->fresh();
    }

    /**
     * Reactivate a certification.
     */
    public function reactivateCertification(Certification $certification): Certification
    {
        $certification->update(['is_active' => true]);
        return $certification->fresh();
    }

    /**
     * Get a certification by UUID.
     */
    public function getCertificationByUuid(string $uuid): ?Certification
    {
        return Certification::where('uuid', $uuid)->first();
    }

    /**
     * Get a certification by UUID or fail.
     */
    public function getCertificationByUuidOrFail(string $uuid): Certification
    {
        $certification = $this->getCertificationByUuid($uuid);

        if (!$certification) {
            throw new InvalidArgumentException('Certification with UUID ' . $uuid . ' not found.');
        }

        return $certification;
    }

    /**
     * Get all certifications for a specific user.
     */
    public function getUserCertifications(User $user, array $filters = []): Collection
    {
        $query = $user->certifications();

        // Apply status filter
        if (isset($filters['status'])) {
            $query->wherePivot('status', $filters['status']);
        }

        // Apply validity filter
        if (isset($filters['valid_only']) && $filters['valid_only']) {
            $query->whereHas('pivot', function ($q): void {
                $q->where(function ($subQuery): void {
                    $subQuery->where('status', 'approved')
                             ->orWhere('status', 'self_confirmed');
                })->where(function ($subQuery): void {
                    $subQuery->whereNull('date_expires')
                             ->orWhere('date_expires', '>', Carbon::now());
                });
            });
        }

        // Apply expiry filter
        if (isset($filters['expired_only']) && $filters['expired_only']) {
            $query->whereHas('pivot', function ($q): void {
                $q->where('date_expires', '<=', Carbon::now())
                  ->whereNotNull('date_expires');
            });
        }

        return $query->withPivot([
            'status', 'date_certified', 'date_expires', 'certified_by', 'notes', 'created_at', 'updated_at',
        ])->orderBy('certifications.title')->get();
    }

    /**
     * Add a certification to a user.
     */
    public function addCertificationToUser(
        User $user,
        Certification $certification,
        string $status = 'pending',
        ?User $certifiedBy = null,
        ?string $notes = null,
        ?Carbon $dateCertified = null,
        ?Carbon $dateExpires = null
    ): CertificationUser {
        // Validate status
        $validStatuses = ['pending', 'approved', 'self_confirmed', 'revoked', 'expired'];
        if (!in_array($status, $validStatuses)) {
            throw new InvalidArgumentException(
                'Invalid status ' . $status .
                '. Must be one of: ' . implode(', ', $validStatuses)
            );
        }

        // Check if user already has this certification
        $existingCertification = CertificationUser::where('user_id', $user->id)
            ->where('certification_id', $certification->id)
            ->first();

        if ($existingCertification) {
            $this->log->warning('Attempted to add duplicate certification to user', [
                'user' => $user->name,
                'user_id' => $user->id,
                'certification' => $certification->title,
                'certification_uuid' => $certification->uuid,
                'existing_status' => $existingCertification->status,
                'attempted_by' => auth()->user()?->name ?? 'system',
                'attempted_by_id' => auth()->user()?->id,
            ]);

            throw new RuntimeException(
                'User ' . $user->name . ' already has certification ' . $certification->title . '. ' .
                'Use updateUserCertification() to modify existing certifications.'
            );
        }

        // Calculate expiry date if not provided
        if (!$dateExpires && !$certification->is_perpetual && in_array($status, ['approved', 'self_confirmed'])) {
            $dateExpires = $this->calculateExpirationDate($certification, $dateCertified ?? Carbon::now());
        }

        // Set default certification date for approved/self_confirmed status
        if (!$dateCertified && in_array($status, ['approved', 'self_confirmed'])) {
            $dateCertified = Carbon::now();
        }

        $certificationUser = CertificationUser::create([
            'user_id' => $user->id,
            'certification_id' => $certification->id,
            'status' => $status,
            'date_certified' => $dateCertified,
            'date_expires' => $dateExpires,
            'certified_by' => $certifiedBy?->id,
            'notes' => $notes,
        ]);

        $this->log->info('Certification added to user', [
            'user' => $user->name,
            'user_id' => $user->id,
            'certification' => $certification->title,
            'certification_uuid' => $certification->uuid,
            'status' => $status,
            'date_certified' => $dateCertified?->toDateTimeString(),
            'date_expires' => $dateExpires?->toDateTimeString() ?? 'never',
            'certified_by' => $certifiedBy?->name,
            'certified_by_id' => $certifiedBy?->id,
            'assigned_by' => auth()->user()?->name ?? 'system',
            'assigned_by_id' => auth()->user()?->id,
            'notes' => $notes ? substr($notes, 0, 100) . (strlen($notes) > 100 ? '...' : '') : null,
        ]);

        return $certificationUser;
    }

    /**
     * Update an existing user certification.
     */
    public function updateUserCertification(
        CertificationUser $userCertification,
        array $data
    ): CertificationUser {
        // Capture old values for audit trail
        $oldData = [
            'status' => $userCertification->status,
            'date_certified' => $userCertification->date_certified?->toDateTimeString(),
            'date_expires' => $userCertification->date_expires?->toDateTimeString(),
            'notes' => $userCertification->notes,
            'certified_by' => $userCertification->certified_by,
        ];

        // Validate status if provided
        if (isset($data['status'])) {
            $validStatuses = ['pending', 'approved', 'self_confirmed', 'revoked', 'expired'];
            if (!in_array($data['status'], $validStatuses)) {
                throw new InvalidArgumentException(
                    'Invalid status ' . $data['status'] . '. Must be one of: ' . implode(', ', $validStatuses)
                );
            }
        }

        // Handle automatic expiry calculation
        if (isset($data['status']) && in_array($data['status'], ['approved', 'self_confirmed'])) {
            $certification = $userCertification->certification;

            // Set certification date if not provided and status is being approved
            if (!isset($data['date_certified']) && !$userCertification->date_certified) {
                $data['date_certified'] = Carbon::now();
            }

            // Calculate expiry date if not provided and certification is not perpetual
            if (!isset($data['date_expires']) && !$certification->is_perpetual) {
                $certificationDate = $data['date_certified'] ?? $userCertification->date_certified ?? Carbon::now();
                $data['date_expires'] = $this->calculateExpirationDate($certification, $certificationDate);
            }
        }

        $userCertification->update($data);
        $updatedCertification = $userCertification->fresh();

        // Log changes
        $changes = [];
        foreach ($oldData as $field => $oldValue) {
            $newValue = match ($field) {
                'date_certified' => $updatedCertification->date_certified?->toDateTimeString(),
                'date_expires' => $updatedCertification->date_expires?->toDateTimeString(),
                default => $updatedCertification->$field
            };

            if ($oldValue !== $newValue) {
                $changes[$field] = ['from' => $oldValue, 'to' => $newValue];
            }
        }

        if (!empty($changes)) {
            $this->log->info('User certification updated', [
                'user' => $userCertification->user->name,
                'user_id' => $userCertification->user_id,
                'certification' => $userCertification->certification->title,
                'certification_uuid' => $userCertification->certification->uuid,
                'changes' => $changes,
                'updated_by' => auth()->user()?->name ?? 'system',
                'updated_by_id' => auth()->user()?->id,
            ]);
        }

        return $updatedCertification;
    }

    /**
     * Remove/revoke a certification from a user.
     */
    public function removeCertificationFromUser(
        User $user,
        Certification $certification,
        bool $softRevoke = true,
        ?string $revocationReason = null
    ): bool {
        $userCertification = CertificationUser::where('user_id', $user->id)
            ->where('certification_id', $certification->id)
            ->first();

        if (!$userCertification) {
            throw new InvalidArgumentException(
                'User ' . $user->name . 'does not have certification ' . $certification->title
            );
        }

        if ($softRevoke) {
            // Soft revocation - change status to revoked
            $userCertification->update([
                'status' => 'revoked',
                'notes' => $revocationReason ?
                    // phpcs:ignore
                    ($userCertification->notes ? $userCertification->notes . "\n\nRevoked: " . $revocationReason : 'Revoked: ' . $revocationReason) :
                    ($userCertification->notes ?? 'Certification revoked'),
            ]);
            return true;
        } else {
            // Hard deletion - permanently remove the record
            return $userCertification->delete();
        }
    }

    /**
     * Calculate expiration date for a certification.
     */
    public function calculateExpirationDate(Certification $certification, ?Carbon $certificationDate = null): ?Carbon
    {
        if ($certification->is_perpetual) {
            return null;
        }

        if (!$certification->validity_period_days || $certification->validity_period_days <= 0) {
            throw new InvalidArgumentException(
                'Cannot calculate expiration for certification ' . $certification->title . ': invalid validity period'
            );
        }

        $startDate = $certificationDate ?? Carbon::now();
        return $startDate->copy()->addDays($certification->validity_period_days);
    }

    /**
     * Mass revoke a certification for all users who have it.
     */
    public function massRevokeCertification(
        Certification $certification,
        ?string $revocationReason = null,
        array $statusFilter = ['approved', 'self_confirmed', 'pending']
    ): int {
        // Get all user certifications for this certification with specified statuses
        $query = CertificationUser::with('user')->where('certification_id', $certification->id);

        if (!empty($statusFilter)) {
            $query->whereIn('status', $statusFilter);
        }

        $userCertifications = $query->get();

        if ($userCertifications->isEmpty()) {
            $this->log->info('Mass revocation attempted but no eligible certifications found', [
                'certification' => $certification->title,
                'certification_uuid' => $certification->uuid,
                'status_filter' => $statusFilter,
                'initiated_by' => auth()->user()?->name ?? 'system',
                'initiated_by_id' => auth()->user()?->id,
            ]);
            return 0;
        }

        $revokedCount = 0;
        $revocationNote = $revocationReason ?? 'Mass revocation of certification ' . $certification->title;
        $affectedUsers = [];

        foreach ($userCertifications as $userCertification) {
            $oldStatus = $userCertification->status;

            // Prepare notes update
            $existingNotes = $userCertification->notes;
            $updatedNotes = $existingNotes
                ? $existingNotes . "\n\n" . Carbon::now()->format('Y-m-d H:i:s') . ' - ' . $revocationNote
                : Carbon::now()->format('Y-m-d H:i:s') . ' - ' . $revocationNote;

            // Update the certification status
            $userCertification->update([
                'status' => 'revoked',
                'notes' => $updatedNotes,
            ]);

            $affectedUsers[] = [
                'user_id' => $userCertification->user_id,
                'user_name' => $userCertification->user->name,
                'old_status' => $oldStatus,
            ];

            $revokedCount++;
        }

        $this->log->info('Mass revocation completed', [
            'certification' => $certification->title,
            'certification_uuid' => $certification->uuid,
            'revoked_count' => $revokedCount,
            'reason' => $revocationNote,
            'status_filter' => $statusFilter,
            'affected_users' => $affectedUsers,
            'initiated_by' => auth()->user()?->name ?? 'system',
            'initiated_by_id' => auth()->user()?->id,
        ]);

        return $revokedCount;
    }

    /**
     * Mass revoke specific user certifications by IDs.
     */
    public function massRevokeUserCertifications(
        array $userCertificationIds,
        ?string $revocationReason = null
    ): int {
        if (empty($userCertificationIds)) {
            return 0;
        }

        $userCertifications = CertificationUser::whereIn('id', $userCertificationIds)
            ->whereIn('status', ['approved', 'self_confirmed', 'pending'])
            ->get();

        if ($userCertifications->isEmpty()) {
            return 0;
        }

        $revokedCount = 0;
        $revocationNote = $revocationReason ?? 'Certification revoked';

        foreach ($userCertifications as $userCertification) {
            // Prepare notes update
            $existingNotes = $userCertification->notes;
            $updatedNotes = $existingNotes
                ? $existingNotes . "\n\n" . Carbon::now()->format('Y-m-d H:i:s') . ' - ' . $revocationNote
                : Carbon::now()->format('Y-m-d H:i:s') . ' - ' . $revocationNote;

            // Update the certification status
            $userCertification->update([
                'status' => 'revoked',
                'notes' => $updatedNotes,
            ]);

            $revokedCount++;
        }

        return $revokedCount;
    }

    /**
     * Check if a user meets certification requirements for an angel type.
     */
    public function checkUserCertificationRequirements(User $user, AngelType $angelType): array
    {
        // Get all required certifications for this angel type
        $requiredCertifications = $angelType->requiredCertifications;

        if ($requiredCertifications->isEmpty()) {
            return [
                'meets_requirements' => true,
                'required_certifications' => [],
                'user_certifications' => [],
                'missing_certifications' => [],
                'expired_certifications' => [],
                'pending_certifications' => [],
            ];
        }

        // Get user's current certifications
        $userCertifications = $this->getUserCertifications($user);

        // Create lookup array for user certifications by certification ID
        $userCertLookup = [];
        foreach ($userCertifications as $cert) {
            $userCertLookup[$cert->id] = $cert;
        }

        $missingCertifications = [];
        $expiredCertifications = [];
        $pendingCertifications = [];
        $validCertifications = [];

        foreach ($requiredCertifications as $requiredCert) {
            if (!isset($userCertLookup[$requiredCert->id])) {
                // User doesn't have this certification at all
                $missingCertifications[] = $requiredCert;
            } else {
                $userCert = $userCertLookup[$requiredCert->id];
                $pivotData = $userCert->pivot;

                switch ($pivotData->status) {
                    case 'pending':
                        $pendingCertifications[] = [
                            'certification' => $requiredCert,
                            'user_certification' => $pivotData,
                        ];
                        break;

                    case 'approved':
                    case 'self_confirmed':
                        // Check if expired
                        if ($pivotData->date_expires && Carbon::parse($pivotData->date_expires)->isPast()) {
                            $expiredCertifications[] = [
                                'certification' => $requiredCert,
                                'user_certification' => $pivotData,
                                'expired_date' => $pivotData->date_expires,
                            ];
                        } else {
                            $validCertifications[] = [
                                'certification' => $requiredCert,
                                'user_certification' => $pivotData,
                            ];
                        }
                        break;

                    case 'revoked':
                    case 'expired':
                        $missingCertifications[] = $requiredCert;
                        break;
                }
            }
        }

        $meetsRequirements = empty($missingCertifications) &&
                           empty($expiredCertifications) &&
                           empty($pendingCertifications);

        return [
            'meets_requirements' => $meetsRequirements,
            'required_certifications' => $requiredCertifications->toArray(),
            'user_certifications' => $validCertifications,
            'missing_certifications' => $missingCertifications,
            'expired_certifications' => $expiredCertifications,
            'pending_certifications' => $pendingCertifications,
        ];
    }

    /**
     * Get a summary of certification requirements compliance for a user across all angel types.
     */
    public function getUserCertificationCompliance(User $user): array
    {
        $angelTypes = AngelType::with('requiredCertifications')->get();
        $compliance = [];

        foreach ($angelTypes as $angelType) {
            $requirements = $this->checkUserCertificationRequirements($user, $angelType);

            $compliance[] = [
                'angel_type' => $angelType,
                'compliance_status' => $requirements['meets_requirements'] ? 'compliant' : 'non_compliant',
                'requirements_check' => $requirements,
            ];
        }

        return $compliance;
    }

    /**
     * Get users who don't meet certification requirements for a specific angel type.
     */
    public function getNonCompliantUsersForAngelType(AngelType $angelType): Collection
    {
        $requiredCertifications = $angelType->requiredCertifications;

        if ($requiredCertifications->isEmpty()) {
            return collect();
        }

        // Get all users who have this angel type
        $users = $angelType->users()->get();
        $nonCompliantUsers = collect();

        foreach ($users as $user) {
            $requirements = $this->checkUserCertificationRequirements($user, $angelType);

            if (!$requirements['meets_requirements']) {
                $nonCompliantUsers->push([
                    'user' => $user,
                    'compliance_issues' => [
                        'missing' => count($requirements['missing_certifications']),
                        'expired' => count($requirements['expired_certifications']),
                        'pending' => count($requirements['pending_certifications']),
                    ],
                    'details' => $requirements,
                ]);
            }
        }

        return $nonCompliantUsers;
    }

    /**
     * Allow a user to self-confirm a certification.
     */
    public function selfConfirmCertification(
        User $user,
        Certification $certification,
        ?string $notes = null
    ): CertificationUser {
        // Check if certification allows self-confirmation
        if (!$certification->allow_self_confirmation) {
            $this->log->warning('Self-confirmation attempted on non-self-confirmable certification', [
                'user' => $user->name,
                'user_id' => $user->id,
                'certification' => $certification->title,
                'certification_uuid' => $certification->uuid,
                'allow_self_confirmation' => false,
            ]);

            throw new InvalidArgumentException(
                'Certification ' . $certification->title . ' does not allow self-confirmation'
            );
        }

        // Check if certification is active
        if (!$certification->is_active) {
            $this->log->warning('Self-confirmation attempted on inactive certification', [
                'user' => $user->name,
                'user_id' => $user->id,
                'certification' => $certification->title,
                'certification_uuid' => $certification->uuid,
                'is_active' => false,
            ]);

            throw new InvalidArgumentException(
                'Certification ' . $certification->title . ' is not currently active'
            );
        }

        // Check if user already has this certification
        $existingCertification = CertificationUser::where('user_id', $user->id)
            ->where('certification_id', $certification->id)
            ->first();

        if ($existingCertification) {
            // Check current status
            switch ($existingCertification->status) {
                case 'self_confirmed':
                case 'approved':
                    // Check if expired and allow re-confirmation
                    if (
                        $existingCertification->date_expires &&
                        Carbon::parse($existingCertification->date_expires)->isPast()
                    ) {
                        $this->log->info('Self-confirmation renewal for expired certification', [
                            'user' => $user->name,
                            'user_id' => $user->id,
                            'certification' => $certification->title,
                            'certification_uuid' => $certification->uuid,
                            'old_status' => $existingCertification->status,
                            'expired_date' => $existingCertification->date_expires->toDateTimeString(),
                        ]);

                        // Update existing record with new self-confirmation
                        return $this->updateExistingCertificationForSelfConfirm(
                            $existingCertification,
                            $certification,
                            $notes
                        );
                    }

                    $this->log->warning('Self-confirmation attempted on valid certification', [
                        'user' => $user->name,
                        'user_id' => $user->id,
                        'certification' => $certification->title,
                        'certification_uuid' => $certification->uuid,
                        'current_status' => $existingCertification->status,
                        'expires' => $existingCertification->date_expires?->toDateTimeString() ?? 'never',
                    ]);

                    throw new RuntimeException(
                        'You already have a valid ' . $certification->title . ' certification'
                    );

                case 'pending':
                    $this->log->warning('Self-confirmation attempted on pending certification', [
                        'user' => $user->name,
                        'user_id' => $user->id,
                        'certification' => $certification->title,
                        'certification_uuid' => $certification->uuid,
                        'current_status' => 'pending',
                    ]);

                    throw new RuntimeException(
                        'Your ' . $certification->title . ' certification is already pending approval'
                    );

                case 'revoked':
                case 'expired':
                    $this->log->info('Self-confirmation renewal for revoked/expired certification', [
                        'user' => $user->name,
                        'user_id' => $user->id,
                        'certification' => $certification->title,
                        'certification_uuid' => $certification->uuid,
                        'old_status' => $existingCertification->status,
                    ]);

                    // Allow re-confirmation of revoked/expired certifications
                    return $this->updateExistingCertificationForSelfConfirm(
                        $existingCertification,
                        $certification,
                        $notes
                    );
            }
        }

        // Create new self-confirmed certification
        $now = Carbon::now();
        $expiryDate = $this->calculateExpirationDate($certification, $now);

        $selfConfirmNotes = $notes
            ? 'Self-confirmed: ' . $notes
            : 'Self-confirmed by user';

        $certificationUser = CertificationUser::create([
            'user_id' => $user->id,
            'certification_id' => $certification->id,
            'status' => 'self_confirmed',
            'date_certified' => $now,
            'date_expires' => $expiryDate,
            'certified_by' => null, // Self-confirmed has no certifier
            'notes' => $selfConfirmNotes,
        ]);

        $this->log->info('User self-confirmed certification', [
            'user' => $user->name,
            'user_id' => $user->id,
            'certification' => $certification->title,
            'certification_uuid' => $certification->uuid,
            'date_certified' => $now->toDateTimeString(),
            'date_expires' => $expiryDate?->toDateTimeString() ?? 'never',
            'notes' => $notes ? substr($notes, 0, 100) . (strlen($notes) > 100 ? '...' : '') : null,
        ]);

        return $certificationUser;
    }

    /**
     * Get all certifications available for self-confirmation by a user.
     */
    public function getSelfConfirmableCertifications(User $user): Collection
    {
        // Get all active certifications that allow self-confirmation
        $selfConfirmableCerts = Certification::where('is_active', true)
            ->where('allow_self_confirmation', true)
            ->get();

        // Get user's current certifications to filter out duplicates
        $userCertifications = $this->getUserCertifications($user);
        $userCertIds = $userCertifications->filter(function ($cert) {
            $status = $cert->pivot->status;
            $isExpired = $cert->pivot->date_expires &&
                        Carbon::parse($cert->pivot->date_expires)->isPast();

            // Filter out valid certifications (approved/self_confirmed and not expired)
            // Keep expired, revoked, or pending ones as they can be re-confirmed
            return !($status === 'approved' || $status === 'self_confirmed') || $isExpired;
        })->pluck('id')->toArray();

        // Return certifications that user can self-confirm
        return $selfConfirmableCerts->filter(function ($cert) use ($userCertIds) {
            // User can self-confirm if they don't have it, or if they have it but it's expired/revoked
            return !in_array($cert->id, $userCertIds);
        });
    }

    /**
     * Update existing certification record for self-confirmation.
     */
    private function updateExistingCertificationForSelfConfirm(
        CertificationUser $existingCertification,
        Certification $certification,
        ?string $notes = null
    ): CertificationUser {
        $now = Carbon::now();
        $expiryDate = $this->calculateExpirationDate($certification, $now);

        $selfConfirmNotes = $notes
            ? 'Self-confirmed: ' . $notes
            : 'Self-confirmed by user';

        // Append to existing notes if any
        $updatedNotes = $existingCertification->notes
            ? $existingCertification->notes . "\n\n" . $now->format('Y-m-d H:i:s') . ' - ' . $selfConfirmNotes
            : $now->format('Y-m-d H:i:s') . ' - ' . $selfConfirmNotes;

        $existingCertification->update([
            'status' => 'self_confirmed',
            'date_certified' => $now,
            'date_expires' => $expiryDate,
            'certified_by' => null,
            'notes' => $updatedNotes,
        ]);

        return $existingCertification->fresh();
    }

    /**
     * Auto-assign certification to existing users when new requirements are added to angel types.
     */
    public function autoAssignCertificationToExistingUsers(
        Certification $certification,
        AngelType $angelType,
        string $assignmentReason = 'Legacy compliance - automatically assigned'
    ): int {
        // Get all users who have this angel type but don't have the certification
        $usersWithAngelType = $angelType->users()->get();

        if ($usersWithAngelType->isEmpty()) {
            return 0;
        }

        // Get users who already have this certification
        $usersWithCertification = CertificationUser::where('certification_id', $certification->id)
            ->pluck('user_id')
            ->toArray();

        $assignedCount = 0;
        $now = Carbon::now();
        $expiryDate = $this->calculateExpirationDate($certification, $now);

        foreach ($usersWithAngelType as $user) {
            // Skip users who already have this certification
            if (in_array($user->id, $usersWithCertification)) {
                continue;
            }

            // Auto-assign with approved status for legacy compliance
            CertificationUser::create([
                'user_id' => $user->id,
                'certification_id' => $certification->id,
                'status' => 'approved',
                'date_certified' => $now,
                'date_expires' => $expiryDate,
                'certified_by' => null, // System assignment
                'notes' => $assignmentReason,
            ]);

            $assignedCount++;
        }

        return $assignedCount;
    }

    /**
     * Bulk auto-assign multiple certifications to users with specific angel types.
     */
    public function bulkAutoAssignCertifications(array $certificationAngelTypePairs): array
    {
        $results = [];

        foreach ($certificationAngelTypePairs as $pair) {
            if (!isset($pair['certification_id']) || !isset($pair['angel_type_id'])) {
                continue;
            }

            $certification = Certification::find($pair['certification_id']);
            $angelType = AngelType::find($pair['angel_type_id']);

            if (!$certification || !$angelType) {
                $results[] = [
                    'certification_id' => $pair['certification_id'] ?? null,
                    'angel_type_id' => $pair['angel_type_id'] ?? null,
                    'assigned_count' => 0,
                    'error' => 'Certification or AngelType not found',
                ];
                continue;
            }

            $assignedCount = $this->autoAssignCertificationToExistingUsers(
                $certification,
                $angelType,
                $pair['reason'] ?? 'Bulk legacy compliance assignment'
            );

            $results[] = [
                'certification_id' => $certification->id,
                'certification_title' => $certification->title,
                'angel_type_id' => $angelType->id,
                'angel_type_name' => $angelType->name,
                'assigned_count' => $assignedCount,
                'error' => null,
            ];
        }

        return $results;
    }

    /**
     * Handle retroactive certification requirement enforcement for existing users.
     */
    public function enforceRetroactiveCertificationRequirements(
        AngelType $angelType,
        array $newRequiredCertificationIds,
        bool $autoAssignToExistingUsers = true
    ): array {
        $results = [
            'angel_type' => $angelType->name,
            'certifications_added' => [],
            'users_affected' => 0,
            'assignments_made' => 0,
        ];

        foreach ($newRequiredCertificationIds as $certificationId) {
            $certification = Certification::find($certificationId);

            if (!$certification) {
                $results['certifications_added'][] = [
                    'certification_id' => $certificationId,
                    'error' => 'Certification not found',
                ];
                continue;
            }

            // Add certification requirement to angel type
            $angelType->requiredCertifications()->syncWithoutDetaching([$certificationId]);

            $assignedCount = 0;
            if ($autoAssignToExistingUsers) {
                $assignedCount = $this->autoAssignCertificationToExistingUsers(
                    $certification,
                    $angelType,
                    'Retroactive requirement enforcement for ' . $angelType->name
                );
            }

            $results['certifications_added'][] = [
                'certification_id' => $certification->id,
                'certification_title' => $certification->title,
                'users_assigned' => $assignedCount,
                'error' => null,
            ];

            $results['assignments_made'] += $assignedCount;
        }

        $results['users_affected'] = $angelType->users()->count();

        return $results;
    }

    /**
     * Get certifications that are expiring within a specified number of days.
     */
    public function getExpiringCertifications(int $daysFromNow = 7): Collection
    {
        $expiryThreshold = Carbon::now()->addDays($daysFromNow);

        return CertificationUser::with(['user', 'certification'])
            ->whereIn('status', ['approved', 'self_confirmed'])
            ->whereNotNull('date_expires')
            ->where('date_expires', '<=', $expiryThreshold)
            ->where('date_expires', '>', Carbon::now()) // Not already expired
            ->orderBy('date_expires')
            ->get();
    }

    /**
     * Get certifications that are expiring for a specific user.
     */
    public function getExpiringCertificationsForUser(User $user, int $daysFromNow = 7): Collection
    {
        $expiryThreshold = Carbon::now()->addDays($daysFromNow);

        return CertificationUser::with(['certification'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['approved', 'self_confirmed'])
            ->whereNotNull('date_expires')
            ->where('date_expires', '<=', $expiryThreshold)
            ->where('date_expires', '>', Carbon::now()) // Not already expired
            ->orderBy('date_expires')
            ->get();
    }

    /**
     * Get certifications that are expiring grouped by days until expiry.
     */
    public function getExpiringCertificationsByDays(int $maxDaysFromNow = 30): array
    {
        $expiryThreshold = Carbon::now()->addDays($maxDaysFromNow);
        $now = Carbon::now();

        $expiringCertifications = CertificationUser::with(['user', 'certification'])
            ->whereIn('status', ['approved', 'self_confirmed'])
            ->whereNotNull('date_expires')
            ->where('date_expires', '<=', $expiryThreshold)
            ->where('date_expires', '>', $now)
            ->orderBy('date_expires')
            ->get();

        $groupedByDays = [];

        foreach ($expiringCertifications as $userCertification) {
            $daysUntilExpiry = $now->diffInDays(Carbon::parse($userCertification->date_expires));

            if (!isset($groupedByDays[$daysUntilExpiry])) {
                $groupedByDays[$daysUntilExpiry] = [];
            }

            $groupedByDays[$daysUntilExpiry][] = $userCertification;
        }

        // Sort by days ascending
        ksort($groupedByDays);

        return $groupedByDays;
    }

    /**
     * Mark expired certifications as expired status.
     */
    public function markExpiredCertifications(): int
    {
        $now = Carbon::now();

        // Find all certifications that should be marked as expired
        $expiredCertifications = CertificationUser::whereIn('status', ['approved', 'self_confirmed'])
            ->whereNotNull('date_expires')
            ->where('date_expires', '<=', $now)
            ->get();

        $markedCount = 0;
        $expiredNote = 'Automatically marked as expired on ' . $now->format('Y-m-d H:i:s');

        foreach ($expiredCertifications as $userCertification) {
            // Append expiry note to existing notes
            $updatedNotes = $userCertification->notes
                ? $userCertification->notes . "\n\n" . $expiredNote
                : $expiredNote;

            $userCertification->update([
                'status' => 'expired',
                'notes' => $updatedNotes,
            ]);

            $markedCount++;
        }

        return $markedCount;
    }

    /**
     * Get all expired certifications that haven't been marked as expired yet.
     */
    public function getUnmarkedExpiredCertifications(): Collection
    {
        $now = Carbon::now();

        return CertificationUser::with(['user', 'certification'])
            ->whereIn('status', ['approved', 'self_confirmed'])
            ->whereNotNull('date_expires')
            ->where('date_expires', '<=', $now)
            ->orderBy('date_expires')
            ->get();
    }

    /**
     * Get expired certifications that are already marked as expired.
     */
    public function getExpiredCertifications(): Collection
    {
        return CertificationUser::with(['user', 'certification'])
            ->where('status', 'expired')
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    /**
     * Get certification expiry statistics.
     */
    public function getCertificationExpiryStatistics(): array
    {
        $now = Carbon::now();

        // Count expiring in next 7 days
        $expiringSoon = CertificationUser::whereIn('status', ['approved', 'self_confirmed'])
            ->whereNotNull('date_expires')
            ->where('date_expires', '<=', $now->copy()->addDays(7))
            ->where('date_expires', '>', $now)
            ->count();

        // Count expiring in next 30 days
        $expiringThisMonth = CertificationUser::whereIn('status', ['approved', 'self_confirmed'])
            ->whereNotNull('date_expires')
            ->where('date_expires', '<=', $now->copy()->addDays(30))
            ->where('date_expires', '>', $now)
            ->count();

        // Count already expired but not marked
        $unmarkedExpired = CertificationUser::whereIn('status', ['approved', 'self_confirmed'])
            ->whereNotNull('date_expires')
            ->where('date_expires', '<=', $now)
            ->count();

        // Count marked as expired
        $markedExpired = CertificationUser::where('status', 'expired')->count();

        // Count perpetual certifications
        $perpetualCertifications = CertificationUser::whereIn('status', ['approved', 'self_confirmed'])
            ->whereNull('date_expires')
            ->count();

        return [
            'expiring_within_7_days' => $expiringSoon,
            'expiring_within_30_days' => $expiringThisMonth,
            'unmarked_expired' => $unmarkedExpired,
            'marked_expired' => $markedExpired,
            'perpetual_certifications' => $perpetualCertifications,
            'total_active_certifications' => $expiringSoon + $expiringThisMonth + $perpetualCertifications,
        ];
    }

    /**
     * Apply for a certification (creates a pending application).
     */
    public function applyCertification(
        User $user,
        Certification $certification,
        ?string $notes = null
    ): CertificationUser {
        // Check if certification is active
        if (!$certification->is_active) {
            $this->log->warning('Application attempted on inactive certification', [
                'user' => $user->name,
                'user_id' => $user->id,
                'certification' => $certification->title,
                'certification_uuid' => $certification->uuid,
                'is_active' => false,
            ]);

            throw new InvalidArgumentException(
                'Certification ' . $certification->title . ' is not currently active'
            );
        }

        // Check if user already has this certification
        $existingCertification = CertificationUser::where('user_id', $user->id)
            ->where('certification_id', $certification->id)
            ->first();

        if ($existingCertification) {
            switch ($existingCertification->status) {
                case 'approved':
                case 'self_confirmed':
                    // Check if expired and allow re-application
                    if (
                        $existingCertification->date_expires &&
                        Carbon::parse($existingCertification->date_expires)->isPast()
                    ) {
                        return $this->reapplyCertification($user, $certification, $notes);
                    }

                    throw new InvalidArgumentException(
                        'You already have a valid ' . $certification->title . ' certification'
                    );

                case 'pending':
                    throw new InvalidArgumentException(
                        'You already have a pending application for ' . $certification->title
                    );

                case 'revoked':
                case 'expired':
                    // Allow re-application for revoked/expired certifications
                    return $this->reapplyCertification($user, $certification, $notes);
            }
        }

        // Create new pending application
        $now = Carbon::now();
        $applicationNotes = $notes
            ? 'Application: ' . $notes
            : 'Applied for certification';

        $certificationUser = CertificationUser::create([
            'user_id' => $user->id,
            'certification_id' => $certification->id,
            'status' => 'pending',
            'date_certified' => null, // Will be set when approved
            'date_expires' => null, // Will be calculated when approved
            'certified_by' => null, // Will be set by approver
            'notes' => $applicationNotes,
        ]);

        $this->log->info('User applied for certification', [
            'user' => $user->name,
            'user_id' => $user->id,
            'certification' => $certification->title,
            'certification_uuid' => $certification->uuid,
            'application_date' => $now->toDateTimeString(),
            'notes' => $notes ? substr($notes, 0, 100) . (strlen($notes) > 100 ? '...' : '') : null,
        ]);

        return $certificationUser;
    }

    /**
     * Re-apply for a certification (updates existing record to pending status).
     */
    public function reapplyCertification(
        User $user,
        Certification $certification,
        ?string $notes = null
    ): CertificationUser {
        // Find existing certification record
        $existingCertification = CertificationUser::where('user_id', $user->id)
            ->where('certification_id', $certification->id)
            ->first();

        if (!$existingCertification) {
            // No existing record, create new application
            return $this->applyCertification($user, $certification, $notes);
        }

        // Check if re-application is allowed based on current status
        $allowedStatuses = ['expired', 'revoked'];
        if (
            $existingCertification->status === 'approved' ||
            $existingCertification->status === 'self_confirmed'
        ) {
            // Only allow if certification has expired
            if (
                !$existingCertification->date_expires ||
                !Carbon::parse($existingCertification->date_expires)->isPast()
            ) {
                throw new InvalidArgumentException(
                    'Cannot re-apply for ' . $certification->title . ' - current certification is still valid'
                );
            }
            $allowedStatuses[] = 'approved';
            $allowedStatuses[] = 'self_confirmed';
        }

        if (!in_array($existingCertification->status, $allowedStatuses)) {
            throw new InvalidArgumentException(
                'Cannot re-apply for ' . $certification->title .
                ' with current status: ' . $existingCertification->status
            );
        }

        $now = Carbon::now();
        $oldStatus = $existingCertification->status;
        $reapplicationNotes = $notes
            ? 'Re-application: ' . $notes
            : 'Re-applied for certification';

        // Update existing record to pending status
        $existingCertification->update([
            'status' => 'pending',
            'date_certified' => null, // Will be set when re-approved
            'date_expires' => null, // Will be calculated when re-approved
            'certified_by' => null, // Will be set by approver
            'notes' => $reapplicationNotes,
        ]);

        $this->log->info('User re-applied for certification', [
            'user' => $user->name,
            'user_id' => $user->id,
            'certification' => $certification->title,
            'certification_uuid' => $certification->uuid,
            'old_status' => $oldStatus,
            'new_status' => 'pending',
            'reapplication_date' => $now->toDateTimeString(),
            'notes' => $notes ? substr($notes, 0, 100) . (strlen($notes) > 100 ? '...' : '') : null,
        ]);

        return $existingCertification;
    }

    /**
     * Get application status history for a user's certification.
     */
    public function getCertificationApplicationHistory(User $user, Certification $certification): array
    {
        // Get all certification records for this user and certification
        $records = CertificationUser::where('user_id', $user->id)
            ->where('certification_id', $certification->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $history = [];

        foreach ($records as $record) {
            $statusChange = [
                'id' => $record->id,
                'status' => $record->status,
                'date_certified' => $record->date_certified,
                'date_expires' => $record->date_expires,
                'certified_by' => $record->certified_by,
                'notes' => $record->notes,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
                'is_current' => true,
            ];

            // Add interpretation of the status change
            if ($record->status === 'pending') {
                $statusChange['action'] = 'Application submitted';
                $statusChange['action_type'] = 'application';
            } elseif ($record->status === 'approved') {
                $statusChange['action'] = 'Application approved';
                $statusChange['action_type'] = 'approval';
            } elseif ($record->status === 'self_confirmed') {
                $statusChange['action'] = 'Self-confirmed certification';
                $statusChange['action_type'] = 'self_confirmation';
            } elseif ($record->status === 'revoked') {
                $statusChange['action'] = 'Certification revoked';
                $statusChange['action_type'] = 'revocation';
            } elseif ($record->status === 'expired') {
                $statusChange['action'] = 'Certification expired';
                $statusChange['action_type'] = 'expiration';
            }

            $history[] = $statusChange;
        }

        return $history;
    }

    /**
     * Get comprehensive application statistics for a user.
     */
    public function getUserApplicationStatistics(User $user): array
    {
        $stats = [
            'total_applications' => 0,
            'pending_applications' => 0,
            'approved_certifications' => 0,
            'self_confirmed_certifications' => 0,
            'expired_certifications' => 0,
            'revoked_certifications' => 0,
            'recent_activity' => [],
        ];

        // Get current certifications
        $currentCertifications = CertificationUser::where('user_id', $user->id)->get();

        foreach ($currentCertifications as $cert) {
            $stats['total_applications']++;

            switch ($cert->status) {
                case 'pending':
                    $stats['pending_applications']++;
                    break;
                case 'approved':
                    $stats['approved_certifications']++;
                    break;
                case 'self_confirmed':
                    $stats['self_confirmed_certifications']++;
                    break;
                case 'expired':
                    $stats['expired_certifications']++;
                    break;
                case 'revoked':
                    $stats['revoked_certifications']++;
                    break;
            }
        }

        // Get recent activity (last 30 days)
        $recentActivity = CertificationUser::where('user_id', $user->id)
            ->where(function ($query): void {
                $query->where('created_at', '>', Carbon::now()->subDays(30))
                      ->orWhere('updated_at', '>', Carbon::now()->subDays(30));
            })
            ->with('certification')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($recentActivity as $activity) {
            $stats['recent_activity'][] = [
                'certification_title' => $activity->certification->title,
                'status' => $activity->status,
                'action_date' => $activity->updated_at,
            ];
        }

        return $stats;
    }

    /**
     * Validate certification data.
     */
    private function validateCertificationData(array $data, ?Certification $existingCertification = null): void
    {
        $errors = [];

        // Required fields
        if (empty($data['title'])) {
            $errors[] = 'Title is required';
        }

        if (empty($data['description'])) {
            $errors[] = 'Description is required';
        }

        // Validate email if provided
        if (!empty($data['contact_email']) && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Contact email must be a valid email address';
        }

        // Validate validity period for non-perpetual certifications
        if (!($data['is_perpetual'] ?? false)) {
            if (
                empty($data['validity_period_days']) ||
                !is_numeric($data['validity_period_days']) || $data['validity_period_days'] <= 0
            ) {
                $errors[] = 'Validity period in days is required for non-perpetual certifications and must be a positive number'; // phpcs:ignore
            }
        }

        // Check for title uniqueness
        if (!empty($data['title'])) {
            $query = Certification::where('title', $data['title']);

            if ($existingCertification) {
                $query->where('id', '!=', $existingCertification->id);
            }

            if ($query->exists()) {
                $errors[] = 'A certification with this title already exists';
            }
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
        }
    }
}

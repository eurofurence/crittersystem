<?php

declare(strict_types=1);

namespace Engelsystem\Controllers\Admin;

use Engelsystem\Controllers\BaseController;
use Engelsystem\Controllers\HasUserNotifications;
use Engelsystem\Controllers\NotificationType;
use Engelsystem\Http\Exceptions\ValidationException;
use Engelsystem\Http\Redirector;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Http\Validation\Validator;
use Engelsystem\Models\Certification;
use Engelsystem\Services\CertificationService;
use Psr\Log\LoggerInterface;

class CertificationsController extends BaseController
{
    use HasUserNotifications;

//    protected array $permissions = [];
    /** @var array<string> */
    protected array $permissions = [
        'certificates.admin',    // New primary certification admin permission
    //    'certificates.manage',   // New certification management permission
    //    'admin_certificates',    // Legacy permission for backward compatibility
    ];

    public function __construct(
        protected LoggerInterface $log,
        protected CertificationService $certificationService,
        protected Redirector $redirect,
        protected Response $response
    ) {
    }

    /**
     * Resolve a Certification model by UUID from request attributes.
     */
    protected function resolveCertification(Request $request, string $attributeName = 'uuid'): Certification
    {
        $uuid = $request->getAttribute($attributeName);

        if (!$uuid) {
            throw new \InvalidArgumentException('No UUID provided for certification resolution');
        }

        return $this->certificationService->getCertificationByUuidOrFail($uuid);
    }

    /**
     * Display a listing of certifications.
     */
    public function index(Request $request): Response
    {
        $filters = [];

        // Handle search filter
        if ($search = $request->get('search')) {
            $filters['search'] = trim($search);
        }

        // Handle active/inactive filter
        if ($request->has('active')) {
            $activeFilter = $request->get('active');
            if ($activeFilter !== null && $activeFilter !== '') {
                $filters['active'] = filter_var($activeFilter, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
            }
        }

        // Handle perpetual/timed filter
        if ($request->has('perpetual')) {
            $perpetualFilter = $request->get('perpetual');
            if ($perpetualFilter !== null && $perpetualFilter !== '') {
                $filters['perpetual'] = filter_var($perpetualFilter, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        // Handle self-confirmable filter
        if ($request->has('self_confirmable')) {
            $selfConfirmableFilter = $request->get('self_confirmable');
            if ($selfConfirmableFilter !== null && $selfConfirmableFilter !== '') {
                $filters['self_confirmable'] = filter_var(
                    $selfConfirmableFilter,
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                );
            }
        }

        // Handle sorting
        $sortBy = $request->get('sort_by', 'title');
        $sortDirection = $request->get('sort_direction', 'asc');

        // Validate sort parameters
        $allowedSortFields = ['title', 'created_at', 'updated_at', 'validity_period_days'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'title';
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? strtolower($sortDirection) : 'asc';

        $filters['sort_by'] = $sortBy;
        $filters['sort_direction'] = $sortDirection;

        // Handle pagination
        $perPage = (int) $request->get('per_page', 15);
        $perPage = max(5, min(100, $perPage)); // Limit between 5 and 100

        // Get paginated certifications
        $paginationResult = $this->certificationService->getPaginatedCertifications($perPage, $filters);

        // Get statistics for dashboard
        $statistics = $this->certificationService->getCertificationExpiryStatistics();

        // Prepare filter state for view
        $filterState = [
            'search' => $search,
            'active' => $request->get('active'),
            'perpetual' => $request->get('perpetual'),
            'self_confirmable' => $request->get('self_confirmable'),
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
            'per_page' => $perPage,
        ];

        return $this->response->withView(
            'admin/certifications/index',
            [
                'certifications' => $paginationResult,
                'statistics' => $statistics,
                'filters' => $filters,
                'filter_state' => $filterState,
                'current_sort' => [
                    'field' => $sortBy,
                    'direction' => $sortDirection,
                ],
                'search' => $search,
                'is_index' => true,
            ]
        );
    }

    /**
     * Show the form for creating a new certification.
     */
    public function create(): Response
    {
        return $this->showEdit(null);
    }

    /**
     * Store a newly created certification.
     */
    public function store(Request $request): Response
    {
        return $this->save($request);
    }

    /**
     * Display the specified certification.
     */
    public function show(Request $request): Response
    {
        $certification = $this->resolveCertification($request, 'certification_uuid');

        // Get user certifications for this certification
        $userCertifications = $certification->users()
            ->withPivot([
                'id', // Add pivot table primary key
                'status',
                'date_certified',
                'date_expires',
                'certified_by',
                'notes',
                'created_at',
                'updated_at',
            ])
            ->orderBy('certifications_user.status')
            ->orderBy('certifications_user.date_expires')
            ->get();

        // Group by status for better display, with special handling for expired certifications
        $usersByStatus = collect();

        // Handle edge case: no users have this certification
        if ($userCertifications->isEmpty()) {
            // Initialize empty collections for all status types
            $usersByStatus = collect([
                'approved' => collect(),
                'pending' => collect(),
                'self_confirmed' => collect(),
                'revoked' => collect(),
                'expired' => collect(),
            ]);
        } else {
//            $now = \Carbon\Carbon::now();

            foreach ($userCertifications as $user) {
                $status = $user->pivot->status;
                $dateExpires = $user->pivot->date_expires;

                // Override status to 'expired' if certification has passed its expiry date
                if ($dateExpires && \Carbon\Carbon::parse($dateExpires)->isPast()) {
                    $status = 'expired';
                }

                // Group users by their effective status
                if (!$usersByStatus->has($status)) {
                    $usersByStatus->put($status, collect());
                }
                $usersByStatus->get($status)->push($user);
            }
        }

        // Calculate individual status counts for statistics display with edge case handling
        $statusCounts = [
            'approved' => $usersByStatus->get('approved', collect())->count(),
            'pending' => $usersByStatus->get('pending', collect())->count(),
            'self_confirmed' => $usersByStatus->get('self_confirmed', collect())->count(),
            'revoked' => $usersByStatus->get('revoked', collect())->count(),
            'expired' => $usersByStatus->get('expired', collect())->count(),
        ];

        // Ensure all counts are integers and handle any edge cases
        foreach ($statusCounts as $status => $count) {
            $statusCounts[$status] = max(0, (int) $count);
        }

        // Calculate total by summing individual status counts for accuracy
        $totalCount = array_sum($statusCounts);

        $userCounts = [
            'total' => $totalCount,
            'approved' => $statusCounts['approved'],
            'pending' => $statusCounts['pending'],
            'self_confirmed' => $statusCounts['self_confirmed'],
            'revoked' => $statusCounts['revoked'],
            'expired' => $statusCounts['expired'],
        ];

        // Ensure all status collections are available for template access
        $statusCollections = [];
        foreach (['approved', 'pending', 'self_confirmed', 'revoked', 'expired'] as $status) {
            $collection = $usersByStatus->get($status, collect());
            // Ensure we have a proper Collection of User models
            $statusCollections[$status] = $collection instanceof \Illuminate\Support\Collection
                ? $collection
                : collect($collection);
        }

        return $this->response->withView(
            'admin/certifications/show',
            [
                'certification' => $certification,
                'usersByStatus' => (object) $statusCollections, // Convert to object for property access
                'userCounts' => $userCounts, // Add the missing userCounts object
                'total_users' => $userCertifications->count(), // Keep for backward compatibility
            ]
        );
    }

    /**
     * Show the form for editing the specified certification.
     */
    public function edit(Request $request): Response
    {
        $certification = $this->resolveCertification($request, 'certification_uuid');
        return $this->showEdit($certification);
    }

    /**
     * Update the specified certification.
     */
    public function update(Request $request): Response
    {
        return $this->save($request);
    }

    /**
     * Remove the specified certification.
     */
    public function destroy(Request $request): Response
    {
//        $data = $this->validate($request, [
//            'uuid' => 'required',
//            'delete' => 'checked',
//        ]);

        $this->validate($request, [
            'uuid' => 'required',
            'delete' => 'checked',
        ]);

        $certification = $this->resolveCertification($request, 'certification_uuid');

        try {
            $this->certificationService->deleteCertification($certification);

            $this->log->info('Deleted certification {certification}', [
                'certification' => $certification->title,
                'uuid' => $certification->uuid,
            ]);

            $this->addNotification('certification.delete.success');
        } catch (\RuntimeException $e) {
            $this->addNotification($e->getMessage(), NotificationType::ERROR);
        }

        return $this->redirect->to('/admin/certifications');
    }

    /**
     * Mass revoke a certification for all users.
     */
    public function massRevoke(Request $request): Response
    {
        $data = $this->validate($request, [
            'uuid' => 'required',
            'revocation_reason' => 'optional|max:500',
            'mass_revoke' => 'checked',
        ]);

        $certification = $this->resolveCertification($request, 'certification_uuid');
        $reason = $data['revocation_reason'] ?? 'Mass revocation of ' . $certification->title;

        $revokedCount = $this->certificationService->massRevokeCertification($certification, $reason);

        $this->log->info('Mass revoked certification {certification} for {count} users', [
            'certification' => $certification->title,
            'uuid' => $certification->uuid,
            'count' => $revokedCount,
            'reason' => $reason,
        ]);

        $this->addNotification('certification.mass_revoke.success');

        return $this->redirect->to('/admin/certifications/' . $certification->uuid);
    }

    /**
     * Deactivate a certification (safer alternative to deletion).
     */
    public function deactivate(Request $request): Response
    {
//        $data = $this->validate($request, [
//            'uuid' => 'required',
//            'deactivate' => 'checked',
//        ]);

        $this->validate($request, [
            'uuid' => 'required',
            'deactivate' => 'checked',
        ]);

        $certification = $this->resolveCertification($request, 'certification_uuid');
        $this->certificationService->deactivateCertification($certification);

        $this->log->info('Deactivated certification {certification}', [
            'certification' => $certification->title,
            'uuid' => $certification->uuid,
        ]);

        $this->addNotification('certification.deactivate.success');

        return $this->redirect->to('/admin/certifications');
    }

    /**
     * Reactivate a certification.
     */
    public function reactivate(Request $request): Response
    {
        $data = $this->validate($request, [
            'uuid' => 'required',
            'reactivate' => 'checked',
        ]);

        $certification = $this->certificationService->getCertificationByUuidOrFail($data['uuid']);
        $this->certificationService->reactivateCertification($certification);

        $this->log->info('Reactivated certification {certification}', [
            'certification' => $certification->title,
            'uuid' => $certification->uuid,
        ]);

        $this->addNotification('certification.reactivate.success');

        return $this->redirect->to('/admin/certifications');
    }

    /**
     * Handle create and update operations.
     */
    protected function save(Request $request): Response
    {
        $uuid = $request->getAttribute('certification_uuid'); // null for create, uuid for update
        $certification = $uuid ? $this->resolveCertification($request, 'certification_uuid') : null;

        if ($request->request->has('delete')) {
            return $this->destroy($request);
        }

        if ($request->request->has('mass_revoke')) {
            return $this->massRevoke($request);
        }

        if ($request->request->has('deactivate')) {
            return $this->deactivate($request);
        }

        if ($request->request->has('reactivate')) {
            return $this->reactivate($request);
        }

        $data = $this->validateCertificationForm($request, $certification);

        // Clean and process data
        $data = $this->processCertificationData($data);

        try {
            if ($certification) {
                // Update existing certification
                $certification = $this->certificationService->updateCertification($certification, $data);
                $action = 'Updated';
            } else {
                // Create new certification
                $certification = $this->certificationService->createCertification($data);
                $action = 'Created';
            }

            $this->log->info('{action} certification {certification}', [
                'action' => $action,
                'certification' => $certification->title,
                'uuid' => $certification->uuid,
                'data' => $data,
            ]);

            $this->addNotification(
                $action === 'Created' ? 'certification.create.success' : 'certification.update.success'
            );

            return $this->redirect->to('/admin/certifications');
        } catch (\InvalidArgumentException $e) {
            throw new ValidationException((new Validator())->addErrors(['general' => [$e->getMessage()]]));
        }
    }

    /**
     * Validate certification form data with comprehensive rules and custom messages.
     */
    protected function validateCertificationForm(Request $request, ?Certification $certification = null): array
    {
        $rules = [
            'title' => 'required|min:3|max:100',
            'description' => 'required|min:10|max:1000',
            'contact_person' => 'optional|max:100',
            'contact_email' => 'optional|max:100',
            'location' => 'optional|max:100',
            'is_perpetual' => 'optional|checked',
            'validity_period_days' => 'optional|min:1|max:3650', // Max 10 years
            'allow_self_confirmation' => 'optional|checked',
            'is_active' => 'optional|checked',
        ];

        // Additional validation for non-perpetual certifications
        $data = $request->getParsedBody();
        if (!isset($data['is_perpetual']) || !$data['is_perpetual']) {
            $rules['validity_period_days'] = 'required|min:1|max:3650';
        }

        // Custom validation messages
        $messages = [
            'title.required' => 'The certification title is required.',
            'title.min' => 'The certification title must be at least 3 characters long.',
            'title.max' => 'The certification title cannot exceed 100 characters.',
            'description.required' => 'The certification description is required.',
            'description.min' => 'The certification description must be at least 10 characters long.',
            'description.max' => 'The certification description cannot exceed 1000 characters.',
            'contact_person.max' => 'The contact person name cannot exceed 100 characters.',
            'contact_email.email' => 'Please provide a valid email address for the contact email.',
            'contact_email.max' => 'The contact email cannot exceed 100 characters.',
            'location.max' => 'The location cannot exceed 100 characters.',
            'validity_period_days.required' => 'Validity period is required for non-perpetual certifications.',
            'validity_period_days.int' => 'Validity period must be a valid number of days.',
            'validity_period_days.min' => 'Validity period must be at least 1 day.',
            'validity_period_days.max' => 'Validity period cannot exceed 3650 days (10 years).',
        ];

        try {
            $validatedData = $this->validate($request, $rules);
        } catch (ValidationException $e) {
            // Enhance validation exception with custom messages
            $validator = new Validator();
            $errors = $e->getValidator()->getErrors();
            $customErrors = [];

            foreach ($errors as $field => $fieldErrors) {
                $customErrors[$field] = [];
                if (is_array($fieldErrors)) {
                    foreach ($fieldErrors as $error) {
                        $customMessage = $messages[$field . '.' . $error] ?? $error;
                        $customErrors[$field][] = $customMessage;
                    }
                } else {
                    $customMessage = $messages[$field . '.' . $fieldErrors] ?? $fieldErrors;
                    $customErrors[$field][] = $customMessage;
                }
            }

            throw new ValidationException($validator->addErrors($customErrors));
        }

        // Additional business logic validation
        $this->validateBusinessRules($validatedData, $certification);

        return $validatedData;
    }

    /**
     * Validate business rules for certification data.
     */
    protected function validateBusinessRules(array $data, ?Certification $certification = null): void
    {
        $errors = [];

        // Check title uniqueness
        if (!empty($data['title'])) {
            $query = Certification::where('title', $data['title']);

            if ($certification) {
                $query->where('id', '!=', $certification->id);
            }

            if ($query->exists()) {
                $errors['title'] = ['A certification with this title already exists.'];
            }
        }

        // Validate contact email if contact person is provided
        if (!empty($data['contact_person']) && empty($data['contact_email'])) {
            $errors['contact_email'] = ['Contact email is recommended when a contact person is specified.'];
        }

        // Validate validity period for non-perpetual certifications
        if (
            !isset($data['is_perpetual']) &&
            (!isset($data['validity_period_days']) ||
                $data['validity_period_days'] <= 0)
        ) {
            $errors['validity_period_days'] = ['Validity period is required for non-perpetual certifications.'];
        }

        // Common validity periods validation (business rule)
        if (isset($data['validity_period_days']) && $data['validity_period_days']) {
            $validPeriods = [30, 90, 180, 365, 730, 1095]; // Common periods: 1m, 3m, 6m, 1y, 2y, 3y
            if (!in_array($data['validity_period_days'], $validPeriods)) {
                // This is a warning, not an error - allow custom periods but suggest standards
                // Could be implemented as a soft validation in the UI
            }
        }

        if (!empty($errors)) {
            $validator = new Validator();
            throw new ValidationException($validator->addErrors($errors));
        }
    }

    /**
     * Process and clean certification form data.
     */
    protected function processCertificationData(array $data): array
    {
        // Clean text data
        $data['title'] = globalCleanText($data['title'], true);
        $data['description'] = globalCleanText($data['description']);
        $data['contact_person'] = !empty($data['contact_person']) ? globalCleanText($data['contact_person']) : null;
        $data['contact_email'] = !empty($data['contact_email']) ? trim($data['contact_email']) : null;
        $data['location'] = !empty($data['location']) ? globalCleanText($data['location']) : null;

        // Convert checkboxes to booleans
        $data['is_perpetual'] = isset($data['is_perpetual']);
        $data['allow_self_confirmation'] = isset($data['allow_self_confirmation']);
        // Default to active for new certifications
        $data['is_active'] = $data['is_active'] ?? true;

        // Handle validity period for perpetual certifications
        if ($data['is_perpetual']) {
            $data['validity_period_days'] = null;
        }

        return $data;
    }

    /**
     * Show the edit form.
     */
    protected function showEdit(?Certification $certification): Response
    {
        return $this->response->withView(
            'admin/certifications/edit',
            [
                'certification' => $certification,
                'is_new' => !$certification,
            ]
        );
    }
}

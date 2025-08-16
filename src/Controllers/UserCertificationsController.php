<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Engelsystem\Controllers\BaseController;
use Engelsystem\Controllers\HasUserNotifications;
use Engelsystem\Controllers\NotificationType;
use Engelsystem\Http\Exceptions\ValidationException;
use Engelsystem\Http\Redirector;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Http\Validation\Validator;
use Engelsystem\Models\Certification;
use Engelsystem\Models\CertificationUser;
use Engelsystem\Models\User\User;
use Engelsystem\Services\CertificationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Psr\Log\LoggerInterface;
use Carbon\Carbon;

class UserCertificationsController extends BaseController
{
    use HasUserNotifications;

    /** @var array<string> */
    protected array $permissions = [
        // Base permission for accessing user certification features
        'certificates.view',
        // Specific method-based permissions
        'userStore' => ['certificates.admin||certificates.manage||certificates.assign'],
        'userUpdate' => ['certificates.admin||certificates.manage||certificates.approve'],
        'userDestroy' => ['certificates.admin||certificates.manage||certificates.revoke'],
    ];

    public function __construct(
        protected LoggerInterface $log,
        protected CertificationService $certificationService,
        protected Response $response,
        protected Redirector $redirect
    ) {
    }

    /**
     * Display user's certifications with filtering and status overview.
     */
    public function userIndex(Request $request): Response
    {
        $user = $this->getUser($request);

        // Get filter parameters
        $status = $request->get('status', '');
        $search = $request->get('search', '');

        // Build filters array for service
        $filters = [];
        if (!empty($status) && $status !== 'all') {
            $filters['status'] = $status;
        }
        if (!empty($search)) {
            $filters['search'] = $search;
        }

        // Get user's certifications
        $certifications = $this->certificationService->getUserCertifications($user, $filters);

        // Get all available certifications for admin interface
        $availableCertifications = $this->certificationService->getAllCertifications(['active' => true]);

        // Get pending applications for "My Applications" section
        $pendingApplications = CertificationUser::with(['certification'])
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get application history for status tracking
        $applicationHistory = CertificationUser::with(['certification'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved', 'rejected', 'cancelled', 'self_confirmed'])
            ->orderBy('updated_at', 'desc')
            ->limit(10) // Show recent 10 applications
            ->get();

        // Calculate application statistics
        $applicationStats = [
            'total_applications' => CertificationUser::where('user_id', $user->id)->count(),
            'pending' => CertificationUser::where('user_id', $user->id)->where('status', 'pending')->count(),
            'approved' => CertificationUser::where('user_id', $user->id)->where('status', 'approved')->count(),
            'self_confirmed' => CertificationUser::where('user_id', $user->id)->where('status', 'self_confirmed')->count(), // phpcs:ignore
            'rejected' => CertificationUser::where('user_id', $user->id)->where('status', 'rejected')->count(),
            'cancelled' => CertificationUser::where('user_id', $user->id)->where('status', 'cancelled')->count(),
        ];

        return $this->response->withView(
            'user/certifications/index',
            [
                'user' => $user,
                'certifications' => $certifications,
                'availableCertifications' => $availableCertifications,
                'pendingApplications' => $pendingApplications,
                'applicationHistory' => $applicationHistory,
                'applicationStats' => $applicationStats,
                'currentStatus' => $status,
                'search' => $search,
            ]
        );
    }

    /**
     * Display admin interface for managing user certifications.
     */
    public function index(Request $request): Response
    {
        $this->checkPermission('certificates.admin');

        // Get filters
        $filters = [
            'search' => $request->get('search', ''),
            'status' => $request->get('status', ''),
            'certification_id' => $request->get('certification_id', ''),
            'user_id' => $request->get('user_id', ''),
        ];

        // Get pagination parameters
        $perPage = max(5, min(100, (int) $request->get('per_page', 20)));
        $page = max(1, (int) $request->get('page', 1));

        // Get pending applications for review
        $pendingApplicationsQuery = CertificationUser::with(['user', 'certification'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc'); // Oldest first for review queue

        // Apply search filter if provided
        if (!empty($filters['search'])) {
            $pendingApplicationsQuery->where(function ($query) use ($filters): void {
                $query->whereHas('user', function ($q) use ($filters): void {
                    $q->where('name', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('email', 'like', '%' . $filters['search'] . '%');
                })->orWhereHas('certification', function ($q) use ($filters): void {
                    $q->where('title', 'like', '%' . $filters['search'] . '%');
                });
            });
        }

        // Apply certification filter if provided
        if (!empty($filters['certification_id'])) {
            $pendingApplicationsQuery->where('certification_id', $filters['certification_id']);
        }

        $pendingApplications = $pendingApplicationsQuery->paginate($perPage, ['*'], 'page', $page);

        // Get recent applications activity (last 50 items for admin dashboard)
        $recentActivity = CertificationUser::with(['user', 'certification'])
            ->whereIn('status', ['approved', 'rejected', 'self_confirmed'])
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get();

        // Get statistics for admin dashboard
        $statistics = [
            'pending_count' => CertificationUser::where('status', 'pending')->count(),
            'approved_today' => CertificationUser::where('status', 'approved')
                ->whereDate('updated_at', Carbon::today())->count(),
            'self_confirmed_today' => CertificationUser::where('status', 'self_confirmed')
                ->whereDate('updated_at', Carbon::today())->count(),
            'rejected_today' => CertificationUser::where('status', 'rejected')
                ->whereDate('updated_at', Carbon::today())->count(),
            'total_applications' => CertificationUser::count(),
            'approval_rate' => $this->calculateApprovalRate(),
        ];

        // Get all certifications for filter dropdown
        $availableCertifications = $this->certificationService->getAllCertifications(['active' => true]);

        // Get filter state for view
        $filterState = array_merge($filters, [
            'per_page' => $perPage,
            'page' => $page,
        ]);

        return $this->response->withView(
            'admin/user-certifications/index',
            [
                'pending_applications' => $pendingApplications,
                'recent_activity' => $recentActivity,
                'statistics' => $statistics,
                'available_certifications' => $availableCertifications,
                'filters' => $filters,
                'filter_state' => $filterState,
            ]
        );
    }

    /**
     * Add a certification to a user (admin function).
     */
    public function userStore(Request $request): Response
    {
        $this->checkPermission('certification.admin');

        $user = $this->getUser($request);

        $validatedData = $this->validate($request, [
            'certification_uuid' => 'required',
            'status' => 'required|in:pending,approved,self_confirmed',
            'date_expires' => 'optional|date',
            'notes' => 'optional|max:1000',
            'checked' => 'required|accepted', // CSRF protection
        ]);

        try {
            // Find certification by UUID
            $certification = Certification::where('uuid', $validatedData['certification_uuid'])->firstOrFail();

            // Check if user already has this certification
            $existingCertification = CertificationUser::where('user_id', $user->id)
                ->where('certification_id', $certification->id)
                ->first();

            if ($existingCertification) {
                throw new ValidationException(
                    (new Validator())->addErrors(['certification_uuid' => ['User already has this certification']])
                );
            }

            // Add certification to user
            // TODO: Revisit this and remove the ignore
            // phpcs:ignore
            $certificationUser = $this->certificationService->addCertificationToUser(
                $user,
                $certification,
                $validatedData['status'],
                null, // certified_by
                $validatedData['notes'] ?? null,
                null, // date_certified (will be set automatically)
                !empty($validatedData['date_expires']) ? new \Carbon\Carbon($validatedData['date_expires']) : null
            );

            $this->log->info('Added certification {certification} to user {user}', [
                'certification' => $certification->title,
                'certification_uuid' => $certification->uuid,
                'user' => $user->name,
                'user_id' => $user->id,
                'status' => $validatedData['status'],
                'expires' => $validatedData['date_expires'] ?? 'never',
            ]);

            $this->addNotification('certification.user.add.success');

            return $this->redirect->to('/users/' . $user->id . '/certifications');
        } catch (ModelNotFoundException) {
            throw new ValidationException(
                (new Validator())->addErrors(['certification_uuid' => ['Certification not found']])
            );
        } catch (\InvalidArgumentException $e) {
            throw new ValidationException(
                (new Validator())->addErrors(['general' => [$e->getMessage()]])
            );
        }
    }

    /**
     * Update a user's certification status or details.
     */
    public function userUpdate(Request $request): Response
    {
        $this->checkPermission('certification.admin');

        $user = $this->getUser($request);
        $certificationUser = $this->resolveCertificationUser($request);

        $validatedData = $this->validate($request, [
            'status' => 'required|in:pending,approved,self_confirmed,revoked',
            'date_expires' => 'optional|date',
            'notes' => 'optional|max:1000',
            'checked' => 'required|accepted', // CSRF protection
        ]);

        try {
            // Update certification status
            $this->certificationService->updateUserCertification(
                $certificationUser,
                $validatedData['status']
            );

            // Update additional fields if provided
            if (isset($validatedData['date_expires'])) {
                $certificationUser->date_expires = !empty($validatedData['date_expires'])
                    ? new \Carbon\Carbon($validatedData['date_expires'])
                    : null;
            }

            if (isset($validatedData['notes'])) {
                $certificationUser->notes = $validatedData['notes'];
            }

            $certificationUser->save();

            $this->log->info('Updated certification {certification} for user {user}', [
                'certification' => $certificationUser->certification->title,
                'certification_uuid' => $certificationUser->certification->uuid,
                'user' => $user->name,
                'user_id' => $user->id,
                'new_status' => $validatedData['status'],
                'expires' => $validatedData['date_expires'] ?? 'never',
            ]);

            $this->addNotification('certification.user.update.success');

            return $this->redirect->to('/users/' . $user->id . '/certifications');
        } catch (\InvalidArgumentException $e) {
            throw new ValidationException(
                (new Validator())->addErrors(['general' => [$e->getMessage()]])
            );
        }
    }

    /**
     * Remove a certification from a user.
     */
    public function userDestroy(Request $request): Response
    {
        $this->checkPermission('certification.admin');

        $user = $this->getUser($request);
        $certificationUser = $this->resolveCertificationUser($request);

        $this->validate($request, [
            'checked' => 'required|accepted', // CSRF protection
        ]);

        try {
            $certificationTitle = $certificationUser->certification->title;

            // Remove certification from user
            $this->certificationService->removeCertificationFromUser(
                $user,
                $certificationUser->certification
            );

            $this->log->info('Removed certification {certification} from user {user}', [
                'certification' => $certificationTitle,
                'certification_uuid' => $certificationUser->certification->uuid,
                'user' => $user->name,
                'user_id' => $user->id,
                'old_status' => $certificationUser->status,
            ]);

            $this->addNotification('certification.user.remove.success');

            return $this->redirect->to('/users/' . $user->id . '/certifications');
        } catch (\InvalidArgumentException $e) {
            throw new ValidationException(
                (new Validator())->addErrors(['general' => [$e->getMessage()]])
            );
        }
    }

    /**
     * Apply for a certification.
     */
    public function apply(Request $request): Response
    {
        $user = auth()->user();

        // Validate inputs from form body
        $data = $this->validate($request, [
            'certification_uuid' => 'required',
            'confirmed' => 'required|accepted',
        ]);

        try {
            // Load certification by UUID
            /** @var Certification $certification */
            $certification = Certification::where('uuid', $data['certification_uuid'])->firstOrFail();

            // Check if certification is active and available for application
            if (!$certification->is_active) {
                $this->log->warning('Application attempted on inactive certification', [
                    'user' => $user->name,
                    'user_id' => $user->id,
                    'certification' => $certification->title,
                    'certification_uuid' => $certification->uuid,
                    'ip_address' => $request->getClientIp(),
                    'user_agent' => $request->getHeaderLine('User-Agent'),
                ]);
                $this->addNotification('certification.apply.inactive', NotificationType::ERROR);
                return $this->redirect->to('/user/certifications');
            }

            // Check if user already has this certification
            $existingCertification = CertificationUser::where('user_id', $user->id)
                ->where('certification_id', $certification->id)
                ->first();

            if ($existingCertification) {
                // Log the attempt and provide appropriate response based on status
                $this->log->info('Application attempted with existing certification', [
                    'user' => $user->name,
                    'user_id' => $user->id,
                    'certification' => $certification->title,
                    'certification_uuid' => $certification->uuid,
                    'existing_status' => $existingCertification->status,
                    'ip_address' => $request->getClientIp(),
                ]);

                switch ($existingCertification->status) {
                    case 'approved':
                    case 'self_confirmed':
                        // Check if expired and allow reapplication
                        if (
                            $existingCertification->date_expires &&
                            $existingCertification->date_expires->isPast()
                        ) {
                            $this->certificationService->reapplyCertification(
                                $user,
                                $certification,
                                'Reapplication for expired certification'
                            );
                            $this->addNotification('certification.apply.reapply_success');
                        } else {
                            $this->addNotification('certification.apply.already_have', NotificationType::WARNING);
                        }
                        break;
                    case 'pending':
                        $this->addNotification('certification.apply.already_pending', NotificationType::WARNING);
                        break;
                    case 'expired':
                    case 'revoked':
                        // Allow reapplication for expired/revoked certifications
                        $this->certificationService->reapplyCertification(
                            $user,
                            $certification,
                            'Reapplication after ' . $existingCertification->status
                        );
                        $this->addNotification('certification.apply.reapply_success');
                        break;
                }
                return $this->redirect->to('/user/certifications');
            }

            // Apply for the certification via service with application metadata
            $applicationNotes = sprintf(
                'Application submitted from IP %s at %s',
                $request->getClientIp(),
                Carbon::now()->toDateTimeString()
            );

            $applicationRecord = $this->certificationService->applyCertification(
                $user,
                $certification,
                $applicationNotes
            );

            $this->log->info('User successfully applied for certification', [
                'user' => $user->name,
                'user_id' => $user->id,
                'certification' => $certification->title,
                'certification_uuid' => $certification->uuid,
                'application_id' => $applicationRecord->id,
                'ip_address' => $request->getClientIp(),
                'user_agent' => substr($request->getHeaderLine('User-Agent'), 0, 255),
                'submission_timestamp' => $applicationRecord->created_at->toDateTimeString(),
            ]);

            $this->addNotification('certification.apply.success');

            return $this->redirect->to('/user/certifications');
        } catch (ModelNotFoundException) {
            $this->log->error('Application attempted for non-existent certification', [
                'user' => $user->name,
                'user_id' => $user->id,
                'certification_uuid' => $data['certification_uuid'],
                'ip_address' => $request->getClientIp(),
            ]);
            $this->addNotification('certification.apply.not_found', NotificationType::ERROR);
            return $this->redirect->to('/user/certifications');
        } catch (\InvalidArgumentException $e) {
            $this->log->warning('Application failed due to validation error', [
                'user' => $user->name,
                'user_id' => $user->id,
                'certification_uuid' => $data['certification_uuid'],
                'error_message' => $e->getMessage(),
                'ip_address' => $request->getClientIp(),
            ]);
            $this->addNotification('certification.apply.error', NotificationType::ERROR);
            return $this->redirect->to('/user/certifications');
        } catch (\Exception $e) {
            $this->log->error('Unexpected error during application submission', [
                'user' => $user->name,
                'user_id' => $user->id,
                'certification_uuid' => $data['certification_uuid'],
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->getClientIp(),
            ]);
            $this->addNotification('certification.apply.system_error', NotificationType::ERROR);
            return $this->redirect->to('/user/certifications');
        }
    }

    /**
     * Withdraw a pending certification application.
     */
    public function withdrawApplication(Request $request): Response
    {
        $user = auth()->user();

        // Validate inputs
        $data = $this->validate($request, [
            'certification_uuid' => 'required',
            'confirmed' => 'required|accepted',
        ]);

        try {
            /** @var Certification $certification */
            $certification = Certification::where('uuid', $data['certification_uuid'])->firstOrFail();

            // Find the user's pending application
            $pendingApplication = CertificationUser::where('user_id', $user->id)
                ->where('certification_id', $certification->id)
                ->where('status', 'pending')
                ->first();

            if (!$pendingApplication) {
                $this->log->warning('Withdrawal attempted for non-existent pending application', [
                    'user' => $user->name,
                    'user_id' => $user->id,
                    'certification' => $certification->title,
                    'certification_uuid' => $certification->uuid,
                    'ip_address' => $request->getClientIp(),
                ]);
                $this->addNotification('certification.withdraw.not_found', NotificationType::ERROR);

                // Return JSON for AJAX requests
                if ($this->isAjaxRequest($request)) {
                    return $this->response->withJson(['error' => 'No pending application found to withdraw.'], 404);
                }

                return $this->redirect->to('/user/certifications');
            }

//            // Update the application status to withdrawn and add withdrawal info
//            $withdrawalNotes = sprintf(
//                'Application withdrawn by user from IP %s at %s. Original notes: %s',
//                $request->getClientIp(),
//                Carbon::now()->toDateTimeString(),
//                $pendingApplication->notes ?? 'None'
//            );

            // Remove the pending application (soft withdrawal by deletion)
            $applicationId = $pendingApplication->id;
            $pendingApplication->delete();

            $this->log->info('User withdrew pending certification application', [
                'user' => $user->name,
                'user_id' => $user->id,
                'certification' => $certification->title,
                'certification_uuid' => $certification->uuid,
                'application_id' => $applicationId,
                'withdrawal_timestamp' => Carbon::now()->toDateTimeString(),
                'ip_address' => $request->getClientIp(),
                'original_notes' => $pendingApplication->notes,
            ]);

            $this->addNotification('certification.withdraw.success');

            // Return JSON for AJAX requests
            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson([
                    'success' => true,
                    'message' => 'Successfully withdrew application for ' . $certification->title . '!',
                ]);
            }

            return $this->redirect->to('/user/certifications');
        } catch (ModelNotFoundException) {
            $this->log->error('Withdrawal attempted for non-existent certification', [
                'user' => $user->name,
                'user_id' => $user->id,
                'certification_uuid' => $data['certification_uuid'],
                'ip_address' => $request->getClientIp(),
            ]);
            $this->addNotification('certification.withdraw.not_found', NotificationType::ERROR);

            // Return JSON for AJAX requests
            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson(['error' => 'Certification not found.'], 404);
            }

            return $this->redirect->to('/user/certifications');
        } catch (\Exception $e) {
            $this->log->error('Unexpected error during application withdrawal', [
                'user' => $user->name,
                'user_id' => $user->id,
                'certification_uuid' => $data['certification_uuid'],
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->getClientIp(),
            ]);
            $this->addNotification('certification.withdraw.system_error', NotificationType::ERROR);

            // Return JSON for AJAX requests
            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson(['error' => 'A system error occurred. Please try again.'], 500);
            }

            return $this->redirect->to('/user/certifications');
        }
    }

    /**
     * Show certification application confirmation view.
     */
    public function showApplyForm(Request $request): Response
    {
        $user = auth()->user();
        $uuid = $request->getAttribute('certification_uuid');

        try {
            /** @var Certification $certification */
            $certification = Certification::where('uuid', $uuid)->firstOrFail();

            // Check if certification is active
            if (!$certification->is_active) {
                $this->addNotification('certification.apply.inactive', NotificationType::ERROR);
                return $this->redirect->to('/user/certifications');
            }

            // Check if user already has this certification
            $existingCertification = CertificationUser::where('user_id', $user->id)
                ->where('certification_id', $certification->id)
                ->first();

            return $this->response->withView(
                'user/certifications/apply',
                [
                    'user' => $user,
                    'certification' => $certification,
                    'existing_certification' => $existingCertification,
                ]
            );
        } catch (ModelNotFoundException) {
            $this->addNotification('certification.apply.not_found', NotificationType::ERROR);
            return $this->redirect->to('/user/certifications');
        }
    }

    /**
     * Allow user to self-confirm an eligible certification.
     */
    public function selfConfirm(Request $request): Response
    {
        $user = auth()->user();

        // Validate inputs from form body
        $data = $this->validate($request, [
            'certification_uuid' => 'required',
            'confirmed' => 'required|accepted',
        ]);

        try {
            // Load certification by UUID
            /** @var Certification $certification */
            $certification = Certification::where('uuid', $data['certification_uuid'])->firstOrFail();

            // Check if certification is active
            if (!$certification->is_active) {
                $this->log->warning('Self-confirmation attempted on inactive certification', [
                    'user' => $user->name,
                    'user_id' => $user->id,
                    'certification' => $certification->title,
                    'certification_uuid' => $certification->uuid,
                    'ip_address' => $request->getClientIp(),
                    'user_agent' => $request->getHeaderLine('User-Agent'),
                ]);
                $this->addNotification('certification.self_confirm.inactive', NotificationType::ERROR);

                // Return JSON for AJAX requests
                if ($this->isAjaxRequest($request)) {
                    return $this->response->withJson(['error' => 'This certification is not currently active.'], 400);
                }

                return $this->redirect->to('/user/certifications');
            }

            // Check self-confirmation policy on certification
            if (!$certification->allow_self_confirmation) {
                $this->log->warning('Self-confirmation attempted on non-self-confirmable certification', [
                    'user' => $user->name,
                    'user_id' => $user->id,
                    'certification' => $certification->title,
                    'certification_uuid' => $certification->uuid,
                    'allow_self_confirmation' => false,
                    'ip_address' => $request->getClientIp(),
                ]);
                $this->addNotification('certification.self_confirm.not_allowed', NotificationType::ERROR);

                // Return JSON for AJAX requests
                if ($this->isAjaxRequest($request)) {
                    return $this->response->withJson(
                        ['error' => 'Self-confirmation is not allowed for this certification.'],
                        403
                    );
                }

                return $this->redirect->to('/user/certifications');
            }

            // Check if user already has this certification
            /** @var CertificationUser|null $certificationUser */
            $certificationUser = CertificationUser::with(['certification'])
                ->where('user_id', $user->id)
                ->where('certification_id', $certification->id)
                ->first();

            if ($certificationUser) {
                // Handle existing certification based on status
                switch ($certificationUser->status) {
                    case 'approved':
                    case 'self_confirmed':
                        // Check if expired and allow re-self-confirmation
                        if (
                            $certificationUser->date_expires &&
                            $certificationUser->date_expires->isPast()
                        ) {
                            $this->log->info('Self-confirmation renewal for expired certification', [
                                'user' => $user->name,
                                'user_id' => $user->id,
                                'certification' => $certification->title,
                                'certification_uuid' => $certification->uuid,
                                'old_status' => $certificationUser->status,
                                'expired_date' => $certificationUser->date_expires->toDateTimeString(),
                                'ip_address' => $request->getClientIp(),
                            ]);
                            break; // Continue with self-confirmation
                        } else {
                            $this->addNotification(
                                'certification.self_confirm.already_valid',
                                NotificationType::WARNING
                            );

                            // Return JSON for AJAX requests
                            if ($this->isAjaxRequest($request)) {
                                return $this->response->withJson(
                                    ['error' => 'You already have a valid certification.'],
                                    400
                                );
                            }

                            return $this->redirect->to('/user/certifications');
                        }

                    case 'pending':
                        // Self-confirm pending application
                        $this->log->info('Self-confirmation of pending application', [
                            'user' => $user->name,
                            'user_id' => $user->id,
                            'certification' => $certification->title,
                            'certification_uuid' => $certification->uuid,
                            'application_id' => $certificationUser->id,
                            'ip_address' => $request->getClientIp(),
                        ]);
                        break; // Continue with self-confirmation

                    case 'expired':
                    case 'revoked':
                        // Allow re-self-confirmation for expired/revoked
                        $this->log->info('Self-confirmation renewal for expired/revoked certification', [
                            'user' => $user->name,
                            'user_id' => $user->id,
                            'certification' => $certification->title,
                            'certification_uuid' => $certification->uuid,
                            'old_status' => $certificationUser->status,
                            'ip_address' => $request->getClientIp(),
                        ]);
                        break; // Continue with self-confirmation
                }
            }

            // Add self-confirmation metadata to notes
            $selfConfirmationNotes = sprintf(
                'Self-confirmed from IP %s at %s. %s',
                $request->getClientIp(),
                Carbon::now()->toDateTimeString(),
                $certificationUser ? 'Updated existing record.' : 'Direct self-confirmation.'
            );

            // Attempt self-confirmation via service
            $updatedCertification = $this->certificationService->selfConfirmCertification(
                $user,
                $certification,
                $selfConfirmationNotes
            );

            $this->log->info('User successfully self-confirmed certification', [
                'user' => $user->name,
                'user_id' => $user->id,
                'certification' => $certification->title,
                'certification_uuid' => $certification->uuid,
                'certification_user_id' => $updatedCertification->id,
                'had_existing_record' => $certificationUser !== null,
                'previous_status' => $certificationUser?->status,
                'new_status' => 'self_confirmed',
                'ip_address' => $request->getClientIp(),
                'user_agent' => substr($request->getHeaderLine('User-Agent'), 0, 255),
                'self_confirmation_timestamp' => $updatedCertification->updated_at->toDateTimeString(),
            ]);

            $this->addNotification('certification.self_confirm.success');

            // Return JSON for AJAX requests
            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson([
                    'success' => true,
                    'message' => 'Successfully self-confirmed ' . $certification->title . '!',
                ]);
            }

            return $this->redirect->to('/user/certifications');
        } catch (ModelNotFoundException) {
            $this->log->error('Self-confirmation attempted for non-existent certification', [
                'user' => $user->name,
                'user_id' => $user->id,
                'certification_uuid' => $data['certification_uuid'],
                'ip_address' => $request->getClientIp(),
            ]);
            $this->addNotification('certification.self_confirm.not_found', NotificationType::ERROR);

            // Return JSON for AJAX requests
            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson(['error' => 'Certification not found.'], 404);
            }

            return $this->redirect->to('/user/certifications');
        } catch (\InvalidArgumentException $e) {
            $this->log->warning('Self-confirmation failed due to validation error', [
                'user' => $user->name,
                'user_id' => $user->id,
                'certification_uuid' => $data['certification_uuid'],
                'error_message' => $e->getMessage(),
                'ip_address' => $request->getClientIp(),
            ]);
            $this->addNotification('certification.self_confirm.error', NotificationType::ERROR);

            // Return JSON for AJAX requests
            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson(['error' => $e->getMessage()], 400);
            }

            return $this->redirect->to('/user/certifications');
        } catch (\RuntimeException $e) {
            $this->log->warning('Self-confirmation failed due to business rule violation', [
                'user' => $user->name,
                'user_id' => $user->id,
                'certification_uuid' => $data['certification_uuid'],
                'error_message' => $e->getMessage(),
                'ip_address' => $request->getClientIp(),
            ]);
            $this->addNotification('certification.self_confirm.error', NotificationType::ERROR);

            // Return JSON for AJAX requests
            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson(['error' => $e->getMessage()], 400);
            }

            return $this->redirect->to('/user/certifications');
        } catch (\Exception $e) {
            $this->log->error('Unexpected error during self-confirmation', [
                'user' => $user->name,
                'user_id' => $user->id,
                'certification_uuid' => $data['certification_uuid'],
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->getClientIp(),
            ]);
            $this->addNotification('certification.self_confirm.system_error', NotificationType::ERROR);

            // Return JSON for AJAX requests
            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson(['error' => 'A system error occurred. Please try again.'], 500);
            }

            return $this->redirect->to('/user/certifications');
        }
    }

    /**
     * Check if request is AJAX
     */
    private function isAjaxRequest(Request $request): bool
    {
        return $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Show self-confirmation form for a specific certification
     */
    public function showSelfConfirm(Request $request): Response
    {
        try {
            $user = auth()->user();
            $certification_uuid = (string) $request->getAttribute('certification_uuid');

            // Find certification by UUID
            /** @var Certification $certification */
            $certification = Certification::where('uuid', $certification_uuid)->firstOrFail();

            // Check if certification is active
            if (!$certification->is_active) {
                $this->addNotification('certification.self_confirm.inactive', NotificationType::ERROR);
                return $this->redirect->to('/user/certifications');
            }

            // Check if certification allows self-confirmation
            if (!$certification->allow_self_confirmation) {
                $this->addNotification('certification.self_confirm.not_allowed', NotificationType::ERROR);
                return $this->redirect->to('/user/certifications');
            }

            // Get existing certification if any
            $existingCertification = CertificationUser::where('user_id', $user->id)
                ->where('certification_id', $certification->id)
                ->first();

            return $this->response->withView('user/certifications/self-confirm', [
                'certification' => $certification,
                'existing_certification' => $existingCertification,
                'user' => $user,
            ]);
        } catch (ModelNotFoundException) {
            $this->addNotification('certification.not_found', NotificationType::ERROR);
            return $this->redirect->to('/user/certifications');
        } catch (\Exception $e) {
            $this->log->error('Error showing self-confirmation form for certification {uuid}', [
                'uuid' => $certification_uuid ?? 'INVALID UUID',
                'user_id' => auth()->user()?->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->addNotification('certification.form.load_error', NotificationType::ERROR);
            return $this->redirect->to('/user/certifications');
        }
    }

    /**
     * Export the authenticated user's certifications.
     */
    public function export(Request $request): Response
    {
        $user = auth()->user();
        $format = strtolower((string) $request->get('format', 'csv'));

        $certifications = $this->certificationService->getUserCertifications($user, []);

        // Normalize data
        $rows = [];
        foreach ($certifications as $cert) {
            $pivot = $cert->pivot;
            $rows[] = [
                'title' => $cert->title,
                'status' => $pivot->status,
                'date_certified' => $pivot->date_certified ? $pivot->date_certified->format('Y-m-d') : '',
                'date_expires' => $pivot->date_expires ? $pivot->date_expires->format('Y-m-d') : '',
                'notes' => (string) ($pivot->notes ?? ''),
            ];
        }

        if ($format === 'json') {
            return $this->response->withJson(['user' => $user->name, 'certifications' => $rows]);
        }

        $filename = 'certifications-' . date('Ymd') . '.csv';
        $rowsLocal = $rows;
        return $this->response->download(function () use ($rowsLocal): void {
            $out = fopen('php://output', 'w');
            // CSV header
            fputcsv($out, ['Title', 'Status', 'Date Certified', 'Date Expires', 'Notes']);
            foreach ($rowsLocal as $r) {
                fputcsv($out, [$r['title'], $r['status'], $r['date_certified'], $r['date_expires'], $r['notes']]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /**
     * Show renewal confirmation for a certification if eligible.
     */
    public function renew(Request $request): Response
    {
        $user = auth()->user();
        $uuid = (string) $request->getAttribute('certification_uuid');

        try {
            /** @var Certification $certification */
            $certification = Certification::where('uuid', $uuid)->firstOrFail();

            /** @var CertificationUser|null $certificationUser */
            $certificationUser = CertificationUser::with(['certification'])
                ->where('user_id', $user->id)
                ->where('certification_id', $certification->id)
                ->first();

            if (!$certificationUser) {
                $this->addNotification('certification.self_confirm.error', NotificationType::ERROR);
                return $this->redirect->to('/user/certifications');
            }

            // Determine eligibility: expired or expiring within 30 days
            $isExpired = false;
            $isExpiringSoon = false;
            if ($certificationUser->date_expires) {
                $now = \Carbon\Carbon::now();
                $expires = \Carbon\Carbon::parse($certificationUser->date_expires);
                $isExpired = $expires->isPast();
                $isExpiringSoon = !$isExpired && $expires->lte($now->copy()->addDays(30));
            }

            if (!$certification->allow_self_confirmation || (!$isExpired && !$isExpiringSoon)) {
                $this->addNotification('certification.self_confirm.error', NotificationType::ERROR);
                return $this->redirect->to('/user/certifications');
            }

            return $this->response->withView('user/certifications/renew', [
                'user' => $user,
                'certification' => $certification,
                'pivot' => $certificationUser,
                'is_expired' => $isExpired,
                'is_expiring_soon' => $isExpiringSoon,
            ]);
        } catch (ModelNotFoundException) {
            $this->addNotification('certification.self_confirm.error', NotificationType::ERROR);
            return $this->redirect->to('/user/certifications');
        }
    }

    /**
     * Process renewal submission for a certification.
     */
    public function processRenewal(Request $request): Response
    {
        $user = auth()->user();
        $uuid = (string) $request->getAttribute('certification_uuid');

        $this->validate($request, [
            'confirmed' => 'required|accepted',
        ]);

        try {
            /** @var Certification $certification */
            $certification = Certification::where('uuid', $uuid)->firstOrFail();

            /** @var CertificationUser|null $certificationUser */
            $certificationUser = CertificationUser::with(['certification'])
                ->where('user_id', $user->id)
                ->where('certification_id', $certification->id)
                ->first();

            if ($certification->allow_self_confirmation) {
                // Let the service handle renewal/expiry recalculation
                $this->certificationService->selfConfirmCertification($user, $certification);
                $this->addNotification('certification.self_confirm.success');
            } else {
                // Fallback: create or set status pending for admin approval
                if ($certificationUser) {
                    $this->certificationService->updateUserCertification($certificationUser, ['status' => 'pending']);
                } else {
                    $this->certificationService->addCertificationToUser($user, $certification, 'pending');
                }
                $this->addNotification('certification.user.update.success');
            }

            return $this->redirect->to('/user/certifications');
        } catch (ModelNotFoundException) {
            $this->addNotification('certification.self_confirm.error', NotificationType::ERROR);
            return $this->redirect->to('/user/certifications');
        } catch (\InvalidArgumentException $e) {
            $this->addNotification('certification.self_confirm.error', NotificationType::ERROR);
            return $this->redirect->to('/user/certifications');
        }
    }

    /**
     * Get user from request (either authenticated user or specified user ID for admin).
     */
    protected function getUser(Request $request): User
    {
        $userId = $request->getAttribute('user_id');

        if ($userId) {
            // Admin viewing specific user's certifications
            $this->checkPermission('certification.admin');
            return User::findOrFail($userId);
        }

        // User viewing their own certifications
        return auth()->user();
    }

    /**
     * Resolve certification user relationship from request.
     */
    protected function resolveCertificationUser(Request $request): CertificationUser
    {
        $certificationUserId = $request->getAttribute('user_certification_id');

        if (!$certificationUserId) {
            throw new ModelNotFoundException('Certification user relationship not found');
        }

        /** @var CertificationUser $certificationUser */
        $certificationUser = CertificationUser::with(['certification', 'user'])->findOrFail($certificationUserId);

        return $certificationUser;
    }

    /**
     * Approve a pending certification application (admin function)
     */
    public function approveApplication(Request $request): Response
    {
        $this->checkPermission('certificates.admin');

        // Validate inputs
        $data = $this->validate($request, [
            'application_id' => 'required|integer',
            'notes' => 'string|max:1000',
        ]);

        try {
            /** @var CertificationUser $application */
            $application = CertificationUser::with(['user', 'certification'])->findOrFail($data['application_id']);

            // Check if application is in pending status
            if ($application->status !== 'pending') {
                $this->log->warning('Admin attempted to approve non-pending application', [
                    'admin' => auth()->user()->name,
                    'admin_id' => auth()->user()->id,
                    'application_id' => $application->id,
                    'current_status' => $application->status,
                    'user' => $application->user->name,
                    'certification' => $application->certification->title,
                ]);

                $this->addNotification('certification.admin.approve.not_pending', NotificationType::ERROR);

                if ($this->isAjaxRequest($request)) {
                    return $this->response->withJson(['error' => 'Application is not in pending status.'], 400);
                }

                return $this->redirect->to('/admin/user-certifications');
            }

            // Update application status and details
            $adminNotes = sprintf(
                'Approved by admin %s on %s. Admin notes: %s. Original notes: %s',
                auth()->user()->name,
                Carbon::now()->toDateTimeString(),
                $data['notes'] ?? 'None',
                $application->notes ?? 'None'
            );

            $application->status = 'approved';
            $application->date_certified = Carbon::now();

            // Set expiry date if not perpetual
            if (!$application->certification->is_perpetual && $application->certification->validity_period_days) {
                $application->date_expires = Carbon::now()->addDays($application->certification->validity_period_days);
            }

            $application->notes = $adminNotes;
            $application->save();

            $this->log->info('Admin approved certification application', [
                'admin' => auth()->user()->name,
                'admin_id' => auth()->user()->id,
                'application_id' => $application->id,
                'user' => $application->user->name,
                'user_id' => $application->user->id,
                'certification' => $application->certification->title,
                'certification_id' => $application->certification->id,
                'admin_notes' => $data['notes'] ?? 'None',
                'expires_at' => $application->date_expires?->toDateTimeString(),
            ]);

            $this->addNotification('certification.admin.approve.success');

            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson([
                    'success' => true,
                    'message' => 'Application approved successfully!',
                ]);
            }

            return $this->redirect->to('/admin/user-certifications');
        } catch (ModelNotFoundException) {
            $this->log->error('Admin attempted to approve non-existent application', [
                'admin' => auth()->user()->name,
                'admin_id' => auth()->user()->id,
                'application_id' => $data['application_id'],
            ]);

            $this->addNotification('certification.admin.application_not_found', NotificationType::ERROR);

            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson(['error' => 'Application not found.'], 404);
            }

            return $this->redirect->to('/admin/user-certifications');
        } catch (\Exception $e) {
            $this->log->error('Error approving certification application', [
                'admin' => auth()->user()->name,
                'admin_id' => auth()->user()->id,
                'application_id' => $data['application_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addNotification('certification.admin.approve.error', NotificationType::ERROR);

            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson(
                    ['error' => 'An error occurred while approving the application.'],
                    500
                );
            }

            return $this->redirect->to('/admin/user-certifications');
        }
    }

    /**
     * Reject a pending certification application (admin function)
     */
    public function rejectApplication(Request $request): Response
    {
        $this->checkPermission('certificates.admin');

        // Validate inputs
        $data = $this->validate($request, [
            'application_id' => 'required|integer',
            'rejection_reason' => 'required|string|max:1000',
        ]);

        try {
            /** @var CertificationUser $application */
            $application = CertificationUser::with(['user', 'certification'])->findOrFail($data['application_id']);

            // Check if application is in pending status
            if ($application->status !== 'pending') {
                $this->log->warning('Admin attempted to reject non-pending application', [
                    'admin' => auth()->user()->name,
                    'admin_id' => auth()->user()->id,
                    'application_id' => $application->id,
                    'current_status' => $application->status,
                    'user' => $application->user->name,
                    'certification' => $application->certification->title,
                ]);

                $this->addNotification('certification.admin.reject.not_pending', NotificationType::ERROR);

                if ($this->isAjaxRequest($request)) {
                    return $this->response->withJson(['error' => 'Application is not in pending status.'], 400);
                }

                return $this->redirect->to('/admin/user-certifications');
            }

            // Update application status and details
            $adminNotes = sprintf(
                'Rejected by admin %s on %s. Rejection reason: %s. Original notes: %s',
                auth()->user()->name,
                Carbon::now()->toDateTimeString(),
                $data['rejection_reason'],
                $application->notes ?? 'None'
            );

            $application->status = 'rejected';
            $application->notes = $adminNotes;
            $application->save();

            $this->log->info('Admin rejected certification application', [
                'admin' => auth()->user()->name,
                'admin_id' => auth()->user()->id,
                'application_id' => $application->id,
                'user' => $application->user->name,
                'user_id' => $application->user->id,
                'certification' => $application->certification->title,
                'certification_id' => $application->certification->id,
                'rejection_reason' => $data['rejection_reason'],
            ]);

            $this->addNotification('certification.admin.reject.success');

            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson([
                    'success' => true,
                    'message' => 'Application rejected successfully!',
                ]);
            }

            return $this->redirect->to('/admin/user-certifications');
        } catch (ModelNotFoundException) {
            $this->log->error('Admin attempted to reject non-existent application', [
                'admin' => auth()->user()->name,
                'admin_id' => auth()->user()->id,
                'application_id' => $data['application_id'],
            ]);

            $this->addNotification('certification.admin.application_not_found', NotificationType::ERROR);

            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson(['error' => 'Application not found.'], 404);
            }

            return $this->redirect->to('/admin/user-certifications');
        } catch (\Exception $e) {
            $this->log->error('Error rejecting certification application', [
                'admin' => auth()->user()->name,
                'admin_id' => auth()->user()->id,
                'application_id' => $data['application_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addNotification('certification.admin.reject.error', NotificationType::ERROR);

            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson([
                    'error' => 'An error occurred while rejecting the application.',
                ], 500);
            }

            return $this->redirect->to('/admin/user-certifications');
        }
    }

    /**
     * Calculate approval rate as percentage
     */
    private function calculateApprovalRate(): float
    {
        $totalDecided = CertificationUser::whereIn('status', ['approved', 'rejected', 'self_confirmed'])->count();

        if ($totalDecided === 0) {
            return 0.0;
        }

        $approved = CertificationUser::whereIn('status', ['approved', 'self_confirmed'])->count();

        return round(($approved / $totalDecided) * 100, 1);
    }

    /**
     * Check if user has required permission.
     */
    protected function checkPermission(string $permission): void
    {
        if (!auth()->user()->hasPermission($permission)) {
            throw new \Engelsystem\Http\Exceptions\HttpForbidden();
        }
    }
}

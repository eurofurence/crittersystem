<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Carbon\Carbon;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Models\DigitalIdToken;
use Engelsystem\Models\Shifts\ShiftEntry;
use Engelsystem\Models\CertificationToken;
use Engelsystem\Models\CertificationUser;

class QrController extends BaseController
{
    public function __construct(
        protected Response $response
    ) {
    }

    /**
     * Verify QR token and display user profile
     */
    public function verifyToken(Request $request): Response
    {
        $tokenString = $request->getAttribute('token');

        if (!$tokenString) {
            return $this->response->withView('digital-id/verify-error', [
                'error' => __('No token provided'),
                'message' => __('The QR code appears to be invalid or incomplete.'),
            ]);
        }

        // Clean up expired tokens first
        DigitalIdToken::cleanupExpiredTokens();

        // Find active token
        $token = DigitalIdToken::findActiveToken($tokenString);

        if (!$token) {
            return $this->response->withView('digital-id/verify-error', [
                'error' => __('Invalid or expired token'),
                'message' => __(
                    'This QR code has expired or is not valid. '
                    . 'Please ask the user to refresh their Digital ID.'
                ),
            ]);
        }

        $user = $token->user;

        if (!$user) {
            return $this->response->withView('digital-id/verify-error', [
                'error' => __('User not found'),
                'message' => __('The user associated with this token could not be found.'),
            ]);
        }

        // Get user's current and next shifts
        $currentShift = $this->getCurrentShift($user->id);
        $nextShift = $this->getNextShift($user->id);

        return $this->response->withView('digital-id/verify', [
            'user'         => $user,
            'token'        => $token,
            'isBoD'        => $user->hasPermission('user.type.bod'),
            'isDirector'   => $user->hasPermission('user.type.director'),
            'isStaff'      => $user->hasPermission('user.type.staff'),
            'badgeNumber'  => $user->personalData->badge_number ?? null,
            'currentShift' => $currentShift,
            'nextShift'    => $nextShift,
            'verifiedAt'   => Carbon::now(),
        ]);
    }

    /**
     * Generate a new QR token for user (API method)
     */
    public function generateToken(Request $request): Response
    {
        $userId = (int) $request->getAttribute('user_id');

        if (!$userId || !auth()->can('admin_user_angeltypes')) {
            return $this->response->withJson(['error' => 'Unauthorized'], 401);
        }

        try {
            $token = $this->createUserToken($userId);

            return $this->response->withJson([
                'success' => true,
                'token' => $token->token,
                'expires_at' => $token->expires_at->toISOString(),
                'verification_url' => url('/digital-id/verify/' . $token->token),
            ]);
        } catch (\Exception $e) {
            return $this->response->withJson(['error' => 'Failed to generate token'], 500);
        }
    }

    /**
     * Get user's current active shift
     */
    protected function getCurrentShift(int $userId): ?array
    {
        $now = Carbon::now();

        $shiftEntry = ShiftEntry::with(['shift.shiftType', 'shift.location'])
            ->where('user_id', $userId)
            ->whereHas('shift', function ($query) use ($now): void {
                $query->where('start', '<=', $now)
                      ->where('end', '>', $now);
            })
            ->first();

        if (!$shiftEntry || !$shiftEntry->shift) {
            return null;
        }

        $shift = $shiftEntry->shift;

        return [
            'id' => $shift->id,
            'title' => $shift->title,
            'type' => $shift->shiftType->name ?? 'Unknown',
            'location' => $shift->location->name ?? 'Unknown',
            'start' => $shift->start,
            'end' => $shift->end,
            'progress' => $this->calculateShiftProgress($shift->start, $shift->end, $now),
        ];
    }

    /**
     * Get user's next upcoming shift
     */
    protected function getNextShift(int $userId): ?array
    {
        $now = Carbon::now();

        $shiftEntry = ShiftEntry::with(['shift.shiftType', 'shift.location'])
            ->where('user_id', $userId)
            ->whereHas('shift', function ($query) use ($now): void {
                $query->where('start', '>', $now);
            })
            ->join('shifts', 'shift_entries.shift_id', '=', 'shifts.id')
            ->orderBy('shifts.start')
            ->select('shift_entries.*')
            ->first();

        if (!$shiftEntry || !$shiftEntry->shift) {
            return null;
        }

        $shift = $shiftEntry->shift;

        return [
            'id' => $shift->id,
            'title' => $shift->title,
            'type' => $shift->shiftType->name ?? 'Unknown',
            'location' => $shift->location->name ?? 'Unknown',
            'start' => $shift->start,
            'end' => $shift->end,
            'starts_in_minutes' => $now->diffInMinutes($shift->start),
            'starts_in_hours' => round($now->diffInHours($shift->start, false), 1),
        ];
    }

    /**
     * Calculate shift progress as percentage
     */
    protected function calculateShiftProgress(Carbon $start, Carbon $end, Carbon $now): int
    {
        $totalDuration = $start->diffInMinutes($end);
        $elapsedDuration = $start->diffInMinutes($now);

        if ($totalDuration <= 0) {
            return 100;
        }

        return min(100, max(0, (int) round(($elapsedDuration / $totalDuration) * 100)));
    }

    /**
     * Get active token for a user
     */
    public function getActiveToken(int $userId): ?DigitalIdToken
    {
        DigitalIdToken::cleanupExpiredTokens();

        return DigitalIdToken::where('user_id', $userId)->active()->first();
    }

    /**
     * Create a new token for user
     */
    protected function createUserToken(int $userId): DigitalIdToken
    {
        $refreshInterval = \Engelsystem\Models\DigitalIdConfig::getRefreshInterval();
        $overlapPeriod = \Engelsystem\Models\DigitalIdConfig::getTokenOverlap();

        // Token expires after refresh interval + overlap period
        $expiresAt = Carbon::now()->addSeconds($refreshInterval + $overlapPeriod);

        return DigitalIdToken::create([
            'user_id' => $userId,
            'token' => DigitalIdToken::generateToken(),
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Verify certification token and handle confirmation
     */
    public function verifyCertificationToken(Request $request): Response
    {
        $tokenString = $request->getAttribute('token');

        if (!$tokenString) {
            return $this->response->withView('digital-id/verify-error', [
                'error' => __('No token provided'),
                'message' => __('The QR code appears to be invalid or incomplete.'),
            ]);
        }

        // Clean up expired tokens first
        CertificationToken::cleanupExpiredTokens();

        // Find active token
        $token = CertificationToken::findActiveToken($tokenString);

        if (!$token) {
            return $this->response->withView('digital-id/verify-error', [
                'error' => __('Invalid or expired token'),
                'message' => __(
                    'This QR code has expired or is not valid. '
                    . 'Please ask the administrator to refresh the certification QR code.'
                ),
            ]);
        }

        $certification = $token->certification;

        if (!$certification || !$certification->is_active) {
            return $this->response->withView('digital-id/verify-error', [
                'error' => __('Certification not available'),
                'message' => __('The certification associated with this token is not available.'),
            ]);
        }

        // Check if user is authenticated
        $user = auth()->user();
        if (!$user) {
            // Redirect to login with return URL
            $returnUrl = url('/certification-scan/verify/' . $tokenString);
            session()->set('url.intended', $returnUrl);
            return $this->response->redirectTo('/login');
        }

        // Check if user has applied for this certification
        $certificationUser = CertificationUser::where('user_id', $user->id)
            ->where('certification_id', $certification->id)
            ->first();

        if (!$certificationUser) {
            return $this->response->withView('digital-id/verify-error', [
                'error' => __('Not applied for this certification'),
                'message' => __('You have not applied for this certification. Please apply first before scanning the QR code.'), // phpcs:ignore
            ]);
        }

        // Check if already approved or self-confirmed
        if (in_array($certificationUser->status, [CertificationUser::STATUS_APPROVED, CertificationUser::STATUS_SELF_CONFIRMED])) { // phpcs:ignore
            return $this->response->withView('digital-id/verify-error', [
                'error' => __('Already confirmed'),
                'message' => __('Your certification has already been confirmed.'),
            ]);
        }

        // Approve the certification
        $certificationUser->status = CertificationUser::STATUS_APPROVED;
        $certificationUser->date_certified = Carbon::now();
        $certificationUser->certified_by = null; // QR-based confirmation - no specific admin

        // Calculate expiry date if not perpetual
        if (!$certification->is_perpetual && $certification->validity_period_days) {
            $certificationUser->date_expires = Carbon::now()->addDays($certification->validity_period_days);
        }

        $certificationUser->save();

        return $this->response->withView('certification-scan/success', [
            'certification' => $certification,
            'user' => $user,
            'certificationUser' => $certificationUser,
        ]);
    }
}

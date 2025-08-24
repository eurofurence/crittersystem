<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Carbon\Carbon;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Models\DigitalIdConfig;
use Engelsystem\Models\DigitalIdToken;

class DigitalIdController extends BaseController
{
    public function __construct(
        protected Response $response
    ) {
    }

    /**
     * Display digital ID card for current user
     */
    public function index(Request $request): Response
    {
        // Check if user is authenticated
        if (!auth()->user()) {
            return redirect(url('/login'));
        }

        // Check if digital ID feature is enabled
        if (!DigitalIdConfig::isDigitalIdEnabled()) {
            error(__('Digital ID system is currently disabled.'));
            return redirect(url('/'));
        }

        $user = auth()->user();
        $refreshInterval = DigitalIdConfig::getRefreshInterval();

        // Get or generate active token for user
        $token = $this->getOrCreateActiveToken($user->id);

        // Calculate when to refresh the token (before it expires)
        $refreshTime = $token->expires_at->subSeconds($refreshInterval / 2);
        $needsRefresh = Carbon::now()->isAfter($refreshTime);

        // If token needs refresh, create a new one
        if ($needsRefresh) {
            $token = $this->refreshUserToken($user->id);
        }

        // Generate QR code URL
        $verificationUrl = url('/digital-id/verify/' . $token->token);

        return $this->response->withView('digital-id/index', [
            'user' => $user,
            'token' => $token,
            'verificationUrl' => $verificationUrl,
            'refreshInterval' => $refreshInterval,
            'isStaff' => $user->hasPermission('user.type.staff'),
            'badgeNumber' => $user->personalData->badge_number ?? null,
        ]);
    }

    /**
     * AJAX endpoint to refresh QR token
     */
    public function refreshToken(Request $request): Response
    {
        // Check if user is authenticated
        if (!auth()->user()) {
            return $this->response->withJson(['error' => 'Unauthorized'], 401);
        }

        // Check if digital ID feature is enabled
        if (!DigitalIdConfig::isDigitalIdEnabled()) {
            return $this->response->withJson(['error' => 'Digital ID system is disabled'], 403);
        }

        $user = auth()->user();

        try {
            // Create new token
            $token = $this->refreshUserToken($user->id);
            $verificationUrl = url('/digital-id/verify/' . $token->token);

            return $this->response->withJson([
                'success' => true,
                'token' => $token->token,
                'verificationUrl' => $verificationUrl,
                'expiresAt' => $token->expires_at->toISOString(),
            ]);
        } catch (\Exception $e) {
            return $this->response->withJson(['error' => 'Failed to refresh token'], 500);
        }
    }

    /**
     * Get existing active token or create new one
     */
    protected function getOrCreateActiveToken(int $userId): DigitalIdToken
    {
        // Clean up expired tokens first
        DigitalIdToken::cleanupExpiredTokens();

        // Try to find existing active token for user
        $existingToken = DigitalIdToken::where('user_id', $userId)
            ->active()
            ->first();

        if ($existingToken) {
            return $existingToken;
        }

        // Create new token
        return $this->createUserToken($userId);
    }

    /**
     * Create a new token for user
     */
    protected function createUserToken(int $userId): DigitalIdToken
    {
        $refreshInterval = DigitalIdConfig::getRefreshInterval();
        $overlapPeriod = DigitalIdConfig::getTokenOverlap();

        // Token expires after refresh interval + overlap period
        $expiresAt = Carbon::now()->addSeconds($refreshInterval + $overlapPeriod);

        return DigitalIdToken::create([
            'user_id' => $userId,
            'token' => DigitalIdToken::generateToken(),
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Refresh user token (keeping old one valid during overlap)
     */
    protected function refreshUserToken(int $userId): DigitalIdToken
    {
        // Create new token
        $newToken = $this->createUserToken($userId);

        // Clean up tokens that are past overlap period
        $overlapPeriod = DigitalIdConfig::getTokenOverlap();
        $cleanupTime = Carbon::now()->subSeconds($overlapPeriod);

        DigitalIdToken::where('user_id', $userId)
            ->where('expires_at', '<', $cleanupTime)
            ->delete();

        return $newToken;
    }
}

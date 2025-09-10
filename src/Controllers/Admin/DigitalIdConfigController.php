<?php

declare(strict_types=1);

namespace Engelsystem\Controllers\Admin;

use Engelsystem\Controllers\BaseController;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Models\DigitalIdConfig;

class DigitalIdConfigController extends BaseController
{
    /** @var string[] */
    protected array $permissions = [
        'config.edit',
    ];

    public function __construct(
        protected Response $response
    ) {
    }

    /**
     * Display digital ID configuration form
     */
    public function index(): Response
    {
        $config = [
            'digital_id_enabled' => DigitalIdConfig::isDigitalIdEnabled(),
            'digital_id_refresh_interval' => DigitalIdConfig::getRefreshInterval(),
            'digital_id_token_overlap' => DigitalIdConfig::getTokenOverlap(),
        ];

        return $this->response->withView('admin/digital-id/config', [
            'config' => $config,
        ]);
    }

    /**
     * Save digital ID configuration
     */
    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'digital_id_enabled' => 'accepted',
            'digital_id_refresh_interval' => 'required|numeric|min:30|max:3600',
            'digital_id_token_overlap' => 'required|numeric|min:0|max:300',
        ]);

        try {
            // Set enabled/disabled status
            DigitalIdConfig::setDigitalIdEnabled($request->has('digital_id_enabled'));

            // Set refresh interval with validation
            $refreshInterval = (int) $data['digital_id_refresh_interval'];
            if ($refreshInterval < 30) {
                error(__('Refresh interval must be at least 30 seconds.'));
                return $this->index();
            }
            DigitalIdConfig::setRefreshInterval($refreshInterval);

            // Set token overlap period
            $tokenOverlap = (int) $data['digital_id_token_overlap'];
            if ($tokenOverlap < 0) {
                error(__('Token overlap must be non-negative.'));
                return $this->index();
            }
            DigitalIdConfig::setTokenOverlap($tokenOverlap);

            // Log the configuration change
            $user = auth()->user();
            $enabledStatus = $request->has('digital_id_enabled') ? 'enabled' : 'disabled';

            engelsystem_log(sprintf(
                'Digital ID configuration updated by %s: enabled=%s, refresh_interval=%ds, token_overlap=%ds',
                $user->displayName,
                $enabledStatus,
                $refreshInterval,
                $tokenOverlap
            ));

            success(__('Digital ID configuration saved successfully.'));

            return redirect(url('/admin/digital-id'));
        } catch (\InvalidArgumentException $e) {
            error(__('Invalid configuration values: %s', [$e->getMessage()]));
            return $this->index();
        } catch (\Exception $e) {
            error(__('Failed to save configuration. Please try again.'));
            return $this->index();
        }
    }
}

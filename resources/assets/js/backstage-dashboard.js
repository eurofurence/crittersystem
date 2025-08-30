// Backstage Dashboard Real-time Updates
(function() {
    'use strict';

    let refreshInterval;
    const REFRESH_INTERVAL = 30000; // 30 seconds default

    function updateKPIs(kpis) {
        if (kpis) {
            const elements = {
                'kpi-eligible-users': kpis.eligible_users || 0,
                'kpi-qualified-users': kpis.qualified_users || 0,
                'kpi-distributions-today': kpis.distributions_today || 0,
                'kpi-distributions-week': kpis.distributions_week || 0,
                'kpi-total-hours': (kpis.total_hours_calculated || 0) + 'h',
                'kpi-pending-recalc': kpis.pending_recalculations || 0
            };

            for (const [elementId, value] of Object.entries(elements)) {
                const element = document.getElementById(elementId);
                if (element) {
                    element.textContent = value;
                }
            }
        }
    }

    function updateSystemStatus(status) {
        if (status) {
            const statusContainer = document.getElementById('system-status');
            let statusHtml = '';

            for (const [service, serviceStatus] of Object.entries(status)) {
                const statusClass = serviceStatus === 'operational' ? 'success' :
                                  (serviceStatus === 'degraded' ? 'warning' : 'danger');
                const statusIcon = serviceStatus === 'operational' ? 'check-circle' :
                                  (serviceStatus === 'degraded' ? 'exclamation-triangle' : 'x-circle');

                statusHtml += `
                    <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                        <span class="fw-medium">${service}</span>
                        <span class="badge bg-${statusClass}">
                            <i class="bi bi-${statusIcon}"></i>
                            ${serviceStatus}
                        </span>
                    </div>
                `;
            }

            if (statusContainer) {
                statusContainer.innerHTML = statusHtml;
            }
        }

        const lastUpdate = document.getElementById('status-last-update');
        if (lastUpdate) {
            lastUpdate.textContent = new Date().toLocaleTimeString();
            lastUpdate.className = 'badge bg-success';
        }
    }

    function refreshData() {
        // Get base URL for API calls
        const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
        
        fetch(baseUrl + '/api/v1/backstage/live-stats', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                updateKPIs(data.data.kpis);
                updateSystemStatus(data.data.system_status);
            }
        })
        .catch(error => {
            console.warn('Failed to refresh dashboard data:', error);
            const lastUpdate = document.getElementById('status-last-update');
            if (lastUpdate) {
                lastUpdate.textContent = 'Update Error';
                lastUpdate.className = 'badge bg-danger';
            }
        });
    }

    // Initialize real-time updates
    document.addEventListener('DOMContentLoaded', function() {
        // Get refresh interval from data attribute if available
        const dashboardEl = document.querySelector('[data-refresh-interval]');
        const customInterval = dashboardEl ? parseInt(dashboardEl.dataset.refreshInterval) : REFRESH_INTERVAL;
        
        // Start periodic refresh
        refreshInterval = setInterval(refreshData, customInterval);

        // Refresh on page visibility change
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                refreshData();
            }
        });

        // Manual refresh button
        const refreshBtn = document.getElementById('manual-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                refreshData();
                this.disabled = true;
                setTimeout(() => this.disabled = false, 2000);
            });
        }

        // Initial load
        refreshData();
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    });
})();
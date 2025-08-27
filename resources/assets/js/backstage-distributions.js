(function() {
    'use strict';

    // Auto-refresh distributions every 30 seconds if on current page
    let refreshInterval;
    const REFRESH_INTERVAL = 30000; // 30 seconds

    function refreshDistributions() {
        // Only refresh if no filters are applied to avoid disrupting user's view
        const hasFilters = new URLSearchParams(window.location.search).has('user_search') ||
                          new URLSearchParams(window.location.search).has('date_from') ||
                          new URLSearchParams(window.location.search).has('distributor');

        if (!hasFilters && !document.hidden) {
            fetch(window.location.href, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(html => {
                // Update only the stats cards without disrupting the table
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newStats = doc.querySelectorAll('.card.border-primary h3, .card.border-success h3, .card.border-info h3, .card.border-warning h3');
                const currentStats = document.querySelectorAll('.card.border-primary h3, .card.border-success h3, .card.border-info h3, .card.border-warning h3');

                newStats.forEach((stat, index) => {
                    if (currentStats[index]) {
                        currentStats[index].textContent = stat.textContent;
                    }
                });
            })
            .catch(error => {
                console.warn('Failed to refresh distribution stats:', error);
            });
        }
    }

    // Initialize auto-refresh
    document.addEventListener('DOMContentLoaded', function() {
        refreshInterval = setInterval(refreshDistributions, REFRESH_INTERVAL);

        // Stop refresh when page is hidden
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                if (refreshInterval) {
                    clearInterval(refreshInterval);
                }
            } else {
                refreshInterval = setInterval(refreshDistributions, REFRESH_INTERVAL);
                refreshDistributions(); // Immediate refresh when page becomes visible
            }
        });

        // Print button functionality
        const printBtn = document.getElementById('print-btn');
        if (printBtn) {
            printBtn.addEventListener('click', function() {
                window.print();
            });
        }
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    });

    // Date range validation
    const dateFromInput = document.querySelector('input[name="date_from"]');
    const dateToInput = document.querySelector('input[name="date_to"]');

    if (dateFromInput && dateToInput) {
        function validateDateRange() {
            if (dateFromInput.value && dateToInput.value) {
                if (new Date(dateFromInput.value) > new Date(dateToInput.value)) {
                    dateToInput.value = dateFromInput.value;
                }
            }
        }

        dateFromInput.addEventListener('change', validateDateRange);
        dateToInput.addEventListener('change', validateDateRange);
    }
})();
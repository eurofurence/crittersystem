(function() {
    'use strict';

    // User Details Modal Handler
    const userDetailsModal = document.getElementById('userDetailsModal');
    if (userDetailsModal) {
        userDetailsModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const modalContent = document.getElementById('userDetailsContent');

            if (userId && modalContent) {
                // Reset content
                modalContent.innerHTML = `
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                `;

                // Get base URL for API
                const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
                
                // Fetch user details
                fetch(`${baseUrl}/api/v1/backstage/users/${userId}/hours`, {
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
                        modalContent.innerHTML = renderUserDetails(data.data);
                    } else {
                        modalContent.innerHTML = `
                            <div class="alert alert-warning">
                                <h6>Error Loading Details</h6>
                                <p class="mb-0">Unable to load user details at this time.</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error fetching user details:', error);
                    modalContent.innerHTML = `
                        <div class="alert alert-danger">
                            <h6>Network Error</h6>
                            <p class="mb-0">Unable to connect to the server. Please try again later.</p>
                        </div>
                    `;
                });
            }
        });
    }

    function renderUserDetails(userData) {
        const hoursData = userData.hours_calculation || {};
        const shifts = userData.recent_shifts || [];
        
        return `
            <div class="row">
                <div class="col-12">
                    <!-- User Basic Info -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">👤 User Information</h6>
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td class="text-muted">Name:</td>
                                            <td><strong>${userData.display_name || userData.name}</strong></td>
                                        </tr>
                                        ${userData.badge_number ? `
                                        <tr>
                                            <td class="text-muted">Badge #:</td>
                                            <td><span class="badge bg-secondary">${userData.badge_number}</span></td>
                                        </tr>
                                        ` : ''}
                                        <tr>
                                            <td class="text-muted">Status:</td>
                                            <td>
                                                ${userData.arrived ? 
                                                    '<span class="badge bg-success">Arrived</span>' : 
                                                    '<span class="badge bg-secondary">Not Arrived</span>'
                                                }
                                                ${userData.active ? 
                                                    '<span class="badge bg-success ms-1">Active</span>' : 
                                                    '<span class="badge bg-warning ms-1">Inactive</span>'
                                                }
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-success mb-3">⏱️ Hours Summary</h6>
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td class="text-muted">Total Hours:</td>
                                            <td><strong class="text-success fs-5">${(hoursData.total_hours || 0).toFixed(1)}h</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Completed Shifts:</td>
                                            <td><span class="badge bg-primary">${hoursData.completed_shifts || 0}</span></td>
                                        </tr>
                                        ${hoursData.night_shifts ? `
                                        <tr>
                                            <td class="text-muted">Night Shifts:</td>
                                            <td><span class="badge bg-dark">${hoursData.night_shifts}</span></td>
                                        </tr>
                                        ` : ''}
                                        <tr>
                                            <td class="text-muted">Last Calculated:</td>
                                            <td class="small text-muted">
                                                ${hoursData.calculation_timestamp ? 
                                                    new Date(hoursData.calculation_timestamp).toLocaleString() : 
                                                    'Never'
                                                }
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hours Breakdown -->
                    ${hoursData.breakdown && hoursData.breakdown.length > 0 ? `
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">📊 Hours Breakdown</h6>
                        </div>
                        <div class="card-body">
                            ${hoursData.breakdown.map(item => `
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>${item.description || item.type}:</span>
                                    <span class="badge bg-info">${(item.hours || 0).toFixed(1)}h</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    ` : ''}
                    
                    <!-- Recent Shifts -->
                    ${shifts.length > 0 ? `
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">📅 Recent Shifts</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Shift</th>
                                            <th>Type</th>
                                            <th>Date/Time</th>
                                            <th>Duration</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${shifts.map(shift => `
                                            <tr>
                                                <td>${shift.shift_title}</td>
                                                <td><span class="badge bg-light text-dark">${shift.angel_type}</span></td>
                                                <td class="small">
                                                    ${shift.start}<br>
                                                    <span class="text-muted">to ${shift.end}</span>
                                                </td>
                                                <td>${(shift.duration || 0).toFixed(1)}h</td>
                                                <td>
                                                    ${shift.is_completed ? 
                                                        '<span class="badge bg-success">Completed</span>' : 
                                                        '<span class="badge bg-warning">Upcoming</span>'
                                                    }
                                                    ${shift.is_night_shift ? 
                                                        '<br><small class="badge bg-dark">Night</small>' : ''
                                                    }
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    ` : `
                    <div class="card">
                        <div class="card-body text-center">
                            <p class="text-muted mb-0">No recent shifts found</p>
                        </div>
                    </div>
                    `}
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="row mt-3">
                <div class="col-12">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" class="btn btn-info btn-sm" onclick="loadGoodiesEligibility(${userData.id})">
                            🎁 Check Goodies Eligibility
                        </button>
                        <a href="/admin/user/${userData.id}/worklog" class="btn btn-secondary btn-sm" target="_blank">
                            📊 View Full Worklog
                        </a>
                    </div>
                </div>
            </div>
        `;
    }

    // Load goodies eligibility for a user
    window.loadGoodiesEligibility = function(userId) {
        const modalContent = document.getElementById('userDetailsContent');
        
        if (!modalContent) return;
        
        // Show loading state
        modalContent.innerHTML += `
            <div id="goodies-section" class="mt-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">🎁 Goodies Eligibility</h6>
                    </div>
                    <div class="card-body text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading eligibility...</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Get base URL
        const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
        
        // Fetch eligibility data
        fetch(`${baseUrl}/api/v1/backstage/users/${userId}/eligibility`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            const goodiesSection = document.getElementById('goodies-section');
            if (goodiesSection && data.success && data.data) {
                goodiesSection.innerHTML = renderGoodiesEligibility(data.data);
            }
        })
        .catch(error => {
            console.error('Error fetching eligibility:', error);
            const goodiesSection = document.getElementById('goodies-section');
            if (goodiesSection) {
                goodiesSection.innerHTML = `
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">🎁 Goodies Eligibility</h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <p class="mb-0">Unable to load goodies eligibility at this time.</p>
                            </div>
                        </div>
                    </div>
                `;
            }
        });
    };

    function renderGoodiesEligibility(eligibilityData) {
        const eligibleItems = eligibilityData.available_items.filter(item => item.is_eligible);
        const ineligibleItems = eligibilityData.available_items.filter(item => !item.is_eligible);
        
        return `
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">🎁 Goodies Eligibility</h6>
                    <div>
                        ${eligibilityData.is_eligible ? 
                            '<span class="badge bg-success">Eligible</span>' : 
                            '<span class="badge bg-warning">Not Eligible</span>'
                        }
                    </div>
                </div>
                <div class="card-body">
                    <!-- Overall Status -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="text-center p-3 border rounded">
                                <h4 class="text-success mb-1">${eligibilityData.total_hours.toFixed(1)}h</h4>
                                <small class="text-muted">Total Hours</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-center p-3 border rounded">
                                <h4 class="mb-1">${eligibilityData.minimum_hours_required}h</h4>
                                <small class="text-muted">Minimum Required</small>
                            </div>
                        </div>
                    </div>
                    
                    ${!eligibilityData.is_eligible ? `
                    <div class="alert alert-warning">
                        <strong>Not Eligible:</strong> ${eligibilityData.reason}
                        ${eligibilityData.hours_deficit > 0 ? 
                            ` Need ${eligibilityData.hours_deficit.toFixed(1)} more hours.` : ''
                        }
                    </div>
                    ` : ''}
                    
                    <!-- Eligible Items -->
                    ${eligibleItems.length > 0 ? `
                    <h6 class="text-success mb-3">✅ Available Goodies</h6>
                    <div class="row">
                        ${eligibleItems.map(item => `
                            <div class="col-md-6 mb-2">
                                <div class="card border-success">
                                    <div class="card-body py-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>${item.name}</strong>
                                                <br><small class="text-muted">${item.category} • ${item.hours_required}h required</small>
                                            </div>
                                            ${eligibilityData.can_distribute ? 
                                                `<button class="btn btn-success btn-sm" onclick="distributeGoodie(${eligibilityData.user_id}, ${item.id})">
                                                    Distribute
                                                </button>` : ''
                                            }
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                    ` : ''}
                    
                    <!-- Ineligible Items (if any) -->
                    ${ineligibleItems.length > 0 ? `
                    <h6 class="text-muted mb-3 mt-4">❌ Not Yet Available</h6>
                    <div class="row">
                        ${ineligibleItems.slice(0, 4).map(item => `
                            <div class="col-md-6 mb-2">
                                <div class="card border-secondary">
                                    <div class="card-body py-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="text-muted">${item.name}</span>
                                                <br><small class="text-muted">${item.category} • ${item.hours_required}h required</small>
                                            </div>
                                            <small class="text-danger">
                                                -${item.hours_deficit.toFixed(1)}h
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                    ` : ''}
                    
                    <!-- Past Distributions -->
                    ${eligibilityData.past_distributions.length > 0 ? `
                    <h6 class="text-info mb-3 mt-4">📦 Past Distributions</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Date</th>
                                    <th>Distributed By</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${eligibilityData.past_distributions.slice(0, 5).map(dist => `
                                    <tr>
                                        <td>${dist.item_name}</td>
                                        <td class="small">${dist.distributed_at}</td>
                                        <td class="small">${dist.distributed_by}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    // Distribute goodie to user (placeholder for now)
    window.distributeGoodie = function(userId, itemId) {
        alert(`Distribution functionality for user ${userId}, item ${itemId} will be implemented in a future update. For now, use the full qualification interface.`);
    };

    // Auto-submit search form on Enter
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('user-search-form').submit();
            }
        });
    }

    // Focus search input on page load
    if (searchInput) {
        searchInput.focus();
    }

    // Clear search form button
    const clearButton = document.getElementById('clear-search-btn');
    if (clearButton) {
        clearButton.addEventListener('click', function(e) {
            e.preventDefault();
            const form = document.getElementById('user-search-form');
            if (form) {
                form.reset();
                if (searchInput) {
                    searchInput.focus();
                }
            }
        });
    }

    // Also handle clear link in navigation
    const clearLink = document.querySelector('a[href*="/search"]:not([href*="/search?"])');
    if (clearLink) {
        clearLink.addEventListener('click', function(e) {
            if (searchInput) {
                searchInput.value = '';
            }
        });
    }
})();
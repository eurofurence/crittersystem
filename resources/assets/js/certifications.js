/**
 * Certification management JavaScript
 * Handles confirmation dialogs and form interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    // Handle confirmation dialogs for dangerous actions
    initConfirmationHandlers();
    
    // Handle certification form interactions
    initCertificationForm();

    // Handle self-confirmation functionality
    initSelfConfirmationHandlers();

    // Handle application withdrawal functionality  
    initWithdrawalHandlers();
    
    // Handle certification filtering functionality
    initCertificationFiltering();
    
    // Handle certification item interactions (self-confirmation)
    initCertificationItemHandlers();
    
    // Handle dynamic certification card interactions
    initCertificationCardInteractions();
    
    // Handle quick filter buttons with event delegation
    initQuickFilterEventDelegation();
    
    // Initialize integrated modal handling for all certification modals
    initIntegratedModalHandling();
    
    // Initialize comprehensive event validation and error handling
    initEventValidationAndErrorHandling();
    
    // Handle print certification functionality
    initPrintHandler();
    
    // Initialize enhanced action feedback for admin inline actions
    initEnhancedActionFeedback();
    
    // Initialize bulk operations functionality
    initBulkOperations();
    
    // Initialize enhanced user table sorting and filtering
    initEnhancedUserTableFeatures();
});

/**
 * Initialize self-confirmation handlers for AJAX submissions
 */
function initSelfConfirmationHandlers() {
    // Handle self-confirmation form submissions (on the self-confirm page)
    const selfConfirmForm = document.querySelector('form[action*="/self-confirm"]');
    if (selfConfirmForm) {
        selfConfirmForm.addEventListener('submit', handleSelfConfirmFormSubmission);
    }

    // Handle quick self-confirmation buttons (from certification cards)
    initQuickSelfConfirmation();
}

/**
 * Handle self-confirmation form submission with AJAX
 */
function handleSelfConfirmFormSubmission(e) {
    e.preventDefault();
    
    const form = e.target;
    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonText = submitButton.innerHTML;
    
    // Check if checkbox is confirmed
    const confirmCheckbox = form.querySelector('input[name="confirmed"]');
    if (!confirmCheckbox || !confirmCheckbox.checked) {
        showAlert('error', 'Please confirm that you understand the responsibility of self-certification.');
        return;
    }

    // Show loading state
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Confirming...';
    
    // Prepare form data
    const formData = new FormData(form);
    
    // Send AJAX request
    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (response.redirected) {
            // Handle redirect by following it
            window.location.href = response.url;
            return;
        }
        return response.json();
    })
    .then(data => {
        if (data && data.success) {
            showAlert('success', data.message || 'Self-confirmation successful!');
            // Redirect after a short delay to show success message
            setTimeout(() => {
                window.location.href = '/user/certifications';
            }, 1500);
        } else if (data && data.error) {
            showAlert('error', data.error);
            // Reset button
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        }
    })
    .catch(error => {
        console.error('Self-confirmation error:', error);
        showAlert('error', 'An unexpected error occurred. Please try again.');
        // Reset button
        submitButton.disabled = false;
        submitButton.innerHTML = originalButtonText;
    });
}

/**
 * Initialize quick self-confirmation from certification cards
 */
function initQuickSelfConfirmation() {
    // Add quick self-confirm buttons with data attributes for AJAX
    const selfConfirmLinks = document.querySelectorAll('a[href*="/self-confirm/"]');
    
    selfConfirmLinks.forEach(link => {
        // Add click handler for AJAX modal
        link.addEventListener('click', function(e) {
            // Check if Ctrl/Cmd is held (allow normal navigation)
            if (e.ctrlKey || e.metaKey) {
                return; // Allow normal link behavior
            }
            
            e.preventDefault();
            const certificationUuid = extractUuidFromUrl(this.href);
            const certificationTitle = this.closest('.certification-card')?.querySelector('.certification-title')?.textContent?.trim() || 'this certification';
            
            showQuickSelfConfirmModal(certificationUuid, certificationTitle);
        });
    });
}

/**
 * Show quick self-confirmation modal
 */
function showQuickSelfConfirmModal(certificationUuid, certificationTitle) {
    const modalId = 'quickSelfConfirmModal';
    
    // Remove existing modal if present
    const existingModal = document.getElementById(modalId);
    if (existingModal) {
        existingModal.remove();
    }
    
    // Create modal HTML with integrated data attributes
    const modalHtml = `
        <div class="modal fade" id="${modalId}" 
             data-certification-uuid="${certificationUuid}" 
             data-certification-title="${certificationTitle}"
             data-dynamic="true"
             tabindex="-1" aria-labelledby="${modalId}Label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="${modalId}Label">
                            <i class="fa fa-check-circle text-success me-2"></i>
                            Self-Confirm Certification
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle me-2"></i>
                            <strong>Quick Self-Confirmation</strong>
                        </div>
                        <p>You are about to self-confirm: <strong>${certificationTitle}</strong></p>
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle me-2"></i>
                            By self-confirming, you take full responsibility for your competence in this certification.
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="quickConfirmAcknowledgment" required>
                            <label class="form-check-label" for="quickConfirmAcknowledgment">
                                <strong>I understand and accept full responsibility for this self-certification</strong>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" 
                                data-certification-action="cancel" 
                                data-bs-dismiss="modal">Cancel</button>
                        <a href="/user/certifications/self-confirm/${certificationUuid}" 
                           class="btn btn-outline-primary me-2"
                           data-certification-action="view-full-form"
                           data-certification-uuid="${certificationUuid}">
                            <i class="fa fa-edit me-1"></i>View Full Form
                        </a>
                        <button type="button" class="btn btn-success" 
                                data-certification-action="confirm"
                                data-action="process-quick-self-confirm" 
                                data-certification-uuid="${certificationUuid}" 
                                data-certification-title="${certificationTitle}">
                            <i class="fa fa-check-circle me-1"></i>Confirm Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById(modalId));
    modal.show();
    
    // Clean up when modal is closed
    document.getElementById(modalId).addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

/**
 * Process quick self-confirmation
 */
function processQuickSelfConfirm(certificationUuid, certificationTitle) {
    const modal = document.getElementById('quickSelfConfirmModal');
    const acknowledgmentCheckbox = modal.querySelector('#quickConfirmAcknowledgment');
    const confirmButton = modal.querySelector('.btn-success');
    
    // Validate acknowledgment
    if (!acknowledgmentCheckbox.checked) {
        showAlert('error', 'Please acknowledge that you understand the responsibility of self-certification.');
        acknowledgmentCheckbox.focus();
        return;
    }
    
    // Show loading state
    const originalButtonText = confirmButton.innerHTML;
    confirmButton.disabled = true;
    confirmButton.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Confirming...';
    
    // Prepare form data
    const formData = new FormData();
    formData.append('certification_uuid', certificationUuid);
    formData.append('confirmed', '1');
    
    // Add CSRF token if available
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                     document.querySelector('input[name="_token"]')?.value;
    if (csrfToken) {
        formData.append('_token', csrfToken);
    }
    
    // Send AJAX request
    fetch('/user/certifications/self-confirm', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (response.redirected) {
            // Handle redirect - close modal and redirect
            bootstrap.Modal.getInstance(modal).hide();
            window.location.href = response.url;
            return;
        }
        return response.json().catch(() => {
            // If JSON parsing fails, assume redirect/success
            bootstrap.Modal.getInstance(modal).hide();
            window.location.reload();
        });
    })
    .then(data => {
        if (data && data.success) {
            // Close modal
            bootstrap.Modal.getInstance(modal).hide();
            
            // Show success message
            showAlert('success', data.message || `Successfully self-confirmed ${certificationTitle}!`);
            
            // Update the certification card status
            updateCertificationCardStatus(certificationUuid, 'self_confirmed');
            
            // Refresh page after short delay to ensure updates are visible
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else if (data && data.error) {
            showAlert('error', data.error);
            // Reset button
            confirmButton.disabled = false;
            confirmButton.innerHTML = originalButtonText;
        }
    })
    .catch(error => {
        console.error('Quick self-confirmation error:', error);
        showAlert('error', 'An unexpected error occurred. Please try again.');
        // Reset button
        confirmButton.disabled = false;
        confirmButton.innerHTML = originalButtonText;
    });
}

/**
 * Update certification card status after successful self-confirmation
 */
function updateCertificationCardStatus(certificationUuid, newStatus) {
    const certificationCard = document.querySelector(`[data-certification-uuid="${certificationUuid}"]`);
    if (certificationCard) {
        // Update status badge
        const statusBadge = certificationCard.querySelector('.status-badge');
        if (statusBadge) {
            statusBadge.className = 'badge bg-success-subtle text-success status-badge';
            statusBadge.innerHTML = '<i class="fa fa-check-circle me-1"></i>Self-Confirmed';
        }
        
        // Update action buttons area
        const actionButtons = certificationCard.querySelector('.ms-3');
        if (actionButtons) {
            actionButtons.innerHTML = `
                <span class="badge bg-success">
                    <i class="fa fa-check-circle me-1"></i>Confirmed
                </span>
            `;
        }
    }
}

/**
 * Extract UUID from URL
 */
function extractUuidFromUrl(url) {
    const matches = url.match(/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i);
    return matches ? matches[1] : null;
}

/**
 * Show alert message
 */
function showAlert(type, message) {
    const alertContainer = document.querySelector('.container') || document.body;
    
    // Remove existing alerts
    const existingAlerts = alertContainer.querySelectorAll('.alert.alert-dismissible');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create alert
    const alertClass = type === 'error' ? 'alert-danger' : 'alert-success';
    const iconClass = type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle';
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        <i class="fa ${iconClass} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            const alert = bootstrap.Alert.getInstance(alertDiv);
            if (alert) {
                alert.close();
            } else {
                alertDiv.remove();
            }
        }
    }, 5000);
}

/**
 * Initialize application withdrawal handlers
 */
function initWithdrawalHandlers() {
    // Handle withdrawal button clicks
    document.addEventListener('click', function(e) {
        const withdrawBtn = e.target.closest('.withdraw-application-btn');
        if (withdrawBtn) {
            e.preventDefault();
            handleApplicationWithdrawal(withdrawBtn);
        }
    });
}

/**
 * Handle application withdrawal with confirmation dialog
 */
function handleApplicationWithdrawal(button) {
    const certificationUuid = button.dataset.certificationUuid;
    const certificationTitle = button.dataset.certificationTitle || 'this certification';
    
    // Show confirmation dialog
    showWithdrawalConfirmDialog(certificationUuid, certificationTitle, button);
}

/**
 * Show withdrawal confirmation dialog
 */
function showWithdrawalConfirmDialog(certificationUuid, certificationTitle, originalButton) {
    const modalId = 'withdrawApplicationModal';
    
    // Remove existing modal if present
    const existingModal = document.getElementById(modalId);
    if (existingModal) {
        existingModal.remove();
    }
    
    // Create modal HTML
    const modalHtml = `
        <div class="modal fade" id="${modalId}" tabindex="-1" aria-labelledby="${modalId}Label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="${modalId}Label">
                            <i class="fa fa-exclamation-triangle text-warning me-2"></i>
                            Withdraw Application
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle me-2"></i>
                            <strong>Are you sure you want to withdraw this application?</strong>
                        </div>
                        <p>You are about to withdraw your application for: <strong>${certificationTitle}</strong></p>
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle me-2"></i>
                            <strong>What happens when you withdraw:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Your application will be removed from the review queue</li>
                                <li>You can reapply for this certification at any time</li>
                                <li>Any progress in the review process will be lost</li>
                            </ul>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirmWithdrawal" required>
                            <label class="form-check-label" for="confirmWithdrawal">
                                <strong>I understand and want to withdraw this application</strong>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-warning" onclick="processApplicationWithdrawal('${certificationUuid}', '${certificationTitle}')">
                            <i class="fa fa-trash me-1"></i>Withdraw Application
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById(modalId));
    modal.show();
    
    // Clean up when modal is closed
    document.getElementById(modalId).addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

/**
 * Process application withdrawal
 */
function processApplicationWithdrawal(certificationUuid, certificationTitle) {
    const modal = document.getElementById('withdrawApplicationModal');
    const confirmCheckbox = modal.querySelector('#confirmWithdrawal');
    const withdrawButton = modal.querySelector('.btn-warning');
    
    // Validate confirmation
    if (!confirmCheckbox.checked) {
        showAlert('error', 'Please confirm that you want to withdraw this application.');
        confirmCheckbox.focus();
        return;
    }
    
    // Show loading state
    const originalButtonText = withdrawButton.innerHTML;
    withdrawButton.disabled = true;
    withdrawButton.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Withdrawing...';
    
    // Prepare form data
    const formData = new FormData();
    formData.append('certification_uuid', certificationUuid);
    formData.append('confirmed', '1');
    
    // Add CSRF token if available
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                     document.querySelector('input[name="_token"]')?.value;
    if (csrfToken) {
        formData.append('_token', csrfToken);
    }
    
    // Send AJAX request
    fetch('/user/certifications/withdraw', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (response.redirected) {
            // Handle redirect - close modal and redirect
            bootstrap.Modal.getInstance(modal).hide();
            window.location.href = response.url;
            return;
        }
        return response.json().catch(() => {
            // If JSON parsing fails, assume redirect/success
            bootstrap.Modal.getInstance(modal).hide();
            window.location.reload();
        });
    })
    .then(data => {
        if (data && data.success) {
            // Close modal
            bootstrap.Modal.getInstance(modal).hide();
            
            // Show success message
            showAlert('success', data.message || `Successfully withdrew application for ${certificationTitle}!`);
            
            // Remove the application card from the UI
            removeApplicationFromUI(certificationUuid);
            
            // Refresh page after short delay to ensure updates are visible
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else if (data && data.error) {
            showAlert('error', data.error);
            // Reset button
            withdrawButton.disabled = false;
            withdrawButton.innerHTML = originalButtonText;
        }
    })
    .catch(error => {
        console.error('Application withdrawal error:', error);
        showAlert('error', 'An unexpected error occurred. Please try again.');
        // Reset button
        withdrawButton.disabled = false;
        withdrawButton.innerHTML = originalButtonText;
    });
}

/**
 * Remove application card from UI after successful withdrawal
 */
function removeApplicationFromUI(certificationUuid) {
    const applicationCards = document.querySelectorAll('[data-certification-uuid="' + certificationUuid + '"]');
    applicationCards.forEach(card => {
        const applicationCard = card.closest('.col-lg-6');
        if (applicationCard) {
            applicationCard.style.transition = 'opacity 0.3s ease';
            applicationCard.style.opacity = '0';
            setTimeout(() => {
                applicationCard.remove();
                
                // Check if "My Applications" section should be hidden
                const applicationsContainer = document.querySelector('.card-body .row.g-3');
                if (applicationsContainer && applicationsContainer.children.length === 0) {
                    const applicationsSection = document.querySelector('.card.mb-4');
                    if (applicationsSection) {
                        applicationsSection.style.transition = 'opacity 0.3s ease';
                        applicationsSection.style.opacity = '0';
                        setTimeout(() => applicationsSection.remove(), 300);
                    }
                }
            }, 300);
        }
    });
}

/**
 * Initialize confirmation handlers for buttons with confirmation attributes
 */
function initConfirmationHandlers() {
    // Find all buttons/forms that need confirmation
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    
    confirmButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            const confirmMessage = this.getAttribute('data-confirm');
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
        });
    });
}

/**
 * Initialize certification form behavior (for edit page)
 */
function initCertificationForm() {
    const isPerpetualCheckbox = document.querySelector('input[name="is_perpetual"]');
    const validityPeriodContainer = document.getElementById('validity-period-container');
    const validityPeriodSelect = document.querySelector('select[name="validity_period_days"]');
    const customValidityContainer = document.getElementById('custom-validity-container');

    // Toggle validity period field based on perpetual checkbox
    if (isPerpetualCheckbox && validityPeriodContainer) {
        isPerpetualCheckbox.addEventListener('change', function() {
            if (this.checked) {
                validityPeriodContainer.style.display = 'none';
                customValidityContainer.style.display = 'none';
            } else {
                validityPeriodContainer.style.display = 'block';
            }
        });
    }

    // Toggle custom validity field based on select value
    if (validityPeriodSelect && customValidityContainer) {
        validityPeriodSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                customValidityContainer.style.display = 'block';
            } else {
                customValidityContainer.style.display = 'none';
            }
        });
    }

    // Form validation
    const form = document.querySelector('form[data-validate="certification"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateCertificationForm()) {
                e.preventDefault();
                showValidationError();
            }
        });
    }
}

/**
 * Validate certification form
 */
function validateCertificationForm() {
    let isValid = true;
    const titleField = document.querySelector('input[name="title"]');
    
    // Title validation
    if (titleField && titleField.value.trim() === '') {
        isValid = false;
        titleField.classList.add('is-invalid');
    } else if (titleField) {
        titleField.classList.remove('is-invalid');
    }

    // Custom validity validation (if applicable)
    const isPerpetualCheckbox = document.querySelector('input[name="is_perpetual"]');
    const isPerpetual = isPerpetualCheckbox && isPerpetualCheckbox.checked;
    const validityPeriodSelect = document.querySelector('select[name="validity_period_days"]');
    const validityPeriod = validityPeriodSelect ? validityPeriodSelect.value : '';
    const customValidityField = document.querySelector('input[name="custom_validity_days"]');

    if (!isPerpetual && validityPeriod === 'custom' && customValidityField) {
        const customValue = parseInt(customValidityField.value);
        if (isNaN(customValue) || customValue < 1 || customValue > 3650) {
            isValid = false;
            customValidityField.classList.add('is-invalid');
        } else {
            customValidityField.classList.remove('is-invalid');
        }
    }

    return isValid;
}

/**
 * Show validation error message
 */
function showValidationError() {
    const alertContainer = document.querySelector('.container');
    if (alertContainer) {
        const existingAlert = alertContainer.querySelector('.alert-danger');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger alert-dismissible fade show';
        alertDiv.innerHTML = `
            <strong>Validation Error:</strong> Please correct the errors below.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        alertContainer.insertBefore(alertDiv, alertContainer.firstChild.nextSibling);
    }
}

// ============================================================================
// CERTIFICATION FILTERING SYSTEM (Migrated from inline JavaScript)
// ============================================================================

/**
 * Initialize certification filtering system
 * Migrated from index.twig inline JavaScript for better maintainability and CSP compliance
 */
function initCertificationFiltering() {
    // Only initialize if we're on a page with certification filtering elements
    const searchInput = document.getElementById('search');
    const certificationCatalog = document.getElementById('certification-catalog');
    
    if (!searchInput || !certificationCatalog) {
        return; // Not on certification listing page
    }
    
    try {
        // Cache DOM elements for performance
        const filterElements = cacheFilterElements();
        
        if (!filterElements) {
            console.warn('Certification filtering: Required DOM elements not found');
            return;
        }
        
        // Setup event listeners for all filter controls
        setupFilterEventListeners(filterElements);
        
        // Setup keyboard shortcuts
        setupFilterKeyboardShortcuts(filterElements);
        
        // Apply initial filtering on page load
        filterCertifications();
        
        // Auto-focus search input if there are URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('search') && searchInput) {
            searchInput.focus();
            searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
        }
        
        console.log('Certification filtering initialized successfully');
        
    } catch (error) {
        console.error('Failed to initialize certification filtering:', error);
        // Graceful degradation - filtering still works via form submission
    }
}

/**
 * Cache frequently accessed DOM elements for performance optimization
 * @returns {Object|null} Object containing cached elements or null if required elements missing
 */
function cacheFilterElements() {
    const elements = {
        searchInput: document.getElementById('search'),
        statusSelect: document.getElementById('status'),
        typeSelect: document.getElementById('type-filter'),
        availabilitySelect: document.getElementById('availability-filter'),
        sortSelect: document.getElementById('sort-filter'),
        clearFiltersBtn: document.getElementById('clear-filters'),
        activeFiltersDiv: document.getElementById('active-filters'),
        filterBadgesDiv: document.getElementById('filter-badges'),
        certificationItems: document.querySelectorAll('.certification-item'),
        quickFilterBtns: document.querySelectorAll('.quick-filter'),
        catalogContainer: document.getElementById('certification-catalog')
    };
    
    // Validate required elements exist
    if (!elements.searchInput || !elements.catalogContainer || !elements.certificationItems.length) {
        return null;
    }
    
    return elements;
}

/**
 * Setup event listeners for all filter controls
 * @param {Object} elements - Cached DOM elements
 */
function setupFilterEventListeners(elements) {
    // Search input with debouncing for performance
    if (elements.searchInput) {
        let searchTimeout;
        elements.searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                filterCertifications();
            }, 300); // 300ms debounce
        });
        
        // Escape key handling for search input
        elements.searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                filterCertifications();
            }
        });
    }
    
    // Filter select dropdowns
    [elements.statusSelect, elements.typeSelect, elements.availabilitySelect, elements.sortSelect].forEach(select => {
        if (select) {
            select.addEventListener('change', filterCertifications);
        }
    });
    
    // Clear filters button
    if (elements.clearFiltersBtn) {
        elements.clearFiltersBtn.addEventListener('click', clearAllFilters);
    }
    
    // Quick filter buttons
    elements.quickFilterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            handleQuickFilterClick(this);
        });
    });
}

/**
 * Setup keyboard shortcuts for filtering
 * @param {Object} elements - Cached DOM elements
 */
function setupFilterKeyboardShortcuts(elements) {
    document.addEventListener('keydown', function(e) {
        // Focus search on Ctrl+F (prevent browser search)
        if ((e.ctrlKey || e.metaKey) && e.key === 'f' && elements.searchInput) {
            e.preventDefault();
            elements.searchInput.focus();
        }
        
        // Clear filters on Escape (when not in search input)
        if (e.key === 'Escape' && document.activeElement !== elements.searchInput) {
            clearAllFilters();
        }
    });
}

/**
 * Handle quick filter button clicks with active state management
 * @param {HTMLElement} button - The clicked quick filter button
 */
function handleQuickFilterClick(button) {
    const quickFilterBtns = document.querySelectorAll('.quick-filter');
    const isActive = button.classList.contains('active');
    
    // Remove active state from all buttons
    quickFilterBtns.forEach(btn => btn.classList.remove('active'));
    
    if (!isActive) {
        // Activate clicked button and apply its filter
        button.classList.add('active');
        applyQuickFilter(button.dataset.filter);
    } else {
        // Deactivate and clear all filters
        clearAllFilters();
    }
}

/**
 * Update filter badges to show active filters
 * Migrated from index.twig inline JavaScript
 * @param {Object} elements - Cached DOM elements (optional, will query if not provided)
 */
function updateFilterBadges(elements) {
    try {
        // Get elements if not provided
        if (!elements) {
            elements = {
                searchInput: document.getElementById('search'),
                statusSelect: document.getElementById('status'),
                typeSelect: document.getElementById('type-filter'),
                availabilitySelect: document.getElementById('availability-filter'),
                sortSelect: document.getElementById('sort-filter'),
                filterBadgesDiv: document.getElementById('filter-badges'),
                activeFiltersDiv: document.getElementById('active-filters'),
                clearFiltersBtn: document.getElementById('clear-filters')
            };
        }
        
        // Check if required elements exist
        if (!elements.filterBadgesDiv || !elements.activeFiltersDiv) {
            console.warn('updateFilterBadges: Required badge container elements not found');
            return;
        }
        
        const badges = [];
        const searchTerm = elements.searchInput?.value.trim() || '';
        const statusFilter = elements.statusSelect?.value || '';
        const typeFilter = elements.typeSelect?.value || '';
        const availabilityFilter = elements.availabilitySelect?.value || '';
        const sortFilter = elements.sortSelect?.value || '';

        // Build badge array with user-friendly labels
        if (searchTerm) {
            badges.push(`<span class="badge bg-info">Search: ${escapeHtml(searchTerm)}</span>`);
        }
        if (statusFilter) {
            badges.push(`<span class="badge bg-primary">Status: ${formatFilterValue(statusFilter)}</span>`);
        }
        if (typeFilter) {
            badges.push(`<span class="badge bg-success">Type: ${formatFilterValue(typeFilter)}</span>`);
        }
        if (availabilityFilter) {
            badges.push(`<span class="badge bg-warning text-dark">Availability: ${formatFilterValue(availabilityFilter)}</span>`);
        }
        if (sortFilter && sortFilter !== 'title') {
            badges.push(`<span class="badge bg-secondary">Sort: ${formatFilterValue(sortFilter)}</span>`);
        }

        // Update UI based on active filters
        if (badges.length > 0) {
            elements.filterBadgesDiv.innerHTML = badges.join(' ');
            elements.activeFiltersDiv.style.display = 'block';
            if (elements.clearFiltersBtn) {
                elements.clearFiltersBtn.style.display = 'inline-block';
            }
        } else {
            elements.activeFiltersDiv.style.display = 'none';
            if (elements.clearFiltersBtn) {
                elements.clearFiltersBtn.style.display = 'none';
            }
        }
        
    } catch (error) {
        console.error('Error updating filter badges:', error);
        // Graceful degradation - continue without badges
    }
}

/**
 * Format filter values for display in badges
 * @param {string} value - Raw filter value
 * @returns {string} Formatted display value
 */
function formatFilterValue(value) {
    const formatMap = {
        'not_applied': 'Not Applied',
        'pending': 'Pending',
        'approved': 'Approved',
        'self_confirmed': 'Self-Confirmed',
        'expired': 'Expired',
        'revoked': 'Revoked',
        'self_confirmable': 'Self-Confirmable',
        'perpetual': 'Perpetual',
        'time_limited': 'Time Limited',
        'available': 'Available',
        'have': 'Have',
        'status': 'Status',
        'type': 'Type',
        'title': 'Title'
    };
    
    return formatMap[value] || value.charAt(0).toUpperCase() + value.slice(1).replace(/_/g, ' ');
}

/**
 * Escape HTML characters to prevent XSS
 * @param {string} text - Text to escape
 * @returns {string} Escaped text
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Sort certification items by specified criteria
 * Migrated from index.twig inline JavaScript
 * @param {NodeList|Array} items - Collection of certification item elements
 * @param {string} sortBy - Sort criteria: 'status', 'type', 'title'
 */
function sortCertifications(items, sortBy) {
    try {
        if (!items || items.length === 0) {
            console.warn('sortCertifications: No items provided for sorting');
            return;
        }
        
        const itemsArray = Array.from(items);
        
        // Validate that all items have a common parent
        const parent = itemsArray[0].parentNode;
        if (!parent) {
            console.warn('sortCertifications: Items have no parent container');
            return;
        }
        
        // Verify all items belong to the same parent
        const invalidItems = itemsArray.filter(item => item.parentNode !== parent);
        if (invalidItems.length > 0) {
            console.warn('sortCertifications: Not all items belong to the same parent container');
            return;
        }

        // Sort the items array
        itemsArray.sort((a, b) => {
            try {
                return compareItems(a, b, sortBy);
            } catch (error) {
                console.error('sortCertifications: Error comparing items:', error);
                // Fallback to title comparison
                return compareByTitle(a, b);
            }
        });

        // Re-append items in sorted order with minimal DOM manipulation
        const fragment = document.createDocumentFragment();
        itemsArray.forEach(item => fragment.appendChild(item));
        parent.appendChild(fragment);
        
    } catch (error) {
        console.error('Error sorting certifications:', error);
        // Graceful degradation - leave items in current order
    }
}

/**
 * Compare two certification items based on sort criteria
 * @param {HTMLElement} a - First item to compare
 * @param {HTMLElement} b - Second item to compare  
 * @param {string} sortBy - Sort criteria
 * @returns {number} Comparison result (-1, 0, 1)
 */
function compareItems(a, b, sortBy) {
    switch(sortBy) {
        case 'status':
            return compareByStatus(a, b);
        case 'type':
            return compareByType(a, b);
        case 'title':
        default:
            return compareByTitle(a, b);
    }
}

/**
 * Compare items by status with defined priority order
 * @param {HTMLElement} a - First item
 * @param {HTMLElement} b - Second item
 * @returns {number} Comparison result
 */
function compareByStatus(a, b) {
    const statusOrder = {
        'not_applied': 0,
        'available': 1, 
        'pending': 2,
        'approved': 3,
        'self_confirmed': 4,
        'expired': 5,
        'revoked': 6
    };
    
    const statusA = a.dataset.status || '';
    const statusB = b.dataset.status || '';
    
    const orderA = statusOrder[statusA] !== undefined ? statusOrder[statusA] : 999;
    const orderB = statusOrder[statusB] !== undefined ? statusOrder[statusB] : 999;
    
    return orderA - orderB;
}

/**
 * Compare items by type (self-confirmable first, then perpetual, then time-limited)
 * @param {HTMLElement} a - First item
 * @param {HTMLElement} b - Second item
 * @returns {number} Comparison result
 */
function compareByType(a, b) {
    const selfConfirmableA = a.dataset.selfConfirmable === 'yes';
    const selfConfirmableB = b.dataset.selfConfirmable === 'yes';
    const typeA = a.dataset.type || '';
    const typeB = b.dataset.type || '';
    
    // Self-confirmable items first, then sort by type
    const keyA = (selfConfirmableA ? 'a_' : 'z_') + typeA;
    const keyB = (selfConfirmableB ? 'a_' : 'z_') + typeB;
    
    return keyA.localeCompare(keyB);
}

/**
 * Compare items by title (alphabetical)
 * @param {HTMLElement} a - First item
 * @param {HTMLElement} b - Second item
 * @returns {number} Comparison result
 */
function compareByTitle(a, b) {
    const titleA = (a.dataset.title || '').toLowerCase();
    const titleB = (b.dataset.title || '').toLowerCase();
    
    return titleA.localeCompare(titleB);
}

/**
 * Filter and display certification items based on current filter criteria
 * Migrated from index.twig inline JavaScript
 * @param {Object} elements - Cached DOM elements (optional, will query if not provided)
 */
function filterCertifications(elements) {
    try {
        // Get elements if not provided (for standalone calls)
        if (!elements) {
            elements = cacheFilterElements();
            if (!elements) {
                console.warn('filterCertifications: Required DOM elements not found');
                return;
            }
        }

        // Get current filter values
        const searchTerm = (elements.searchInput?.value || '').toLowerCase().trim();
        const statusFilter = elements.statusSelect?.value || '';
        const typeFilter = elements.typeSelect?.value || '';
        const availabilityFilter = elements.availabilitySelect?.value || '';
        const sortFilter = elements.sortSelect?.value || 'title';

        let visibleCount = 0;
        const visibleItems = [];

        // Filter each certification item
        elements.certificationItems.forEach(item => {
            try {
                const visible = isItemVisible(item, {
                    searchTerm,
                    statusFilter,
                    typeFilter,
                    availabilityFilter
                });

                // Update item visibility
                item.style.display = visible ? 'block' : 'none';
                
                if (visible) {
                    visibleCount++;
                    visibleItems.push(item);
                }
            } catch (error) {
                console.error('Error filtering item:', error);
                // Default to showing the item if filtering fails
                item.style.display = 'block';
                visibleCount++;
                visibleItems.push(item);
            }
        });

        // Sort visible items if any are found
        if (visibleItems.length > 0) {
            sortCertifications(visibleItems, sortFilter);
        }

        // Update result counter
        updateResultCounter(visibleCount);

        // Update filter badges
        updateFilterBadges(elements);

        // Show/hide no results message
        updateNoResultsMessage(elements.catalogContainer, visibleCount);

    } catch (error) {
        console.error('Error in filterCertifications:', error);
        // Graceful degradation - show error message to user
        showAlert('error', 'Filtering temporarily unavailable. Please try refreshing the page.');
    }
}

/**
 * Determine if a certification item should be visible based on filter criteria
 * @param {HTMLElement} item - Certification item element
 * @param {Object} filters - Filter criteria object
 * @returns {boolean} True if item should be visible
 */
function isItemVisible(item, filters) {
    const {searchTerm, statusFilter, typeFilter, availabilityFilter} = filters;
    
    // Get item data attributes (with fallbacks)
    const title = (item.dataset.title || '').toLowerCase();
    const description = (item.dataset.description || '').toLowerCase();
    const status = item.dataset.status || '';
    const type = item.dataset.type || '';
    const selfConfirmable = item.dataset.selfConfirmable || '';
    const availability = item.dataset.availability || '';

    // Apply search filter (title or description)
    if (searchTerm && !title.includes(searchTerm) && !description.includes(searchTerm)) {
        return false;
    }

    // Apply status filter
    if (statusFilter && status !== statusFilter) {
        return false;
    }

    // Apply type filter with special handling for self_confirmable
    if (typeFilter) {
        if (typeFilter === 'self_confirmable' && selfConfirmable !== 'yes') {
            return false;
        } else if (typeFilter === 'perpetual' && type !== 'perpetual') {
            return false;
        } else if (typeFilter === 'time_limited' && type !== 'time_limited') {
            return false;
        }
    }

    // Apply availability filter
    if (availabilityFilter && availability !== availabilityFilter) {
        return false;
    }

    return true; // Item passes all filters
}

/**
 * Update the result counter display
 * @param {number} visibleCount - Number of visible items
 */
function updateResultCounter(visibleCount) {
    try {
        const counter = document.querySelector('.card-header small');
        if (counter) {
            const countText = visibleCount === 1 ? 'certification' : 'certifications';
            counter.textContent = `${visibleCount} ${countText} showing`;
        }
    } catch (error) {
        console.error('Error updating result counter:', error);
        // Non-critical error, continue without counter update
    }
}

/**
 * Show or hide the "no results" message
 * @param {HTMLElement} catalogContainer - Main catalog container
 * @param {number} visibleCount - Number of visible items
 */
function updateNoResultsMessage(catalogContainer, visibleCount) {
    try {
        if (!catalogContainer) {
            return;
        }

        let noResultsMsg = document.getElementById('no-results-message');

        if (visibleCount === 0) {
            // Create no results message if it doesn't exist
            if (!noResultsMsg) {
                noResultsMsg = document.createElement('div');
                noResultsMsg.id = 'no-results-message';
                noResultsMsg.className = 'list-group-item text-center py-5';
                noResultsMsg.innerHTML = `
                    <div class="text-muted">
                        <i class="fa fa-search display-6 mb-3"></i>
                        <h5>No Results Found</h5>
                        <p>Try adjusting your search criteria or clearing the filters.</p>
                        <button class="btn btn-outline-primary btn-sm mt-2" onclick="clearAllFilters()">
                            <i class="fa fa-refresh me-1"></i>Clear All Filters
                        </button>
                    </div>
                `;
                catalogContainer.appendChild(noResultsMsg);
            }
            noResultsMsg.style.display = 'block';
        } else if (noResultsMsg) {
            // Hide existing no results message
            noResultsMsg.style.display = 'none';
        }
        
    } catch (error) {
        console.error('Error updating no results message:', error);
        // Non-critical error, continue without message update
    }
}

/**
 * Clear all active filters and reset to default state
 * Migrated from index.twig inline JavaScript
 * @param {Object} elements - Cached DOM elements (optional, will query if not provided)
 */
function clearAllFilters(elements) {
    try {
        // Get elements if not provided (for standalone calls or onclick handlers)
        if (!elements) {
            elements = {
                searchInput: document.getElementById('search'),
                statusSelect: document.getElementById('status'),
                typeSelect: document.getElementById('type-filter'),
                availabilitySelect: document.getElementById('availability-filter'),
                sortSelect: document.getElementById('sort-filter'),
                quickFilterBtns: document.querySelectorAll('.quick-filter')
            };
        }

        // Reset all filter form controls to default values
        if (elements.searchInput) {
            elements.searchInput.value = '';
        }
        
        if (elements.statusSelect) {
            elements.statusSelect.value = '';
        }
        
        if (elements.typeSelect) {
            elements.typeSelect.value = '';
        }
        
        if (elements.availabilitySelect) {
            elements.availabilitySelect.value = '';
        }
        
        if (elements.sortSelect) {
            elements.sortSelect.value = 'title';
        }

        // Remove active states from quick filter buttons
        const quickFilterBtns = elements.quickFilterBtns || document.querySelectorAll('.quick-filter');
        quickFilterBtns.forEach(btn => {
            try {
                btn.classList.remove('active');
            } catch (error) {
                console.error('Error removing active class from quick filter button:', error);
                // Continue with other buttons
            }
        });

        // Reapply filtering to show all items
        filterCertifications(elements);
        
        // Focus search input for better UX
        if (elements.searchInput) {
            elements.searchInput.focus();
        }
        
        console.log('All filters cleared successfully');

    } catch (error) {
        console.error('Error clearing filters:', error);
        // Graceful degradation - try to reload page as fallback
        try {
            showAlert('warning', 'Filter reset failed. Please refresh the page to clear filters.');
        } catch (alertError) {
            console.error('Unable to show alert:', alertError);
            // Last resort - inform user via console
            console.warn('Filter reset failed. Please refresh the page manually.');
        }
    }
}

/**
 * Apply a predefined quick filter configuration
 * Migrated from index.twig inline JavaScript (with bug fix)
 * @param {string} filterType - Type of quick filter to apply
 * @param {Object} elements - Cached DOM elements (optional, will query if not provided)
 */
function applyQuickFilter(filterType, elements) {
    try {
        // Clear all existing filters first
        clearAllFilters(elements);

        // Get elements if not provided 
        if (!elements) {
            elements = {
                statusSelect: document.getElementById('status'),
                typeSelect: document.getElementById('type-filter'),
                availabilitySelect: document.getElementById('availability-filter')
            };
        }

        // Apply specific filter based on type
        switch(filterType) {
            case 'self_confirmable':
                // Show only self-confirmable and available certifications
                if (elements.typeSelect) {
                    elements.typeSelect.value = 'self_confirmable';
                }
                if (elements.availabilitySelect) {
                    elements.availabilitySelect.value = 'available';
                }
                console.log('Applied self-confirmable quick filter');
                break;
                
            case 'available':
                // Show only available certifications (BUG FIX: was availabilityFilter.value)
                if (elements.availabilitySelect) {
                    elements.availabilitySelect.value = 'available';
                }
                console.log('Applied available quick filter');
                break;
                
            case 'expiring':
                // Show approved certifications (note: true expiring logic needs server-side support)
                if (elements.statusSelect) {
                    elements.statusSelect.value = 'approved';
                }
                console.log('Applied expiring quick filter (showing approved certifications)');
                console.warn('Full expiring logic requires server-side date calculation support');
                break;
                
            case 'expired':
                // Show only expired certifications
                if (elements.statusSelect) {
                    elements.statusSelect.value = 'expired';
                }
                console.log('Applied expired quick filter');
                break;
                
            default:
                console.warn(`Unknown quick filter type: ${filterType}`);
                // Don't apply any additional filters, just leave cleared
                return;
        }

        // Apply the new filter configuration
        filterCertifications(elements);

    } catch (error) {
        console.error('Error applying quick filter:', error);
        // Graceful degradation - inform user and suggest manual filtering
        try {
            showAlert('warning', `Quick filter "${filterType}" failed. Please use the manual filter controls above.`);
        } catch (alertError) {
            console.error('Unable to show quick filter error alert:', alertError);
        }
    }
}

/**
 * Initialize event delegation for dynamic certification card interactions
 * Handles all interactive elements within certification cards for CSP compliance and performance
 */
function initCertificationCardInteractions() {
    try {
        // Use single delegated event listener for all certification card interactions
        document.addEventListener('click', function(e) {
            
            // Handle certification card collapse buttons for history sections
            const collapseButton = e.target.closest('.certification-item [data-bs-toggle="collapse"]');
            if (collapseButton) {
                // Bootstrap handles this natively, but we can add analytics or custom behavior
                const targetId = collapseButton.dataset.bsTarget;
                const expanded = collapseButton.getAttribute('aria-expanded') === 'true';
                
                console.log(`History section ${expanded ? 'collapsed' : 'expanded'}:`, targetId);
                
                // Optional: Track user engagement with certification history
                // analyticsTrack('certification_history_toggle', { action: expanded ? 'collapse' : 'expand' });
            }
            
            // Handle any dynamic action buttons that might be added to cards
            const actionButton = e.target.closest('.certification-item [data-action]');
            if (actionButton && !actionButton.closest('[data-action="self-confirm"]') && !actionButton.closest('[data-action="process-quick-self-confirm"]')) {
                const action = actionButton.dataset.action;
                const certificationCard = actionButton.closest('.certification-item');
                
                // Get certification context from the card
                const certificationData = {
                    uuid: certificationCard?.dataset.certificationUuid,
                    title: certificationCard?.dataset.certificationTitle,
                    status: certificationCard?.dataset.certificationStatus
                };
                
                console.log('Dynamic certification action triggered:', action, certificationData);
                
                // Handle specific dynamic actions
                switch(action) {
                    case 'quick-renew':
                        handleQuickRenew(certificationData);
                        break;
                    case 'download-certificate':
                        handleCertificateDownload(certificationData);
                        break;
                    case 'share-certification':
                        handleCertificationShare(certificationData);
                        break;
                    default:
                        console.warn('Unknown certification action:', action);
                }
            }
            
        });
        
        // Handle keyboard interactions for accessibility
        document.addEventListener('keydown', function(e) {
            // Handle Enter/Space on focusable certification elements
            if ((e.key === 'Enter' || e.key === ' ') && e.target.closest('.certification-item')) {
                const focusedElement = e.target;
                
                // If focused element has role="button" or is a button-like element
                if (focusedElement.getAttribute('role') === 'button' || 
                    focusedElement.matches('.btn-link, .certification-action')) {
                    e.preventDefault();
                    focusedElement.click();
                }
            }
        });
        
        // Initialize any certification cards that need immediate setup
        setupCertificationCardEnhancements();
        
        console.log('Certification card interactions initialized successfully');
        
    } catch (error) {
        console.error('Failed to initialize certification card interactions:', error);
        // Graceful degradation - individual elements still work
    }
}

/**
 * Setup enhancements for certification cards (called on page load and after dynamic updates)
 */
function setupCertificationCardEnhancements() {
    try {
        // Add hover effects and accessibility improvements to certification cards
        const certificationCards = document.querySelectorAll('.certification-item');
        
        certificationCards.forEach(card => {
            // Skip if already enhanced
            if (card.dataset.enhanced === 'true') return;
            
            // Mark as enhanced
            card.dataset.enhanced = 'true';
            
            // Add keyboard navigation support
            const interactiveElements = card.querySelectorAll('button, a, [tabindex]');
            interactiveElements.forEach((element, index) => {
                // Ensure proper tab order within card
                if (!element.hasAttribute('tabindex') && element.tagName !== 'A') {
                    element.setAttribute('tabindex', '0');
                }
            });
            
            // Add focus management for collapse sections
            const collapseButtons = card.querySelectorAll('[data-bs-toggle="collapse"]');
            collapseButtons.forEach(button => {
                const targetId = button.dataset.bsTarget?.substring(1); // Remove #
                if (targetId) {
                    // When collapse is shown, focus management
                    document.addEventListener('shown.bs.collapse', function(event) {
                        if (event.target.id === targetId) {
                            // Optionally focus first interactive element in collapsed content
                            const firstFocusable = event.target.querySelector('button, a, input, select, textarea, [tabindex]');
                            // Don't auto-focus as it might be disruptive to screen readers
                        }
                    });
                }
            });
        });
        
        console.log(`Enhanced ${certificationCards.length} certification cards`);
        
    } catch (error) {
        console.error('Error setting up certification card enhancements:', error);
    }
}

/**
 * Placeholder handlers for future dynamic actions
 * These will be implemented as needed for specific features
 */

/**
 * Initialize event delegation for quick filter buttons using data attributes
 * Provides CSP-compliant filtering with support for dynamic buttons
 * Uses data-filter-type attributes for better semantic clarity
 */
function initQuickFilterEventDelegation() {
    try {
        // Use event delegation for all quick filter buttons
        document.addEventListener('click', function(e) {
            // Check for both data-filter-type (new format) and data-filter (legacy)
            const filterButton = e.target.closest('[data-filter-type], .quick-filter[data-filter]');
            
            if (filterButton) {
                e.preventDefault();
                
                // Get filter type from new or legacy attribute
                const filterType = filterButton.dataset.filterType || filterButton.dataset.filter;
                
                if (filterType) {
                    handleQuickFilterAction(filterButton, filterType);
                } else {
                    console.warn('Quick filter button missing data-filter-type or data-filter attribute');
                    showAlert('warning', 'Unable to apply filter. Button configuration error.');
                }
            }
        });
        
        // Handle keyboard activation for accessibility
        document.addEventListener('keydown', function(e) {
            if ((e.key === 'Enter' || e.key === ' ') && 
                e.target.matches('[data-filter-type], .quick-filter[data-filter]')) {
                e.preventDefault();
                e.target.click();
            }
        });
        
        // Initialize visual states for any existing quick filter buttons
        setupQuickFilterButtonStates();
        
        console.log('Quick filter event delegation initialized successfully');
        
    } catch (error) {
        console.error('Failed to initialize quick filter event delegation:', error);
        // Graceful degradation - existing button listeners still work
    }
}

/**
 * Handle quick filter button actions with enhanced state management
 * @param {HTMLElement} button - The clicked filter button
 * @param {string} filterType - The type of filter to apply
 */
function handleQuickFilterAction(button, filterType) {
    try {
        const allQuickFilterButtons = document.querySelectorAll('[data-filter-type], .quick-filter[data-filter]');
        const isCurrentlyActive = button.classList.contains('active');
        
        // Remove active state from all quick filter buttons
        allQuickFilterButtons.forEach(btn => {
            btn.classList.remove('active');
            btn.setAttribute('aria-pressed', 'false');
        });
        
        if (!isCurrentlyActive) {
            // Activate clicked button
            button.classList.add('active');
            button.setAttribute('aria-pressed', 'true');
            
            // Apply the filter
            applyQuickFilter(filterType);
            
            console.log('Quick filter applied:', filterType);
            
            // Optional: Track filter usage for analytics
            // analyticsTrack('quick_filter_used', { filter_type: filterType });
            
        } else {
            // Button was active, so clear all filters (deactivate)
            clearAllFilters();
            
            console.log('Quick filter cleared (button was active)');
        }
        
        // Update filter badges to reflect current state
        updateFilterBadges();
        
    } catch (error) {
        console.error('Error handling quick filter action:', error);
        showAlert('warning', `Unable to apply "${filterType}" filter. Please try using the manual filter controls.`);
    }
}

/**
 * Setup initial states and accessibility attributes for quick filter buttons
 */
function setupQuickFilterButtonStates() {
    try {
        const quickFilterButtons = document.querySelectorAll('[data-filter-type], .quick-filter[data-filter]');
        
        quickFilterButtons.forEach(button => {
            // Add ARIA attributes for accessibility
            if (!button.hasAttribute('aria-pressed')) {
                button.setAttribute('aria-pressed', 'false');
            }
            
            // Add role if not present
            if (!button.hasAttribute('role') && button.tagName !== 'BUTTON') {
                button.setAttribute('role', 'button');
            }
            
            // Add tabindex if not focusable
            if (!button.hasAttribute('tabindex') && button.tagName !== 'BUTTON') {
                button.setAttribute('tabindex', '0');
            }
            
            // Ensure proper cursor style
            button.style.cursor = 'pointer';
            
            // Add hover effect classes if needed
            if (!button.classList.contains('btn')) {
                button.classList.add('quick-filter-button');
            }
        });
        
        console.log(`Setup ${quickFilterButtons.length} quick filter button states`);
        
    } catch (error) {
        console.error('Error setting up quick filter button states:', error);
    }
}

/**
 * Initialize integrated modal event handling for all certification-related modals
 * Provides centralized management and consistent behavior across different modal types
 */
function initIntegratedModalHandling() {
    try {
        // Set up global modal event listeners for all certification modals
        document.addEventListener('show.bs.modal', function(e) {
            const modal = e.target;
            
            // Handle different types of certification modals
            if (modal.id === 'quickSelfConfirmModal') {
                handleSelfConfirmModalShow(modal);
            }
            // Future modal types can be added here
        });
        
        document.addEventListener('hide.bs.modal', function(e) {
            const modal = e.target;
            
            // Clean up any certification-specific modal state
            if (modal.id === 'quickSelfConfirmModal') {
                handleSelfConfirmModalHide(modal);
            }
        });
        
        document.addEventListener('hidden.bs.modal', function(e) {
            const modal = e.target;
            
            // Remove certification modals from DOM when hidden (cleanup)
            if (modal.id && modal.id.includes('Certification') || modal.id === 'quickSelfConfirmModal') {
                handleModalCleanup(modal);
            }
        });
        
        // Handle modal form submissions with enhanced integration
        document.addEventListener('submit', function(e) {
            const form = e.target;
            const modal = form.closest('.modal');
            
            if (modal && modal.id === 'quickSelfConfirmModal') {
                e.preventDefault();
                handleIntegratedSelfConfirmSubmission(form, modal);
            }
        });
        
        // Handle certification action buttons within modals
        document.addEventListener('click', function(e) {
            const button = e.target.closest('.modal [data-certification-action]');
            
            if (button) {
                e.preventDefault();
                handleCertificationModalAction(button);
            }
        });
        
        console.log('Integrated modal handling initialized successfully');
        
    } catch (error) {
        console.error('Failed to initialize integrated modal handling:', error);
        // Graceful degradation - individual modals still work
    }
}

/**
 * Handle self-confirmation modal show event
 * @param {HTMLElement} modal - The modal element
 */
function handleSelfConfirmModalShow(modal) {
    try {
        // Focus management
        const firstInput = modal.querySelector('input[type="checkbox"]');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 300);
        }
        
        // Initialize any dynamic content
        const certificationUuid = modal.dataset.certificationUuid;
        if (certificationUuid) {
            // Could load additional certification data here if needed
            console.log('Self-confirmation modal shown for UUID:', certificationUuid);
        }
        
        // Track modal opening for analytics
        // analyticsTrack('self_confirm_modal_opened', { certification_uuid: certificationUuid });
        
    } catch (error) {
        console.error('Error handling self-confirm modal show:', error);
    }
}

/**
 * Handle self-confirmation modal hide event
 * @param {HTMLElement} modal - The modal element
 */
function handleSelfConfirmModalHide(modal) {
    try {
        // Reset any form states
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
        }
        
        // Reset any loading states
        const buttons = modal.querySelectorAll('button[data-certification-action]');
        buttons.forEach(button => {
            button.disabled = false;
            button.classList.remove('loading');
        });
        
        console.log('Self-confirmation modal hidden, state reset');
        
    } catch (error) {
        console.error('Error handling self-confirm modal hide:', error);
    }
}

/**
 * Handle modal cleanup after hidden event
 * @param {HTMLElement} modal - The modal element
 */
function handleModalCleanup(modal) {
    try {
        // For dynamically created modals, remove from DOM
        if (modal.dataset.dynamic === 'true') {
            setTimeout(() => modal.remove(), 300);
        }
        
        // Clear any cached data
        delete modal.dataset.certificationUuid;
        delete modal.dataset.certificationTitle;
        
        console.log('Modal cleanup completed:', modal.id);
        
    } catch (error) {
        console.error('Error during modal cleanup:', error);
    }
}

/**
 * Handle integrated self-confirmation form submission
 * @param {HTMLFormElement} form - The form element
 * @param {HTMLElement} modal - The modal element
 */
function handleIntegratedSelfConfirmSubmission(form, modal) {
    try {
        const certificationUuid = form.dataset.certificationUuid || 
                                modal.querySelector('[data-certification-uuid]')?.dataset.certificationUuid;
        const certificationTitle = form.dataset.certificationTitle ||
                                 modal.querySelector('[data-certification-title]')?.dataset.certificationTitle;
        
        if (certificationUuid) {
            // Use existing processQuickSelfConfirm with enhanced integration
            processQuickSelfConfirm(certificationUuid, certificationTitle);
        } else {
            throw new Error('Missing certification UUID for form submission');
        }
        
    } catch (error) {
        console.error('Error handling integrated self-confirm submission:', error);
        showAlert('error', 'Unable to process self-confirmation. Please try again.');
    }
}

/**
 * Handle certification action buttons within modals
 * @param {HTMLElement} button - The action button
 */
function handleCertificationModalAction(button) {
    try {
        const action = button.dataset.certificationAction;
        const modal = button.closest('.modal');
        
        switch(action) {
            case 'confirm':
                handleModalConfirmAction(button, modal);
                break;
            case 'cancel':
                handleModalCancelAction(button, modal);
                break;
            case 'view-full-form':
                handleModalViewFullFormAction(button, modal);
                break;
            default:
                console.warn('Unknown certification modal action:', action);
        }
        
    } catch (error) {
        console.error('Error handling certification modal action:', error);
    }
}

/**
 * Handle modal confirm actions with integrated state management
 */
function handleModalConfirmAction(button, modal) {
    const form = modal.querySelector('form');
    if (form) {
        form.requestSubmit();
    } else {
        // Direct action handling
        const certificationUuid = button.dataset.certificationUuid;
        const certificationTitle = button.dataset.certificationTitle;
        
        if (certificationUuid) {
            processQuickSelfConfirm(certificationUuid, certificationTitle);
        }
    }
}

/**
 * Handle modal cancel actions
 */
function handleModalCancelAction(button, modal) {
    const modalInstance = bootstrap.Modal.getInstance(modal);
    if (modalInstance) {
        modalInstance.hide();
    }
}

/**
 * Handle view full form actions
 */
function handleModalViewFullFormAction(button, modal) {
    const certificationUuid = button.dataset.certificationUuid;
    if (certificationUuid) {
        window.location.href = `/user/certifications/self-confirm/${certificationUuid}`;
    }
}

/**
 * Initialize comprehensive event validation and error handling for all delegated events
 * Provides centralized validation, error handling, and graceful degradation
 */
function initEventValidationAndErrorHandling() {
    try {
        // Global error boundary for all certification-related events
        document.addEventListener('error', function(e) {
            // Handle JavaScript errors in event handlers
            if (e.target && e.target.closest('.certification-container, .certification-item')) {
                console.error('Certification event error:', e.error);
                handleCertificationEventError(e);
            }
        });
        
        // Comprehensive click event validation
        document.addEventListener('click', function(e) {
            try {
                validateAndHandleCertificationClick(e);
            } catch (error) {
                console.error('Click event validation error:', error);
                handleEventValidationError(error, 'click', e.target);
            }
        });
        
        // Form submission validation for certification forms
        document.addEventListener('submit', function(e) {
            try {
                validateAndHandleCertificationSubmit(e);
            } catch (error) {
                console.error('Submit event validation error:', error);
                handleEventValidationError(error, 'submit', e.target);
            }
        });
        
        // Input validation for certification-related inputs
        document.addEventListener('input', function(e) {
            try {
                validateAndHandleCertificationInput(e);
            } catch (error) {
                console.error('Input event validation error:', error);
                handleEventValidationError(error, 'input', e.target);
            }
        });
        
        // Set up CSP violation reporting for inline event handlers
        setupCSPViolationReporting();
        
        console.log('Event validation and error handling initialized successfully');
        
    } catch (error) {
        console.error('Failed to initialize event validation and error handling:', error);
    }
}

/**
 * Validate and handle certification-related click events
 * @param {Event} e - The click event
 */
function validateAndHandleCertificationClick(e) {
    const target = e.target;
    
    // Validate self-confirmation buttons
    if (target.closest('[data-action="self-confirm"]')) {
        validateSelfConfirmAction(target, e);
    }
    
    // Validate quick filter buttons
    if (target.closest('[data-filter-type], .quick-filter[data-filter]')) {
        validateQuickFilterAction(target, e);
    }
    
    // Validate print actions
    if (target.closest('[data-action="print-certifications"]')) {
        validatePrintAction(target, e);
    }
    
    // Validate modal actions
    if (target.closest('[data-certification-action]')) {
        validateModalAction(target, e);
    }
    
    // Validate dynamic certification actions
    if (target.closest('[data-action]') && target.closest('.certification-item')) {
        validateDynamicCertificationAction(target, e);
    }
}

/**
 * Validate self-confirmation actions
 * @param {Element} target - The clicked element
 * @param {Event} event - The click event
 */
function validateSelfConfirmAction(target, event) {
    const button = target.closest('[data-action="self-confirm"]');
    const certificationUuid = button?.dataset.certificationUuid;
    const certificationTitle = button?.dataset.certificationTitle;
    
    if (!certificationUuid) {
        throw new ValidationError('Self-confirmation button missing required data-certification-uuid attribute', {
            element: button,
            action: 'self-confirm'
        });
    }
    
    if (!certificationTitle || certificationTitle.trim() === '') {
        console.warn('Self-confirmation button missing certification title, using fallback');
    }
    
    // Check if button is disabled
    if (button.disabled || button.classList.contains('disabled')) {
        event.preventDefault();
        throw new ValidationError('Self-confirmation button is disabled', {
            element: button,
            action: 'self-confirm'
        });
    }
}

/**
 * Validate quick filter actions
 * @param {Element} target - The clicked element
 * @param {Event} event - The click event
 */
function validateQuickFilterAction(target, event) {
    const button = target.closest('[data-filter-type], .quick-filter[data-filter]');
    const filterType = button?.dataset.filterType || button?.dataset.filter;
    
    if (!filterType) {
        throw new ValidationError('Quick filter button missing filter type attribute', {
            element: button,
            action: 'quick-filter'
        });
    }
    
    // Validate filter type is supported
    const supportedFilters = ['self_confirmable', 'available', 'expiring', 'expired'];
    if (!supportedFilters.includes(filterType)) {
        throw new ValidationError(`Unsupported filter type: ${filterType}`, {
            element: button,
            action: 'quick-filter',
            filterType
        });
    }
    
    // Check if filtering system is available
    if (!document.querySelector('#search, #status, #type-filter')) {
        throw new ValidationError('Filter controls not available on this page', {
            element: button,
            action: 'quick-filter'
        });
    }
}

/**
 * Validate print actions
 * @param {Element} target - The clicked element
 * @param {Event} event - The click event
 */
function validatePrintAction(target, event) {
    // Check if browser supports printing
    if (typeof window.print !== 'function') {
        throw new ValidationError('Print functionality not available in this browser', {
            element: target,
            action: 'print'
        });
    }
    
    // Check if there's content to print
    const printableContent = document.querySelector('.certification-item, .certification-catalog');
    if (!printableContent) {
        throw new ValidationError('No printable certification content found on page', {
            element: target,
            action: 'print'
        });
    }
}

/**
 * Validate modal actions
 * @param {Element} target - The clicked element
 * @param {Event} event - The click event
 */
function validateModalAction(target, event) {
    const button = target.closest('[data-certification-action]');
    const action = button?.dataset.certificationAction;
    
    if (!action) {
        throw new ValidationError('Modal button missing certification action', {
            element: button,
            action: 'modal'
        });
    }
    
    const modal = button.closest('.modal');
    if (!modal) {
        throw new ValidationError('Modal action button not within a modal', {
            element: button,
            action: 'modal'
        });
    }
    
    // Validate action-specific requirements
    if (action === 'confirm') {
        validateModalConfirmAction(button, modal);
    }
}

/**
 * Validate modal confirm actions
 * @param {Element} button - The confirm button
 * @param {Element} modal - The modal element
 */
function validateModalConfirmAction(button, modal) {
    // Check for required form validation
    const form = modal.querySelector('form');
    if (form) {
        const requiredInputs = form.querySelectorAll('[required]');
        const invalidInputs = [];
        
        requiredInputs.forEach(input => {
            if (!input.checkValidity()) {
                invalidInputs.push(input);
            }
        });
        
        if (invalidInputs.length > 0) {
            throw new ValidationError('Required form fields are not valid', {
                element: form,
                action: 'form-validation',
                invalidInputs: invalidInputs.map(input => input.name || input.id)
            });
        }
    }
}

/**
 * Validate dynamic certification actions
 * @param {Element} target - The clicked element
 * @param {Event} event - The click event
 */
function validateDynamicCertificationAction(target, event) {
    const button = target.closest('[data-action]');
    const action = button?.dataset.action;
    const certificationCard = button?.closest('.certification-item');
    
    if (!certificationCard) {
        throw new ValidationError('Dynamic action not within certification item', {
            element: button,
            action: 'dynamic'
        });
    }
    
    // Some actions may require certification UUID
    const requiresUuid = ['quick-renew', 'download-certificate', 'share-certification'];
    if (requiresUuid.includes(action)) {
        const certificationUuid = certificationCard.dataset.certificationUuid || 
                                button.dataset.certificationUuid;
        
        if (!certificationUuid) {
            throw new ValidationError(`Action "${action}" requires certification UUID`, {
                element: button,
                action: 'dynamic',
                requiredData: 'certification-uuid'
            });
        }
    }
}

/**
 * Validate and handle certification form submissions
 * @param {Event} e - The submit event
 */
function validateAndHandleCertificationSubmit(e) {
    const form = e.target;
    
    // Only validate certification-related forms
    if (form.closest('.modal') && form.closest('.modal').id === 'quickSelfConfirmModal') {
        // Additional validation can be added here
        const acknowledgmentCheckbox = form.querySelector('#quickConfirmAcknowledgment');
        if (acknowledgmentCheckbox && !acknowledgmentCheckbox.checked) {
            e.preventDefault();
            throw new ValidationError('Acknowledgment checkbox must be checked', {
                element: acknowledgmentCheckbox,
                action: 'form-validation'
            });
        }
    }
}

/**
 * Validate and handle certification input events
 * @param {Event} e - The input event
 */
function validateAndHandleCertificationInput(e) {
    const input = e.target;
    
    // Validate search input
    if (input.id === 'search') {
        // Could add search term validation here
        const searchTerm = input.value.trim();
        if (searchTerm.length > 100) {
            console.warn('Search term is very long, may impact performance');
        }
    }
}

/**
 * Handle certification-related event errors
 * @param {Event} errorEvent - The error event
 */
function handleCertificationEventError(errorEvent) {
    const element = errorEvent.target;
    const error = errorEvent.error;
    
    // Log detailed error information
    console.error('Certification event error details:', {
        error: error,
        element: element,
        stack: error?.stack,
        timestamp: new Date().toISOString()
    });
    
    // Provide user feedback
    try {
        showAlert('error', 'An unexpected error occurred. Please refresh the page and try again.');
    } catch (alertError) {
        console.error('Unable to show error alert:', alertError);
        alert('An unexpected error occurred. Please refresh the page and try again.');
    }
}

/**
 * Handle event validation errors
 * @param {Error} error - The validation error
 * @param {string} eventType - The type of event
 * @param {Element} element - The element that triggered the event
 */
function handleEventValidationError(error, eventType, element) {
    const errorInfo = {
        error: error,
        eventType: eventType,
        element: element,
        timestamp: new Date().toISOString()
    };
    
    console.error('Event validation error:', errorInfo);
    
    // Provide specific user feedback based on error type
    if (error instanceof ValidationError) {
        handleValidationError(error, element);
    } else {
        // Generic error handling
        try {
            showAlert('warning', 'Unable to complete action. Please check the page and try again.');
        } catch (alertError) {
            console.error('Unable to show validation error alert:', alertError);
        }
    }
}

/**
 * Handle specific validation errors with user-friendly messages
 * @param {ValidationError} error - The validation error
 * @param {Element} element - The element that triggered the error
 */
function handleValidationError(error, element) {
    let userMessage = 'Unable to complete action.';
    
    // Provide specific messages based on error context
    if (error.context?.action === 'self-confirm') {
        userMessage = 'Unable to open self-confirmation dialog. Please try again.';
    } else if (error.context?.action === 'quick-filter') {
        userMessage = 'Filter could not be applied. Please use the manual filter controls.';
    } else if (error.context?.action === 'print') {
        userMessage = 'Print function not available. Please use your browser\'s print option (Ctrl+P).';
    } else if (error.context?.action === 'form-validation') {
        userMessage = 'Please fill in all required fields correctly.';
    }
    
    try {
        showAlert('warning', userMessage);
    } catch (alertError) {
        console.error('Unable to show validation error alert:', alertError);
        alert(userMessage);
    }
}

/**
 * Setup CSP violation reporting to catch inline event handler usage
 */
function setupCSPViolationReporting() {
    document.addEventListener('securitypolicyviolation', function(e) {
        if (e.violatedDirective === 'script-src' && 
            e.blockedURI.includes('inline') &&
            e.sourceFile.includes('certifications')) {
            
            console.warn('CSP violation detected in certification code:', {
                directive: e.violatedDirective,
                blockedURI: e.blockedURI,
                lineNumber: e.lineNumber,
                sourceFile: e.sourceFile
            });
            
            // Could send violation reports to server for monitoring
            // reportCSPViolation(e);
        }
    });
}

/**
 * Custom validation error class for better error handling
 */
class ValidationError extends Error {
    constructor(message, context) {
        super(message);
        this.name = 'ValidationError';
        this.context = context;
    }
}

function handleQuickRenew(certificationData) {
    console.log('Quick renew not yet implemented for:', certificationData);
    showAlert('info', 'Quick renewal feature coming soon. Please use the main renewal process.');
}

function handleCertificateDownload(certificationData) {
    console.log('Certificate download not yet implemented for:', certificationData);
    showAlert('info', 'Certificate download feature coming soon.');
}

function handleCertificationShare(certificationData) {
    console.log('Certification sharing not yet implemented for:', certificationData);
    showAlert('info', 'Certification sharing feature coming soon.');
}

/**
 * Initialize certification item handlers for self-confirmation buttons
 * Creates global selfConfirm function and sets up event delegation for CSP compliance
 * Replaces inline onclick="selfConfirm(...)" handlers in certification-item.twig
 */
function initCertificationItemHandlers() {
    try {
        // Create global selfConfirm function for onclick handlers (backwards compatibility)
        window.selfConfirm = function(certificationUuid, certificationTitle) {
            try {
                // Use existing modal functionality
                showQuickSelfConfirmModal(certificationUuid, certificationTitle);
                console.log('Self-confirm modal opened for:', certificationTitle);
            } catch (error) {
                console.error('Error opening self-confirm modal:', error);
                // Fallback: redirect to full form
                window.location.href = `/user/certifications/self-confirm/${certificationUuid}`;
            }
        };
        
        // Set up event delegation for future CSP compliance (when templates are updated)
        document.addEventListener('click', function(e) {
            // Check if clicked element is a self-confirm button with data attributes
            const selfConfirmButton = e.target.closest('[data-action="self-confirm"]');
            if (selfConfirmButton) {
                e.preventDefault();
                
                const certificationUuid = selfConfirmButton.dataset.certificationUuid;
                const certificationTitle = selfConfirmButton.dataset.certificationTitle || 'Unknown Certification';
                
                if (certificationUuid) {
                    showQuickSelfConfirmModal(certificationUuid, certificationTitle);
                } else {
                    console.error('Self-confirm button missing required data-certification-uuid attribute');
                    showAlert('error', 'Unable to process self-confirmation. Missing certification information.');
                }
                return;
            }
            
            // Check if clicked element is a process quick self-confirm button
            const processButton = e.target.closest('[data-action="process-quick-self-confirm"]');
            if (processButton) {
                e.preventDefault();
                
                const certificationUuid = processButton.dataset.certificationUuid;
                const certificationTitle = processButton.dataset.certificationTitle || 'Unknown Certification';
                
                if (certificationUuid) {
                    processQuickSelfConfirm(certificationUuid, certificationTitle);
                } else {
                    console.error('Process button missing required data-certification-uuid attribute');
                    showAlert('error', 'Unable to process self-confirmation. Missing certification information.');
                }
                return;
            }
        });
        
        console.log('Certification item handlers initialized successfully');
        
    } catch (error) {
        console.error('Failed to initialize certification item handlers:', error);
        // Graceful degradation - existing onclick handlers still work as fallback
    }
}

/**
 * Initialize print handler using event delegation to replace onclick handlers
 * Migrated from index.twig and certifications.twig inline onclick for CSP compliance
 */
function initPrintHandler() {
    try {
        // Use event delegation on document to handle print links
        document.addEventListener('click', function(e) {
            // Check if clicked element is a print certification link
            if (e.target && e.target.closest('[data-action="print-certifications"]')) {
                e.preventDefault();
                handlePrintCertifications();
            }
        });
        
        console.log('Print handler initialized successfully');
        
    } catch (error) {
        console.error('Failed to initialize print handler:', error);
        // Graceful degradation - onclick handlers in templates still work as fallback
    }
}

/**
 * Handle certification printing functionality
 * Replaces inline printCertifications() function from templates
 */
function handlePrintCertifications() {
    try {
        // Simple print functionality - opens browser print dialog
        window.print();
        
        console.log('Print dialog opened');
        
    } catch (error) {
        console.error('Error opening print dialog:', error);
        // Graceful degradation - inform user if print fails
        try {
            showAlert('warning', 'Unable to open print dialog. Please use your browser\'s print function (Ctrl+P).');
        } catch (alertError) {
            // Last resort - browser alert
            alert('Unable to open print dialog. Please use your browser\'s print function (Ctrl+P).');
        }
    }
}

/**
 * Initialize enhanced action feedback for admin inline actions
 */
function initEnhancedActionFeedback() {
    // Handle approve action buttons
    const approveButtons = document.querySelectorAll('button[data-action="approve"]');
    approveButtons.forEach(button => {
        button.addEventListener('click', handleApproveAction);
    });
    
    // Handle revoke action buttons  
    const revokeButtons = document.querySelectorAll('button[data-action="revoke"]');
    revokeButtons.forEach(button => {
        button.addEventListener('click', handleRevokeAction);
    });
    
    // Handle form-based actions (for fallback support)
    const actionForms = document.querySelectorAll('form[data-action-form]');
    actionForms.forEach(form => {
        form.addEventListener('submit', handleActionFormSubmit);
    });
}

/**
 * Handle approve action with enhanced feedback
 */
function handleApproveAction(e) {
    e.preventDefault();
    
    const button = e.target.closest('button');
    const userId = button.getAttribute('data-user-id');
    const certId = button.getAttribute('data-cert-id');
    const userName = button.getAttribute('data-user-name') || 'this user';
    
    // Enhanced confirmation dialog
    const confirmed = confirm(
        `Are you sure you want to APPROVE the certification for ${userName}?\n\n` +
        `This action will:\n` +
        `• Mark their certification as approved\n` +
        `• Make it visible in their active certifications\n` +
        `• Send a notification email (if enabled)\n\n` +
        `This action can be reversed by revoking the certification later.`
    );
    
    if (!confirmed) {
        return;
    }
    
    // Set loading state
    setButtonLoadingState(button, 'Approving...', 'fa-check');
    
    // Prepare form data - use POST to /update endpoint instead of PUT with method override
    const formData = new FormData();
    formData.append('action', 'approve');
    formData.append('status', 'approved');
    formData.append('checked', 'on');  // Required by controller validation
    
    // Add CSRF token if available
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                     document.querySelector('input[name="_token"]')?.value;
    if (csrfToken) {
        formData.append('_token', csrfToken);
    }
    
    // Send AJAX request to update endpoint
    const actionUrl = `/admin/user/${userId}/certifications/${certId}/update`;
    
    fetch(actionUrl, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (response.ok) {
            return response.json().catch(() => ({})); // Handle non-JSON responses
        }
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    })
    .then(data => {
        // Success feedback
        showAlert('success', data.message || `Successfully approved certification for ${userName}!`);
        
        // Update UI elements
        updateUserRowAfterAction(button, 'approved');
        
        // Reset button state
        resetButtonState(button, 'Approve', 'fa-check');
        
    })
    .catch(error => {
        console.error('Approve action failed:', error);
        
        // Error feedback
        showAlert('error', `Failed to approve certification: ${error.message}`);
        
        // Reset button state
        resetButtonState(button, 'Approve', 'fa-check');
    });
}

/**
 * Handle revoke action with enhanced feedback
 */
function handleRevokeAction(e) {
    e.preventDefault();
    
    const button = e.target.closest('button');
    const userId = button.getAttribute('data-user-id');
    const certId = button.getAttribute('data-cert-id');
    const userName = button.getAttribute('data-user-name') || 'this user';
    
    // Enhanced confirmation dialog with warning
    const confirmed = confirm(
        `⚠️ REVOKE CERTIFICATION - ${userName}\n\n` +
        `Are you sure you want to REVOKE this certification?\n\n` +
        `This action will:\n` +
        `• Remove the certification from their active list\n` +
        `• Mark it as revoked in their certification history\n` +
        `• Send a revocation notification (if enabled)\n` +
        `• May affect their access to restricted areas/roles\n\n` +
        `⚠️ This is a serious action that should only be taken when necessary.\n\n` +
        `Type 'REVOKE' to confirm:`
    );
    
    if (!confirmed) {
        return;
    }
    
    // Additional confirmation for revoke actions
    const doubleConfirm = prompt(
        `Final confirmation required.\n\nType 'REVOKE' to confirm revocation of ${userName}'s certification:`
    );
    
    if (doubleConfirm !== 'REVOKE') {
        showAlert('info', 'Revocation cancelled - confirmation text did not match.');
        return;
    }
    
    // Set loading state
    setButtonLoadingState(button, 'Revoking...', 'fa-times');
    
    // Prepare form data - use POST to /delete endpoint instead of DELETE with method override  
    const formData = new FormData();
    formData.append('action', 'revoke');
    formData.append('reason', 'Administrative revocation');
    formData.append('checked', 'on');  // Required by controller validation
    
    // Add CSRF token if available
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                     document.querySelector('input[name="_token"]')?.value;
    if (csrfToken) {
        formData.append('_token', csrfToken);
    }
    
    // Send AJAX request to delete endpoint
    const actionUrl = `/admin/user/${userId}/certifications/${certId}/delete`;
    
    fetch(actionUrl, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (response.ok) {
            return response.json().catch(() => ({})); // Handle non-JSON responses
        }
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    })
    .then(data => {
        // Success feedback
        showAlert('success', data.message || `Successfully revoked certification for ${userName}.`);
        
        // Update UI elements
        updateUserRowAfterAction(button, 'revoked');
        
        // Reset button state
        resetButtonState(button, 'Revoke', 'fa-times');
        
    })
    .catch(error => {
        console.error('Revoke action failed:', error);
        
        // Error feedback  
        showAlert('error', `Failed to revoke certification: ${error.message}`);
        
        // Reset button state
        resetButtonState(button, 'Revoke', 'fa-times');
    });
}

/**
 * Handle form-based action submissions (fallback)
 */
function handleActionFormSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const submitButton = form.querySelector('button[type="submit"]');
    const action = form.getAttribute('data-action-form');
    
    if (action === 'approve' || action === 'revoke') {
        // Use enhanced handlers for these actions
        if (action === 'approve') {
            // Trigger approve handler on the button
            const approveButton = form.querySelector('button[data-action="approve"]');
            if (approveButton) {
                handleApproveAction({ target: approveButton, preventDefault: () => {} });
                return;
            }
        } else if (action === 'revoke') {
            // Trigger revoke handler on the button
            const revokeButton = form.querySelector('button[data-action="revoke"]');
            if (revokeButton) {
                handleRevokeAction({ target: revokeButton, preventDefault: () => {} });
                return;
            }
        }
    }
    
    // Fallback to normal form submission with loading state
    if (submitButton) {
        setButtonLoadingState(submitButton, 'Processing...', 'fa-spinner');
    }
    
    // Allow normal form submission
    form.submit();
}

/**
 * Set button to loading state
 */
function setButtonLoadingState(button, loadingText, originalIcon = '') {
    // Store original state
    button.setAttribute('data-original-text', button.innerHTML);
    button.setAttribute('data-original-disabled', button.disabled);
    
    // Set loading state
    button.disabled = true;
    button.innerHTML = `<i class="fa fa-spinner fa-spin"></i> ${loadingText}`;
}

/**
 * Reset button to original state
 */
function resetButtonState(button, originalText, originalIcon = '') {
    // Use stored original state or provided defaults
    const storedText = button.getAttribute('data-original-text');
    const storedDisabled = button.getAttribute('data-original-disabled') === 'true';
    
    if (storedText) {
        button.innerHTML = storedText;
    } else if (originalIcon) {
        button.innerHTML = `<i class="fa ${originalIcon}"></i> ${originalText}`;
    } else {
        button.innerHTML = originalText;
    }
    
    button.disabled = storedDisabled;
    
    // Clean up stored attributes
    button.removeAttribute('data-original-text');
    button.removeAttribute('data-original-disabled');
}

/**
 * Update user row UI after successful action
 */
function updateUserRowAfterAction(actionButton, newStatus) {
    const userRow = actionButton.closest('tr');
    if (!userRow) return;
    
    // Find and update status badge
    const statusBadge = userRow.querySelector('.badge');
    if (statusBadge) {
        // Remove old status classes
        statusBadge.classList.remove('bg-warning', 'bg-success', 'bg-danger', 'bg-secondary', 'bg-info');
        
        // Add new status class and text
        switch (newStatus) {
            case 'approved':
                statusBadge.classList.add('bg-success');
                statusBadge.textContent = 'Approved';
                break;
            case 'revoked':
                statusBadge.classList.add('bg-danger');
                statusBadge.textContent = 'Revoked';
                break;
            case 'pending':
                statusBadge.classList.add('bg-warning');
                statusBadge.textContent = 'Pending';
                break;
            default:
                statusBadge.classList.add('bg-secondary');
                statusBadge.textContent = newStatus;
        }
    }
    
    // Update action buttons based on new status
    updateActionButtonsForStatus(userRow, newStatus);
    
    // Add visual feedback (brief highlight)
    userRow.classList.add('table-success');
    setTimeout(() => {
        userRow.classList.remove('table-success');
    }, 2000);
}

/**
 * Update action buttons visibility based on status
 */
function updateActionButtonsForStatus(userRow, status) {
    const approveButton = userRow.querySelector('button[data-action="approve"]');
    const revokeButton = userRow.querySelector('button[data-action="revoke"]');
    
    if (approveButton && revokeButton) {
        switch (status) {
            case 'approved':
                // Hide approve, show revoke
                approveButton.style.display = 'none';
                revokeButton.style.display = 'inline-block';
                break;
            case 'revoked':
                // Show approve, hide revoke
                approveButton.style.display = 'inline-block'; 
                revokeButton.style.display = 'none';
                break;
            case 'pending':
                // Show both
                approveButton.style.display = 'inline-block';
                revokeButton.style.display = 'inline-block';
                break;
            default:
                // Show both by default
                approveButton.style.display = 'inline-block';
                revokeButton.style.display = 'inline-block';
        }
    }
}

/**
 * Initialize bulk operations functionality
 */
function initBulkOperations() {
    // Handle select all checkboxes
    document.querySelectorAll('[id^="select-all-"]').forEach(selectAllCheckbox => {
        selectAllCheckbox.addEventListener('change', function() {
            const status = this.dataset.target;
            const checkboxes = document.querySelectorAll(`.bulk-select[data-status="${status}"]`);
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActionButtons(status);
        });
    });

    // Handle individual checkbox changes
    document.querySelectorAll('.bulk-select').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const status = this.dataset.status;
            updateSelectAllCheckbox(status);
            updateBulkActionButtons(status);
        });
    });

    // Handle bulk action buttons
    document.querySelectorAll('[id^="bulk-approve-"], [id^="bulk-revoke-"]').forEach(button => {
        button.addEventListener('click', handleBulkAction);
    });
}

/**
 * Update the select all checkbox state based on individual selections
 */
function updateSelectAllCheckbox(status) {
    const checkboxes = document.querySelectorAll(`.bulk-select[data-status="${status}"]`);
    const selectAllCheckboxes = document.querySelectorAll(`[data-target="${status}"]`);
    
    const totalCheckboxes = checkboxes.length;
    const checkedCheckboxes = document.querySelectorAll(`.bulk-select[data-status="${status}"]:checked`).length;
    
    selectAllCheckboxes.forEach(selectAll => {
        if (checkedCheckboxes === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        } else if (checkedCheckboxes === totalCheckboxes) {
            selectAll.checked = true;
            selectAll.indeterminate = false;
        } else {
            selectAll.checked = false;
            selectAll.indeterminate = true;
        }
    });
}

/**
 * Update bulk action buttons based on selections
 */
function updateBulkActionButtons(status) {
    const checkedCheckboxes = document.querySelectorAll(`.bulk-select[data-status="${status}"]:checked`);
    const bulkButtons = document.querySelectorAll(`[data-status="${status}"][id^="bulk-"]`);
    
    bulkButtons.forEach(button => {
        button.disabled = checkedCheckboxes.length === 0;
        
        // Update button text with count
        const action = button.dataset.action;
        const baseText = action === 'approve' ? 'Bulk Approve' : 'Bulk Revoke';
        const icon = action === 'approve' ? 
            '<i class="bi bi-check"></i>' : 
            '<i class="bi bi-x"></i>';
        
        if (checkedCheckboxes.length > 0) {
            button.innerHTML = `${icon} ${baseText} (${checkedCheckboxes.length})`;
        } else {
            button.innerHTML = `${icon} ${baseText}`;
        }
    });
}

/**
 * Handle bulk action execution
 */
function handleBulkAction(event) {
    event.preventDefault();
    
    const button = event.currentTarget;
    const action = button.dataset.action;
    const status = button.dataset.status;
    const certificationId = button.dataset.certificationId;
    
    const checkedCheckboxes = document.querySelectorAll(`.bulk-select[data-status="${status}"]:checked`);
    
    if (checkedCheckboxes.length === 0) {
        showAlert('Please select at least one certification to ' + action + '.', 'warning');
        return;
    }
    
    const certificationIds = Array.from(checkedCheckboxes).map(cb => parseInt(cb.value));
    const count = certificationIds.length;
    
    // Show confirmation dialog
    const actionText = action === 'approve' ? 'approve' : 'revoke';
    const confirmMessage = `Are you sure you want to ${actionText} ${count} certification(s)?`;
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    // Additional confirmation for bulk revoke actions
    if (action === 'revoke') {
        const doubleConfirm = prompt(
            `FINAL CONFIRMATION REQUIRED\n\nThis will revoke ${count} certification(s) which is a significant administrative action.\n\nType 'BULK REVOKE' to confirm:`
        );
        if (doubleConfirm !== 'BULK REVOKE') {
            return;
        }
    }
    
    // Disable button and show loading state
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
    
    // Prepare form data
    const formData = new FormData();
    formData.append('action', action);
    certificationIds.forEach(id => {
        formData.append('certification_ids[]', id);
    });
    
    // Add CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                      document.querySelector('input[name="_token"]')?.value;
    if (csrfToken) {
        formData.append('_token', csrfToken);
    }
    
    // Make AJAX request
    fetch('/admin/user-certifications/bulk', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        // Restore button
        button.disabled = false;
        button.innerHTML = originalText;
        
        if (data.success) {
            showAlert(data.message, 'success');
            
            // Refresh the page to show updated data
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert(data.message || 'An error occurred during bulk operation.', 'danger');
            
            // Show error details if provided
            if (data.error_details && data.error_details.length > 0) {
                console.error('Bulk operation errors:', data.error_details);
            }
        }
    })
    .catch(error => {
        console.error('Bulk operation error:', error);
        
        // Restore button
        button.disabled = false;
        button.innerHTML = originalText;
        
        showAlert('An error occurred during bulk operation.', 'danger');
    });
}

/**
 * Initialize enhanced user table features including sorting and filtering
 */
function initEnhancedUserTableFeatures() {
    initTableSorting();
    initTableFiltering();
    initStatusNotifications();
}

/**
 * Initialize table sorting functionality
 */
function initTableSorting() {
    // Handle sort dropdown options
    document.addEventListener('click', function(e) {
        if (e.target.closest('.sort-option')) {
            e.preventDefault();
            
            const sortOption = e.target.closest('.sort-option');
            const sortType = sortOption.dataset.sort;
            const targetStatus = sortOption.dataset.target;
            
            sortUserTable(targetStatus, sortType);
        }
    });

    // Handle column header sorting
    document.addEventListener('click', function(e) {
        if (e.target.closest('.sortable')) {
            const header = e.target.closest('.sortable');
            const table = header.closest('table');
            const status = table.querySelector('.sortable-tbody').dataset.status;
            const sortType = header.dataset.sort;
            
            // Toggle sort direction
            const currentDirection = header.dataset.direction || 'asc';
            const newDirection = currentDirection === 'asc' ? 'desc' : 'asc';
            header.dataset.direction = newDirection;
            
            // Update sort icons
            table.querySelectorAll('.sortable i').forEach(icon => {
                icon.className = 'fa fa-sort text-muted ms-1';
            });
            
            const icon = header.querySelector('i');
            icon.className = newDirection === 'asc' 
                ? 'fa fa-sort-up text-primary ms-1' 
                : 'fa fa-sort-down text-primary ms-1';
            
            sortUserTable(status, sortType + (newDirection === 'desc' ? '-desc' : ''));
        }
    });
}

/**
 * Sort user table by specified criteria
 */
function sortUserTable(status, sortType) {
    const table = document.querySelector(`#users-table-${status}`);
    if (!table) return;
    
    const tbody = table.querySelector('.sortable-tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        let aVal, bVal;
        
        switch (sortType) {
            case 'name':
            case 'name-desc':
                aVal = a.dataset.userName || '';
                bVal = b.dataset.userName || '';
                break;
                
            case 'date':
            case 'date-desc':
                aVal = new Date(a.dataset.certifiedAt || 0);
                bVal = new Date(b.dataset.certifiedAt || 0);
                break;
                
            case 'expires':
            case 'expires-desc':
                aVal = new Date(a.dataset.expiresAt || '9999-12-31');
                bVal = new Date(b.dataset.expiresAt || '9999-12-31');
                break;
                
            default:
                return 0;
        }
        
        let result;
        if (aVal < bVal) result = -1;
        else if (aVal > bVal) result = 1;
        else result = 0;
        
        // Reverse for desc sorts
        if (sortType.includes('-desc')) {
            result *= -1;
        }
        
        return result;
    });
    
    // Clear and re-append sorted rows
    tbody.innerHTML = '';
    rows.forEach(row => tbody.appendChild(row));
    
    // Add visual feedback
    tbody.style.opacity = '0.7';
    setTimeout(() => {
        tbody.style.opacity = '1';
    }, 200);
}

/**
 * Initialize table filtering functionality
 */
function initTableFiltering() {
    // Add search inputs to each tab if they don't exist
    document.querySelectorAll('.tab-pane').forEach(tabPane => {
        const card = tabPane.querySelector('.card');
        if (!card) return;
        
        const cardHeader = card.querySelector('.card-header');
        if (!cardHeader || cardHeader.querySelector('.table-search')) return;
        
        const status = tabPane.id;
        
        // Create search input
        const searchContainer = document.createElement('div');
        searchContainer.className = 'table-search ms-2';
        searchContainer.innerHTML = `
            <div class="input-group input-group-sm" style="width: 200px;">
                <span class="input-group-text">
                    <i class="fa fa-search"></i>
                </span>
                <input type="text" class="form-control" placeholder="Search users..." 
                       id="search-${status}" data-target="${status}">
            </div>
        `;
        
        cardHeader.appendChild(searchContainer);
    });
    
    // Handle search input
    document.addEventListener('input', function(e) {
        if (e.target.matches('[id^="search-"]')) {
            const searchTerm = e.target.value.toLowerCase();
            const targetStatus = e.target.dataset.target;
            
            filterUserTable(targetStatus, searchTerm);
        }
    });
}

/**
 * Filter user table by search term
 */
function filterUserTable(status, searchTerm) {
    const table = document.querySelector(`#users-table-${status}`);
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const userName = row.dataset.userName || '';
        const userNameText = row.querySelector('strong')?.textContent?.toLowerCase() || '';
        
        if (userName.includes(searchTerm) || userNameText.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Update empty state
    showSearchResults(status, visibleCount, searchTerm);
}

/**
 * Show search results or empty state
 */
function showSearchResults(status, count, searchTerm) {
    const tabPane = document.querySelector(`#${status}`);
    let emptyState = tabPane.querySelector('.search-empty-state');
    
    if (count === 0 && searchTerm) {
        if (!emptyState) {
            emptyState = document.createElement('div');
            emptyState.className = 'search-empty-state text-center py-4';
            emptyState.innerHTML = `
                <div class="text-muted">
                    <i class="fa fa-search fa-2x mb-3"></i>
                    <h6>No users found matching "${searchTerm}"</h6>
                    <p>Try adjusting your search terms.</p>
                </div>
            `;
            
            const cardBody = tabPane.querySelector('.card-body');
            cardBody.appendChild(emptyState);
        }
        
        emptyState.querySelector('h6').textContent = `No users found matching "${searchTerm}"`;
        emptyState.style.display = 'block';
    } else if (emptyState) {
        emptyState.style.display = 'none';
    }
}

/**
 * Initialize status-based notifications and visual indicators
 */
function initStatusNotifications() {
    // Highlight pending approvals
    updatePendingIndicators();
    
    // Set up periodic updates for expiring certifications
    setInterval(updateExpiryWarnings, 300000); // Check every 5 minutes
    updateExpiryWarnings();
}

/**
 * Update pending approval indicators
 */
function updatePendingIndicators() {
    const pendingTab = document.querySelector('#pending-tab');
    const pendingBadge = pendingTab?.querySelector('.badge');
    
    if (pendingBadge && parseInt(pendingBadge.textContent) > 0) {
        // Add pulsing animation for pending items
        pendingTab.classList.add('position-relative');
        
        // Animate the notification dot
        const notificationDot = pendingTab.querySelector('.position-absolute');
        if (notificationDot) {
            setInterval(() => {
                notificationDot.style.animation = 'none';
                setTimeout(() => {
                    notificationDot.style.animation = 'pulse 2s infinite';
                }, 10);
            }, 3000);
        }
    }
}

/**
 * Update expiry warnings for certifications expiring soon
 */
function updateExpiryWarnings() {
    document.querySelectorAll('[data-expires-at]').forEach(row => {
        const expiresAt = new Date(row.dataset.expiresAt);
        const now = new Date();
        const daysToExpiry = Math.ceil((expiresAt - now) / (1000 * 60 * 60 * 24));
        
        const expiryBadge = row.querySelector('.badge');
        if (expiryBadge && daysToExpiry <= 7 && daysToExpiry > 0) {
            expiryBadge.classList.remove('bg-warning');
            expiryBadge.classList.add('bg-danger');
            expiryBadge.innerHTML = `<i class="fa fa-exclamation-triangle"></i> ${daysToExpiry} days`;
        }
    });
}
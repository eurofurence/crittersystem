/**
 * Purge Controller JavaScript
 * Handles confirmation flow, backup status checking, and execution
 */

class PurgeController {
  constructor() {
    this.currentPreviewData = null;
    this.confirmationModal = null;
    this.isExecuting = false;

    this.init();
  }

  init() {
    // Initialize confirmation modal (support both IDs used across templates)
    const modalElementPrimary = document.getElementById('purgeConfirmModal');
    const modalElementAlt = document.getElementById('confirmationModal');
    const modalElement = modalElementPrimary || modalElementAlt;
    if (modalElement) {
      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        this.confirmationModal = new bootstrap.Modal(modalElement);
      } else {
        // Fallback in case Bootstrap JS is not globally available
        this.confirmationModal = this.createFallbackModal(modalElement);
      }
    }

    // Bind event listeners
    this.bindEvents();
    this.bindPreviewForm();
  }

  // Minimal modal fallback if Bootstrap's JS is not available globally
  createFallbackModal(modalEl) {
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade';

    const show = () => {
      document.body.appendChild(backdrop);
      // Force reflow to enable CSS transition
      void backdrop.offsetWidth; // eslint-disable-line no-unused-expressions
      backdrop.classList.add('show');

      modalEl.style.display = 'block';
      modalEl.removeAttribute('aria-hidden');
      modalEl.classList.add('show');
      document.body.classList.add('modal-open');
    };

    const hide = () => {
      modalEl.classList.remove('show');
      modalEl.setAttribute('aria-hidden', 'true');
      modalEl.style.display = 'none';
      document.body.classList.remove('modal-open');
      if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
    };

    // Close on elements with [data-bs-dismiss="modal"]
    modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach((btn) => {
      btn.addEventListener('click', hide);
    });

    return { show, hide };
  }

  bindEvents() {
    // Listen for purge confirmation events (from preview.twig)
    window.addEventListener('purgeConfirmed', () => {
      this.executePurge();
    });

    // Handle confirmation input validation (support both sets of IDs)
    const confirmInput = document.getElementById('confirmationInput') || document.getElementById('confirmText');
    const backupConfirm = document.getElementById('backupConfirm');

    if (confirmInput) {
      confirmInput.addEventListener('input', () => this.validateConfirmation());
    }
    if (backupConfirm) {
      backupConfirm.addEventListener('change', () => this.validateConfirmation());
    }

    // Handle final confirm button (preview.twig style)
    const finalConfirmButton = document.getElementById('finalConfirmButton');
    if (finalConfirmButton) {
      finalConfirmButton.addEventListener('click', () => {
        this.confirmPurge();
      });
    }

    // Handle "DELETE" confirmation modal execute (index.twig style)
    const execBtn = document.getElementById('confirmPurgeBtn');
    if (execBtn) {
      execBtn.addEventListener('click', () => this.executeFromModal());
    }
  }

  bindPreviewForm() {
    const form = document.getElementById('purge-form');
    const previewBtn = document.getElementById('preview-btn');
    const previewResults = document.getElementById('preview-results');
    if (!form || !previewResults) return;

    const messages = this.getMessages();

    const validateForm = () => {
      const categories = document.querySelectorAll('input[name="categories[]"]:checked');
      const cutoffDate = document.querySelector('input[name="cutoff_date"]').value;
      const errors = [];

      if (categories.length === 0) {
        errors.push(messages.selectCategory);
      }

      if (!cutoffDate) {
        errors.push(messages.selectDate);
      } else {
        const selectedDate = new Date(cutoffDate);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (selectedDate >= today) {
          errors.push(messages.pastDate);
        }
      }
      return errors;
    };

    const showErrors = (errors) => {
      const errorHtml = `<div class="alert alert-danger"><ul class="mb-0">${errors
        .map((error) => `<li>${error}</li>`)
        .join('')}</ul></div>`;
      previewResults.innerHTML = errorHtml;
    };

    const showLoading = () => {
      previewResults.innerHTML = `<div class="text-center"><i class="fa fa-spinner fa-spin"></i> ${messages.generating}</div>`;
    };

    const renderPreview = (data) => {
      let html = '<div class="alert alert-info">';
      html += `<h5>${messages.previewTitle}</h5>`;
      html += `<p>${messages.previewIntro} <strong>${data.summary.cutoff_date}</strong>:</p>`;
      html += '</div>';

      if (data.summary.total_items > 0) {
        html += '<div class="table-responsive">';
        html += '<table class="table table-sm table-striped">';
        html += `<thead><tr><th>${messages.category}</th><th>${messages.primaryItems}</th><th>${messages.relatedData}</th></tr></thead>`;
        html += '<tbody>';

        for (const [category, categoryData] of Object.entries(data.preview)) {
          html += '<tr>';
          html += `<td><strong>${category.charAt(0).toUpperCase()}${category.slice(1)}</strong></td>`;
          html += `<td><span class="badge bg-danger">${categoryData.count}</span></td>`;
          html += '<td>';

          if (categoryData.dependencies && Object.keys(categoryData.dependencies).length > 0) {
            html += '<small>';
            for (const [depType, count] of Object.entries(categoryData.dependencies)) {
              if (count > 0) {
                const label = depType.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
                html += `<div>${label}: ${count}</div>`;
              }
            }
            html += '</small>';
          } else {
            html += `<span class="text-muted">${messages.noDependencies}</span>`;
          }

          html += '</td>';
          html += '</tr>';
        }

        html += '</tbody>';
        html += '<tfoot><tr class="fw-bold">';
        html += `<td>${messages.total}</td>`;
        html += `<td><span class="badge bg-danger">${data.summary.total_items}</span></td>`;
        html += `<td><span class="badge bg-warning">${data.summary.total_dependencies}</span> ${messages.relatedItems}</td>`;
        html += '</tr></tfoot>';
        html += '</table></div>';

        html += '<div class="alert alert-warning mt-3">';
        html += '<i class="fa fa-exclamation-triangle"></i> ';
        html += `<strong>${messages.warning}</strong> `;
        html += messages.permanentDelete.replace('{items}', `<strong>${data.summary.total_items}</strong>`);
        if (data.summary.total_dependencies > 0) {
          html += ` ${messages.and} <strong>${data.summary.total_dependencies}</strong> ${messages.relatedItems}.`;
        } else {
          html += '.';
        }
        html += ` ${messages.cannotUndo}`;
        html += '</div>';

        html += '<div class="mt-3">';
        html += `<button type="button" class="btn btn-danger" id="proceed-to-execute"><i class="fa fa-trash"></i> ${messages.proceedDelete}</button> `;
        html += `<button type="button" class="btn btn-secondary" id="cancel-purge"><i class="fa fa-times"></i> ${messages.cancel}</button>`;
        html += '</div>';
      } else {
        html += '<div class="alert alert-success">';
        html += `<i class="fa fa-check-circle"></i> ${messages.noDataFound}`;
        html += '</div>';
      }

      previewResults.innerHTML = html;

      // Bind action buttons (CSP-compliant: no inline handlers)
      const proceedBtn = document.getElementById('proceed-to-execute');
      if (proceedBtn) {
        proceedBtn.addEventListener('click', () => {
          if (typeof showConfirmationDialog === 'function') {
            showConfirmationDialog();
          }
        });
      }
      const cancelBtn = document.getElementById('cancel-purge');
      if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
          if (typeof cancelPreview === 'function') {
            cancelPreview();
          }
        });
      }
    };

    const updateValidationFeedback = () => {
      const errors = validateForm();
      const categoryCheckboxes = document.querySelectorAll('input[name="categories[]"]');
      const dateInput = document.querySelector('input[name="cutoff_date"]');

      categoryCheckboxes.forEach((cb) => {
        cb.parentElement.classList.remove('text-danger');
      });
      if (dateInput) dateInput.classList.remove('is-invalid');

      if (errors.length > 0) {
        if (previewBtn) previewBtn.disabled = true;

        if (document.querySelectorAll('input[name="categories[]"]:checked').length === 0) {
          categoryCheckboxes.forEach((cb) => {
            cb.parentElement.classList.add('text-danger');
          });
        }

        if (!dateInput.value || new Date(dateInput.value) >= new Date()) {
          dateInput.classList.add('is-invalid');
        }
      } else {
        if (previewBtn) previewBtn.disabled = false;
      }
    };

    // Attach listeners
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const errors = validateForm();
      if (errors.length > 0) {
        showErrors(errors);
        return;
      }
      showLoading();
      if (previewBtn) previewBtn.disabled = true;

      const formData = new FormData(form);
      fetch('/admin/purge/preview', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
        },
      })
        .then((r) => r.json())
        .then((data) => {
          if (data.success) {
            renderPreview(data);
          } else {
            showErrors([data.message || messages.errorGenerating]);
          }
        })
        .catch(() => {
          showErrors([messages.networkError]);
        })
        .finally(() => {
          if (previewBtn) previewBtn.disabled = false;
        });
    });

    const categoryCheckboxes = document.querySelectorAll('input[name="categories[]"]');
    const dateInput = document.querySelector('input[name="cutoff_date"]');
    categoryCheckboxes.forEach((cb) => cb.addEventListener('change', updateValidationFeedback));
    if (dateInput) {
      dateInput.addEventListener('change', updateValidationFeedback);
      dateInput.addEventListener('input', updateValidationFeedback);
    }
    updateValidationFeedback();
  }

  getMessages() {
    const el = document.getElementById('preview-results');
    const d = (el && el.dataset) || {};
    return {
      selectCategory: d.msgSelectCategory || 'Please select at least one category to purge.',
      selectDate: d.msgSelectDate || 'Please select a cutoff date.',
      pastDate: d.msgPastDate || 'Cutoff date must be in the past.',
      generating: d.msgGenerating || 'Generating preview...',
      errorGenerating: d.msgErrorGenerating || 'An error occurred while generating preview.',
      networkError: d.msgNetworkError || 'Network error occurred. Please try again.',
      previewTitle: d.msgPreviewTitle || 'Preview Results',
      previewIntro: d.msgPreviewIntro || 'Data that will be affected if purge is executed on',
      category: d.msgCategory || 'Category',
      primaryItems: d.msgPrimaryItems || 'Primary Items',
      relatedData: d.msgRelatedData || 'Related Data',
      relatedItems: d.msgRelatedItems || 'related items',
      noDependencies: d.msgNoDependencies || 'No dependencies',
      total: d.msgTotal || 'Total',
      warning: d.msgWarning || 'Warning:',
      permanentDelete: d.msgPermanentDelete || 'This operation will permanently delete {items} primary items',
      and: d.msgAnd || 'and',
      cannotUndo: d.msgCannotUndo || 'This action cannot be undone.',
      proceedDelete: d.msgProceedDelete || 'Proceed to Delete',
      cancel: d.msgCancel || 'Cancel',
      noDataFound: d.msgNoDataFound || 'No data found matching the selected criteria.',
      mustTypeDelete: d.msgMustTypeDelete || 'You must type "DELETE" to confirm.',
      executing: d.msgExecuting || 'Creating backup and executing purge...',
      purgeSuccess: d.msgPurgeSuccess || 'Purge completed successfully.',
      purgeError: d.msgPurgeError || 'An error occurred during purge execution.',
      finalStatus: d.msgFinalStatus || 'Final status:',
      totalAffected: d.msgTotalAffected || 'Total affected:',
    };
  }

  validateConfirmation() {
    const confirmInput = document.getElementById('confirmationInput') || document.getElementById('confirmText');
    const backupConfirm = document.getElementById('backupConfirm');
    const finalConfirmButton = document.getElementById('finalConfirmButton');

    if (!confirmInput || !finalConfirmButton) return;

    const isInputValid = confirmInput.value === 'DELETE';
    const isCheckboxChecked = backupConfirm ? backupConfirm.checked : true;

    finalConfirmButton.disabled = !(isInputValid && isCheckboxChecked);

    // Visual feedback
    if (confirmInput.value && confirmInput.value !== 'DELETE') {
      confirmInput.classList.add('is-invalid');
    } else {
      confirmInput.classList.remove('is-invalid');
    }
  }

  confirmPurge() {
    if (this.isExecuting) {
      return;
    }

    // Show execution progress
    this.showExecutionProgress();

    // Close confirmation modal
    if (this.confirmationModal) {
      this.confirmationModal.hide();
    }

    // Execute the purge
    this.executePurge();
  }

  executeFromModal() {
    const messages = this.getMessages();
    const form = document.getElementById('purge-form');
    const confirmInput = document.getElementById('confirmText');
    const errorBox = document.getElementById('confirmError');
    const execBtn = document.getElementById('confirmPurgeBtn');
    const statusBox = document.getElementById('executeStatus');
    if (!form || !confirmInput || !errorBox || !execBtn || !statusBox) {
      // fall back to generic execute
      return this.executePurge();
    }

    if (confirmInput.value !== 'DELETE') {
      errorBox.textContent = messages.mustTypeDelete;
      errorBox.classList.remove('d-none');
      return;
    }
    errorBox.classList.add('d-none');

    const fd = new FormData(form);
    fd.append('confirmation', 'DELETE');

    execBtn.disabled = true;
    statusBox.innerHTML = `<div class="alert alert-info"><i class="fa fa-cog fa-spin"></i> ${messages.executing}</div>`;

    fetch('/admin/purge/execute', {
      method: 'POST',
      body: fd,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
      },
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.success) {
          statusBox.innerHTML = `<div class="alert alert-success"><i class="fa fa-check-circle"></i> ${
            data.message || messages.purgeSuccess
          }</div>`;
          if (data.status_url) {
            fetch(data.status_url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
              .then((r) => r.json())
              .then((st) => {
                if (st && !st.error) {
                  const extra = document.createElement('div');
                  extra.className = 'mt-2';
                  extra.innerHTML = `<small class="text-muted">${messages.finalStatus} ${st.status} — ${
                    messages.totalAffected
                  } ${st.total_affected ?? 0}</small>`;
                  statusBox.appendChild(extra);
                }
              })
              .catch(() => {});
          }
        } else {
          statusBox.innerHTML = `<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> ${
            data.message || messages.purgeError
          }</div>`;
          execBtn.disabled = false;
        }
      })
      .catch(() => {
        statusBox.innerHTML = `<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> ${messages.networkError}</div>`;
        execBtn.disabled = false;
      });
  }

  showExecutionProgress() {
    const previewResults = document.getElementById('preview-results');
    if (!previewResults) return;

    const progressHtml = `
            <div class="alert alert-info">
                <h5><i class="fa fa-cog fa-spin"></i> Executing Purge Operation</h5>
                <div class="progress mb-3">
                    <div class="progress-bar progress-bar-striped progress-bar-animated"
                         role="progressbar"
                         style="width: 0%"
                         id="purge-progress-bar">0%</div>
                </div>
                <div id="purge-status">Initializing...</div>
                <div class="mt-3">
                    <button type="button" class="btn btn-secondary" disabled>
                        <i class="fa fa-clock"></i> Operation in Progress
                    </button>
                </div>
            </div>
        `;

    previewResults.innerHTML = progressHtml;
  }

  updateProgress(percentage, message) {
    const progressBar = document.getElementById('purge-progress-bar');
    const statusDiv = document.getElementById('purge-status');

    if (progressBar) {
      progressBar.style.width = `${percentage}%`;
      progressBar.textContent = `${percentage}%`;
    }

    if (statusDiv) {
      statusDiv.textContent = message;
    }
  }

  async executePurge() {
    if (this.isExecuting) {
      return;
    }

    this.isExecuting = true;

    try {
      // Get form data
      const form = document.getElementById('purge-form');
      const formData = new FormData(form);
      formData.append('confirmation', 'DELETE');

      // Update progress
      this.updateProgress(5, 'Starting purge operation...');

      // Make AJAX request to execute purge
      const response = await fetch('/admin/purge/execute', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
        },
      });

      const data = await response.json();

      if (data.success) {
        this.showSuccessResults(data);
      } else {
        this.showErrorResults(data.message);
      }
    } catch (error) {
      console.error('Error during purge execution:', error);
      this.showErrorResults('Network error occurred during purge execution.');
    } finally {
      this.isExecuting = false;
    }
  }

  showSuccessResults(data) {
    const previewResults = document.getElementById('preview-results');
    if (!previewResults) return;

    let html = '<div class="alert alert-success">';
    html += '<h5><i class="fa fa-check-circle"></i> Purge Operation Completed Successfully</h5>';
    html += '<p>The purge operation has been completed. Here are the results:</p>';
    html += '</div>';

    // Results summary
    html += '<div class="card">';
    html += '<div class="card-header"><h6>Purge Results</h6></div>';
    html += '<div class="card-body">';
    html += '<div class="row">';
    html += '<div class="col-md-6">';
    html += `<strong>Total Items Deleted:</strong> ${data.summary.total_deleted}<br>`;
    html += `<strong>Related Items Deleted:</strong> ${data.summary.total_dependencies}<br>`;
    html += `<strong>Categories Processed:</strong> ${data.summary.categories_processed.join(', ')}`;
    html += '</div>';
    html += '<div class="col-md-6">';
    html += '<strong>Backup Location:</strong><br>';
    html += `<small class="text-muted">${data.summary.backup_info.backup_path}</small>`;
    html += '</div>';
    html += '</div>';
    html += '</div>';
    html += '</div>';

    // Detailed results
    if (data.results) {
      html += '<div class="card mt-3">';
      html += '<div class="card-header"><h6>Detailed Results by Category</h6></div>';
      html += '<div class="card-body">';
      html += '<div class="table-responsive">';
      html += '<table class="table table-sm">';
      html += '<thead><tr><th>Category</th><th>Items Deleted</th><th>Dependencies</th></tr></thead>';
      html += '<tbody>';

      for (const [category, result] of Object.entries(data.results)) {
        html += '<tr>';
        html += `<td>${category.charAt(0).toUpperCase()}${category.slice(1)}</td>`;
        html += `<td><span class="badge bg-danger">${result.deleted}</span></td>`;
        html += '<td>';

        if (result.dependencies) {
          const depCount = Object.values(result.dependencies).reduce((a, b) => a + b, 0);
          html += `<span class="badge bg-warning">${depCount}</span>`;
        } else {
          html += '<span class="badge bg-secondary">0</span>';
        }

        html += '</td>';
        html += '</tr>';
      }

      html += '</tbody>';
      html += '</table>';
      html += '</div>';
      html += '</div>';
      html += '</div>';
    }

    // Action buttons
    html += '<div class="mt-3">';
    html += '<button type="button" class="btn btn-primary" id="reload-after-purge">';
    html += '<i class="fa fa-refresh"></i> Start New Purge';
    html += '</button>';
    html += '</div>';

    previewResults.innerHTML = html;
    const reloadBtn = document.getElementById('reload-after-purge');
    if (reloadBtn) reloadBtn.addEventListener('click', () => window.location.reload());
  }

  showErrorResults(message) {
    const previewResults = document.getElementById('preview-results');
    if (!previewResults) return;

    const html = `
            <div class="alert alert-danger">
                <h5><i class="fa fa-exclamation-circle"></i> Purge Operation Failed</h5>
                <p>The purge operation could not be completed:</p>
                <p><strong>${message}</strong></p>
                <div class="mt-3">
                    <button type="button" class="btn btn-primary" id="reload-after-error">
                        <i class="fa fa-refresh"></i> Try Again
                    </button>
                </div>
            </div>
        `;

    previewResults.innerHTML = html;
    const reloadErrBtn = document.getElementById('reload-after-error');
    if (reloadErrBtn) reloadErrBtn.addEventListener('click', () => window.location.reload());
  }

  // Simulate progress for demonstration (can be replaced with real-time updates)
  simulateProgress() {
    let progress = 0;
    const steps = [
      { progress: 10, message: 'Creating database backup...' },
      { progress: 20, message: 'Verifying backup integrity...' },
      { progress: 30, message: 'Starting data purge...' },
      { progress: 50, message: 'Purging user data...' },
      { progress: 70, message: 'Purging related data...' },
      { progress: 90, message: 'Finalizing operation...' },
      { progress: 100, message: 'Purge completed successfully!' },
    ];

    let stepIndex = 0;
    const interval = setInterval(() => {
      if (stepIndex < steps.length) {
        const step = steps[stepIndex];
        this.updateProgress(step.progress, step.message);
        stepIndex++;
      } else {
        clearInterval(interval);
      }
    }, 1000);
  }
}

// Global functions for backward compatibility
function showConfirmationDialog() {
  const purgeController = window.purgeController || new PurgeController();

  if (purgeController.confirmationModal) {
    purgeController.confirmationModal.show();
  } else {
    alert('Confirmation dialog not available. Please ensure the page has loaded completely.');
  }
}

function cancelPreview() {
  document.getElementById('preview-results').innerHTML =
    '<p class="text-muted">Select categories and date, then click Preview to see what will be affected.</p>';
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  window.purgeController = new PurgeController();

  // Audit logs auto-refresh (CSP-safe): refresh every 30s when active purges exist and no modal is open
  const auditEl = document.getElementById('audit-logs');
  if (auditEl && auditEl.dataset.hasActivePurges === '1') {
    setInterval(() => {
      if (!document.querySelector('.modal.show')) {
        window.location.reload();
      }
    }, 30000);
  }
});

// Handle rate limiting errors
function handleRateLimitError() {
  const previewResults = document.getElementById('preview-results');
  if (previewResults) {
    previewResults.innerHTML = `
            <div class="alert alert-warning">
                <i class="fa fa-clock"></i>
                <strong>Rate Limit Exceeded</strong>
                <p>Please wait a few minutes before attempting another purge operation.</p>
            </div>
        `;
  }
}

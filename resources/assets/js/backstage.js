/**
 * Backstage management JavaScript
 * Handles dashboard functionality, user search, and goodie distribution
 */

document.addEventListener('DOMContentLoaded', () => {
  // Initialize dashboard with auto-refresh
  initDashboard();

  // Initialize user search functionality
  initUserSearch();

  // Initialize goodie selection workflow
  initGoodieSelection();

  // Initialize distribution interface
  initDistributionInterface();

  // Initialize modal handlers
  initModalHandlers();

  // Initialize form handlers
  initFormHandlers();
});

/**
 * Initialize dashboard with KPI loading and auto-refresh
 */
function initDashboard() {
  const dashboardContainer = document.getElementById('backstage-dashboard');
  if (!dashboardContainer) return;

  // Load initial KPI data
  loadKPIs();

  // Set up auto-refresh every 30 seconds
  setInterval(loadKPIs, 30000);

  // Manual refresh button
  const refreshBtn = document.getElementById('refresh-kpis');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      loadKPIs(true);
    });
  }
}

/**
 * Load KPI data from API
 */
function loadKPIs(manual = false) {
  const kpiContainer = document.getElementById('kpi-container');
  const refreshBtn = document.getElementById('refresh-kpis');

  if (!kpiContainer) return;

  // Show loading state
  if (manual && refreshBtn) {
    const originalText = refreshBtn.innerHTML;
    refreshBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    refreshBtn.disabled = true;

    setTimeout(() => {
      refreshBtn.innerHTML = originalText;
      refreshBtn.disabled = false;
    }, 2000);
  }

  fetch('/api/v1/backstage/kpis', {
    method: 'GET',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(data => {
      updateKPIDisplay(data);
    })
    .catch(error => {
      console.error('Failed to load KPIs:', error);
      showAlert('error', 'Failed to load dashboard data. Please try again.');
    });
}

/**
 * Update KPI display with fresh data
 */
function updateKPIDisplay(kpiData) {
  // Update critter count
  const critterCountElement = document.getElementById('critter-count');
  if (critterCountElement && kpiData.total_critters !== undefined) {
    critterCountElement.textContent = kpiData.total_critters.toLocaleString();
  }

  // Update confirmed critter count
  const confirmedCountElement = document.getElementById('confirmed-count');
  if (confirmedCountElement && kpiData.confirmed_critters !== undefined) {
    confirmedCountElement.textContent = kpiData.confirmed_critters.toLocaleString();
  }

  // Update goodie stats
  const totalGoodiesElement = document.getElementById('total-goodies');
  if (totalGoodiesElement && kpiData.total_goodies !== undefined) {
    totalGoodiesElement.textContent = kpiData.total_goodies.toLocaleString();
  }

  const distributedGoodiesElement = document.getElementById('distributed-goodies');
  if (distributedGoodiesElement && kpiData.distributed_goodies !== undefined) {
    distributedGoodiesElement.textContent = kpiData.distributed_goodies.toLocaleString();
  }

  // Update timestamp
  const timestampElement = document.getElementById('last-updated');
  if (timestampElement) {
    timestampElement.textContent = new Date().toLocaleTimeString();
  }
}

/**
 * Initialize user search functionality
 */
function initUserSearch() {
  const searchInput = document.getElementById('user-search-input');
  const searchResults = document.getElementById('search-results');
  const searchButton = document.getElementById('user-search-button');

  if (!searchInput || !searchResults) return;

  let searchTimeout;

  // Search on input with debounce
  searchInput.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    const query = searchInput.value.trim();

    if (query.length < 2) {
      searchResults.innerHTML = '';
      return;
    }

    searchTimeout = setTimeout(() => {
      performUserSearch(query);
    }, 300);
  });

  // Search on button click
  if (searchButton) {
    searchButton.addEventListener('click', () => {
      const query = searchInput.value.trim();
      if (query.length >= 2) {
        performUserSearch(query);
      }
    });
  }

  // Search on Enter key
  searchInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      const query = searchInput.value.trim();
      if (query.length >= 2) {
        performUserSearch(query);
      }
    }
  });
}

/**
 * Perform user search via API
 */
function performUserSearch(query) {
  const searchResults = document.getElementById('search-results');
  const searchButton = document.getElementById('user-search-button');

  if (!searchResults) return;

  // Show loading state
  searchResults.innerHTML = '<div class="text-center p-3"><i class="fa fa-spinner fa-spin"></i> Searching...</div>';

  if (searchButton) {
    searchButton.disabled = true;
  }

  const searchParams = new URLSearchParams({ q: query });

  fetch(`/api/v1/backstage/users/search?${searchParams}`, {
    method: 'GET',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(data => {
      displaySearchResults(data);
    })
    .catch(error => {
      console.error('Search failed:', error);
      searchResults.innerHTML = '<div class="alert alert-danger">Search failed. Please try again.</div>';
    })
    .finally(() => {
      if (searchButton) {
        searchButton.disabled = false;
      }
    });
}

/**
 * Display user search results
 */
function displaySearchResults(results) {
  const searchResults = document.getElementById('search-results');
  if (!searchResults) return;

  if (!results.data || results.data.length === 0) {
    searchResults.innerHTML = '<div class="alert alert-info">No critters found matching your search.</div>';
    return;
  }

  let html = '<div class="list-group">';

  results.data.forEach(user => {
    html += `
      <div class="list-group-item list-group-item-action" data-user-id="${user.id}">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="mb-1">${escapeHtml(user.name)}</h6>
            ${user.personal_data?.pronouns ?
    `<small class="text-muted"><i class="fa fa-user"></i> ${escapeHtml(user.personal_data.pronouns)}</small>` :
    ''
}
          </div>
          <div>
            <button class="btn btn-primary btn-sm view-user-details" data-user-id="${user.id}">
              <i class="fa fa-eye"></i> View
            </button>
          </div>
        </div>
      </div>
    `;
  });

  html += '</div>';
  searchResults.innerHTML = html;

  // Add click handlers for result items
  searchResults.querySelectorAll('.view-user-details').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const userId = btn.getAttribute('data-user-id');
      showUserDetails(userId);
    });
  });
}

/**
 * Show detailed user information in modal
 */
function showUserDetails(userId) {
  const user = findUserInResults(userId);
  if (!user) {
    showAlert('error', 'User information not available.');
    return;
  }

  // Create modal content
  const modalContent = `
    <div class="modal fade" id="userDetailsModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Critter Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6">
                <h6>Basic Information</h6>
                <p><strong>Name:</strong> ${escapeHtml(user.name)}</p>
                ${user.personal_data?.pronouns ?
    `<p><strong>Pronouns:</strong> ${escapeHtml(user.personal_data.pronouns)}</p>` :
    ''
}
              </div>
              <div class="col-md-6">
                <h6>Status</h6>
                <p><strong>Confirmed:</strong>
                  <span class="badge ${user.state?.arrived_at ? 'bg-success' : 'bg-warning'}">
                    ${user.state?.arrived_at ? 'Yes' : 'No'}
                  </span>
                </p>
                ${user.state?.arrived_at ?
    `<p><strong>Arrived:</strong> ${new Date(user.state.arrived_at).toLocaleString()}</p>` :
    ''
}
              </div>
            </div>
            <div class="mt-3">
              <h6>Actions</h6>
              <button class="btn btn-success btn-sm select-user-for-goodies" data-user-id="${user.id}">
                <i class="fa fa-gift"></i> Select for Goodies
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  `;

  // Remove existing modal if any
  const existingModal = document.getElementById('userDetailsModal');
  if (existingModal) {
    existingModal.remove();
  }

  // Add modal to page
  document.body.insertAdjacentHTML('beforeend', modalContent);

  // Show modal
  const modal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
  modal.show();

  // Add event handler for goodie selection
  document.querySelector('.select-user-for-goodies').addEventListener('click', () => {
    modal.hide();
    selectUserForGoodies(user);
  });
}

/**
 * Find user in current search results
 */
function findUserInResults(userId) {
  // This is a simplified version - in a real app, you'd store the search results
  // For now, we'll extract from DOM or use a global variable
  const searchResults = document.getElementById('search-results');
  const userElement = searchResults.querySelector(`[data-user-id="${userId}"]`);

  if (userElement) {
    // Extract basic info from DOM
    const name = userElement.querySelector('h6').textContent;
    const email = userElement.querySelector('.fa-envelope').parentNode.textContent.replace('✉', '').trim();

    return {
      id: userId,
      name: name,
      email: email,
      state: { arrived_at: null }, // Default - would be from API in real implementation
      personal_data: {}
    };
  }

  return null;
}

/**
 * Initialize goodie selection workflow
 */
function initGoodieSelection() {
  // This would be implemented based on the specific goodie system requirements
  console.log('Goodie selection initialized');
}

/**
 * Select user for goodies distribution
 */
function selectUserForGoodies(user) {
  // Store selected user data
  const selectedUserData = {
    id: user.id,
    name: user.name,
    email: user.email
  };

  // Update UI to show selected user
  const selectedUserDisplay = document.getElementById('selected-user-display');
  if (selectedUserDisplay) {
    selectedUserDisplay.innerHTML = `
      <div class="alert alert-info">
        <strong>Selected Critter:</strong> ${escapeHtml(user.name)} (${escapeHtml(user.email)})
        <button class="btn btn-sm btn-outline-secondary float-end" id="clear-selection">
          <i class="fa fa-times"></i> Clear
        </button>
      </div>
    `;

    // Add clear handler
    document.getElementById('clear-selection').addEventListener('click', () => {
      selectedUserDisplay.innerHTML = '';
      clearGoodieSelection();
    });
  }

  // Show goodie selection interface
  showGoodieSelectionInterface(selectedUserData);
}

/**
 * Show goodie selection interface
 */
function showGoodieSelectionInterface(userData) {
  const goodieInterface = document.getElementById('goodie-selection-interface');
  if (!goodieInterface) return;

  goodieInterface.style.display = 'block';
  goodieInterface.scrollIntoView({ behavior: 'smooth' });

  // Enable goodie selection controls
  const goodieCheckboxes = goodieInterface.querySelectorAll('.goodie-checkbox');
  goodieCheckboxes.forEach(checkbox => {
    checkbox.disabled = false;
  });

  const distributeButton = document.getElementById('distribute-goodies-btn');
  if (distributeButton) {
    distributeButton.disabled = false;
  }
}

/**
 * Clear goodie selection
 */
function clearGoodieSelection() {
  const goodieInterface = document.getElementById('goodie-selection-interface');
  if (goodieInterface) {
    goodieInterface.style.display = 'none';

    // Reset checkboxes
    const goodieCheckboxes = goodieInterface.querySelectorAll('.goodie-checkbox');
    goodieCheckboxes.forEach(checkbox => {
      checkbox.checked = false;
      checkbox.disabled = true;
    });
  }
}

/**
 * Initialize distribution interface
 */
function initDistributionInterface() {
  const distributeButton = document.getElementById('distribute-goodies-btn');
  if (distributeButton) {
    distributeButton.addEventListener('click', handleGoodieDistribution);
  }
}

/**
 * Handle goodie distribution
 */
function handleGoodieDistribution() {
  const selectedGoodies = [];
  const goodieCheckboxes = document.querySelectorAll('.goodie-checkbox:checked');

  goodieCheckboxes.forEach(checkbox => {
    selectedGoodies.push({
      id: checkbox.value,
      name: checkbox.getAttribute('data-goodie-name') || 'Unknown Item'
    });
  });

  if (selectedGoodies.length === 0) {
    showAlert('warning', 'Please select at least one goodie to distribute.');
    return;
  }

  const selectedUserDisplay = document.getElementById('selected-user-display');
  if (!selectedUserDisplay.innerHTML) {
    showAlert('error', 'Please select a critter first.');
    return;
  }

  // Show confirmation dialog
  const confirmMessage = `Distribute ${selectedGoodies.length} goodie(s) to the selected critter?`;

  if (confirm(confirmMessage)) {
    // Here you would typically make an API call to record the distribution
    simulateDistribution(selectedGoodies);
  }
}

/**
 * Simulate goodie distribution (placeholder)
 */
function simulateDistribution(goodies) {
  const distributeButton = document.getElementById('distribute-goodies-btn');
  const originalText = distributeButton.innerHTML;

  // Show loading state
  distributeButton.disabled = true;
  distributeButton.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Distributing...';

  // Simulate API call delay
  setTimeout(() => {
    // Reset button
    distributeButton.innerHTML = originalText;
    distributeButton.disabled = false;

    // Show success message
    showAlert('success', `Successfully distributed ${goodies.length} goodie(s)!`);

    // Clear selections
    clearGoodieSelection();
    document.getElementById('selected-user-display').innerHTML = '';

    // Refresh KPIs to show updated counts
    loadKPIs();
  }, 2000);
}

/**
 * Initialize modal handlers
 */
function initModalHandlers() {
  // Handle modal cleanup on hide
  document.addEventListener('hidden.bs.modal', (e) => {
    if (e.target.id === 'userDetailsModal') {
      e.target.remove();
    }
  });
}

/**
 * Initialize form handlers
 */
function initFormHandlers() {
  // Handle any forms on the backstage page
  const forms = document.querySelectorAll('.backstage-form');
  forms.forEach(form => {
    form.addEventListener('submit', handleFormSubmission);
  });
}

/**
 * Handle form submissions with AJAX
 */
function handleFormSubmission(e) {
  e.preventDefault();

  const form = e.target;
  const submitButton = form.querySelector('button[type="submit"]');
  const originalButtonText = submitButton.innerHTML;

  // Show loading state
  submitButton.disabled = true;
  submitButton.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';

  const formData = new FormData(form);

  fetch(form.action, {
    method: 'POST',
    body: formData,
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
    .then(response => {
      if (response.redirected) {
        window.location.href = response.url;
        return;
      }
      return response.json();
    })
    .then(data => {
      if (data) {
        if (data.success) {
          showAlert('success', data.message || 'Operation completed successfully');
        } else {
          showAlert('error', data.message || 'Operation failed');
        }
      }
    })
    .catch(error => {
      console.error('Form submission failed:', error);
      showAlert('error', 'Form submission failed. Please try again.');
    })
    .finally(() => {
    // Reset button
      submitButton.innerHTML = originalButtonText;
      submitButton.disabled = false;
    });
}

/**
 * Show alert message
 */
function showAlert(type, message) {
  const alertContainer = document.getElementById('alert-container') || document.body;

  const alertHtml = `
    <div class="alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show" role="alert">
      ${escapeHtml(message)}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  `;

  alertContainer.insertAdjacentHTML('afterbegin', alertHtml);

  // Auto-remove after 5 seconds
  setTimeout(() => {
    const alerts = alertContainer.querySelectorAll('.alert');
    if (alerts.length > 0) {
      alerts[0].remove();
    }
  }, 5000);
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, m => map[m]);
}

/**
 * Digital ID System JavaScript
 * Handles QR code generation, auto-refresh, and user interactions using event listeners
 */

import QRCode from 'qrcode';

let digitalIdConfig = {};
let autoRefreshTimer = null;
let lastUpdateTime = null;
let isInitialized = false;

/**
 * Get configuration from meta tags
 * @returns {Object} Configuration object
 */
function getConfigFromMeta() {
  const config = {};
  
  // Get page type
  const pageTypeMeta = document.querySelector('meta[name="digital-id:page-type"]');
  config.pageType = pageTypeMeta ? pageTypeMeta.content : 'main';
  
  // Get configuration for main and certification pages
  if (config.pageType === 'main' || config.pageType === 'certification') {
    const refreshIntervalMeta = document.querySelector('meta[name="digital-id:refresh-interval"]');
    const verificationUrlMeta = document.querySelector('meta[name="digital-id:verification-url"]');
    const refreshEndpointMeta = document.querySelector('meta[name="digital-id:refresh-endpoint"]');
    const tokenMeta = document.querySelector('meta[name="digital-id:token"]');
    
    config.refreshInterval = refreshIntervalMeta ? parseInt(refreshIntervalMeta.content) : 60;
    config.verificationUrl = verificationUrlMeta ? verificationUrlMeta.content : '';
    config.refreshEndpoint = refreshEndpointMeta ? refreshEndpointMeta.content : '';
    config.token = tokenMeta ? tokenMeta.content : '';
  }
  
  // Get CSRF token (used by all page types)
  const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
  config.csrfToken = csrfTokenMeta ? csrfTokenMeta.content : '';
  
  return config;
}

/**
 * Initialize the system when DOM is loaded
 */
document.addEventListener('DOMContentLoaded', () => {
  const config = getConfigFromMeta();
  
  if (config.pageType === 'error') {
    initializeErrorPage();
  } else if (config.pageType === 'verify') {
    initializeVerifyPage();
  } else if (config.pageType === 'main' || config.pageType === 'certification') {
    initializeMainPage(config);
  } else {
    console.warn('Unknown or missing digital ID page type');
  }
});

/**
 * Initialize the main Digital ID page
 * @param {Object} config - Configuration object from backend
 */
function initializeMainPage(config) {
  if (isInitialized) return;

  digitalIdConfig = config;
  lastUpdateTime = new Date();
  isInitialized = true;

  // Set up all event listeners
  setupMainPageEventListeners();

  // Generate initial QR code
  updateQrDisplay(config.verificationUrl).catch(error => {
    console.error('Failed to generate initial QR code:', error);
  });

  // Start auto-refresh timer
  startAutoRefresh(config.refreshInterval);

  // Update last updated timestamp
  updateLastUpdatedTime();

  console.log('Digital ID system initialized', config);
}

/**
 * Initialize the error page
 */
function initializeErrorPage() {
  setupErrorPageEventListeners();
  console.log('Digital ID error page initialized');
}

/**
 * Initialize the verify page
 */
function initializeVerifyPage() {
  // Auto-refresh the verification page every 30 seconds for security
  setTimeout(function() {
    window.location.reload();
  }, 30000);
  
  // Show verification timestamp
  const now = new Date();
  console.log('Identity verified at:', now.toLocaleString());
  
  console.log('Digital ID verify page initialized');
}

/**
 * Set up event listeners for the main Digital ID page
 */
function setupMainPageEventListeners() {
  // Back button
  const backBtn = document.getElementById('back-btn');
  if (backBtn) {
    backBtn.addEventListener('click', handleBackButton);
  }

  // Refresh button
  const refreshBtn = document.getElementById('refresh-btn');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', handleManualRefresh);
  }

  // Print button
  const printBtn = document.getElementById('print-btn');
  if (printBtn) {
    printBtn.addEventListener('click', handlePrintButton);
  }

  // Page visibility changes (pause/resume refresh when tab not active)
  document.addEventListener('visibilitychange', handleVisibilityChange);

  // Handle online/offline status
  window.addEventListener('online', handleOnlineStatus);
  window.addEventListener('offline', handleOnlineStatus);

  // Handle page unload cleanup
  window.addEventListener('beforeunload', cleanupDigitalId);

  // Global error handler
  window.addEventListener('error', handleGlobalError);

  // Keyboard shortcuts
  document.addEventListener('keydown', handleKeyboardShortcuts);
}

/**
 * Set up event listeners for the error page
 */
function setupErrorPageEventListeners() {
  // Retry button
  const retryBtn = document.getElementById('retry-btn');
  if (retryBtn) {
    retryBtn.addEventListener('click', handleRetryButton);
  }

  // Go back button
  const goBackBtn = document.getElementById('go-back-btn');
  if (goBackBtn) {
    goBackBtn.addEventListener('click', handleBackButton);
  }
}

/**
 * Handle back button clicks
 */
function handleBackButton(event) {
  event.preventDefault();

  if (window.history.length > 1) {
    window.history.back();
  } else {
    // Fallback - go to home page
    window.location.href = '/';
  }
}

/**
 * Handle print button clicks
 */
function handlePrintButton(event) {
  event.preventDefault();
  window.print();
}

/**
 * Handle retry button clicks (error page)
 */
function handleRetryButton(event) {
  event.preventDefault();
  window.location.reload();
}

/**
 * Handle keyboard shortcuts
 */
function handleKeyboardShortcuts(event) {
  // Ctrl/Cmd + R: Manual refresh
  if ((event.ctrlKey || event.metaKey) && event.key === 'r' && !event.shiftKey) {
    event.preventDefault();
    if (digitalIdConfig.refreshEndpoint) {
      handleManualRefresh();
    }
    return;
  }

  // Ctrl/Cmd + P: Print
  if ((event.ctrlKey || event.metaKey) && event.key === 'p') {
    event.preventDefault();
    handlePrintButton(event);
    return;
  }

  // Escape: Go back
  if (event.key === 'Escape') {
    event.preventDefault();
    handleBackButton(event);
    return;
  }
}

/**
 * Handle manual refresh button click
 */
async function handleManualRefresh(event) {
  if (event) event.preventDefault();

  const refreshBtn = document.getElementById('refresh-btn');

  if (refreshBtn) {
    refreshBtn.disabled = true;
    refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> <span class="spinner-border spinner-border-sm ms-2"></span>';
  }

  try {
    const success = await refreshQrCode();
    if (success) {
      showStatusMessage('QR code refreshed successfully', 'success', 3000);
    } else {
      showStatusMessage('Failed to refresh QR code', 'danger');
    }
  } catch (error) {
    console.error('Manual refresh error:', error);
    showStatusMessage('Error refreshing QR code', 'danger');
  } finally {
    if (refreshBtn) {
      refreshBtn.disabled = false;
      refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Refresh';
    }
  }
}

/**
 * Refresh the QR code by calling the backend endpoint
 * @returns {boolean} Success status
 */
async function refreshQrCode() {
  if (!digitalIdConfig.refreshEndpoint) {
    console.error('No refresh endpoint configured');
    return false;
  }

  try {
    const response = await fetch(digitalIdConfig.refreshEndpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': digitalIdConfig.csrfToken
      }
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const data = await response.json();

    if (data.success) {
      // Update the QR code display
      await updateQrDisplay(data.verificationUrl);

      // Update config with new token
      digitalIdConfig.token = data.token;
      digitalIdConfig.verificationUrl = data.verificationUrl;

      // Update last updated time
      lastUpdateTime = new Date();
      updateLastUpdatedTime();

      return true;
    } else {
      throw new Error(data.error || 'Unknown error');
    }
  } catch (error) {
    console.error('Failed to refresh QR code:', error);
    return false;
  }
}

/**
 * Update the QR code display with new URL
 * @param {string} verificationUrl - The URL to encode in QR code
 */
async function updateQrDisplay(verificationUrl) {
  const qrDisplay = document.getElementById('qr-code-display');

  if (!qrDisplay) return;

  // Clear existing content and show loading
  qrDisplay.innerHTML = `
    <div class="d-flex justify-content-center align-items-center bg-light rounded h-100">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Generating QR Code...</span>
      </div>
    </div>
  `;

  try {
    // Generate QR code as canvas
    const canvas = document.createElement('canvas');
    await QRCode.toCanvas(canvas, verificationUrl, {
      width: 240,
      height: 240,
      margin: 2,
      color: {
        dark: '#000000',
        light: '#FFFFFF'
      },
      errorCorrectionLevel: 'M'
    });

    // Create container for QR code
    const qrContainer = document.createElement('div');
    qrContainer.className = 'd-flex justify-content-center align-items-center bg-white rounded h-100 flex-column p-2';
    
    // Add canvas to container
    canvas.style.width = '100%';
    canvas.style.height = 'auto';
    canvas.style.maxWidth = '240px';
    canvas.style.maxHeight = '240px';
    qrContainer.appendChild(canvas);

    // Add URL info below QR code
    const urlInfo = document.createElement('div');
    urlInfo.className = 'small text-muted mt-2 text-center';
    urlInfo.style.fontSize = '0.7rem';
    urlInfo.style.wordBreak = 'break-all';
    urlInfo.textContent = verificationUrl;
    qrContainer.appendChild(urlInfo);

    // Clear loading and add QR code
    qrDisplay.innerHTML = '';
    qrDisplay.appendChild(qrContainer);

    // Add fade-in animation
    qrContainer.classList.add('fade-in');

    console.log('QR code generated for URL:', verificationUrl);
  } catch (error) {
    console.error('Failed to generate QR code:', error);
    
    // Fallback to text display if QR generation fails
    const fallbackContainer = document.createElement('div');
    fallbackContainer.className = 'd-flex justify-content-center align-items-center bg-light rounded h-100 flex-column';
    fallbackContainer.innerHTML = `
      <div class="text-center">
        <i class="bi bi-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
        <div class="mt-2 text-muted">QR Code generation failed</div>
        <div class="small text-muted mt-2" style="font-size: 0.7rem; word-break: break-all;">
          ${escapeHtml(verificationUrl)}
        </div>
      </div>
    `;
    
    qrDisplay.innerHTML = '';
    qrDisplay.appendChild(fallbackContainer);
  }
}

/**
 * Start the auto-refresh timer
 * @param {number} intervalSeconds - Refresh interval in seconds
 */
function startAutoRefresh(intervalSeconds) {
  // Clear existing timer
  if (autoRefreshTimer) {
    clearInterval(autoRefreshTimer);
  }

  // Set new timer
  autoRefreshTimer = setInterval(async () => {
    if (!document.hidden) { // Only refresh if page is visible
      console.log('Auto-refreshing QR code...');
      const success = await refreshQrCode();
      if (success) {
        console.log('Auto-refresh successful');
      } else {
        console.warn('Auto-refresh failed');
        showStatusMessage('Auto-refresh failed - please refresh manually', 'warning', 5000);
      }
    }
  }, intervalSeconds * 1000);

  console.log(`Auto-refresh started with ${intervalSeconds}s interval`);
}

/**
 * Handle page visibility changes (tab switching)
 */
function handleVisibilityChange() {
  if (document.hidden) {
    console.log('Page hidden - auto-refresh continues in background');
  } else {
    console.log('Page visible - resuming normal operation');
    // Refresh immediately when tab becomes active
    if (digitalIdConfig.refreshEndpoint) {
      setTimeout(async () => {
        await refreshQrCode();
      }, 1000);
    }
  }
}

/**
 * Handle online/offline status changes
 */
function handleOnlineStatus() {
  const isOnline = navigator.onLine;

  if (isOnline) {
    showStatusMessage('Connection restored', 'success', 3000);
    // Refresh QR code when back online
    if (digitalIdConfig.refreshEndpoint) {
      setTimeout(async () => {
        await refreshQrCode();
      }, 1000);
    }
  } else {
    showStatusMessage('Connection lost - QR code will not update', 'warning');
  }

  console.log('Network status changed:', isOnline ? 'online' : 'offline');
}

/**
 * Handle global JavaScript errors
 */
function handleGlobalError(event) {
  console.error('Digital ID error:', event.error);
  showStatusMessage('A system error occurred', 'danger');
}

/**
 * Show status message to user
 * @param {string} message - Message to display
 * @param {string} type - Bootstrap alert type (success, danger, warning, info)
 * @param {number} duration - Auto-hide duration in ms (0 = don't auto-hide)
 */
function showStatusMessage(message, type = 'info', duration = 0) {
  const statusContainer = document.getElementById('status-messages');
  if (!statusContainer) return;

  // Create alert element
  const alertId = `alert-${  Date.now()}`;
  const alert = document.createElement('div');
  alert.id = alertId;
  alert.className = `alert alert-${type} alert-dismissible fade show`;
  alert.innerHTML = `
        ${escapeHtml(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;

  // Clear existing messages and add new one
  statusContainer.innerHTML = '';
  statusContainer.appendChild(alert);

  // Auto-hide if duration is set
  if (duration > 0) {
    setTimeout(() => {
      const alertElement = document.getElementById(alertId);
      if (alertElement && window.bootstrap) {
        const bsAlert = new bootstrap.Alert(alertElement);
        bsAlert.close();
      }
    }, duration);
  }
}

/**
 * Update the "last updated" timestamp display
 */
function updateLastUpdatedTime() {
  const lastUpdatedElement = document.getElementById('last-updated');
  if (lastUpdatedElement && lastUpdateTime) {
    lastUpdatedElement.textContent = lastUpdateTime.toLocaleTimeString();
  }
}

/**
 * Cleanup function - call when page is unloaded
 */
function cleanupDigitalId() {
  if (autoRefreshTimer) {
    clearInterval(autoRefreshTimer);
    autoRefreshTimer = null;
  }
  isInitialized = false;
}

/**
 * Utility function to escape HTML
 * @param {string} text - Text to escape
 * @returns {string} Escaped HTML
 */
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

/**
 * Utility function to format time
 * @param {Date} date - Date to format
 * @returns {string} Formatted time string
 */
function formatTime(date) {
  return date.toLocaleTimeString([], {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });
}

// Legacy functions for backward compatibility (kept for safety)
window.initDigitalId = initializeMainPage;
window.refreshQrCode = refreshQrCode;
window.updateQrDisplay = updateQrDisplay;

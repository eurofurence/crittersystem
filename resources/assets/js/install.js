// Install workflow JavaScript
// This file is NOT compiled with webpack - make sure that is located at: `/assets/special/'

// Global variables
let terminal;
let authenticated = false;
let systemStatus = null;
let csrfToken = document.getElementById('csrf').getAttribute('content');

// Initialize page
document.addEventListener('DOMContentLoaded', () => {
  // Try to check if already authenticated
  refreshStatus();

  // Handle the buttons/actions
  document.getElementById('btn-authenticate').addEventListener('click', authenticate);
  document.getElementById('btn-refreshStatus').addEventListener('click', refreshStatus);
  document.getElementById('migrate-btn').addEventListener('click', startMigration);
  document.getElementById('terminal-toggle').addEventListener('click', toggleTerminal);
  document.getElementById('btn-executeSql').addEventListener('click', executeSql);
  document.getElementById('btn-clearSqlResult').addEventListener('click', clearSqlResult);
});

// Add CSRF Token to URL
function addCSRF(url) {
  return `${url}?token=${document.getElementById('csrf').getAttribute('content')}`;
}

// Authentication
async function authenticate() {
  const password = document.getElementById('setup-password').value;
  const errorDiv = document.getElementById('auth-error');
  const spinner = document.getElementById('auth-spinner');

  if (!password) {
    showError(errorDiv, 'Please enter the setup password');
    return;
  }

  spinner.classList.remove('hidden');
  errorDiv.classList.add('hidden');

  try {
    const response = await fetch('/admin/install/authenticate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': csrfToken,
      },
      body: `password=${encodeURIComponent(password)}`,
    });

    const result = await response.json();

    if (response.ok && result.success) {
      authenticated = true;
      updateStepStatus('auth', 'success', 'Authenticated');
      enableStep('status');
      refreshStatus();
    } else {
      showError(errorDiv, result.error || 'Authentication failed');
    }
  } catch (error) {
    showError(errorDiv, `Network error: ${error.message}`);
  } finally {
    spinner.classList.add('hidden');
  }
}

// System Status
async function refreshStatus() {
  if (!authenticated) return;

  try {
    const response = await fetch('/admin/install/status');
    const status = await response.json();

    if (response.ok) {
      systemStatus = status;
      displaySystemStatus(status);
      enableStep('migration');
      enableStep('sql');

      // Check if installation is complete
      if (status.migration_files.migration_ok) {
        updateStepStatus('completion', 'success', 'Complete');
        showCompletionContent();
      }
    } else {
      console.error('Failed to fetch system status');
    }
  } catch (error) {
    console.error('Error fetching system status:', error);
  }
}

function displaySystemStatus(status) {
  // Database status
  const dbStatusDiv = document.getElementById('db-status');
  if (status.database.status === 'connected') {
    dbStatusDiv.innerHTML = `
            <div class="alert alert-success py-2">
                <i class="bi bi-check-circle"></i> Connected (${status.database.driver}: ${status.database.database})
            </div>
        `;
    updateStepStatus('status', 'success', 'Connected');
  } else {
    dbStatusDiv.innerHTML = `
            <div class="alert alert-danger py-2">
                <i class="bi bi-x-circle"></i> Error: ${status.database.error}
            </div>
        `;
    updateStepStatus('status', 'error', 'Connection Failed');
  }

  // System info
  document.getElementById('system-info').innerHTML = `
        <p class="mb-1"><strong>Hostname:</strong> ${status.hostname}</p>
        <p class="mb-1"><strong>PHP Version:</strong> ${status.php_version}</p>
        <p class="mb-0"><strong>Last Check:</strong> ${new Date(status.timestamp).toLocaleString()}</p>
    `;

  // Migration files info
  const migrationInfo = document.getElementById('migration-info');
  const files = status.migration_files;
  migrationInfo.innerHTML = `
        <p class="mb-1">${getStatusIcon(files.migrate_script)} Migration script available</p>
        <p class="mb-1">${getStatusIcon(!files.migration_running)} Not currently running</p>
        <p class="mb-1">${getStatusIcon(!files.migration_fail)} No migration failures</p>
        <p class="mb-1">${getStatusIcon(files.migration_ok)} Migration complete</p>
    `;

  showStepContent('status');
}

// Database Migration
async function startMigration() {
  const btn = document.getElementById('migrate-btn');
  const spinner = document.getElementById('migrate-spinner');
  const resultDiv = document.getElementById('migration-result');

  btn.disabled = true;
  spinner.classList.remove('hidden');
  updateStepStatus('migration', 'active', 'Running...');

  try {
    const response = await fetch('/admin/install/migrate', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
      },
    });

    const result = await response.json();

    if (result.success) {
      updateStepStatus('migration', 'success', 'Complete');
      resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <h6><i class="bi bi-check-circle"></i> Migration Successful!</h6>
                    <p class="mb-0">Database migration completed successfully.</p>
                </div>
            `;
      refreshStatus(); // Check for completion
    } else {
      updateStepStatus('migration', 'error', 'Failed');
      resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <h6><i class="bi bi-x-circle"></i> Migration Failed</h6>
                    <p class="mb-0">Exit code: ${result.exit_code}</p>
                    <pre class="mt-2 mb-0">${result.output}</pre>
                </div>
            `;
    }

    // Display output in terminal if available
    if (terminal && result.output) {
      terminal.write(result.output);
    }
  } catch (error) {
    updateStepStatus('migration', 'error', 'Error');
    resultDiv.innerHTML = `
            <div class="alert alert-danger">
                <h6><i class="bi bi-x-circle"></i> Network Error</h6>
                <p class="mb-0">${error.message}</p>
            </div>
        `;
  } finally {
    btn.disabled = false;
    spinner.classList.add('hidden');
  }
}

function toggleTerminal() {
  const container = document.getElementById('terminal-container');
  const toggle = document.getElementById('terminal-toggle');

  if (container.classList.contains('hidden')) {
    container.classList.remove('hidden');
    toggle.innerHTML = '<i class="bi bi-terminal"></i> Hide Terminal';

    // Initialize terminal if not already done
    if (!terminal) {
      terminal = new Terminal({
        theme: {
          background: '#000000',
          foreground: '#ffffff',
        },
        fontSize: 14,
        fontFamily: 'Monaco, Menlo, "Ubuntu Mono", monospace',
      });
      terminal.open(container);
      terminal.write('Terminal ready for migration output...\r\n');
    }
  } else {
    container.classList.add('hidden');
    toggle.innerHTML = '<i class="bi bi-terminal"></i> Show Terminal';
  }
}

// SQL Console
async function executeSql() {
  const query = document.getElementById('sql-query').value.trim();
  const spinner = document.getElementById('sql-spinner');
  const resultDiv = document.getElementById('sql-result');

  if (!query) {
    showError(resultDiv, 'Please enter a SQL query');
    return;
  }

  spinner.classList.remove('hidden');

  try {
    const response = await fetch('/admin/install/sql', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': csrfToken,
      },
      body: `sql=${encodeURIComponent(query)}`,
    });

    const result = await response.json();

    if (result.success) {
      resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <h6><i class="bi bi-check-circle"></i> Query executed successfully</h6>
                    <p>Rows returned: ${result.count}</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        ${formatSqlResults(result.data)}
                    </table>
                </div>
            `;
    } else {
      resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <h6><i class="bi bi-x-circle"></i> Query failed</h6>
                    <p class="mb-0">${result.error}</p>
                </div>
            `;
    }
  } catch (error) {
    resultDiv.innerHTML = `
            <div class="alert alert-danger">
                <h6><i class="bi bi-x-circle"></i> Network Error</h6>
                <p class="mb-0">${error.message}</p>
            </div>
        `;
  } finally {
    spinner.classList.add('hidden');
  }
}

function clearSqlResult() {
  document.getElementById('sql-result').innerHTML = '';
  document.getElementById('sql-query').value = '';
}

function formatSqlResults(data) {
  if (!data || data.length === 0) {
    return '<tr><td>No results</td></tr>';
  }

  const headers = Object.keys(data[0]);
  let html = '<thead><tr>';
  headers.forEach((header) => {
    html += `<th>${header}</th>`;
  });
  html += '</tr></thead><tbody>';

  data.forEach((row) => {
    html += '<tr>';
    headers.forEach((header) => {
      html += `<td>${row[header] !== null ? row[header] : '<em>NULL</em>'}</td>`;
    });
    html += '</tr>';
  });

  html += '</tbody>';
  return html;
}

// Utility functions
function updateStepStatus(stepId, status, text) {
  const number = document.getElementById(`${stepId}-number`);
  const statusBadge = document.getElementById(`${stepId}-status`);

  number.className = `step-number ${status}`;
  statusBadge.className = `badge status-badge bg-${getStatusColor(status)}`;
  statusBadge.textContent = text;
}

function enableStep(stepId) {
  showStepContent(stepId);
}

function showStepContent(stepId) {
  document.getElementById(`${stepId}-content`).classList.remove('hidden');
}

function showCompletionContent() {
  showStepContent('completion');
}

function getStatusColor(status) {
  if (status === 'success') {
    return 'success';
  } else if (status === 'error') {
    return 'danger';
  } else if (status === 'active') {
    return 'primary';
  } else {
    return 'secondary';
  }
}

function getStatusIcon(condition) {
  return condition ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>';
}

function showError(element, message) {
  element.innerHTML = `<i class="bi bi-exclamation-triangle"></i> ${message}`;
  element.classList.remove('hidden');
}

// Handle Enter key in password field
document.getElementById('setup-password').addEventListener('keypress', (e) => {
  if (e.key === 'Enter') {
    authenticate();
  }
});

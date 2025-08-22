document.addEventListener('DOMContentLoaded', function() {
    const createDumpBtn = document.getElementById('create-dump-btn');
    const dumpStatus = document.getElementById('dump-status');
    const dumpProgress = document.getElementById('dump-progress');
    const restoreForm = document.getElementById('restore-form');
    const restoreStatus = document.getElementById('restore-status');
    const validationResults = document.getElementById('validation-results');
    const validationDetails = document.getElementById('validation-details');
    const restoreConfirmation = document.getElementById('restore-confirmation');
    const executeRestoreBtn = document.getElementById('execute-restore-btn');
    const cancelRestoreBtn = document.getElementById('cancel-restore-btn');
    
    let currentUploadPath = null;

    // Create dump functionality
    createDumpBtn.addEventListener('click', function() {
        createDumpBtn.disabled = true;
        dumpStatus.className = 'alert alert-info';
        dumpStatus.textContent = 'Creating database dump...';
        dumpStatus.classList.remove('d-none');
        dumpProgress.classList.remove('d-none');

        fetch('/admin/dumpmanager/create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                dumpStatus.className = 'alert alert-success';
                dumpStatus.innerHTML = `
                    <strong>Success!</strong> ${data.message}<br>
                    <strong>Tables:</strong> ${data.tables_count}<br>
                    <strong>Size:</strong> ${(data.size / 1024 / 1024).toFixed(2)} MB<br>
                    <a href="/admin/dumpmanager/download/${data.filename}" class="btn btn-success btn-sm mt-2">
                        <i class="fa fa-download"></i> Download Dump
                    </a>
                `;
            } else {
                dumpStatus.className = 'alert alert-danger';
                dumpStatus.textContent = 'Error: ' + data.message;
            }
        })
        .catch(error => {
            dumpStatus.className = 'alert alert-danger';
            dumpStatus.textContent = 'Network error occurred';
        })
        .finally(() => {
            createDumpBtn.disabled = false;
            dumpProgress.classList.add('d-none');
        });
    });

    // Restore validation
    restoreForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(restoreForm);
        
        restoreStatus.className = 'alert alert-info';
        restoreStatus.textContent = 'Validating dump file...';
        restoreStatus.classList.remove('d-none');

        fetch('/admin/dumpmanager/upload', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                restoreStatus.className = 'alert alert-success';
                restoreStatus.textContent = 'Dump file validated successfully';
                
                currentUploadPath = data.upload_path;
                
                validationDetails.innerHTML = `
                    <p><strong>Timestamp:</strong> ${data.validation.dump_timestamp}</p>
                    <p><strong>Tables:</strong> ${data.validation.table_count}</p>
                    <p><strong>Created by:</strong> ${data.validation.metadata.created_by || 'Unknown'}</p>
                `;
                
                validationResults.classList.remove('d-none');
            } else {
                restoreStatus.className = 'alert alert-danger';
                restoreStatus.textContent = 'Error: ' + data.error;
            }
        })
        .catch(error => {
            restoreStatus.className = 'alert alert-danger';
            restoreStatus.textContent = 'Network error occurred';
        });
    });

    // Confirmation input validation
    restoreConfirmation.addEventListener('input', function() {
        executeRestoreBtn.disabled = this.value !== 'RESTORE_DATABASE';
    });

    // Execute restore
    executeRestoreBtn.addEventListener('click', function() {
        if (!currentUploadPath) return;
        
        const confirmationValue = restoreConfirmation.value;
        
        executeRestoreBtn.disabled = true;
        restoreStatus.className = 'alert alert-info';
        restoreStatus.textContent = 'Restoring database... This may take a while.';

        const csrfToken = document.querySelector('input[name="_token"]').value;

        fetch('/admin/dumpmanager/restore', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                upload_path: currentUploadPath,
                confirmation: confirmationValue
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                restoreStatus.className = 'alert alert-success';
                restoreStatus.innerHTML = `
                    <strong>Success!</strong> ${data.message}<br>
                    <strong>Restored Tables:</strong> ${data.restored_tables}<br>
                    <strong>Original Timestamp:</strong> ${data.timestamp}
                `;
                validationResults.classList.add('d-none');
                restoreForm.reset();
                currentUploadPath = null;
            } else {
                restoreStatus.className = 'alert alert-danger';
                restoreStatus.textContent = 'Error: ' + data.error;
            }
        })
        .catch(error => {
            restoreStatus.className = 'alert alert-danger';
            restoreStatus.textContent = 'Network error occurred';
        })
        .finally(() => {
            executeRestoreBtn.disabled = false;
        });
    });

    // Cancel restore
    cancelRestoreBtn.addEventListener('click', function() {
        validationResults.classList.add('d-none');
        restoreConfirmation.value = '';
        executeRestoreBtn.disabled = true;
        currentUploadPath = null;
    });
});
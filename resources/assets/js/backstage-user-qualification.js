(function() {
    'use strict';

    const form = document.getElementById('qualification-form');
    const checkboxes = document.querySelectorAll('.goodie-checkbox');
    const submitBtn = document.getElementById('submit-qualification');
    const clearBtn = document.getElementById('clear-selection');
    const summary = document.getElementById('selection-summary');

    if (form && checkboxes.length > 0) {
        function updateSelectionSummary() {
            const selected = Array.from(checkboxes).filter(cb => cb.checked);
            const count = selected.length;

            if (count === 0) {
                summary.textContent = 'No goodies selected';
                submitBtn.disabled = true;
            } else {
                const names = selected.map(cb => {
                    const card = cb.closest('.goodie-card');
                    return card.querySelector('.card-title').textContent.trim();
                });

                if (count === 1) {
                    summary.textContent = 'Selected: ' + names[0];
                } else {
                    summary.textContent = 'Selected ' + count + ' items: ' + names.slice(0, 2).join(', ') + (count > 2 ? ', ...' : '');
                }
                submitBtn.disabled = false;
            }

            // Update visual feedback
            checkboxes.forEach(cb => {
                const card = cb.closest('.goodie-card');
                if (cb.checked) {
                    card.classList.add('border-success');
                    card.classList.remove('border-light');
                } else {
                    card.classList.remove('border-success');
                    card.classList.add('border-light');
                }
            });
        }

        // Attach event listeners
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectionSummary);
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                checkboxes.forEach(cb => {
                    if (!cb.disabled) {
                        cb.checked = false;
                    }
                });
                updateSelectionSummary();
            });
        }

        // Form submission confirmation
        form.addEventListener('submit', function(e) {
            const selected = Array.from(checkboxes).filter(cb => cb.checked);

            if (selected.length === 0) {
                e.preventDefault();
                alert('Please select at least one goodie to qualify the user.');
                return;
            }

            const itemNames = selected.map(cb => {
                const card = cb.closest('.goodie-card');
                return card.querySelector('.card-title').textContent.trim();
            });

            // Get user name from page
            const userName = document.querySelector('h4')?.textContent?.trim() || 'this user';
            
            const confirmMessage = 'Are you sure you want to qualify this user for the selected goodies?\n\n' +
                                 'User: ' + userName + '\n' +
                                 'Items:\n' +
                                 itemNames.map(name => '- ' + name).join('\n');

            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        });

        // Initialize
        updateSelectionSummary();
    }

    // Goodie card click handling
    document.querySelectorAll('.goodie-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.type !== 'checkbox') {
                const checkbox = this.querySelector('.goodie-checkbox');
                if (checkbox && !checkbox.disabled) {
                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change'));
                }
            }
        });
    });
})();
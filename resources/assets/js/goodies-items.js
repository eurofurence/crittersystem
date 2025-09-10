/**
 * Goodies Items Management JavaScript
 * Handles item-specific functionality like delete confirmations
 */

document.addEventListener('DOMContentLoaded', function() {
    initDeleteConfirmations();
});

/**
 * Initialize confirmation dialogs for delete actions
 */
function initDeleteConfirmations() {
    document.querySelectorAll('[data-confirm]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
}
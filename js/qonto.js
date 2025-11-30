/**
 * Qonto Module JavaScript
 */

// Auto-refresh transaction status
function qontoAutoRefresh() {
    if (window.location.href.indexOf('doliqonto/transactions.php') > -1) {
        setTimeout(function() {
            location.reload();
        }, 60000); // Refresh every minute
    }
}

// Confirm bulk actions
function qontoConfirmBulk(action) {
    if (action === 'ignore') {
        return confirm('Are you sure you want to ignore the selected transactions?');
    }
    return true;
}

// Highlight matching invoice
function qontoHighlightMatch(element) {
    // Remove previous highlights
    document.querySelectorAll('.qonto-match-highlight').forEach(function(el) {
        el.classList.remove('qonto-match-highlight');
    });
    
    // Add highlight to selected element
    element.classList.add('qonto-match-highlight');
}

// Filter transactions by status
function qontoFilterStatus(status) {
    const url = new URL(window.location);
    url.searchParams.set('search_sync_status', status);
    window.location = url;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh for transaction pages
    qontoAutoRefresh();
    
    // Add confirmation to bulk actions
    const bulkForm = document.querySelector('form[action*="massaction"]');
    if (bulkForm) {
        bulkForm.addEventListener('submit', function(e) {
            const action = this.querySelector('select[name="massaction"]').value;
            if (!qontoConfirmBulk(action)) {
                e.preventDefault();
            }
        });
    }
});

// AJAX sync transactions
function qontoSyncTransactions() {
    const button = document.querySelector('.qonto-sync-button');
    if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="qonto-loading"></span> Syncing...';
        
        fetch('?action=sync')
            .then(response => response.text())
            .then(() => {
                location.reload();
            })
            .catch(error => {
                console.error('Sync error:', error);
                button.disabled = false;
                button.innerHTML = 'Sync Transactions';
                alert('Sync failed. Please try again.');
            });
    }
}

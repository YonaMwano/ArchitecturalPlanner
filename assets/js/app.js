/**
 * BOQ-CAD Global JS helpers
 */
const BOQ = {
    formatMoney(n) {
        return new Intl.NumberFormat('en-TZ', { style: 'decimal', maximumFractionDigits: 0 }).format(n) + ' TZS';
    },
    showToast(msg, type = 'success') {
        const colors = { success: 'bg-green-600', error: 'bg-red-600', info: 'bg-blue-600' };
        const el = document.createElement('div');
        el.className = `fixed top-4 right-4 z-50 text-white px-4 py-3 rounded-lg shadow-lg ${colors[type] || colors.info}`;
        el.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>${msg}`;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3500);
    },
    async post(url, data) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data)
        });
        return res.json();
    }
};

// Confirm delete
document.addEventListener('click', e => {
    if (e.target.closest('[data-confirm]')) {
        if (!confirm(e.target.closest('[data-confirm]').dataset.confirm)) {
            e.preventDefault();
        }
    }
});

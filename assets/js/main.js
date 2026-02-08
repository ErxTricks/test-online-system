// Auto-save functionality
document.addEventListener('DOMContentLoaded', function() {
    // Auto-uppercase token input
    const tokenInput = document.getElementById('token');
    if (tokenInput) {
        tokenInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }
    
    // Prevent multiple form submissions
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Memproses...';
                setTimeout(() => {
                    submitBtn.disabled = false;
                }, 3000);
            }
        });
    });
    
    // Warn user before leaving test page
    if (window.location.pathname.includes('test.php')) {
        window.addEventListener('beforeunload', function(e) {
            e.preventDefault();
            e.returnValue = 'Jawaban Anda akan tersimpan. Yakin ingin keluar?';
        });
    }
});

// Smooth scroll to unanswered questions
function scrollToUnanswered() {
    const unanswered = document.querySelector('.question-card:not(:has(input:checked))');
    if (unanswered) {
        unanswered.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}
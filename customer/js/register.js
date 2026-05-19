// Password Toggle
document.getElementById('togglePassword')?.addEventListener('click', function () {
    const input = document.getElementById('password');
    const icon = this.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
    // Jangan focus() agar layout tidak shift saat validasi aktif
});

// Password Strength
document.getElementById('password')?.addEventListener('input', function () {
    const val = this.value;
    const fill = document.getElementById('strengthFill');
    const text = document.getElementById('strengthText');

    let strength = 0;
    if (val.length >= 6) strength++;
    if (val.length >= 10) strength++;
    if (/[a-z]/.test(val) && /[A-Z]/.test(val)) strength++;
    if (/\d/.test(val)) strength++;
    if (/[^a-zA-Z0-9]/.test(val)) strength++;

    fill.className = 'strength-fill';
    text.className = 'strength-text';

    if (val.length === 0) {
        text.textContent = 'Gunakan kombinasi huruf & angka';
        return;
    }

    if (strength <= 2) {
        fill.classList.add('weak');
        text.classList.add('weak');
        text.textContent = 'Lemah';
    } else if (strength <= 4) {
        fill.classList.add('medium');
        text.classList.add('medium');
        text.textContent = 'Sedang';
    } else {
        fill.classList.add('strong');
        text.classList.add('strong');
        text.textContent = 'Kuat ✓';
    }
});

// Form Validation
document.querySelector('.auth-form')?.addEventListener('submit', function (e) {
    const terms = document.getElementById('terms');
    if (!terms.checked) {
        e.preventDefault();
        alert('Anda harus menyetujui Syarat & Ketentuan');
        terms.focus();
        return;
    }
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});

// Auto-focus first empty field
document.addEventListener('DOMContentLoaded', function () {
    const firstEmpty = document.querySelector(
        '.auth-form input:not([value]):not([type="checkbox"]):not([type="submit"]):not([type="hidden"])'
    );
    if (firstEmpty) setTimeout(() => firstEmpty.focus(), 100);
});

// Real-time validation feedback
document.querySelectorAll('.form-control').forEach(input => {
    input.addEventListener('blur', function () {
        if (this.value.trim() !== '') {
            this.classList.remove('is-invalid');
        }
    });
});
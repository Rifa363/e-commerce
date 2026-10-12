const API_BASE = 'api';

// Tab switching
document.querySelectorAll('.auth-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        const target = tab.dataset.tab;
        document.getElementById('loginForm').style.display = target === 'login' ? 'block' : 'none';
        document.getElementById('registerForm').style.display = target === 'register' ? 'block' : 'none';
        clearAlerts();
    });
});

function showAlert(type, message) {
    clearAlerts();
    const el = document.getElementById(type === 'error' ? 'authError' : 'authSuccess');
    el.textContent = message;
    el.style.display = 'block';
}

function clearAlerts() {
    document.getElementById('authError').style.display = 'none';
    document.getElementById('authSuccess').style.display = 'none';
}

function getPostLoginRedirect(defaultRedirect) {
    const next = new URLSearchParams(window.location.search).get('next');
    if (next === 'checkout') return 'index.html?checkout=1';
    if (next === 'custom-cake.html' || next === 'orders.html' || next === 'chat.html') return next;
    return defaultRedirect;
}

// LOGIN
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = {
        email: formData.get('email'),
        password: formData.get('password')
    };

    try {
        const res = await fetch(`${API_BASE}/auth.php?action=login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(data)
        });
        const result = await res.json();

        if (result.success) {
            showAlert('success', 'Login berhasil! Mengalihkan...');
            localStorage.setItem('user', JSON.stringify(result.user));
            setTimeout(() => {
                window.location.href = result.user.role === 'customer'
                    ? getPostLoginRedirect(result.redirect)
                    : result.redirect;
            }, 800);
        } else {
            showAlert('error', result.message);
        }
    } catch (err) {
        showAlert('error', 'Koneksi gagal: ' + err.message);
    }
});

// REGISTER
document.getElementById('registerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = {
        name: formData.get('name'),
        email: formData.get('email'),
        phone: formData.get('phone'),
        password: formData.get('password')
    };

    try {
        const res = await fetch(`${API_BASE}/auth.php?action=register`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(data)
        });
        const result = await res.json();

        if (result.success) {
            showAlert('success', 'Registrasi berhasil! Mengalihkan...');
            localStorage.setItem('user', JSON.stringify(result.user));
            setTimeout(() => {
                window.location.href = getPostLoginRedirect('index.html');
            }, 800);
        } else {
            showAlert('error', result.message);
        }
    } catch (err) {
        showAlert('error', 'Koneksi gagal: ' + err.message);
    }
});

// Check if already logged in
(async () => {
    try {
        const res = await fetch(`${API_BASE}/auth.php?action=check`, { credentials: 'include' });
        const result = await res.json();
        if (result.logged_in) {
            localStorage.setItem('user', JSON.stringify(result.user));
        }
    } catch (e) {}
})();
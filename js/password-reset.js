const passwordResetApi = 'api/password_reset.php';
const requestForm = document.getElementById('requestResetForm');
const completeForm = document.getElementById('completeResetForm');
const resetEmail = document.getElementById('resetEmail');
const resetChannel = document.getElementById('resetChannel');
const channelSetupMessage = document.getElementById('channelSetupMessage');
const resetMessage = document.getElementById('resetMessage');

function showResetMessage(message, type = '') {
    resetMessage.textContent = message;
    resetMessage.className = `reset-message ${type}`;
}

async function loadResetChannels() {
    const response = await fetch(passwordResetApi);
    const result = await response.json();
    if (!response.ok || !result.success) {
        throw new Error(result.message || 'Metode verifikasi tidak dapat dimuat.');
    }

    const emailOption = resetChannel.querySelector('option[value="email"]');
    const whatsappOption = resetChannel.querySelector('option[value="whatsapp"]');
    emailOption.disabled = !result.channels.email;
    whatsappOption.disabled = !result.channels.whatsapp;

    if (result.channels.email) {
        resetChannel.value = 'email';
        channelSetupMessage.textContent = 'Pilih kanal yang masih dapat Anda akses.';
    } else if (result.channels.whatsapp) {
        resetChannel.value = 'whatsapp';
        channelSetupMessage.textContent = 'Pilih kanal yang masih dapat Anda akses.';
    } else {
        resetChannel.disabled = true;
        document.getElementById('requestResetButton').disabled = true;
        channelSetupMessage.textContent = 'Pengiriman kode belum tersedia. Admin perlu mengatur SMTP email atau gateway WhatsApp terlebih dahulu.';
        channelSetupMessage.classList.add('error');
    }
}

requestForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = document.getElementById('requestResetButton');
    button.disabled = true;
    showResetMessage('Memproses permintaan...');

    try {
        const response = await fetch(passwordResetApi, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'request',
                email: resetEmail.value.trim(),
                channel: resetChannel.value
            })
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Permintaan kode verifikasi gagal.');
        }
        completeForm.style.display = 'block';
        showResetMessage(result.message, 'success');
    } catch (error) {
        showResetMessage(error.message, 'error');
    } finally {
        button.disabled = false;
    }
});

completeForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = document.getElementById('completeResetButton');
    button.disabled = true;
    showResetMessage('Memverifikasi kode...');

    try {
        const response = await fetch(passwordResetApi, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'reset',
                email: resetEmail.value.trim(),
                channel: resetChannel.value,
                code: document.getElementById('resetCode').value.trim(),
                password: document.getElementById('newPassword').value
            })
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Password tidak dapat diubah.');
        }
        requestForm.style.display = 'none';
        completeForm.style.display = 'none';
        showResetMessage(`${result.message} Mengalihkan ke halaman login...`, 'success');
        window.setTimeout(() => {
            window.location.href = 'login.html';
        }, 1800);
    } catch (error) {
        showResetMessage(error.message, 'error');
    } finally {
        button.disabled = false;
    }
});

loadResetChannels().catch((error) => {
    document.getElementById('requestResetButton').disabled = true;
    channelSetupMessage.textContent = error.message;
    channelSetupMessage.classList.add('error');
});

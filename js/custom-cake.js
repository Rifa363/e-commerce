const CUSTOM_CAKE_API = 'api/customer/custom_cakes.php';

function customCakeText(tag, className, text) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    element.textContent = text;
    return element;
}

function customCakeStatus(status) {
    return {
        pending: 'Menunggu peninjauan',
        quoted: 'Penawaran dikirim',
        confirmed: 'Dikonfirmasi',
        in_production: 'Sedang dibuat',
        ready: 'Siap',
        completed: 'Selesai',
        cancelled: 'Dibatalkan'
    }[status] || status;
}

function renderCustomCake(order) {
    const card = document.createElement('article');
    card.className = 'customer-order-card';
    const head = document.createElement('div');
    head.className = 'customer-order-header';
    const label = document.createElement('div');
    label.append(
        customCakeText('h3', '', `#${order.order_code}`),
        customCakeText('p', 'customer-order-date', new Date(order.created_at.replace(' ', 'T')).toLocaleString('id-ID'))
    );
    const status = customCakeText('span', 'order-status', customCakeStatus(order.status));
    head.append(label, status);

    const detail = document.createElement('details');
    detail.className = 'customer-order-details';
    detail.append(customCakeText('summary', '', `${order.cake_type} · ${order.servings} porsi · ${order.flavor}`));
    const body = document.createElement('div');
    body.className = 'custom-cake-history-detail';
    const entries = [
        ['Acara', new Date(order.event_at.replace(' ', 'T')).toLocaleString('id-ID')],
        ['Isian', order.filling || '-'],
        ['Frosting', order.frosting || '-'],
        ['Tulisan kue', order.cake_message || '-'],
        ['Desain', order.design_description],
        ['Alergi', order.allergies || '-'],
        ['Anggaran', order.budget ? new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(order.budget)) : '-']
    ];
    entries.forEach(([name, value]) => {
        const row = document.createElement('p');
        row.append(customCakeText('strong', '', `${name}: `), document.createTextNode(value));
        body.append(row);
    });
    if (order.admin_quote !== null) {
        body.append(customCakeText('p', '', `Penawaran harga: ${new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(order.admin_quote))}`));
    }
    if (order.admin_notes) body.append(customCakeText('p', '', `Catatan RiRa Cakes: ${order.admin_notes}`));
    detail.append(body);
    card.append(head, detail);
    return card;
}

async function loadCustomCakeOrders() {
    const message = document.getElementById('customCakeHistoryMessage');
    try {
        const response = await fetch(CUSTOM_CAKE_API, { credentials: 'include' });
        const result = await response.json();
        if (response.status === 401) {
            window.location.href = 'login.html';
            return false;
        }
        if (!response.ok || !result.success) throw new Error(result.message || 'Pre-order tidak dapat dimuat.');
        document.getElementById('customCakeHistoryList').replaceChildren(...result.data.map(renderCustomCake));
        message.textContent = result.data.length ? 'Status pre-order Anda.' : 'Anda belum mengirim pre-order custom cake.';
        return true;
    } catch (error) {
        message.textContent = error.message;
        message.classList.add('orders-message-error');
        return false;
    }
}

document.getElementById('customCakeForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const submit = document.getElementById('customCakeSubmit');
    const message = document.getElementById('customCakeMessage');
    const data = Object.fromEntries(new FormData(form).entries());
    data.servings = Number(data.servings);
    data.budget = data.budget === '' ? null : Number(data.budget);
    submit.disabled = true;
    message.textContent = 'Mengirim permintaan...';
    message.classList.remove('orders-message-error');
    try {
        const response = await fetch(CUSTOM_CAKE_API, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (response.status === 401) {
            window.location.href = 'login.html';
            return;
        }
        if (!response.ok || !result.success) throw new Error(result.message || 'Pre-order gagal dikirim.');
        message.textContent = `${result.message} Kode: ${result.order_code}. Email ${result.customer_email_sent ? 'terkirim' : 'belum terkirim (periksa konfigurasi email)'}.`;
        const chatLink = document.getElementById('customCakeChatLink');
        chatLink.href = 'chat.html';
        chatLink.style.display = 'inline-flex';
        form.reset();
        await loadCustomCakeOrders();
    } catch (error) {
        message.textContent = error.message;
        message.classList.add('orders-message-error');
    } finally {
        submit.disabled = false;
    }
});

(async () => {
    const response = await fetch('api/auth.php?action=check', { credentials: 'include' });
    const auth = await response.json();
    if (!auth.logged_in || auth.user.role !== 'customer') {
        window.location.href = 'login.html?next=custom-cake.html';
        return;
    }

    const minimumDate = new Date(Date.now() + 48 * 60 * 60 * 1000);
    minimumDate.setMinutes(minimumDate.getMinutes() - minimumDate.getTimezoneOffset());
    document.getElementById('eventAt').min = minimumDate.toISOString().slice(0, 16);
    await loadCustomCakeOrders();
})().catch((error) => {
    const message = document.getElementById('customCakeHistoryMessage');
    message.textContent = error.message;
    message.classList.add('orders-message-error');
});

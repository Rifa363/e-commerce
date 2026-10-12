const CUSTOMER_ORDERS_API = 'api/customer/orders.php';

const formatOrderCurrency = (amount) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
}).format(Number(amount) || 0);

function createOrderText(tag, className, text) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    element.textContent = text;
    return element;
}

function renderOrder(order) {
    const card = document.createElement('article');
    card.className = 'customer-order-card';

    const header = document.createElement('div');
    header.className = 'customer-order-header';
    const identity = document.createElement('div');
    identity.append(
        createOrderText('h2', '', `Pesanan #${order.order_code}`),
        createOrderText('p', 'customer-order-date', new Date(order.created_at.replace(' ', 'T')).toLocaleString('id-ID'))
    );
    const status = createOrderText('span', `order-status order-status-${order.status}`, order.status_display);
    header.append(identity, status);

    const summary = document.createElement('div');
    summary.className = 'customer-order-summary';
    summary.append(
        createOrderText('span', '', `${order.item_count} item`),
        createOrderText('strong', '', formatOrderCurrency(order.total_amount))
    );

    const details = document.createElement('details');
    details.className = 'customer-order-details';
    details.append(createOrderText('summary', '', 'Lihat detail transaksi'));

    const detailBody = document.createElement('div');
    detailBody.className = 'customer-order-detail-body';
    const items = document.createElement('div');
    items.className = 'customer-order-items';
    order.items.forEach((item) => {
        const row = document.createElement('div');
        row.className = 'customer-order-item';
        const name = item.product_name || 'Produk tidak tersedia';
        row.append(
            createOrderText('span', '', `${name} × ${item.quantity}`),
            createOrderText('strong', '', formatOrderCurrency(Number(item.price) * Number(item.quantity)))
        );
        items.append(row);
    });

    const info = document.createElement('div');
    info.className = 'customer-order-info';
    [
        ['Pembayaran', order.payment_method_display],
        ['Pengiriman', order.shipping_method_display],
        ['Subtotal', formatOrderCurrency(order.subtotal)],
        [`Diskon${order.voucher_code ? ` (${order.voucher_code})` : ''}`, `-${formatOrderCurrency(order.discount)}`],
        ['Ongkir', formatOrderCurrency(order.shipping_cost)],
        ['Alamat', order.address]
    ].forEach(([label, value]) => {
        const row = document.createElement('p');
        row.append(createOrderText('strong', '', `${label}: `), document.createTextNode(value));
        info.append(row);
    });

    if (order.payment_details) {
        try {
            const paymentDetails = JSON.parse(order.payment_details);
            if (order.payment_method === 'transfer' && paymentDetails.bank) {
                info.append(createOrderText('p', '', `Rekening: ${paymentDetails.bank} ${paymentDetails.account_number || ''} a.n. ${paymentDetails.account_holder || ''}`));
            }
            if (paymentDetails.deadline) {
                info.append(createOrderText('p', '', `Batas pembayaran: ${new Date(paymentDetails.deadline.replace(' ', 'T')).toLocaleString('id-ID')}`));
            }
        } catch (error) {
            console.error('Invalid payment details for order:', order.order_code, error);
        }
    }

    if (order.has_payment_proof) {
        const proofLink = document.createElement('a');
        proofLink.href = `api/customer/payment_proof.php?order_id=${encodeURIComponent(order.id)}`;
        proofLink.target = '_blank';
        proofLink.rel = 'noopener';
        proofLink.className = 'order-proof-link';
        proofLink.textContent = 'Lihat bukti transfer';
        info.append(proofLink);
    }

    detailBody.append(items, info);
    details.append(detailBody);
    card.append(header, summary, details);
    return card;
}

async function loadCustomerOrders() {
    const message = document.getElementById('ordersMessage');
    const list = document.getElementById('ordersList');
    try {
        const response = await fetch(CUSTOMER_ORDERS_API, { credentials: 'include' });
        const result = await response.json();
        if (response.status === 401) {
            window.location.href = 'login.html?next=orders.html';
            return;
        }
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Riwayat transaksi tidak dapat dimuat.');
        }

        list.replaceChildren(...result.data.map(renderOrder));
        message.textContent = result.data.length
            ? 'Riwayat transaksi akun Anda.'
            : 'Belum ada transaksi pada akun ini. Pesanan yang dibuat tanpa login tidak muncul di riwayat akun.';
        message.classList.toggle('orders-message-empty', result.data.length === 0);
    } catch (error) {
        message.textContent = error.message;
        message.classList.add('orders-message-error');
    }
}

loadCustomerOrders();

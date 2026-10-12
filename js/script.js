// ============================================
// RiRa Cakes - Frontend Logic
// ============================================

// ============================================
// AUTH CHECK - Update UI berdasarkan login status
// ============================================
async function checkAuth() {
    try {
        const res = await fetch(`${API_BASE}/auth.php?action=check`, { credentials: 'include' });
        const result = await res.json();
        const profileBtn = document.getElementById('profileBtn');
        const historyLink = document.getElementById('ordersHistoryLink');
        
        if (result.logged_in) {
            localStorage.setItem('user', JSON.stringify(result.user));
            if (historyLink) historyLink.style.display = result.user.role === 'customer' ? 'inline-flex' : 'none';
            profileBtn.innerHTML = `<i class="fas fa-user-circle"></i> <span style="font-size:0.85rem;margin-left:5px;">${result.user.name}</span>`;
            profileBtn.onclick = () => {
                if (confirm(`Logout sebagai ${result.user.name}?`)) {
                    logout();
                }
            };
        } else {
            if (historyLink) historyLink.style.display = 'none';
            profileBtn.innerHTML = `<i class="fas fa-user"></i> <span style="font-size:0.85rem;margin-left:5px;">Masuk</span>`;
            profileBtn.onclick = () => window.location.href = 'login.html';
        }
    } catch (e) {
        console.error('Auth check error:', e);
    }
}

async function logout() {
    await fetch(`${API_BASE}/auth.php?action=logout`, { credentials: 'include' });
    localStorage.removeItem('user');
    window.location.reload();
}

const API_BASE = 'api'; // Ganti dengan URL API jika deploy terpisah
let cart = [];
try {
    const savedCart = JSON.parse(localStorage.getItem('cart') || '[]');
    if (Array.isArray(savedCart)) {
        cart = savedCart.filter((item) =>
            item && typeof item === 'object'
            && Number.isInteger(Number(item.id)) && Number(item.id) > 0
            && Number.isFinite(Number(item.price)) && Number(item.price) >= 0
            && Number.isInteger(Number(item.qty)) && Number(item.qty) > 0
            && typeof item.name === 'string'
        ).map((item) => ({
            id: Number(item.id),
            name: item.name,
            price: Number(item.price),
            image: typeof item.image === 'string' ? item.image : '',
            qty: Number(item.qty)
        }));
    }
} catch (error) {
    console.error('Keranjang tersimpan tidak dapat dibaca:', error);
}
let allProducts = [];
let appliedVoucher = null;

const formatRupiah = (num) => new Intl.NumberFormat('id-ID', { 
    style: 'currency', currency: 'IDR', minimumFractionDigits: 0 
}).format(num);

async function isCustomerLoggedIn() {
    const response = await fetch(`${API_BASE}/auth.php?action=check`, { credentials: 'include' });
    if (!response.ok) throw new Error('Status login tidak dapat diperiksa.');
    const result = await response.json();
    return result.logged_in === true && result.user?.role === 'customer';
}

function redirectToCustomerLogin(next) {
    window.location.href = `login.html?next=${encodeURIComponent(next)}`;
}

// ============================================
// 1. LOAD PRODUCTS FROM DATABASE (via API)
// ============================================
async function loadProducts(category = 'all') {
    try {
        const response = await fetch(`${API_BASE}/get_products.php?category=${category}`);
        const result = await response.json();
        
        if (result.success) {
            allProducts = result.data;
            renderProducts(allProducts);
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        console.error('Error loading products:', error);
        document.getElementById('productsGrid').innerHTML = 
            '<p class="loading">Gagal memuat produk. Pastikan server PHP berjalan.</p>';
    }
}

function renderProducts(products) {
    const grid = document.getElementById('productsGrid');
    if (products.length === 0) {
        grid.innerHTML = '<p class="loading">Tidak ada produk ditemukan.</p>';
        return;
    }

    const cards = products.map((product) => {
        const card = document.createElement('article');
        card.className = 'product-card';
        const imageContainer = document.createElement('div');
        imageContainer.className = 'product-img';
        if (product.label) {
            const label = document.createElement('span');
            label.className = 'product-label';
            label.textContent = product.label;
            imageContainer.append(label);
        }
        const image = document.createElement('img');
        image.alt = product.name;
        const imageUrl = String(product.image_url || '');
        image.src = /^https?:\/\//i.test(imageUrl) || (/^[\w./-]+$/.test(imageUrl) && !imageUrl.startsWith('..'))
            ? imageUrl
            : 'https://via.placeholder.com/400x400?text=No+Image';
        image.addEventListener('error', () => {
            image.src = 'https://via.placeholder.com/400x400?text=No+Image';
        }, { once: true });
        imageContainer.append(image);

        const info = document.createElement('div');
        info.className = 'product-info';
        const title = document.createElement('h3');
        title.className = 'product-title';
        title.textContent = product.name;
        const description = document.createElement('p');
        description.className = 'product-description';
        description.textContent = String(product.description || 'Deskripsi produk belum tersedia.');
        const meta = document.createElement('div');
        meta.className = 'product-meta';
        const star = document.createElement('i');
        star.className = 'fas fa-star';
        meta.append(star, document.createTextNode(
            ` ${Number(product.display_rating ?? product.rating).toFixed(1)} (${Number(product.review_count || 0)} ulasan) | Terjual ${Number(product.sold_count) || 0}`
        ));
        const price = document.createElement('div');
        price.className = 'product-price';
        price.textContent = formatRupiah(product.price);

        const addButton = document.createElement('button');
        addButton.className = 'btn-add-cart';
        const plus = document.createElement('i');
        plus.className = 'fas fa-plus';
        addButton.append(plus, document.createTextNode(' Tambah ke Keranjang'));
        addButton.addEventListener('click', () => addToCart(
            Number(product.id),
            product.name,
            Number(product.price),
            imageUrl
        ));

        const reviewButton = document.createElement('button');
        reviewButton.className = 'btn-review-product';
        const reviewIcon = document.createElement('i');
        reviewIcon.className = 'fas fa-star';
        reviewButton.append(reviewIcon, document.createTextNode(' Rating & Review'));
        reviewButton.addEventListener('click', () => openReviewModal(Number(product.id)));

        info.append(title, description, meta, price, addButton, reviewButton);
        card.append(imageContainer, info);
        return card;
    });
    grid.replaceChildren(...cards);
}

// ============================================
// 2. FILTER TABS
// ============================================
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        loadProducts(btn.dataset.filter);
    });
});

// ============================================
// 3. SEARCH FUNCTIONALITY
// ============================================
document.getElementById('searchInput').addEventListener('input', (e) => {
    const keyword = e.target.value.toLowerCase();
    const filtered = allProducts.filter(p => p.name.toLowerCase().includes(keyword));
    renderProducts(filtered);
});

// ============================================
// 4. CART LOGIC
// ============================================
function addToCart(id, name, price, image) {
    const existing = cart.find(item => item.id === id);
    if (existing) {
        existing.qty++;
    } else {
        cart.push({ id, name, price, image, qty: 1 });
    }
    updateCartUI();
    openCart();
}

function saveCart() {
    try {
        localStorage.setItem('cart', JSON.stringify(cart));
    } catch (error) {
        console.error('Keranjang tidak dapat disimpan:', error);
    }
}

function updateCartUI() {
    saveCart();
    const totalItems = cart.reduce((sum, item) => sum + item.qty, 0);
    document.getElementById('cartCount').innerText = totalItems;
    
    const cartItemsEl = document.getElementById('cartItems');
    if (cart.length === 0) {
        cartItemsEl.innerHTML = '<p style="text-align:center;color:#999;margin-top:50px;">Keranjang kosong</p>';
    } else {
        cartItemsEl.innerHTML = cart.map(item => `
            <div class="cart-item">
                <img src="${item.image}" class="cart-item-img" alt="${item.name}">
                <div class="cart-item-info">
                    <div class="cart-item-title">${item.name}</div>
                    <div class="cart-item-price">${formatRupiah(item.price)}</div>
                    <div class="qty-control">
                        <button class="qty-btn" onclick="changeQty(${item.id}, -1)">-</button>
                        <span>${item.qty}</span>
                        <button class="qty-btn" onclick="changeQty(${item.id}, 1)">+</button>
                    </div>
                    <span class="remove-item" onclick="removeItem(${item.id})">Hapus</span>
                </div>
            </div>
        `).join('');
    }

    const total = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    document.getElementById('cartTotal').innerText = formatRupiah(total);
}

function changeQty(id, change) {
    const item = cart.find(i => i.id === id);
    item.qty += change;
    if (item.qty <= 0) removeItem(id);
    else updateCartUI();
}

function removeItem(id) {
    cart = cart.filter(i => i.id !== id);
    updateCartUI();
}

// ============================================
// 5. MODAL & SIDEBAR
// ============================================
const overlay = document.getElementById('overlay');
const cartSidebar = document.getElementById('cartSidebar');
const checkoutModal = document.getElementById('checkoutModal');
const successModal = document.getElementById('successModal');
const paymentModal = document.getElementById('paymentModal');
const proofModal = document.getElementById('proofModal');
const reviewModal = document.getElementById('reviewModal');

function openCart() {
    overlay.classList.add('active');
    cartSidebar.classList.add('active');
}
function closeCart() {
    overlay.classList.remove('active');
    cartSidebar.classList.remove('active');
}
function closeAllModals() {
    overlay.classList.remove('active');
    cartSidebar.classList.remove('active');
    checkoutModal.classList.remove('active');
    successModal.classList.remove('active');
    paymentModal.classList.remove('active');
    proofModal.classList.remove('active');
    reviewModal.classList.remove('active');
}

document.getElementById('cartIcon').addEventListener('click', openCart);
document.getElementById('closeCart').addEventListener('click', closeCart);
overlay.addEventListener('click', closeAllModals);

document.getElementById('btnCheckout').addEventListener('click', () => {
    if (cart.length === 0) return alert('Keranjang masih kosong!');
    isCustomerLoggedIn()
        .then((loggedIn) => {
            if (!loggedIn) {
                redirectToCustomerLogin('checkout');
                return;
            }
            closeCart();
            setTimeout(() => {
                checkoutModal.classList.add('active');
                updateSummary();
            }, 300);
        })
        .catch((error) => {
            console.error('Pemeriksaan akun untuk checkout gagal:', error);
            alert('Tidak dapat memeriksa status login. Silakan coba lagi.');
        });
});

document.getElementById('checkoutCancel').addEventListener('click', () => {
    checkoutModal.classList.remove('active');
    overlay.classList.remove('active');
    document.getElementById('catalog').scrollIntoView({ behavior: 'smooth' });
});

// ============================================
// 6. CHECKOUT SUMMARY
// ============================================
const shippingMap = { instant: 25000, sameday: 18000, reguler: 12000 };

function updateSummary() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const shipping = shippingMap[document.getElementById('coShipping').value] || 0;
    if (appliedVoucher && appliedVoucher.subtotal !== subtotal) {
        appliedVoucher = null;
        document.getElementById('voucherMessage').textContent = 'Keranjang berubah. Periksa kembali kode promo.';
        document.getElementById('voucherMessage').style.color = '#b00020';
    }
    const discount = appliedVoucher ? appliedVoucher.discount : 0;
    const total = subtotal - discount + shipping;
    
    document.getElementById('summarySubtotal').innerText = formatRupiah(subtotal);
    document.getElementById('summaryShipping').innerText = formatRupiah(shipping);
    document.getElementById('summaryDiscountRow').style.display = discount ? 'flex' : 'none';
    document.getElementById('summaryDiscount').innerText = `-${formatRupiah(discount)}`;
    document.getElementById('summaryTotal').innerText = formatRupiah(total);
}

document.getElementById('coShipping').addEventListener('change', updateSummary);
document.getElementById('coShipping').addEventListener('change', () => {
    if (appliedVoucher) document.getElementById('applyVoucherBtn').click();
});

document.getElementById('applyVoucherBtn').addEventListener('click', async () => {
    const codeInput = document.getElementById('voucherCode');
    const message = document.getElementById('voucherMessage');
    const button = document.getElementById('applyVoucherBtn');
    const code = codeInput.value.trim().toUpperCase();
    if (!code) {
        appliedVoucher = null;
        message.textContent = 'Masukkan kode promo terlebih dahulu.';
        message.style.color = '#b00020';
        updateSummary();
        return;
    }

    button.disabled = true;
    message.textContent = 'Memeriksa kode promo...';
    message.style.color = '';
    try {
        const response = await fetch(`${API_BASE}/validate_voucher.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                code,
                shipping_method: document.getElementById('coShipping').value,
                items: cart.map(({ id, qty }) => ({ id, qty }))
            })
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Kode promo tidak dapat digunakan.');
        }
        appliedVoucher = { code: result.code, discount: Number(result.discount), subtotal: Number(result.subtotal) };
        codeInput.value = result.code;
        message.textContent = `Kode ${result.code} berhasil digunakan. Hemat ${formatRupiah(result.discount)}.`;
        message.style.color = '#26733b';
        updateSummary();
    } catch (error) {
        appliedVoucher = null;
        message.textContent = error.message;
        message.style.color = '#b00020';
        updateSummary();
    } finally {
        button.disabled = false;
    }
});

document.getElementById('voucherCode').addEventListener('input', () => {
    if (appliedVoucher && document.getElementById('voucherCode').value.trim().toUpperCase() !== appliedVoucher.code) {
        appliedVoucher = null;
        document.getElementById('voucherMessage').textContent = '';
        updateSummary();
    }
});

let activeReviewProductId = null;

async function openReviewModal(productId) {
    activeReviewProductId = productId;
    const list = document.getElementById('publicReviews');
    const form = document.getElementById('reviewForm');
    const eligibilityMessage = document.getElementById('reviewEligibilityMessage');
    form.style.display = 'none';
    list.textContent = 'Memuat ulasan...';
    eligibilityMessage.textContent = '';
    document.getElementById('reviewText').value = '';
    document.getElementById('reviewSubmitMessage').textContent = '';
    reviewModal.classList.add('active');

    try {
        const response = await fetch(`${API_BASE}/reviews.php?product_id=${encodeURIComponent(productId)}`, { credentials: 'include' });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Ulasan tidak dapat dimuat.');
        document.getElementById('reviewProductTitle').textContent = `Rating & Review: ${result.product.name}`;
        const summary = document.createElement('p');
        summary.className = 'review-summary';
        summary.textContent = result.review_count
            ? `Rating ${Number(result.average_rating).toFixed(1)} dari ${result.review_count} ulasan`
            : 'Belum ada ulasan untuk produk ini.';
        list.replaceChildren(summary);

        result.reviews.forEach((review) => {
            const card = document.createElement('article');
            card.className = 'public-review-card';
            const heading = document.createElement('div');
            heading.className = 'public-review-heading';
            const stars = document.createElement('span');
            stars.className = 'review-stars';
            stars.textContent = `${'★'.repeat(Number(review.rating))}${'☆'.repeat(5 - Number(review.rating))}`;
            const name = document.createElement('strong');
            name.textContent = review.customer_name;
            heading.append(name, stars);
            const text = document.createElement('p');
            text.textContent = review.review;
            const date = document.createElement('small');
            date.textContent = new Date(review.created_at.replace(' ', 'T')).toLocaleDateString('id-ID');
            card.append(heading, text, date);
            list.append(card);
        });

        if (result.eligibility.can_review) {
            form.style.display = 'block';
            eligibilityMessage.textContent = 'Anda dapat mengulas produk ini karena pesanan Anda sudah selesai. Ulasan akan ditampilkan setelah moderasi.';
        } else if (!result.eligibility.logged_in) {
            eligibilityMessage.textContent = 'Masuk sebagai customer yang telah menyelesaikan pesanan produk ini untuk menulis ulasan.';
            const loginLink = document.createElement('a');
            loginLink.href = 'login.html?next=index.html';
            loginLink.className = 'btn btn-primary';
            loginLink.textContent = 'Masuk / Daftar';
            loginLink.style.display = 'inline-block';
            loginLink.style.marginTop = '10px';
            eligibilityMessage.append(document.createElement('br'), loginLink);
        } else if (result.eligibility.review_status === 'pending') {
            eligibilityMessage.textContent = 'Ulasan Anda sedang menunggu persetujuan admin.';
        } else if (result.eligibility.review_status === 'approved') {
            eligibilityMessage.textContent = 'Ulasan Anda sudah disetujui.';
        } else if (result.eligibility.review_status === 'rejected') {
            eligibilityMessage.textContent = 'Ulasan Anda tidak disetujui. Setiap customer dapat mengirim satu ulasan per produk.';
        } else {
            eligibilityMessage.textContent = 'Ulasan hanya tersedia setelah pesanan produk ini berstatus selesai.';
        }
    } catch (error) {
        list.textContent = error.message;
    }
}

document.getElementById('reviewForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = document.getElementById('submitReview');
    const message = document.getElementById('reviewSubmitMessage');
    button.disabled = true;
    message.textContent = 'Mengirim ulasan...';
    try {
        const response = await fetch(`${API_BASE}/reviews.php`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                product_id: activeReviewProductId,
                rating: Number(document.getElementById('reviewRating').value),
                review: document.getElementById('reviewText').value
            })
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Ulasan tidak berhasil dikirim.');
        message.textContent = result.message;
        const chatLink = document.createElement('a');
        chatLink.href = 'chat.html';
        chatLink.className = 'btn btn-primary';
        chatLink.textContent = 'Lanjutkan percakapan di Chat';
        chatLink.style.display = 'inline-flex';
        chatLink.style.marginTop = '10px';
        message.append(document.createElement('br'), chatLink);
        message.style.color = '#26733b';
        document.getElementById('reviewForm').reset();
    } catch (error) {
        message.textContent = error.message;
        message.style.color = '#b00020';
    } finally {
        button.disabled = false;
    }
});

function openProofModal(orderCode = '', phone = '') {
    isCustomerLoggedIn().then((loggedIn) => {
        if (!loggedIn) {
            redirectToCustomerLogin('index.html');
            return;
        }
        showProofModal(orderCode, phone);
    }).catch((error) => {
        console.error('Pemeriksaan akun untuk unggah bukti gagal:', error);
        alert('Tidak dapat memeriksa status login. Silakan coba lagi.');
    });
}

function showProofModal(orderCode = '', phone = '') {
    const form = document.getElementById('proofForm');
    form.reset();
    document.getElementById('proofOrderCode').value = orderCode;
    document.getElementById('proofPhone').value = phone;
    document.getElementById('proofMessage').textContent = '';
    document.getElementById('proofMessage').style.color = '';
    proofModal.classList.add('active');
}

function closeProofModal() {
    proofModal.classList.remove('active');
}

document.getElementById('proofForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const image = document.getElementById('proofImage').files[0];
    const message = document.getElementById('proofMessage');
    const submitButton = document.getElementById('proofSubmit');
    if (!image) {
        message.textContent = 'Pilih gambar bukti transfer terlebih dahulu.';
        message.style.color = '#b00020';
        return;
    }
    if (image.size > 5 * 1024 * 1024) {
        message.textContent = 'Ukuran gambar maksimal 5 MB.';
        message.style.color = '#b00020';
        return;
    }

    const formData = new FormData();
    formData.append('order_code', document.getElementById('proofOrderCode').value.trim());
    formData.append('recipient_phone', document.getElementById('proofPhone').value.trim());
    formData.append('proof', image);
    submitButton.disabled = true;
    message.textContent = 'Mengunggah bukti transfer...';
    message.style.color = '';

    try {
        const response = await fetch(`${API_BASE}/upload_payment_proof.php`, {
            method: 'POST',
            credentials: 'include',
            body: formData
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Bukti transfer gagal diunggah.');
        }
        message.textContent = result.message;
        message.style.color = '#26733b';
        document.getElementById('proofImage').value = '';
        setTimeout(closeProofModal, 1800);
    } catch (error) {
        message.textContent = error.message;
        message.style.color = '#b00020';
    } finally {
        submitButton.disabled = false;
    }
});

// ============================================
// 7. SUBMIT CHECKOUT TO DATABASE
// ============================================
document.getElementById('checkoutForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const payload = {
        recipient_name: document.getElementById('coName').value,
        recipient_phone: document.getElementById('coPhone').value,
        recipient_email: document.getElementById('coEmail').value,
        address: document.getElementById('coAddress').value,
        shipping_method: document.getElementById('coShipping').value,
        payment_method: document.getElementById('coPayment').value,
        notes: document.getElementById('coNotes').value,
        voucher_code: appliedVoucher ? appliedVoucher.code : '',
        items: cart
    };

    try {
        const response = await fetch(`${API_BASE}/checkout.php`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        
        if (result.success) {
    checkoutModal.classList.remove('active');
    
    // Tampilkan instruksi pembayaran
    showPaymentInstructions(result, payload.recipient_phone);
    
    cart = [];
    appliedVoucher = null;
    updateCartUI();
    document.getElementById('checkoutForm').reset();
    document.getElementById('voucherMessage').textContent = '';
    document.getElementById('summaryDiscountRow').style.display = 'none';
}

function showPaymentInstructions(result, recipientPhone) {
    const modal = document.getElementById('paymentModal');
    const title = document.getElementById('paymentTitle');
    const content = document.getElementById('paymentContent');
    const proofPrompt = document.getElementById('paymentProofPrompt');
    const chatLink = document.getElementById('orderChatLink');
    chatLink.style.display = 'inline-flex';

    title.innerHTML = `<i class="fas fa-check-circle" style="color:#4CAF50;"></i> Pesanan #${result.order_code} Berhasil!`;
    proofPrompt.style.display = result.payment_method === 'transfer' ? 'block' : 'none';
    document.getElementById('proofOrderCode').value = result.order_code;
    document.getElementById('proofPhone').value = recipientPhone;
    
    let html = `<p style="margin-bottom:15px;">Subtotal: ${formatRupiah(result.subtotal)}</p>`;
    if (Number(result.discount) > 0) {
        html += `<p style="margin-bottom:15px;color:#26733b;">Diskon${result.voucher_code ? ` (${result.voucher_code})` : ''}: -${formatRupiah(result.discount)}</p>`;
    }
    html += `<p style="margin-bottom:15px;">Ongkir: ${formatRupiah(result.shipping_cost)}</p>`;
    html += `<p style="margin-bottom:15px;">Total: <strong>${formatRupiah(result.total)}</strong></p>`;
    
    if (result.payment_method === 'transfer') {
        const pd = result.payment_details;
        html += `
            <div style="background:#f9f9f9;padding:15px;border-radius:8px;">
                <h3 style="margin-bottom:10px;">Transfer ke:</h3>
                <p><strong>Bank:</strong> ${pd.bank}</p>
                <p><strong>No. Rekening:</strong> ${pd.account_number}</p>
                <p><strong>Atas Nama:</strong> ${pd.account_holder}</p>
                <p><strong>Jumlah:</strong> ${formatRupiah(pd.amount)}</p>
            </div>
        `;
    } else if (result.payment_method === 'qris') {
        html += `
            <div style="text-align:center;background:#f9f9f9;padding:20px;border-radius:8px;">
                <h3 style="margin-bottom:10px;">Scan QRIS:</h3>
                <img src="${result.payment_details.qr_image}" style="max-width:200px;margin:10px auto;border:1px solid #ddd;padding:10px;border-radius:8px;">
                <p><strong>Jumlah:</strong> ${formatRupiah(result.payment_details.amount)}</p>
            </div>
        `;
    } else {
        html += `
            <div style="background:#d4edda;padding:15px;border-radius:8px;color:#155724;">
                <i class="fas fa-truck"></i> <strong>COD:</strong> Bayar tunai saat pesanan diantar oleh kurir kami.
            </div>
        `;
    }
    
    content.innerHTML = html;
    modal.classList.add('active');
}
    } catch (error) {
        alert('Error koneksi: ' + error.message);
    }
});

// ============================================
// 8. INIT
// ============================================
loadProducts();
updateCartUI();
checkAuth(); 

if (new URLSearchParams(window.location.search).get('checkout') === '1') {
    isCustomerLoggedIn().then((loggedIn) => {
        if (!loggedIn) {
            redirectToCustomerLogin('checkout');
            return;
        }
        if (cart.length === 0) {
            window.history.replaceState({}, '', window.location.pathname);
            alert('Keranjang Anda kosong. Silakan pilih produk terlebih dahulu.');
            return;
        }
        overlay.classList.add('active');
        checkoutModal.classList.add('active');
        updateSummary();
    }).catch((error) => {
        console.error('Checkout tidak dapat dilanjutkan:', error);
        alert('Tidak dapat memeriksa status login. Silakan coba lagi.');
    });
}
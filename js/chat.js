(() => {
    const root = document.querySelector('[data-api][data-role]');
    const api = root.dataset.api;
    const authApi = root.dataset.authApi;
    const role = root.dataset.role;
    const notice = document.getElementById('chatNotice');
    const messages = document.getElementById('chatMessages');
    const form = document.getElementById('chatForm');
    const input = document.getElementById('chatInput');
    const imageInput = document.getElementById('chatImage');
    const imageStatus = document.getElementById('chatImageStatus');
    const submit = form.querySelector('button[type="submit"]');
    const list = document.getElementById('chatConversationList');
    let conversationId = null;
    let polling = false;
    let renderedConversationId = null;
    let renderedMessageSignature = '';
    let conversationListInitialized = false;
    const lastMessageIds = new Map();

    const toastRegion = document.createElement('div');
    toastRegion.className = 'chat-toast-region';
    toastRegion.setAttribute('role', 'status');
    toastRegion.setAttribute('aria-live', 'polite');
    document.body.append(toastRegion);

    function addText(tag, className, text) {
        const element = document.createElement(tag);
        if (className) element.className = className;
        element.textContent = text;
        return element;
    }

    function showChatToast(title, message) {
        const toast = document.createElement('div');
        toast.className = 'chat-toast';
        const content = document.createElement('div');
        content.append(addText('strong', 'chat-toast-title', title), addText('span', 'chat-toast-message', message));
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'chat-toast-close';
        close.setAttribute('aria-label', 'Tutup notifikasi');
        close.textContent = '×';
        close.addEventListener('click', () => toast.remove());
        toast.append(content, close);
        toastRegion.append(toast);
        window.setTimeout(() => toast.remove(), 8000);
    }

    async function request(url = api, options = {}) {
        const response = await fetch(url, { credentials: 'include', ...options });
        const result = await response.json();
        if (response.status === 401) {
            window.location.href = role === 'admin' ? '../login.html' : 'login.html?next=chat.html';
            throw new Error('Silakan masuk terlebih dahulu.');
        }
        if (!response.ok || !result.success) throw new Error(result.message || 'Chat gagal dimuat.');
        return result.data;
    }

    function renderMessages(items, id) {
        const signature = JSON.stringify(items.map((item) => [item.id, item.message, item.attachment_path]));
        if (Number(id) === Number(renderedConversationId) && signature === renderedMessageSignature) return;
        const previousMessageId = lastMessageIds.get(Number(id));
        if (previousMessageId !== undefined) {
            items.filter((item) => Number(item.id) > previousMessageId && item.sender_role !== role)
                .forEach((item) => showChatToast(
                    role === 'admin' ? 'Pesan baru dari customer' : 'Balasan baru dari RiRa Cakes',
                    item.message || (item.attachment_path ? 'Mengirim foto' : 'Anda menerima pesan baru.')
                ));
        }
        lastMessageIds.set(
            Number(id),
            Math.max(previousMessageId || 0, ...items.map((item) => Number(item.id)))
        );
        const wasAtBottom = messages.scrollHeight - messages.scrollTop - messages.clientHeight < 50;
        messages.replaceChildren();
        items.forEach((item) => {
            const row = document.createElement('div');
            row.className = `chat-message ${item.sender_role === role ? 'chat-message-own' : ''}`;
            row.append(
                addText('span', 'chat-message-author', item.sender_role === 'admin' ? 'RiRa Cakes' : item.sender_role === 'system' ? 'Info' : role === 'admin' ? 'Customer' : 'Anda'),
            );
            if (item.message) row.append(addText('p', 'chat-message-text', item.message));
            if (item.attachment_path) {
                const imageLink = document.createElement('a');
                imageLink.href = `${api.replace(/chat\.php$/, 'chat_attachment.php')}?id=${encodeURIComponent(item.id)}`;
                imageLink.target = '_blank';
                imageLink.rel = 'noopener';
                imageLink.className = 'chat-image-link';
                const image = document.createElement('img');
                image.src = imageLink.href;
                image.alt = 'Foto lampiran chat';
                image.loading = 'lazy';
                imageLink.append(image);
                row.append(imageLink);
            }
            row.append(addText('time', 'chat-message-time', new Date(item.created_at.replace(' ', 'T')).toLocaleString('id-ID')));
            messages.append(row);
        });
        renderedConversationId = Number(id);
        renderedMessageSignature = signature;
        if (wasAtBottom) messages.scrollTop = messages.scrollHeight;
    }

    function renderConversationList(items) {
        if (!list) return;
        const scrollTop = list.scrollTop;
        list.replaceChildren();
        items.forEach((item) => {
            const id = Number(item.id);
            const messageId = Number(item.last_message_id) || 0;
            const previousMessageId = lastMessageIds.get(id);
            if (conversationListInitialized && previousMessageId !== undefined
                && messageId > previousMessageId && item.last_sender_role !== role) {
                showChatToast(
                    `Pesan baru dari ${item.customer_name}`,
                    item.last_message || 'Anda menerima pesan baru.'
                );
            }
            lastMessageIds.set(id, Math.max(previousMessageId || 0, messageId));
        });
        conversationListInitialized = true;
        if (!items.length) {
            list.append(addText('p', 'chat-list-empty', 'Belum ada percakapan customer.'));
            return;
        }
        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `chat-conversation-item${Number(item.id) === Number(conversationId) ? ' active' : ''}`;
            const identity = document.createElement('span');
            identity.className = 'chat-conversation-identity';
            identity.append(addText('strong', '', item.customer_name));
            if (Number(item.unread_count) > 0) {
                identity.append(addText('span', 'chat-conversation-unread', Number(item.unread_count) > 99 ? '99+' : String(item.unread_count)));
            }
            button.append(
                identity,
                addText('span', '', item.last_message || 'Belum ada pesan')
            );
            button.addEventListener('click', () => selectConversation(Number(item.id)));
            list.append(button);
        });
        list.scrollTop = scrollTop;
    }

    async function loadConversationList() {
        const conversations = await request();
        renderConversationList(conversations);
        if (role === 'customer') {
            if (conversations.conversation) {
                await selectConversation(Number(conversations.conversation.id), false);
            } else {
                conversationId = null;
                messages.replaceChildren(addText('p', 'chat-list-empty', 'Belum ada pesan. Mulai percakapan dengan tim kami.'));
                renderedConversationId = null;
                renderedMessageSignature = '';
                notice.textContent = 'Pesan Anda akan dibalas oleh tim RiRa Cakes melalui chat ini.';
            }
        } else if (!conversationId && conversations.length) {
            await selectConversation(Number(conversations[0].id), false);
        }
    }

    async function selectConversation(id, refreshList = true) {
        conversationId = id;
        const data = await request(`${api}?conversation_id=${encodeURIComponent(id)}`);
        renderMessages(data.messages, id);
        if (!document.hidden) {
            await request(api, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_read', conversation_id: id })
            });
        }
        input.disabled = false;
        imageInput.disabled = false;
        submit.disabled = false;
        if (role === 'admin') {
            notice.textContent = `Percakapan dengan ${data.conversation.customer_name}.`;
            if (refreshList) {
                const conversations = await request();
                renderConversationList(conversations);
            }
        } else {
            notice.textContent = 'Percakapan Anda dengan tim RiRa Cakes.';
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = input.value.trim();
        const image = imageInput.files[0];
        if ((!message && !image) || submit.disabled) return;
        submit.disabled = true;
        notice.textContent = 'Mengirim pesan...';
        try {
            const payload = new FormData();
            payload.append('message', message);
            if (conversationId) payload.append('conversation_id', conversationId);
            if (image) payload.append('image', image);
            const data = await request(api, {
                method: 'POST',
                body: payload
            });
            conversationId = Number(data.conversation_id);
            input.value = '';
            imageInput.value = '';
            imageStatus.textContent = '';
            await loadConversationList();
            if (role === 'admin') {
                await selectConversation(conversationId);
            }
        } catch (error) {
            notice.textContent = error.message;
            notice.classList.add('orders-message-error');
        } finally {
            submit.disabled = role === 'admin' && !conversationId;
        }
    });

    imageInput.addEventListener('change', () => {
        const image = imageInput.files[0];
        if (!image) {
            imageStatus.textContent = 'Format foto JPG, PNG, atau WEBP; maksimal 5 MB.';
            return;
        }
        const allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowed.includes(image.type) || image.size > 5 * 1024 * 1024) {
            imageInput.value = '';
            imageStatus.textContent = 'Pilih foto JPG, PNG, atau WEBP maksimal 5 MB.';
            return;
        }
        imageStatus.textContent = `Foto terpilih: ${image.name}`;
        submit.disabled = role === 'admin' && !conversationId;
        if (role === 'customer') submit.disabled = false;
    });

    input.addEventListener('input', () => {
        submit.disabled = role === 'admin' ? !conversationId : false;
    });

    document.getElementById('chatLogout')?.addEventListener('click', async () => {
        await fetch(`${authApi}?action=logout`, { credentials: 'include' });
        window.location.href = '../login.html';
    });

    async function refresh() {
        if (polling) return;
        polling = true;
        try {
            if (role === 'admin') {
                const conversations = await request();
                renderConversationList(conversations);
                if (conversationId && conversations.some((item) => Number(item.id) === conversationId)) {
                    await selectConversation(conversationId, false);
                } else if (!conversationId && conversations.length) {
                    await selectConversation(Number(conversations[0].id), false);
                }
            } else if (conversationId) {
                await selectConversation(conversationId, false);
            }
        } catch (error) {
            notice.textContent = error.message;
            notice.classList.add('orders-message-error');
        } finally {
            polling = false;
        }
    }

    (async () => {
        try {
            const authResponse = await fetch(`${authApi}?action=check`, { credentials: 'include' });
            const auth = await authResponse.json();
            if (!auth.logged_in || auth.user.role !== role) {
                window.location.href = role === 'admin' ? '../login.html' : 'login.html?next=chat.html';
                return;
            }
            input.disabled = false;
            imageInput.disabled = false;
            submit.disabled = role === 'admin';
            await loadConversationList();
            window.setInterval(refresh, 5000);
        } catch (error) {
            notice.textContent = error.message;
            notice.classList.add('orders-message-error');
        }
    })();
})();

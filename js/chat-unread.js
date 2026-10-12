(() => {
    const script = document.currentScript;
    const api = script.dataset.api;
    const links = document.querySelectorAll('[data-chat-unread]');
    if (!api || !links.length) return;

    const badges = Array.from(links, (link) => {
        const badge = document.createElement('span');
        badge.className = 'chat-unread-badge';
        badge.hidden = true;
        badge.setAttribute('aria-live', 'polite');
        badge.setAttribute('aria-atomic', 'true');
        link.append(badge);
        return badge;
    });

    async function refreshUnreadCount() {
        if (document.hidden) return;
        try {
            const response = await fetch(`${api}?action=unread-count`, { credentials: 'include' });
            if (response.status === 401 || response.status === 403) return;
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Jumlah pesan belum dibaca tidak dapat dimuat.');
            }
            const count = Number(result.data.unread_count) || 0;
            badges.forEach((badge) => {
                badge.hidden = count === 0;
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.setAttribute('aria-label', `${count} pesan belum dibaca`);
            });
        } catch (error) {
            console.error('Chat unread count refresh failed:', error);
        }
    }

    refreshUnreadCount();
    window.setInterval(refreshUnreadCount, 5000);
    document.addEventListener('visibilitychange', refreshUnreadCount);
})();

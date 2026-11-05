(function () {
    'use strict';

    var config = window.catNotifications || {};
    var overlayLoading = false;
    var overlayOpen = false;
    var overlayHeartbeatTimer = null;
    var overlayRetryTimer = null;

    function request(action, data) {
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('nonce', config.nonce || '');
        Object.keys(data || {}).forEach(function (key) {
            body.set(key, String(data[key]));
        });

        return window.fetch(config.ajaxUrl || '', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body.toString()
        }).then(function (response) {
            return response.json();
        });
    }

    function updateUnreadBadge(count) {
        document.querySelectorAll('.app-nav__badge').forEach(function (badge) {
            if (count <= 0) {
                badge.remove();
                return;
            }
            badge.textContent = count > 99 ? '99+' : String(count);
        });
    }

    function bindCenter() {
        document.querySelectorAll('[data-notification-read]').forEach(function (button) {
            button.addEventListener('click', function () {
                var item = button.closest('[data-notification-id]');
                var id = item ? item.getAttribute('data-notification-id') : '';
                request('cat_notification_mark_read', {notification_id: id}).then(function (payload) {
                    if (!payload.success || !item) {
                        return;
                    }
                    item.classList.remove('is-unread');
                    button.remove();
                    updateUnreadBadge(Number(payload.data.unread || 0));
                });
            });
        });

        var readAll = document.querySelector('[data-notifications-read-all]');
        if (!readAll) {
            return;
        }
        readAll.addEventListener('click', function () {
            request('cat_notification_mark_read', {all: 1}).then(function (payload) {
                if (!payload.success) {
                    return;
                }
                document.querySelectorAll('.notifications-list__item').forEach(function (item) {
                    item.classList.remove('is-unread');
                });
                document.querySelectorAll('[data-notification-read]').forEach(function (button) {
                    button.remove();
                });
                readAll.remove();
                updateUnreadBadge(0);
            });
        });
    }

    function claimData(notification) {
        return {
            notification_id: notification.id,
            claim_token: notification.claimToken
        };
    }

    function acknowledge(notification) {
        return request('cat_notification_overlay_seen', claimData(notification));
    }

    function stopOverlayHeartbeat() {
        if (!overlayHeartbeatTimer) {
            return;
        }
        window.clearTimeout(overlayHeartbeatTimer);
        overlayHeartbeatTimer = null;
    }

    function abandonOverlay() {
        stopOverlayHeartbeat();
        var overlay = document.querySelector('.notification-overlay');
        if (overlay) {
            overlay.remove();
        }
        overlayOpen = false;
        loadNextOverlay();
    }

    function startOverlayHeartbeat(notification) {
        stopOverlayHeartbeat();
        overlayHeartbeatTimer = window.setTimeout(function renewClaim() {
            overlayHeartbeatTimer = null;
            request('cat_notification_overlay_heartbeat', claimData(notification)).then(function (payload) {
                if (payload.success && overlayOpen) {
                    startOverlayHeartbeat(notification);
                    return;
                }
                if (overlayOpen) {
                    abandonOverlay();
                }
            }).catch(function () {
                if (overlayOpen) {
                    startOverlayHeartbeat(notification);
                }
            });
        }, 45000);
    }

    function showOverlay(notification) {
        if (overlayRetryTimer) {
            window.clearTimeout(overlayRetryTimer);
            overlayRetryTimer = null;
        }
        overlayOpen = true;
        startOverlayHeartbeat(notification);
        var overlay = document.createElement('div');
        overlay.className = 'notification-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'notification-overlay-title');

        var card = document.createElement('div');
        card.className = 'notification-overlay__card';
        if (notification.sourceType === 'trophy') {
            card.classList.add('notification-overlay__card--trophy');
        }

        if (notification.imageUrl) {
            var image = document.createElement('img');
            image.className = 'notification-overlay__image';
            image.src = notification.imageUrl;
            image.alt = '';
            card.appendChild(image);
        }

        var eyebrow = document.createElement('p');
        eyebrow.className = 'notification-overlay__eyebrow';
        eyebrow.textContent = notification.sourceType === 'trophy'
            ? (config.labels.trophy || '')
            : (config.labels.notification || '');
        card.appendChild(eyebrow);

        var title = document.createElement('h2');
        title.id = 'notification-overlay-title';
        title.textContent = notification.title || '';
        card.appendChild(title);

        if (notification.body) {
            var body = document.createElement('p');
            body.className = 'notification-overlay__body';
            body.textContent = notification.body;
            card.appendChild(body);
        }

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'bouton-principal';
        close.textContent = config.labels.close || '';
        close.addEventListener('click', function () {
            stopOverlayHeartbeat();
            overlay.remove();
            overlayOpen = false;
            acknowledge(notification).finally(loadNextOverlay);
        });
        card.appendChild(close);
        overlay.appendChild(card);
        document.body.appendChild(overlay);
        close.focus();
    }

    function scheduleOverlayRetry() {
        if (overlayRetryTimer) {
            window.clearTimeout(overlayRetryTimer);
        }
        overlayRetryTimer = window.setTimeout(function () {
            overlayRetryTimer = null;
            loadNextOverlay();
        }, 125000);
    }

    function loadNextOverlay() {
        if (document.visibilityState === 'hidden' || overlayLoading || overlayOpen) {
            return;
        }
        overlayLoading = true;
        request('cat_notification_overlay_next', {}).then(function (payload) {
            if (!payload.success || !payload.data.notification) {
                scheduleOverlayRetry();
                return;
            }
            showOverlay(payload.data.notification);
        }).catch(function () {
            scheduleOverlayRetry();
        }).finally(function () {
            overlayLoading = false;
        });
    }

    if (!config.ajaxUrl || !config.nonce) {
        return;
    }
    bindCenter();
    loadNextOverlay();
    document.addEventListener('visibilitychange', loadNextOverlay);
})();

(function () {
    if (window.__ticketChatBubbleLoaded) { return; }
    window.__ticketChatBubbleLoaded = true;
    function waitForJquery(retry) {
        if (window.jQuery) { init(window.jQuery); return; }
        if (retry > 40) return;
        setTimeout(function () { waitForJquery(retry + 1); }, 250);
    }

    function escapeHtml(text) {
        var map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" };
        return text.replace(/[&<>"']/g, function (c) { return map[c] || c; });
    }

    function textToHtml(text) {
        return escapeHtml(text).replace(/\n/g, '<br>');
    }

    function init($) {
        var cfg = window.ticketChatConfig || {};
        var userId = cfg.userId || 0;
        var userRole = (cfg.userRole || '').toLowerCase();
        var userEmpId = cfg.userEmpId || 0;

        function resolveAssetPath(path) {
            if (!path) { return ''; }
            if (/^https?:\/\//i.test(path)) { return path; }
            if (path.charAt(0) !== '/') { path = '/' + path; }
            var origin = window.location.origin || '';
            return origin ? origin + path : path;
        }

        var notificationIconSource = cfg.notificationIcon || '/gg_app/dist/img/sumlogo.png';
        var notificationIconResolved = resolveAssetPath(notificationIconSource);
        var notificationIconCached = '';
        var notificationIconPromise = null;

        function getNotificationIconUrl() {
            if (!notificationIconResolved) { return Promise.resolve(''); }
            if (notificationIconCached) { return Promise.resolve(notificationIconCached); }
            if (!window.fetch) {
                notificationIconCached = notificationIconResolved;
                return Promise.resolve(notificationIconCached);
            }
            if (!notificationIconPromise) {
                notificationIconPromise = fetch(notificationIconResolved)
                    .then(function (resp) {
                        if (!resp.ok) { throw new Error('Failed to load icon'); }
                        return resp.blob();
                    })
                    .then(function (blob) {
                        notificationIconCached = URL.createObjectURL(blob);
                        return notificationIconCached;
                    })
                    .catch(function () {
                        notificationIconCached = notificationIconResolved;
                        return notificationIconCached;
                    });
            }
            return notificationIconPromise;
        }
        if (!userId) return;

        var bubble = $('#ticketChatBubble');
        if (!bubble.length) return;
        var toast = $('#ticketChatToast');
        var panel = $('#ticketChatPanel');
        var listContainer = $('#ticketChatList');
        var threadWrapper = $('#ticketChatThreadWrapper');
        var threadSubject = $('#ticketChatThreadSubject');
        var threadMeta = $('#ticketChatThreadMeta');
        var threadBody = $('#ticketChatThread');
        var replyInput = $('#ticketChatReplyInput');
        var replyAttachment = $('#ticketChatReplyAttachment');
        var attachmentNames = $('#ticketChatAttachmentNames');
        var replyButton = $('#ticketChatReplySend');
        var replyButtonHtml = replyButton.length ? replyButton.html() : '';
        var replySending = false;
        var replyForm = $('#ticketChatReplyForm');
        var closedMessage = $('#ticketChatClosedMessage');
        var openDetailBtn = $('.ticket-chat-open-detail');
        var completeBtn = $('.ticket-chat-complete');
        var completeBtnLabel = completeBtn.length ? completeBtn.text() : '';
        var backBtn = $('.ticket-chat-back');
        var refreshBtn = $('.ticket-chat-refresh');
        var closeBtn = $('.ticket-chat-close');
        var readSyncTimer = null;
        var threadRefreshTimer = null;
        var currentThreadMaxId = 0;
        var completionInProgress = false;
        var imageModalInstance = null;
        var imageModalImg = null;

        // Global Zoom & Pan State
        var currentZoom = 1;
        var isDragging = false;
        var startX = 0, startY = 0;
        var translateX = 0, translateY = 0;
        var lastTranslateX = 0, lastTranslateY = 0;

        // Initialize Universal Zoom (Fix for Edit Ticket Modal & others)
        initUniversalImageZoom();

        function updateZoom() {
            if (!imageModalImg) return;
            if (currentZoom < 1) currentZoom = 1;
            if (currentZoom > 5) currentZoom = 5;

            imageModalImg.style.transform = 'translate(' + translateX + 'px, ' + translateY + 'px) scale(' + currentZoom + ')';
            imageModalImg.style.transformOrigin = 'center center';
            imageModalImg.style.transition = isDragging ? 'none' : 'transform 0.1s ease-out';

            if (currentZoom > 1) {
                imageModalImg.style.cursor = isDragging ? 'grabbing' : 'grab';
            } else {
                imageModalImg.style.cursor = 'zoom-in';
                translateX = 0; translateY = 0;
                lastTranslateX = 0; lastTranslateY = 0;
            }
        }

        function handleWheelZoom(e) {
            var modalEl = document.getElementById('ticketChatImageModal');
            if (!modalEl || !modalEl.classList.contains('show') || modalEl.style.display === 'none') return;
            e.preventDefault();
            if (e.deltaY < 0) { currentZoom += 0.25; } else { currentZoom -= 0.25; }
            if (currentZoom <= 1) {
                currentZoom = 1;
                translateX = 0; translateY = 0;
                lastTranslateX = 0; lastTranslateY = 0;
            }
            updateZoom();
        }

        function onMouseDown(e) {
            if (currentZoom <= 1) return;
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            imageModalImg.style.cursor = 'grabbing';
            imageModalImg.style.transition = 'none';
            e.preventDefault();
        }

        function onMouseMove(e) {
            if (!isDragging || currentZoom <= 1) return;
            e.preventDefault();
            var deltaX = e.clientX - startX;
            var deltaY = e.clientY - startY;
            translateX = lastTranslateX + deltaX;
            translateY = lastTranslateY + deltaY;
            updateZoom();
        }

        function onMouseUp(e) {
            if (!isDragging) return;
            isDragging = false;
            lastTranslateX = translateX;
            lastTranslateY = translateY;
            if (currentZoom > 1) imageModalImg.style.cursor = 'grab';
            updateZoom();
        }

        function initUniversalImageZoom() {
            var modalEl = document.getElementById('ticketChatImageModal');
            if (!modalEl) return;

            var $modal = $(modalEl);
            // Universal listeners that handle BS4 or BS5 trigger
            $modal.off('shown.bs.modal.universalZoom').on('shown.bs.modal.universalZoom', function () {
                imageModalImg = modalEl.querySelector('#ticketChatImageModalImg');
                currentZoom = 1;
                translateX = 0; translateY = 0;
                lastTranslateX = 0; lastTranslateY = 0;
                if (imageModalImg) {
                    imageModalImg.style.transform = '';
                    imageModalImg.style.transition = '';
                    imageModalImg.style.cursor = 'zoom-in';
                    imageModalImg.style.border = '';
                    // Re-attach listeners to ensure they work on newly set images
                    imageModalImg.removeEventListener('mousedown', onMouseDown);
                    imageModalImg.addEventListener('mousedown', onMouseDown);
                }

                // Global listeners for zoom/panning
                window.removeEventListener('mousemove', onMouseMove);
                window.addEventListener('mousemove', onMouseMove);
                window.removeEventListener('mouseup', onMouseUp);
                window.addEventListener('mouseup', onMouseUp);
                window.removeEventListener('wheel', handleWheelZoom);
                window.addEventListener('wheel', handleWheelZoom, { passive: false });
            });

            $modal.off('hidden.bs.modal.universalZoom').on('hidden.bs.modal.universalZoom', function () {
                window.removeEventListener('wheel', handleWheelZoom);
                if (imageModalImg) {
                    imageModalImg.removeEventListener('mousedown', onMouseDown);
                }
                window.removeEventListener('mousemove', onMouseMove);
                window.removeEventListener('mouseup', onMouseUp);
                if (imageModalImg) {
                    imageModalImg.style.transform = '';
                    imageModalImg.style.border = '';
                }
                currentZoom = 1;
                isDragging = false;
            });
        }

        function ensureImageModal() {
            // Deprecated in favor of initUniversalImageZoom, but kept for compatibility
            initUniversalImageZoom();
            return null;
        }

        function openInlineImage(src, alt) {
            if (!src) { return; }
            var modalEl = document.getElementById('ticketChatImageModal');
            if (!modalEl) {
                window.open(src, '_blank');
                return;
            }

            // Set image before showing modal
            imageModalImg = modalEl.querySelector('#ticketChatImageModalImg');
            if (imageModalImg) {
                imageModalImg.src = src;
                imageModalImg.alt = alt || 'Preview';
            }

            // Try BS5
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                try {
                    var inst = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    inst.show();
                    return;
                } catch (e) { }
            }

            // Fallback to jQuery (BS4) for list/edit pages
            if (typeof $ !== 'undefined' && typeof $.fn.modal === 'function') {
                $(modalEl).modal('show');
            } else {
                window.open(src, '_blank');
            }
        }

        function normalizeInlineImage($img) {
            if (!$img || !$img.length) { return; }
            var src = $img.attr('src') || '';
            if (!src) { return; }
            $img.attr({ loading: 'lazy', decoding: 'async' });
            var $link = $img.parent('a.chat-inline-img-link');
            if (!$link.length) {
                $img.wrap('<a class="chat-inline-img-link" href="' + src + '" target="_blank" rel="noopener noreferrer"></a>');
                $link = $img.parent('a.chat-inline-img-link');
            } else {
                $link.attr('href', src);
            }
            $link.off('click.ticketBubbleImage').on('click.ticketBubbleImage', function (e) {
                e.preventDefault();
                e.stopPropagation();
                // Check tutorial first (Gamified Unlock)
                if (window.UniversalZoomTutorial && typeof window.UniversalZoomTutorial.checkAndShow === 'function') {
                    window.UniversalZoomTutorial.checkAndShow(function () {
                        openInlineImage($img.attr('src'), $img.attr('alt'));
                    });
                } else {
                    openInlineImage($img.attr('src'), $img.attr('alt'));
                }
            });
            if (src.indexOf('data:') === 0 && !$img.data('blobReady') && !$img.data('blobBusy') && window.fetch) {
                $img.data('blobBusy', true);
                var original = src;
                fetch(original)
                    .then(function (resp) { return resp.blob(); })
                    .then(function (blob) {
                        var blobUrl = URL.createObjectURL(blob);
                        var prev = $img.data('blobUrl');
                        if (prev) { URL.revokeObjectURL(prev); }
                        $img.attr('src', blobUrl);
                        $img.data('blobUrl', blobUrl);
                        $img.data('blobReady', true);
                        $img.data('blobBusy', false);
                        var $parent = $img.parent('a.chat-inline-img-link');
                        if ($parent.length) { $parent.attr('href', blobUrl); }
                    })
                    .catch(function () {
                        $img.data('blobBusy', false);
                    });
            }
        }

        function enhanceThreadImages($scope) {
            var $context = ($scope && $scope.length) ? $scope : threadBody;
            var $imgs = $context.find('.bubble img');
            if ($context.is('.bubble img')) {
                $imgs = $imgs.add($context);
            }
            $imgs.each(function () { normalizeInlineImage($(this)); });
        }

        var storageKey = 'ticket_last_check_' + userId;
        var lastCheck = localStorage.getItem(storageKey) || '';
        var notifyKey = 'ticket_last_notified_' + userId;
        var hideKey = 'ticket_bubble_hidden_' + userId;
        var readKey = 'ticket_last_read_id_' + userId;
        var bubblePosKey = 'ticket_bubble_pos_' + userId;
        var activationKey = 'ticket_bubble_activated_' + userId;
        var deliveryKey = 'ticket_notification_cache_' + userId;
        var lastNotifiedId = parseInt(localStorage.getItem(notifyKey) || '0', 10);
        if (isNaN(lastNotifiedId)) { lastNotifiedId = 0; }
        var lastSeenId = parseInt(localStorage.getItem(readKey) || '0', 10);
        if (isNaN(lastSeenId)) { lastSeenId = 0; }
        var latestKnownId = Math.max(lastSeenId, lastNotifiedId);
        var storedHidden = localStorage.getItem(hideKey);
        var bubbleHidden = storedHidden === null ? true : storedHidden === '1';
        if (storedHidden === null) {
            try { localStorage.setItem(hideKey, '1'); } catch (err) { }
        }
        var storedActivation = localStorage.getItem(activationKey);
        var bubbleActivated = storedActivation === '1';
        if (!bubbleActivated && storedHidden === '0') {
            bubbleActivated = true;
            try { localStorage.setItem(activationKey, '1'); } catch (err) { }
        }
        var currentTicket = null;
        var pollTimer = null;
        var deliveryCache = { msg: [], ticket: {} };
        try {
            var rawDelivery = JSON.parse(localStorage.getItem(deliveryKey) || '{}');
            if (rawDelivery) {
                if (Array.isArray(rawDelivery.msg)) {
                    deliveryCache.msg = rawDelivery.msg.map(function (val) { return parseInt(val, 10); }).filter(function (val) { return !isNaN(val); });
                }
                if (rawDelivery.ticket && typeof rawDelivery.ticket === 'object') {
                    deliveryCache.ticket = rawDelivery.ticket;
                }
            }
        } catch (err) { }

        function getActiveTicketId() {
            var source = (typeof window.currentTicketId !== 'undefined') ? window.currentTicketId : cfg.activeTicketId;
            var id = parseInt(source, 10);
            return isNaN(id) ? 0 : id;
        }

        function alignPanelToBubble() {
            if (!panel || !panel.length || panel.hasClass('d-none')) { return; }
            if (!bubble || !bubble.length || !bubble.is(':visible')) { return; }

            var rect = bubble[0].getBoundingClientRect();
            var panelWidth = 360;
            var targetHeight = 530; // Original design height
            var viewportWidth = window.innerWidth;
            var viewportHeight = window.innerHeight;
            var gap = 12;

            // Horizontal Alignment
            var left = rect.left + rect.width + gap;
            if (left + panelWidth > viewportWidth - gap) {
                left = rect.left - panelWidth - gap;
            }
            if (left < gap) { left = gap; }

            // Vertical Alignment Strategy
            var spaceAbove = rect.top;
            var spaceBelow = viewportHeight - (rect.top + rect.height);
            var anchorToBottom = (spaceAbove > spaceBelow) || (rect.top > viewportHeight * 0.45);

            // Calculate global max height (limited only by screen, not bubble)
            var maxH = Math.min(targetHeight, viewportHeight - 2 * gap);
            panel.css('max-height', maxH + 'px');

            if (anchorToBottom) {
                // Preferred bottom matches bubble bottom
                var b = viewportHeight - (rect.top + rect.height);
                if (b < gap) b = gap;

                // If it hits top, shift it down to stay on screen
                if (b + maxH > viewportHeight - gap) {
                    b = viewportHeight - maxH - gap;
                }
                if (b < gap) b = gap;

                panel.css({
                    left: left + 'px',
                    bottom: b + 'px',
                    top: 'auto',
                    right: 'auto'
                });
            } else {
                // Preferred top matches bubble top
                var t = rect.top;
                if (t < gap) t = gap;

                // If it hits bottom, shift it up to stay on screen
                if (t + maxH > viewportHeight - gap) {
                    t = viewportHeight - maxH - gap;
                }
                if (t < gap) t = gap;

                panel.css({
                    left: left + 'px',
                    top: t + 'px',
                    bottom: 'auto',
                    right: 'auto'
                });
            }
        }

        function alignToastToBubble() {
            if (!toast || !toast.length || !toast.hasClass('show')) { return; }
            if (!bubble || !bubble.length || !bubble.is(':visible')) { return; }
            var rect = bubble[0].getBoundingClientRect();
            var toastWidth = toast.outerWidth() || 260;
            var toastHeight = toast.outerHeight() || 110;
            var viewportWidth = window.innerWidth;
            var viewportHeight = window.innerHeight;
            var gap = 12;
            var left = rect.left - toastWidth - gap;
            if (left < gap) {
                left = rect.right + gap;
            }
            if (left + toastWidth > viewportWidth - gap) {
                left = viewportWidth - toastWidth - gap;
            }
            if (left < gap) { left = gap; }
            var top = rect.top - toastHeight - gap;
            if (top < gap) {
                top = rect.bottom + gap;
            }
            if (top + toastHeight > viewportHeight - gap) {
                top = viewportHeight - toastHeight - gap;
            }
            toast.css({ left: left + 'px', top: top + 'px', right: 'auto', bottom: 'auto' });
        }

        function applyBubbleState() {
            if (!bubble || !bubble.length) { return; }
            var shouldShow = bubbleActivated && !bubbleHidden;
            if (shouldShow) {
                bubble.removeClass('d-none');
            } else {
                bubble.addClass('d-none');
            }
        }

        function ensureBubbleActivated(options) {
            options = options || {};
            if (!bubbleActivated) {
                bubbleActivated = true;
                localStorage.setItem(activationKey, '1');
            }
            if (options.reveal) {
                bubbleHidden = false;
                localStorage.setItem(hideKey, '0');
            }
            applyBubbleState();
        }

        function autoActivateFromPageContext() {
            if (getActiveTicketId() > 0) {
                ensureBubbleActivated({ reveal: true });
            }
        }

        function hideBubbleUntilNew() {
            markAllRead();
            bubbleHidden = true;
            localStorage.setItem(hideKey, '1');
            applyBubbleState();
            panel.addClass('d-none');
            showList();
        }

        function revealBubble() {
            ensureBubbleActivated({ reveal: true });
        }

        function persistDeliveryCache() {
            try {
                localStorage.setItem(deliveryKey, JSON.stringify(deliveryCache));
            } catch (err) { }
        }

        function pruneTicketDeliveryCache() {
            var cutoff = Date.now() - 600000; // 10 minutes retention
            Object.keys(deliveryCache.ticket || {}).forEach(function (key) {
                var ts = parseInt(deliveryCache.ticket[key], 10);
                if (isNaN(ts) || ts < cutoff) {
                    delete deliveryCache.ticket[key];
                }
            });
        }
        pruneTicketDeliveryCache();

        function hasDeliveredNotice(messageId, ticketId) {
            if (messageId) {
                return deliveryCache.msg.indexOf(messageId) !== -1;
            }
            if (ticketId) {
                var last = parseInt(deliveryCache.ticket[ticketId], 10);
                return !!last && (Date.now() - last) < 120000; // 2 minutes throttle
            }
            return false;
        }

        function rememberDelivery(messageId, ticketId) {
            if (messageId) {
                if (deliveryCache.msg.indexOf(messageId) === -1) {
                    deliveryCache.msg.push(messageId);
                    if (deliveryCache.msg.length > 120) {
                        deliveryCache.msg = deliveryCache.msg.slice(-90);
                    }
                }
            } else if (ticketId) {
                deliveryCache.ticket[ticketId] = Date.now();
                pruneTicketDeliveryCache();
            }
            persistDeliveryCache();
        }

        function formatNowSql() {
            var d = new Date();
            function pad(v) { return (v < 10 ? '0' : '') + v; }
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' '
                + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
        }

        function updateLastSeen(targetId) {
            if (targetId && targetId > latestKnownId) {
                latestKnownId = targetId;
            }
            if (latestKnownId > lastSeenId) {
                lastSeenId = latestKnownId;
                localStorage.setItem(readKey, String(lastSeenId));
            }
            if (lastNotifiedId < lastSeenId) {
                lastNotifiedId = lastSeenId;
                localStorage.setItem(notifyKey, String(lastNotifiedId));
            }
        }

        function markAllRead() {
            updateLastSeen(latestKnownId);
            lastCheck = formatNowSql();
            localStorage.setItem(storageKey, lastCheck);
            updateBadge(0);
        }

        function applyTheme() {
            var theme = (cfg.theme || 'primary').toLowerCase();
            bubble.removeClass(function (index, className) {
                return (className.match(/theme-\S+/g) || []).join(' ');
            });
            bubble.addClass('theme-' + theme);
        }

        function clampBubblePosition(pos) {
            var width = window.innerWidth;
            var height = window.innerHeight;
            var bubbleWidth = bubble.outerWidth() || 54;
            var bubbleHeight = bubble.outerHeight() || 54;
            var margin = 8;
            return {
                x: Math.min(Math.max(margin, pos.x), Math.max(margin, width - bubbleWidth - margin)),
                y: Math.min(Math.max(margin, pos.y), Math.max(margin, height - bubbleHeight - margin))
            };
        }

        function applyBubblePosition() {
            var raw = localStorage.getItem(bubblePosKey);
            if (!raw) return;
            try {
                var pos = JSON.parse(raw);
                if (typeof pos.x === 'number' && typeof pos.y === 'number') {
                    var clamped = clampBubblePosition(pos);
                    bubble.css({ left: clamped.x + 'px', top: clamped.y + 'px', right: 'auto', bottom: 'auto' });
                    alignToastToBubble();
                    alignPanelToBubble();
                }
            } catch (err) { }
        }

        function saveBubblePosition(x, y) {
            var clamped = clampBubblePosition({ x: x, y: y });
            localStorage.setItem(bubblePosKey, JSON.stringify(clamped));
        }

        function initBubbleDrag() {
            var dragging = false;
            var offsetX = 0;
            var offsetY = 0;
            var startX = 0;
            var startY = 0;
            var hasPointerDown = false;
            var dragThreshold = 8; // px, to distinguish tap vs drag

            function getPoint(evt) {
                var e = evt.originalEvent || evt;
                if (e.touches && e.touches.length) {
                    return { x: e.touches[0].clientX, y: e.touches[0].clientY };
                }
                if (e.changedTouches && e.changedTouches.length) {
                    return { x: e.changedTouches[0].clientX, y: e.changedTouches[0].clientY };
                }
                return { x: e.clientX || 0, y: e.clientY || 0 };
            }

            bubble.on('mousedown touchstart', function (e) {
                if ($(e.target).closest('.ticket-chat-close-icon').length) return;
                var point = getPoint(e);
                var rect = bubble[0].getBoundingClientRect();
                offsetX = point.x - rect.left;
                offsetY = point.y - rect.top;
                startX = point.x;
                startY = point.y;
                dragging = false;
                hasPointerDown = true;
                // important: no preventDefault here so tap still fires click on mobile
            });

            $(document).on('mousemove touchmove', function (e) {
                if (!hasPointerDown) return;
                var point = getPoint(e);
                if (!point) return;
                var dx = Math.abs(point.x - startX);
                var dy = Math.abs(point.y - startY);
                if (!dragging && (dx > dragThreshold || dy > dragThreshold)) {
                    dragging = true;
                    bubble.addClass('dragging');
                }
                if (!dragging) return;
                var newLeft = point.x - offsetX;
                var newTop = point.y - offsetY;
                var clamped = clampBubblePosition({ x: newLeft, y: newTop });
                bubble.css({ left: clamped.x + 'px', top: clamped.y + 'px', right: 'auto', bottom: 'auto' });
                if (!panel.hasClass('d-none')) {
                    alignPanelToBubble();
                }
                alignToastToBubble();
                e.preventDefault();
            });

            $(document).on('mouseup touchend touchcancel', function () {
                if (!hasPointerDown) return;
                hasPointerDown = false;
                if (!dragging) return; // tap case is handled by normal click handler
                dragging = false;
                bubble.removeClass('dragging');
                var rect = bubble[0].getBoundingClientRect();
                saveBubblePosition(rect.left, rect.top);
                alignPanelToBubble();
                alignToastToBubble();
            });

            $(window).on('resize', function () {
                applyBubblePosition();
                alignPanelToBubble();
                alignToastToBubble();
            });
        }

        function ensureNotificationPermission() {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        }

        function showToast(latest) {
            if (!latest || !latest.length) return;
            var item = latest[0];
            toast.html('<strong>' + (item.ticket_no || 'Ticket') + '</strong><br><small>' + item.sender + ' · ' + item.time + '</small><div>' + item.preview + '</div>');
            toast.addClass('show');
            alignToastToBubble();
            setTimeout(function () { toast.removeClass('show'); }, 4000);
        }


        $(window).on('storage', function (e) {
            var evt = e.originalEvent || e;
            // Sync Read Status
            if (evt.key === readKey) {
                var val = parseInt(evt.newValue, 10);
                if (!isNaN(val) && val > lastSeenId) {
                    lastSeenId = val;
                    if (lastNotifiedId < lastSeenId) {
                        lastNotifiedId = lastSeenId;
                        try { localStorage.setItem(notifyKey, String(lastNotifiedId)); } catch (ex) { }
                    }
                    pollCounts();
                }
            }
            // Sync Notification Status (Double notification fix)
            if (evt.key === notifyKey) {
                var val = parseInt(evt.newValue, 10);
                if (!isNaN(val) && val > lastNotifiedId) {
                    lastNotifiedId = val;
                    // If notified elsewhere, we should just update badge
                    pollCounts();
                }
            }
            // Sync Bubble Hidden/Shown
            if (evt.key === hideKey) {
                bubbleHidden = (evt.newValue === '1');
                applyBubbleState();
            }
        });

        // Double check storage before notifying to avoid race conditions
        function notify(latest) {
            if (!latest || !latest.length) return;

            // Re-read latest notified ID from storage
            var remoteLastNotified = parseInt(localStorage.getItem(notifyKey) || '0', 10);
            if (!isNaN(remoteLastNotified) && remoteLastNotified > lastNotifiedId) {
                lastNotifiedId = remoteLastNotified;
            }

            var activeTicketId = getActiveTicketId();
            var newItems = [];

            latest.forEach(function (item) {
                var mid = parseInt(item.message_id || 0, 10);
                if (!isNaN(mid) && mid > latestKnownId) {
                    latestKnownId = mid;
                }
                var ticketId = parseInt(item.ticket_id || 0, 10);
                if (activeTicketId && ticketId === activeTicketId) {
                    if (!isNaN(mid) && mid > lastSeenId) {
                        updateLastSeen(mid);
                    }
                    return;
                }
                var dedupeHit = hasDeliveredNotice(!isNaN(mid) && mid > 0 ? mid : 0, ticketId || 0);
                if (mid && mid > lastSeenId && mid > lastNotifiedId && !dedupeHit) {
                    newItems.push(item);
                    rememberDelivery(mid, ticketId || 0);
                    // Do not update lastNotifiedId immediately here, to allow accumulation? 
                    // No, original logic was updating it if mid > lastNotifiedId?
                    // Actually checking logic below:
                    // Original: if (mid > lastNotifiedId) { lastNotifiedId = mid; } return;
                    return;
                }
                if (!mid && !dedupeHit) {
                    newItems.push(item);
                    rememberDelivery(0, ticketId || 0);
                    return;
                }
            });

            // Find max mid in newItems to update lastNotifiedId
            var maxNewMid = 0;
            newItems.forEach(function (item) {
                var m = parseInt(item.message_id || 0, 10);
                if (m > maxNewMid) maxNewMid = m;
            });

            if (!newItems.length) {
                // Ensure storage is consistent even if we didn't notify
                if (maxNewMid > lastNotifiedId) lastNotifiedId = maxNewMid;
                localStorage.setItem(notifyKey, String(lastNotifiedId));
                return;
            }

            if (maxNewMid > lastNotifiedId) {
                lastNotifiedId = maxNewMid;
            }
            localStorage.setItem(notifyKey, String(lastNotifiedId));

            revealBubble();
            if ('Notification' in window && Notification.permission === 'granted') {
                getNotificationIconUrl().then(function (iconUrl) {
                    newItems.forEach(function (item) {
                        new Notification('Ticket ' + item.ticket_no, {
                            body: item.sender + ': ' + item.preview,
                            icon: iconUrl || undefined,
                            badge: iconUrl || undefined
                        });
                    });
                });
            } else {
                showToast(newItems);
            }
        }


        var badge = bubble.find('.ticket-chat-count');
        badge.addClass('d-none');

        function updateBadge(count) {
            if (count > 0) {
                badge.text(count > 9 ? '9+' : count).removeClass('d-none');
            } else {
                badge.addClass('d-none');
            }
        }

        function pollCounts() {
            $.ajax({
                url: '/gg_app/pages/ticket/check_new_messages.php',
                method: 'POST',
                data: {
                    last_check: lastCheck,
                    last_seen_id: lastSeenId,
                    exclude_ticket_id: getActiveTicketId()
                },
                dataType: 'json'
            }).done(function (resp) {
                if (!resp || !resp.success) return;
                lastCheck = resp.server_time || lastCheck;
                localStorage.setItem(storageKey, lastCheck);
                var maxResp = parseInt(resp.max_message_id || 0, 10);
                if (!isNaN(maxResp) && maxResp > latestKnownId) {
                    latestKnownId = maxResp;
                }
                var count = parseInt(resp.count || 0, 10);
                updateBadge(count);
                if (count > 0) {
                    notify(resp.latest || []);
                }
            });
        }

        function setListLoading() {
            listContainer.html('<div class="text-center text-muted p-3">Memuat percakapan...</div>');
            alignPanelToBubble();
        }

        function renderConversations(items) {
            if (!items || !items.length) {
                listContainer.html('<div class="text-center text-muted p-3">Belum ada percakapan</div>');
                return;
            }
            var html = items.map(function (item) {
                var unread = item.unread_count > 0 ? 'unread' : '';
                var subject = item.subject || 'Tanpa subject';
                var creator = item.creator_name || '';
                var meta = [item.ticket_no || '', creator].filter(Boolean).join(' · ');
                var badge = item.unread_count > 0 ? '<span class="badge badge-primary ms-2">' + (item.unread_count > 9 ? '9+' : item.unread_count) + '</span>' : '';
                return '<div class="ticket-chat-list-item ' + unread + '" data-id="' + item.ticket_id + '" data-ticket="' + item.ticket_no + '">'
                    + '<div class="d-flex align-items-start justify-content-between gap-2">'
                    + '<div class="ticket-chat-list-info"><strong>' + subject + '</strong><small>' + meta + '</small></div>'
                    + '<div class="text-end"><small>' + (item.last_time || '') + '</small>' + badge + '</div>'
                    + '</div>'
                    + '<div class="text-muted small mt-1">' + (item.preview || '') + '</div>'
                    + '</div>';
            }).join('');
            listContainer.html(html);
            alignPanelToBubble();
        }

        function loadOverview() {
            setListLoading();
            $.ajax({
                url: '/gg_app/pages/ticket/chat_overview.php',
                method: 'POST',
                data: { last_check: lastCheck, last_seen_id: lastSeenId },
                dataType: 'json'
            }).done(function (resp) {
                if (!resp || !resp.success) {
                    listContainer.html('<div class="text-center text-muted p-3">Tidak bisa memuat percakapan</div>');
                    return;
                }
                renderConversations(resp.conversations || []);
            }).fail(function () {
                listContainer.html('<div class="text-center text-muted p-3">Koneksi bermasalah</div>');
            });
        }

        function formatAttachments(att) {
            if (!att || !att.length) return '';
            return '<div class="attachments">' + att.map(function (a) {
                var path = a.file_path || '';
                if (path.indexOf('/gg_app/') !== 0) { path = '/gg_app/' + path.replace(/^\/+/, ''); }
                var name = path.split('/').pop();
                return '<a href="' + path + '" target="_blank" rel="noopener noreferrer"><i class="fas fa-paperclip"></i> ' + name + '</a>';
            }).join('') + '</div>';
        }

        function buildReceiptTooltip(msg) {
            if (!msg || !msg.receipt) { return 'Terkirim'; }
            if (msg.receipt.read && msg.receipt.readers && msg.receipt.readers.length) {
                var parts = msg.receipt.readers.map(function (r) {
                    var base = r.name || ('User ' + (r.user_id || ''));
                    return r.read_at ? base + ' (' + r.read_at + ')' : base;
                });
                return 'Dibaca oleh ' + parts.join(', ');
            }
            return 'Terkirim';
        }

        function renderThread(messages, options) {
            options = options || {};
            var preserveScroll = !!options.preserveScroll;
            var shouldStickBottom = true;
            if (preserveScroll && threadBody.length && threadBody[0]) {
                var el = threadBody[0];
                var diff = el.scrollHeight - el.scrollTop - el.clientHeight;
                shouldStickBottom = diff < 80;
            }
            if (!messages || !messages.length) {
                threadBody.html('<div class="text-center text-muted">Belum ada pesan</div>');
                return;
            }
            var html = messages.map(function (msg) {
                var attachments = formatAttachments(msg.attachments);
                var statusHtml = '';
                var senderInfo = '';
                if (msg.is_me) {
                    var read = msg.receipt && msg.receipt.read;
                    var icon = read ? 'fa-check-double' : 'fa-check';
                    var cls = read ? 'read' : 'sent';
                    var tooltip = escapeHtml(buildReceiptTooltip(msg));
                    statusHtml = '<span class="ticket-chat-status ' + cls + '" title="' + tooltip + '"><i class="fas ' + icon + '"></i></span>';
                } else {
                    // Show sender name for others
                    var adminIcon = msg.is_admin ? '<i class="fas fa-crown text-warning me-1" title="Administrator"></i> ' : '';
                    senderInfo = '<div class="small text-muted mb-1 ms-1">' + adminIcon + escapeHtml(msg.sender_name || 'User') + '</div>';
                }
                return '<div class="ticket-chat-thread-message ' + (msg.is_me ? 'me' : '') + '">'
                    + senderInfo
                    + '<div class="bubble">' + (msg.message_html || '') + attachments + '</div>'
                    + '<div class="ticket-chat-meta text-muted small mt-1">' + (msg.created_at_fmt || '') + statusHtml + '</div>'
                    + '</div>';
            }).join('');
            threadBody.html(html);
            enhanceThreadImages(threadBody);
            if (shouldStickBottom && threadBody.length && threadBody[0]) {
                threadBody.scrollTop(threadBody[0].scrollHeight);
            }
            alignPanelToBubble();
        }

        function scheduleReadSync(ticketId, lastMessageId) {
            if (!ticketId || !lastMessageId) { return; }
            if (readSyncTimer) { clearTimeout(readSyncTimer); }
            readSyncTimer = setTimeout(function () {
                $.ajax({
                    url: '/gg_app/pages/ticket/chat_mark_read.php',
                    method: 'POST',
                    data: { ticket_id: ticketId, last_message_id: lastMessageId },
                    dataType: 'json'
                });
            }, 400);
        }

        function startThreadRefresh() {
            if (threadRefreshTimer) { return; }
            threadRefreshTimer = setInterval(function () {
                if (currentTicket && !panel.hasClass('d-none')) {
                    loadThread(currentTicket.ticket_id, { silent: true });
                }
            }, 5000);
        }

        function stopThreadRefresh() {
            if (!threadRefreshTimer) { return; }
            clearInterval(threadRefreshTimer);
            threadRefreshTimer = null;
        }

        function updateCompletionState() {
            var hasTicket = !!currentTicket;
            var isClosed = !!(hasTicket && currentTicket.closed_at);

            // Check if user is assigned technician or Administrator
            var isAssignedTech = (hasTicket && currentTicket.assigned_to && currentTicket.assigned_to == userEmpId);
            var isAdmin = (userRole === 'administrator');
            var isAuthorized = isAssignedTech || isAdmin;

            if (replyForm.length && closedMessage.length) {
                if (isClosed) {
                    replyForm.addClass('d-none');
                    closedMessage.removeClass('d-none');
                } else {
                    replyForm.removeClass('d-none');
                    closedMessage.addClass('d-none');
                }
            }
            if (completeBtn.length) {
                if (!hasTicket || !isAuthorized) {
                    completeBtn.addClass('d-none').prop('disabled', true);
                    if (completeBtnLabel) { completeBtn.text(completeBtnLabel); }
                } else if (isClosed) {
                    // Change to Hapus button
                    completeBtn.removeClass('d-none btn-success').addClass('btn-danger')
                        .prop('disabled', false)
                        .text('Hapus');
                } else {
                    // Show Selesai button
                    completeBtn.removeClass('d-none btn-danger').addClass('btn-success')
                        .prop('disabled', completionInProgress);
                    if (completeBtnLabel && completeBtn.text() !== completeBtnLabel && !completionInProgress) {
                        completeBtn.text(completeBtnLabel);
                    }
                }
            }
        }

        function completeCurrentTicket() {
            if (!currentTicket || completionInProgress) { return; }

            var requiresAssetNote = !!currentTicket.has_asset;
            if (requiresAssetNote) {
                if (typeof Swal === 'undefined') {
                    var note = prompt('Ticket ini terhubung dengan asset. Masukkan keterangan maintenance.');
                    if (!note) { alert('Keterangan maintenance wajib diisi'); return; }
                    executeComplete({ maintenance_note: note.trim() });
                    return;
                }
                Swal.fire({
                    title: 'Selesaikan Ticket & Update Asset',
                    html: '<p>Ticket ini terhubung dengan asset. Masukkan keterangan maintenance untuk riwayat asset.</p>' +
                        '<textarea id="ticketChatMaintenanceNote" class="form-control" rows="3" placeholder="Contoh: Ganti SSD, Install Ulang, dll..."></textarea>',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonText: 'Selesai & Simpan',
                    cancelButtonText: 'Batal',
                    preConfirm: function () {
                        var textarea = Swal.getPopup().querySelector('#ticketChatMaintenanceNote');
                        var value = textarea ? textarea.value.trim() : '';
                        if (!value) {
                            Swal.showValidationMessage('Keterangan maintenance wajib diisi');
                        }
                        return { note: value };
                    }
                }).then(function (result) {
                    if (result.isConfirmed && result.value && result.value.note) {
                        executeComplete({ maintenance_note: result.value.note });
                    }
                });
                return;
            }

            if (typeof Swal === 'undefined') {
                if (!confirm('Selesaikan ticket ini?')) { return; }
                executeComplete();
                return;
            }

            Swal.fire({
                title: 'Selesaikan Ticket?',
                text: 'Ticket akan ditandai sebagai selesai.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Selesai',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d'
            }).then(function (result) {
                if (result.isConfirmed) {
                    executeComplete();
                }
            });
        }

        function executeComplete(options) {
            options = options || {};
            completionInProgress = true;
            if (completeBtn.length) {
                completeBtn.prop('disabled', true).removeClass('d-none').text('Memproses...');
            }
            var payload = { ticket_id: currentTicket.ticket_id };
            if (options.maintenance_note) {
                payload.maintenance_note = options.maintenance_note;
            }
            $.ajax({
                url: '/gg_app/pages/ticket/close_ticket.php',
                method: 'POST',
                data: payload,
                dataType: 'json'
            }).done(function (resp) {
                if (resp && resp.success) {
                    currentTicket.closed_at = formatNowSql();

                    try { localStorage.setItem('ticket_status_changed', Date.now().toString()); } catch (e) { }

                    if (typeof window.ticketDataTable !== 'undefined' && window.ticketDataTable) {
                        try { window.ticketDataTable.ajax.reload(null, false); } catch (err) { console.error(err); }
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: resp.message || 'Ticket berhasil diselesaikan',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        alert(resp.message || 'Ticket berhasil diselesaikan');
                    }

                    showList();
                    loadOverview();
                    pollCounts();
                } else {
                    var errMsg = (resp && resp.message) ? resp.message : 'Gagal menyelesaikan ticket';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: errMsg });
                    } else {
                        alert(errMsg);
                    }
                }
            }).fail(function () {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Koneksi bermasalah' });
                } else {
                    alert('Koneksi bermasalah');
                }
            }).always(function () {
                completionInProgress = false;
                if (completeBtn.length) {
                    completeBtn.prop('disabled', false).text(completeBtnLabel || 'Selesai');
                }
                updateCompletionState();
            });
        }

        function deleteTicketFromView() {
            if (!currentTicket) { return; }
            var ticketId = currentTicket.ticket_id;

            listContainer.find('.ticket-chat-list-item[data-id="' + ticketId + '"]').fadeOut(300, function () {
                $(this).remove();
                if (listContainer.find('.ticket-chat-list-item').length === 0) {
                    listContainer.html('<div class="text-center text-muted p-3">Belum ada percakapan</div>');
                }
            });

            showList();
            pollCounts();
        }

        function loadThread(ticketId, options) {
            options = options || {};
            var silent = !!options.silent;
            if (!ticketId) return;
            $.ajax({
                url: '/gg_app/pages/ticket/chat_thread.php',
                method: 'POST',
                data: { ticket_id: ticketId },
                dataType: 'json'
            }).done(function (resp) {
                if (!resp || !resp.success) return;
                currentTicket = resp.ticket;
                threadSubject.text(resp.ticket.subject || 'Ticket');
                var metaParts = [];
                if (resp.ticket.ticket_no) { metaParts.push(resp.ticket.ticket_no); }
                if (resp.ticket.creator_name) { metaParts.push(resp.ticket.creator_name); }
                threadMeta.text(metaParts.join(' · ') || '');

                // Show assigned technician if available
                var assignedContainer = $('#ticketChatThreadAssigned');
                var assignedNameSpan = $('#ticketChatAssignedName');
                if (resp.ticket.assigned_name) {
                    assignedNameSpan.text(resp.ticket.assigned_name);
                    assignedContainer.show();
                } else {
                    assignedContainer.hide();
                }

                updateCompletionState();
                renderThread(resp.messages || [], { preserveScroll: silent });
                var highest = 0;
                (resp.messages || []).forEach(function (msg) {
                    var mid = parseInt(msg.message_id || 0, 10);
                    if (!isNaN(mid) && mid > highest) { highest = mid; }
                });
                if (highest > currentThreadMaxId) {
                    currentThreadMaxId = highest;
                }
                if (highest > latestKnownId) { latestKnownId = highest; }
                markAllRead();
                if (highest > 0) { scheduleReadSync(currentTicket.ticket_id, highest); }
                if (!silent) {
                    $('#ticketChatReplyAttachment').val('');
                    attachmentNames.text('');
                    replyInput.val('');
                    listContainer.addClass('d-none');
                    threadWrapper.removeClass('d-none');
                    startThreadRefresh();
                    alignPanelToBubble();
                }
            });
        }

        function showList() {
            currentTicket = null;
            currentThreadMaxId = 0;
            stopThreadRefresh();
            threadSubject.text('Ticket');
            threadMeta.text('-');
            threadWrapper.addClass('d-none');
            listContainer.removeClass('d-none');
            updateCompletionState();
            alignPanelToBubble();
        }

        function setReplySending(state) {
            replySending = state;
            if (!replyButton.length) { return; }
            replyButton.prop('disabled', state);
            if (state) {
                replyButton.html('<span class="spinner-border spinner-border-sm mr-1"></span>Mengirim...');
            } else {
                replyButton.html(replyButtonHtml || 'Kirim');
            }
        }

        function sendReply() {
            if (!currentTicket || replySending) return;
            var text = replyInput.val().trim();
            if (!text && (!replyAttachment[0].files || !replyAttachment[0].files.length)) return;
            var html = text ? '<p>' + textToHtml(text) + '</p>' : '';
            var formData = new FormData();
            formData.append('ticket_id', currentTicket.ticket_id);
            formData.append('message', html);
            var files = replyAttachment[0].files || [];
            for (var i = 0; i < files.length; i++) { formData.append('attachments[]', files[i]); }
            setReplySending(true);
            $.ajax({
                url: '/gg_app/pages/ticket/reply_ticket.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json'
            }).done(function (resp) {
                if (resp && resp.success) {
                    replyInput.val('');
                    replyAttachment.val('');
                    attachmentNames.text('');
                    loadThread(currentTicket.ticket_id);
                    pollCounts();
                } else {
                    alert(resp && resp.message ? resp.message : 'Gagal mengirim pesan');
                }
            }).fail(function () {
                alert('Koneksi bermasalah');
            }).always(function () {
                setReplySending(false);
            });
        }

        bubble.on('click', function (e) {
            if ($(e.target).closest('.ticket-chat-close-icon').length) {
                return;
            }
            panel.toggleClass('d-none');
            if (!panel.hasClass('d-none')) {
                markAllRead();
                loadOverview();
                alignPanelToBubble();
            } else {
                showList();
            }
        });
        bubble.on('click', '.ticket-chat-close-icon', function (e) {
            e.preventDefault();
            e.stopPropagation();
            hideBubbleUntilNew();
        });

        listContainer.on('click', '.ticket-chat-list-item', function () {
            var ticketId = $(this).data('id');
            loadThread(ticketId);
        });

        backBtn.on('click', function () { showList(); });
        closeBtn.on('click', function () { panel.addClass('d-none'); showList(); });
        refreshBtn.on('click', function () { loadOverview(); });
        openDetailBtn.on('click', function () { if (currentTicket) window.location.href = '/gg_app/pages/ticket/detail.php?id=' + currentTicket.ticket_id; });
        completeBtn.on('click', function (e) {
            e.preventDefault();
            if (currentTicket && currentTicket.closed_at) {
                // Ticket is closed, delete from view
                deleteTicketFromView();
            } else {
                // Ticket is open, complete it
                completeCurrentTicket();
            }
        });
        replyButton.on('click', sendReply);
        replyInput.on('keypress', function (e) { if (e.which === 13 && !e.shiftKey) { e.preventDefault(); sendReply(); } });
        replyAttachment.on('change', function () {
            var names = [].map.call(this.files || [], function (f) { return f.name; });
            attachmentNames.text(names.length ? names.join(', ') : '');
        });

        applyTheme();
        applyBubblePosition();
        initBubbleDrag();
        applyBubbleState();
        autoActivateFromPageContext();
        ensureNotificationPermission();
        updateCompletionState();
        pollCounts();
        pollTimer = setInterval(pollCounts, 15000);

        $(window).on('scroll', function () { alignPanelToBubble(); });
    }

    waitForJquery(0);
})();

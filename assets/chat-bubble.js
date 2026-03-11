/**
 * Super Speedy Chat - Front-end Chat Bubble
 * Vanilla JS, no jQuery dependency.
 */
(function () {
    'use strict';

    if (typeof ssc_config === 'undefined') {
        return;
    }

    var config = ssc_config;
    var state = {
        open: false,
        conversationId: 0,
        lastMessageId: 0,
        visitorHash: '',
        pollTimer: null,
        pollInterval: (parseInt(config.poll_interval, 10) || 2000),
        idlePollInterval: (parseInt(config.idle_poll_interval, 10) || 5000),
        currentInterval: 0,
        idleTimeout: null,
        isIdle: false,
        messageCount: 0,
        unreadCount: 0,
        emailProvided: false,
        sessionStarted: false,
        timeoutTriggered: false,
        firstVisitorMessageAt: 0,
        sounds: {}
    };

    // ---------------------------------------------------------------
    // DOM Creation
    // ---------------------------------------------------------------

    function createWidget() {
        var wrapper = document.createElement('div');
        wrapper.id = 'ssc-wrapper';

        if (config.bubble_position === 'bottom-left') {
            wrapper.className = 'ssc-position-bottom-left';
        }

        // Apply Customizer color overrides via CSS custom properties.
        if (config.primary_color) {
            document.documentElement.style.setProperty('--ssc-primary', config.primary_color);
            document.documentElement.style.setProperty('--ssc-primary-dark', darkenColor(config.primary_color, 20));
        }
        if (config.header_bg_color) {
            document.documentElement.style.setProperty('--ssc-header-bg', config.header_bg_color);
        }
        if (config.visitor_msg_color) {
            document.documentElement.style.setProperty('--ssc-visitor-bg', config.visitor_msg_color);
        }

        // Trigger button
        var trigger = document.createElement('button');
        trigger.id = 'ssc-trigger';
        trigger.setAttribute('aria-label', 'Open chat');

        var triggerIcon = getTriggerIconHtml(config.trigger_icon, config.trigger_icon_image);
        trigger.innerHTML =
            triggerIcon +
            '<svg class="ssc-close-icon" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>';

        var badge = document.createElement('span');
        badge.id = 'ssc-unread-badge';
        badge.textContent = '0';
        trigger.appendChild(badge);

        // Widget
        var widget = document.createElement('div');
        widget.id = 'ssc-widget';

        var headerHtml = '<div class="ssc-header">';
        if (config.header_image) {
            headerHtml += '<img class="ssc-header-image" src="' + escapeAttr(config.header_image) + '" alt="" />';
        }
        headerHtml += '<span class="ssc-header-title">' + escapeHtml(config.window_title || 'Chat') + '</span>';
        headerHtml += '<span class="ssc-header-status" id="ssc-status"></span>';
        headerHtml += '</div>';

        widget.innerHTML =
            headerHtml +
            '<div class="ssc-messages" id="ssc-messages"></div>' +
            '<div class="ssc-email-prompt" id="ssc-email-prompt">' +
                '<p>Leave your email and we\'ll notify you when we reply:</p>' +
                '<div class="ssc-email-prompt-form">' +
                    '<input type="email" id="ssc-email-input" placeholder="your@email.com" />' +
                    '<button id="ssc-email-submit">Send</button>' +
                '</div>' +
            '</div>' +
            '<div class="ssc-login-prompt" id="ssc-login-prompt">' +
                '<a href="' + (config.login_url || '/wp-login.php') + '">Log in</a> or <a href="' + (config.register_url || '/wp-login.php?action=register') + '">create an account</a> to save your chat history.' +
            '</div>' +
            '<div class="ssc-input-area">' +
                '<textarea id="ssc-input" rows="2" placeholder="Type a message..." maxlength="' + (config.max_message_length || 500) + '"></textarea>' +
                '<button class="ssc-send-btn" id="ssc-send-btn">Send</button>' +
            '</div>';

        wrapper.appendChild(trigger);
        wrapper.appendChild(widget);
        document.body.appendChild(wrapper);

        // Event listeners
        trigger.addEventListener('click', toggleChat);
        document.getElementById('ssc-send-btn').addEventListener('click', sendMessage);
        document.getElementById('ssc-input').addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        document.getElementById('ssc-email-submit').addEventListener('click', submitEmail);
        document.getElementById('ssc-email-input').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitEmail();
            }
        });

        // Auto-grow textarea
        document.getElementById('ssc-input').addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 80) + 'px';
            resetIdle();
        });

        // Preload sounds
        if (config.sounds_enabled) {
            preloadSounds();
        }
    }

    // ---------------------------------------------------------------
    // Sound
    // ---------------------------------------------------------------

    function preloadSounds() {
        var soundFiles = {
            open: 'woosh.mp3',
            close: 'woosh.mp3',
            message: 'msg.mp3'
        };

        for (var key in soundFiles) {
            if (soundFiles.hasOwnProperty(key)) {
                var audio = new Audio(config.sounds_url + soundFiles[key]);
                audio.preload = 'auto';
                audio.volume = 0.3;
                state.sounds[key] = audio;
            }
        }
    }

    function playSound(name) {
        if (!config.sounds_enabled || !state.sounds[name]) return;
        try {
            var sound = state.sounds[name];
            sound.currentTime = 0;
            sound.play().catch(function () { /* autoplay blocked, ignore */ });
        } catch (e) { /* ignore */ }
    }

    // ---------------------------------------------------------------
    // Toggle Chat
    // ---------------------------------------------------------------

    function toggleChat() {
        if (state.open) {
            closeChat();
        } else {
            openChat();
        }
    }

    function openChat() {
        state.open = true;
        var widget = document.getElementById('ssc-widget');
        var trigger = document.getElementById('ssc-trigger');

        widget.classList.remove('ssc-hiding');
        widget.classList.add('ssc-visible');
        trigger.classList.add('ssc-open');

        playSound('open');

        // Clear unread.
        state.unreadCount = 0;
        updateBadge();

        // Start session if needed.
        if (!state.sessionStarted) {
            startSession();
        } else {
            startPolling();
        }

        // Focus input.
        setTimeout(function () {
            document.getElementById('ssc-input').focus();
        }, 300);
    }

    function closeChat() {
        state.open = false;
        var widget = document.getElementById('ssc-widget');
        var trigger = document.getElementById('ssc-trigger');

        widget.classList.add('ssc-hiding');
        trigger.classList.remove('ssc-open');

        playSound('close');
        stopPolling();

        setTimeout(function () {
            widget.classList.remove('ssc-visible');
            widget.classList.remove('ssc-hiding');
        }, 300);
    }

    // ---------------------------------------------------------------
    // Session
    // ---------------------------------------------------------------

    function startSession() {
        apiRequest('POST', 'session', {}, function (data) {
            state.sessionStarted = true;
            state.conversationId = data.conversation_id;
            state.visitorHash = data.visitor_hash;

            var messagesEl = document.getElementById('ssc-messages');
            messagesEl.innerHTML = '';

            // Show welcome message.
            if (config.welcome_message) {
                appendSystemMessage(config.welcome_message, 'ssc-welcome');
            }

            // Render existing messages.
            if (data.messages && data.messages.length > 0) {
                for (var i = 0; i < data.messages.length; i++) {
                    appendMessage(data.messages[i], false);
                }
                state.lastMessageId = data.messages[data.messages.length - 1].id;
                state.messageCount = data.messages.length;
            }

            scrollToBottom();
            startPolling();
        });
    }

    // ---------------------------------------------------------------
    // Messaging
    // ---------------------------------------------------------------

    function sendMessage() {
        var input = document.getElementById('ssc-input');
        var text = input.value.trim();
        if (!text) return;

        var btn = document.getElementById('ssc-send-btn');
        btn.disabled = true;

        apiRequest('POST', 'send', {
            message: text,
            page_url: window.location.href
        }, function (data) {
            btn.disabled = false;
            input.value = '';
            input.style.height = 'auto';
            state.conversationId = data.conversation_id;
            state.messageCount++;

            // Track first visitor message time for timeout.
            if (!state.firstVisitorMessageAt) {
                state.firstVisitorMessageAt = Date.now();
                scheduleTimeoutCheck();
            }

            // Check login prompt.
            checkLoginPrompt();

            // Immediately poll to get the message back.
            pollMessages();
        }, function () {
            btn.disabled = false;
        });
    }

    function appendMessage(msg, isNew) {
        var messagesEl = document.getElementById('ssc-messages');
        var div = document.createElement('div');
        var type = msg.participant_type || 'visitor';

        div.className = 'ssc-msg ssc-msg-' + type;
        if (isNew) {
            div.className += ' ssc-msg-new';
        }

        var html = '';
        if (type !== 'visitor' && type !== 'system') {
            html += '<div class="ssc-msg-sender">' + escapeHtml(msg.display_name || 'Support') + '</div>';
        }
        html += '<p class="ssc-msg-text">' + escapeHtml(msg.message) + '</p>';

        if (msg.created_at) {
            html += '<span class="ssc-msg-time">' + formatTime(msg.created_at) + '</span>';
        }

        div.innerHTML = html;
        messagesEl.appendChild(div);

        // Update lastMessageId.
        if (msg.id && parseInt(msg.id, 10) > state.lastMessageId) {
            state.lastMessageId = parseInt(msg.id, 10);
        }

        if (isNew) {
            scrollToBottom();
        }
    }

    function appendSystemMessage(text, extraClass) {
        var messagesEl = document.getElementById('ssc-messages');
        var div = document.createElement('div');
        div.className = 'ssc-msg ssc-msg-system';
        if (extraClass) div.className += ' ' + extraClass;
        div.innerHTML = '<p class="ssc-msg-text">' + escapeHtml(text) + '</p>';
        messagesEl.appendChild(div);
    }

    // ---------------------------------------------------------------
    // Polling
    // ---------------------------------------------------------------

    function startPolling() {
        stopPolling();
        state.currentInterval = state.pollInterval;
        state.isIdle = false;
        poll();
    }

    function stopPolling() {
        if (state.pollTimer) {
            clearTimeout(state.pollTimer);
            state.pollTimer = null;
        }
        if (state.idleTimeout) {
            clearTimeout(state.idleTimeout);
            state.idleTimeout = null;
        }
    }

    function poll() {
        if (!state.open && !state.conversationId) return;

        pollMessages(function () {
            state.pollTimer = setTimeout(poll, state.currentInterval);
        });

        // Set idle backoff after 30s of no user activity.
        resetIdle();
    }

    function pollMessages(callback) {
        if (!state.conversationId) {
            if (callback) callback();
            return;
        }

        apiRequest('GET', 'poll?since_id=' + state.lastMessageId, null, function (data) {
            if (data.messages && data.messages.length > 0) {
                var hadNew = false;
                for (var i = 0; i < data.messages.length; i++) {
                    var msg = data.messages[i];
                    if (parseInt(msg.id, 10) > state.lastMessageId) {
                        appendMessage(msg, true);
                        hadNew = true;

                        // If admin replied, clear timeout state.
                        if (msg.participant_type === 'admin' || msg.participant_type === 'bot') {
                            state.timeoutTriggered = false;
                        }

                        // Sound for non-visitor messages.
                        if (msg.participant_type !== 'visitor') {
                            if (state.open) {
                                playSound('message');
                            } else {
                                state.unreadCount++;
                                updateBadge();
                                playSound('message');
                            }
                        }
                    }
                }

                if (hadNew) {
                    // Reset to fast polling when new messages arrive.
                    state.currentInterval = state.pollInterval;
                    state.isIdle = false;
                }
            }

            if (callback) callback();
        }, function () {
            if (callback) callback();
        });
    }

    function resetIdle() {
        if (state.idleTimeout) {
            clearTimeout(state.idleTimeout);
        }

        if (state.isIdle) {
            state.isIdle = false;
            state.currentInterval = state.pollInterval;
        }

        state.idleTimeout = setTimeout(function () {
            state.isIdle = true;
            state.currentInterval = state.idlePollInterval;
        }, 30000);
    }

    // ---------------------------------------------------------------
    // Unread Badge
    // ---------------------------------------------------------------

    function updateBadge() {
        var badge = document.getElementById('ssc-unread-badge');
        if (!badge) return;

        if (state.unreadCount > 0) {
            badge.textContent = state.unreadCount > 9 ? '9+' : state.unreadCount;
            badge.classList.add('ssc-has-unread');
        } else {
            badge.classList.remove('ssc-has-unread');
        }
    }

    // ---------------------------------------------------------------
    // Email Prompt
    // ---------------------------------------------------------------

    function scheduleTimeoutCheck() {
        var timeout = (parseInt(config.admin_timeout, 10) || 30) * 1000;

        setTimeout(function () {
            if (state.timeoutTriggered || state.emailProvided) return;

            // Check if admin has replied.
            var msgs = document.querySelectorAll('.ssc-msg-admin, .ssc-msg-bot');
            if (msgs.length > 0) return; // Admin already replied.

            state.timeoutTriggered = true;

            if (config.timeout_action === 'show_email_prompt') {
                showEmailPrompt();
            }
        }, timeout);
    }

    function showEmailPrompt() {
        var prompt = document.getElementById('ssc-email-prompt');
        if (prompt) {
            prompt.classList.add('ssc-visible');
        }
    }

    function submitEmail() {
        var input = document.getElementById('ssc-email-input');
        var email = input.value.trim();

        if (!email || !isValidEmail(email)) {
            input.style.borderColor = '#e74c3c';
            return;
        }

        input.style.borderColor = '';

        apiRequest('POST', 'email', { email: email }, function () {
            state.emailProvided = true;
            var prompt = document.getElementById('ssc-email-prompt');
            prompt.innerHTML = '<p style="color:green;margin:0;padding:4px 0;">Thanks! We\'ll email you when we reply.</p>';

            setTimeout(function () {
                prompt.classList.remove('ssc-visible');
            }, 3000);
        });
    }

    // ---------------------------------------------------------------
    // Login Prompt
    // ---------------------------------------------------------------

    function checkLoginPrompt() {
        if (config.is_logged_in) return;

        var promptAfter = parseInt(config.login_prompt_after, 10) || 5;
        if (state.messageCount >= promptAfter) {
            var prompt = document.getElementById('ssc-login-prompt');
            if (prompt) {
                prompt.classList.add('ssc-visible');
            }
        }
    }

    // ---------------------------------------------------------------
    // API Helper
    // ---------------------------------------------------------------

    function apiRequest(method, endpoint, data, onSuccess, onError) {
        var xhr = new XMLHttpRequest();
        var url = config.rest_url + endpoint;

        xhr.open(method, url, true);
        xhr.setRequestHeader('X-WP-Nonce', config.nonce);
        xhr.setRequestHeader('Content-Type', 'application/json');

        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (onSuccess) onSuccess(response);
                } catch (e) {
                    if (onError) onError(e);
                }
            } else {
                if (onError) onError(xhr);
            }
        };

        if (data && method !== 'GET') {
            xhr.send(JSON.stringify(data));
        } else {
            xhr.send();
        }
    }

    // ---------------------------------------------------------------
    // Utilities
    // ---------------------------------------------------------------

    function scrollToBottom() {
        var el = document.getElementById('ssc-messages');
        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function formatTime(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr.replace(' ', 'T'));
        var hours = d.getHours();
        var minutes = d.getMinutes();
        return (hours < 10 ? '0' : '') + hours + ':' + (minutes < 10 ? '0' : '') + minutes;
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function escapeAttr(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function getTriggerIconHtml(iconType, customImageUrl) {
        var svgIcons = {
            chat:    '<svg class="ssc-chat-icon" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>',
            speech:  '<svg class="ssc-chat-icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 5.58 2 10c0 2.24 1.12 4.27 2.94 5.72L4 22l4.83-2.89C9.87 19.7 10.9 20 12 20c5.52 0 10-3.58 10-8s-4.48-8-10-8z"/></svg>',
            headset: '<svg class="ssc-chat-icon" viewBox="0 0 24 24"><path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h3c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z"/></svg>'
        };

        if (iconType === 'custom' && customImageUrl) {
            return '<img class="ssc-chat-icon ssc-trigger-img" src="' + escapeAttr(customImageUrl) + '" alt="" />';
        }

        return svgIcons[iconType] || svgIcons.chat;
    }

    function darkenColor(hex, percent) {
        hex = hex.replace('#', '');
        var r = parseInt(hex.substring(0, 2), 16);
        var g = parseInt(hex.substring(2, 4), 16);
        var b = parseInt(hex.substring(4, 6), 16);
        r = Math.max(0, Math.floor(r * (1 - percent / 100)));
        g = Math.max(0, Math.floor(g * (1 - percent / 100)));
        b = Math.max(0, Math.floor(b * (1 - percent / 100)));
        return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
    }

    // ---------------------------------------------------------------
    // Initialize
    // ---------------------------------------------------------------

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createWidget);
    } else {
        createWidget();
    }

})();

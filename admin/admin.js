/**
 * Super Speedy Chat - Admin JS
 */
(function ($) {
    'use strict';

    if (typeof ssc_admin === 'undefined') {
        return;
    }

    var config = ssc_admin;
    var state = {
        page: 1,
        status: '',
        search: '',
        pollTimer: null,
        lastMessageId: 0
    };

    // ---------------------------------------------------------------
    // Initialization
    // ---------------------------------------------------------------

    $(document).ready(function () {
        if ($('#ssc-conversation-detail').length) {
            initConversationDetail();
        } else if ($('#ssc-conversation-list').length) {
            initTabs();
            initConversationList();
        }
    });

    // ---------------------------------------------------------------
    // Tab Switching (SSS pattern)
    // ---------------------------------------------------------------

    function initTabs() {
        // Click handler for nav tabs.
        $(document).on('click', '.super-speedy-chat .nav-tab-wrapper .nav-tab', function (e) {
            window.history.pushState(null, null, '#' + $(this).index());
            ssc_click_tab($(this).index());
            e.preventDefault();
            e.stopPropagation();
        });

        // Handle browser back/forward.
        $(window).on('popstate', function () {
            var hash = location.hash.replace('#', '');
            var idx = parseInt(hash, 10);
            if (!isNaN(idx)) {
                ssc_click_tab(idx);
            }
        });

        // Restore tab from URL hash on load.
        var hash = location.hash.replace('#', '');
        var idx = parseInt(hash, 10);
        if (!isNaN(idx) && idx > 0) {
            ssc_click_tab(idx);
        }
    }

    function ssc_click_tab(tabindex) {
        var $tabs = $('.super-speedy-chat .nav-tab-wrapper .nav-tab');
        var $chats = $('#ssc-tab-chats');
        var $form = $('#ssc-settings-form');
        var $settingsTabs = $('.super-speedy-chat #ssc-settings-form > .ssc_tab');

        // Update active tab.
        $tabs.removeClass('nav-tab-active');
        $tabs.eq(tabindex).addClass('nav-tab-active');
        $tabs.eq(tabindex).focus();

        if (tabindex === 0) {
            // Chats tab: show chat list, hide settings form.
            $chats.css('display', 'block');
            $form.css('display', 'none');
        } else {
            // Settings tabs: hide chat list, show form + correct settings tab.
            $chats.css('display', 'none');
            $form.css('display', 'block');
            $settingsTabs.css('display', 'none');
            // tabindex 1 = first settings section (index 0 within the form).
            $settingsTabs.eq(tabindex - 1).css('display', 'block');
        }
    }

    // ---------------------------------------------------------------
    // Conversation List
    // ---------------------------------------------------------------

    function initConversationList() {
        loadConversations();

        // Filter buttons
        $(document).on('click', '.ssc-filter-btn', function () {
            $('.ssc-filter-btn').removeClass('active');
            $(this).addClass('active');
            state.status = $(this).data('status') || '';
            state.page = 1;
            loadConversations();
        });

        // Search
        var searchTimeout;
        $('#ssc-search-input').on('input', function () {
            clearTimeout(searchTimeout);
            var val = $(this).val();
            searchTimeout = setTimeout(function () {
                state.search = val;
                state.page = 1;
                loadConversations();
            }, 400);
        });

        // Pagination
        $(document).on('click', '.ssc-page-prev', function () {
            if (state.page > 1) {
                state.page--;
                loadConversations();
            }
        });

        $(document).on('click', '.ssc-page-next', function () {
            state.page++;
            loadConversations();
        });

        // Row click to view
        $(document).on('click', '.ssc-conversation-row', function (e) {
            if ($(e.target).is('a, button')) return;
            var id = $(this).data('id');
            window.location.href = config.rest_url.replace('/wp-json/ssc/v1/', '') + '/wp-admin/admin.php?page=ssc&conversation_id=' + id;
        });

        // Auto-refresh every 10s
        setInterval(function () {
            loadConversations(true);
        }, 10000);
    }

    function loadConversations(silent) {
        var params = {
            page: state.page,
            per_page: 20
        };
        if (state.status) params.status = state.status;
        if (state.search) params.search = state.search;

        apiGet('admin/conversations', params, function (data) {
            renderConversationRows(data.items);
            updatePagination(data);
            if (!silent) {
                updateStats();
            }
        });
    }

    function renderConversationRows(items) {
        var tbody = $('#ssc-conversations-tbody');
        tbody.empty();

        if (!items || items.length === 0) {
            tbody.html('<tr class="ssc-empty-row"><td colspan="6">No conversations found.</td></tr>');
            return;
        }

        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            var row = '<tr class="ssc-conversation-row" data-id="' + item.id + '">' +
                '<td class="ssc-col-visitor"><strong>' + esc(item.visitor_name) + '</strong>' +
                    (item.visitor_email ? '<br><small>' + esc(item.visitor_email) + '</small>' : '') +
                '</td>' +
                '<td class="ssc-col-message">' + esc(item.last_message_preview || '—') + '</td>' +
                '<td class="ssc-col-status"><span class="ssc-status-badge ssc-status-' + item.status + '">' + item.status + '</span></td>' +
                '<td class="ssc-col-started">' + formatDate(item.started_at) + '</td>' +
                '<td class="ssc-col-activity">' + formatDate(item.last_message_at) + '</td>' +
                '<td class="ssc-col-actions"><a href="admin.php?page=ssc&conversation_id=' + item.id + '" class="button button-small">View</a></td>' +
                '</tr>';
            tbody.append(row);
        }
    }

    function updatePagination(data) {
        var info = 'Page ' + data.page + ' of ' + data.total_pages + ' (' + data.total + ' total)';
        $('#ssc-page-info').text(info);
        $('.ssc-page-prev').prop('disabled', data.page <= 1);
        $('.ssc-page-next').prop('disabled', data.page >= data.total_pages);
    }

    function updateStats() {
        // Get counts for different statuses
        apiGet('admin/conversations', { status: 'active', per_page: 1 }, function (data) {
            $('#ssc-stat-active').text(data.total);
        });

        apiGet('admin/conversations', { status: 'waiting', per_page: 1 }, function (data) {
            $('#ssc-stat-waiting').text(data.total);
        });

        // Total today - no direct endpoint, use total from current query
        apiGet('admin/conversations', { per_page: 1 }, function (data) {
            $('#ssc-stat-today').text(data.total);
        });
    }

    // ---------------------------------------------------------------
    // Conversation Detail
    // ---------------------------------------------------------------

    function initConversationDetail() {
        var conversationId = config.conversation_id;
        if (!conversationId) return;

        loadConversation(conversationId);

        // Send reply
        $('#ssc-send-reply').on('click', function () {
            sendReply(conversationId);
        });

        $('#ssc-reply-textarea').on('keydown', function (e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                sendReply(conversationId);
            }
        });

        // Close conversation
        $('#ssc-close-conversation').on('click', function () {
            if (confirm('Close this conversation?')) {
                apiPost('admin/close/' + conversationId, {}, function () {
                    window.location.href = 'admin.php?page=ssc';
                });
            }
        });

        // Poll for new messages every 3 seconds.
        state.pollTimer = setInterval(function () {
            pollConversation(conversationId);
        }, 3000);
    }

    function loadConversation(id) {
        apiGet('admin/conversation/' + id, {}, function (data) {
            // Populate sidebar info.
            var conv = data.conversation;
            $('#ssc-info-name').text(conv.visitor_name || '—');
            $('#ssc-info-email').text(conv.visitor_email || '—');
            $('#ssc-info-ip').text(conv.ip_address || '—');
            $('#ssc-info-referrer').text(conv.referrer_url || '—');
            $('#ssc-info-useragent').text(conv.user_agent || '—');
            $('#ssc-info-page-url').text(conv.last_page_url || '—');
            $('#ssc-info-started').text(formatDate(conv.started_at));

            // Render messages.
            renderMessages(data.messages);
        });
    }

    function pollConversation(id) {
        apiGet('admin/conversation/' + id, { since_id: state.lastMessageId }, function (data) {
            if (data.messages && data.messages.length > 0) {
                appendMessages(data.messages);
            }
        });
    }

    function renderMessages(messages) {
        var thread = $('#ssc-messages-thread');
        thread.empty();

        if (!messages || messages.length === 0) {
            thread.html('<div class="ssc-loading">No messages yet.</div>');
            return;
        }

        for (var i = 0; i < messages.length; i++) {
            appendSingleMessage(messages[i], thread);
        }

        scrollThreadToBottom();
    }

    function appendMessages(messages) {
        var thread = $('#ssc-messages-thread');
        for (var i = 0; i < messages.length; i++) {
            if (parseInt(messages[i].id, 10) > state.lastMessageId) {
                appendSingleMessage(messages[i], thread);
            }
        }
        scrollThreadToBottom();
    }

    function appendSingleMessage(msg, thread) {
        var type = msg.participant_type || 'visitor';
        var cannedBtn = '';
        if (type === 'admin') {
            cannedBtn = '<button class="ssc-save-canned-btn" title="Save as canned response" data-msg-id="' + (msg.id || '') + '">&#9733;</button>';
        }

        var html = '<div class="ssc-message ssc-message-' + type + '" data-msg-text="' + escAttr(msg.message) + '">' +
            '<div class="ssc-message-header">' +
                '<strong class="ssc-message-sender">' + esc(msg.display_name || 'Unknown') + '</strong>' +
                '<span class="ssc-message-type ssc-type-' + type + '">' + type + '</span>' +
                '<span class="ssc-message-time">' + formatDate(msg.created_at) + '</span>' +
                cannedBtn +
            '</div>' +
            '<div class="ssc-message-body">' + esc(msg.message) + '</div>' +
            '</div>';

        thread.append(html);

        if (msg.id && parseInt(msg.id, 10) > state.lastMessageId) {
            state.lastMessageId = parseInt(msg.id, 10);
        }
    }

    function escAttr(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function sendReply(conversationId) {
        var textarea = $('#ssc-reply-textarea');
        var message = textarea.val().trim();
        if (!message) return;

        var btn = $('#ssc-send-reply');
        btn.prop('disabled', true);

        apiPost('admin/reply', {
            conversation_id: conversationId,
            message: message
        }, function () {
            textarea.val('');
            btn.prop('disabled', false);
            pollConversation(conversationId);
        }, function () {
            btn.prop('disabled', false);
        });
    }

    function scrollThreadToBottom() {
        var thread = document.getElementById('ssc-messages-thread');
        if (thread) {
            thread.scrollTop = thread.scrollHeight;
        }
    }

    // ---------------------------------------------------------------
    // Save as Canned Response (in conversation detail)
    // ---------------------------------------------------------------

    $(document).on('click', '.ssc-save-canned-btn', function () {
        var $btn = $(this);
        var $msg = $btn.closest('.ssc-message');

        // Already has a form open? Close it.
        if ($msg.next('.ssc-canned-form-inline').length) {
            $msg.next('.ssc-canned-form-inline').remove();
            return;
        }

        var responseText = $msg.attr('data-msg-text') || '';
        var msgId = $btn.data('msg-id') || '';

        // Find the preceding visitor message for the question.
        var questionText = '';
        var $prev = $msg.prevAll('.ssc-message-visitor').first();
        if ($prev.length) {
            questionText = $prev.attr('data-msg-text') || '';
        }

        var form = '<div class="ssc-canned-form-inline">' +
            '<label>Question:</label>' +
            '<textarea class="ssc-canned-question" rows="2">' + esc(questionText) + '</textarea>' +
            '<label>Response:</label>' +
            '<textarea class="ssc-canned-response" rows="3">' + esc(responseText) + '</textarea>' +
            '<label>Category (optional):</label>' +
            '<input type="text" class="ssc-canned-category" placeholder="e.g. billing, setup, general" />' +
            '<div class="ssc-canned-form-actions">' +
                '<button class="button button-primary ssc-canned-save">Save</button>' +
                '<button class="button ssc-canned-cancel">Cancel</button>' +
            '</div>' +
            '</div>';

        $msg.after(form);
        var $form = $msg.next('.ssc-canned-form-inline');

        $form.find('.ssc-canned-save').on('click', function () {
            var data = {
                question: $form.find('.ssc-canned-question').val().trim(),
                response: $form.find('.ssc-canned-response').val().trim(),
                category: $form.find('.ssc-canned-category').val().trim(),
                source_message_id: msgId
            };

            if (!data.response) {
                alert('Response text is required.');
                return;
            }

            apiPost('admin/canned', data, function () {
                $form.html('<p style="color:green;padding:8px 0;">Saved as canned response!</p>');
                setTimeout(function () { $form.remove(); }, 2000);
            });
        });

        $form.find('.ssc-canned-cancel').on('click', function () {
            $form.remove();
        });
    });

    // ---------------------------------------------------------------
    // Canned Responses Tab
    // ---------------------------------------------------------------

    var cannedState = { loaded: false };

    // Load canned responses when their tab becomes visible.
    $(document).on('click', '.super-speedy-chat .nav-tab-wrapper .nav-tab', function () {
        var idx = $(this).index();
        // Canned Responses tab index = 5 (0:Chats, 1:General, 2:Display, 3:Behaviour, 4:Email, 5:Canned, 6:Discord, 7:Status)
        if (idx === 5 && !cannedState.loaded) {
            cannedState.loaded = true;
            loadCannedResponses();
        }
    });

    var cannedSearchTimeout;
    $(document).on('input', '#ssc-canned-search', function () {
        clearTimeout(cannedSearchTimeout);
        var val = $(this).val();
        cannedSearchTimeout = setTimeout(function () {
            loadCannedResponses(val);
        }, 400);
    });

    function loadCannedResponses(search) {
        var params = { per_page: 100 };
        if (search) params.search = search;

        apiGet('admin/canned', params, function (data) {
            renderCannedRows(data.items);
        });
    }

    function renderCannedRows(items) {
        var tbody = $('#ssc-canned-tbody');
        tbody.empty();

        if (!items || items.length === 0) {
            tbody.html('<tr><td colspan="5" style="text-align:center;color:#999;padding:20px;">No canned responses yet. Open a conversation and click the star icon on any admin message to save it.</td></tr>');
            return;
        }

        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            var q = esc(item.question_summary || '');
            var r = esc(item.response_text || '');
            if (q.length > 100) q = q.substring(0, 100) + '...';
            if (r.length > 120) r = r.substring(0, 120) + '...';

            var row = '<tr data-canned-id="' + item.id + '">' +
                '<td class="ssc-col-question">' + q + '</td>' +
                '<td class="ssc-col-response">' + r + '</td>' +
                '<td class="ssc-col-category">' + esc(item.category || '') + '</td>' +
                '<td class="ssc-col-used">' + (item.usage_count || 0) + '</td>' +
                '<td class="ssc-col-actions">' +
                    '<button class="button button-small ssc-canned-edit-btn">Edit</button> ' +
                    '<button class="button button-small ssc-canned-delete-btn">Delete</button>' +
                '</td>' +
                '</tr>';
            tbody.append(row);
        }
    }

    $(document).on('click', '.ssc-canned-edit-btn', function () {
        var $row = $(this).closest('tr');
        var id = $row.data('canned-id');

        // Fetch full data and show edit form.
        apiGet('admin/canned', { per_page: 1, search: '' }, function () {
            // We already have the data in the row, but let's fetch the full item.
        });

        // For simplicity, fetch all and find the one we want.
        apiGet('admin/canned', { per_page: 200 }, function (data) {
            var item = null;
            for (var i = 0; i < data.items.length; i++) {
                if (parseInt(data.items[i].id, 10) === parseInt(id, 10)) {
                    item = data.items[i];
                    break;
                }
            }
            if (!item) return;

            var editRow = '<tr class="ssc-canned-edit-row"><td colspan="5">' +
                '<div class="ssc-canned-edit-form">' +
                '<label>Question:</label><textarea class="ssc-ce-question" rows="2">' + esc(item.question_summary) + '</textarea>' +
                '<label>Response:</label><textarea class="ssc-ce-response" rows="3">' + esc(item.response_text) + '</textarea>' +
                '<label>Category:</label><input type="text" class="ssc-ce-category" value="' + escAttr(item.category || '') + '" />' +
                '<div class="ssc-canned-form-actions">' +
                    '<button class="button button-primary ssc-ce-save" data-id="' + id + '">Update</button> ' +
                    '<button class="button ssc-ce-cancel">Cancel</button>' +
                '</div></div></td></tr>';

            $row.hide().after(editRow);
        });
    });

    $(document).on('click', '.ssc-ce-save', function () {
        var id = $(this).data('id');
        var $editRow = $(this).closest('.ssc-canned-edit-row');
        var data = {
            question: $editRow.find('.ssc-ce-question').val().trim(),
            response: $editRow.find('.ssc-ce-response').val().trim(),
            category: $editRow.find('.ssc-ce-category').val().trim()
        };

        apiPut('admin/canned/' + id, data, function () {
            loadCannedResponses($('#ssc-canned-search').val());
        });
    });

    $(document).on('click', '.ssc-ce-cancel', function () {
        var $editRow = $(this).closest('.ssc-canned-edit-row');
        $editRow.prev('tr').show();
        $editRow.remove();
    });

    $(document).on('click', '.ssc-canned-delete-btn', function () {
        if (!confirm('Delete this canned response?')) return;
        var id = $(this).closest('tr').data('canned-id');

        apiDelete('admin/canned/' + id, function () {
            loadCannedResponses($('#ssc-canned-search').val());
        });
    });

    // ---------------------------------------------------------------
    // Discord Test Connection
    // ---------------------------------------------------------------

    $(document).on('click', '#ssc-discord-test', function () {
        var $btn = $(this);
        var $result = $('#ssc-discord-test-result');
        $btn.prop('disabled', true);
        $result.text('Testing...').css('color', '');

        apiPost('admin/discord/test', {}, function (data) {
            $btn.prop('disabled', false);
            if (data.success) {
                $result.text('Connected! Bot: ' + data.bot_name).css('color', 'green');
            } else {
                $result.text('Failed: ' + (data.message || 'Unknown error')).css('color', 'red');
            }
        }, function () {
            $btn.prop('disabled', false);
            $result.text('Connection failed. Check your bot token.').css('color', 'red');
        });
    });

    // ---------------------------------------------------------------
    // API Helpers
    // ---------------------------------------------------------------

    function apiGet(endpoint, params, onSuccess, onError) {
        var url = config.rest_url + endpoint;
        if (params) {
            var qs = $.param(params);
            if (qs) url += '?' + qs;
        }

        $.ajax({
            url: url,
            method: 'GET',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', config.nonce);
            },
            success: function (data) {
                if (onSuccess) onSuccess(data);
            },
            error: function (xhr) {
                if (onError) onError(xhr);
            }
        });
    }

    function apiPost(endpoint, data, onSuccess, onError) {
        $.ajax({
            url: config.rest_url + endpoint,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', config.nonce);
            },
            success: function (data) {
                if (onSuccess) onSuccess(data);
            },
            error: function (xhr) {
                if (onError) onError(xhr);
            }
        });
    }

    function apiPut(endpoint, data, onSuccess, onError) {
        $.ajax({
            url: config.rest_url + endpoint,
            method: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify(data),
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', config.nonce);
            },
            success: function (data) {
                if (onSuccess) onSuccess(data);
            },
            error: function (xhr) {
                if (onError) onError(xhr);
            }
        });
    }

    function apiDelete(endpoint, onSuccess, onError) {
        $.ajax({
            url: config.rest_url + endpoint,
            method: 'DELETE',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', config.nonce);
            },
            success: function (data) {
                if (onSuccess) onSuccess(data);
            },
            error: function (xhr) {
                if (onError) onError(xhr);
            }
        });
    }

    // ---------------------------------------------------------------
    // Utilities
    // ---------------------------------------------------------------

    function esc(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        var d = new Date(dateStr.replace(' ', 'T'));
        if (isNaN(d.getTime())) return dateStr;

        var now = new Date();
        var diff = now - d;

        // Less than a minute ago
        if (diff < 60000) return 'Just now';
        // Less than an hour
        if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
        // Less than a day
        if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';

        // Otherwise show date
        var month = d.getMonth() + 1;
        var day = d.getDate();
        var hours = d.getHours();
        var mins = d.getMinutes();
        return day + '/' + month + ' ' + (hours < 10 ? '0' : '') + hours + ':' + (mins < 10 ? '0' : '') + mins;
    }

})(jQuery);

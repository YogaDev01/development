@extends('layouts.app')

@section('title', 'TesysAI')

@section('content')

<div class="content" id="aiPageWrap">
    <div class="container-fluid" id="aiPageContainer">
        <div id="aiLayout" class="ai-row">

            <div id="aiSidebar" class="ai-sidebar-col">
                <div class="ai-sidebar-topbar">
                    <button type="button" class="btn btn-block" id="btnNewChat">
                        <i class="fas fa-plus mr-1"></i> Percakapan Baru
                    </button>
                </div>
                <div id="convList" class="ai-sidebar-scroll">
                    <div class="text-muted text-center py-3" id="convEmpty" style="font-size:11px;">Belum ada riwayat percakapan.</div>
                </div>
            </div>

            <div class="card shadow-sm ai-chat-col" id="aiCard">
                <div class="card-header d-flex align-items-center ai-chat-topbar">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnBackToList" title="Kembali ke daftar">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <div style="width:30px;height:30px;background:var(--charcoal);border-radius:50%;display:flex;align-items:center;justify-content:center;margin-right:10px;">
                        <i class="fas fa-robot" style="color:#F5A623;font-size:13px;"></i>
                    </div>
                    <div>
                        <strong style="font-size:13px;" id="convTitle">TesysAI</strong>
                        <div class="text-muted" style="font-size:10.5px;">Tanya soal Budget, Realisasi, Invoice, atau Payment Purchase Order.</div>
                    </div>
                    <div class="ml-auto d-flex align-items-center" style="gap:6px;">
                        <div class="form-check mb-0" style="font-size:11px;">
                            <input type="checkbox" class="form-check-input" id="chkAutoSpeak">
                            <label class="form-check-label" for="chkAutoSpeak" style="cursor:pointer;">Bacakan jawaban</label>
                        </div>
                    </div>
                </div>

                <div class="card-body ai-chat-scroll" id="chatBody">
                    <div id="chatEmpty" class="text-center text-muted py-5">
                        <i class="fas fa-comments d-block mb-2" style="font-size:26px;"></i>
                        Mulai percakapan — ketik atau tekan mic untuk bicara.
                    </div>
                    <div id="chatMessages"></div>
                </div>

                <div class="card-footer ai-chat-footer">
                    <form id="frmChat" class="d-flex align-items-end" style="gap:8px;" autocomplete="off">
                        <button type="button" class="btn btn-outline-secondary" id="btnMic" title="Bicara">
                            <i class="fas fa-microphone"></i>
                        </button>
                        <textarea id="inpMessage" class="form-control" rows="1" placeholder="Tanyakan apa saja..." style="resize:none;"></textarea>
                        <button type="submit" class="btn btn-primary" id="btnSend">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                    <small class="text-muted d-block mt-1" id="micStatus" style="min-height:14px;"></small>
                </div>
            </div>

        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
/*
 * Layout pola sama seperti project-monitoring/show.blade.php: 1 baris
 * tinggi tetap (calc(100vh - Xpx)), header/footer masing-masing kolom
 * flex-shrink:0 (tidak ikut scroll), isi tengah scroll independen.
 * Mobile: sidebar & chat jadi 2 "layar" terpisah (ala WhatsApp), toggle
 * lewat class .mobile-chat-active + tombol kembali, bukan ditumpuk jadi 1.
 */
#aiPageWrap { display:flex; flex-direction:column; padding-bottom:0; }
#aiPageContainer { flex:1 1 auto; display:flex; flex-direction:column; min-height:0; }

.ai-row { display:flex; gap:12px; height:calc(100vh - 170px); min-height:480px; }

.ai-sidebar-col {
    width:230px; flex:0 0 230px; background:#fff; border:1px solid var(--border); border-radius:var(--radius-md);
    display:flex; flex-direction:column; overflow:hidden; height:100%;
}
.ai-sidebar-topbar { padding:10px; flex-shrink:0; border-bottom:1px solid var(--border); }
.ai-sidebar-scroll { flex:1 1 auto; overflow-y:auto; padding:6px; min-height:0; }

#btnNewChat { background:var(--charcoal); color:#fff; font-size:12px; }
#btnNewChat:hover { background:#152A42; color:#fff; }
.conv-item {
    padding:8px 10px; border-radius:var(--radius-sm); cursor:pointer; font-size:12px; margin-bottom:3px;
    display:flex; align-items:center; justify-content:space-between; gap:6px; color:var(--text);
}
.conv-item:hover { background:#F1F3F5; }
.conv-item.active { background:var(--charcoal); color:#fff; }
.conv-item .conv-title { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1 1 auto; }
.conv-item .conv-del { opacity:0; font-size:10px; flex:0 0 auto; }
.conv-item:hover .conv-del { opacity:.6; }
.conv-item .conv-del:hover { opacity:1 !important; color:var(--danger); }

.ai-chat-col { flex:1 1 auto; display:flex; flex-direction:column; height:100%; margin-bottom:0; min-width:0; }
.ai-chat-topbar { flex-shrink:0; }
.ai-chat-scroll { flex:1 1 auto; overflow-y:auto; min-height:0; background:#F8F9FA; }
.ai-chat-footer { flex-shrink:0; }

#btnBackToList { display:none; margin-right:8px; flex-shrink:0; }

@media (max-width: 767.98px) {
    .ai-row { height:calc(100vh - 170px) !important; }
    .ai-sidebar-col { width:100%; flex:0 0 auto; height:100% !important; }
    .ai-chat-col { display:none !important; height:100% !important; }
    .ai-row.mobile-chat-active .ai-sidebar-col { display:none !important; }
    .ai-row.mobile-chat-active .ai-chat-col { display:flex !important; }
    #btnBackToList { display:inline-flex !important; }
}
.ai-msg { display:flex; margin-bottom:14px; }
.ai-msg.user { justify-content:flex-end; }
.ai-bubble {
    max-width: 72%; padding: 9px 13px; border-radius: var(--radius-lg); font-size: 13px; line-height: 1.5;
    white-space: pre-wrap; word-break: break-word;
}
.ai-msg.user .ai-bubble { background:var(--charcoal); color:#fff; border-bottom-right-radius:2px; }
.ai-msg.bot .ai-bubble { background:#fff; border:1px solid var(--border); color:var(--text); border-bottom-left-radius:2px; }
.ai-bubble strong { font-weight:700; }
.ai-bubble .ai-list { margin:2px 0 6px 18px; padding:0; }
.ai-bubble .ai-list li { margin-bottom:3px; }
.ai-bubble .ai-space { height:6px; }
.ai-typing { display:inline-flex; gap:3px; align-items:center; }
.ai-typing span { width:6px; height:6px; border-radius:50%; background:#9CA3AF; animation: ai-blink 1.2s infinite ease-in-out; }
.ai-typing span:nth-child(2) { animation-delay:.2s; }
.ai-typing span:nth-child(3) { animation-delay:.4s; }
@keyframes ai-blink { 0%, 80%, 100% { opacity:.3; } 40% { opacity:1; } }
#btnMic.recording { background:var(--danger); color:#fff; border-color:var(--danger); }
.ai-cr-link { color:#0d6efd; font-weight:600; text-decoration:none; }
.ai-cr-link:hover { text-decoration:underline; }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    var currentConversationId = null;
    var chatUrl          = "{{ route('tesysai.chat') }}";
    var conversationsUrl = "{{ url('tesysai/conversations') }}";
    var budgetRequestUrl   = "{{ url('budget-request') }}";
    var dailyTransactionUrl = "{{ url('daily-transaction') }}";
    var csrfToken         = "{{ csrf_token() }}";

    function scrollToBottom() {
        var el = document.getElementById('chatBody');
        el.scrollTop = el.scrollHeight;
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // Markdown ringan khusus balasan bot: **bold**, list bernomor "1. ", list bullet "- "/"* ".
    // Teks di-escape dulu sebelum diformat, supaya balasan model tidak bisa menyuntik HTML.
    // crLinks: {"CR-0826...": 15, ...} — kode CR valid, jadi link buka modal detail CR.
    // batchLinks: {"100826-27TRX": 12, ...} — kode batch Treasury (Daily Transaction),
    // jadi link buka halaman Daily Transaction (BUKAN modal CR).
    function renderMarkdown(text, crLinks, batchLinks) {
        var escaped = escapeHtml(text).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        // Link markdown standar [teks](url) — dipakai balasan search_web (sumber
        // internet). Cuma http(s), supaya tidak ada javascript:/data: URI nyelip.
        escaped = escaped.replace(/\[([^\[\]]+)\]\((https?:\/\/[^\s()]+)\)/g, '<a href="$2" target="_blank" rel="noopener" class="ai-cr-link">$1</a>');
        var lines = escaped.split('\n');
        var html = '';
        var listBuffer = [];
        var listType = null;

        function flushList() {
            if (!listBuffer.length) return;
            var tag = listType === 'ol' ? 'ol' : 'ul';
            html += '<' + tag + ' class="ai-list">' + listBuffer.map(function (li) { return '<li>' + li + '</li>'; }).join('') + '</' + tag + '>';
            listBuffer = [];
            listType = null;
        }

        lines.forEach(function (line) {
            var numbered = line.match(/^\s*\d+\.\s+(.*)$/);
            var bulleted = line.match(/^\s*[-*]\s+(.*)$/);
            if (numbered) {
                if (listType !== 'ol') { flushList(); listType = 'ol'; }
                listBuffer.push(numbered[1]);
            } else if (bulleted) {
                if (listType !== 'ul') { flushList(); listType = 'ul'; }
                listBuffer.push(bulleted[1]);
            } else {
                flushList();
                html += line.trim() === '' ? '<div class="ai-space"></div>' : '<div>' + line + '</div>';
            }
        });
        flushList();

        // Ubah kode CR yang valid (dari crLinks, bukan sembarang teks) jadi link
        // buka modal detail CR — aman disisipkan di sini karena "html" di titik
        // ini sudah melalui escapeHtml(), dan kode CR-nya sendiri cuma angka/huruf.
        if (crLinks) {
            Object.keys(crLinks).forEach(function (code) {
                var id = crLinks[code];
                var link = '<a href="' + budgetRequestUrl + '?open=' + encodeURIComponent(id) + '" target="_blank" rel="noopener" class="ai-cr-link">' + code + '</a>';
                html = html.split(code).join(link);
            });
        }
        if (batchLinks) {
            Object.keys(batchLinks).forEach(function (code) {
                var id = batchLinks[code];
                var link = '<a href="' + dailyTransactionUrl + '/' + encodeURIComponent(id) + '" target="_blank" rel="noopener" class="ai-cr-link">' + code + '</a>';
                html = html.split(code).join(link);
            });
        }

        return html;
    }

    function appendMessage(role, text, crLinks, batchLinks) {
        $('#chatEmpty').hide();
        var cls = role === 'user' ? 'user' : 'bot';
        var $msg = $('<div class="ai-msg ' + cls + '"><div class="ai-bubble"></div></div>');
        if (role === 'user') {
            $msg.find('.ai-bubble').text(text);
        } else {
            $msg.find('.ai-bubble').html(renderMarkdown(text, crLinks, batchLinks));
        }
        $('#chatMessages').append($msg);
        scrollToBottom();
        return $msg;
    }

    function appendTyping() {
        var $msg = $('<div class="ai-msg bot" id="aiTypingRow"><div class="ai-bubble"><span class="ai-typing"><span></span><span></span><span></span></span></div></div>');
        $('#chatMessages').append($msg);
        scrollToBottom();
    }

    function removeTyping() {
        $('#aiTypingRow').remove();
    }

    function speak(text) {
        if (!$('#chkAutoSpeak').is(':checked')) return;
        if (!('speechSynthesis' in window)) return;
        var plain = text.replace(/\*\*/g, '').replace(/^\s*[-*]\s+/gm, '').replace(/^\s*\d+\.\s+/gm, '');
        window.speechSynthesis.cancel();
        var utter = new SpeechSynthesisUtterance(plain);
        utter.lang = 'id-ID';
        window.speechSynthesis.speak(utter);
    }

    /* ── RIWAYAT PERCAKAPAN (sidebar) ── */

    function loadConversations() {
        $.get(conversationsUrl).done(function (res) {
            var list = res.conversations || [];
            var $list = $('#convList').empty();

            if (!list.length) {
                $list.append('<div class="text-muted text-center py-3" style="font-size:11px;">Belum ada riwayat percakapan.</div>');
                return;
            }

            list.forEach(function (c) {
                var $item = $('<div class="conv-item" data-id="' + c.id + '"></div>');
                var $title = $('<span class="conv-title"></span>').text(c.title || 'Percakapan baru');
                var $del = $('<i class="fas fa-trash-alt conv-del" title="Hapus"></i>');
                if (String(c.id) === String(currentConversationId)) $item.addClass('active');
                $item.append($title).append($del);
                $list.append($item);
            });
        });
    }

    function startNewChat() {
        currentConversationId = null;
        $('#chatMessages').empty();
        $('#chatEmpty').show();
        $('#convTitle').text('TesysAI');
        $('.conv-item').removeClass('active');
        $('#aiLayout').addClass('mobile-chat-active'); // mobile: langsung pindah ke layar chat
    }

    function openConversation(id) {
        $.get(conversationsUrl + '/' + id + '/messages').done(function (res) {
            currentConversationId = id;
            $('#chatMessages').empty();
            $('#chatEmpty').hide();

            (res.messages || []).forEach(function (m) {
                appendMessage(m.role === 'user' ? 'user' : 'bot', m.content, m.cr_links || {}, m.batch_links || {});
            });

            if (!(res.messages || []).length) $('#chatEmpty').show();

            $('.conv-item').removeClass('active');
            $('.conv-item[data-id="' + id + '"]').addClass('active');
            var title = $('.conv-item[data-id="' + id + '"] .conv-title').text();
            $('#convTitle').text(title || 'TesysAI');
            $('#aiLayout').addClass('mobile-chat-active'); // mobile: pindah ke layar chat
        });
    }

    $('#btnNewChat').on('click', startNewChat);

    $('#btnBackToList').on('click', function () {
        $('#aiLayout').removeClass('mobile-chat-active');
    });

    $('#convList').on('click', '.conv-item', function (e) {
        if ($(e.target).hasClass('conv-del')) return;
        openConversation($(this).data('id'));
    });

    $('#convList').on('click', '.conv-del', function (e) {
        e.stopPropagation();
        var $item = $(this).closest('.conv-item');
        var id = $item.data('id');

        Swal.fire({
            title: 'Hapus Percakapan?', text: 'Percakapan ini tidak dapat dikembalikan.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#9a3030', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(function (r) {
            if (!r.isConfirmed) return;

            $.ajax({
                url: conversationsUrl + '/' + id,
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            }).done(function () {
                if (String(id) === String(currentConversationId)) startNewChat();
                loadConversations();
            });
        });
    });

    /* ── KIRIM PESAN ── */

    function sendMessage(text) {
        text = (text || '').trim();
        if (!text) return;

        appendMessage('user', text);
        $('#inpMessage').val('').trigger('input');
        appendTyping();
        $('#btnSend').prop('disabled', true);

        var payload = { message: text };
        if (currentConversationId) payload.conversation_id = currentConversationId;

        $.ajax({
            url: chatUrl,
            method: 'POST',
            data: payload,
            headers: { 'X-CSRF-TOKEN': csrfToken }
        }).done(function (res) {
            removeTyping();
            var reply = res.reply || 'Maaf, tidak ada balasan.';
            var isNewConversation = !currentConversationId;
            currentConversationId = res.conversation_id;
            appendMessage('bot', reply, res.cr_links || {}, res.batch_links || {});
            speak(reply);
            $('#convTitle').text(res.title || 'TesysAI');
            if (isNewConversation) {
                loadConversations();
            } else {
                $('.conv-item').removeClass('active');
                $('.conv-item[data-id="' + currentConversationId + '"]').addClass('active');
            }
        }).fail(function () {
            removeTyping();
            appendMessage('bot', 'Maaf, terjadi kesalahan menghubungi TesysAI. Coba lagi.');
        }).always(function () {
            $('#btnSend').prop('disabled', false);
        });
    }

    $('#frmChat').on('submit', function (e) {
        e.preventDefault();
        sendMessage($('#inpMessage').val());
    });

    $('#inpMessage').on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            $('#frmChat').trigger('submit');
        }
    });

    $('#inpMessage').on('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    /* ── MIC INPUT (Web Speech API) ── */
    var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
        $('#btnMic').prop('disabled', true).attr('title', 'Browser tidak mendukung input suara');
        $('#micStatus').text('Input suara tidak didukung di browser ini — gunakan Chrome/Edge.');
    } else {
        var recognition = new SpeechRecognition();
        recognition.lang = 'id-ID';
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;
        var isRecording = false;

        recognition.onstart = function () {
            isRecording = true;
            $('#btnMic').addClass('recording');
            $('#micStatus').text('Mendengarkan...');
        };
        recognition.onend = function () {
            isRecording = false;
            $('#btnMic').removeClass('recording');
            $('#micStatus').text('');
        };
        recognition.onerror = function (e) {
            $('#micStatus').text('Gagal menangkap suara: ' + e.error);
        };
        recognition.onresult = function (e) {
            var transcript = e.results[0][0].transcript;
            $('#inpMessage').val(transcript);
            sendMessage(transcript);
        };

        $('#btnMic').on('click', function () {
            if (isRecording) {
                recognition.stop();
            } else {
                recognition.start();
            }
        });
    }

    loadConversations();

    // Auto-mulai chat kalau datang dari Dashboard (?start=<teks>) — kirim
    // langsung sekali, lalu bersihkan URL supaya tidak terkirim ulang saat refresh.
    (function () {
        var params = new URLSearchParams(window.location.search);
        var start = params.get('start');
        if (start) {
            history.replaceState(null, '', window.location.pathname);
            sendMessage(start);
        }
    })();
});
</script>
@endpush

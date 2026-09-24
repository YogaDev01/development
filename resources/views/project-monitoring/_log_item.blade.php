<div class="chat-bubble-wrap" data-log-id="{{ $log->id }}" data-scope-stage="{{ $log->project_detail_id }}" data-scope-budget="{{ $log->budget_plan_id }}" data-search-text="{{ strtolower($log->message . ' ' . ($log->user->name ?? '')) }}">
    @if($log->type === 'SYSTEM')
        <div class="text-center my-2">
            <span class="badge badge-light text-muted" style="font-size:11px;font-weight:400;">
                {{ $log->message }} · {{ $log->created_at->format('d/m/Y H:i') }}
            </span>
        </div>
    @else
        <div class="d-flex mb-3 {{ $log->user_id === auth()->id() ? 'justify-content-end' : 'justify-content-start' }}">
            <div class="chat-bubble {{ $log->user_id === auth()->id() ? 'chat-bubble-me' : 'chat-bubble-other' }} {{ $log->attachments->isNotEmpty() ? 'chat-bubble-has-media' : '' }}">
                <div class="chat-bubble-author">{{ $log->user->name ?? 'System' }}</div>

                @if($log->message)
                <div class="chat-bubble-text">{!! $log->message_html !!}</div>
                @endif

                @if($log->attachments->isNotEmpty())
                <div class="chat-bubble-attachments" data-image-group="log-{{ $log->id }}">
                    @foreach($log->attachments as $att)
                        @if($att->isImage())
                            {{-- lazy: tiap gambar = satu request PHP+MySQL (route
                                 /storage terautentikasi); tanpa ini seluruh
                                 riwayat chat memuat puluhan gambar ~1 MB serentak. --}}
                            <img src="{{ asset('storage/'.$att->file_path) }}"
                                 loading="lazy" decoding="async"
                                 class="chat-att-img"
                                 data-full="{{ asset('storage/'.$att->file_path) }}"
                                 data-name="{{ $att->file_name }}">
                        @elseif($att->isVideo())
                            <video src="{{ asset('storage/'.$att->file_path) }}" class="chat-att-video" controls preload="none"></video>
                        @elseif($att->isPdf())
                            <a href="{{ asset('storage/'.$att->file_path) }}" target="_blank" rel="noopener" class="chat-att-file">
                                <i class="fas fa-file-pdf mr-1"></i>{{ $att->file_name }}
                            </a>
                        @else
                            <a href="{{ asset('storage/'.$att->file_path) }}" download="{{ $att->file_name }}" class="chat-att-file">
                                <i class="fas fa-file-download mr-1"></i>{{ $att->file_name }}
                            </a>
                        @endif
                    @endforeach
                </div>
                @endif

                <div class="chat-bubble-time">{{ $log->created_at->format('d/m/Y H:i') }}</div>
            </div>
        </div>
    @endif
</div>

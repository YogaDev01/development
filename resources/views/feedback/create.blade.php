@extends('layouts.app')

@section('title', 'Buat Submission')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ route('feedback.index') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Buat Submission — General Feedback</h1>
            </div>
        </div>

        @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @include('feedback._disclaimer')

        <div class="card card-primary card-outline shadow-sm">
            <div class="card-body">
                <form action="{{ route('feedback.store') }}" method="POST" id="frmFeedback">
                    @csrf

                    <div class="form-group">
                        <label class="font-weight-600">Kategori <span class="text-danger">*</span></label>
                        <select name="category" id="selCategory" class="form-control form-control-sm" required>
                            <option value="">-- Pilih Kategori --</option>
                            <option value="SUGGESTION">Suggestion — Saran</option>
                            <option value="CRITICISM">Criticism — Kritik (wajib disertai saran perbaikan)</option>
                            <option value="CONCERN">Concern — Kekhawatiran/Keresahan</option>
                            <option value="IDEA">Idea — Ide/Gagasan</option>
                            <option value="OTHER">Other — Lainnya</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-600">Judul <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control form-control-sm" maxlength="150"
                               placeholder="Ringkasan singkat submission Anda..." required value="{{ old('title') }}">
                    </div>

                    <!-- MESSAGE TUNGGAL (semua kategori kecuali Criticism) -->
                    <div class="form-group" id="wrapMessage">
                        <label class="font-weight-600">Isi Masukan <span class="text-danger">*</span></label>
                        <textarea name="message" id="inpMessage" class="form-control form-control-sm" rows="5"
                                  placeholder="Tuliskan saran, ide, concern, atau masukan Anda secara lengkap..."></textarea>
                    </div>

                    <!-- CRITICISM: WAJIB DUA BAGIAN -->
                    <div id="wrapCriticism" style="display:none;">
                        <div class="alert alert-warning py-2" style="font-size:11.5px;">
                            <i class="fas fa-info-circle mr-1"></i>
                            Kritik harus disertai saran perbaikan — keduanya wajib diisi, tidak boleh hanya salah satu.
                        </div>
                        <div class="form-group">
                            <label class="font-weight-600">Kritik <span class="text-danger">*</span></label>
                            <textarea name="criticism_text" id="inpCriticism" class="form-control form-control-sm" rows="4"
                                      placeholder="Apa yang menurut Anda perlu dikritisi?"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-600">Saran Perbaikan <span class="text-danger">*</span></label>
                            <textarea name="suggestion_text" id="inpSuggestion" class="form-control form-control-sm" rows="4"
                                      placeholder="Menurut Anda, bagaimana sebaiknya hal ini diperbaiki?"></textarea>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="willing_to_discuss" value="1" class="custom-control-input" id="chkWilling">
                            <label class="custom-control-label font-weight-600" for="chkWilling">
                                Saya bersedia berdiskusi lebih lanjut dengan CEO mengenai hal ini
                            </label>
                        </div>
                    </div>

                    <div class="form-group" id="wrapWhatsapp" style="display:none;">
                        <label class="font-weight-600">No. WhatsApp <span class="text-danger">*</span></label>
                        <input type="text" name="whatsapp_number" id="inpWhatsapp" class="form-control form-control-sm" placeholder="08xxxxxxxxxx">
                        <small class="text-muted">Akan dihubungi oleh CEO lewat nomor ini.</small>
                    </div>

                    <div class="text-right mt-3">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane mr-1"></i> Kirim Submission</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(function () {
    function toggleCategory() {
        var isCriticism = $('#selCategory').val() === 'CRITICISM';
        $('#wrapMessage').toggle(!isCriticism);
        $('#wrapCriticism').toggle(isCriticism);
        $('#inpMessage').prop('required', !isCriticism);
        $('#inpCriticism').prop('required', isCriticism);
        $('#inpSuggestion').prop('required', isCriticism);
    }
    $('#selCategory').on('change', toggleCategory);
    toggleCategory();

    function toggleWhatsapp() {
        var willing = $('#chkWilling').is(':checked');
        $('#wrapWhatsapp').toggle(willing);
        $('#inpWhatsapp').prop('required', willing);
    }
    $('#chkWilling').on('change', toggleWhatsapp);
    toggleWhatsapp();
});
</script>
@endpush

<?php

namespace App\Http\Controllers;

use App\Models\FeedbackSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeedbackController extends Controller
{
    /**
     * Pihak berwenang = superadmin, atau role yang diberi akses menu
     * 'feedback-inbox' lewat Role > Menu Access (mis. role CEO).
     * Dipakai untuk gerbang lihat-semua-submission & aksi respond/close,
     * TERPISAH dari sistem role KPI/Performance Management manapun.
     */
    private function isAuthorizedViewer(): bool
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }
        if (!$user->role) {
            return false;
        }
        if (strtolower($user->role->name) === 'superadmin') {
            return true;
        }
        return $user->role->hasMenu('feedback-inbox');
    }

    /*
    |--------------------------------------------------------------------------
    | RIWAYAT SAYA — setiap karyawan hanya lihat submission miliknya sendiri
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $submissions = FeedbackSubmission::where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('feedback.index', compact('submissions'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE / STORE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        return view('feedback.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'category'            => 'required|in:' . implode(',', FeedbackSubmission::CATEGORIES),
            'title'               => 'required|string|max:150',
            'message'             => 'required_unless:category,CRITICISM|nullable|string|max:5000',
            'criticism_text'      => 'required_if:category,CRITICISM|nullable|string|max:5000',
            'suggestion_text'     => 'required_if:category,CRITICISM|nullable|string|max:5000',
            'willing_to_discuss'  => 'nullable|boolean',
            'whatsapp_number'     => 'required_if:willing_to_discuss,1|nullable|string|max:20',
        ], [
            'criticism_text.required_if'  => 'Bagian Kritik wajib diisi untuk kategori Criticism.',
            'suggestion_text.required_if' => 'Bagian Saran wajib diisi untuk kategori Criticism — kritik tanpa saran perbaikan tidak diperbolehkan.',
            'message.required_unless'     => 'Isi masukan wajib diisi.',
            'whatsapp_number.required_if' => 'No. WhatsApp wajib diisi kalau Anda bersedia dihubungi.',
        ]);

        $willing = $request->boolean('willing_to_discuss');

        FeedbackSubmission::create([
            'user_id'            => Auth::id(),
            'category'           => $request->category,
            'title'              => $request->title,
            'message'            => $request->category === 'CRITICISM' ? null : $request->message,
            'criticism_text'     => $request->category === 'CRITICISM' ? $request->criticism_text : null,
            'suggestion_text'    => $request->category === 'CRITICISM' ? $request->suggestion_text : null,
            'willing_to_discuss' => $willing,
            'whatsapp_number'    => $willing ? $request->whatsapp_number : null,
            'status'             => 'SUBMITTED',
        ]);

        return redirect(route('feedback.index'))
            ->with('success', 'Submission Anda berhasil dikirim. Terima kasih atas masukannya.');
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW — pemilik ATAU pihak berwenang saja
    |--------------------------------------------------------------------------
    */

    public function show(int $id)
    {
        $submission = FeedbackSubmission::with(['submitter', 'reviewedBy', 'respondedBy', 'closedBy'])->findOrFail($id);

        $authorized = $this->isAuthorizedViewer();
        $isOwner    = $submission->user_id === Auth::id();

        if (!$isOwner && !$authorized) {
            abort(403, 'Anda tidak memiliki akses untuk melihat submission ini.');
        }

        // Auto-mark REVIEWED begitu pihak berwenang (bukan pemilik) membuka detailnya.
        if ($authorized && !$isOwner && $submission->status === 'SUBMITTED') {
            $submission->update([
                'status'      => 'REVIEWED',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);
        }

        return view('feedback.show', [
            'submission' => $submission,
            'isOwner'    => $isOwner,
            'authorized' => $authorized,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | INBOX — daftar SELURUH submission, khusus pihak berwenang
    |--------------------------------------------------------------------------
    */

    public function inbox()
    {
        $submissions = FeedbackSubmission::with('submitter')->latest()->get();

        $summary = [
            'total'     => $submissions->count(),
            'submitted' => $submissions->where('status', 'SUBMITTED')->count(),
            'reviewed'  => $submissions->where('status', 'REVIEWED')->count(),
            'responded' => $submissions->where('status', 'RESPONDED')->count(),
            'closed'    => $submissions->where('status', 'CLOSED')->count(),
        ];

        return view('feedback.inbox', compact('submissions', 'summary'));
    }

    /*
    |--------------------------------------------------------------------------
    | RESPOND & CLOSE — hanya pihak berwenang
    |--------------------------------------------------------------------------
    */

    public function respond(Request $request, int $id)
    {
        if (!$this->isAuthorizedViewer()) {
            abort(403);
        }

        $submission = FeedbackSubmission::findOrFail($id);

        if ($submission->status === 'CLOSED') {
            return back()->with('error', 'Submission ini sudah Closed.');
        }

        $request->validate([
            'response_text' => 'required|string|max:5000',
        ]);

        $submission->update([
            'response_text' => $request->response_text,
            'responded_by'  => Auth::id(),
            'responded_at'  => now(),
            'status'        => 'RESPONDED',
        ]);

        return back()->with('success', 'Tanggapan berhasil dikirim.');
    }

    public function close(int $id)
    {
        if (!$this->isAuthorizedViewer()) {
            abort(403);
        }

        $submission = FeedbackSubmission::findOrFail($id);

        if ($submission->status !== 'RESPONDED') {
            return back()->with('error', 'Submission harus ditanggapi (Responded) terlebih dahulu sebelum ditandai selesai.');
        }

        $submission->update([
            'status'    => 'CLOSED',
            'closed_by' => Auth::id(),
            'closed_at' => now(),
        ]);

        return back()->with('success', 'Submission ditandai selesai (Closed).');
    }
}

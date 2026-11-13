<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\CandidateLog;
use App\Models\ManpowerRequest;
use App\Support\DocumentSequence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Kandidat / Talent Pool (HRIS) — tahap 2b. Hanya HR_ROLES.
 */
class CandidateController extends Controller
{
    /** Kolom template impor (sama dengan mockup). */
    private const IMPORT_HEADERS = ['name', 'address', 'phone_number', 'place_of_birth', 'date_of_birth', 'gender',
        'marital_status', 'last_education', 'posisi', 'grade', 'sumber'];

    private function authorizeHr(): void
    {
        abort_unless(Auth::user()->hasRole(ManpowerRequest::HR_ROLES), 403, 'Hanya HR yang dapat mengelola kandidat.');
    }

    public function index(Request $request)
    {
        $this->authorizeHr();

        $q = Candidate::with(['recruitments', 'logs']);
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"));
        }
        if ($pos = $request->query('position')) {
            $q->where('position', $pos);
        }
        $rows  = $q->orderBy('name')->get();
        $avail = $request->query('availability');
        if ($avail) {
            $rows = $rows->filter(fn ($c) => $c->availability() === $avail)->values();
        }

        return view('candidates.index', [
            'rows' => $rows,
            'positions' => Candidate::whereNotNull('position')->distinct()->orderBy('position')->pluck('position'),
        ]);
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:150', 'phone' => 'required|string|max:30', 'birth_date' => 'required|date',
            'address' => 'nullable|string|max:500', 'birth_place' => 'nullable|string|max:100',
            'gender' => 'nullable|in:L,P', 'marital_status' => 'nullable|in:' . implode(',', Candidate::MARITAL),
            'education' => 'nullable|in:' . implode(',', Candidate::EDUCATION),
            'position' => 'nullable|string|max:100', 'grade' => 'nullable|in:' . implode(',', ManpowerRequest::GRADES),
            'source' => 'nullable|in:' . implode(',', Candidate::SOURCES),
        ];
    }

    private function createCandidate(array $data): Candidate
    {
        $n = DocumentSequence::next('candidate', fn () => 0);

        return Candidate::create($data + ['code' => 'CND-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'created_by' => Auth::id()]);
    }

    public function store(Request $request)
    {
        $this->authorizeHr();
        $data = $request->validate($this->rules());

        $c = DB::transaction(function () use ($data) {
            $c = $this->createCandidate($data);
            $this->log($c, 'Masuk Talent Pool dari ' . ($data['source'] ?? '-') . ' — melamar ' . ($data['position'] ?? '-') . ' (' . ($data['grade'] ?? '-') . ')');

            return $c;
        });

        return redirect()->route('candidates.show', $c)->with('success', "Talent {$c->name} disimpan.");
    }

    public function show(Candidate $candidate)
    {
        $this->authorizeHr();
        $candidate->load(['documents', 'logs.user', 'recruitments.detail.request', 'hiredEmployee']);

        return view('candidates.show', ['c' => $candidate]);
    }

    /* -------------------------------------------------------------- DOKUMEN */

    public function documentStore(Request $request, Candidate $candidate)
    {
        $this->authorizeHr();
        $request->validate(['file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx']);

        $file = $request->file('file');
        $candidate->documents()->create([
            'path' => $file->store('candidates/' . $candidate->id, 'secure'), 'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(), 'size' => $file->getSize(), 'uploaded_by' => Auth::id(),
        ]);
        $this->log($candidate, 'Dokumen diunggah: ' . $file->getClientOriginalName());

        return back()->with('success', 'Dokumen diunggah.');
    }

    public function documentDownload(Candidate $candidate, CandidateDocument $document)
    {
        $this->authorizeHr();
        abort_unless($document->candidate_id === $candidate->id, 404);

        return Storage::disk('secure')->response($document->path, $document->original_name);
    }

    public function documentDestroy(Candidate $candidate, CandidateDocument $document)
    {
        $this->authorizeHr();
        abort_unless($document->candidate_id === $candidate->id, 404);
        Storage::disk('secure')->delete($document->path);
        $document->delete();
        $this->log($candidate, 'Dokumen dihapus: ' . $document->original_name);

        return back()->with('success', 'Dokumen dihapus.');
    }

    /* -------------------------------------------------------------- IMPOR EXCEL */

    public function template()
    {
        $this->authorizeHr();
        $contoh = ['Andi Nugroho', 'Jl. Melati No. 14', '0812-1111-2222', 'Kupang', '1996-04-12', 'LAKI-LAKI', 'BELUM MENIKAH', 'S1', 'NOC Officer', 'EXPERIENCED', 'JOBSTREET'];

        return response()->streamDownload(function () use ($contoh) {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::IMPORT_HEADERS);
            fputcsv($out, $contoh);
            fclose($out);
        }, 'template-kandidat.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request)
    {
        $this->authorizeHr();
        $request->validate(['file' => 'required|file|max:5120|mimes:csv,txt,xls,xlsx']);

        $sheet = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray(null, true, true, false);
        $head  = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($sheet) ?? []);
        $miss  = array_diff(['name', 'phone_number', 'date_of_birth'], $head);
        if ($miss) {
            return back()->with('error', 'Kolom wajib tidak ada di file: ' . implode(', ', $miss) . '. Gunakan template.');
        }
        if (count($sheet) > 500) {
            return back()->with('error', 'Maksimal 500 baris per impor.');
        }

        $ok = 0;
        $errors = [];
        DB::transaction(function () use ($sheet, $head, &$ok, &$errors) {
            foreach ($sheet as $i => $line) {
                $row = array_combine($head, array_pad(array_slice($line, 0, count($head)), count($head), null));
                if (! array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== '')) {
                    continue;
                }
                $no  = $i + 2;
                $dob = $this->parseDate($row['date_of_birth'] ?? null);
                if (trim((string) ($row['name'] ?? '')) === '' || trim((string) ($row['phone_number'] ?? '')) === '' || ! $dob) {
                    $errors[] = "Baris {$no}: name, phone_number, dan date_of_birth (YYYY-MM-DD) wajib valid.";
                    continue;
                }
                $gender = strtoupper(trim((string) ($row['gender'] ?? '')));
                $c = $this->createCandidate([
                    'name' => trim($row['name']), 'address' => $row['address'] ?? null, 'phone' => trim((string) $row['phone_number']),
                    'birth_place' => $row['place_of_birth'] ?? null, 'birth_date' => $dob,
                    'gender' => str_starts_with($gender, 'L') ? 'L' : (str_starts_with($gender, 'P') ? 'P' : null),
                    'marital_status' => in_array($row['marital_status'] ?? null, Candidate::MARITAL, true) ? $row['marital_status'] : null,
                    'education' => in_array($row['last_education'] ?? null, Candidate::EDUCATION, true) ? $row['last_education'] : null,
                    'position' => $row['posisi'] ?? null,
                    'grade' => in_array($row['grade'] ?? null, ManpowerRequest::GRADES, true) ? $row['grade'] : null,
                    'source' => in_array($row['sumber'] ?? null, Candidate::SOURCES, true) ? $row['sumber'] : null,
                ]);
                $this->log($c, 'Masuk Talent Pool lewat impor Excel — melamar ' . ($c->position ?? '-'));
                $ok++;
            }
        });

        $msg = "{$ok} kandidat diimpor." . ($errors ? ' ' . count($errors) . ' baris dilewati: ' . implode(' ', array_slice($errors, 0, 3)) : '');

        return back()->with($errors && ! $ok ? 'error' : 'success', $msg);
    }

    private function parseDate($v): ?string
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        if (is_numeric($v) && $v > 20000) { // serial tanggal Excel
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($v)->format('Y-m-d');
        }
        $t = strtotime((string) $v);

        return $t ? date('Y-m-d', $t) : null;
    }

    private function log(Candidate $c, string $text): void
    {
        CandidateLog::create(['candidate_id' => $c->id, 'user_id' => Auth::id(), 'description' => $text]);
    }
}

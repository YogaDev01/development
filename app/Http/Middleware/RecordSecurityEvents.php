<?php

namespace App\Http\Middleware;

use App\Models\UserActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jejak audit export/cetak (D18) dan akses ditolak (D11) — Fase Security &
 * Access Control, disetujui pemilik 2026-11-07.
 *
 * Dipasang di grup web SETELAH EnsureUserActive. Hanya MENGAMATI respons:
 * tidak pernah mengubah status, isi, atau header respons, jadi alur dan
 * tampilan bagi user tidak berubah sama sekali. Seluruh pencatatan
 * gagal-aman — error di sini tidak pernah menggagalkan request.
 *
 * Kenapa middleware, bukan di tiap controller: ada 13 route export/cetak di
 * 9 controller; satu titik pengamatan tidak bisa "terlewat" saat route export
 * baru ditambahkan, dan 403 dari gerbang mana pun (role:, menu:, abort(403)
 * di controller) sudah berupa respons ketika kembali ke sini.
 */
class RecordSecurityEvents
{
    /**
     * Prefix nama route → module UserActivityLog. Prefix yang tidak dikenal
     * tetap dicatat dengan module 'security'.
     */
    private const MODULE_BY_ROUTE = [
        'budget-request'       => 'cost_request',
        'budget-plan'          => 'budget_plan',
        'project'              => 'purchase_order',
        'invoice-cash-receipt' => 'invoice',
        'delivery-note'        => 'delivery_note',
        'cost-register'        => 'cost_register',
        'daily-transaction'    => 'treasury',
        'payroll-run'          => 'payroll',
        'todo-list'            => 'todo_list',
        'trouble-ticket'       => 'trouble_ticket',
    ];

    /** Satu catatan "akses ditolak" per user + URL per jendela ini (detik). */
    private const DENIED_WINDOW = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->observe($request, $response);
        } catch (\Throwable $e) {
            Log::warning('RecordSecurityEvents gagal mencatat', ['message' => $e->getMessage()]);
        }

        return $response;
    }

    private function observe(Request $request, Response $response): void
    {
        $user = $request->user();
        if (! $user) {
            return; // tamu: login/route publik punya jejaknya sendiri
        }

        $status = $response->getStatusCode();

        if ($status === 403) {
            $this->recordDenied($request, $user->getKey());

            return;
        }

        $this->recordExport($request, $status);
    }

    /**
     * D11 — akses ditolak. Dibatasi satu baris per (user, method, path) per
     * menit supaya tombol yang diklik berulang atau polling AJAX tidak
     * membanjiri tabel log di shared hosting.
     */
    private function recordDenied(Request $request, int $userId): void
    {
        $path = '/' . ltrim($request->path(), '/');
        $key  = 'secdenied:' . sha1($userId . '|' . $request->method() . '|' . $path);

        if (! Cache::add($key, 1, self::DENIED_WINDOW)) {
            return;
        }

        UserActivityLog::record(
            'security',
            'access_denied',
            'Akses ditolak: ' . $request->method() . ' ' . mb_substr($path, 0, 200),
            null,
            $request->route()?->getName(),
        );
    }

    /** D18 — export & cetak dokumen (route bernama *.export*, *.print). */
    private function recordExport(Request $request, int $status): void
    {
        if ($status < 200 || $status >= 300) {
            return;
        }

        $name = (string) $request->route()?->getName();
        if (! preg_match('/^(.+?)\.(export(?:-[a-z]+)?|print)$/', $name, $m)) {
            return;
        }

        // Controller yang sudah mencatat export-nya sendiri (mis. Cost
        // Register) menandai request — jangan dicatat dua kali.
        if ($request->attributes->get('activity.export_logged')) {
            return;
        }

        $isPrint = $m[2] === 'print';
        $module  = self::MODULE_BY_ROUTE[$m[1]] ?? 'security';
        $id      = $request->route('id');

        UserActivityLog::record(
            $module,
            $isPrint ? 'print' : 'export',
            ($isPrint ? 'Mencetak ' : 'Export ') . str_replace(['.', '-'], ' ', $name) . ($id ? ' #' . $id : ''),
            null,
            $request->isMethod('POST') ? 'baris terpilih: ' . count((array) $request->input('ids', [])) : null,
        );
    }
}

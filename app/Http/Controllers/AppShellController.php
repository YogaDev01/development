<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Halaman muka aplikasi selain Finance (HR, R&D, Logistic, Operation, Project).
 *
 * Selain menampilkan halaman mukanya, di sinilah aplikasi yang sedang dibuka
 * DIINGAT. Sebagian modul dipakai bersama beberapa aplikasi (Customer, Team,
 * Milestone, …) dan route-nya cuma satu, jadi tanpa penanda ini membuka
 * Customer dari HR akan melempar tampilan kembali ke Finance. Yang disimpan
 * hanya nama aplikasinya — tidak ada kaitannya dengan hak akses.
 */
class AppShellController extends Controller
{
    public function show(Request $request, string $app)
    {
        $config = config("apps.items.$app");

        if (!$config) {
            throw new NotFoundHttpException("Aplikasi '$app' tidak dikenal.");
        }

        $request->session()->put('tesys.app', $app);

        // Aplikasi yang sudah punya modul tidak memakai halaman ini: langsung
        // ke 'landing' (halaman muka modulnya), bukan ke 'home' yang justru
        // menunjuk balik ke alamat ini.
        if ($config['ready'] ?? false) {
            $tujuan = $config['landing'] ?? $config['home'];

            return Redirect::to($tujuan === $request->path() || ltrim($tujuan, '/') === $request->path()
                ? '/'
                : $tujuan);
        }

        return view('app-shell.coming-soon', [
            'appKey' => $app,
            'app'    => $config,
        ]);
    }
}

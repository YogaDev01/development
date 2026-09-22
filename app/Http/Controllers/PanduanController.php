<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class PanduanController extends Controller
{
    /**
     * Halaman indeks panduan — hanya topik yang menunya tampil untuk user ini.
     */
    public function index()
    {
        return view('panduan.index', [
            'groups' => $this->visibleGroups(),
        ]);
    }

    /**
     * Halaman detail satu topik panduan.
     */
    public function show(string $slug)
    {
        $groups = $this->visibleGroups();

        $topic = null;
        $groupLabel = null;
        foreach ($groups as $group) {
            foreach ($group['topics'] as $t) {
                if ($t['slug'] === $slug) {
                    $topic = $t;
                    $groupLabel = $group['label'];
                    break 2;
                }
            }
        }

        abort_unless($topic && View::exists("panduan.topics.{$slug}"), 404);

        return view('panduan.show', [
            'groups'     => $groups,
            'topic'      => $topic,
            'groupLabel' => $groupLabel,
        ]);
    }

    /**
     * Grup + topik yang boleh dilihat user aktif, sesuai gate tiap topik.
     * Grup tanpa topik yang lolos akan dihilangkan.
     */
    private function visibleGroups(): array
    {
        $groups = config('panduan.groups', []);
        $result = [];

        foreach ($groups as $key => $group) {
            $topics = array_values(array_filter(
                $group['topics'],
                fn ($topic) => $this->canSee($topic['gate'] ?? null)
            ));

            if ($topics) {
                $group['topics'] = $topics;
                $result[$key] = $group;
            }
        }

        return $result;
    }

    /**
     * Evaluasi satu gate: null (selalu), "menu:<key>", atau "role:a,b".
     */
    private function canSee(?string $gate): bool
    {
        if (empty($gate)) {
            return true;
        }

        $user = Auth::user();
        if (! $user) {
            return false;
        }

        [$type, $value] = array_pad(explode(':', $gate, 2), 2, '');

        if ($type === 'menu') {
            $role = $user->effectiveRole();
            return $role && $role->hasMenu(trim($value));
        }

        if ($type === 'role') {
            return $user->hasRole(array_map('trim', explode(',', $value)));
        }

        return false;
    }
}

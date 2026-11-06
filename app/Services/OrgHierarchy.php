<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Hierarki organisasi yang dibaca dari Team: siapa atasan siapa.
 *
 * TESYS tidak punya relasi "reports_to"; atasan diturunkan dari leader team dan
 * penyambungan antar-team. Ini otoritas approval, bukan akses data — hierarki
 * tidak mewariskan akses (keputusan K3).
 *
 * Graf team dimuat sekali per request (disimpan di atribut request, bukan di
 * instance, karena instance controller bisa dipakai ulang antar-request).
 */
class OrgHierarchy
{
    /**
     * Tempat tiap team di bagan: [team_id => ['type' => member|parent|root, 'team' => id]].
     *
     * Aturan penyambungan team (hanya tampilan & otoritas — tidak memberi akses, K3):
     *  1. Leader team T yang juga ANGGOTA (bukan leader) team B → team T
     *     digantung di bawah kartu orang itu di team B. Contoh: Ketut
     *     memimpin B, Yoga anggota B & leader A → Ketut → Yoga → anggota A.
     *     Bila ia anggota beberapa team, dipilih membership primary, lalu
     *     level terkecil, lalu nama team.
     *  2. Bila tidak ada (1), Induk Team (parent_team_id) → team T beserta
     *     kartu leader-nya digantung di bawah leader team induk.
     *  3. Selain itu team menjadi akar.
     * Penyambungan yang membentuk lingkaran diputus (team menjadi akar).
     */
    public function attachments(Collection $teams): array
    {
        $byId = $teams->keyBy('id');

        // Membership non-leader per user: [user_id => [[team, member], ...]]
        $memberOf = [];
        foreach ($teams as $t) {
            foreach ($t->activeMembers as $m) {
                if ($m->user && (int) $m->user_id !== (int) $t->leader_id) {
                    $memberOf[(int) $m->user_id][] = ['team' => $t, 'member' => $m];
                }
            }
        }

        // 1) Tentukan tempat tiap team: member / parent / root.
        $attach = [];
        foreach ($teams as $t) {
            $leaderId = (int) $t->leader_id;
            $kandidat = collect($memberOf[$leaderId] ?? [])->sortBy(fn ($x) => [
                ($x['member']->is_primary ?? false) ? 0 : 1, (int) $x['member']->level, $x['team']->name,
            ])->first();

            if ($leaderId && $kandidat) {
                $attach[$t->id] = ['type' => 'member', 'team' => (int) $kandidat['team']->id];
            } elseif ($t->parent_team_id && $byId->has($t->parent_team_id)) {
                $attach[$t->id] = ['type' => 'parent', 'team' => (int) $t->parent_team_id];
            } else {
                $attach[$t->id] = ['type' => 'root'];
            }
        }

        // Putus lingkaran: telusuri rantai penyambungan; bila kembali ke
        // dirinya, team itu dijadikan akar.
        foreach ($teams as $t) {
            $lihat = [];
            $cur = $t->id;
            while (($attach[$cur]['type'] ?? 'root') !== 'root') {
                if (isset($lihat[$cur])) {
                    $attach[$t->id] = ['type' => 'root'];
                    break;
                }
                $lihat[$cur] = true;
                $cur = $attach[$cur]['team'];
            }
        }

        return $attach;
    }

    /** Graf team ACTIVE, sekali per request. */
    private function graph(): array
    {
        $request = request();

        if ($graf = $request->attributes->get('org_hierarchy')) {
            return $graf;
        }

        $teams = Team::where('status', 'ACTIVE')->with('activeMembers.user:id')->get()->keyBy('id');
        $graf  = ['teams' => $teams, 'attach' => $this->attachments($teams)];
        $request->attributes->set('org_hierarchy', $graf);

        return $graf;
    }

    /** Team ACTIVE tempat user menjadi anggota ACTIVE. */
    private function teamsOf(int $userId, Collection $teams): Collection
    {
        return $teams->filter(fn ($t) => $t->activeMembers->contains(fn ($m) => (int) $m->user_id === $userId));
    }

    /**
     * Atasan langsung: leader team ACTIVE tempat user menjadi anggota.
     *
     * @return Collection<int, int>
     */
    public function directLeaderIds(int $userId): Collection
    {
        return $this->teamsOf($userId, $this->graph()['teams'])
            ->pluck('leader_id')->filter()->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === $userId)
            ->unique()->values();
    }

    /**
     * Atasan di atas atasan langsung: leader team-team di atas team user,
     * menyusuri penyambungan sampai akar.
     *
     * @return Collection<int, int>
     */
    public function upperLeaderIds(int $userId): Collection
    {
        ['teams' => $teams, 'attach' => $attach] = $this->graph();
        $hasil = collect();

        foreach ($this->teamsOf($userId, $teams) as $team) {
            $lihat = [];
            $cur = (int) $team->id;

            while (isset($attach[$cur]['team']) && ! isset($lihat[$cur])) {
                $lihat[$cur] = true;
                $cur = (int) $attach[$cur]['team'];
                $hasil->push((int) ($teams[$cur]->leader_id ?? 0));
            }
        }

        $langsung = $this->directLeaderIds($userId);

        return $hasil->filter()
            ->reject(fn ($id) => $id === $userId || $langsung->contains($id))
            ->unique()->values();
    }

    /** $leaderId atasan $userId, langsung maupun di atasnya? */
    public function isLeaderOf(int $leaderId, int $userId): bool
    {
        return $this->directLeaderIds($userId)->contains($leaderId)
            || $this->upperLeaderIds($userId)->contains($leaderId);
    }

    /**
     * Semua bawahan seorang leader: anggota team yang ia pimpin dan seluruh
     * team di bawahnya.
     *
     * @return Collection<int, int>
     */
    public function subordinateIds(int $leaderId): Collection
    {
        ['teams' => $teams, 'attach' => $attach] = $this->graph();

        $anak = [];
        foreach ($attach as $teamId => $a) {
            if (isset($a['team'])) {
                $anak[$a['team']][] = (int) $teamId;
            }
        }

        $antre = $teams->filter(fn ($t) => (int) $t->leader_id === $leaderId)->keys()->map(fn ($id) => (int) $id)->all();
        $lihat = [];
        $hasil = collect();

        while ($antre) {
            $id = array_pop($antre);
            if (isset($lihat[$id])) {
                continue;
            }
            $lihat[$id] = true;
            $hasil = $hasil->merge($teams[$id]->activeMembers->pluck('user_id'));
            $antre = array_merge($antre, $anak[$id] ?? []);
        }

        return $hasil->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === $leaderId)
            ->unique()->values();
    }
}

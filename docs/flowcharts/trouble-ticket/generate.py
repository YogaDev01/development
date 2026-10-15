# -*- coding: utf-8 -*-
"""Generator flowchart modul Trouble Ticket TESYS (SVG)."""
import io, os, html

W, H = 2600, 3200
INK = "#0f2b46"       # garis & teks utama
LINE = "#44708f"      # panah
FILL = "#eef5fc"      # isi proses
STROKE = "#9cbcd6"
HEAD = "#1f4868"
DFILL = "#eaf4e4"
DSTROKE = "#7fae63"
MUTED = "#5b7b95"
ACCENT = {
    "OPEN": "#3b7ea1", "REMOTE": "#2f8f83", "PLAN": "#c08a2e",
    "TRIP": "#b06a3b", "IN PROGRESS": "#4a6fb5", "PROGRESS DONE": "#7a5aa6",
    "CLOSED": "#2f7d4f",
}
LANES = {"L": 560, "C": 1300, "R": 2040}
BW = 680
ROW0, ROWH = 300, 275
DEC_PAD = 95


def esc(s):
    return html.escape(s)


def wrap(text, maxch):
    out, line = [], ""
    for w in text.split():
        if len(line) + len(w) + 1 > maxch and line:
            out.append(line)
            line = w
        else:
            line = (line + " " + w).strip()
    if line:
        out.append(line)
    return out


class Diagram:
    def __init__(self, title, subtitle="", note=None):
        self.title, self.subtitle, self.note = title, subtitle, note or []
        self.nodes = {}
        self.body = []
        self.wires = []

    def add(self, nid, lane, row, kind, title, sub=None, w=BW, accent=None):
        x = LANES[lane] - w // 2
        y = ROW0 + row * ROWH
        h = 160 if kind == "stage" else (150 if kind == "dec" else 132)
        self.nodes[nid] = dict(x=x, y=y, w=w, h=h, kind=kind, title=title, sub=sub,
                               cx=LANES[lane], cy=y + h / 2.0,
                               accent=accent or ACCENT.get(title, HEAD))
        return nid

    def _text(self, cx, y, lines, size, weight, color, lh=None, anchor="middle", ls=0):
        lh = lh or size + 12
        y0 = y - (len(lines) - 1) * lh / 2.0
        for i, ln in enumerate(lines):
            self.body.append(
                '<text x="%.0f" y="%.0f" text-anchor="%s" dominant-baseline="middle" '
                'font-family="Segoe UI, Calibri, Arial, sans-serif" font-size="%d" '
                'font-weight="%s" letter-spacing="%s" fill="%s">%s</text>'
                % (cx, y0 + i * lh, anchor, size, weight, ls, color, esc(ln)))

    def draw_nodes(self):
        for n in self.nodes.values():
            x, y, w, h = n["x"], n["y"], n["w"], n["h"]
            if n["kind"] == "dec":
                cx, cy = n["cx"], n["cy"]
                hw, hh = w / 2.0 + DEC_PAD, h / 2.0 + 35
                self.body.append(
                    '<polygon points="%.0f,%.0f %.0f,%.0f %.0f,%.0f %.0f,%.0f" fill="%s" '
                    'stroke="%s" stroke-width="3" filter="url(#sh)"/>'
                    % (cx - hw, cy, cx, cy - hh, cx + hw, cy, cx, cy + hh, DFILL, DSTROKE))
                self._text(cx, cy, wrap(n["title"], 32), 30, "600", INK)
                continue

            self.body.append(
                '<rect x="%d" y="%d" width="%d" height="%d" rx="18" fill="%s" stroke="%s" '
                'stroke-width="2" filter="url(#sh)"/>' % (x, y, w, h, "#ffffff" if n["kind"] == "stage" else FILL, STROKE))

            if n["kind"] == "stage":
                a = n["accent"]
                self.body.append(
                    '<path d="M%d,%d h%d a18,18 0 0 1 18,18 v44 h-%d v-44 a18,18 0 0 1 18,-18 z" fill="%s"/>'
                    % (x + 18, y, w - 36, w, HEAD))
                self.body.append('<rect x="%d" y="%d" width="10" height="%d" rx="5" fill="%s"/>'
                                 % (x, y + 70, h - 86, a))
                self._text(n["cx"], y + 31, [n["title"]], 31, "700", "#ffffff", ls=1.5)
                if n["sub"]:
                    self._text(n["cx"], y + 112, wrap(n["sub"], 46), 26, "400", INK, 34)
            else:
                self._text(n["cx"], n["cy"], wrap(n["title"], 40), 27, "400", INK, 36)

    def _poly(self, pts, label=None, lx=None, ly=None):
        d = " ".join("%.0f,%.0f" % p for p in pts)
        self.wires.append('<polyline points="%s" fill="none" stroke="%s" stroke-width="3" '
                          'stroke-linejoin="round" marker-end="url(#ar)"/>' % (d, LINE))
        if label:
            tw = len(label) * 14 + 24
            self.wires.append('<rect x="%.0f" y="%.0f" width="%d" height="40" rx="12" fill="#ffffff" '
                              'stroke="%s" stroke-width="1.5"/>' % (lx - tw / 2.0, ly - 20, tw, STROKE))
            self.wires.append('<text x="%.0f" y="%.0f" text-anchor="middle" dominant-baseline="middle" '
                              'font-family="Segoe UI, Arial, sans-serif" font-size="25" fill="%s">%s</text>'
                              % (lx, ly + 1, MUTED, esc(label)))

    def edge(self, a, b, label=None, side=None, corridor=None):
        A, B = self.nodes[a], self.nodes[b]
        aw = A["w"] / 2.0 + (DEC_PAD if A["kind"] == "dec" else 0)
        bw = B["w"] / 2.0 + (DEC_PAD if B["kind"] == "dec" else 0)
        ab, bt = A["y"] + A["h"], B["y"]
        if side in ("l", "r"):
            sx = A["cx"] - aw - 6 if side == "l" else A["cx"] + aw + 6
            sy = A["cy"]
            cx = corridor if corridor is not None else B["cx"]
            if B["y"] > A["y"]:
                if abs(cx - B["cx"]) < 1:
                    pts = [(sx, sy), (cx, sy), (cx, bt - 6)]
                else:
                    tx = B["cx"] + bw + 6 if cx > B["cx"] else B["cx"] - bw - 6
                    pts = [(sx, sy), (cx, sy), (cx, B["cy"]), (tx, B["cy"])]
            elif B["y"] < A["y"]:
                tx = B["cx"] + bw + 6 if cx > B["cx"] else B["cx"] - bw - 6
                pts = [(sx, sy), (cx, sy), (cx, B["cy"]), (tx, B["cy"])]
            else:
                tx = B["cx"] + bw + 6 if sx > B["cx"] else B["cx"] - bw - 6
                pts = [(sx, sy), (tx, B["cy"])]
            self._poly(pts, label, sx + (60 if side == "r" else -60), sy - 34)
        else:
            if abs(A["cx"] - B["cx"]) < 1:
                self._poly([(A["cx"], ab + 6), (B["cx"], bt - 6)], label, A["cx"], (ab + bt) / 2.0)
            else:
                my = (ab + bt) / 2.0
                self._poly([(A["cx"], ab + 6), (A["cx"], my), (B["cx"], my), (B["cx"], bt - 6)],
                           label, (A["cx"] + B["cx"]) / 2.0, my)

    def render(self, path):
        global H
        maxrow = max((n["y"] + n["h"]) for n in self.nodes.values())
        H = int(maxrow + 150 + (44 * len(self.note) + 110 if self.note else 60))
        self.draw_nodes()
        head = ('<rect x="0" y="0" width="%d" height="190" fill="#f6fafd"/>'
                '<rect x="0" y="188" width="%d" height="3" fill="%s"/>'
                '<rect x="90" y="56" width="10" height="70" rx="5" fill="%s"/>'
                '<text x="128" y="92" font-family="Segoe UI, Calibri, Arial, sans-serif" font-size="52" '
                'font-weight="700" letter-spacing="1" fill="%s">%s</text>'
                % (W, W, STROKE, "#3b7ea1", INK, esc(self.title)))
        if self.subtitle:
            head += ('<text x="128" y="144" font-family="Segoe UI, Arial, sans-serif" font-size="30" '
                     'fill="%s">%s</text>' % (MUTED, esc(self.subtitle)))
        note = ""
        if self.note:
            top = H - 70 - 44 * len(self.note)
            note = ('<rect x="90" y="%d" width="%d" height="%d" rx="16" fill="#f6fafd" stroke="%s" '
                    'stroke-width="2"/>' % (top - 30, W - 180, 44 * len(self.note) + 56, STROKE))
            note += ('<rect x="90" y="%d" width="10" height="%d" rx="5" fill="#3b7ea1"/>'
                     % (top - 30, 44 * len(self.note) + 56))
            for i, l in enumerate(self.note):
                note += ('<text x="138" y="%d" font-family="Segoe UI, Arial, sans-serif" font-size="27" '
                         'fill="%s">%s</text>' % (top + 12 + i * 44, INK, esc(l)))
        svg = ('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">'
               '<defs><marker id="ar" markerWidth="13" markerHeight="13" refX="10" refY="4.5" orient="auto">'
               '<path d="M0,0 L10,4.5 L0,9 z" fill="%s"/></marker>'
               '<filter id="sh" x="-20%%" y="-20%%" width="140%%" height="160%%">'
               '<feDropShadow dx="0" dy="4" stdDeviation="6" flood-color="#0f2b46" flood-opacity="0.13"/>'
               '</filter></defs>'
               '<rect width="%d" height="%d" fill="#ffffff"/>%s%s%s%s</svg>'
               % (W, H, W, H, LINE, W, H, head, "".join(self.wires), "".join(self.body), note))
        io.open(path, "w", encoding="utf-8").write(svg)


OUT = r"C:\laragon\www\tesys-master\docs\flowcharts\trouble-ticket"
os.makedirs(OUT, exist_ok=True)

# ------------------------------------------------------------------ 00 ALUR UTAMA
d = Diagram("ALUR TROUBLE TICKET SAMPAI CLOSED", "TESYS — Operations · Trouble Ticket", [
    "SLA Resolution hanya berjalan dari tahap In Progress sampai Closed; Remote / Plan / Trip tidak dihitung.",
    "Form Close bisa dikirim dari tahap Remote, Plan, Trip, dan In Progress (TroubleTicket::CLOSABLE).",
    "Close dari Trip sebelum On Site: perjalanan yang sedang berjalan dibatalkan otomatis, SLA Travel tidak dihitung.",
    "Change Teknisi hanya pada tahap Plan / Trip selama perjalanan belum dimulai (canReassign).",
    "Approval penutupan: role supervisor dan bukan teknisi tiket itu sendiri (canApproveClose).",
])
d.add("open", "C", 0, "stage", "OPEN", "Tiket dibuat: site -> teknisi PIC; SLA Response mulai")
d.add("ack", "C", 1, "proc", "Teknisi membuka tiket -> acknowledged_at; SLA Response berhenti")
d.add("remote", "C", 2, "stage", "REMOTE", "Teknisi memilih Remote; SLA Resolution belum berjalan")
d.add("d1", "C", 3, "dec", "Gangguan selesai remote?")
d.add("close1", "L", 4, "proc", "Ya: Form Close — RCA, keterangan, evidence (wajib)")
d.add("mkplan", "R", 4, "proc", "Tidak: Create Plan — isi tanggal plan visit")
d.add("plan", "R", 5, "stage", "PLAN", "Menunggu tanggal plan visit")
d.add("d2", "R", 6, "dec", "Tanggal visit tiba?")
d.add("trip", "C", 7, "stage", "TRIP", "Mulai perjalanan + evidence -> SLA Travel mulai")
d.add("d3", "C", 8, "dec", "Teknisi tiba di site?")
d.add("cancel", "R", 8, "proc", "Tidak: batal perjalanan + alasan + tanggal baru -> kembali ke Plan")
d.add("onsite", "C", 9, "proc", "Ya: On Site -> SLA Travel berhenti -> Start Progress")
d.add("prog", "C", 10, "stage", "IN PROGRESS", "SLA Resolution MULAI di sini; perbaikan + evidence")
d.add("done", "C", 11, "stage", "PROGRESS DONE", "Form close terkirim; approval = PENDING")
d.add("d4", "C", 12, "dec", "Supervisor menyetujui?")
d.add("rej", "R", 12, "proc", "Tidak: Reject + alasan -> kembali ke Remote / In Progress")
d.add("closed", "C", 13, "stage", "CLOSED", "SLA Resolution berhenti; tiket final")
d.edge("open", "ack")
d.edge("ack", "remote")
d.edge("remote", "d1")
d.edge("d1", "close1", "Ya", side="l")
d.edge("d1", "mkplan", "Tidak", side="r")
d.edge("mkplan", "plan")
d.edge("plan", "d2")
d.edge("d2", "trip", "Ya", side="l")
d.edge("d2", "plan", "Tidak", side="r", corridor=2480)
d.edge("trip", "d3")
d.edge("d3", "cancel", "Tidak", side="r")
d.edge("cancel", "plan", side="r", corridor=2480)
d.edge("d3", "onsite", "Ya")
d.edge("onsite", "prog")
d.edge("prog", "done")
d.edge("close1", "done", side="l", corridor=170)
d.edge("done", "d4")
d.edge("d4", "closed", "Ya")
d.edge("d4", "rej", "Tidak", side="r")
d.edge("rej", "prog", side="r", corridor=2480)
d.render(os.path.join(OUT, "00_Alur_Utama.svg"))

# ------------------------------------------------------------------ 01 OPEN
d = Diagram("1 · OPEN", "Pembuatan tiket & respons pertama teknisi", [
    "create(): site ACTIVE, teknisi wajib PIC internal aktif site itu dan bukan leader team.",
    "Kebijakan SLA dipilih per Problem (SlaPolicy::applicableForProblem); tanpa Problem dipakai kebijakan umum.",
    "recordAccess(): hanya akses teknisi yang ditugaskan yang mencatat acknowledged_at — sekali seumur tiket.",
    "Dari OPEN teknisi hanya bisa memilih Remote; Create Plan baru tersedia setelah tahap Remote.",
])
d.add("src", "C", 0, "proc", "CSO / NOC / Admin membuat tiket: site, device, initial problem, deskripsi")
d.add("val", "C", 1, "proc", "Validasi master: site aktif, teknisi PIC internal aktif, RCA/Sub-RCA cocok")
d.add("num", "C", 2, "proc", "No. TT otomatis (generateNumber) atau No. TT custom yang belum terpakai")
d.add("open", "C", 3, "stage", "OPEN", "Tiket tersimpan; assigned_at diisi; SLA Response mulai")
d.add("notif", "R", 4, "proc", "Notifikasi ke teknisi & leader team")
d.add("d1", "C", 4, "dec", "Teknisi membuka tiket?")
d.add("wait", "L", 5, "proc", "Belum: SLA Response terus berjalan (risiko breach)")
d.add("ack", "C", 5, "proc", "Ya: acknowledged_at + response_seconds dicatat; SLA Response berhenti")
d.add("lock", "C", 6, "proc", "mustDecide: chat & aksi lain terkunci sampai teknisi memilih Remote")
d.add("remote", "C", 7, "stage", "REMOTE", "Teknisi menekan Mulai Remote")
d.edge("src", "val")
d.edge("val", "num")
d.edge("num", "open")
d.edge("open", "d1")
d.edge("open", "notif", side="r", corridor=2040)
d.edge("d1", "wait", "Belum", side="l")
d.edge("d1", "ack", "Ya")
d.edge("ack", "lock")
d.edge("lock", "remote")
d.render(os.path.join(OUT, "01_Open.svg"))

# ------------------------------------------------------------------ 02 REMOTE
d = Diagram("2 · REMOTE", "Penanganan jarak jauh (visit_decision = NON_VISIT)", [
    "startRemote(): hanya dari OPEN, oleh teknisi yang ditugaskan atau superadmin.",
    "CATATAN: kode saat ini masih mengisi progress_started_at di startRemote() — belum sesuai diagram ini.",
    "SLA Resolution tidak berjalan di tahap Remote: hitungannya baru mulai saat progres di site dimulai.",
    "Form close wajib evidence; RCA & Sub-RCA diperbarui dari isian form close.",
    "Patokan SLA Resolution untuk tiket yang ditutup langsung dari Remote: PERLU KONFIRMASI.",
])
d.add("start", "C", 0, "stage", "REMOTE", "SLA Response sudah berhenti; SLA Resolution belum berjalan")
d.add("work", "C", 1, "proc", "Teknisi menangani gangguan dari jarak jauh; chat & lampiran tercatat di timeline")
d.add("d1", "C", 2, "dec", "Gangguan selesai remote?")
d.add("form", "L", 3, "proc", "Ya: Form Close — RCA, Sub-RCA, keterangan, evidence (wajib)")
d.add("note", "R", 3, "proc", "Tidak: catat bukti upaya remote + alasan perlu visit")
d.add("plan", "R", 4, "proc", "Create Plan: isi tanggal plan visit (+ target online)")
d.add("planst", "R", 5, "stage", "PLAN", "visit_decision = VISIT; menunggu tanggal kunjungan")
d.add("done", "L", 4, "stage", "PROGRESS DONE", "approval = PENDING; menunggu supervisor")
d.add("d2", "L", 5, "dec", "Supervisor menyetujui?")
d.add("closed", "L", 6, "stage", "CLOSED", "Tiket final")
d.add("back", "C", 6, "proc", "Reject: tiket kembali ke REMOTE untuk diperbaiki isiannya")
d.edge("start", "work")
d.edge("work", "d1")
d.edge("d1", "form", "Ya", side="l")
d.edge("d1", "note", "Tidak", side="r")
d.edge("note", "plan")
d.edge("plan", "planst")
d.edge("form", "done")
d.edge("done", "d2")
d.edge("d2", "closed", "Ya")
d.edge("d2", "back", "Tidak", side="r")
d.edge("back", "start", side="r", corridor=930)
d.render(os.path.join(OUT, "02_Remote.svg"))

# ------------------------------------------------------------------ 03 PLAN
d = Diagram("3 · PLAN", "Menunggu tanggal plan visit (tanpa approval plan)", [
    "Tidak ada approval plan (keputusan 12 Okt 2026): tiket naik sendiri ke Trip saat tanggalnya tiba.",
    "promoteDueTrips() dipanggil saat daftar/detail dibuka — tidak ada scheduler di shared hosting.",
    "Change Teknisi (reassign) di tahap Plan boleh sekaligus mengganti tanggal visit.",
])
d.add("plan", "C", 0, "stage", "PLAN", "visit_site = tanggal rencana kunjungan")
d.add("d0", "C", 1, "dec", "Jadwal / teknisi diubah?")
d.add("re", "R", 2, "proc", "Create Plan ulang atau Change Teknisi oleh leader team -> tanggal baru")
d.add("d1", "C", 3, "dec", "Tanggal visit tiba?")
d.add("wait", "L", 4, "proc", "Belum: tiket tetap menunggu di tahap Plan")
d.add("trip", "C", 5, "stage", "TRIP", "promoteDueTrips() menaikkan tiket otomatis")
d.add("close", "R", 4, "proc", "Opsi: gangguan beres sebelum tanggal visit -> Form Close dari Plan")
d.add("done", "R", 5, "stage", "PROGRESS DONE", "Menunggu approval supervisor (tanpa melewati In Progress)")
d.edge("plan", "d0")
d.edge("d0", "re", "Ya", side="r")
d.edge("re", "plan", side="r", corridor=2500)
d.edge("d0", "d1", "Tidak")
d.edge("d1", "wait", "Belum", side="l")
d.edge("wait", "plan", side="l", corridor=150)
d.edge("d1", "trip", "Ya")
d.edge("d1", "close", "Form Close", side="r")
d.edge("close", "done")
d.render(os.path.join(OUT, "03_Plan.svg"))

# ------------------------------------------------------------------ 04 TRIP
d = Diagram("4 · TRIP", "Perjalanan ke site & konfirmasi On Site", [
    "startTravel(): evidence perjalanan WAJIB (keputusan 15 Okt 2026); SLA Travel mulai.",
    "cancelTravel(): wajib alasan + tanggal plan berikutnya; jejak perjalanan dikosongkan, status kembali ke PLAN.",
    "Setelah On Site tiket tidak bisa dijadwal ulang dan teknisi tidak bisa diganti.",
])
d.add("trip", "C", 0, "stage", "TRIP", "Tanggal visit tiba; menunggu keberangkatan")
d.add("go", "C", 1, "proc", "Mulai Perjalanan + evidence (wajib) -> travel_started_at, SLA Travel mulai")
d.add("d1", "C", 2, "dec", "Perjalanan berlanjut?")
d.add("cancel", "R", 3, "proc", "Tidak: Batal Perjalanan — alasan + tanggal plan berikutnya")
d.add("plan", "R", 4, "stage", "PLAN", "SLA Travel dibatalkan (tidak dihitung)")
d.add("onsite", "C", 3, "proc", "Ya: klik On Site -> travel_arrived_at, SLA Travel berhenti")
d.add("prog", "C", 4, "stage", "IN PROGRESS", "Start Progress -> SLA Resolution MULAI")
d.add("early", "L", 3, "proc", "Gangguan beres sebelum tiba: Form Close dari Trip -> perjalanan dibatalkan otomatis")
d.add("done", "L", 4, "stage", "PROGRESS DONE", "Menunggu approval supervisor")
d.edge("trip", "go")
d.edge("go", "d1")
d.edge("d1", "cancel", "Tidak", side="r")
d.edge("cancel", "plan")
d.edge("d1", "onsite", "Ya")
d.edge("onsite", "prog")
d.edge("d1", "early", "Form Close", side="l")
d.edge("early", "done")
d.render(os.path.join(OUT, "04_Trip.svg"))

# ------------------------------------------------------------------ 05 IN PROGRESS
d = Diagram("5 · IN PROGRESS", "Pengerjaan di site sampai form close terkirim", [
    "startProgress(): hanya dari TRIP dan hanya setelah On Site dikonfirmasi.",
    "progress_started_at tidak pernah ditimpa — SLA Resolution dihitung dari titik pertama kali progres dimulai.",
    "close(): evidence wajib untuk semua tiket; RCA/Sub-RCA tiket diperbarui sesuai isian form close.",
])
d.add("prog", "C", 0, "stage", "IN PROGRESS", "SLA Resolution berjalan (sejak Start Progress)")
d.add("work", "C", 1, "proc", "Troubleshooting di site; chat, foto, dan catatan masuk timeline tiket")
d.add("d1", "C", 2, "dec", "Gangguan selesai?")
d.add("cont", "R", 3, "proc", "Belum: pengerjaan lanjut, SLA Resolution terus berjalan")
d.add("form", "C", 3, "proc", "Ya: Form Close — RCA, Sub-RCA, keterangan penyelesaian, evidence (wajib)")
d.add("done", "C", 4, "stage", "PROGRESS DONE", "resolved_at & progress_done_at diisi; approval = PENDING")
d.add("notif", "L", 4, "proc", "Notifikasi ke supervisor: tiket menunggu approval")
d.edge("prog", "work")
d.edge("work", "d1")
d.edge("d1", "cont", "Belum", side="r")
d.edge("cont", "work", side="r", corridor=2500)
d.edge("d1", "form", "Ya")
d.edge("form", "done")
d.edge("done", "notif", side="l")
d.render(os.path.join(OUT, "05_In_Progress.svg"))

# ------------------------------------------------------------------ 06 PROGRESS DONE
d = Diagram("6 · PROGRESS DONE", "Approval penutupan oleh supervisor", [
    "canApproveClose(): status PROGRESS_DONE, role TROUBLE_TICKET_APPROVE, dan bukan teknisi tiket itu sendiri.",
    "SLA Resolution TETAP berjalan selama menunggu approval dan setelah reject.",
    "rejectClose(): wajib alasan; tiket kembali ke REMOTE (jalur non-visit) atau IN PROGRESS (jalur visit).",
])
d.add("done", "C", 0, "stage", "PROGRESS DONE", "approval = PENDING; SLA Resolution masih berjalan")
d.add("rev", "C", 1, "proc", "Supervisor memeriksa RCA, keterangan penyelesaian, dan evidence")
d.add("d1", "C", 2, "dec", "Isian form close memadai?")
d.add("rej", "R", 3, "proc", "Tidak: Reject + alasan -> approval = REJECTED, resolved_at dikosongkan")
d.add("d2", "R", 4, "dec", "Jalur tiket?")
d.add("remote", "R", 5, "stage", "REMOTE", "Non-visit: teknisi memperbaiki isian")
d.add("prog", "C", 5, "stage", "IN PROGRESS", "Visit: teknisi memperbaiki isian")
d.add("app", "L", 3, "proc", "Ya: Approve -> approved_at/by, closed_at, resolution_seconds dihitung")
d.add("closed", "L", 4, "stage", "CLOSED", "SLA Resolution berhenti; tiket final")
d.edge("done", "rev")
d.edge("rev", "d1")
d.edge("d1", "rej", "Tidak", side="r")
d.edge("rej", "d2")
d.edge("d2", "remote", "Non-visit", side="r", corridor=2500)
d.edge("d2", "prog", "Visit", side="l")
d.edge("d1", "app", "Ya", side="l")
d.edge("app", "closed")
d.render(os.path.join(OUT, "06_Progress_Done.svg"))

# ------------------------------------------------------------------ 07 CLOSED
d = Diagram("7 · CLOSED", "Tiket final & rekap SLA", [
    "FINAL_STATUSES = ['CLOSED']: tidak ada transisi keluar, chat dikunci (canMessage false).",
    "SLA Response = acknowledged_at - created_at; SLA Travel = travel_arrived_at - travel_started_at;",
    "SLA Resolution = closed_at - progress_started_at, yaitu hanya rentang Progress sampai Closed.",
    "Lampiran tetap di disk secure, diakses lewat SecureFileController + FileAccess::allowsTroubleTicket().",
])
d.add("app", "C", 0, "proc", "Supervisor menyetujui penutupan")
d.add("closed", "C", 1, "stage", "CLOSED", "status final; closed_at & closed_by diisi")
d.add("sla", "L", 2, "proc", "resolution_seconds dikunci; tiga metrik SLA ditandai aman / warning / breach")
d.add("notif", "R", 2, "proc", "Notifikasi ke teknisi & pembuat tiket")
d.add("lock", "C", 3, "proc", "Chat dan seluruh aksi workflow terkunci; timeline jadi arsip")
d.add("rep", "C", 4, "proc", "Tiket masuk rekap index & laporan SLA (filter status, site, teknisi, periode)")
d.edge("app", "closed")
d.edge("closed", "sla", side="l")
d.edge("closed", "notif", side="r")
d.edge("closed", "lock")
d.edge("lock", "rep")
d.render(os.path.join(OUT, "07_Closed.svg"))

print("selesai")

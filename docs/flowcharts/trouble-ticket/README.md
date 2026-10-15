# Flowchart modul Trouble Ticket

Isi diagram diambil dari kode pada 15 Okt 2026: `app/Services/TroubleTicketWorkflow.php`,
`app/Models/TroubleTicket.php` (`STATUSES`, `CLOSABLE`, `FINAL_STATUSES`).

| Berkas | Isi |
|---|---|
| `00_Alur_Utama` | Alur penuh Open → Remote → Plan → Trip → In Progress → Progress Done → Closed |
| `01_Open` | Pembuatan tiket, validasi master, SLA Response, respons pertama teknisi |
| `02_Remote` | Penanganan jarak jauh, percabangan close vs create plan |
| `03_Plan` | Menunggu tanggal visit, `promoteDueTrips()`, reschedule / change teknisi |
| `04_Trip` | Mulai perjalanan (+evidence), batal perjalanan, On Site |
| `05_In_Progress` | Pengerjaan di site sampai form close terkirim |
| `06_Progress_Done` | Approval / reject penutupan oleh supervisor |
| `07_Closed` | Tiket final dan rekap tiga metrik SLA |

Tiap diagram tersedia sebagai `.svg` (sumber) dan `.png` (2600 px, siap tempel ke dokumen).

Regenerasi:

```
python docs/flowcharts/trouble-ticket/generate.py
```

lalu render PNG dengan headless Chrome (lebar 2600, tinggi mengikuti atribut `height` tiap SVG).

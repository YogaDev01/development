<#
.SYNOPSIS
    Membangun CSS Tailwind (v4.3.3, binary mandiri — lihat docs/ui/tailwind.md)
    untuk salah satu dari dua target: produksi (default) atau uji.

.DESCRIPTION
    Target 'produksi' (default):
      masukan  resources/tailwind/tesys-tw.css  (tanpa safelist)
      keluaran public/css/tesys-tw.css
    Target 'uji':
      masukan  resources/tailwind/tesys-tw.uji.css  (= masukan produksi + safelist uji)
      keluaran tools/tailwind/uji/tesys-tw.uji.css  (ter-ignore git; DITOLAK bila
               jalurnya berada di bawah public/ — artefak uji tidak boleh dipublikasikan)

    Hanya membaca resources/tailwind, resources/views, dan public/js/tesys-*.js,
    lalu menulis SATU berkas keluaran target. Tidak menyentuh artisan, composer,
    npm, database, maupun .env.

    Binary diperiksa dulu terhadap SHA-256 yang dipatok; bila tidak cocok, build
    ditolak (mencegah menjalankan executable yang berbeda dari yang diverifikasi).

.PARAMETER Target
    'produksi' (default) atau 'uji'. Tidak ada target gabungan; jalankan skrip
    dua kali bila perlu keduanya.

.PARAMETER Cek
    Tidak menulis ke keluaran target. Membangun DUA kali ke folder sementara dan
    membandingkan SHA-256 keduanya (uji determinisme), lalu membandingkan dengan
    berkas keluaran target yang sudah terpasang. Kode keluar 1 bila berkas
    terpasang berbeda dari hasil build (atau bila tidak deterministik). Bila
    berkas terpasang belum ada, perbandingan dilewati dan itu tidak dianggap
    gagal (berlaku untuk kedua target; target uji memang tidak selalu dibangun).

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts/build-tailwind.ps1
    powershell -ExecutionPolicy Bypass -File scripts/build-tailwind.ps1 -Cek
    powershell -ExecutionPolicy Bypass -File scripts/build-tailwind.ps1 -Target uji
    powershell -ExecutionPolicy Bypass -File scripts/build-tailwind.ps1 -Target uji -Cek
#>
param(
    [switch]$Cek,
    [ValidateSet('produksi', 'uji')]
    [string]$Target = 'produksi'
)

$ErrorActionPreference = 'Stop'

$Root   = Split-Path -Parent $PSScriptRoot
$Bin    = Join-Path $Root 'tools\tailwind\tailwindcss.exe'
$BinSha ='e0e260ce048014e9268f6237ff18f8ccf02cef521cbd0ae04e82c2cdf7aa3955'  # tailwindcss-windows-x64.exe v4.3.3

# Peta jalur per target (relatif terhadap akar proyek).
$Peta = @{
    produksi = @{ Masukan = 'resources\tailwind\tesys-tw.css';     Keluaran = 'public\css\tesys-tw.css' }
    uji      = @{ Masukan = 'resources\tailwind\tesys-tw.uji.css'; Keluaran = 'tools\tailwind\uji\tesys-tw.uji.css' }
}
$Masukan      = Join-Path $Root $Peta[$Target].Masukan
$Keluaran     = Join-Path $Root $Peta[$Target].Keluaran
$KeluaranRel  = $Peta[$Target].Keluaran -replace '\\', '/'

if (-not (Test-Path -LiteralPath $Bin -PathType Leaf)) {
    throw "Binary tidak ditemukan: $Bin (lihat docs/ui/tailwind.md, §1 Versi, binary, checksum)."
}
$actual = (Get-FileHash -LiteralPath $Bin -Algorithm SHA256).Hash.ToLower()
if ($actual -ne $BinSha) {
    throw "SHA-256 binary tidak cocok. Diharapkan $BinSha, ditemukan $actual. Build dibatalkan."
}
if (-not (Test-Path -LiteralPath $Masukan -PathType Leaf)) {
    throw "Berkas masukan tidak ada: $Masukan"
}
if ($Target -eq 'uji') {
    # Masukan uji mengimpor masukan produksi; keduanya harus ada.
    $Dasar = Join-Path $Root $Peta['produksi'].Masukan
    if (-not (Test-Path -LiteralPath $Dasar -PathType Leaf)) {
        throw "Berkas masukan produksi (diimpor target uji) tidak ada: $Dasar"
    }
    # Artefak uji memuat safelist (termasuk kelas yang dilarang di halaman): tolak jalur di bawah public/.
    $dirPublik = [System.IO.Path]::GetFullPath((Join-Path $Root 'public')).TrimEnd('\') + '\'
    $fullKeluaran = [System.IO.Path]::GetFullPath($Keluaran)
    if ($fullKeluaran.StartsWith($dirPublik, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Keluaran target uji tidak boleh berada di bawah public\: $fullKeluaran"
    }
}

function Invoke-Build([string]$Tujuan) {
    $global:LASTEXITCODE = -1
    & $Bin -i $Masukan -o $Tujuan --minify --silent
    if ($global:LASTEXITCODE -ne 0) {
        throw "Build gagal (kode keluar $global:LASTEXITCODE)."
    }
}

if ($Cek) {
    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ('tesys-tw-cek-' + [guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $tmp | Out-Null
    $script:BedaTerpasang = $false
    try {
        $a = Join-Path $tmp 'a.css'
        $b = Join-Path $tmp 'b.css'
        Invoke-Build $a
        Invoke-Build $b
        $ha = (Get-FileHash -LiteralPath $a -Algorithm SHA256).Hash.ToLower()
        $hb = (Get-FileHash -LiteralPath $b -Algorithm SHA256).Hash.ToLower()
        Write-Host "target  : $Target"
        Write-Host "build 1 : $ha"
        Write-Host "build 2 : $hb"
        if ($ha -ne $hb) { throw 'TIDAK deterministik: dua build menghasilkan hash berbeda.' }
        Write-Host 'deterministik: OK'
        if (Test-Path -LiteralPath $Keluaran -PathType Leaf) {
            $hc = (Get-FileHash -LiteralPath $Keluaran -Algorithm SHA256).Hash.ToLower()
            Write-Host "terpasang: $hc"
            if ($hc -ne $ha) { $script:BedaTerpasang = $true; Write-Warning "$KeluaranRel TIDAK sama dengan hasil build saat ini (belum dibangun ulang)." }
            else { Write-Host "$KeluaranRel sama dengan hasil build saat ini: OK" }
        }
        else {
            Write-Host "$KeluaranRel belum ada: perbandingan dilewati (tidak dianggap gagal)."
        }
    }
    finally {
        Remove-Item -LiteralPath $tmp -Recurse -Force -ErrorAction SilentlyContinue
    }
    if ($script:BedaTerpasang) { exit 1 }
    return
}

if ($Target -eq 'uji') {
    $dirKeluaran = Split-Path -Parent $Keluaran
    if (-not (Test-Path -LiteralPath $dirKeluaran -PathType Container)) {
        New-Item -ItemType Directory -Path $dirKeluaran | Out-Null
    }
}

Invoke-Build $Keluaran
$hash = (Get-FileHash -LiteralPath $Keluaran -Algorithm SHA256).Hash.ToLower()
$size = (Get-Item -LiteralPath $Keluaran).Length
Write-Host "target  : $Target"
Write-Host "ditulis : $Keluaran"
Write-Host "ukuran  : $size byte"
Write-Host "sha256  : $hash"

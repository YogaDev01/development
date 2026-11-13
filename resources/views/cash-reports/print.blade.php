<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $data['title'] }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #222; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #666; margin-bottom: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #999; padding: 4px 6px; vertical-align: top; }
        th { background: #eee; text-align: left; }
        .text-right { text-align: right; } .text-nowrap { white-space: nowrap; }
        .font-weight-bold { font-weight: bold; }
        .text-danger { color: #a00; } .text-muted { color: #666; }
        ul { padding-left: 18px; }
        @media print { body { margin: 8mm; } .noprint { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <h1>{{ $data['title'] }}</h1>
    <div class="meta">
        {{ $data['description'] }}<br>
        Dicetak {{ now()->format('d M Y H:i') }} oleh {{ auth()->user()->username ?? '-' }}
    </div>
    @include('cash-reports._table')
</body>
</html>

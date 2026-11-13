<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $letter->letter_no }}</title>
    <style>
        body{margin:0;background:#f4f5f7;padding:24px}
        .wrap{max-width:820px;margin:0 auto}
        .bar{margin-bottom:14px;font-family:system-ui,sans-serif;font-size:13px}
        .bar button{padding:6px 14px}
        @media print{body{background:#fff;padding:0}.bar{display:none}.letter-paper{border:none!important}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="bar"><button onclick="window.print()">Cetak</button></div>
    @include('letter._paper')
</div>
</body>
</html>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 2cm 1.8cm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5pt; color: #111; }
        .header { text-align: center; border-bottom: 2px solid #111; padding-bottom: 10px; margin-bottom: 24px; }
        .header .org { font-size: 16pt; font-weight: bold; letter-spacing: 1px; }
        .header .sub { font-size: 9.5pt; }
        h1 { text-align: center; font-size: 15pt; text-transform: uppercase; margin: 0 0 4px; }
        .reference { text-align: center; font-family: 'DejaVu Sans Mono', monospace; margin-bottom: 24px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data td { padding: 5px 6px; vertical-align: top; }
        table.data td.label { width: 32%; font-weight: bold; }
        table.list { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
        table.list th, table.list td { border: 1px solid #999; padding: 3px 4px; text-align: left; vertical-align: top; }
        table.list th { background: #e8eef3; }
        .signatures { width: 100%; margin-top: 48px; }
        .signatures td { width: 33%; text-align: center; font-weight: bold; vertical-align: top; }
        .signatures .line { margin-top: 60px; border-top: 1px solid #111; font-weight: normal; font-size: 8.5pt; padding-top: 2px; }
        .footer { position: fixed; bottom: -1.2cm; left: 0; right: 0; font-size: 8pt; color: #555; text-align: center; }
        .muted { color: #555; }
    </style>
</head>
<body>
    <div class="header">
        <div class="org">OMIT</div>
        <div class="sub">Organisation Médicale Interentreprise de Toliara</div>
    </div>
    @yield('content')
    <div class="footer">Document généré le {{ now()->format('d/m/Y à H:i') }} par l'application de gestion du personnel de l'OMIT</div>
</body>
</html>

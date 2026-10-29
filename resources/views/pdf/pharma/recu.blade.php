<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $vente->reference_vente }}</title>
    <style>
        @page { margin: 3mm 4mm; }

        /* Interligne fixé à 1,0 sur tout le ticket. */
        * { font-family: 'Courier New', monospace; font-size: 10px; color: #000; line-height: 1; }
        body { width: 100%; margin: 0; }

        .centre { text-align: center; }
        .gras { font-weight: bold; }
        .titre { font-size: 15px; font-weight: bold; letter-spacing: 3px; }
        .petit { font-size: 8px; }
        .trait { border-top: 1px dashed #000; margin: 4px 0; }
        .trait-plein { border-top: 1px solid #000; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 2px 0; }
        .droite { text-align: right; white-space: nowrap; }
        .section { text-align: center; font-weight: bold; font-size: 10px; letter-spacing: 1px; padding: 3px 0; border-top: 1px solid #000; border-bottom: 1px solid #000; margin: 6px 0 3px 0; }
        .ligne td { padding-top: 3px; }
        .detail { font-size: 8px; padding-top: 0; padding-bottom: 2px; }
        .sous-total td { font-weight: bold; border-top: 1px dotted #000; padding-top: 3px; }
        .total td { font-size: 12px; font-weight: bold; }
        .logo { width: 22mm; height: 22mm; }
        .barcode img { width: 46mm; height: 9mm; }
    </style>
</head>
<body>

@php
    $format = fn ($montant) => number_format((float) $montant, 0, ',', ' ');
@endphp

<div class="centre">
    <img class="logo" src="{{ $logo }}" alt="OMIT"><br>
    <span class="titre">OMIT</span><br>
    <span class="petit">Organisation Médicale Interentreprises de Toliara</span><br>
    <span class="petit">Pharmacie</span>
</div>

<div class="trait-plein"></div>

<table>
    <tr><td>Ticket : <b>{{ $vente->reference_vente }}</b></td></tr>
    <tr><td>Date &nbsp;: {{ $vente->created_at->format('d/m/Y H:i') }}</td></tr>
    <tr><td>Client : {{ mb_substr($vente->client, 0, 28) }}</td></tr>
    <tr><td>Caisse : {{ mb_substr($caissier, 0, 28) }}</td></tr>
</table>

{{-- Toutes les lignes de la vente, séparées en sections (catégories) --}}
@foreach ($sections as $section)
    @php
        $type = $section['type'];
    @endphp

    <div class="section">{{ $section['titre'] }}</div>

    @if ($type === 'Consultation')
        <table>
            @foreach ($section['lignes'] as $l)
                <tr class="ligne">
                    <td class="gras">{{ $l->libelle() }}</td>
                    <td class="droite gras">{{ $format($l->montant_brut) }} Ar</td>
                </tr>
            @endforeach
        </table>
        @foreach ($consultations as $c)
            <div class="petit" style="margin-top: 2px;">
                Patient : {{ $c->nom_patient }} ({{ \App\Models\Pharma\Consultation::sexeLibelle($c->sex_patient) }}, {{ $c->ageLibelle() }})<br>
                Docteur : Dr {{ $c->docteurNom() }}
            </div>
        @endforeach
    @else
        <table>
            @foreach ($section['lignes'] as $l)
                <tr class="ligne">
                    <td colspan="2" class="gras">{{ $type === 'Analyse' ? $l->libelle() : mb_substr($l->libelle(), 0, 34) }}</td>
                </tr>
                <tr>
                    <td class="detail">
                        @if ($type !== 'Analyse')
                            {{ $l->qte }} x {{ $format($l->p_u_brut) }}
                        @endif
                    </td>
                    <td class="droite">{{ $format($l->montant_brut) }} Ar</td>
                </tr>
                @if ($type === 'Article')
                    <tr>
                        <td colspan="2" class="detail">Lot {{ optional($l->lot)->lot ?? '—' }}@if ($l->lot?->date_peremption) · pér. {{ $l->lot->date_peremption->format('m/Y') }}@endif</td>
                    </tr>
                @endif
            @endforeach
        </table>
    @endif

    @if (count($sections) > 1)
        <table>
            <tr class="sous-total">
                <td>Sous-total {{ mb_strtolower($section['titre']) }}</td>
                <td class="droite">{{ $format($section['sous_total']) }} Ar</td>
            </tr>
        </table>
    @endif
@endforeach

<div class="trait-plein"></div>

{{-- Totaux de la vente --}}
<table>
    <tr class="total">
        <td>TOTAL</td>
        <td class="droite">{{ $format($vente->montant_brut) }} Ar</td>
    </tr>
    <tr>
        <td>Payé</td>
        <td class="droite">{{ $format($vente->montant_paye) }} Ar</td>
    </tr>
    @if ($monnaie > 0 && $monnaieArrondie !== $monnaie)
        <tr>
            <td>Monnaie calculée</td>
            <td class="droite">{{ $format($monnaie) }} Ar</td>
        </tr>
    @endif
    <tr class="gras">
        <td>Monnaie à rendre</td>
        <td class="droite">{{ $format($monnaieArrondie) }} Ar</td>
    </tr>
</table>

<div class="trait"></div>

<div class="centre barcode" style="padding-top: 4px;">
    <img src="data:image/png;base64,{{ $barcode }}" alt="{{ $vente->reference_vente }}"><br>
    <span class="petit">{{ $vente->reference_vente }}</span><br><br>
    <span class="petit gras">Merci de votre confiance !</span>
</div>

</body>
</html>

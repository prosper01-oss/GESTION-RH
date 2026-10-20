@extends('pdf.layout')

@section('content')
    <h1>{{ $title }}</h1>
    <div class="reference">{{ $request->reference }}</div>

    <table class="data">
        @foreach ($rows as $label => $value)
            <tr>
                <td class="label">{{ $label }}</td>
                <td>{{ $value }}</td>
            </tr>
        @endforeach
    </table>

    <table class="signatures">
        <tr>
            <td>L'agent<div class="line">Signature</div></td>
            <td>Les Ressources Humaines<div class="line">Signature et cachet</div></td>
            <td>Le Médecin-Chef
                @if(!empty($approverSignature))
                    <div style="margin-top:8px;"><img src="{{ $approverSignature }}" style="max-height:55px;max-width:130px;"></div>
                @endif
                <div class="line">Signature</div>
            </td>
        </tr>
    </table>
@endsection

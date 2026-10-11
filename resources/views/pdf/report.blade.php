@extends('pdf.layout')

@section('content')
    <h1>{{ $title }}</h1>
    @foreach ($summary as $line)
        <p class="muted" style="margin: 2px 0; text-align: center;">{{ $line }}</p>
    @endforeach

    <table class="list" style="margin-top: 16px;">
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}" style="text-align: center;">Aucune donnée pour cette période.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection

<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-size: 10pt; }
        h1 { font-size: 14pt; margin: 0 0 8pt; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.5pt solid #999; padding: 4pt 6pt; text-align: {{ $direction === 'rtl' ? 'right' : 'left' }}; }
        th { background: #eee; }
        .ltr { direction: ltr; }
        .footer { margin-top: 8pt; font-size: 8pt; color: #555; }
    </style>
</head>
<body>
    <h1>{{ __('Languages') }}</h1>
    <table>
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td class="ltr">{{ $row->code }}</td>
                    <td class="ltr">{{ $row->english_name }}</td>
                    <td class="ltr">{{ $row->text_direction }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="footer">{{ $footer }}</p>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $kind }} {{ $kind === 'Quotation' ? $doc->quotation_number : $doc->invoice_number }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>@page { size: A4; margin: 14mm; } body { background: #f6f7f9; }</style>
</head>
<body class="py-8 print:py-0">
    <div class="max-w-3xl mx-auto px-4 print:px-0">
        <div class="no-print flex justify-end mb-4"><button onclick="window.print()" class="btn btn-primary">Print / Save as PDF</button></div>
        @include('partials.doc-preview')
    </div>
</body>
</html>

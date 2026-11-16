<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $document->ref_no }} · {{ $title }}</title>
    @if (file_exists(public_path('images/favicon.png')))<link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">@endif
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    @include('partials.documents.styles')
    <style>body { background: #eef0f3; } @media print { .no-print { display: none !important; } }</style>
</head>
<body>
    <div class="no-print sticky top-0 z-10 bg-surface border-b border-line">
        <div class="max-w-[900px] mx-auto px-4 h-14 flex items-center gap-3">
            <a href="{{ route('documents.show', $document) }}" class="btn btn-ghost btn-sm"><x-icon name="arrow-left" /> Back</a>
            <div class="flex-1 min-w-0 text-[13px]">
                <div class="font-medium truncate">{{ $document->ref_no }} · {{ $title }}</div>
                @if ($copyOf)
                    <div class="text-muted truncate">Copy of the print by {{ $copyOf->user?->name ?? 'someone' }} on {{ $copyOf->printed_at->format('d M Y, g:i a') }}</div>
                @else
                    <div class="text-muted" id="print-count">{{ $document->print_count ? 'Printed '.$document->print_count.'× · last '.$document->last_printed_at?->diffForHumans() : 'Not printed yet' }}</div>
                @endif
            </div>
            @if ($letterhead && ($letterhead->header_path || $letterhead->footer_path))
                <label class="flex items-center gap-2 text-[13px] text-ink-2" title="Untick for paper that already has the letterhead printed on it">
                    <input type="checkbox" id="with-lh" class="checkbox" checked> Letterhead
                </label>
            @endif
            <button type="button" id="print-btn" class="btn btn-primary"><x-icon name="printer" /> Print / Save as PDF</button>
        </div>
    </div>

    <div class="py-8 print:py-0">
        @include('partials.documents.sheet', ['letterhead' => $letterhead, 'body' => $body, 'attrs' => 'id=sheet'])
    </div>

    <script>
        (function () {
            const btn = document.getElementById('print-btn');
            const box = document.getElementById('with-lh');
            const sheet = document.getElementById('sheet');
            box?.addEventListener('change', () => sheet.classList.toggle('lh-plain', !box.checked));
            async function printNow() {
                btn.disabled = true;
                try {
                    const res = await fetch(@js($logUrl), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ with_letterhead: box ? box.checked : true, from: @js($copyOf?->id) }),
                        credentials: 'same-origin',
                    });
                    const data = await res.json().catch(() => ({}));
                    const label = document.getElementById('print-count');
                    if (label && data.count) label.textContent = 'Printed ' + data.count + '× · saved to the history';
                } catch (e) { /* print anyway */ }
                btn.disabled = false;
                window.print();
            }
            btn.addEventListener('click', printNow);
            // Opened with ?go=1: wait for the letterhead images, then open the print dialog.
            if (new URLSearchParams(location.search).get('go') === '1') {
                history.replaceState(null, '', location.pathname);
                const imgs = Array.from(document.images);
                Promise.all(imgs.map((i) => i.complete ? null : new Promise((r) => { i.onload = i.onerror = r; }))).then(() => setTimeout(printNow, 250));
            }
        })();
    </script>
</body>
</html>

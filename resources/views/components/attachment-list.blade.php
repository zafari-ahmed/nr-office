@props(['attachments', 'deletable' => true])
<ul class="divide-y divide-line">
    @foreach ($attachments as $file)
        <li class="flex items-center gap-3 py-2.5">
            <span class="grid place-items-center size-9 rounded-lg bg-surface-2 text-muted"><x-icon :name="$file->isImage() ? 'eye' : 'paperclip'" /></span>
            <div class="min-w-0 flex-1">
                <a href="{{ route('attachments.download', [$file, 'inline' => 1]) }}" target="_blank" class="block truncate font-medium hover:text-accent">{{ $file->original_name }}</a>
                <div class="text-xs text-muted">{{ $file->humanSize() }} · {{ $file->created_at->format('d M Y') }}@if ($file->uploader) · {{ $file->uploader->name }}@endif</div>
            </div>
            <a href="{{ route('attachments.download', $file) }}" class="btn btn-icon" title="Download"><x-icon name="download" /></a>
            @if ($deletable)
                <form method="POST" action="{{ route('attachments.destroy', $file) }}" onsubmit="return confirm('Remove this file?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-icon hover:text-red-600" title="Remove"><x-icon name="trash-2" /></button>
                </form>
            @endif
        </li>
    @endforeach
</ul>

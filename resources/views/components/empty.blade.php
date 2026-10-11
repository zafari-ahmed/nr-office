@props(['icon' => 'inbox', 'title', 'text' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center text-center py-12 px-6']) }}>
    <span class="grid place-items-center size-12 rounded-full bg-accent-soft text-accent mb-3"><x-icon :name="$icon" class="size-5" /></span>
    <div class="font-medium text-ink">{{ $title }}</div>
    @if ($text)<p class="text-muted text-sm mt-1 max-w-sm">{{ $text }}</p>@endif
    @if ($slot->isNotEmpty())<div class="mt-4">{{ $slot }}</div>@endif
</div>

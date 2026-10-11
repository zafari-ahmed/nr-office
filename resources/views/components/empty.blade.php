@props(['icon' => 'inbox', 'title', 'text' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center text-center py-12 px-6']) }}>
    <span class="grid place-items-center size-11 rounded-[10px] bg-accent-soft text-accent-ink mb-3.5"><x-icon :name="$icon" class="size-5" /></span>
    <div class="text-[15px] font-semibold text-ink">{{ $title }}</div>
    @if ($text)<p class="text-ink-2 text-[13.5px] mt-1 max-w-[340px]">{{ $text }}</p>@endif
    @if ($slot->isNotEmpty())<div class="mt-4">{{ $slot }}</div>@endif
</div>

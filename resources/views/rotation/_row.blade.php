<div class="px-5 py-3 border-t border-line">
    <div class="flex items-center gap-3">
        <x-avatar :name="$v->employee->full_name" :src="$v->employee->photoUrl()" size="size-8 text-[11px]" />
        <div class="flex-1 min-w-0 text-[13px]">
            <div class="font-medium truncate">{{ $v->employee->full_name }}</div>
            <div class="text-xs text-muted truncate">{{ $v->site_name }} · due {{ $v->expected_return_date->format('D d M') }}</div>
        </div>
    </div>
    <div class="flex gap-2 mt-2 pl-11">
        <x-action :url="route('rotation.return', $v)" class="btn btn-secondary btn-xs"><x-icon name="check" /> Returned</x-action>
        <x-action :url="route('rotation.extend', $v)" data-days="2" class="btn btn-ghost btn-xs">+2 days</x-action>
    </div>
</div>

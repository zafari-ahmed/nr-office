<x-layouts.app title="Notifications">
    <x-page-header title="Notifications">
        <x-slot:actions>
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-secondary"><x-icon name="check" /> Mark all read</button></form>
        </x-slot:actions>
    </x-page-header>
    <div class="card divide-y divide-line">
        @forelse ($notifications as $n)
            <a href="{{ route('notifications.open', $n->id) }}" @class(['flex gap-3 px-5 py-4 hover:bg-surface-2', 'bg-accent-soft/40' => ! $n->read_at])>
                <span class="pill-{{ $n->data['tone'] ?? 'blue' }} grid place-items-center size-9 rounded-full shrink-0"><x-icon :name="$n->data['icon'] ?? 'bell'" /></span>
                <span class="flex-1 min-w-0"><span class="block font-medium">{{ $n->data['title'] ?? '' }}</span><span class="block text-[13px] text-muted">{{ $n->data['body'] ?? '' }}</span></span>
                <span class="text-xs text-muted shrink-0">{{ $n->created_at->diffForHumans() }}</span>
            </a>
        @empty
            <x-empty icon="bell" title="No notifications" text="Reminders for follow-ups, staff returns and overdue invoices land here every morning." />
        @endforelse
        {{ $notifications->links() }}
    </div>
</x-layouts.app>

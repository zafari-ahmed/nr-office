{{-- Add follow-up drawer. With $project set it posts to that project; otherwise a project dropdown is shown. --}}
<x-drawer name="follow-up{{ $project ? '-'.$project->id : '' }}" title="Add follow-up" subtitle="{{ $project?->name ?? 'Remind yourself or a teammate to get back to a client' }}">
    <form id="follow-up-form{{ $project?->id }}" method="POST" action="{{ $project ? route('follow-ups.store', $project) : route('follow-ups.store-any') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="_drawer" value="follow-up{{ $project ? '-'.$project->id : '' }}">
        @unless ($project)
            <x-field label="Project" name="project_id" :options="$projects->mapWithKeys(fn ($p) => [$p->id => $p->name.' · '.$p->code])" blank="Choose project…" required />
        @endunless
        <x-field label="What needs to happen" name="title" required placeholder="Call client about revised quote" />
        <div class="grid grid-cols-2 gap-4">
            <x-field label="Due date" name="due_date" type="date" :value="today()->addDays(2)->format('Y-m-d')" required />
            <x-field label="Assign to" name="assigned_to" :options="$users->pluck('name', 'id')" :value="auth()->id()" />
        </div>
        <x-field label="Notes" name="notes" type="textarea" placeholder="Context for whoever picks this up" />
    </form>
    <x-slot:footer>
        <button type="button" class="btn btn-secondary" @click="$store.drawer.close()">Cancel</button>
        <button form="follow-up-form{{ $project?->id }}" class="btn btn-primary"><x-icon name="check" /> Add follow-up</button>
    </x-slot:footer>
</x-drawer>

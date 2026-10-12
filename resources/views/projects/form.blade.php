<x-layouts.app :title="$project->exists ? 'Edit project' : 'New project'">
    <x-page-header :title="$project->exists ? 'Edit '.$project->name : 'New project'" :subtitle="'Project code '.$project->code" />

    <form method="POST" action="{{ $project->exists ? route('projects.update', $project) : route('projects.store') }}" class="grid lg:grid-cols-3 gap-6" x-data="{ newClient: {{ old('new_client_name') ? 'true' : 'false' }} }">
        @csrf
        @if ($project->exists) @method('PUT') @endif
        <div class="lg:col-span-2 card card-pad space-y-5">
            <x-field label="Project name" name="name" :value="$project->name" required placeholder="Gulberg Residence Renovation" />
            <div>
                <div class="flex items-center justify-between">
                    <label class="label">Client</label>
                    <button type="button" class="text-xs link mb-1.5" @click="newClient = !newClient" x-text="newClient ? 'Pick existing client' : '+ New client'"></button>
                </div>
                <div x-show="!newClient">
                    <x-field name="client_id" :options="$clients->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])" blank="Choose client…" :value="$project->client_id" />
                </div>
                <div x-show="newClient" x-cloak class="grid sm:grid-cols-2 gap-3">
                    <x-field name="new_client_name" placeholder="Client name" />
                    <x-field name="new_client_phone" placeholder="Phone" />
                </div>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-field label="Site address" name="site_address" :value="$project->site_address" />
                <x-field label="City" name="city" :value="$project->city" placeholder="Lahore" />
            </div>
            <x-field label="Description / scope" name="description" type="textarea" rows="5" :value="$project->description" />
        </div>
        <div class="space-y-6">
            <div class="card card-pad space-y-4">
                <x-field label="Status" name="status" :options="\App\Models\Project::STATUSES" :value="$project->status" required />
                <x-field label="Project value (PKR)" name="project_value" type="number" step="0.01" min="0" :value="$project->project_value" />
                <x-field label="Project manager" name="manager_id" :options="$managers->pluck('name', 'id')" blank="—" :value="$project->manager_id" />
                <div class="grid grid-cols-2 gap-3">
                    <x-field label="Start" name="start_date" type="date" :value="$project->start_date" />
                    <x-field label="End" name="end_date" type="date" :value="$project->end_date" />
                </div>
            </div>
            @php $teamIds = collect(old('team', $project->exists ? $project->members()->pluck('employees.id')->all() : []))->map(fn ($id) => (int) $id)->all(); @endphp
            <div class="card overflow-hidden" x-data="{ q: '' }">
                <div class="px-[18px] pt-4 pb-3">
                    <h2 class="card-title">Team</h2>
                    <p class="text-[12.5px] text-muted mt-0.5">Staff on the team can open this project, add notes, follow-ups and files.</p>
                    <input x-model="q" placeholder="Search staff" class="input !h-8 mt-3 text-[13px]" aria-label="Search staff">
                </div>
                <div class="max-h-72 overflow-y-auto border-t border-line divide-y divide-line-2">
                    @forelse ($employees as $e)
                        <label class="flex items-center gap-2.5 px-[18px] py-2 cursor-pointer hover:bg-surface-2" x-show="!q || @js(mb_strtolower($e->full_name.' '.$e->designation)).includes(q.toLowerCase())">
                            <input type="checkbox" name="team[]" value="{{ $e->id }}" class="checkbox" @checked(in_array($e->id, $teamIds, true))>
                            <x-avatar :name="$e->full_name" :src="$e->photoUrl()" size="size-7 text-[10.5px]" />
                            <span class="min-w-0 leading-tight"><span class="block text-[13px] font-medium truncate">{{ $e->full_name }}</span><span class="block text-[11.5px] text-muted truncate">{{ $e->designation }}</span></span>
                        </label>
                    @empty
                        <p class="px-[18px] py-3 text-[13px] text-muted">No active employees yet.</p>
                    @endforelse
                </div>
            </div>
            <div class="flex gap-2 justify-end">
                <a href="{{ $project->exists ? route('projects.show', $project) : route('projects.index') }}" class="btn btn-secondary">Cancel</a>
                <button class="btn btn-primary"><x-icon name="check" /> {{ $project->exists ? 'Save changes' : 'Create project' }}</button>
            </div>
        </div>
    </form>
</x-layouts.app>

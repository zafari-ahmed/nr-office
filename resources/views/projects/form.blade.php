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
            <div class="flex gap-2 justify-end">
                <a href="{{ $project->exists ? route('projects.show', $project) : route('projects.index') }}" class="btn btn-secondary">Cancel</a>
                <button class="btn btn-primary"><x-icon name="check" /> {{ $project->exists ? 'Save changes' : 'Create project' }}</button>
            </div>
        </div>
    </form>
</x-layouts.app>

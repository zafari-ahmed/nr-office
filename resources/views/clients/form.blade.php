<x-layouts.app :title="$client->exists ? 'Edit client' : 'Add client'">
    <x-page-header :title="$client->exists ? 'Edit '.$client->displayName() : 'Add client'" />
    <form method="POST" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" class="card card-pad space-y-4 max-w-2xl">
        @csrf
        @if ($client->exists) @method('PUT') @endif
        <div class="grid sm:grid-cols-2 gap-4">
            <x-field label="Client name" name="name" :value="$client->name" required />
            <x-field label="Company" name="company_name" :value="$client->company_name" />
            <x-field label="Contact person" name="contact_person" :value="$client->contact_person" />
            <x-field label="Phone" name="phone" :value="$client->phone" />
            <x-field label="Email" name="email" type="email" :value="$client->email" />
            <x-field label="NTN" name="ntn" :value="$client->ntn" />
            <x-field label="City" name="city" :value="$client->city" />
        </div>
        <x-field label="Address" name="address" type="textarea" rows="2" :value="$client->address" />
        <x-field label="Notes" name="notes" type="textarea" rows="3" :value="$client->notes" />
        <div class="flex justify-between pt-2">
            @if ($client->exists)
                <x-action :url="route('clients.destroy', $client)" method="DELETE" confirm="Remove this client?" class="btn btn-ghost hover:text-red-600"><x-icon name="trash-2" /> Remove</x-action>
            @else <span></span> @endif
            <div class="flex gap-2"><a href="{{ route('clients.index') }}" class="btn btn-secondary">Cancel</a><button class="btn btn-primary">Save</button></div>
        </div>
    </form>
</x-layouts.app>

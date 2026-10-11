{{-- Add expense drawer. Expects $projects, $heads, $employees; optional $project to preselect. --}}
<x-drawer name="expense" title="Add expense" subtitle="Receipts and project link are optional">
    <form id="expense-form" method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data" class="space-y-4" x-data="{ head: '{{ old('expense_head_id') }}' }">
        @csrf
        <input type="hidden" name="_drawer" value="expense">
        <div class="grid grid-cols-2 gap-4">
            <x-field label="Expense head" name="expense_head_id" :options="$heads->pluck('name', 'id')" blank="Choose…" required class="col-span-2" x-model="head" />
            <x-field label="Amount (PKR)" name="amount" type="number" step="0.01" min="1" required />
            <x-field label="Date" name="expense_date" type="date" :value="today()->format('Y-m-d')" required />
        </div>
        <x-field label="Description" name="description" required placeholder="e.g. Tea, sugar and cleaning supplies" />
        <div class="grid grid-cols-2 gap-4">
            <x-field label="Vendor / paid to" name="vendor" />
            <x-field label="Payment method" name="payment_method" :options="\App\Models\Expense::METHODS" />
        </div>
        <x-field label="Link to project" name="project_id" :options="$projects->mapWithKeys(fn ($p) => [$p->id => $p->name.' · '.$p->code])" blank="No project (office expense)" :value="isset($project) ? $project?->id : null" />
        <div class="grid grid-cols-2 gap-4">
            <x-field label="Employee (salary)" name="employee_id" :options="$employees->pluck('full_name', 'id')" blank="—" />
            <x-field label="Salary month" name="payroll_month" type="month" hint="For salary payments" />
        </div>
        <x-field label="Reference no." name="reference_no" placeholder="Cheque / transfer reference" />
        <x-dropzone name="receipts[]" label="Receipt photo or PDF" accept="image/*,application/pdf" />
    </form>
    <x-slot:footer>
        <button type="button" class="btn btn-secondary" @click="$store.drawer.close()">Cancel</button>
        <button form="expense-form" class="btn btn-primary"><x-icon name="check" /> Save expense</button>
    </x-slot:footer>
</x-drawer>

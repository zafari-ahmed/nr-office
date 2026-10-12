{{-- Add expense drawer. Expects $projects, $heads, $employees; optional $project to preselect.
     Picking the Salary head swaps the form to: employee, month and amount (filled from their salary).
     The project link only shows for heads marked "links to project" (e.g. Project expenses), where it is required. --}}
@php
    $salaryHeads = $heads->where('slug', 'salary')->pluck('id')->map(fn ($id) => (string) $id)->values();
    $projectHeads = $heads->where('links_project', true)->pluck('id')->map(fn ($id) => (string) $id)->values();
    $salaries = $employees->filter(fn ($e) => $e->basic_salary !== null)->mapWithKeys(fn ($e) => [$e->id => (float) $e->basic_salary]);
@endphp
<x-drawer name="expense" title="Add expense" subtitle="Receipts are optional">
    <form id="expense-form" method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data" class="space-y-4"
          x-data="{ head: '{{ old('expense_head_id', isset($project) && $project ? $projectHeads->first() : '') }}', employee: '{{ old('employee_id') }}', amount: '{{ old('amount') }}',
                    month: '{{ old('payroll_month') ? substr(old('payroll_month'), 0, 7) : now()->format('Y-m') }}',
                    salaryHeads: @js($salaryHeads), projectHeads: @js($projectHeads), salaries: @js($salaries),
                    get isSalary() { return this.salaryHeads.includes(String(this.head)) },
                    get isProject() { return this.projectHeads.includes(String(this.head)) },
                    pickEmployee() { if (this.salaries[this.employee] !== undefined) this.amount = this.salaries[this.employee] } }">
        @csrf
        <input type="hidden" name="_drawer" value="expense">
        <x-field label="Expense head" name="expense_head_id" :options="$heads->pluck('name', 'id')" blank="Choose…" required x-model="head" />

        {{-- Salary: who and which month --}}
        <template x-if="isSalary">
            <div class="grid grid-cols-2 gap-4">
                <x-field label="Employee" name="employee_id" :options="$employees->pluck('full_name', 'id')" blank="Choose employee…" required class="col-span-2" x-model="employee" x-on:change="pickEmployee()" />
                <x-month-picker label="Salary month" name="payroll_month" required x-model="month" />
                <div>
                    <label class="label" for="f-amount-salary">Amount (PKR)<span class="text-red-fg"> *</span></label>
                    <input id="f-amount-salary" type="number" name="amount" step="0.01" min="1" required class="input" x-model="amount">
                    <p class="hint" x-show="salaries[employee] !== undefined">From their basic salary. Change it for bonuses or deductions.</p>
                </div>
            </div>
        </template>

        {{-- Everything else --}}
        <template x-if="!isSalary">
            <div class="space-y-4">
                <div>
                    <label class="label" for="f-amount">Amount (PKR)<span class="text-red-fg"> *</span></label>
                    <input id="f-amount" type="number" name="amount" step="0.01" min="1" required class="input" x-model="amount">
                    @error('amount')<p class="error">{{ $message }}</p>@enderror
                </div>
                <x-field label="Description" name="description" required placeholder="e.g. Tea, sugar and cleaning supplies" />
                <x-field label="Vendor / paid to" name="vendor" />
                <template x-if="isProject">
                    <x-field label="Project" name="project_id" :options="$projects->mapWithKeys(fn ($p) => [$p->id => $p->name.' · '.$p->code])" blank="Choose project…" required :value="isset($project) ? $project?->id : null" />
                </template>
            </div>
        </template>

        <div class="grid grid-cols-2 gap-4">
            <x-field label="Date paid" name="expense_date" type="date" :value="today()->format('Y-m-d')" required />
            <x-field label="Payment method" name="payment_method" :options="\App\Models\Expense::METHODS" />
        </div>
        <x-field label="Reference no." name="reference_no" placeholder="Cheque / transfer reference" />
        <x-dropzone name="receipts[]" label="Receipt photo or PDF" accept="image/*,application/pdf" />
    </form>
    <x-slot:footer>
        <button type="button" class="btn btn-secondary" @click="$store.drawer.close()">Cancel</button>
        <button form="expense-form" class="btn btn-primary"><x-icon name="check" /> Save expense</button>
    </x-slot:footer>
</x-drawer>

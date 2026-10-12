<x-layouts.app :title="$employee->full_name">
    @php
        $liveLabels = ['in_office' => 'In office', 'on_site' => 'On site', 'on_leave' => 'On leave'];
        $tabs = ['profile' => 'Profile', 'salary' => 'Salary history', 'visits' => 'Site visits', 'assets' => 'Assigned assets', 'documents' => 'Documents'];
    @endphp
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-4">
            @if (auth()->user()->hasPermission('employees.manage') || auth()->user()->employee_id === $employee->id)
            <form method="POST" action="{{ route('employees.photo.update', $employee) }}" enctype="multipart/form-data" class="relative group shrink-0">
                @csrf
                <label class="block cursor-pointer rounded-full" title="{{ $employee->photo_path ? 'Change photo' : 'Upload photo' }}">
                    <x-avatar :name="$employee->full_name" :src="$employee->photoUrl()" size="size-16 text-lg" />
                    <span class="absolute inset-0 grid place-items-center rounded-full bg-gray-900/45 text-white opacity-0 group-hover:opacity-100 transition"><x-icon name="camera" class="size-5" /></span>
                    <span class="absolute -bottom-0.5 -right-0.5 grid place-items-center size-6 rounded-full bg-surface border border-line text-ink-2 shadow-sm"><x-icon name="camera" class="size-3.5" /></span>
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" onchange="this.form.submit()">
                </label>
            </form>
            @else
                <x-avatar :name="$employee->full_name" :src="$employee->photoUrl()" size="size-16 text-lg" />
            @endif
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-[22px] font-semibold">{{ $employee->full_name }}</h1>
                    <x-pill :status="$liveStatus" :label="$liveLabels[$liveStatus] ?? status_label($liveStatus)" />
                </div>
                <p class="text-muted">{{ $employee->designation }}{{ $employee->department ? ' · '.$employee->department : '' }} · {{ $employee->employee_code }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            @can('employees.manage')
                <button class="btn btn-secondary" @click="$store.drawer.open('leave')"><x-icon name="calendar-days" /> Record leave</button>
                <a href="{{ route('employees.edit', $employee) }}" class="btn btn-secondary"><x-icon name="pencil" /> Edit</a>
            @elseif (auth()->user()->employee_id === $employee->id)
                <a href="{{ route('account.edit') }}" class="btn btn-secondary"><x-icon name="pencil" /> Edit profile</a>
            @endcan
        </div>
    </div>

    <nav class="tabs mb-5">
        @foreach ($tabs as $key => $label)
            @continue($key === 'salary' && ! $canSeeSalary)
            <a href="{{ route('employees.show', [$employee, 'tab' => $key]) }}" @class(['tab', 'tab-active' => $tab === $key])>{{ $label }}</a>
        @endforeach
    </nav>

    @switch($tab)
        @case('salary')
            @if ($canSeeSalary)
                <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6">
                    <div class="space-y-6">
                        <div class="card overflow-x-auto">
                            <div class="px-5 pt-5 pb-3 card-title">Salary revisions</div>
                            <table class="table">
                                <thead><tr><th>Effective</th><th class="text-right">Basic</th><th class="text-right">Allowances</th><th class="text-right">Deductions</th><th class="text-right">Net</th><th>Reason</th></tr></thead>
                                <tbody>
                                @forelse ($employee->salaries as $s)
                                    <tr><td>{{ $s->effective_from->format('d M Y') }}</td><td class="text-right tabular-nums">{{ money($s->basic_salary) }}</td><td class="text-right tabular-nums">{{ money($s->allowances) }}</td><td class="text-right tabular-nums">{{ money($s->deductions) }}</td><td class="text-right tabular-nums font-medium">{{ money($s->net_salary) }}</td><td class="text-muted">{{ $s->reason }}</td></tr>
                                @empty
                                    <tr><td colspan="6"><x-empty icon="banknote" title="No salary records" /></td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="card overflow-x-auto">
                            <div class="px-5 pt-5 pb-3 card-title">Salary payments</div>
                            <table class="table">
                                <thead><tr><th>Paid on</th><th>Month</th><th>Status</th><th class="text-right">Amount</th></tr></thead>
                                <tbody>
                                @forelse ($employee->salaryExpenses as $p)
                                    <tr><td>{{ $p->expense_date->format('d M Y') }}</td><td>{{ $p->payroll_month?->format('M Y') ?? '—' }}</td><td><x-pill :status="$p->status" /></td><td class="text-right tabular-nums">{{ money($p->amount) }}</td></tr>
                                @empty
                                    <tr><td colspan="4"><x-empty icon="wallet" title="No payments yet" text="Record salary under Expenses with this employee selected." /></td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('salaries.store', $employee) }}" class="card card-pad space-y-4 self-start">
                        @csrf
                        <h2 class="card-title">Revise salary</h2>
                        <x-field label="Effective from" name="effective_from" type="date" :value="today()->startOfMonth()->format('Y-m-d')" required />
                        <x-field label="Basic (PKR)" name="basic_salary" type="number" step="0.01" min="0" :value="$employee->basic_salary" required />
                        <div class="grid grid-cols-2 gap-3">
                            <x-field label="Allowances" name="allowances" type="number" step="0.01" min="0" value="0" />
                            <x-field label="Deductions" name="deductions" type="number" step="0.01" min="0" value="0" />
                        </div>
                        <x-field label="Reason" name="reason" placeholder="Annual increment" />
                        <button class="btn btn-primary w-full">Save revision</button>
                    </form>
                </div>
            @endif
            @break

        @case('visits')
            <div class="card overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Site</th><th>Left</th><th>Planned return</th><th>Returned</th><th>Days</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($employee->siteVisits as $v)
                        <tr>
                            <td><div class="font-medium">{{ $v->site_name }}</div><div class="text-xs text-muted">{{ $v->project?->name }}</div></td>
                            <td>{{ $v->departure_date->format('d M Y') }}</td><td>{{ $v->expected_return_date->format('d M Y') }}</td>
                            <td>{{ $v->actual_return_date?->format('d M Y') ?? '—' }}</td><td>{{ $v->planned_days }}</td>
                            <td><x-pill :status="$v->displayStatus()" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty icon="map-pin" title="No site visits yet" /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @break

        @case('assets')
            <div class="card overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Asset</th><th>Tag</th><th>Condition</th><th>Since</th></tr></thead>
                    <tbody>
                    @forelse ($employee->assets as $a)
                        <tr class="cursor-pointer" @can('inventory.view') onclick="location='{{ route('inventory.show', $a) }}'" @endcan><td class="font-medium">{{ $a->name }}</td><td>{{ $a->asset_tag ?? '—' }}</td><td><x-pill :status="$a->condition" /></td><td>{{ $a->updated_at->format('d M Y') }}</td></tr>
                    @empty
                        <tr><td colspan="4"><x-empty icon="laptop" title="No assets assigned" /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @break

        @case('documents')
            <div class="card card-pad space-y-4">
                @can('employees.manage')
                    <form method="POST" action="{{ route('employees.documents', $employee) }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <x-dropzone label="Upload CNIC copy, CV, contract or certificates" />
                        <div class="flex justify-end"><button class="btn btn-primary btn-sm"><x-icon name="upload" /> Upload</button></div>
                    </form>
                @endcan
                <x-attachment-list :attachments="$employee->attachments" />
            </div>
            @break

        @default
            <div class="grid lg:grid-cols-3 gap-6">
                <div class="card card-pad lg:col-span-2">
                    <dl class="grid sm:grid-cols-2 gap-x-8 gap-y-4 text-[13.5px]">
                        @foreach ([
                            'CNIC' => $employee->cnic, "Father's name" => $employee->father_name, 'Phone' => $employee->phone, 'Email' => $employee->email,
                            'Date of birth' => $employee->date_of_birth?->format('d M Y'), 'Gender' => ucfirst((string) $employee->gender),
                            'Joined' => $employee->joining_date?->format('d M Y'), 'Employment' => \App\Models\Employee::TYPES[$employee->employment_type] ?? '',
                            'Emergency contact' => trim($employee->emergency_contact_name.' '.$employee->emergency_contact_phone), 'Bank' => trim($employee->bank_name.' '.$employee->bank_account_no),
                            'Site rotation' => $employee->is_site_staff ? 'Yes'.($employee->rotation_order ? ' (#'.$employee->rotation_order.')' : '') : 'No',
                            'Login' => $employee->user?->email,
                        ] as $label => $value)
                            <div><dt class="text-xs text-muted">{{ $label }}</dt><dd class="font-medium">{{ $value ?: '—' }}</dd></div>
                        @endforeach
                        <div class="sm:col-span-2"><dt class="text-xs text-muted">Address</dt><dd>{{ $employee->address ?: '—' }}</dd></div>
                    </dl>
                </div>
                <div class="card">
                    <div class="px-5 pt-5 pb-2 card-title">Leaves</div>
                    @forelse ($employee->leaves as $l)
                        <div class="flex items-center gap-3 px-5 py-2.5 border-t border-line">
                            <div class="flex-1 text-[13px]">
                                <div class="font-medium">{{ \App\Models\EmployeeLeave::TYPES[$l->leave_type] ?? $l->leave_type }} · {{ $l->days() }} day(s)</div>
                                <div class="text-xs text-muted">{{ $l->start_date->format('d M') }} – {{ $l->end_date->format('d M Y') }}{{ $l->reason ? ' · '.$l->reason : '' }}</div>
                            </div>
                            @can('employees.manage')<x-action :url="route('leaves.destroy', $l)" method="DELETE" confirm="Remove this leave?" class="btn btn-icon !size-7 hover:text-red-fg"><x-icon name="trash-2" /></x-action>@endcan
                        </div>
                    @empty
                        <p class="px-5 pb-5 text-[13px] text-muted">No leaves recorded.</p>
                    @endforelse
                </div>
            </div>
    @endswitch

    <x-slot:drawers>
        <x-drawer name="leave" title="Record leave" :subtitle="$employee->full_name">
            <form id="leave-form" method="POST" action="{{ route('leaves.store', $employee) }}" class="space-y-4">
                @csrf
                <input type="hidden" name="_drawer" value="leave">
                <x-field label="Type" name="leave_type" :options="\App\Models\EmployeeLeave::TYPES" />
                <div class="grid grid-cols-2 gap-4">
                    <x-field label="From" name="start_date" type="date" :value="today()->format('Y-m-d')" required />
                    <x-field label="To" name="end_date" type="date" :value="today()->format('Y-m-d')" required />
                </div>
                <x-field label="Reason" name="reason" />
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" @click="$store.drawer.close()">Cancel</button>
                <button form="leave-form" class="btn btn-primary">Save</button>
            </x-slot:footer>
        </x-drawer>
    </x-slot:drawers>
</x-layouts.app>

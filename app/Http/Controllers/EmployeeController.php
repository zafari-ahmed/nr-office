<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Support\Activity;
use App\Support\Numbering;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $employees = Employee::with('currentVisit')
            ->when($request->status !== 'all', fn ($q) => $q->active())
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('full_name', 'like', "%$s%")->orWhere('designation', 'like', "%$s%")->orWhere('cnic', 'like', "%$s%")))
            ->orderBy('full_name')->get();

        $employees->each(fn ($e) => $e->live_status = $e->liveStatus());
        if ($request->live) {
            $employees = $employees->where('live_status', $request->live)->values();
        }

        return view('employees.index', compact('employees'));
    }

    public function create()
    {
        return view('employees.form', ['employee' => new Employee([
            'employee_code' => Numbering::employee(), 'employment_type' => 'permanent', 'status' => 'active', 'joining_date' => today(),
        ])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['employee_code'] = Numbering::employee();
        $employee = Employee::create(collect($data)->except('photo')->all());
        $this->savePhoto($request, $employee);
        if ($employee->basic_salary > 0) {
            $employee->salaries()->create([
                'effective_from' => $employee->joining_date ?? today(), 'basic_salary' => $employee->basic_salary,
                'net_salary' => $employee->basic_salary, 'reason' => 'Joining salary', 'created_by' => $request->user()->id,
            ]);
        }
        Activity::log('created', "Employee {$employee->full_name} added", $employee);

        return redirect()->route('employees.show', $employee)->with('success', 'Employee added.');
    }

    public function show(Request $request, Employee $employee)
    {
        $employee->load('salaries', 'leaves', 'siteVisits.project', 'assets', 'attachments', 'user', 'salaryExpenses.head');

        return view('employees.show', [
            'employee' => $employee,
            'liveStatus' => $employee->liveStatus(),
            'tab' => $request->query('tab', 'profile'),
            'canSeeSalary' => $request->user()->hasRole('admin', 'accounts'),
        ]);
    }

    public function edit(Employee $employee)
    {
        return view('employees.form', compact('employee'));
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $this->validated($request, $employee);
        $employee->update(collect($data)->except('photo', 'basic_salary')->all());
        $this->savePhoto($request, $employee);

        return redirect()->route('employees.show', $employee)->with('success', 'Employee updated.');
    }

    public function uploadDocuments(Request $request, Employee $employee)
    {
        $request->validate(['files' => ['required', 'array'], 'files.*' => ['file', 'max:20480']]);
        $employee->attachFiles($request->file('files'), 'document');

        return redirect()->route('employees.show', [$employee, 'tab' => 'documents'])->with('success', 'Documents uploaded.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Employee archived.');
    }

    private function savePhoto(Request $request, Employee $employee): void
    {
        if ($request->hasFile('photo')) {
            if ($employee->photo_path) {
                Storage::disk('public')->delete($employee->photo_path);
            }
            $employee->update(['photo_path' => $request->file('photo')->store('employees/photos', 'public')]);
        }
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'cnic' => ['nullable', 'regex:/^\d{5}-\d{7}-\d$/', Rule::unique('employees')->ignore($employee)],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'designation' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['required', Rule::in(array_keys(Employee::TYPES))],
            'joining_date' => ['nullable', 'date'],
            'leaving_date' => ['nullable', 'date'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_no' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(array_keys(Employee::STATUSES))],
            'rotation_order' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ], ['cnic.regex' => 'CNIC must look like 35202-1234567-1.']);

        $data['is_site_staff'] = $request->boolean('is_site_staff');
        $data['basic_salary'] ??= 0;

        return $data;
    }
}

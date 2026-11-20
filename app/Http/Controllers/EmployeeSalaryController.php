<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Support\Activity;
use Illuminate\Http\Request;

class EmployeeSalaryController extends Controller
{
    public function store(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'effective_from' => ['required', 'date'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $data['allowances'] ??= 0;
        $data['deductions'] ??= 0;
        $data['net_salary'] = $data['basic_salary'] + $data['allowances'] - $data['deductions'];

        $employee->salaries()->create([...$data, 'created_by' => $request->user()->id]);
        if (\Illuminate\Support\Carbon::parse($data['effective_from'])->lte(today())) {
            $employee->update(['basic_salary' => $data['basic_salary']]);
        }
        Activity::log('salary_revised', "Salary revised for {$employee->full_name}", $employee);

        return redirect()->route('employees.show', [$employee, 'tab' => 'salary'])->with('success', 'Salary revision saved.');
    }

    /** Remove a revision; the employee's current salary falls back to the latest one still in effect (0 if none). */
    public function destroy(EmployeeSalary $salary)
    {
        $employee = $salary->employee;
        $salary->delete();
        $current = $employee->salaries()->whereDate('effective_from', '<=', today())->orderByDesc('effective_from')->orderByDesc('id')->first();
        $employee->update(['basic_salary' => $current?->basic_salary ?? 0]);
        Activity::log('salary_revision_deleted', "Salary revision from {$salary->effective_from->format('d M Y')} deleted for {$employee->full_name}", $employee);

        return redirect()->route('employees.show', [$employee, 'tab' => 'salary'])->with('success', 'Salary revision deleted.');
    }
}

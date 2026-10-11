<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeLeave;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeLeaveController extends Controller
{
    public function store(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'leave_type' => ['required', Rule::in(array_keys(EmployeeLeave::TYPES))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $employee->leaves()->create([...$data, 'status' => 'approved', 'approved_by' => $request->user()->id]);

        return redirect()->route('employees.show', [$employee, 'tab' => 'profile'])->with('success', 'Leave recorded.');
    }

    public function destroy(EmployeeLeave $leave)
    {
        $leave->delete();

        return back()->with('success', 'Leave removed.');
    }
}

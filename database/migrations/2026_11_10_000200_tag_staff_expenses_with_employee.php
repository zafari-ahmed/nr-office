<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Expenses now belong to the employee who spent them. Tag the ones Staff already filed (not salaries)
     * with the employee profile of the person who added them.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'staff')->whereNotNull('employee_id')->get(['id', 'employee_id'])
            ->each(fn ($user) => DB::table('expenses')->where('created_by', $user->id)
                ->whereNull('employee_id')->whereNull('payroll_month')
                ->update(['employee_id' => $user->employee_id]));
    }

    public function down(): void
    {
        // Nothing to undo: the tag is still correct if this is rolled back.
    }
};

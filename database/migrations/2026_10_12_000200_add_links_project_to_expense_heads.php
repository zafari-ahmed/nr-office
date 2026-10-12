<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only heads with links_project show (and require) the "Link to project" field on an expense.
        Schema::table('expense_heads', function (Blueprint $table) {
            $table->boolean('links_project')->default(false)->after('monthly_budget');
        });

        if (! DB::table('expense_heads')->where('slug', 'project-expenses')->exists()) {
            DB::table('expense_heads')->insert([
                'name' => 'Project expenses', 'slug' => 'project-expenses', 'color' => 'blue', 'icon' => 'folder-kanban',
                'monthly_budget' => 0, 'links_project' => true, 'is_active' => true,
                'sort_order' => (int) DB::table('expense_heads')->max('sort_order') + 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } else {
            DB::table('expense_heads')->where('slug', 'project-expenses')->update(['links_project' => true]);
        }
    }

    public function down(): void
    {
        DB::table('expense_heads')->where('slug', 'project-expenses')->whereNotExists(
            fn ($q) => $q->from('expenses')->whereColumn('expenses.expense_head_id', 'expense_heads.id')
        )->delete();
        Schema::table('expense_heads', fn (Blueprint $table) => $table->dropColumn('links_project'));
    }
};

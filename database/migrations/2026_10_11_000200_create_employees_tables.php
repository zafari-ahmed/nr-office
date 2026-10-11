<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 20)->unique();
            $table->string('full_name');
            $table->string('father_name')->nullable();
            $table->string('cnic', 15)->nullable()->unique();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('designation')->nullable();
            $table->string('department')->nullable();
            $table->string('employment_type', 20)->default('permanent');
            $table->date('joining_date')->nullable();
            $table->date('leaving_date')->nullable();
            $table->decimal('basic_salary', 14, 2)->default(0);
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no', 50)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->boolean('is_site_staff')->default(false);
            $table->unsignedInteger('rotation_order')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->decimal('basic_salary', 14, 2);
            $table->decimal('allowances', 14, 2)->default(0);
            $table->decimal('deductions', 14, 2)->default(0);
            $table->decimal('net_salary', 14, 2);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('employee_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('leave_type', 20)->default('annual');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason')->nullable();
            $table->string('status', 20)->default('approved')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leaves');
        Schema::dropIfExists('employee_salaries');
        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['employee_id']));
        Schema::dropIfExists('employees');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 20)->index();
            $table->string('reference_no', 60)->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->date('sent_date');
            $table->string('sent_to_name')->nullable();
            $table->string('sent_to_email')->nullable();
            $table->text('last_discussion')->nullable();
            $table->string('email_subject')->nullable();
            $table->longText('email_body')->nullable();
            $table->date('next_follow_up_date')->nullable();
            $table->string('status', 20)->default('sent')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_logs');
    }
};

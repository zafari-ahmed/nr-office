<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Letterhead documents: letterheads (header + footer images cut from an uploaded PDF/Word file),
     * templates (a starting body per document type, e.g. Quotation, Purchase order), documents made from them,
     * and a print history with a copy of what was printed each time.
     */
    public function up(): void
    {
        Schema::create('letterheads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('source_path')->nullable();      // the uploaded PDF / Word / image, kept for reference
            $table->string('source_name')->nullable();
            $table->string('header_path')->nullable();      // PNG, full page width
            $table->string('footer_path')->nullable();
            $table->decimal('header_mm', 6, 1)->default(40); // space the header takes at the top of every page
            $table->decimal('footer_mm', 6, 1)->default(25);
            $table->decimal('side_mm', 6, 1)->default(20);   // left and right margin for the content
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ref_prefix', 10)->default('DOC');
            $table->foreignId('letterhead_id')->nullable()->constrained()->nullOnDelete(); // null = default letterhead
            $table->longText('body')->nullable();
            $table->string('sample_path')->nullable();   // the old document it was made from
            $table->string('sample_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no')->unique();
            $table->foreignId('template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->foreignId('letterhead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('recipient')->nullable();
            $table->date('doc_date');
            $table->longText('body')->nullable();
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_prints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('letterhead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('recipient')->nullable();
            $table->date('doc_date');
            $table->longText('body')->nullable();   // exactly what was printed, placeholders filled in
            $table->boolean('with_letterhead')->default(true);
            $table->timestamp('printed_at');
            $table->index(['document_id', 'printed_at']);
        });

        // Managers and accounts can make documents; add the right to roles whose access Admin already saved.
        foreach (['manager', 'accounts'] as $role) {
            $row = DB::table('settings')->where('key', "access.{$role}")->first();
            if ($row) {
                $keys = array_values(array_unique(array_merge(array_filter(explode(',', (string) $row->value)), ['documents.manage'])));
                DB::table('settings')->where('key', "access.{$role}")->update(['value' => implode(',', $keys), 'updated_at' => now()]);
            }
        }
        Cache::forget('settings.all');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_prints');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('letterheads');
    }
};

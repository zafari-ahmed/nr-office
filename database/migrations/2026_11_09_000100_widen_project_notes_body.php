<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Notes now hold formatted HTML from the editor, which can outgrow a TEXT column when pasted from documents. */
    public function up(): void
    {
        Schema::table('project_notes', fn (Blueprint $table) => $table->mediumText('body')->change());
    }

    public function down(): void
    {
        Schema::table('project_notes', fn (Blueprint $table) => $table->text('body')->change());
    }
};

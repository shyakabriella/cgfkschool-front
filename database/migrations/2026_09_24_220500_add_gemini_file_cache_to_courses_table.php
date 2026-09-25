<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->json('gemini_curriculum_file')
                ->nullable()
                ->after('notes_path');

            $table->json('gemini_notes_file')
                ->nullable()
                ->after('gemini_curriculum_file');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'gemini_curriculum_file',
                'gemini_notes_file',
            ]);
        });
    }
};

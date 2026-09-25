<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('curriculum_path')
                ->nullable()
                ->after('description');

            $table->string('notes_path')
                ->nullable()
                ->after('curriculum_path');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'curriculum_path',
                'notes_path',
            ]);
        });
    }
};

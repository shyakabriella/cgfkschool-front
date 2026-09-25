<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_note_chunks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('course_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('page_number');

            $table->longText('content');

            $table->string('content_hash', 64);

            $table->timestamps();

            $table->unique([
                'course_id',
                'page_number',
            ]);

            $table->index([
                'course_id',
                'content_hash',
            ]);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('notes_index_status', 20)
                ->default('pending')
                ->after('notes_path');

            $table->unsignedInteger('notes_indexed_pages')
                ->default(0)
                ->after('notes_index_status');

            $table->timestamp('notes_indexed_at')
                ->nullable()
                ->after('notes_indexed_pages');

            $table->text('notes_index_error')
                ->nullable()
                ->after('notes_indexed_at');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'notes_index_status',
                'notes_indexed_pages',
                'notes_indexed_at',
                'notes_index_error',
            ]);
        });

        Schema::dropIfExists('course_note_chunks');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('course_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('teacher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('type', [
                'assignment',
                'quiz',
                'exam',
            ]);

            $table->string('title');

            $table->enum('difficulty', [
                'easy',
                'medium',
                'hard',
                'mixed',
            ])->default('medium');

            $table->text('instructions')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedInteger('total_marks')->default(0);
            $table->unsignedInteger('question_count')->default(0);

            $table->json('source_documents')->nullable();
            $table->string('ai_model')->nullable();

            $table->enum('status', [
                'draft',
                'published',
                'archived',
            ])->default('draft');

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};

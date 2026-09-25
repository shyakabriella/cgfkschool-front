<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'teaching_materials',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('course_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('teacher_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->foreignId('school_class_id')
                    ->constrained('school_classes')
                    ->cascadeOnDelete();

                $table->foreignId(
                    'course_learning_unit_id'
                )
                    ->constrained(
                        'course_learning_units'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'course_indicative_content_id'
                )
                    ->constrained(
                        'course_indicative_contents'
                    )
                    ->cascadeOnDelete();

                $table->string('title');
                $table->date('lesson_date');
                $table->unsignedInteger(
                    'duration_minutes'
                );

                $table->json('generated_content')->nullable();
                $table->json('source_documents')->nullable();
                $table->string('ai_model')->nullable();

                $table->enum('status', [
                    'draft',
                    'approved',
                    'insufficient_material',
                    'archived',
                ])->default('draft');

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_materials');
    }
};

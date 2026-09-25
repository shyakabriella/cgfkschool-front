<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'assessment_attempts',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId(
                    'assessment_assignment_id'
                )
                    ->unique()
                    ->constrained(
                        'assessment_assignments'
                    )
                    ->cascadeOnDelete();

                $table->foreignId('student_id')
                    ->constrained('students')
                    ->cascadeOnDelete();

                $table->unsignedInteger(
                    'current_position'
                )->default(1);

                $table->timestamp(
                    'question_started_at'
                )->nullable();

                $table->timestamp(
                    'started_at'
                )->nullable();

                $table->timestamp(
                    'submitted_at'
                )->nullable();

                $table->string('status')
                    ->default('in_progress');

                $table->decimal(
                    'score',
                    8,
                    2
                )->default(0);

                $table->timestamps();

                $table->index([
                    'student_id',
                    'status',
                ]);
            }
        );

        Schema::create(
            'assessment_answers',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId(
                    'assessment_attempt_id'
                )
                    ->constrained(
                        'assessment_attempts'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'assessment_question_id'
                )
                    ->constrained(
                        'assessment_questions'
                    )
                    ->cascadeOnDelete();

                $table->longText('answer')
                    ->nullable();

                $table->boolean('timed_out')
                    ->default(false);

                $table->boolean('is_correct')
                    ->nullable();

                $table->decimal(
                    'marks_awarded',
                    8,
                    2
                )->nullable();

                $table->timestamp('shown_at')
                    ->nullable();

                $table->timestamp('answered_at')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'assessment_attempt_id',
                        'assessment_question_id',
                    ],
                    'attempt_question_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'assessment_answers'
        );

        Schema::dropIfExists(
            'assessment_attempts'
        );
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'assessment_assignments',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('assessment_id')
                    ->constrained('assessments')
                    ->cascadeOnDelete();

                $table->foreignId('student_id')
                    ->constrained('students')
                    ->cascadeOnDelete();

                $table->foreignId('assigned_by')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->string('status')
                    ->default('assigned');

                $table->timestamp('assigned_at')
                    ->nullable();

                $table->timestamp('due_at')
                    ->nullable();

                $table->timestamp('submitted_at')
                    ->nullable();

                $table->decimal(
                    'score',
                    8,
                    2
                )->nullable();

                $table->timestamps();

                $table->unique([
                    'assessment_id',
                    'student_id',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'assessment_assignments'
        );
    }
};

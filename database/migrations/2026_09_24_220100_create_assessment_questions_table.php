<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'assessment_questions',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('assessment_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->enum('type', [
                    'multiple_choice',
                    'true_false',
                    'short_answer',
                    'essay',
                ]);

                $table->longText('question');
                $table->json('options')->nullable();
                $table->longText('correct_answer')->nullable();
                $table->text('explanation')->nullable();
                $table->unsignedInteger('marks')->default(1);
                $table->unsignedInteger('position')->default(1);
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_questions');
    }
};

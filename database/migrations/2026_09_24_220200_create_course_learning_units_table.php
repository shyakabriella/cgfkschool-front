<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'course_learning_units',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('course_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->string('code')->nullable();
                $table->text('title');
                $table->text('description')->nullable();
                $table->json('learning_outcomes')->nullable();
                $table->string('source_page')->nullable();
                $table->unsignedInteger('position')->default(1);
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('course_learning_units');
    }
};

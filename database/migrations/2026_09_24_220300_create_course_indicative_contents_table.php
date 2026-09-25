<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'course_indicative_contents',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('course_learning_unit_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->text('title');
                $table->text('details')->nullable();
                $table->string('source_page')->nullable();
                $table->unsignedInteger('position')->default(1);
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'course_indicative_contents'
        );
    }
};

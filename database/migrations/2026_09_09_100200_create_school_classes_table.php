<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the school classes table.
     */
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('department_program_id')
                ->constrained('department_programs')
                ->restrictOnDelete();

            $table->string('name', 150);
            $table->string('code', 30);

            $table->string('level', 50)->nullable();
            $table->text('description')->nullable();

            $table->enum('status', [
                'active',
                'inactive',
            ])->default('active');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['department_program_id', 'name'],
                'school_class_program_name_unique'
            );

            $table->unique(
                ['department_program_id', 'code'],
                'school_class_program_code_unique'
            );

            $table->index([
                'department_program_id',
                'status',
            ]);
        });
    }

    /**
     * Remove the school classes table.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};

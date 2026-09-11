<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the department programs table.
     */
    public function up(): void
    {
        Schema::create('department_programs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('department_id')
                ->constrained('departments')
                ->restrictOnDelete();

            $table->string('name', 150);
            $table->string('code', 30);

            $table->enum('type', [
                'trade',
                'option',
            ]);

            $table->text('description')->nullable();

            $table->enum('status', [
                'active',
                'inactive',
            ])->default('active');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['department_id', 'name'],
                'department_program_name_unique'
            );

            $table->unique(
                ['department_id', 'code'],
                'department_program_code_unique'
            );

            $table->index([
                'department_id',
                'type',
                'status',
            ]);
        });
    }

    /**
     * Remove the department programs table.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_programs');
    }
};

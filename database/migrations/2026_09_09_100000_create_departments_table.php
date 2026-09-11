<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the departments table.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150)->unique();
            $table->string('code', 30)->unique();

            $table->text('description')->nullable();

            $table->enum('status', [
                'active',
                'inactive',
            ])->default('active');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Remove the departments table.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};

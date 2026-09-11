<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('school_class_id')
                ->nullable()
                ->constrained('school_classes')
                ->nullOnDelete();

            $table->string('name');
            $table->string('code', 50);
            $table->decimal('amount', 15, 2);
            $table->string('academic_year', 20);
            $table->unsignedTinyInteger('term')->nullable();
            $table->boolean('is_required')->default(true);
            $table->enum('status', ['active', 'inactive'])
                ->default('active');
            $table->timestamps();

            $table->index([
                'academic_year',
                'term',
                'school_class_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_items');
    }
};

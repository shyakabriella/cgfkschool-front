<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rwanda_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('rwanda_locations')
                ->cascadeOnDelete();

            $table->string('name', 150);
            $table->enum('type', [
                'province',
                'district',
                'sector',
                'cell',
                'village',
            ]);

            $table->timestamps();

            $table->unique(
                ['parent_id', 'type', 'name'],
                'rwanda_location_parent_type_name_unique'
            );

            $table->index(['type', 'name']);
            $table->index(['parent_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rwanda_locations');
    }
};

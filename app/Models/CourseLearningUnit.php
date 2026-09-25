<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseLearningUnit extends Model
{
    protected $fillable = [
        'course_id',
        'code',
        'title',
        'description',
        'learning_outcomes',
        'source_page',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'learning_outcomes' => 'array',
            'position' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function indicativeContents(): HasMany
    {
        return $this->hasMany(
            CourseIndicativeContent::class
        )->orderBy('position');
    }

    public function teachingMaterials(): HasMany
    {
        return $this->hasMany(
            TeachingMaterial::class
        );
    }
}

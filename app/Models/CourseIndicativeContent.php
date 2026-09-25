<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseIndicativeContent extends Model
{
    protected $fillable = [
        'course_learning_unit_id',
        'title',
        'details',
        'source_page',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function learningUnit(): BelongsTo
    {
        return $this->belongsTo(
            CourseLearningUnit::class,
            'course_learning_unit_id'
        );
    }

    public function teachingMaterials(): HasMany
    {
        return $this->hasMany(
            TeachingMaterial::class
        );
    }
}

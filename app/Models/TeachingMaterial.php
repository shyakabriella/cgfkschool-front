<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingMaterial extends Model
{
    protected $fillable = [
        'course_id',
        'teacher_id',
        'school_class_id',
        'course_learning_unit_id',
        'course_indicative_content_id',
        'title',
        'lesson_date',
        'duration_minutes',
        'generated_content',
        'source_documents',
        'ai_model',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'lesson_date' => 'date',
            'duration_minutes' => 'integer',
            'generated_content' => 'array',
            'source_documents' => 'array',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'teacher_id'
        );
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function learningUnit(): BelongsTo
    {
        return $this->belongsTo(
            CourseLearningUnit::class,
            'course_learning_unit_id'
        );
    }

    public function indicativeContent(): BelongsTo
    {
        return $this->belongsTo(
            CourseIndicativeContent::class,
            'course_indicative_content_id'
        );
    }
}

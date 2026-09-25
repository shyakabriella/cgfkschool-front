<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'teacher_id',
        'school_class_id',
        'course_learning_unit_id',
        'course_indicative_content_id',
        'type',
        'title',
        'difficulty',
        'instructions',
        'duration_minutes',
        'total_marks',
        'question_count',
        'source_documents',
        'ai_model',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'total_marks' => 'integer',
            'question_count' => 'integer',
            'source_documents' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(
            SchoolClass::class
        );
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

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'teacher_id'
        );
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class)
            ->orderBy('position');
    }
}

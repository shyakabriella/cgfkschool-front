<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'first_name',
        'last_name',
        'father_name',
        'mother_name',
        'parent_contact',
        'guardian_name',
        'guardian_contact',
        'guardian_relationship',
        'date_of_birth',
        'school_class_id',
        'province',
        'district',
        'sector',
        'cell',
        'village',
        'previous_school_name',
        'documents',
        'image',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'documents' => 'array',
        ];
    }

    protected $appends = [
        'full_name',
        'image_url',
        'document_urls',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image
            ? asset('storage/'.$this->image)
            : null;
    }

    public function getDocumentUrlsAttribute(): array
    {
        return collect($this->documents ?? [])
            ->map(fn (string $document) => asset('storage/'.$document))
            ->values()
            ->all();
    }
}

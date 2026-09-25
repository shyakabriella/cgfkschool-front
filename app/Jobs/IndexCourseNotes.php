<?php

namespace App\Jobs;

use App\Models\Course;
use App\Services\CourseNoteIndexer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class IndexCourseNotes implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(
        public int $courseId
    ) {
    }

    public function handle(
        CourseNoteIndexer $indexer
    ): void {
        $course = Course::query()->findOrFail(
            $this->courseId
        );

        $course->forceFill([
            'notes_index_status' => 'processing',
            'notes_index_error' => null,
        ])->save();

        try {
            $indexer->index($course);
        } catch (Throwable $exception) {
            $course->forceFill([
                'notes_index_status' => 'failed',
                'notes_index_error' => mb_substr(
                    $exception->getMessage(),
                    0,
                    2000
                ),
            ])->save();

            throw $exception;
        }
    }
}

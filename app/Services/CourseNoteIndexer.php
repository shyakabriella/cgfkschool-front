<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseNoteChunk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class CourseNoteIndexer
{
    public function index(Course $course): int
    {
        if (! $course->notes_path) {
            throw new RuntimeException(
                'This course does not have an uploaded notes PDF.'
            );
        }

        $pdfPath = $this->resolveNotesPath(
            $course->notes_path
        );

        if (! file_exists($pdfPath)) {
            throw new RuntimeException(
                'The uploaded course notes file could not be found.'
            );
        }

        $temporaryDirectory = storage_path(
            'app/course-note-indexing'
        );

        if (! is_dir($temporaryDirectory)) {
            mkdir($temporaryDirectory, 0775, true);
        }

        $textPath = $temporaryDirectory
            . DIRECTORY_SEPARATOR
            . 'course-' . $course->id . '-notes.txt';

        $process = new Process([
            'pdftotext',
            '-layout',
            '-enc',
            'UTF-8',
            $pdfPath,
            $textPath,
        ]);

        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                trim($process->getErrorOutput())
                    ?: 'The PDF text could not be extracted.'
            );
        }

        if (! file_exists($textPath)) {
            throw new RuntimeException(
                'The extracted notes text file was not created.'
            );
        }

        $extractedText = file_get_contents($textPath);

        @unlink($textPath);

        if (! is_string($extractedText)) {
            throw new RuntimeException(
                'The extracted course notes could not be read.'
            );
        }

        /*
         * pdftotext separates PDF pages using a form-feed
         * character. This lets us preserve the original page
         * number without manually separating the PDF.
         */
        $pages = preg_split(
            "/\f/",
            $extractedText
        );

        if (! is_array($pages)) {
            throw new RuntimeException(
                'The PDF pages could not be separated.'
            );
        }

        $chunks = [];

        foreach ($pages as $index => $pageContent) {
            $content = $this->cleanText($pageContent);

            if ($content === '') {
                continue;
            }

            $chunks[] = [
                'course_id' => $course->id,
                'page_number' => $index + 1,
                'content' => $content,
                'content_hash' => hash(
                    'sha256',
                    $content
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (count($chunks) === 0) {
            throw new RuntimeException(
                'No readable text was found in the PDF. '
                . 'The PDF may contain scanned images only.'
            );
        }

        DB::transaction(function () use ($course, $chunks) {
            CourseNoteChunk::query()
                ->where('course_id', $course->id)
                ->delete();

            /*
             * Insert records in small groups to avoid creating
             * one very large database query.
             */
            foreach (array_chunk($chunks, 25) as $group) {
                CourseNoteChunk::query()->insert($group);
            }

            $course->forceFill([
                'notes_index_status' => 'ready',
                'notes_indexed_pages' => count($chunks),
                'notes_indexed_at' => now(),
                'notes_index_error' => null,
            ])->save();
        });

        return count($chunks);
    }

    private function resolveNotesPath(string $storedPath): string
    {
        $storedPath = ltrim($storedPath, '/');

        if (str_starts_with($storedPath, 'storage/')) {
            $storedPath = substr($storedPath, 8);
        }

        if (Storage::disk('public')->exists($storedPath)) {
            return Storage::disk('public')->path($storedPath);
        }

        if (Storage::disk('local')->exists($storedPath)) {
            return Storage::disk('local')->path($storedPath);
        }

        $directPath = storage_path('app/public/' . $storedPath);

        if (file_exists($directPath)) {
            return $directPath;
        }

        throw new RuntimeException(
            'Course notes file was not found at: '
            . $storedPath
        );
    }

    private function cleanText(string $content): string
    {
        $content = str_replace(
            ["\r\n", "\r"],
            "\n",
            $content
        );

        $content = preg_replace(
            '/[ \t]+/',
            ' ',
            $content
        ) ?? $content;

        $content = preg_replace(
            "/\n{3,}/",
            "\n\n",
            $content
        ) ?? $content;

        return trim($content);
    }
}

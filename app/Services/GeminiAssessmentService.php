<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GeminiAssessmentService
{
    public function generate(
        Course $course,
        array $settings
    ): array {
        $apiKey = (string) config('gemini.api_key');
        $model = (string) config('gemini.model');

        if ($apiKey === '') {
            throw new RuntimeException(
                'The Gemini API key is not configured.'
            );
        }

        if (! $course->notes_path) {
            throw new RuntimeException(
                'Upload the course notes before generating an assessment.'
            );
        }

        $noteContext = app(
            CourseNoteSearchService::class
        )->search(
            $course,
            $settings['learning_unit_title'],
            $settings['indicative_content_title'],
            8
        );

        $selectedPages = implode(
            ', ',
            $noteContext['pages']
        );

        $prompt = $this->buildPrompt(
            $course,
            $settings
        );

        $prompt .= <<<PROMPT


SELECTED SOURCE INFORMATION

The following text was extracted from the relevant pages of the uploaded course notes.

Selected PDF pages: {$selectedPages}

Important rules:
- Use only the source text provided below.
- Do not use general knowledge.
- Do not create questions from another learning unit.
- Do not create questions from another indicative content.
- Every answer must be directly supported by this source.
- If the source is insufficient, do not invent facts.

BEGIN COURSE NOTES SOURCE

{$noteContext['content']}

END COURSE NOTES SOURCE
PROMPT;

        $url = sprintf(
            '%s/v1beta/models/%s:generateContent',
            rtrim(
                (string) config('gemini.base_url'),
                '/'
            ),
            $model
        );

        $response = Http::connectTimeout(15)
            ->timeout(120)
            ->withQueryParameters([
                'key' => $apiKey,
            ])
            ->post($url, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            [
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 4000,
                    'responseMimeType' =>
                        'application/json',
                    'responseJsonSchema' =>
                        $this->responseSchema(),
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                $response->json('error.message')
                ?: 'Gemini could not generate the assessment.'
            );
        }

        $json = $response->json(
            'candidates.0.content.parts.0.text'
        );

        if (! is_string($json) || trim($json) === '') {
            throw new RuntimeException(
                'Gemini returned an empty assessment.'
            );
        }

        $assessment = json_decode(
            $json,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (
            ! isset($assessment['questions'])
            || ! is_array($assessment['questions'])
        ) {
            throw new RuntimeException(
                'Gemini returned an invalid assessment format.'
            );
        }

        return [
            'assessment' => $assessment,
            'documents' => [
                [
                    'document' => 'Course notes',
                    'pages' => $noteContext['pages'],
                ],
            ],
            'model' => $model,
        ];
    }

    private function prepareDocuments(
        Course $course,
        array $settings
    ): array {
        $documents = [];

        if (
            ($settings['use_curriculum'] ?? true) &&
            $course->curriculum_path
        ) {
            $documents[] = $this->documentInformation(
                $course->curriculum_path,
                'Course curriculum',
                'curriculum'
            );
        }

        if (
            ($settings['use_notes'] ?? true) &&
            $course->notes_path
        ) {
            $documents[] = $this->documentInformation(
                $course->notes_path,
                'Course notes',
                'notes'
            );
        }

        return array_values(
            array_filter($documents)
        );
    }

    private function documentInformation(
        string $storagePath,
        string $name,
        string $source
    ): ?array {
        $disk = Storage::disk('public');

        if (! $disk->exists($storagePath)) {
            return null;
        }

        $extension = strtolower(
            pathinfo($storagePath, PATHINFO_EXTENSION)
        );

        if ($extension !== 'pdf') {
            throw new RuntimeException(
                "{$name} must be uploaded as a PDF before AI generation."
            );
        }

        return [
            'path' => $disk->path($storagePath),
            'name' => $name,
            'source' => $source,
        ];
    }

    private function uploadFile(
        string $absolutePath,
        string $displayName
    ): array {
        $apiKey = (string) config('gemini.api_key');
        $baseUrl = rtrim(
            (string) config('gemini.base_url'),
            '/'
        );

        $fileSize = filesize($absolutePath);

        if ($fileSize === false) {
            throw new RuntimeException(
                'The course document could not be read.'
            );
        }

        $startResponse = Http::timeout(60)
            ->withHeaders([
                'X-Goog-Upload-Protocol' => 'resumable',
                'X-Goog-Upload-Command' => 'start',
                'X-Goog-Upload-Header-Content-Length' =>
                    (string) $fileSize,
                'X-Goog-Upload-Header-Content-Type' =>
                    'application/pdf',
            ])
            ->withQueryParameters([
                'key' => $apiKey,
            ])
            ->post(
                "{$baseUrl}/upload/v1beta/files",
                [
                    'file' => [
                        'display_name' => $displayName,
                    ],
                ]
            );

        if ($startResponse->failed()) {
            throw new RuntimeException(
                $startResponse->json('error.message')
                ?: 'Gemini could not start the document upload.'
            );
        }

        $uploadUrl = $startResponse->header(
            'X-Goog-Upload-URL'
        );

        if (! $uploadUrl) {
            throw new RuntimeException(
                'Gemini did not return a document upload URL.'
            );
        }

        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            throw new RuntimeException(
                'The course document could not be opened.'
            );
        }

        $uploadResponse = Http::timeout(
            (int) config('gemini.timeout', 180)
        )
            ->withHeaders([
                'Content-Length' => (string) $fileSize,
                'X-Goog-Upload-Offset' => '0',
                'X-Goog-Upload-Command' =>
                    'upload, finalize',
            ])
            ->withBody(
                $contents,
                'application/pdf'
            )
            ->post($uploadUrl);

        if ($uploadResponse->failed()) {
            throw new RuntimeException(
                $uploadResponse->json('error.message')
                ?: 'The document failed to upload to Gemini.'
            );
        }

        $file = $uploadResponse->json('file');

        if (
            ! is_array($file) ||
            empty($file['uri'])
        ) {
            throw new RuntimeException(
                'Gemini returned invalid uploaded-file information.'
            );
        }

        return $this->waitUntilActive($file);
    }

    private function waitUntilActive(array $file): array
    {
        $state = $file['state'] ?? 'ACTIVE';

        if ($state === 'ACTIVE') {
            return $file;
        }

        $name = $file['name'] ?? null;

        if (! $name) {
            return $file;
        }

        for ($attempt = 0; $attempt < 15; $attempt++) {
            usleep(500000);

            $response = Http::timeout(30)
                ->withQueryParameters([
                    'key' => config('gemini.api_key'),
                ])
                ->get(
                    rtrim(
                        (string) config('gemini.base_url'),
                        '/'
                    )."/v1beta/{$name}"
                );

            if ($response->failed()) {
                continue;
            }

            $file = $response->json();
            $state = $file['state'] ?? null;

            if ($state === 'ACTIVE') {
                return $file;
            }

            if ($state === 'FAILED') {
                throw new RuntimeException(
                    'Gemini failed to process the PDF document.'
                );
            }
        }

        throw new RuntimeException(
            'Gemini is still processing the document. Please try again.'
        );
    }

    private function buildPrompt(
        Course $course,
        array $settings
    ): string {
        $types = implode(
            ', ',
            $settings['question_types']
        );

        return <<<PROMPT
You are an experienced secondary school teacher.

Create a {$settings['type']} using only information found in the provided extracted course-notes pages.

Course name: {$course->name}
Course code: {$course->code}
Selected learning unit: {$settings['learning_unit_title']}
Selected indicative content: {$settings['indicative_content_title']}
Assessment title: {$settings['title']}
Difficulty: {$settings['difficulty']}
Number of questions: {$settings['question_count']}
Allowed question types: {$types}
Requested duration: {$settings['duration_minutes']} minutes
Requested total marks: {$settings['total_marks']}

Scope requirements:
- Generate questions only about the selected indicative content.
- Do not generate questions about other learning units or other indicative contents.
- Use the provided extracted course-note pages related to the selected indicative content.
- Every question and answer must be supported by the uploaded course materials.
- Do not use outside knowledge.
- If the uploaded materials do not sufficiently cover the selected indicative content, do not invent questions.

Assessment requirements:
- Create exactly {$settings['question_count']} questions.
- The sum of question marks must equal {$settings['total_marks']}.
- Do not invent information outside the supplied documents.
- Use clear language suitable for students.
- For multiple-choice questions, provide exactly four options.
- For true-or-false questions, provide True and False as options.
- For short-answer and essay questions, return an empty options array.
- Include the correct answer and a short explanation for every question.
- Return only the required structured assessment.
PROMPT;
    }

    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                ],
                'instructions' => [
                    'type' => 'string',
                ],
                'duration_minutes' => [
                    'type' => 'integer',
                ],
                'total_marks' => [
                    'type' => 'integer',
                ],
                'questions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => [
                                'type' => 'string',
                                'enum' => [
                                    'multiple_choice',
                                    'true_false',
                                    'short_answer',
                                    'essay',
                                ],
                            ],
                            'question' => [
                                'type' => 'string',
                            ],
                            'options' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string',
                                ],
                            ],
                            'correct_answer' => [
                                'type' => 'string',
                            ],
                            'explanation' => [
                                'type' => 'string',
                            ],
                            'marks' => [
                                'type' => 'integer',
                            ],
                        ],
                        'required' => [
                            'type',
                            'question',
                            'options',
                            'correct_answer',
                            'explanation',
                            'marks',
                        ],
                    ],
                ],
            ],
            'required' => [
                'title',
                'instructions',
                'duration_minutes',
                'total_marks',
                'questions',
            ],
        ];
    }
}

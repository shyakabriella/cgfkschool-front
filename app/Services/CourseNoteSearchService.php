<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseNoteChunk;
use Illuminate\Support\Collection;
use RuntimeException;

class CourseNoteSearchService
{
    /**
     * Find relevant pages from the indexed course notes.
     *
     * @return array{
     *     pages: array<int>,
     *     content: string,
     *     total_characters: int
     * }
     */
    public function search(
        Course $course,
        string $learningUnit,
        string $indicativeContent,
        int $maximumPages = 12
    ): array {
        if ($course->notes_index_status !== 'ready') {
            throw new RuntimeException(
                'The course notes have not been indexed yet.'
            );
        }

        $chunks = CourseNoteChunk::query()
            ->where('course_id', $course->id)
            ->orderBy('page_number')
            ->get([
                'page_number',
                'content',
            ]);

        if ($chunks->isEmpty()) {
            throw new RuntimeException(
                'No indexed course-note pages were found.'
            );
        }

        $contentKeywords = $this->keywords(
            $indicativeContent
        );

        $unitKeywords = $this->keywords(
            $learningUnit
        );

        $scoredPages = $chunks
            ->map(function (
                CourseNoteChunk $chunk
            ) use (
                $learningUnit,
                $indicativeContent,
                $contentKeywords,
                $unitKeywords
            ) {
                return [
                    'page_number' => $chunk->page_number,
                    'score' => $this->scorePage(
                        $chunk->content,
                        $learningUnit,
                        $indicativeContent,
                        $contentKeywords,
                        $unitKeywords
                    ),
                    'content' => $chunk->content,
                ];
            })
            ->filter(
                fn (array $page) => $page['score'] > 0
            )
            ->sortByDesc('score')
            ->values();

        if ($scoredPages->isEmpty()) {
            throw new RuntimeException(
                'The selected indicative content was not found '
                . 'inside the uploaded course notes.'
            );
        }

        /*
         * Use the strongest matching page as the starting page.
         * Include one page before it and the pages that follow it.
         *
         * This works well because a topic normally starts with its
         * heading and continues on the following pages.
         */
        $bestPage = (int) $scoredPages
            ->first()['page_number'];

        $startPage = max(1, $bestPage - 1);

        $pageNumbers = range(
            $startPage,
            $startPage + $maximumPages - 1
        );

        $selectedChunks = $chunks
            ->whereIn('page_number', $pageNumbers)
            ->sortBy('page_number')
            ->values();

        /*
         * Stop the prompt from becoming too large.
         * Around 55,000 characters is normally enough for one
         * indicative content and is much smaller than 346 pages.
         */
        $selectedChunks = $this->limitCharacters(
            $selectedChunks,
            55000
        );

        $pages = $selectedChunks
            ->pluck('page_number')
            ->map(fn ($page) => (int) $page)
            ->values()
            ->all();

        $combinedContent = $selectedChunks
            ->map(function (CourseNoteChunk $chunk) {
                return "=== COURSE NOTES PAGE "
                    . $chunk->page_number
                    . " ===\n"
                    . $chunk->content;
            })
            ->implode("\n\n");

        return [
            'pages' => $pages,
            'content' => $combinedContent,
            'total_characters' => mb_strlen(
                $combinedContent
            ),
        ];
    }

    /**
     * Calculate how relevant a page is to the selected content.
     *
     * @param array<int, string> $contentKeywords
     * @param array<int, string> $unitKeywords
     */
    private function scorePage(
        string $pageContent,
        string $learningUnit,
        string $indicativeContent,
        array $contentKeywords,
        array $unitKeywords
    ): int {
        $page = $this->normalize($pageContent);

        if (mb_strlen($page) < 150) {
            return 0;
        }

        $score = 0;

        $contentPhrase = $this->normalize(
            $indicativeContent
        );

        $unitPhrase = $this->normalize(
            $learningUnit
        );

        /*
         * An exact indicative-content heading is the strongest
         * possible match.
         */
        if (
            $contentPhrase !== ''
            && str_contains($page, $contentPhrase)
        ) {
            $score += 150;
        }

        if (
            $unitPhrase !== ''
            && str_contains($page, $unitPhrase)
        ) {
            $score += 30;
        }

        foreach ($contentKeywords as $keyword) {
            $occurrences = substr_count(
                $page,
                $keyword
            );

            $score += min($occurrences, 10) * 8;
        }

        foreach ($unitKeywords as $keyword) {
            $occurrences = substr_count(
                $page,
                $keyword
            );

            $score += min($occurrences, 5) * 2;
        }

        /*
         * Detailed pages normally contain more useful text than
         * a short table-of-contents entry.
         */
        if (mb_strlen($pageContent) >= 1000) {
            $score += 10;
        }

        if (mb_strlen($pageContent) >= 2000) {
            $score += 10;
        }

        return $score;
    }

    /**
     * Convert a title into useful search keywords.
     *
     * @return array<int, string>
     */
    private function keywords(string $value): array
    {
        $value = $this->normalize($value);

        $words = preg_split(
            '/\s+/',
            $value
        ) ?: [];

        $stopWords = [
            'a',
            'an',
            'and',
            'are',
            'as',
            'at',
            'be',
            'by',
            'for',
            'from',
            'in',
            'into',
            'is',
            'it',
            'of',
            'on',
            'or',
            'the',
            'their',
            'this',
            'to',
            'with',
            'using',
            'about',
            'introduction',
            'overview',
        ];

        return collect($words)
            ->map(
                fn (string $word) => trim($word)
            )
            ->filter(
                fn (string $word) =>
                    mb_strlen($word) >= 3
                    && ! in_array(
                        $word,
                        $stopWords,
                        true
                    )
            )
            ->unique()
            ->values()
            ->all();
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);

        $value = preg_replace(
            '/[^\pL\pN]+/u',
            ' ',
            $value
        ) ?? $value;

        $value = preg_replace(
            '/\s+/',
            ' ',
            $value
        ) ?? $value;

        return trim($value);
    }

    /**
     * @param Collection<int, CourseNoteChunk> $chunks
     * @return Collection<int, CourseNoteChunk>
     */
    private function limitCharacters(
        Collection $chunks,
        int $maximumCharacters
    ): Collection {
        $selected = collect();
        $currentCharacters = 0;

        foreach ($chunks as $chunk) {
            $characters = mb_strlen(
                $chunk->content
            );

            if (
                $selected->isNotEmpty()
                && $currentCharacters + $characters
                    > $maximumCharacters
            ) {
                break;
            }

            $selected->push($chunk);
            $currentCharacters += $characters;
        }

        return $selected;
    }
}

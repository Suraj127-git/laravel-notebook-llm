<?php

namespace App\Services;

class ChunkingService
{
    // ~500 tokens at ~4 chars/token
    private int $chunkSize = 2000;

    // ~50 token overlap for context continuity
    private int $overlap = 200;

    // Minimum chunk size to keep (filter noise)
    private int $minChunkLength = 50;

    /**
     * Split text into overlapping chunks suitable for embedding.
     *
     * @return string[]
     */
    public function chunk(string $text): array
    {
        $text = $this->normalizeWhitespace($text);

        if (strlen($text) <= $this->chunkSize) {
            $trimmed = trim($text);

            return strlen($trimmed) >= $this->minChunkLength ? [$trimmed] : [];
        }

        $chunks = [];
        $start = 0;
        $length = strlen($text);

        while ($start < $length) {
            $end = $start + $this->chunkSize;

            if ($end >= $length) {
                $chunk = trim(substr($text, $start));
                if (strlen($chunk) >= $this->minChunkLength) {
                    $chunks[] = $chunk;
                }
                break;
            }

            $breakPoint = $this->findBreakPoint($text, $start, $end);
            $chunk = trim(substr($text, $start, $breakPoint - $start));

            if (strlen($chunk) >= $this->minChunkLength) {
                $chunks[] = $chunk;
            }

            // Advance with overlap so consecutive chunks share context
            $start = max($start + 1, $breakPoint - $this->overlap);
        }

        return $chunks;
    }

    /**
     * Estimate token count using the ~4 chars/token heuristic.
     */
    public function estimateTokens(string $text): int
    {
        return (int) ceil(strlen($text) / 4);
    }

    /**
     * Find the best position to break text within a chunk boundary.
     * Prefers paragraph > newline > sentence > word boundaries.
     */
    private function findBreakPoint(string $text, int $start, int $end): int
    {
        $segment = substr($text, $start, $end - $start);
        $half = (int) ($this->chunkSize / 2);

        // Paragraph break
        $pos = strrpos($segment, "\n\n");
        if ($pos !== false && $pos > $half) {
            return $start + $pos + 2;
        }

        // Single newline
        $pos = strrpos($segment, "\n");
        if ($pos !== false && $pos > $half) {
            return $start + $pos + 1;
        }

        // Sentence end (period followed by space)
        $pos = strrpos($segment, '. ');
        if ($pos !== false && $pos > $half) {
            return $start + $pos + 2;
        }

        // Question or exclamation mark
        foreach (['? ', '! '] as $marker) {
            $pos = strrpos($segment, $marker);
            if ($pos !== false && $pos > $half) {
                return $start + $pos + 2;
            }
        }

        // Word boundary
        $pos = strrpos($segment, ' ');
        if ($pos !== false) {
            return $start + $pos + 1;
        }

        return $end;
    }

    private function normalizeWhitespace(string $text): string
    {
        // Collapse 3+ consecutive newlines to 2
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        // Collapse multiple spaces (but not newlines)
        $text = preg_replace('/ {2,}/', ' ', $text);

        return trim($text);
    }
}

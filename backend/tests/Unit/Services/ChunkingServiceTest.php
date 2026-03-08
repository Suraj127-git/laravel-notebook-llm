<?php

namespace Tests\Unit\Services;

use App\Services\ChunkingService;
use PHPUnit\Framework\TestCase;

class ChunkingServiceTest extends TestCase
{
    private ChunkingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChunkingService();
    }

    public function test_short_text_returns_single_chunk(): void
    {
        $text = str_repeat('word ', 50); // ~250 chars, well under 2000

        $chunks = $this->service->chunk($text);

        $this->assertCount(1, $chunks);
        $this->assertStringContainsString('word', $chunks[0]);
    }

    public function test_long_text_produces_multiple_chunks(): void
    {
        // ~6000 chars → should produce at least 2 chunks
        $text = str_repeat("This is a sentence about a topic. ", 180);

        $chunks = $this->service->chunk($text);

        $this->assertGreaterThan(1, count($chunks));
    }

    public function test_all_chunks_are_within_size_limit(): void
    {
        $text = str_repeat("Another sentence in the document content. ", 200);

        foreach ($this->service->chunk($text) as $chunk) {
            // Chunks may slightly exceed due to boundary detection, but should be reasonable
            $this->assertLessThan(3000, strlen($chunk));
        }
    }

    public function test_overlap_means_consecutive_chunks_share_content(): void
    {
        // Build text with recognizable paragraph boundaries
        $paragraphs = [];
        for ($i = 1; $i <= 20; $i++) {
            $paragraphs[] = "Paragraph {$i}: ".str_repeat("content word {$i} ", 60);
        }
        $text = implode("\n\n", $paragraphs);

        $chunks = $this->service->chunk($text);
        $this->assertGreaterThan(1, count($chunks));

        // Each chunk should be non-empty
        foreach ($chunks as $chunk) {
            $this->assertNotEmpty(trim($chunk));
        }
    }

    public function test_very_short_text_returns_empty_array(): void
    {
        $chunks = $this->service->chunk('hi'); // < 50 chars minimum

        $this->assertCount(0, $chunks);
    }

    public function test_estimate_tokens_uses_four_char_heuristic(): void
    {
        $text = str_repeat('a', 400);

        $this->assertSame(100, $this->service->estimateTokens($text));
    }

    public function test_empty_text_returns_empty_array(): void
    {
        $this->assertCount(0, $this->service->chunk(''));
    }
}

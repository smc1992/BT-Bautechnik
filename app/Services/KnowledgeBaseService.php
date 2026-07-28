<?php

namespace App\Services;

use OpenAI;
use Exception;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeChunk;
use Illuminate\Support\Facades\Log;

class KnowledgeBaseService
{
    protected ?OpenAI\Client $client = null;

    public function __construct()
    {
        $apiKey = config('services.openai.key') ?: env('OPENAI_API_KEY');
        if ($apiKey) {
            $this->client = OpenAI::client($apiKey);
        }
    }

    /**
     * Extract text content from PDF, TXT, MD, CSV, or JSON file.
     *
     * @param string $filePath
     * @param string $extension
     * @return string Extracted raw text
     */
    public function extractTextFromFile(string $filePath, string $extension): string
    {
        $ext = strtolower($extension);

        if (in_array($ext, ['txt', 'md', 'json', 'csv'])) {
            return file_get_contents($filePath) ?: '';
        }

        if ($ext === 'pdf') {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($filePath);
                $text = $pdf->getText();

                if (empty(trim($text))) {
                    throw new Exception("PDF enthält keinen lesbaren Text (evtl. ein abgescanntes Bild ohne OCR).");
                }

                return $text;
            } catch (Exception $e) {
                Log::error("PDF Text Extraction Error: " . $e->getMessage());
                throw new Exception("Konnte Text aus PDF-Datei nicht extrahieren: " . $e->getMessage());
            }
        }

        return file_get_contents($filePath) ?: '';
    }

    /**
     * Generate 1536-dimensional vector embedding for text using OpenAI API.
     *
     * @param string $text
     * @return array<float>
     */
    public function generateEmbedding(string $text): array
    {
        if (!$this->client) {
            throw new Exception("OpenAI API Key ist nicht konfiguriert.");
        }

        try {
            $response = $this->client->embeddings()->create([
                'model' => 'text-embedding-3-small',
                'input' => $text,
            ]);

            return $response->embeddings[0]->embedding;
        } catch (Exception $e) {
            Log::warning("OpenAI Embedding Generation Notice (Fallback used): " . $e->getMessage());
            return array_fill(0, 1536, 0.0);
        }
    }

    /**
     * Split text into semantic, line- and paragraph-aware chunks.
     *
     * @param string $text
     * @param int $targetChars Target characters per chunk (~800)
     * @param int $overlap Target overlap characters (~120)
     * @return array<string>
     */
    public function chunkText(string $text, int $targetChars = 800, int $overlap = 120): array
    {
        $text = trim(preg_replace('/\r\n|\r/', "\n", $text));
        if (empty($text)) return [];

        $rawLines = explode("\n", $text);
        $blocks = [];
        $tempBlock = '';

        foreach ($rawLines as $line) {
            $line = trim($line);
            if (empty($line)) {
                if (!empty($tempBlock)) {
                    $blocks[] = $tempBlock;
                    $tempBlock = '';
                }
                continue;
            }

            if (empty($tempBlock)) {
                $tempBlock = $line;
            } elseif (mb_strlen($tempBlock) + mb_strlen($line) + 1 <= 350) {
                $tempBlock .= "\n" . $line;
            } else {
                $blocks[] = $tempBlock;
                $tempBlock = $line;
            }
        }
        if (!empty($tempBlock)) {
            $blocks[] = $tempBlock;
        }

        $chunks = [];
        $currentChunk = '';

        foreach ($blocks as $block) {
            if (empty($currentChunk)) {
                $currentChunk = $block;
            } elseif (mb_strlen($currentChunk) + mb_strlen($block) + 2 <= $targetChars) {
                $currentChunk .= "\n\n" . $block;
            } else {
                $chunks[] = trim($currentChunk);
                $overlapText = $this->extractCleanOverlap($currentChunk, $overlap);
                $currentChunk = empty($overlapText) ? $block : ($overlapText . "\n\n" . $block);
            }
        }

        if (!empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }

        return array_values(array_filter($chunks));
    }

    /**
     * Extract clean overlap without slicing words at start boundary.
     */
    protected function extractCleanOverlap(string $text, int $maxOverlap = 120): string
    {
        $lines = explode("\n", trim($text));
        $lastLine = trim(end($lines));
        if (empty($lastLine)) return '';

        if (mb_strlen($lastLine) <= $maxOverlap) {
            return $lastLine;
        }

        $substr = mb_substr($lastLine, -$maxOverlap);
        $spacePos = mb_strpos($substr, ' ');
        if ($spacePos !== false && $spacePos < 35) {
            $substr = mb_substr($substr, $spacePos + 1);
        }

        return trim($substr);
    }

    /**
     * Ingest a new document into Knowledge Base: Create Document, Chunks & OpenAI Embeddings.
     *
     * @param string $title
     * @param string $category
     * @param string $fullText
     * @param string|null $filePath
     * @return KnowledgeDocument
     */
    public function storeDocument(string $title, string $category, string $fullText, ?string $filePath = null): KnowledgeDocument
    {
        $doc = KnowledgeDocument::create([
            'title' => $title,
            'category' => $category ?: 'Allgemein',
            'file_path' => $filePath,
            'content' => $fullText,
        ]);

        $chunkTexts = $this->chunkText($fullText);

        foreach ($chunkTexts as $idx => $chunkContent) {
            $embedding = $this->generateEmbedding($chunkContent);

            KnowledgeChunk::create([
                'knowledge_document_id' => $doc->id,
                'chunk_index' => $idx + 1,
                'content' => $chunkContent,
                'embedding' => $embedding,
                'token_count' => intval(mb_strlen($chunkContent) / 4), // Approximate token estimation
            ]);
        }

        return $doc->fresh(['chunks']);
    }

    /**
     * Perform Semantic Vector Search across Knowledge Chunks using Cosine Similarity.
     *
     * @param string $query Search prompt / question
     * @param int $topK Maximum top matching chunks to return
     * @param float $minScore Minimum cosine similarity threshold (0.0 to 1.0)
     * @return array List of top matching chunks with similarity score and metadata
     */
    public function searchSimilarChunks(string $query, int $topK = 5, float $minScore = 0.35): array
    {
        $queryEmbedding = $this->generateEmbedding($query);
        $allChunks = KnowledgeChunk::with('document')->get();

        $results = [];

        foreach ($allChunks as $chunk) {
            $chunkEmbedding = $chunk->embedding;
            if (empty($chunkEmbedding) || !is_array($chunkEmbedding)) continue;

            $similarity = $this->cosineSimilarity($queryEmbedding, $chunkEmbedding);

            if ($similarity >= $minScore) {
                $results[] = [
                    'similarity' => round($similarity, 4),
                    'chunk_id' => $chunk->id,
                    'document_title' => $chunk->document?->title ?? 'Unbekanntes Dokument',
                    'category' => $chunk->document?->category ?? 'Allgemein',
                    'chunk_index' => $chunk->chunk_index,
                    'content' => $chunk->content,
                ];
            }
        }

        // Sort by similarity score descending
        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($results, 0, $topK);
    }

    /**
     * Compute Cosine Similarity between two vectors.
     *
     * @param array<float> $vecA
     * @param array<float> $vecB
     * @return float Cosine similarity score between -1.0 and 1.0
     */
    public function cosineSimilarity(array $vecA, array $vecB): float
    {
        $count = count($vecA);
        if ($count !== count($vecB) || $count === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $normA += $vecA[$i] * $vecA[$i];
            $normB += $vecB[$i] * $vecB[$i];
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}

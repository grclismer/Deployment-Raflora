<?php

namespace App\Services;

use App\Support\DecorAreaBreakdown;
use App\Support\EventDesignBrief;
use App\Support\ImageOrientation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeminiVisionService
{
    /**
     * Part of the analysis cache key, so results cached for an older prompt (for example one
     * box per row instead of one per cluster) are never served for the current one.
     */
    public const PROMPT_VERSION = '2026-10-13-boxes';

    /** Box confidence at or above which a box is drawn as a confirmed location. */
    public const BOX_CONFIDENT = 0.8;

    /** Box confidence at or above which a box is drawn as "possible"; below it, the box is hidden. */
    public const BOX_POSSIBLE = 0.5;

    public const MAX_BOXES_PER_ROW = 12;

    /**
     * Ordered free-tier fallback models.
     * @var string[]
     */
    /**
     * Ordered supported models: prefers current GA Flash-Lite model for high-throughput,
     * low-cost structured vision analysis, with Flash and legacy fallbacks.
     * @var string[]
     */
    protected array $fallbackModels = [
        'gemini-3.5-flash-lite',
        'gemini-3.5-flash',
        'gemini-2.5-flash',
    ];

    /**
     * Maximum consecutive quota/rate-limit failures before failing fast to protect API quota.
     */
    protected int $maxConsecutiveQuotaFailures = 2;

    protected string $apiUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        // Base URL is built per-model when calling the Google Gemini REST endpoint.
        $this->apiUrl = (string) (config('services.gemini.api_url') ?: 'https://generativelanguage.googleapis.com/v1beta/models');
        $this->apiKey = config('services.gemini.api_key');
    }

    public function getFallbackModels(): array
    {
        return $this->fallbackModels;
    }

    public function setFallbackModels(array $models): self
    {
        $this->fallbackModels = $models;
        return $this;
    }

    /**
     * Determine if an HTTP response or body indicates a retryable rate limit or service error.
     */
    public function isRetryableRateLimit(int $status, string $body): bool
    {
        return $status === 429
            || $status === 503
            || stripos($body, 'RESOURCE_EXHAUSTED') !== false
            || stripos($body, 'UNAVAILABLE') !== false
            || stripos($body, 'quota') !== false
            || stripos($body, 'rate limit') !== false;
    }

    /**
     * Compute and execute controlled exponential backoff with jitter for retryable 429/503 responses.
     */
    public function handleRetryBackoff(?int $retryAfterHeader, int $attempt): float
    {
        if ($retryAfterHeader !== null && $retryAfterHeader > 0) {
            $delay = min(3.0, (float) $retryAfterHeader);
        } else {
            $base = 0.5 * (2 ** min($attempt, 3));
            $jitter = mt_rand(0, 150) / 1000;
            $delay = min(2.5, $base + $jitter);
        }

        Log::info("GeminiVisionService: Controlled backoff for {$delay}s (attempt {$attempt}) on rate limit/service busy.");
        $this->sleepBackoff($delay);

        return $delay;
    }

    /**
     * Sleep helper for backoff, bypassed during automated unit tests.
     */
    protected function sleepBackoff(float $seconds): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        usleep((int) ($seconds * 1000000));
    }

    /**
     * Validate whether an uploaded image is floral or event-related.
     *
     * Uses Gemini Vision to inspect the image and determine if it contains
     * flowers, floral arrangements, event decorations, bouquets, or related content.
     * Rejects electronic devices, vapes, random objects, memes, screenshots, etc.
     *
     * @param string $filePath Local filesystem path to the image
     * @return array{is_valid: bool, rejection_reason: string|null, model_used: string|null}
     */
    public function validateImage(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [
                'is_valid' => false,
                'rejection_reason' => 'Image file not found.',
                'model_used' => null,
                'error_type' => 'analysis',
            ];
        }

        $validationPrompt = <<<'PROMPT'
You are a strict image content validator for a floral event services company called Raflora Enterprises.

Your ONLY task is to determine whether the uploaded image is relevant to floral arrangements, event decorations, or event planning.

The image must also be sufficiently usable for reliable material analysis. Reject images that are blurry, distorted, corrupted, extremely low-resolution, too dark, overexposed, obstructed, or have important arrangement details that are not visible.

ACCEPTED images (is_floral_or_event_related = true):
- Flower arrangements, bouquets, corsages, centerpieces
- Individual flowers or bunches of flowers (fresh, dried, or artificial)
- Event venue decorations (weddings, birthdays, corporate events, debuts, funerals)
- Floral-themed mood boards or collages
- Event setup references (arches, table settings, backdrops with floral elements)
- Plants, greenery, foliage used in event styling
- Handmade floral crafts (ribbon roses, fabric flowers, foam flowers)

REJECTED images (is_floral_or_event_related = false):
- Electronic devices (phones, laptops, vapes, e-cigarettes, gadgets)
- Random objects unrelated to events (food, cars, tools, clothing)
- Memes, screenshots, text-only images, selfies without floral context
- Explicit, offensive, or inappropriate content
- Animals (unless part of an event decoration context)
- Blank or corrupted images

Return ONLY valid JSON with this exact schema:
{
  "is_floral_or_event_related": true/false,
    "is_clear_usable": true/false,
  "rejection_reason": "string or null"
}

Set is_clear_usable to true only when the image is clear enough to reliably identify relevant flowers, materials, or event-decor details. If either validation condition fails, set rejection_reason to a short, polite, user-friendly explanation (1-2 sentences max) of why the image was rejected.
PROMPT;

        $preparedImage = $this->prepareImageForGemini($filePath);
        $mimeType = $preparedImage['mime_type'];
        $base64 = $preparedImage['data'];

        $lastException = null;
        $lastFailureType = 'analysis';
        $consecutiveQuotaFailures = 0;
        $attempt = 0;

        foreach ($this->fallbackModels as $model) {
            $attempt++;
            try {
                if (empty($this->apiKey)) {
                    throw new \Exception('GEMINI_API_KEY is not configured in environment.');
                }

                $endpoint = rtrim($this->apiUrl, '/') . "/{$model}:generateContent?key=" . urlencode($this->apiKey);

                $payload = [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $validationPrompt],
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data'      => $base64,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'responseMimeType' => 'application/json',
                    ],
                ];

                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->timeout(60)->connectTimeout(60)->post($endpoint, $payload);

                $status = $response->status();
                $body = $response->body();

                if ($this->isRetryableRateLimit($status, $body)) {
                    $lastFailureType = 'service';
                    $consecutiveQuotaFailures++;
                    Log::warning("Image validation: Quota/rate-limit reached for {$model} (status={$status}).");

                    if ($consecutiveQuotaFailures >= $this->maxConsecutiveQuotaFailures) {
                        Log::warning("Image validation: Quota exhausted across models; stopping early to protect API quota.");
                        break;
                    }

                    $retryAfter = $response->header('Retry-After');
                    $this->handleRetryBackoff($retryAfter ? (int) $retryAfter : null, $attempt);
                    continue;
                }

                if ($status === 404) {
                    Log::warning("Image validation: Model {$model} not found. Trying next model.");
                    continue;
                }

                if ($status >= 400) {
                    throw new \Exception("Gemini API error (model={$model}) status={$status}: {$body}");
                }

                $responseData = $response->json();
                $generatedText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

                if (is_string($generatedText)) {
                    $generatedText = trim($generatedText);
                }

                if (empty($generatedText)) {
                    Log::warning("Image validation: Empty response from model {$model}.");
                    continue;
                }

                $parsed = $this->extractFirstJson($generatedText);

                if ($parsed === null) {
                    Log::warning("Image validation: Could not parse JSON from model {$model}. Raw: {$generatedText}");
                    continue;
                }

                $isValid = $parsed['is_floral_or_event_related'] ?? false;
                $isClearUsable = $parsed['is_clear_usable'] ?? false;
                $rejectionReason = $parsed['rejection_reason'] ?? null;

                if ($isValid !== true || $isClearUsable !== true) {
                    return [
                        'is_valid' => false,
                        'rejection_reason' => $rejectionReason ?? 'The image is not clear enough for reliable analysis. Please upload a clearer image.',
                        'model_used' => $model,
                        'error_type' => 'image_quality',
                    ];
                }

                return [
                    'is_valid' => true,
                    'rejection_reason' => null,
                    'model_used' => $model,
                    'error_type' => null,
                ];
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastException = $e;
                $lastFailureType = 'connection';
                Log::warning("Image validation: Connection timeout for model {$model}.", ['message' => $e->getMessage()]);
                continue;
            } catch (\Exception $e) {
                $lastException = $e;
                $msg = $e->getMessage();
                if (stripos($msg, 'timeout') !== false || stripos($msg, 'timed out') !== false || stripos($msg, 'connect') !== false || stripos($msg, 'connection') !== false) {
                    $lastFailureType = 'connection';
                } elseif (stripos($msg, 'RESOURCE_EXHAUSTED') !== false || stripos($msg, 'UNAVAILABLE') !== false || stripos($msg, 'quota') !== false || stripos($msg, '429') !== false || stripos($msg, '503') !== false || stripos($msg, 'rate limit') !== false) {
                    $lastFailureType = 'service';
                    $consecutiveQuotaFailures++;
                    Log::warning("Image validation: Quota/limit for {$model}.");
                    if ($consecutiveQuotaFailures >= $this->maxConsecutiveQuotaFailures) {
                        Log::warning("Image validation: Quota exhausted across models; stopping early.");
                        break;
                    }
                    $this->handleRetryBackoff(null, $attempt);
                    continue;
                } else {
                    $lastFailureType = 'analysis';
                }
                Log::error("Image validation failed for model {$model}.", ['message' => $msg]);
                continue;
            }
        }

        Log::warning('Image validation: All Gemini models failed.', [
            'last_exception' => $lastException?->getMessage(),
            'file' => $filePath,
        ]);

        return [
            'is_valid' => false,
            'rejection_reason' => self::failureMessageForType($lastFailureType),
            'model_used' => null,
            'error_type' => $lastFailureType,
        ];
    }

    public static function classifyFailure(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        if ($exception instanceof \Illuminate\Http\Client\ConnectionException
            || stripos($message, 'timeout') !== false
            || stripos($message, 'timed out') !== false
            || stripos($message, 'connect') !== false
            || stripos($message, 'connection') !== false) {
            return 'connection';
        }

        if (stripos($message, 'RESOURCE_EXHAUSTED') !== false
            || stripos($message, 'UNAVAILABLE') !== false
            || stripos($message, 'quota') !== false
            || stripos($message, '429') !== false
            || stripos($message, '503') !== false
            || stripos($message, 'rate limit') !== false) {
            return 'service';
        }

        if (stripos($message, 'empty_or_invalid_analysis') !== false
            || stripos($message, 'empty') !== false
            || stripos($message, 'invalid json') !== false
            || stripos($message, 'missing generated text') !== false
            || stripos($message, 'no inspiration image available') !== false) {
            return 'analysis';
        }

        return 'analysis';
    }

    public static function failureMessageForType(string $type): string
    {
        return match ($type) {
            'image_quality' => 'The image could not be analyzed. Please choose a clearer floral or event photo.',
            'connection' => 'Image analysis could not be completed because of a connection problem. Please check your internet connection and try again.',
            'timeout' => 'Image analysis timed out. Please try again.',
            'service' => 'Image analysis could not be completed right now. Please try again.',
            'analysis' => 'Image analysis could not be completed. Please try again with a clearer image or a different photo.',
            default => 'Image analysis could not be completed. Please try again.',
        };
    }

    public function coerceAnalysisMaterials(mixed $materials): array
    {
        if (is_array($materials)) {
            return array_values(array_filter($materials, fn ($item) => is_array($item)));
        }

        if (is_object($materials)) {
            $decoded = json_decode(json_encode($materials), true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, fn ($item) => is_array($item)));
            }
        }

        if (is_string($materials)) {
            $decoded = json_decode($materials, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, fn ($item) => is_array($item)));
            }
        }

        return [];
    }

    public function normalizeAreaAnalysis(array $analysis): array
    {
        $materials = $this->coerceAnalysisMaterials($analysis['suggested_materials'] ?? []);
        if ($materials === []) {
            return [];
        }

        $groups = [];

        foreach ($materials as $index => $material) {
            if (!is_array($material)) {
                continue;
            }

            $areaKey = strtolower(trim((string) ($material['area'] ?? $material['location'] ?? 'overall')));
            $areaKey = $areaKey === '' ? 'overall' : $areaKey;
            $areaLabel = $this->formatAreaLabel($areaKey);

            $quantity = $material['quantity'] ?? $material['estimated_quantity'] ?? 0;
            $unitType = (string) ($material['unit_type'] ?? $material['unit'] ?? 'pcs');
            $unitCost = $material['unit_cost_php'] ?? $material['estimated_unit_cost_php'] ?? $material['estimated_unit_cost'] ?? 0;
            $detected = filter_var($material['is_detected'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $recommendation = filter_var($material['is_recommendation'] ?? false, FILTER_VALIDATE_BOOLEAN);
            // Boxes are re-validated here too: analyses saved before boxes were cleaned on arrival
            // (one box_2d, or a visual_location point) are drawn by the same rules.
            $boxes = ($detected && !$recommendation) ? $this->normalizeBoxes($material)['boxes'] : [];
            $visualLocation = !($detected && !$recommendation) ? null
                : ($boxes !== [] ? $this->boxToRegion($boxes[0]['box_2d']) : ($this->rawBoxEntries($material) === [] ? $this->normalizeVisualLocation($material['visual_location'] ?? null) : null));
            $quality = $this->deriveItemConfidenceMetadata($material, $detected, $recommendation, $visualLocation);
            $decorArea = DecorAreaBreakdown::normalizeTag($material['decor_area'] ?? null);
            $colorKey = $decorArea ?? $areaKey;

            $groups[$areaKey]['area_key'] = $areaKey;
            $groups[$areaKey]['area_label'] = $areaLabel;
            $normalizedItem = [
                'line_id' => is_string($material['line_id'] ?? null) && $material['line_id'] !== '' ? $material['line_id'] : 'm' . ($index + 1),
                'name' => trim((string) ($material['item_name'] ?? 'Unknown item')),
                'area_key' => $areaKey,
                'area_label' => $areaLabel,
                'category' => strtolower(trim((string) ($material['category'] ?? 'prop'))),
                'quantity' => is_numeric($quantity) ? (float) $quantity : 0.0,
                'unit_type' => trim($unitType) !== '' ? trim($unitType) : 'pcs',
                'unit_cost_php' => is_numeric($unitCost) ? (float) $unitCost : 0.0,
                'source_label' => $quality['source_label'],
                'source_type' => $quality['source_type'],
                'note' => trim((string) ($material['note'] ?? '')),
                'visual_location' => $visualLocation,
                // Each box as shares of the displayed photo (x/y top-left), for percent overlays.
                'boxes' => array_map(fn (array $box) => $this->boxToRegion($box['box_2d']) + ['possible' => $box['possible']], $boxes),
                'decor_area' => $decorArea,
                'color_key' => $colorKey,
                'color' => DecorAreaBreakdown::color($colorKey),
                'color_label' => match (true) {
                    $decorArea === DecorAreaBreakdown::SHARED => DecorAreaBreakdown::SHARED_LABEL,
                    $decorArea !== null => EventDesignBrief::DECOR_AREAS[$decorArea],
                    default => $areaLabel,
                },
                'image_id' => is_numeric($material['image_id'] ?? null) ? (int) $material['image_id'] : null,
                'confidence' => $quality['confidence'],
                'confidence_percent' => $quality['confidence_percent'],
                'detected_regions' => $quality['detected_regions'],
                'needs_review' => $quality['needs_review'],
                'review_message' => $quality['review_message'],
                'is_estimated' => $quality['is_estimated'],
                'display_tag' => $quality['source_label'],
            ];
            $groups[$areaKey]['items'][] = $normalizedItem;
        }

        foreach ($groups as &$group) {
            $group['summary'] = count($group['items']) . ' item' . (count($group['items']) === 1 ? '' : 's');
        }
        unset($group);

        return array_values($groups);
    }

    protected function deriveItemConfidenceMetadata(array $material, bool $detected, bool $recommendation, ?array $visualLocation): array
    {
        $confidence = $this->confidenceOrNull($material['confidence'] ?? $material['ai_confidence'] ?? null);

        $detectedRegions = is_numeric($material['detected_regions'] ?? null) ? (int) $material['detected_regions'] : (($detected && $visualLocation !== null) ? 1 : 0);
        $reviewMessage = trim((string) ($material['review_message'] ?? $material['review_reason'] ?? ''));
        $hasExplicitReviewFlag = filter_var($material['needs_review'] ?? $material['review_required'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $baseMagnitude = min(1, max(0, $detectedRegions / 8));
        $locationEvidence = $visualLocation !== null ? 0.12 : 0.0;
        $boxEvidence = 0.0;
        if ($visualLocation !== null && isset($visualLocation['width'], $visualLocation['height'])) {
            $boxArea = (float) $visualLocation['width'] * (float) $visualLocation['height'];
            $boxEvidence = min(0.08, sqrt(max(0, $boxArea)) * 0.12);
        }
        $reviewPenalty = $hasExplicitReviewFlag || $reviewMessage !== '' ? 0.16 : 0.0;

        if ($confidence === null) {
            if ($recommendation) {
                $confidence = 0.45 + ($baseMagnitude * 0.08);
            } elseif ($visualLocation !== null) {
                $confidence = 0.75 + $locationEvidence + $boxEvidence + ($baseMagnitude * 0.08);
            } elseif ($detected) {
                $confidence = 0.56 + ($baseMagnitude * 0.12);
            } else {
                $confidence = 0.35 + ($baseMagnitude * 0.08);
            }

            $confidence -= $reviewPenalty;
        }

        $confidence = min(0.99, max(0.15, $confidence));
        $sourceLabel = ($detected && !$recommendation) ? 'AI Detected' : 'Estimated';
        $sourceType = ($detected && !$recommendation) ? 'detected' : 'recommendation';
        $needsReview = $recommendation
            || $confidence < 0.8
            || ($detected && $visualLocation === null)
            || $hasExplicitReviewFlag
            || $reviewMessage !== '';

        if ($reviewMessage === '') {
            if ($recommendation) {
                $reviewMessage = 'This item is estimated and may not be visible in the uploaded image.';
            } elseif ($detected && $visualLocation === null) {
                $reviewMessage = 'Some flowers are overlapping or the item is not clearly visible.';
            } elseif ($confidence < 0.8) {
                $reviewMessage = 'AI confidence is low for this item and it may need manual review.';
            }
        }

        return [
            'confidence' => round($confidence, 2),
            'confidence_percent' => (int) round($confidence * 100),
            'detected_regions' => max(0, $detectedRegions),
            'source_label' => $sourceLabel,
            'source_type' => $sourceType,
            'needs_review' => $needsReview,
            'review_message' => $reviewMessage,
            'is_estimated' => $recommendation || !($detected && !$recommendation),
        ];
    }

    /**
     * Where an item is in the photo, as shares of the displayed image: the region {x, y, width,
     * height} of a box (x/y at its top-left corner), or the bare point {x, y} of an analysis
     * saved before boxes were requested.
     */
    protected function normalizeVisualLocation(mixed $location, mixed $box = null): ?array
    {
        $box = $this->normalizeBox($box);
        if ($box !== null) {
            return $this->boxToRegion($box);
        }

        if (!is_array($location) || !isset($location['x'], $location['y'])
            || !is_numeric($location['x']) || !is_numeric($location['y'])) {
            return null;
        }

        $x = (float) $location['x'];
        $y = (float) $location['y'];
        if (!is_finite($x) || !is_finite($y) || $x < 0 || $x > 1 || $y < 0 || $y > 1) {
            return null;
        }

        $normalized = ['x' => $x, 'y' => $y];
        foreach (['width', 'height'] as $dimension) {
            if (!array_key_exists($dimension, $location)) {
                continue;
            }

            if (!is_numeric($location[$dimension])) {
                return null;
            }

            $value = (float) $location[$dimension];
            if (!is_finite($value) || $value < 0) {
                return null;
            }

            $remaining = $dimension === 'width' ? 1 - $x : 1 - $y;
            $normalized[$dimension] = round(min($value, $remaining), 6);
        }

        return $normalized;
    }

    /**
     * A [ymin, xmin, ymax, xmax] box on Gemini's 0-1000 scale as shares of the image:
     * {x, y, width, height} with x/y at the top-left corner. Percent positions over the whole
     * displayed photo are these shares × 100.
     */
    public function boxToRegion(array $box): array
    {
        [$ymin, $xmin, $ymax, $xmax] = array_values($box);

        return [
            'x' => round($xmin / 1000, 6),
            'y' => round($ymin / 1000, 6),
            'width' => round(($xmax - $xmin) / 1000, 6),
            'height' => round(($ymax - $ymin) / 1000, 6),
        ];
    }

    /**
     * A usable [ymin, xmin, ymax, xmax] box on Gemini's 0-1000 scale, or null.
     *
     * Edges slightly past the image (up to 10% of a side, from rounding) are clamped to it. A box
     * entirely outside the image, on another scale (pixels or 0-1 shares), with its edges in the
     * wrong order, or with no real size after clamping is dropped, never repaired, so no
     * annotation is drawn from a guessed correction.
     */
    public function normalizeBox(mixed $box): ?array
    {
        if (!is_array($box) || count($box) !== 4 || !array_is_list($box)) {
            return null;
        }

        $values = [];
        foreach ($box as $value) {
            if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < -100 || (float) $value > 1100) {
                return null;
            }
            $values[] = (float) $value;
        }

        [$ymin, $xmin, $ymax, $xmax] = $values;
        if ($ymin >= $ymax || $xmin >= $xmax || $ymax <= 0 || $xmax <= 0 || $ymin >= 1000 || $xmin >= 1000) {
            return null;
        }

        $values = array_map(fn (float $value) => min(1000.0, max(0.0, $value)), $values);
        [$ymin, $xmin, $ymax, $xmax] = $values;
        if ($ymax - $ymin < 2 || $xmax - $xmin < 2) {
            return null;
        }

        return $values;
    }

    /**
     * The box entries a row carries, in any shape the AI or older analyses used: `boxes` as
     * [{box_2d, confidence}] or bare [ymin, xmin, ymax, xmax] lists, or a single `box_2d`.
     *
     * @return list<array{box: mixed, confidence: mixed}>
     */
    protected function rawBoxEntries(array $material): array
    {
        $entries = [];
        if (is_array($material['boxes'] ?? null)) {
            foreach ($material['boxes'] as $entry) {
                if (is_array($entry) && array_key_exists('box_2d', $entry)) {
                    $entries[] = ['box' => $entry['box_2d'], 'confidence' => $entry['confidence'] ?? null];
                } elseif (is_array($entry) && array_is_list($entry)) {
                    $entries[] = ['box' => $entry, 'confidence' => null];
                }
            }

            return $entries;
        }

        $single = $material['box_2d'] ?? null;
        if (is_array($single) && array_is_list($single) && is_array($single[0] ?? null)) {
            foreach ($single as $box) {
                $entries[] = ['box' => $box, 'confidence' => null];
            }
        } elseif ($single !== null) {
            $entries[] = ['box' => $single, 'confidence' => null];
        }

        return $entries;
    }

    /**
     * A row's boxes after validation: invalid ones dropped, near-duplicates removed, at most
     * MAX_BOXES_PER_ROW. A box's confidence is its own (or the row's), never above the row's.
     * From BOX_CONFIDENT it is a confirmed location, from BOX_POSSIBLE a "possible" one, and
     * below that it is hidden (counted, not kept) for staff review.
     *
     * @return array{boxes: list<array{box_2d: array, confidence: ?float, possible: bool}>, hidden: int}
     */
    protected function normalizeBoxes(array $material): array
    {
        $rowConfidence = $this->confidenceOrNull($material['confidence'] ?? $material['ai_confidence'] ?? null);
        $kept = [];
        $hidden = 0;

        foreach ($this->rawBoxEntries($material) as $entry) {
            $box = $this->normalizeBox($entry['box']);
            if ($box === null) {
                continue;
            }

            $confidence = $this->confidenceOrNull($entry['confidence']);
            $confidence = $confidence === null ? $rowConfidence : ($rowConfidence === null ? $confidence : min($confidence, $rowConfidence));
            if ($confidence !== null && $confidence < self::BOX_POSSIBLE) {
                $hidden++;
                continue;
            }

            foreach ($kept as $other) {
                if ($this->boxOverlap($box, $other['box_2d']) >= 0.85) {
                    continue 2;
                }
            }

            if (count($kept) < self::MAX_BOXES_PER_ROW) {
                $kept[] = [
                    'box_2d' => $box,
                    'confidence' => $confidence !== null ? round($confidence, 2) : null,
                    'possible' => $confidence !== null && $confidence < self::BOX_CONFIDENT,
                ];
            }
        }

        return ['boxes' => $kept, 'hidden' => $hidden];
    }

    /** Intersection over union of two 0-1000 boxes. */
    protected function boxOverlap(array $a, array $b): float
    {
        $height = min($a[2], $b[2]) - max($a[0], $b[0]);
        $width = min($a[3], $b[3]) - max($a[1], $b[1]);
        if ($height <= 0 || $width <= 0) {
            return 0.0;
        }

        $intersection = $height * $width;
        $union = (($a[2] - $a[0]) * ($a[3] - $a[1])) + (($b[2] - $b[0]) * ($b[3] - $b[1])) - $intersection;

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /** An AI confidence as 0-1 (percent values are scaled), or null when missing or invalid. */
    protected function confidenceOrNull(mixed $value): ?float
    {
        if (!is_numeric($value) || !is_finite((float) $value)) {
            return null;
        }

        $confidence = (float) $value;
        if ($confidence > 1 && $confidence <= 100) {
            $confidence /= 100;
        }

        return $confidence >= 0 && $confidence <= 1 ? $confidence : null;
    }

    protected function formatAreaLabel(string $areaKey): string
    {
        $map = [
            'ceiling' => 'Ceiling',
            'stage' => 'Stage',
            'altar' => 'Altar',
            'entrance' => 'Entrance',
            'tables' => 'Table Styling',
            'table' => 'Table Styling',
            'backdrop' => 'Backdrop',
            'wall' => 'Wall Decor',
            'aisle' => 'Aisle',
            'overall' => 'Overall Design',
            'general' => 'Overall Design',
        ];

        $normalized = strtolower(trim($areaKey));
        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        return ucwords(str_replace(['-', '_'], ' ', $normalized));
    }

    /**
     * Validate the minimum complete analysis contract before it can reach a booking.
     * Invalid rows reject the whole response so partial results cannot be persisted.
     *
     * Every row gets a `line_id` ("m1", "m2", ...) that its boxes and every list showing it refer
     * to. With the client's design brief, every row is tagged with a picked area or "shared".
     */
    public function normalizeAiAnalysis(array $analysis, ?array $designBrief = null): array
    {
        $materials = $analysis['suggested_materials'] ?? [];
        if (!is_array($materials)) {
            $materials = [];
        }

        $normalized = [];
        foreach ($materials as $material) {
            if (!is_array($material)) {
                continue;
            }

            $itemName = trim((string) ($material['item_name'] ?? ''));
            if ($itemName === '') {
                continue;
            }

            $category = strtolower(trim((string) ($material['category'] ?? '')));
            if (!in_array($category, ['flower', 'foliage', 'prop', 'supply'], true)) {
                $category = $this->inferCategoryFromItemName($itemName);
            }

            $unitType = strtolower(trim((string) ($material['unit_type'] ?? $material['unit'] ?? '')));
            if (!in_array($unitType, ['stem', 'bunch', 'piece', 'set', 'pcs'], true)) {
                $unitType = $this->defaultUnitForCategory($category);
            }

            $detected = filter_var($material['is_detected'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $recommended = filter_var($material['is_recommendation'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $isDetection = $detected && !$recommended;

            // Quantities and prices are AI estimates only. A missing or invalid value is recorded as
            // unavailable (null) for staff to supply; it is never replaced with a guessed value.
            $quantity = $this->positiveNumberOrNull($material['quantity'] ?? $material['estimated_quantity'] ?? null);
            $unitCost = $this->positiveNumberOrNull($material['unit_cost_php'] ?? $material['estimated_unit_cost_php'] ?? $material['estimated_unit_cost'] ?? null);
            $unitCost = $unitCost !== null ? round($unitCost, 2) : null;

            // Per-area breakdown fields are only requested when the client picked areas to decorate.
            foreach (['decor_area' => 'normalizeTag', 'quantity_basis' => 'normalizeBasis'] as $field => $clean) {
                if (array_key_exists($field, $material)) {
                    $material[$field] = DecorAreaBreakdown::$clean($material[$field]);
                }
            }

            // Only visible detections keep boxes; a recommendation's boxes are orphans and dropped.
            $boxes = $isDetection ? $this->normalizeBoxes($material) : ['boxes' => [], 'hidden' => 0];
            $firstBox = $boxes['boxes'][0]['box_2d'] ?? null;

            // Problems the application finds itself; the AI's own review message is kept in front of them.
            $aiReviewMessage = trim((string) ($material['review_message'] ?? $material['review_reason'] ?? ''));
            $systemNotes = array_values(array_filter([
                DecorAreaBreakdown::basisProblem($material['quantity_basis'] ?? null, $quantity),
                array_filter(array_column($boxes['boxes'], 'possible')) !== [] ? 'Possible match in the photo: staff should confirm the marked location.' : null,
                $boxes['hidden'] > 0 ? 'The AI was unsure where this item is in the photo, so that location is hidden for staff review.' : null,
            ]));

            $normalized[] = array_merge($material, [
                'line_id' => 'm' . (count($normalized) + 1),
                'item_name' => $itemName,
                'category' => $category,
                'unit_type' => $unitType,
                'quantity' => $quantity,
                'estimated_quantity' => $quantity,
                'quantity_status' => $quantity !== null ? 'ai_estimate' : 'unavailable',
                'unit_cost_php' => $unitCost,
                'estimated_unit_cost_php' => $unitCost,
                'price_status' => $unitCost !== null ? 'ai_estimate' : 'unavailable',
                'is_detected' => $isDetection,
                'is_recommendation' => $recommended,
                'is_custom_item' => (bool) ($material['is_custom_item'] ?? false),
                'boxes' => $boxes['boxes'],
                'hidden_box_count' => $boxes['hidden'],
                'box_2d' => $firstBox,
                'visual_location' => !$isDetection ? null
                    : ($firstBox !== null ? $this->boxToRegion($firstBox) : ($this->rawBoxEntries($material) === [] ? $this->normalizeVisualLocation($material['visual_location'] ?? null) : null)),
                'needs_review' => filter_var($material['needs_review'] ?? $material['review_required'] ?? false, FILTER_VALIDATE_BOOLEAN) || $systemNotes !== [],
                // A note already in the message (an analysis normalized twice) is not repeated.
                'review_message' => implode(' ', array_filter([$aiReviewMessage, ...array_filter($systemNotes, fn (string $note) => !str_contains($aiReviewMessage, $note))])),
                'area' => $this->normalizeAreaKey($material['area'] ?? $material['location'] ?? 'overall'),
                'note' => trim((string) ($material['note'] ?? '')),
                'suggested_alternative' => !empty($material['suggested_alternative']) ? trim((string) $material['suggested_alternative']) : null,
                'alternative_reason' => !empty($material['alternative_reason']) ? trim((string) $material['alternative_reason']) : null,
                'seasonality_status' => !empty($material['seasonality_status']) ? trim((string) $material['seasonality_status']) : null,
                'seasonal_notes' => !empty($material['seasonal_notes']) ? trim((string) $material['seasonal_notes']) : null,
                'seasonal_sources' => !empty($material['seasonal_sources']) && is_array($material['seasonal_sources']) ? $material['seasonal_sources'] : null,
            ]);
        }

        $normalized = DecorAreaBreakdown::assignAreas($normalized, $designBrief);
        $analysis['suggested_materials'] = $normalized;
        $analysis['pricing_summary'] = $this->buildPricingSummary($normalized);

        return $analysis;
    }

    /**
     * Totals are calculated by the application from the AI's per-item estimates. Rows without an
     * AI quantity or price are excluded and listed, so a partial estimate is never shown as complete.
     */
    public function buildPricingSummary(array $materials): array
    {
        $rawMaterialsTotal = 0.0;
        $itemizedBreakdown = [];
        $unpricedItems = [];

        foreach ($materials as $material) {
            if (!is_array($material)) {
                continue;
            }

            $quantity = $this->positiveNumberOrNull($material['quantity'] ?? $material['estimated_quantity'] ?? null);
            $unitCost = $this->positiveNumberOrNull($material['unit_cost_php'] ?? $material['estimated_unit_cost_php'] ?? $material['estimated_unit_cost'] ?? null);

            if ($quantity === null || $unitCost === null) {
                $unpricedItems[] = trim((string) ($material['item_name'] ?? 'Unknown item'));
                continue;
            }

            $subtotal = round($quantity * $unitCost, 2);
            $rawMaterialsTotal += $subtotal;
            $itemizedBreakdown[] = [
                'item_name' => trim((string) ($material['item_name'] ?? 'Unknown item')),
                'unit_type' => trim((string) ($material['unit_type'] ?? 'pcs')),
                'quantity' => $quantity,
                'estimated_quantity' => $quantity,
                'unit_cost_php' => round($unitCost, 2),
                'estimated_unit_cost_php' => round($unitCost, 2),
                'estimated_subtotal_php' => $subtotal,
            ];
        }

        $rawMaterialsTotal = round($rawMaterialsTotal, 2);
        $estimatedGrandTotal = round($rawMaterialsTotal * 3.0, 2);

        return [
            'markup_multiplier' => 3.0,
            'raw_materials_total_php' => $rawMaterialsTotal,
            'estimated_grand_total_php' => $estimatedGrandTotal,
            'itemized_breakdown' => $itemizedBreakdown,
            'price_basis' => 'ai_estimate_unverified',
            'is_complete' => $unpricedItems === [],
            'unpriced_item_count' => count($unpricedItems),
            'unpriced_items' => $unpricedItems,
        ];
    }

    protected function positiveNumberOrNull(mixed $value): ?float
    {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value <= 0) {
            return null;
        }

        return (float) $value;
    }

    protected function inferCategoryFromItemName(string $itemName): string
    {
        $lower = strtolower($itemName);

        if (preg_match('/rose|peony|lily|orchid|tulip|hydrangea|ranunculus|calla|daisy|sunflower|carnation|gerbera|baby\s*breath|cymbidium|alstroemeria|snapdragon|aster|lisianthus/i', $lower)) {
            return 'flower';
        }

        if (preg_match('/leaf|fern|foliage|greens|eucalyptus|salal|ruscus|palm|ivy|olive|monstera|aglaonema|bamboo|garland/i', $lower)) {
            return 'foliage';
        }

        if (preg_match('/vase|container|basket|base|stand|arch|frame|backdrop|linen|runner|candles|candlestick|furniture|chair|table|glass|candleholder|pedestal|signage/i', $lower)) {
            return 'prop';
        }

        return 'supply';
    }

    protected function defaultUnitForCategory(string $category): string
    {
        return match ($category) {
            'flower' => 'stem',
            'foliage' => 'bunch',
            'prop' => 'piece',
            'supply' => 'set',
            default => 'piece',
        };
    }

    protected function normalizeAreaKey(string $area): string
    {
        $areaKey = strtolower(trim($area));
        $areaKey = $areaKey === '' ? 'overall' : $areaKey;

        if (in_array($areaKey, ['tables', 'table'], true)) {
            return 'tables';
        }

        if (in_array($areaKey, ['wall', 'backdrop', 'stage', 'altar', 'entrance', 'aisle', 'ceiling', 'overall'], true)) {
            return $areaKey;
        }

        return 'overall';
    }

    public function validateAnalysisPayload(array $analysis): void
    {
        $materials = $analysis['suggested_materials'] ?? null;
        $allowedCategories = ['flower', 'foliage', 'prop', 'supply'];
        $allowedUnits = ['stem', 'bunch', 'piece', 'set', 'pcs'];

        if (!is_array($materials) || $materials === []) {
            throw new \RuntimeException('empty_or_invalid_analysis');
        }

        foreach ($materials as $material) {
            if (!is_array($material)) {
                throw new \RuntimeException('incomplete_analysis_item');
            }

            $name = trim((string) ($material['item_name'] ?? ''));
            $category = strtolower(trim((string) ($material['category'] ?? '')));
            $unit = strtolower(trim((string) ($material['unit_type'] ?? '')));
            $quantity = $material['quantity'] ?? $material['estimated_quantity'] ?? null;
            $unitCost = $material['unit_cost_php'] ?? $material['estimated_unit_cost_php'] ?? $material['estimated_unit_cost'] ?? null;

            // A quantity or price may be absent (recorded as unavailable for staff to supply),
            // but a value that is present must be a usable positive number.
            if ($name === '' || !in_array($category, $allowedCategories, true) || !in_array($unit, $allowedUnits, true)
                || ($quantity !== null && $this->positiveNumberOrNull($quantity) === null)
                || ($unitCost !== null && $this->positiveNumberOrNull($unitCost) === null)) {
                throw new \RuntimeException('incomplete_analysis_item');
            }
        }
    }

    /**
     * Analyze an image file path and return parsed JSON and model used.
     * @param string $filePath Local filesystem path to image
     * @return array ['model_used'=>string, 'analysis'=>array, 'raw_response'=>string]
     * @throws \Exception
     */
    public static function buildAnalysisDiagnostic(string $source, array $analysisResult, array $requestContext, ?string $imageHash, ?string $imagePath, bool $completedTemplateReused, array $imageMeta = [], array $generationConfig = [], ?string $requestId = null): array
    {
        $analysis = $analysisResult['analysis'] ?? $analysisResult;
        $materials = [];

        foreach (($analysis['suggested_materials'] ?? []) as $rawMaterial) {
            if (!is_array($rawMaterial)) {
                continue;
            }

            $category = trim((string) ($rawMaterial['category'] ?? ''));
            $sourceType = 'recommendation';
            if (!empty($rawMaterial['is_detected']) && empty($rawMaterial['is_recommendation'])) {
                $sourceType = 'detected';
            }

            $materials[] = [
                'name' => trim((string) ($rawMaterial['item_name'] ?? 'Unknown item')),
                'category' => $category,
                'quantity' => $rawMaterial['quantity'] ?? $rawMaterial['estimated_quantity'] ?? null,
                'unit' => $rawMaterial['unit_type'] ?? $rawMaterial['unit'] ?? null,
                'area' => $rawMaterial['area'] ?? null,
                'source' => $sourceType,
                'note' => trim((string) ($rawMaterial['note'] ?? '')),
                'visual_location' => is_array($rawMaterial['visual_location'] ?? null) ? $rawMaterial['visual_location'] : null,
                'box_2d' => is_array($rawMaterial['box_2d'] ?? null) ? $rawMaterial['box_2d'] : null,
                'boxes' => is_array($rawMaterial['boxes'] ?? null) ? $rawMaterial['boxes'] : null,
                'decor_area' => $rawMaterial['decor_area'] ?? null,
                'quantity_basis' => $rawMaterial['quantity_basis'] ?? null,
                'suggested_alternative' => $rawMaterial['suggested_alternative'] ?? null,
                'alternative_reason' => $rawMaterial['alternative_reason'] ?? null,
                'seasonality_status' => $rawMaterial['seasonality_status'] ?? null,
                'seasonal_notes' => $rawMaterial['seasonal_notes'] ?? null,
            ];
        }

        $imageInfo = [
            'hash' => $imageHash,
            'mime_type' => $imageMeta['mime_type'] ?? (is_string($imagePath) && file_exists($imagePath) ? mime_content_type($imagePath) ?: null : null),
            'width' => $imageMeta['width'] ?? null,
            'height' => $imageMeta['height'] ?? null,
            'prepared_mime_type' => $imageMeta['prepared_mime_type'] ?? null,
            'prepared_width' => $imageMeta['prepared_width'] ?? null,
            'prepared_height' => $imageMeta['prepared_height'] ?? null,
        ];

        $normalizedRequestContext = [];
        foreach (['event_type', 'event_date', 'event_time', 'end_time', 'venue', 'venue_type', 'venue_size', 'venue_setting', 'guest_count', 'table_count', 'special_requests', 'design_brief'] as $key) {
            if (array_key_exists($key, $requestContext) && $requestContext[$key] !== null && $requestContext[$key] !== '' && $requestContext[$key] !== []) {
                $normalizedRequestContext[$key] = $requestContext[$key];
            }
        }

        $generationConfig = is_array($generationConfig) ? $generationConfig : [];
        $diagnostic = [
            'diagnostic_id' => $requestId ?? (string) Str::uuid(),
            'source' => $source,
            'timestamp' => now()->toIso8601String(),
            'analysis_request_id' => $requestId ?? (string) Str::uuid(),
            'fresh_gemini_analysis' => !$completedTemplateReused,
            'completed_template_reused' => (bool) $completedTemplateReused,
            'image' => $imageInfo,
            'request_context' => $normalizedRequestContext,
            'gemini_config' => [
                'model' => $analysisResult['model_used'] ?? $analysisResult['model'] ?? null,
                'temperature' => $generationConfig['temperature'] ?? null,
                'response_mime_type' => $generationConfig['responseMimeType'] ?? $generationConfig['response_mime_type'] ?? 'application/json',
                'generation_config' => $generationConfig,
            ],
            'analysis_result' => [
                'suggested_materials' => $materials,
            ],
            'raw_response' => $analysisResult['raw_response'] ?? null,
        ];

        return $diagnostic;
    }

    /**
     * Cache key part for an analysis. Every input that is sent to Gemini is included, so a result
     * produced for one event date, time or venue is never reused for a different one.
     */
    public static function analysisContextHash(array $context): string
    {
        $keys = ['event_type', 'event_date', 'event_time', 'end_time', 'venue', 'venue_setting', 'scale', 'special_requests', 'design_brief'];

        return md5(json_encode(array_map(
            fn (string $key) => is_array($context[$key] ?? null) ? $context[$key] : (string) ($context[$key] ?? ''),
            array_combine($keys, $keys)
        ) + ['prompt_version' => self::PROMPT_VERSION]));
    }

    /**
     * Evidence kept with every analysis: where the result came from, the model and generation
     * settings used, when Gemini produced it, and whether an event date was available.
     *
     * @param  string  $source  gemini | cache | completed_booking_template
     */
    public static function buildAnalysisMeta(array $analysisResult, string $source, array $context): array
    {
        $eventDate = !empty($context['event_date']) ? (string) $context['event_date'] : null;

        return [
            'source' => $source,
            'model' => $analysisResult['model_used'] ?? null,
            'generation_config' => $analysisResult['generation_config'] ?? null,
            'analyzed_at' => $analysisResult['analyzed_at'] ?? null,
            'event_date' => $eventDate,
            'event_date_provided' => $eventDate !== null,
            'context' => array_filter($context, fn ($value) => $value !== null && $value !== ''),
        ];
    }

    public function analyzeImageFromPath(
        string $filePath,
        ?string $specialRequests = null,
        ?string $eventType = null,
        ?string $eventTime = null,
        ?string $venue = null,
        ?string $scaleContext = null,
        ?string $eventDate = null,
        ?string $endTime = null,
        ?array $designBrief = null,
        ?string $venueSetting = null
    ): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        $systemPrompt = <<<'PROMPT'
You are a senior floral appraiser specializing in the wholesale and retail flower markets of Metro Manila, Philippines (specifically Dangwa Flower Market), and custom artisan/craft arrangements.
Inspect user-uploaded images of flower arrangements or perishable floral items—whether they are professional stock images from the internet or raw, phone-captured snapshots of homemade or artisan arrangements (which may feature fresh flowers, handmade ribbon folds, fabric, foam, or custom craft materials). Identify the components, estimate standard Philippine Peso (PHP / ₱) market prices, and return ONLY a structured JSON response.

DYNAMIC VISUAL DETECTION & ANTI-HALLUCINATION RULES (CRITICAL):
1. Visual Grounding Only: ONLY classify an item as detected when that specific item is visibly present and reasonably locatable in the uploaded image. Perform a full-scene visual sweep across all areas (top, middle, ground), and for portrait or landscape images explicitly scan every visible zone from edge to edge before finalizing the list. Do not stop after the first obvious centerpiece. Include all clearly visible flower varieties, foliage, containers, backdrops, linens, furniture, and staged props in the scene. Do not under-detect merely because the image is portrait, landscape, dense, or visually complex.
2. Full Coverage Mandatory: Treat the entire image as a design inventory. If a visible element is meaningful to the arrangement or venue styling, include it. Do not omit obvious flowers in corners, background clusters, or secondary structures simply because the main item is in focus.
3. No Color Inventing: Restrict variations strictly to the image's dominant, visible color palette. NEVER introduce unrepresented colors.
4. No Presumptuous Bundling: Do NOT guess unseen accessories or "standard package add-ons" (no photo booths, welcome signages, or aisle carpets unless they physically appear in the photo).
5. Inferred Mechanics Are Recommendations: Behind-the-scenes mechanical supplies may be listed only as recommendations when they are not directly visible. They must use `is_detected: false`, `is_recommendation: true`, and `boxes: []`.
6. Detection Granularity: Do not create multiple detected rows for the same physical visual element or near-duplicate descriptions. Separate rows are allowed only when the items are genuinely distinct and visually distinguishable.
7. Detection Versus Recommendation: Every row must be either a visible detection or a recommendation. A recommendation may support planning, but it is not a visual detection and must never receive boxes merely because it appears in the material list.

CATEGORY CLASSIFICATION (STRICT):
Assign EVERY detected item to its most fitting functional category. The category output must NEVER be empty, null, or set to "misc". You must use one of the following exact string values:
- "flower": Any fresh, dried, artificial, or focal blooms, accent flowers, and floral fillers.
- "foliage": Any greenery, leaves, plants, or filler foliage present.
- "prop": Any functional, structural, decorative, lighting, or staging elements (containers, bases, backdrops, overhead fixtures, furniture, floor treatments).
- "supply": Any behind-the-scenes mechanical or structural supplies required to construct and secure the visible setup (foam, wiring, ties, mechanics).

ITEMIZATION RULES:
1. Prevent Floral Aggregation: Do NOT combine clearly distinguishable flower species or color variations into a single generic item line. Separate them only when the visual difference is clear and useful for planning; do not fragment one visual cluster into many arbitrary rows.
2. Annotation Eligibility: A row may receive `boxes` only when the named item itself is visible and each box can be tied to that item in the image. If it is not visually identifiable, hidden, inferred, supporting, or uncertain, keep the row as a recommendation or detected-without-box only when justified, and use `boxes: []`.
3. Coverage Audit: Before returning JSON, review the image a second time by zones and confirm that every clearly distinguishable visible material, object, or functional prop useful for the design breakdown was considered. Do not add a fixed number of rows and do not invent uncertain detections; use empty `boxes` or recommendations when a visible distinction cannot be supported.
4. Object-Specific Grounding Audit: For every box, identify the actual visible object first, then draw the box tightly around that object only. One box covers one flower cluster, arrangement or object: never one box per stem or bloom, and never one box around several separate clusters. When the same item appears in several separate places (for example the same roses in every centerpiece), give one box per place, up to 12; when there are more than 12, box the 12 clearest and put the total number of places in `detected_regions`. Do not box a neighboring flower, a mixed cluster, the background, a garment, or a general area for a flower row. If a box cannot separate the named item from nearby items, leave that box out instead of returning a broad or misleading box.
5. Non-Venue Composition Handling: If the upload is a floral garment, wearable arrangement, bouquet, headpiece, floral sculpture, product composition, or other non-venue floral design, analyze the visible composition itself. Do not force venue areas such as ceiling, stage, or tables; use `overall` when no approved area fits. Break out clearly distinguishable flower groups, foliage, structural visible materials, and decorative components, while keeping hidden mechanics as recommendations with empty `boxes`.

QUANTITY & SCALE GROUNDING:
1. Primary Anchors: The booked table count and guest count (provided in the Context block below) MUST be used as the absolute anchors for calculating the quantities of table linens, chairs, and centerpieces. Use the uploaded photo only for visual style, bloom density, and material selection, while multiplying those materials by the provided table/guest counts.
2. Provide realistic estimated quantities and prices in Philippine Pesos (PHP / ₱). Use realistic wholesale floor prices for flowers in the Dangwa/local market context.
3. Output raw baseline market costs ONLY. Do NOT apply labor, setup, or 3x markup in the item prices (the backend handles the 3x pricing rule).
4. Boxes Are Not Counts: Quantities come from the Context counts and the counting rules above, never from the number of boxes. A box only marks where an item is visible in this photo, not how many are needed for the event.

SUGGESTED ALTERNATIVES & SEASONAL CONTEXT (DECISION-SUPPORT ONLY):
1. Alternatives: If a detected or recommended flower is expensive, delicate, or potentially difficult to source in the Philippines, you may suggest a practical, accessible floral substitute in `suggested_alternative` (e.g., Spray Rose for Garden Rose) and state why in `alternative_reason`. If no substitute is needed, set both to null.
2. Seasonality: Note general seasonal context relative to the event date/month in `seasonal_notes` and set `seasonality_status` (e.g. "year_round", "in_season", "off_season", "requires_validation") if known for the local Philippine market. This is decision-support only and never authoritative; authorized Raflora staff must validate availability.

REQUIRED JSON SCHEMA:
{
  "suggested_materials": [
    {
      "item_name": "string",
      "category": "flower|foliage|prop|supply",
      "quantity": number,
      "unit_type": "stem|bunch|piece|set",
      "unit_cost_php": number,
      "area": "ceiling|stage|altar|entrance|tables|backdrop|wall|aisle|overall",
      "is_detected": boolean,
      "is_recommendation": boolean,
      "is_custom_item": boolean,
      "confidence": number from 0.0 to 1.0,
      "detected_regions": integer,
      "needs_review": boolean,
      "review_message": "string or empty string",
      "note": "string",
      "suggested_alternative": "string or null",
      "alternative_reason": "string or null",
      "seasonality_status": "string or null",
      "seasonal_notes": "string or null",
      "boxes": [{ "box_2d": [ymin, xmin, ymax, xmax], "confidence": number from 0.0 to 1.0 }]
    }
  ]
}

When area information is available, group items by the area where the item actually appears and indicate whether each entry is an AI-detected element or an AI recommendation. For each visibly identifiable detection, return its bounding boxes in `boxes`, one per visible cluster or instance. Each `box_2d` is four integers [ymin, xmin, ymax, xmax] on a 0-1000 scale of this image exactly as it was given to you: y is measured down from the top edge as a share of the image height, x is measured right from the left edge as a share of the image width, and (0, 0) is the top-left corner. Keep the order y before x. Draw each box tightly around the visible extent of the named item itself, not an arbitrary part of its area. Each box's `confidence` is your calibrated probability that this box really contains the named item. Use an empty list when the item cannot be visually grounded with reasonable confidence. Recommendations and non-visible supporting materials must use an empty list. Never derive boxes from area, item order, array position, a generic region, or another item's box; never invent coordinates. Before returning JSON, verify that every box belongs to a row with `is_detected: true` and `is_recommendation: false`, that every detected row with no reliable box uses an empty list rather than a guess, and that each box visibly encloses the named item itself rather than only its surrounding area.

CONFIDENCE CALIBRATION:
- Return `confidence` for every row as your calibrated probability that the named item is actually visible and correctly identified in this image, not how certain you are that the item is commonly used in event design.
- Use the full 0.0-1.0 range. Clear, unobstructed, object-specific detections with a reliable box should generally be 0.85-0.98. Partially visible, overlapping, small, or ambiguous detections should generally be 0.55-0.84. Recommendations or items not visibly confirmed should generally be 0.30-0.60.
- Do not reuse the same confidence value across rows unless their visual evidence is genuinely equivalent. A recommendation must not receive the same confidence as a clearly visible detection.
- Set `needs_review` to true when confidence is below 0.80, the named item is partly obscured or overlapping, or a box cannot be tied to that item. Explain the reason briefly in `review_message`. Set it to false for clear, object-specific detections.
PROMPT;

        $briefLines = EventDesignBrief::promptContextLines($designBrief, $venueSetting);

        if (!empty($eventType) || !empty($eventDate) || !empty($eventTime) || !empty($endTime) || !empty($venue) || !empty($specialRequests) || !empty($scaleContext) || $briefLines !== []) {
            $systemPrompt .= "\n\nContext:";
            if (!empty($eventType)) {
                $systemPrompt .= "\n- Event Type: " . trim($eventType);
            }
            if (!empty($eventDate)) {
                $systemPrompt .= "\n- Event Date: " . trim($eventDate);
            }
            if (!empty($eventTime)) {
                $systemPrompt .= "\n- Event Start Time: " . trim($eventTime);
            }
            if (!empty($endTime)) {
                $systemPrompt .= "\n- Event End Time: " . trim($endTime);
            }
            if (!empty($venue)) {
                $systemPrompt .= "\n- Venue: " . trim($venue);
            }
            if (!empty($scaleContext)) {
                $systemPrompt .= "\n- Scale: " . trim($scaleContext);
            }
            if (!empty($specialRequests)) {
                $systemPrompt .= "\n- Special Requests: " . trim($specialRequests);
            }
            foreach ($briefLines as $line) {
                $systemPrompt .= "\n- " . $line;
            }
        }

        $systemPrompt .= EventDesignBrief::promptRules($designBrief, $venueSetting);

        $preparedImage = $this->prepareImageForGemini($filePath);
        $mimeType = $preparedImage['mime_type'];
        $base64 = $preparedImage['data'];

        $lastException = null;
        $consecutiveQuotaFailures = 0;
        $attempt = 0;

        foreach ($this->fallbackModels as $model) {
            $attempt++;
            try {
                // Build Google Gemini v1beta endpoint for the specific model and include API key in query
                if (empty($this->apiKey)) {
                    throw new \Exception('GEMINI_API_KEY is not configured in environment.');
                }

                $endpoint = rtrim($this->apiUrl, '/') . "/{$model}:generateContent?key=" . urlencode($this->apiKey);

                $payload = [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $systemPrompt],
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data'      => $base64,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'responseMimeType' => 'application/json',
                    ],
                ];

                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->timeout(60)->connectTimeout(60)->post($endpoint, $payload);

                $status = $response->status();

                // Detect quota / rate limit
                $body = $response->body();

                // Detect quota / rate limit or temporary unavailability by status or response content
                if ($this->isRetryableRateLimit($status, $body)) {
                    $consecutiveQuotaFailures++;
                    Log::warning("Quota/rate-limit reached for {$model} (status={$status}).");
                    if ($consecutiveQuotaFailures >= $this->maxConsecutiveQuotaFailures) {
                        Log::warning("Quota exhausted across models; failing fast to protect API quota.");
                        throw new \Exception("Gemini quota exhausted across models (status={$status}): {$body}");
                    }
                    $retryAfter = $response->header('Retry-After');
                    $this->handleRetryBackoff($retryAfter ? (int) $retryAfter : null, $attempt);
                    continue; // try next model
                }

                if ($status === 404) {
                    Log::warning("Gemini model {$model} not found or unavailable; trying next model.", [
                        'status' => $status,
                        'body' => $body,
                    ]);
                    continue;
                }

                if ($status >= 400) {
                    // Non-quota client/server error — bail out
                    throw new \Exception("Gemini API error (model={$model}) status={$status}: {$body}");
                }

                $responseData = $response->json();

                $generationConfig = [
                    'temperature' => 0.2,
                    'responseMimeType' => 'application/json',
                ];

                // Prefer the first candidate part text when available.
                $generatedText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if (is_string($generatedText)) {
                    $generatedText = trim($generatedText);
                }

                if (empty($generatedText)) {
                    // Fallback to scanning all candidate parts for JSON.
                    $candidateContent = $responseData['candidates'][0]['content'] ?? [];
                    $candidateParts = [];

                    if (is_array($candidateContent)) {
                        foreach ($candidateContent as $contentSegment) {
                            if (is_array($contentSegment) && isset($contentSegment['parts']) && is_array($contentSegment['parts'])) {
                                $candidateParts = array_merge($candidateParts, $contentSegment['parts']);
                            } elseif (is_array($contentSegment) && isset($contentSegment['text'])) {
                                $candidateParts[] = $contentSegment;
                            } elseif (is_string($contentSegment)) {
                                $candidateParts[] = ['text' => $contentSegment];
                            }
                        }
                    }

                    if (empty($candidateParts) && is_string($candidateContent)) {
                        $candidateParts[] = ['text' => $candidateContent];
                    }

                    $allText = [];
                    foreach ($candidateParts as $part) {
                        if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                            $text = trim($part['text']);
                            if ($text !== '') {
                                $allText[] = $text;
                            }
                            if ($this->extractFirstJson($text) !== null) {
                                $generatedText = $text;
                                break;
                            }
                        }
                    }

                    if ($generatedText === null && !empty($allText)) {
                        $generatedText = implode("\n", $allText);
                    }
                }

                if (empty($generatedText)) {
                    throw new \Exception("Gemini response missing generated text (model={$model}). Raw: {$body}");
                }

                $parsed = $this->extractFirstJson($generatedText);
                if ($parsed === null) {
                    throw new \Exception("Generated content did not contain valid JSON (model={$model}). Raw generated: {$generatedText}");
                }

                $preparedMeta = [
                    'mime_type' => $mimeType,
                    'width' => null,
                    'height' => null,
                    'prepared_mime_type' => $mimeType,
                    'prepared_width' => $preparedImage['width'],
                    'prepared_height' => $preparedImage['height'],
                    'exif_orientation' => $preparedImage['exif_orientation'],
                    'box_orientation' => $preparedImage['box_orientation'],
                ];

                return [
                    'model_used' => $model,
                    'analyzed_at' => now()->toIso8601String(),
                    'analysis' => $this->alignBoxesWithDisplayedImage($parsed, $preparedImage['box_orientation']),
                    'raw_response' => $body,
                    'generation_config' => $generationConfig,
                    'image_metadata' => $preparedMeta,
                ];
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastException = $e;
                Log::warning('GeminiVisionService connection timeout or network issue.', [
                    'message' => $e->getMessage(),
                    'model' => $model,
                    'file' => $filePath,
                ]);

                continue; // Move to the next fallback model
            } catch (\Exception $e) {
                $lastException = $e;
                Log::error('GeminiVisionService analyzeImageFromPath failed.', [
                    'message' => $e->getMessage(),
                    'model' => $model,
                    'file' => $filePath,
                    'throwable' => $e,
                ]);

                // If exception message indicates quota or temporary capacity, continue; else rethrow
                $msg = $e->getMessage();
                if (stripos($msg, 'RESOURCE_EXHAUSTED') !== false || stripos($msg, 'UNAVAILABLE') !== false || stripos($msg, 'quota') !== false || stripos($msg, '429') !== false || stripos($msg, '503') !== false) {
                    $consecutiveQuotaFailures++;
                    Log::warning("Quota/limit detected for {$model}: {$msg}.");
                    if ($consecutiveQuotaFailures >= $this->maxConsecutiveQuotaFailures) {
                        Log::warning("Quota exhausted across models; failing fast to protect API quota.");
                        throw $e;
                    }
                    $this->handleRetryBackoff(null, $attempt);
                    continue;
                }

                throw $e;
            }
        }

        Log::warning('All Gemini fallback models failed; throwing exception.', [
            'last_exception' => $lastException?->getMessage(),
            'file' => $filePath,
        ]);

        throw new \Exception('Failed to analyze image with AI after trying all fallback models: ' . ($lastException ? $lastException->getMessage() : 'Unknown error'));
    }

    /**
     * Gemini returns boxes in the frame of the image it was sent. When that was the stored frame
     * of a turned phone photo, each box is turned the way browsers turn the photo, so it lands
     * on the item as people see it. When the frame is unknown no box is kept.
     */
    protected function alignBoxesWithDisplayedImage(array $analysis, ?int $boxOrientation): array
    {
        if ($boxOrientation === 1 || !is_array($analysis['suggested_materials'] ?? null)) {
            return $analysis;
        }

        foreach ($analysis['suggested_materials'] as $index => $material) {
            if (!is_array($material)) {
                continue;
            }

            $aligned = [];
            foreach ($boxOrientation === null ? [] : $this->rawBoxEntries($material) as $entry) {
                $box = $this->normalizeBox($entry['box']);
                if ($box !== null) {
                    $aligned[] = ['box_2d' => ImageOrientation::mapBoxToDisplay($box, $boxOrientation), 'confidence' => $entry['confidence']];
                }
            }

            $material['boxes'] = $aligned;
            unset($material['box_2d'], $material['visual_location']);
            $analysis['suggested_materials'][$index] = $material;
        }

        return $analysis;
    }

    /**
     * Prepare an image for Gemini: an upright JPEG of at most 1024px when GD is available,
     * otherwise the original bytes with the EXIF turn tag neutralised.
     *
     * `box_orientation` is the EXIF turn still to apply to Gemini's boxes so they match the photo
     * as browsers show it: 1 for none, null when it cannot be known.
     *
     * @return array{mime_type: string, data: string, width: ?int, height: ?int, exif_orientation: int, box_orientation: ?int}
     * @throws \Exception
     */
    protected function prepareImageForGemini(string $filePath): array
    {
        $orientation = ImageOrientation::read($filePath);

        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng') || !function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
            $bytes = file_get_contents($filePath);
            $info = @getimagesize($filePath);
            $boxOrientation = 1;

            if ($orientation !== 1) {
                $untagged = ImageOrientation::withoutOrientationTag($bytes);
                $bytes = $untagged ?? $bytes;
                $boxOrientation = $untagged !== null ? $orientation : null;
            }

            return [
                'mime_type' => mime_content_type($filePath) ?: 'image/jpeg',
                'data' => base64_encode($bytes),
                'width' => is_array($info) ? (int) $info[0] : null,
                'height' => is_array($info) ? (int) $info[1] : null,
                'exif_orientation' => $orientation,
                'box_orientation' => $boxOrientation,
            ];
        }

        $info = getimagesize($filePath);
        if ($info === false) {
            throw new \Exception('Unsupported image format for Gemini upload.');
        }

        $source = match ($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($filePath),
            IMAGETYPE_PNG => imagecreatefrompng($filePath),
            IMAGETYPE_GIF => imagecreatefromgif($filePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($filePath),
            IMAGETYPE_BMP => imagecreatefrombmp($filePath),
            default => throw new \Exception('Unsupported image type for Gemini upload.'),
        };

        if ($source === false) {
            throw new \Exception('Failed to read source image for resizing.');
        }

        // GD ignores the EXIF tag, so the pixels are turned upright here before Gemini sees them.
        $upright = $this->turnUpright($source, $orientation);
        $boxOrientation = $upright !== null ? 1 : $orientation;
        $source = $upright ?? $source;

        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = min(1, 1024 / $width, 1024 / $height);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        if ($resized === false) {
            throw new \Exception('Failed to create resized image canvas.');
        }

        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        $written = imagejpeg($resized, null, 80);
        $jpeg = ob_get_clean();

        if (!$written || !is_string($jpeg) || $jpeg === '') {
            throw new \Exception('Failed to write resized JPEG image for Gemini upload.');
        }

        // Upright pixels carry no tag; unturned pixels are sent as stored and their boxes turned later.
        return [
            'mime_type' => 'image/jpeg',
            'data' => base64_encode($jpeg),
            'width' => $newWidth,
            'height' => $newHeight,
            'exif_orientation' => $orientation,
            'box_orientation' => $boxOrientation,
        ];
    }

    /** The image flipped/turned to its EXIF orientation, or null when GD could not do it. */
    protected function turnUpright(\GdImage $image, int $orientation): ?\GdImage
    {
        if ($orientation === 1) {
            return $image;
        }

        // imagerotate() turns anticlockwise; 270 is a quarter turn clockwise.
        $angle = match ($orientation) {
            3 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };

        if ($angle !== 0) {
            $image = imagerotate($image, $angle, 0);
            if ($image === false) {
                return null;
            }
        }

        $flip = match ($orientation) {
            2, 5, 7 => IMG_FLIP_HORIZONTAL,
            4 => IMG_FLIP_VERTICAL,
            default => null,
        };

        if ($flip !== null && !imageflip($image, $flip)) {
            return null;
        }

        return $image;
    }

    /**
     * Try to extract the first JSON object or array from a string.
     * @param string $text
     * @return array|null
     */
    protected function extractFirstJson(string $text): ?array
    {
        // Attempt pure json decode first
        $clean = trim($text);
        $decoded = json_decode($clean, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Find first JSON object or array using regex
        if (preg_match('/(\{.*\}|\[.*\])/s', $text, $m)) {
            $candidate = $m[0];
            $decoded = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}

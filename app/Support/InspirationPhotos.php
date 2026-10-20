<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The inspiration photos of one Smart AI request and the decoration areas each is for.
 *
 * A photo is linked to one or more of the areas the client picked (photo_areas) and is analyzed
 * for those areas only. Photos that share an area are additional views of one design, so their
 * results are combined, never added up: per area, the same material seen in several photos
 * becomes one line with the largest quantity, and differing quantities are flagged for staff
 * review. Materials of photos with no area in common are separate installations and stay
 * separate. Matching is by area, item name and unit, which is not visual duplicate detection.
 *
 * Stored analysis (temporary_guest_bookings.analysis_data, bookings.ai_analysis_data):
 *   images[]             one entry per photo: id, image_path, photo_areas, its own rows
 *                        (image_id, line_id "p{id}-m{n}", boxes) for the annotated photo
 *   suggested_materials  the combined rows used for areas, estimates and booking materials,
 *                        each with image_ids and source_lines (photo, line, quantity)
 */
final class InspirationPhotos
{
    public const MAX_PHOTOS = 5;

    /** Upload size limit in kilobytes (5 MB), shared by every inspiration photo upload. */
    public const MAX_KILOBYTES = 5120;

    public const MIMES = ['jpg', 'jpeg', 'png', 'webp'];

    /** Validation for one uploaded photo and the areas it is for. */
    public static function uploadRules(): array
    {
        return [
            'inspiration_image' => ['required', 'image', 'mimes:' . implode(',', self::MIMES), 'max:' . self::MAX_KILOBYTES],
            'photo_areas' => ['nullable', 'array', 'max:' . count(EventDesignBrief::DECOR_AREAS)],
            'photo_areas.*' => ['string', Rule::in(array_keys(EventDesignBrief::DECOR_AREAS))],
        ];
    }

    /**
     * The picked areas a photo is for, in form order. With picked areas the photo needs at least
     * one, all among them; without picked areas it has none.
     *
     * @throws ValidationException
     */
    public static function areasFor(mixed $photoAreas, ?array $brief): array
    {
        $picked = DecorAreaBreakdown::requestedAreas($brief);
        $given = array_values(array_unique(array_filter((array) $photoAreas, 'is_string')));

        if ($picked === []) {
            if ($given !== []) {
                throw ValidationException::withMessages(['photo_areas' => 'Pick the areas to decorate before linking a photo to them.']);
            }

            return [];
        }

        if ($given === []) {
            throw ValidationException::withMessages(['photo_areas' => 'Choose which of your selected areas this photo is for.']);
        }

        if (array_diff($given, $picked) !== []) {
            throw ValidationException::withMessages(['photo_areas' => 'A photo can only be linked to areas you selected to decorate.']);
        }

        return array_values(array_intersect($picked, $given));
    }

    /**
     * Whether a photo's stored analysis was made with other event details than the ones being
     * submitted. A photo is analyzed with the brief narrowed to its own areas
     * (EventDesignBrief::forPhoto), so it is compared against that same narrowed brief: adding an
     * area or editing another area's note leaves it current. Analyses made without event details
     * carry no context to compare.
     *
     * @param  array  $context  the submitted analysis context (GeminiVisionService::analysisContextHash keys)
     */
    public static function analysisIsStale(array $payload, array $context): bool
    {
        if (empty($payload['has_context']) || !isset($payload['context_hash'])) {
            return false;
        }

        $context['design_brief'] = EventDesignBrief::forPhoto($context['design_brief'] ?? null, (array) ($payload['photo_areas'] ?? []));

        return $payload['context_hash'] !== \App\Services\GeminiVisionService::analysisContextHash($context);
    }

    /**
     * Analysis tokens posted by a booking form (a JSON list or an array), limited to MAX_PHOTOS.
     *
     * @throws ValidationException
     */
    public static function tokensFrom(mixed $raw): array
    {
        $tokens = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : $raw;
        $tokens = is_array($tokens) ? array_values(array_unique(array_filter($tokens, fn ($token) => is_string($token) && $token !== ''))) : [];

        if (count($tokens) > self::MAX_PHOTOS) {
            throw ValidationException::withMessages(['analysis_tokens' => 'You can add up to ' . self::MAX_PHOTOS . ' inspiration photos.']);
        }

        return $tokens;
    }

    /**
     * The stored analysis for a request, built from the photos analyzed in this session
     * (session payloads, never browser data). Each photo's file moves from temporary to booking
     * storage. Payloads saved before photos had areas count as covering every picked area.
     *
     * @param  list<array>  $payloads  in upload order
     * @param  bool  $storeFiles  false for a preview: photos stay where they are
     *
     * @throws ValidationException when a photo is linked to an area the request no longer has
     */
    public static function analysisData(array $payloads, ?array $brief, bool $storeFiles = true): array
    {
        $picked = DecorAreaBreakdown::requestedAreas($brief);
        $photos = [];

        foreach (array_values($payloads) as $index => $payload) {
            $id = $index + 1;
            $photoAreas = array_key_exists('photo_areas', $payload) ? array_values((array) $payload['photo_areas']) : $picked;
            if ($picked !== [] && ($photoAreas === [] || array_diff($photoAreas, $picked) !== [])) {
                throw ValidationException::withMessages(['analysis_tokens' => 'Photo ' . $id . ' was analyzed for areas that are no longer selected. Re-analyze it or update its areas.']);
            }
            if ($picked === []) {
                $photoAreas = [];
            }

            $path = $payload['temp_path'] ?? null;
            if ($storeFiles && $path && Storage::disk('local')->exists($path) && str_starts_with($path, 'bookings/temp-analysis/')) {
                $destination = 'bookings/inspiration-images/' . basename($path);
                Storage::disk('local')->move($path, $destination);
                $path = $destination;
            } elseif (!$path || !Storage::disk('local')->exists($path)) {
                $path = null;
            }

            $analysis = is_array($payload['analysis'] ?? null) ? $payload['analysis'] : [];
            $photos[] = [
                'id' => $id,
                'image_path' => $path,
                'temp_path' => $path,
                'original_filename' => $payload['original_filename'] ?? ('Inspiration Photo ' . $id),
                'analysis_token' => $payload['token'] ?? null,
                'photo_areas' => $photoAreas,
                'analysis' => $analysis,
                'suggested_materials' => self::tagRows($analysis['suggested_materials'] ?? [], $id),
                'color_palette' => $analysis['color_palette'] ?? null,
                'arrangement_style' => $analysis['arrangement_style'] ?? null,
                'summary' => $analysis['summary'] ?? null,
            ];
        }

        return [
            'is_multi_image' => count($photos) > 1,
            'images' => $photos,
            'suggested_materials' => self::combine($photos),
            'summary' => $photos[0]['summary'] ?? null,
            'color_palette' => $photos[0]['color_palette'] ?? null,
            'arrangement_style' => $photos[0]['arrangement_style'] ?? null,
        ];
    }

    /**
     * One list of material rows for the request: photos sharing an area are views of one design
     * and are merged; photos with no area in common are added as separate installations.
     *
     * @param  list<array{id: int, photo_areas: list<string>, suggested_materials: list<array>}>  $photos
     */
    public static function combine(array $photos): array
    {
        $groupOf = self::designGroups($photos);
        $buckets = [];

        foreach ($photos as $photo) {
            foreach ($photo['suggested_materials'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $key = $groupOf[$photo['id']] . '|' . ($row['decor_area'] ?? '') . '|' . self::nameKey((string) ($row['item_name'] ?? '')) . '|' . strtolower((string) ($row['unit_type'] ?? ''));
                $buckets[$key][] = $row;
            }
        }

        return array_values(array_map(fn (array $rows) => self::merge($rows), $buckets));
    }

    /**
     * Each photo's own rows for drawing its annotations: image_id and a line ID unique per
     * request. Falls back to the given rows for single-photo analyses stored without images[].
     */
    public static function overlayRows(?array $analysisData, array $fallbackRows): array
    {
        $images = is_array($analysisData['images'] ?? null) ? $analysisData['images'] : [];
        $rows = [];
        foreach ($images as $index => $image) {
            if (is_array($image) && is_array($image['suggested_materials'] ?? null)) {
                array_push($rows, ...self::tagRows($image['suggested_materials'], (int) ($image['id'] ?? $index + 1)));
            }
        }

        return $rows !== [] ? $rows : $fallbackRows;
    }

    /**
     * The photos as booking pages show them: index (for the image URL), id, file name and the
     * areas each is for, with labels and colors.
     *
     * @return list<array{index: int, id: int, filename: string, areas: list<array{key: string, label: string, color: string}>}>
     */
    public static function galleryPhotos(?array $analysisData): array
    {
        $photos = [];
        foreach (is_array($analysisData['images'] ?? null) ? $analysisData['images'] : [] as $index => $image) {
            if (!is_array($image)) {
                continue;
            }
            $photos[] = [
                'index' => $index,
                'id' => (int) ($image['id'] ?? $index + 1),
                'filename' => (string) ($image['original_filename'] ?? ('Photo ' . ($index + 1))),
                'areas' => array_values(array_map(fn (string $area) => [
                    'key' => $area,
                    'label' => EventDesignBrief::DECOR_AREAS[$area] ?? $area,
                    'color' => DecorAreaBreakdown::color($area),
                ], array_filter((array) ($image['photo_areas'] ?? []), fn ($area) => is_string($area) && isset(EventDesignBrief::DECOR_AREAS[$area])))),
            ];
        }

        return $photos;
    }

    /** Rows of one photo with its image_id and line IDs prefixed "p{id}-". */
    private static function tagRows(mixed $rows, int $imageId): array
    {
        $tagged = [];
        foreach (is_array($rows) ? array_values($rows) : [] as $position => $row) {
            if (!is_array($row)) {
                continue;
            }
            $lineId = is_string($row['line_id'] ?? null) && $row['line_id'] !== '' ? $row['line_id'] : 'm' . ($position + 1);
            $row['image_id'] = $imageId;
            $row['line_id'] = str_starts_with($lineId, 'p' . $imageId . '-') ? $lineId : 'p' . $imageId . '-' . $lineId;
            $tagged[] = $row;
        }

        return $tagged;
    }

    /**
     * Photos that share an area belong to one design (connected through any chain of shared
     * areas). Without picked areas every photo is a view of the same design.
     *
     * @return array<int, int> photo id => group number
     */
    private static function designGroups(array $photos): array
    {
        $parent = [];
        foreach ($photos as $photo) {
            $parent[$photo['id']] = $photo['id'];
        }
        $find = function (int $id) use (&$parent, &$find): int {
            return $parent[$id] === $id ? $id : ($parent[$id] = $find($parent[$id]));
        };

        $firstByArea = [];
        foreach ($photos as $photo) {
            $areas = $photo['photo_areas'] === [] ? ['*'] : $photo['photo_areas'];
            foreach ($areas as $area) {
                if (isset($firstByArea[$area])) {
                    $parent[$find($photo['id'])] = $find($firstByArea[$area]);
                } else {
                    $firstByArea[$area] = $photo['id'];
                }
            }
        }

        return array_map(fn (int $id) => $find($id), array_combine(array_keys($parent), array_keys($parent)));
    }

    /** One line for the same material seen in several views: the largest quantity, flagged when they differ. */
    private static function merge(array $rows): array
    {
        $sources = array_map(fn (array $row) => [
            'image_id' => $row['image_id'] ?? null,
            'line_id' => $row['line_id'] ?? null,
            'quantity' => self::quantity($row),
        ], $rows);

        $primary = $rows[0];
        foreach ($rows as $row) {
            if ((self::quantity($row) ?? -1) > (self::quantity($primary) ?? -1)) {
                $primary = $row;
            }
        }

        $merged = $primary + [];
        $merged['image_ids'] = array_values(array_unique(array_filter(array_column($sources, 'image_id'), fn ($id) => $id !== null)));
        $merged['source_lines'] = $sources;

        if (count($rows) > 1) {
            $quantities = array_map(fn ($quantity) => $quantity === null ? null : round($quantity, 3), array_column($sources, 'quantity'));
            if (count(array_unique($quantities, SORT_REGULAR)) > 1) {
                $unit = trim((string) ($primary['unit_type'] ?? ''));
                $shown = implode(' vs ', array_map(
                    fn (array $source) => 'photo ' . $source['image_id'] . ': ' . ($source['quantity'] === null ? 'no quantity' : rtrim(rtrim(number_format($source['quantity'], 2, '.', ''), '0'), '.') . ($unit !== '' ? ' ' . $unit : '')),
                    $sources
                ));
                $note = 'Photos show this differently (' . $shown . '). They are treated as views of one design, so the largest quantity is used until Raflora confirms.';
                $merged['needs_review'] = true;
                $merged['review_message'] = trim(trim((string) ($primary['review_message'] ?? '')) . ' ' . $note);
                $merged['view_conflict'] = true;
            }
        }

        return $merged;
    }

    private static function quantity(array $row): ?float
    {
        $value = $row['quantity'] ?? $row['estimated_quantity'] ?? null;

        return is_numeric($value) && is_finite((float) $value) && (float) $value > 0 ? (float) $value : null;
    }

    private static function nameKey(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($name)) ?? '');
    }
}

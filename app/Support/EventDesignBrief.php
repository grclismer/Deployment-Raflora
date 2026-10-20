<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Validation\Rule;

/**
 * The optional event-planning details a client gives alongside a Smart AI booking:
 * venue setting (Step 1) and the design brief (Step 2: how to use the photo, areas to
 * decorate, theme, palette, flower preferences, material and budget).
 *
 * One definition shared by the booking forms, validation, storage, the Gemini prompt and
 * the Admin/Staff booking pages, so all of them always offer and describe the same options.
 * The photo stays the primary evidence: these details shape scale, recommendations,
 * substitutes and logistics, never invented detections or prices.
 */
final class EventDesignBrief
{
    public const VENUE_SETTINGS = [
        'indoor' => 'Indoor',
        'outdoor' => 'Outdoor',
        'both' => 'Indoor & outdoor',
    ];

    public const PHOTO_USAGE = [
        'exact' => ['label' => 'Copy it exactly', 'hint' => 'Recreate this look as closely as possible.'],
        'style' => ['label' => 'Same style', 'hint' => 'Keep the style; my theme and colors may adjust it.'],
        'colors' => ['label' => 'Colors only', 'hint' => 'I mainly like the colors in this photo.'],
    ];

    public const DECOR_AREAS = [
        'entrance' => 'Entrance or arch',
        'stage' => 'Stage or backdrop',
        'centerpieces' => 'Table centerpieces',
        'vip_table' => 'VIP / presidential table',
        'aisle' => 'Aisle',
        'cake_table' => 'Cake table',
        'bouquets' => 'Bouquets',
    ];

    public const THEMES = [
        'rustic' => 'Rustic',
        'elegant' => 'Elegant',
        'modern' => 'Modern',
        'boho' => 'Boho',
        'tropical' => 'Tropical',
        'garden' => 'Garden',
        'other' => 'Other',
    ];

    public const MATERIALS = [
        'fresh' => 'Fresh flowers',
        'artificial' => 'Artificial flowers',
        'mixed' => 'Mixed (fresh + artificial)',
    ];

    public const BUDGET_RANGES = [
        'under_15k' => 'Under ₱15,000',
        '15k_30k' => '₱15,000 – ₱30,000',
        '30k_60k' => '₱30,000 – ₱60,000',
        '60k_100k' => '₱60,000 – ₱100,000',
        'over_100k' => 'Over ₱100,000',
    ];

    public const MAX_COLORS = 3;

    /** Form field names that make up the brief (stored together in the design_brief column). */
    public const FIELDS = [
        'photo_usage', 'decor_areas', 'decor_area_notes', 'theme', 'theme_other', 'color_palette',
        'preferred_flowers', 'avoid_flowers', 'flower_material', 'budget_range',
    ];

    /**
     * Validation for end time, venue setting and every brief field. All optional.
     */
    public static function rules(): array
    {
        return [
            'end_time' => ['nullable', 'date_format:H:i'],
            'venue_setting' => ['nullable', Rule::in(array_keys(self::VENUE_SETTINGS))],
            'photo_usage' => ['nullable', Rule::in(array_keys(self::PHOTO_USAGE))],
            'decor_areas' => ['nullable', 'array', 'max:' . count(self::DECOR_AREAS)],
            'decor_areas.*' => ['string', Rule::in(array_keys(self::DECOR_AREAS))],
            'decor_area_notes' => ['nullable', 'array'],
            'decor_area_notes.*' => ['nullable', 'string', 'max:500'],
            'theme' => ['nullable', Rule::in(array_keys(self::THEMES))],
            'theme_other' => ['nullable', 'string', 'max:60'],
            'color_palette' => ['nullable', 'array', 'max:' . self::MAX_COLORS],
            'color_palette.*' => ['nullable', 'string', 'max:40'],
            'preferred_flowers' => ['nullable', 'string', 'max:255'],
            'avoid_flowers' => ['nullable', 'string', 'max:255'],
            'flower_material' => ['nullable', Rule::in(array_keys(self::MATERIALS))],
            'budget_range' => ['nullable', Rule::in(array_keys(self::BUDGET_RANGES))],
        ];
    }

    public static function messages(): array
    {
        return [
            'end_time.date_format' => 'The end time must be a valid time.',
            'venue_setting.in' => 'Please choose indoor, outdoor, or both.',
            'photo_usage.in' => 'Please choose how we should use your photo.',
            'decor_areas.*.in' => 'Please choose areas to decorate from the list.',
            'decor_area_notes.*.max' => 'Area preference notes may not exceed 500 characters.',
            'theme.in' => 'Please choose a theme from the list.',
            'theme_other.max' => 'The theme description may not exceed 60 characters.',
            'color_palette.max' => 'Choose up to ' . self::MAX_COLORS . ' colors.',
            'color_palette.*.max' => 'Each color may not exceed 40 characters.',
            'preferred_flowers.max' => 'Preferred flowers may not exceed 255 characters.',
            'avoid_flowers.max' => 'Flowers to avoid may not exceed 255 characters.',
            'flower_material.in' => 'Please choose fresh, artificial, or mixed flowers.',
            'budget_range.in' => 'Please choose a budget range from the list.',
        ];
    }

    /**
     * Clean submitted brief fields into the stored shape, or null when nothing was given.
     */
    public static function fromInput(array $input): ?array
    {
        $text = fn (string $key, int $max) => mb_substr(trim((string) ($input[$key] ?? '')), 0, $max);
        $option = fn (string $key, array $options) => array_key_exists((string) ($input[$key] ?? ''), $options) ? (string) $input[$key] : null;

        $areas = array_values(array_intersect(array_keys(self::DECOR_AREAS), (array) ($input['decor_areas'] ?? [])));

        $areaNotes = [];
        foreach ((array) ($input['decor_area_notes'] ?? []) as $areaKey => $noteText) {
            if (in_array((string) $areaKey, $areas, true)) {
                $cleanNote = mb_substr(trim((string) $noteText), 0, 500);
                if ($cleanNote !== '') {
                    $areaNotes[(string) $areaKey] = $cleanNote;
                }
            }
        }

        $colors = [];
        foreach ((array) ($input['color_palette'] ?? []) as $color) {
            $color = mb_substr(trim((string) $color), 0, 40);
            if ($color !== '' && !in_array(mb_strtolower($color), array_map('mb_strtolower', $colors), true)) {
                $colors[] = $color;
            }
        }

        $theme = $option('theme', self::THEMES);

        $brief = array_filter([
            'photo_usage' => $option('photo_usage', self::PHOTO_USAGE),
            'decor_areas' => $areas,
            'decor_area_notes' => $areaNotes,
            'theme' => $theme,
            'theme_other' => $theme === 'other' ? $text('theme_other', 60) : '',
            'color_palette' => array_slice($colors, 0, self::MAX_COLORS),
            'preferred_flowers' => $text('preferred_flowers', 255),
            'avoid_flowers' => $text('avoid_flowers', 255),
            'flower_material' => $option('flower_material', self::MATERIALS),
            'budget_range' => $option('budget_range', self::BUDGET_RANGES),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        return $brief === [] ? null : $brief;
    }

    /**
     * The brief one photo is analyzed with: only the picked areas this photo is for, so the AI
     * plans those areas and no others. Every other detail is unchanged. Without photo areas
     * (no areas picked) the brief is returned as is.
     */
    public static function forPhoto(?array $brief, array $photoAreas): ?array
    {
        if ($brief === null || $photoAreas === []) {
            return $brief;
        }

        $brief['decor_areas'] = array_values(array_intersect(array_keys(self::DECOR_AREAS), $photoAreas));
        if (isset($brief['decor_area_notes']) && is_array($brief['decor_area_notes'])) {
            $brief['decor_area_notes'] = array_intersect_key($brief['decor_area_notes'], array_flip($brief['decor_areas']));
        }

        return $brief;
    }

    public static function venueSettingFromInput(array $input): ?string
    {
        $setting = (string) ($input['venue_setting'] ?? '');

        return array_key_exists($setting, self::VENUE_SETTINGS) ? $setting : null;
    }

    /**
     * Human-readable rows for the Admin/Staff booking pages and the guest review step.
     *
     * @return array<string, string> label => value
     */
    public static function displayRows(?array $brief): array
    {
        if (empty($brief)) {
            return [];
        }

        $theme = isset($brief['theme']) ? (self::THEMES[$brief['theme']] ?? null) : null;
        if ($theme === 'Other' && !empty($brief['theme_other'])) {
            $theme = 'Other: ' . $brief['theme_other'];
        }

        $areaPreferences = [];
        foreach ($brief['decor_areas'] ?? [] as $area) {
            if (!empty($brief['decor_area_notes'][$area])) {
                $areaPreferences[] = (self::DECOR_AREAS[$area] ?? $area) . ': "' . $brief['decor_area_notes'][$area] . '"';
            }
        }

        return array_filter([
            'Use of photo' => isset($brief['photo_usage']) ? (self::PHOTO_USAGE[$brief['photo_usage']]['label'] ?? null) : null,
            'Areas to decorate' => implode(', ', array_map(fn ($area) => self::DECOR_AREAS[$area] ?? $area, $brief['decor_areas'] ?? [])),
            'Area preferences' => $areaPreferences !== [] ? implode('; ', $areaPreferences) : null,
            'Theme' => $theme,
            'Color palette' => implode(', ', $brief['color_palette'] ?? []),
            'Preferred flowers' => $brief['preferred_flowers'] ?? null,
            'Flowers to avoid' => $brief['avoid_flowers'] ?? null,
            'Flower material' => isset($brief['flower_material']) ? (self::MATERIALS[$brief['flower_material']] ?? null) : null,
            'Budget range' => isset($brief['budget_range']) ? (self::BUDGET_RANGES[$brief['budget_range']] ?? null) : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Context lines for the Gemini prompt's "Context:" block.
     *
     * @return list<string>
     */
    public static function promptContextLines(?array $brief, ?string $venueSetting): array
    {
        $lines = [];
        if ($venueSetting && isset(self::VENUE_SETTINGS[$venueSetting])) {
            $lines[] = 'Venue Setting: ' . self::VENUE_SETTINGS[$venueSetting];
        }
        foreach (self::displayRows($brief) as $label => $value) {
            $lines[] = 'Client ' . $label . ': ' . $value;
        }

        return $lines;
    }

    /**
     * Rules telling the model how to apply the client's details. Only the rules for details
     * the client actually gave are included, so prompts without a brief are unchanged.
     */
    public static function promptRules(?array $brief, ?string $venueSetting): string
    {
        $brief = $brief ?? [];
        $rules = [];

        $usage = $brief['photo_usage'] ?? null;
        if ($usage === 'exact') {
            $rules[] = 'Photo usage is "Copy it exactly": the photo wins over the client theme, color palette and flower preferences. Detected rows describe exactly what is visible, in the visible colors. Mention any conflict with the theme or palette in `note`, but do not change the items to follow it.';
        } elseif ($usage === 'style') {
            $rules[] = 'Photo usage is "Same style": take arrangement style, shape and bloom density from the photo. Detected rows still describe what is visible; the client theme and palette may shape recommendation rows, `suggested_alternative` choices and notes.';
        } elseif ($usage === 'colors') {
            $rules[] = 'Photo usage is "Colors only": the client mainly wants the photo\'s color palette. Detected rows still describe only what is visible, but recommendation rows for the requested areas may use any suitable flowers and structures in those colors.';
        }

        if (($brief['theme'] ?? null) || !empty($brief['color_palette'])) {
            $rules[] = 'Theme and color palette guide recommendation rows, `suggested_alternative` and notes. They never change the colors or identity of detected rows: the No Color Inventing rule still applies to every detection.';
        }

        if (!empty($brief['decor_areas'])) {
            $listed = implode(', ', array_map(
                fn ($area) => '"' . $area . '" (' . self::DECOR_AREAS[$area] . ')',
                array_values(array_intersect(array_keys(self::DECOR_AREAS), $brief['decor_areas']))
            ));
            $rules[] = 'Areas to decorate: plan arrangements for exactly the listed areas. Each listed area that the photo does not show gets recommendation rows (`is_detected: false`, `is_recommendation: true`, `boxes: []`) in the matching `area` value (entrance → entrance, stage or backdrop → stage/backdrop, table centerpieces, VIP table and cake table → tables, aisle → aisle, bouquets → overall). Multiply by the Context counts: one centerpiece per table (table count), one arrangement each for the VIP and cake tables, aisle and bouquet counts sized to the event type and guest count. A material seen in the photo belongs to the listed area it will be used for (for example, an arch in the photo becomes the stage backdrop when only the stage is listed); leave out materials that serve none of the listed areas.';
            $rules[] = 'Per-area breakdown: add two fields to every row. `decor_area` must be exactly one of ' . $listed . ', or "shared" for supplies and materials used across several of those areas. No other value is accepted: rows with any other value are moved to "shared". `quantity_basis` is a short calculation showing how the quantity follows from the Context counts, ending with "= <quantity> <unit>" where the number equals the row\'s `quantity` (for example "15 tables × 1 centerpiece × 12 stems = 180 stems"). A row\'s quantity is the total for its area only, so a material used in two areas gets one row per area.';
        }

        if (!empty($brief['preferred_flowers'])) {
            $rules[] = 'Preferred flowers may be used in recommendation rows and as `suggested_alternative`. They do not turn non-visible flowers into detections.';
        }

        if (!empty($brief['avoid_flowers'])) {
            $rules[] = 'Flowers to avoid (allergies or preferences) must never appear in recommendation rows or as `suggested_alternative`. If an avoided flower is visible in the photo, keep the detected row, set `needs_review` to true, explain it in `review_message`, and give a `suggested_alternative` that is not on the avoid list.';
        }

        $material = $brief['flower_material'] ?? null;
        if ($material === 'artificial') {
            $rules[] = 'Flower material is artificial: price flower and foliage rows at local artificial/silk flower market rates and say "artificial" in `note`. Seasonal availability does not apply to these rows.';
        } elseif ($material === 'mixed') {
            $rules[] = 'Flower material is mixed: keep focal blooms fresh and suggest artificial versions in `note` only where they make sense (ceiling or hanging pieces, long outdoor exposure, filler bulk).';
        } elseif ($material === 'fresh') {
            $rules[] = 'Flower material is fresh: price all flower and foliage rows as fresh stems.';
        }

        if ($venueSetting === 'outdoor' || $venueSetting === 'both') {
            $where = $venueSetting === 'both' ? 'For the outdoor areas (usually entrance and aisle)' : 'The event is outdoors';
            $rules[] = $where . ': prefer heat- and wind-tolerant flowers in `suggested_alternative`, flag delicate blooms with a wilting note, and recommend sturdier mechanics (weighted bases, secured structures) as supply recommendation rows.';
        }

        if (!empty($brief['budget_range'])) {
            $markup = rtrim(rtrim(number_format(Setting::aiEstimateMarkup() ?? Setting::DEFAULT_AI_ESTIMATE_MARKUP, 2, '.', ''), '0'), '.') . 'x';
            $rules[] = "Budget never changes prices or quantities. Keep `unit_cost_php` as plain market prices and keep quantities at what the design needs. The client budget is for the final quoted price, which is about {$markup} your raw material total. If {$markup} the raw total would exceed the budget, use `suggested_alternative` and `alternative_reason` for cheaper substitutes and describe a scaled-down option (which quantities could be reduced) in `note`.";
        }

        if ($rules === []) {
            return '';
        }

        return "\n\nCLIENT DESIGN BRIEF RULES (apply to the Client details in the Context block):\n"
            . implode("\n", array_map(fn ($rule, $i) => ($i + 1) . '. ' . $rule, $rules, array_keys($rules)));
    }
}

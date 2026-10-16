<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\DecorAreaBreakdown;
use App\Support\EventDesignBrief;

/**
 * Indicative AI price ranges for guests and clients: one per decoration area the client picked
 * plus a separate Shared Materials group, or one overall range when no areas were picked. There
 * is deliberately no combined total across areas.
 *
 *   material cost   = Σ quantity × Gemini unit price, over the group's rows that have both
 *   labour & setup  = the Admin allowance, applied once per booking: a fixed amount split across
 *                     the groups in proportion to their material cost, or a percentage of each
 *                     group's material cost before markup (the official quotation's basis)
 *   point           = material cost × AI markup (Admin setting) + labour & setup
 *   range           = point × 0.85 to point × 1.15
 *
 * Money is counted in whole centavos with half-up rounding at each step, and every split hands
 * its rounding remainder out by largest share, so the groups reconcile exactly with the booking's
 * allowance. The displayed range is widened to whole pesos (low end down, high end up), so it
 * always contains the exact ±15% bounds.
 *
 * Only display values leave this class: never unit prices, material costs, the markup or the
 * labour figures. Callers pass server-stored analysis rows only. Rows reused from a completed
 * booking carry Raflora's own costs, so they never count; a group with any unpriced material is
 * shown as incomplete and one with no priced material as unavailable, never as complete.
 */
final class AiEstimateService
{
    public const LOW_PERCENT = 85;

    public const HIGH_PERCENT = 115;

    public const OVERALL = 'overall';

    public const OVERALL_LABEL = 'Overall estimate';

    /**
     * @return array{status: string, reason: ?string, mode: string, labour_included: bool, groups: list<array>}
     */
    public function estimate(mixed $materials, ?array $brief): array
    {
        $requested = DecorAreaBreakdown::requestedAreas($brief);
        $mode = $requested === [] ? 'overall' : 'areas';
        $rows = is_array($materials) ? array_values(array_filter($materials, 'is_array')) : [];

        $groups = [];
        foreach ($mode === 'areas' ? $requested : [self::OVERALL] as $key) {
            $groups[$key] = ['rows' => 0, 'priced' => 0, 'raw' => 0];
        }
        foreach ($rows as $row) {
            $key = $mode === 'areas' ? DecorAreaBreakdown::areaKey($row, $requested) : self::OVERALL;
            $groups[$key] ??= ['rows' => 0, 'priced' => 0, 'raw' => 0];
            $groups[$key]['rows']++;
            $cents = self::rowCents($row);
            if ($cents !== null) {
                $groups[$key]['priced']++;
                $groups[$key]['raw'] += $cents;
            }
        }

        $markup = Setting::aiEstimateMarkup();
        $labour = Setting::aiLabourAllowance();
        if ($markup === null || $labour['mode'] === 'invalid') {
            return $this->result('unavailable', 'settings', $mode, false, $groups, null);
        }

        $raw = array_map(fn (array $group) => $group['raw'], $groups);
        $labourTotal = match ($labour['mode']) {
            'fixed' => (int) round($labour['value'] * 100),
            'percentage' => self::halfUpDiv(array_sum($raw) * (int) round($labour['value'] * 100), 10000),
            default => 0,
        };
        $labourShares = self::allocate($labourTotal, $raw);
        $markupHundredths = (int) round($markup * 100);

        $points = [];
        foreach ($groups as $key => $group) {
            $points[$key] = $group['raw'] > 0
                ? self::halfUpDiv($group['raw'] * $markupHundredths, 100) + $labourShares[$key]
                : null;
        }

        $available = array_filter($points, fn ($point) => $point !== null) !== [];

        return $this->result($available ? 'available' : 'unavailable', $available ? null : ($rows === [] ? 'no_materials' : 'no_prices'), $mode, $labourTotal > 0, $groups, $points);
    }

    /**
     * The estimate for a request's stored analysis (analysis_data / ai_analysis_data, kept on the
     * server), or null when it has no AI materials, as for a package booking.
     */
    public function forAnalysis(mixed $analysisData, ?array $brief): ?array
    {
        $rows = is_array($analysisData) && is_array($analysisData['suggested_materials'] ?? null) ? $analysisData['suggested_materials'] : [];

        return $rows === [] ? null : $this->estimate($rows, $brief);
    }

    /** "₱12,750 – ₱17,250" for a range in whole pesos. */
    public static function formatRange(int $lowPesos, int $highPesos): string
    {
        return '₱' . number_format($lowPesos) . ' – ₱' . number_format($highPesos);
    }

    /**
     * The exact ±15% bounds of a point estimate in centavos, and the whole-peso range shown for it.
     *
     * @return array{low_centavos: int, high_centavos: int, low: int, high: int}
     */
    public static function range(int $pointCentavos): array
    {
        $low = self::halfUpDiv($pointCentavos * self::LOW_PERCENT, 100);
        $high = self::halfUpDiv($pointCentavos * self::HIGH_PERCENT, 100);

        return ['low_centavos' => $low, 'high_centavos' => $high, 'low' => intdiv($low, 100), 'high' => intdiv($high + 99, 100)];
    }

    private function result(string $status, ?string $reason, string $mode, bool $labourIncluded, array $groups, ?array $points): array
    {
        $list = [];
        $totalPoint = 0;
        $totalRows = 0;
        $totalPriced = 0;
        $hasAnyPriced = false;
        $allGroupsComplete = true;

        foreach ($groups as $key => $group) {
            $totalRows += $group['rows'];
            $totalPriced += $group['priced'];
            $point = $points[$key] ?? null;
            if ($point !== null) {
                $totalPoint += $point;
                $hasAnyPriced = true;
            }
            if ($group['rows'] > 0 && $group['priced'] < $group['rows']) {
                $allGroupsComplete = false;
            }

            $range = $point !== null ? self::range($point) : null;
            $groupStatus = match (true) {
                $range === null => 'unavailable',
                $group['priced'] < $group['rows'] => 'incomplete',
                default => 'complete',
            };

            $list[] = [
                'key' => $key,
                'label' => match ($key) {
                    self::OVERALL => self::OVERALL_LABEL,
                    DecorAreaBreakdown::SHARED => DecorAreaBreakdown::SHARED_LABEL,
                    default => EventDesignBrief::DECOR_AREAS[$key] ?? $key,
                },
                'color' => DecorAreaBreakdown::color($key),
                'status' => $groupStatus,
                'low' => $range['low'] ?? null,
                'high' => $range['high'] ?? null,
                'display' => $range !== null ? self::formatRange($range['low'], $range['high']) : null,
                'material_count' => $group['rows'],
                'unpriced_count' => $group['rows'] - $group['priced'],
                'note' => match (true) {
                    $reason === 'settings' => 'AI estimates are paused while Raflora updates its pricing settings.',
                    $group['rows'] === 0 => 'No AI suggestions for this area yet; Raflora will plan it during review.',
                    $groupStatus === 'unavailable' => 'The AI could not price these materials, so no estimate is shown.',
                    $groupStatus === 'incomplete' => 'Incomplete: covers ' . $group['priced'] . ' of ' . $group['rows'] . ' materials. The others have no AI price yet, so the final amount may be higher.',
                    default => null,
                },
            ];
        }

        $combined = null;
        if ($points === null || !$hasAnyPriced || $totalRows === 0) {
            $combined = [
                'status' => 'unavailable',
                'low' => null,
                'high' => null,
                'display' => null,
                'total_materials' => $totalRows,
                'priced_materials' => $totalPriced,
                'note' => $reason === 'settings'
                    ? 'AI estimates are paused while Raflora updates its pricing settings.'
                    : 'Preliminary estimate pending: materials will be reviewed and priced by Raflora florists.',
            ];
        } else {
            $combinedRange = self::range($totalPoint);
            $combinedStatus = $allGroupsComplete ? 'complete' : 'incomplete';
            $combined = [
                'status' => $combinedStatus,
                'low' => $combinedRange['low'],
                'high' => $combinedRange['high'],
                'display' => self::formatRange($combinedRange['low'], $combinedRange['high']),
                'total_materials' => $totalRows,
                'priced_materials' => $totalPriced,
                'note' => $combinedStatus === 'complete'
                    ? 'Combined preliminary AI estimate across all areas. Subject to Raflora staff review.'
                    : 'Incomplete preliminary estimate: covers ' . $totalPriced . ' of ' . $totalRows . ' materials. Remaining items will be priced by Raflora florists.',
            ];
        }

        return [
            'status' => $status,
            'reason' => $reason,
            'mode' => $mode,
            'labour_included' => $labourIncluded,
            'groups' => $list,
            'combined' => $combined,
        ];
    }

    /** A row's AI material cost in centavos, or null when it has no usable quantity and price. */
    private static function rowCents(array $row): ?int
    {
        if (($row['source'] ?? null) === 'completed_template') {
            return null;
        }

        $quantity = self::positiveOrNull($row['quantity'] ?? $row['estimated_quantity'] ?? null);
        $price = self::positiveOrNull($row['unit_cost_php'] ?? $row['estimated_unit_cost_php'] ?? $row['estimated_unit_cost'] ?? null);
        if ($quantity === null || $price === null) {
            return null;
        }

        $cents = (int) round($quantity * (int) round($price * 100));

        return $cents > 0 ? $cents : null;
    }

    /**
     * Splits an amount in centavos across groups in proportion to their weights; the rounding
     * remainder goes to the largest fractional shares, so the parts always add up to the total.
     *
     * @param  array<string, int>  $weights
     * @return array<string, int>
     */
    private static function allocate(int $total, array $weights): array
    {
        $shares = array_fill_keys(array_keys($weights), 0);
        $sum = array_sum($weights);
        if ($total <= 0 || $sum <= 0) {
            return $shares;
        }

        $remainders = [];
        foreach ($weights as $key => $weight) {
            $exact = $total * $weight;
            $shares[$key] = intdiv($exact, $sum);
            $remainders[$key] = $exact % $sum;
        }

        arsort($remainders);
        $left = $total - array_sum($shares);
        foreach (array_keys($remainders) as $key) {
            if ($left <= 0) {
                break;
            }
            if ($weights[$key] > 0) {
                $shares[$key]++;
                $left--;
            }
        }

        return $shares;
    }

    /** $numerator / $denominator for non-negative integers, rounded half up. */
    private static function halfUpDiv(int $numerator, int $denominator): int
    {
        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }

    private static function positiveOrNull(mixed $value): ?float
    {
        return is_numeric($value) && is_finite((float) $value) && (float) $value > 0 ? (float) $value : null;
    }
}

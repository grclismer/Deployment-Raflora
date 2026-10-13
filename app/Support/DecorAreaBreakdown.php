<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Quotation;

/**
 * Groups the AI material rows by the areas the client asked to decorate
 * (EventDesignBrief::DECOR_AREAS), so each area shows what it needs and how it was counted.
 *
 * Every row belongs to one picked area or to "shared": assignAreas() re-tags anything else.
 * Prices never come from the AI. When Raflora price lines are given (the booking's materials,
 * or the issued quotation's lines), each line's verified amount is split over the AI rows it
 * covers, so area subtotals only add up existing verified line prices and always sum to the
 * line total. Lines that match no AI row are listed under "Added by Raflora".
 */
final class DecorAreaBreakdown
{
    /** `decor_area` value for supplies and materials used across several areas. */
    public const SHARED = 'shared';

    public const SHARED_LABEL = 'Shared & general';

    /** Group for priced booking lines that no AI row covers. */
    public const RAFLORA = 'raflora';

    public const RAFLORA_LABEL = 'Added by Raflora';

    public const MISSING_MESSAGE = 'No materials suggested — staff will review';

    /** What each view may see: Raflora's raw line costs, the client's quote share, or quantities only. */
    public const MODE_COSTS = 'costs';

    public const MODE_SHARE = 'share';

    public const MODE_QUANTITIES = 'quantities';

    /**
     * One color per area, used for the area cards and the AI boxes drawn over the photo. Decor
     * area keys and the AI's visual `area` keys share one map (bookings without picked areas
     * color their boxes by visual area).
     */
    private const COLORS = [
        'entrance' => '#e11d48',
        'stage' => '#7c3aed',
        'centerpieces' => '#059669',
        'vip_table' => '#d97706',
        'aisle' => '#0284c7',
        'cake_table' => '#db2777',
        'bouquets' => '#4f46e5',
        self::SHARED => '#475569',
        self::RAFLORA => '#0f766e',
        'ceiling' => '#0891b2',
        'altar' => '#9333ea',
        'tables' => '#059669',
        'backdrop' => '#c026d3',
        'wall' => '#ca8a04',
        'overall' => '#475569',
    ];

    private const DEFAULT_COLOR = '#059669';

    /**
     * Rows without a usable `decor_area` (older analyses, or a tag the client did not pick) are
     * placed by their visual `area` when that points at exactly one picked area.
     */
    private const VISUAL_AREA_CANDIDATES = [
        'entrance' => ['entrance'],
        'stage' => ['stage'],
        'backdrop' => ['stage'],
        'altar' => ['stage'],
        'wall' => ['stage'],
        'aisle' => ['aisle'],
        'tables' => ['centerpieces', 'vip_table', 'cake_table'],
    ];

    public static function color(?string $key): string
    {
        return self::COLORS[strtolower(trim((string) $key))] ?? self::DEFAULT_COLOR;
    }

    /** @return array<string, string> every area color, plus "default", for the pages that draw boxes in JS */
    public static function colors(): array
    {
        return self::COLORS + ['default' => self::DEFAULT_COLOR];
    }

    /** @return list<string> the client's picked area keys, in the order the form lists them */
    public static function requestedAreas(?array $brief): array
    {
        return array_values(array_intersect(array_keys(EventDesignBrief::DECOR_AREAS), (array) ($brief['decor_areas'] ?? [])));
    }

    /**
     * Sets every row's `decor_area` to one of the client's picked areas or "shared". Rows are
     * returned unchanged when the client picked no areas.
     */
    public static function assignAreas(array $materials, ?array $brief): array
    {
        $requested = self::requestedAreas($brief);
        if ($requested === []) {
            return $materials;
        }

        foreach ($materials as $index => $row) {
            if (is_array($row)) {
                $materials[$index]['decor_area'] = self::areaFor($row, $requested);
            }
        }

        return $materials;
    }

    /**
     * The breakdown a booking page shows for one viewer:
     *   admin   Raflora's raw line costs per area (the booking's materials)
     *   staff   quantities only, from the booking's materials
     *   client  quantities only until a quotation is issued, then each area's share of it
     */
    public static function forBooking(object $booking, mixed $materials, string $view): ?array
    {
        $brief = is_array($booking->design_brief ?? null) ? $booking->design_brief : null;

        if (!$booking instanceof Booking) {
            return self::build($materials, $brief);
        }

        if ($view === 'admin') {
            return self::build($materials, $brief, self::linesFromBookingItems($booking->bookingItems));
        }

        if ($view === 'staff') {
            return self::build($materials, $brief, self::linesFromBookingItems($booking->bookingItems, withPrices: false));
        }

        $quotation = $booking->acceptedQuotation ?? $booking->activeQuotation;
        if ($quotation instanceof Quotation && (float) $quotation->final_quoted_price > 0 && is_array($quotation->items_snapshot)) {
            return self::build($materials, $brief, self::linesFromQuotation($quotation), (float) $quotation->final_quoted_price);
        }

        return self::build($materials, $brief);
    }

    /**
     * @param  list<array{id?: int|string|null, name: string, inventory_item_id?: int|null, quantity: float|int|string, unit_price?: float|int|string|null}>|null  $lines
     *         Raflora price lines; null for quantities only
     * @param  float|null  $quoteTotal  the issued quotation's final price, to split over the areas
     * @return array{mode: string, areas: list<array>, missing_areas: list<array{key: string, label: string}>, raw_total_php: ?float, quote_total_php: ?float, pending_count: int}|null
     *         null when the client picked no areas or there are no rows
     */
    public static function build(mixed $materials, ?array $brief, ?array $lines = null, ?float $quoteTotal = null): ?array
    {
        $requested = self::requestedAreas($brief);
        $rows = is_array($materials) ? array_values(array_filter($materials, 'is_array')) : [];
        if ($requested === [] || $rows === []) {
            return null;
        }

        $items = [];
        foreach ($rows as $index => $row) {
            $items[] = self::item($row, $index) + ['area' => self::areaFor($row, $requested)];
        }

        $priced = $lines !== null && array_filter($lines, fn ($line) => ($line['unit_price'] ?? null) !== null) !== [];
        $mode = $lines === null || !$priced ? self::MODE_QUANTITIES : ($quoteTotal !== null ? self::MODE_SHARE : self::MODE_COSTS);
        $extraItems = [];
        if ($lines !== null) {
            [$items, $extraItems] = self::allocate($items, $rows, self::cleanLines($lines));
        }

        $itemsByArea = [];
        foreach ($items as $item) {
            $itemsByArea[$item['area']][] = $item;
        }
        if ($extraItems !== []) {
            $itemsByArea[self::RAFLORA] = $extraItems;
        }

        $areas = [];
        foreach (array_merge($requested, [self::SHARED, self::RAFLORA]) as $key) {
            $areaItems = $itemsByArea[$key] ?? [];
            if ($areaItems !== [] || in_array($key, $requested, true)) {
                $areas[] = self::group($key, $areaItems, in_array($key, $requested, true), $mode);
            }
        }

        $rawTotal = $mode === self::MODE_QUANTITIES ? null : round(array_sum(array_column($areas, 'subtotal_php')), 2);
        if ($mode === self::MODE_SHARE) {
            $areas = self::splitQuote($areas, (float) $rawTotal, (float) $quoteTotal);
        }

        return [
            'mode' => $mode,
            'areas' => $areas,
            'missing_areas' => array_values(array_map(
                fn (array $area) => ['key' => $area['key'], 'label' => $area['label']],
                array_filter($areas, fn (array $area) => $area['missing'])
            )),
            'raw_total_php' => $rawTotal,
            'quote_total_php' => $mode === self::MODE_SHARE ? round((float) $quoteTotal, 2) : null,
            'pending_count' => $mode === self::MODE_QUANTITIES ? 0 : array_sum(array_column($areas, 'pending_count')),
        ];
    }

    /** Price lines from the booking's materials (BookingItem rows). */
    public static function linesFromBookingItems(iterable $bookingItems, bool $withPrices = true): array
    {
        $lines = [];
        foreach ($bookingItems as $item) {
            $lines[] = [
                'id' => $item->id,
                'name' => (string) $item->item_name,
                'inventory_item_id' => $item->inventory_item_id,
                'quantity' => (float) $item->quantity,
                'unit_price' => $withPrices ? (float) $item->quoted_unit_price : null,
            ];
        }

        return $lines;
    }

    /** Price lines from an issued quotation's item snapshot (Admin-approved prices only). */
    public static function linesFromQuotation(Quotation $quotation): array
    {
        return array_values(array_map(fn (array $item) => [
            'id' => null,
            'name' => (string) ($item['item_name'] ?? ''),
            'quantity' => (float) ($item['quantity'] ?? 0),
            'unit_price' => (float) ($item['quoted_unit_price'] ?? 0),
        ], array_filter((array) $quotation->items_snapshot, 'is_array')));
    }

    /**
     * Clean an AI `decor_area` value: an area key or "shared", otherwise null.
     */
    public static function normalizeTag(mixed $value): ?string
    {
        $tag = is_string($value) ? strtolower(trim($value)) : '';

        return isset(EventDesignBrief::DECOR_AREAS[$tag]) || $tag === self::SHARED ? $tag : null;
    }

    /**
     * Clean an AI `quantity_basis` value (the calculation behind a quantity), or null.
     */
    public static function normalizeBasis(mixed $value): ?string
    {
        $basis = is_scalar($value) ? trim(preg_replace('/\s+/', ' ', (string) $value)) : '';

        return $basis === '' ? null : mb_substr($basis, 0, 160);
    }

    /**
     * Why a counting does not support its quantity, or null when it does (or cannot be read).
     * Checks the result after the last "=" against the quantity, and a plain product such as
     * "15 tables × 1 centerpiece × 12 stems" against that result.
     */
    public static function basisProblem(?string $basis, ?float $quantity): ?string
    {
        if ($basis === null || $quantity === null || !str_contains($basis, '=')) {
            return null;
        }

        $equals = strrpos($basis, '=');
        $left = substr($basis, 0, $equals);
        if (!preg_match('/-?\d[\d,]*(?:\.\d+)?/', substr($basis, $equals + 1), $match)) {
            return null;
        }

        $result = (float) str_replace(',', '', $match[0]);
        if (abs($result - $quantity) > max(0.5, $quantity * 0.01)) {
            return 'The AI counting gives ' . self::number($result) . ' but its quantity is ' . self::number($quantity) . '.';
        }

        // Only a pure product is recomputed; additions, ranges or prose are left alone.
        // "×", "*", or an "x" between numbers/spaces (never the x inside a word such as "box").
        $factors = preg_split('/\s*(?:×|\*|(?<=[\d\s])[xX](?=[\s\d]))\s*/u', trim($left));
        if (count($factors) >= 2 && !preg_match('/[+\/=]|\d\s*-\s*\d/', $left)) {
            $product = 1.0;
            foreach ($factors as $factor) {
                if (!preg_match('/^\D*?(\d[\d,]*(?:\.\d+)?)/', $factor, $number)) {
                    return null;
                }
                $product *= (float) str_replace(',', '', $number[1]);
            }

            if (abs($product - $result) > max(0.5, $result * 0.01)) {
                return 'The AI counting multiplies to ' . self::number($product) . ', not ' . self::number($result) . '.';
            }
        }

        return null;
    }

    private static function areaFor(array $row, array $requested): string
    {
        $tag = self::normalizeTag($row['decor_area'] ?? null);
        if ($tag === self::SHARED || in_array($tag, $requested, true)) {
            return $tag;
        }

        $candidates = self::VISUAL_AREA_CANDIDATES[strtolower(trim((string) ($row['area'] ?? '')))] ?? [];
        $picked = array_values(array_intersect($candidates, $requested));

        return count($picked) === 1 ? $picked[0] : self::SHARED;
    }

    private static function item(array $row, int $index): array
    {
        $quantity = self::positiveOrNull($row['quantity'] ?? $row['estimated_quantity'] ?? null);
        $name = trim((string) ($row['item_name'] ?? ''));
        $lineId = is_string($row['line_id'] ?? null) && $row['line_id'] !== '' ? $row['line_id'] : 'm' . ($index + 1);

        return [
            'line_id' => $lineId,
            'name' => $name !== '' ? $name : 'Unnamed item',
            'category' => strtolower(trim((string) ($row['category'] ?? ''))),
            'quantity' => $quantity,
            'ai_quantity' => $quantity,
            'unit_type' => trim((string) ($row['unit_type'] ?? '')) ?: 'pcs',
            'quantity_basis' => self::normalizeBasis($row['quantity_basis'] ?? null),
            'is_detected' => !empty($row['is_detected']) && empty($row['is_recommendation']),
            'needs_review' => !empty($row['needs_review']),
            'in_booking' => null,
            'unit_price' => null,
            'amount' => null,
        ];
    }

    private static function cleanLines(array $lines): array
    {
        $clean = [];
        foreach ($lines as $line) {
            $quantity = is_numeric($line['quantity'] ?? null) ? max(0.0, (float) $line['quantity']) : 0.0;
            $price = is_numeric($line['unit_price'] ?? null) && (float) $line['unit_price'] > 0 ? round((float) $line['unit_price'], 2) : null;
            $clean[] = [
                'id' => $line['id'] ?? null,
                'name' => trim((string) ($line['name'] ?? '')),
                'inventory_item_id' => $line['inventory_item_id'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $price,
                'amount' => $price !== null ? round($quantity * $price, 2) : null,
            ];
        }

        return $clean;
    }

    /**
     * Links each AI row to the Raflora line it became and splits that line's quantity and amount
     * over its rows in proportion to the AI quantities (one row: the whole line).
     *
     * @return array{0: list<array>, 1: list<array>} the AI items, and items for lines no row covers
     */
    private static function allocate(array $items, array $rows, array $lines): array
    {
        $rowsByLine = [];
        foreach ($items as $index => $item) {
            $lineIndex = self::matchLine($rows[$index], $lines);
            $items[$index]['in_booking'] = $lineIndex !== null;
            if ($lineIndex !== null) {
                $rowsByLine[$lineIndex][] = $index;
            }
        }

        foreach ($rowsByLine as $lineIndex => $itemIndexes) {
            $line = $lines[$lineIndex];
            $weights = array_map(fn ($i) => (float) ($items[$i]['ai_quantity'] ?? 0), $itemIndexes);
            $weightTotal = array_sum($weights);
            $allocatedAmount = 0.0;

            foreach ($itemIndexes as $position => $i) {
                $share = $weightTotal > 0 ? $weights[$position] / $weightTotal : 1 / count($itemIndexes);
                $isLast = $position === count($itemIndexes) - 1;
                $items[$i]['quantity'] = round($line['quantity'] * $share, 2);
                $items[$i]['unit_price'] = $line['unit_price'];
                if ($line['amount'] !== null) {
                    // The last row takes the rounding remainder, so the rows add up to the line exactly.
                    $amount = $isLast ? round($line['amount'] - $allocatedAmount, 2) : round($line['amount'] * $share, 2);
                    $allocatedAmount += $amount;
                    $items[$i]['amount'] = $amount;
                }
            }
        }

        $extra = [];
        foreach ($lines as $lineIndex => $line) {
            if (isset($rowsByLine[$lineIndex]) || $line['quantity'] <= 0) {
                continue;
            }

            $extra[] = [
                'line_id' => 'line-' . ($line['id'] ?? $lineIndex + 1),
                'name' => $line['name'] !== '' ? $line['name'] : 'Unnamed item',
                'category' => '',
                'quantity' => round($line['quantity'], 2),
                'ai_quantity' => null,
                'unit_type' => '',
                'quantity_basis' => null,
                'is_detected' => false,
                'needs_review' => false,
                'in_booking' => true,
                'unit_price' => $line['unit_price'],
                'amount' => $line['amount'],
                'area' => self::RAFLORA,
            ];
        }

        return [$items, $extra];
    }

    /** The Raflora line an AI row became: same booking line, same stock item, or same name. */
    private static function matchLine(array $row, array $lines): ?int
    {
        $checks = [
            fn (array $line) => !empty($row['booking_item_id']) && $line['id'] !== null && (string) $line['id'] === (string) $row['booking_item_id'],
            fn (array $line) => !empty($row['inventory_item_id']) && !empty($line['inventory_item_id']) && (int) $line['inventory_item_id'] === (int) $row['inventory_item_id'],
            fn (array $line) => self::nameKey($line['name']) !== '' && self::nameKey($line['name']) === self::nameKey((string) ($row['item_name'] ?? '')),
        ];

        foreach ($checks as $check) {
            foreach ($lines as $index => $line) {
                if ($check($line)) {
                    return $index;
                }
            }
        }

        return null;
    }

    private static function nameKey(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($name)) ?? '');
    }

    private static function group(string $key, array $items, bool $requested, string $mode): array
    {
        $priced = $mode !== self::MODE_QUANTITIES;
        $amounts = array_filter(array_column($items, 'amount'), fn ($value) => $value !== null);

        return [
            'key' => $key,
            'label' => match ($key) {
                self::SHARED => self::SHARED_LABEL,
                self::RAFLORA => self::RAFLORA_LABEL,
                default => EventDesignBrief::DECOR_AREAS[$key],
            },
            'color' => self::color($key),
            'requested' => $requested,
            'missing' => $requested && $items === [],
            'items' => $items,
            'item_count' => count($items),
            'subtotal_php' => $priced ? round(array_sum($amounts), 2) : null,
            'pending_count' => $priced ? count($items) - count($amounts) : 0,
            'share_php' => null,
            'share_percent' => null,
        ];
    }

    /** Each area's part of the quoted price, by its share of the verified line total. */
    private static function splitQuote(array $areas, float $rawTotal, float $quoteTotal): array
    {
        if ($rawTotal <= 0) {
            return $areas;
        }

        $largest = null;
        $allocated = 0.0;
        foreach ($areas as $index => $area) {
            $share = round($area['subtotal_php'] / $rawTotal * $quoteTotal, 2);
            $areas[$index]['share_php'] = $share;
            $areas[$index]['share_percent'] = round($area['subtotal_php'] / $rawTotal * 100, 1);
            $allocated += $share;
            if ($largest === null || $area['subtotal_php'] > $areas[$largest]['subtotal_php']) {
                $largest = $index;
            }
        }

        // Rounding remainder goes to the largest area, so the shares add up to the quote exactly.
        $areas[$largest]['share_php'] = round($areas[$largest]['share_php'] + ($quoteTotal - $allocated), 2);

        return $areas;
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private static function positiveOrNull(mixed $value): ?float
    {
        return is_numeric($value) && is_finite((float) $value) && (float) $value > 0 ? (float) $value : null;
    }
}

<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use Illuminate\Support\Collection;

class QuotationPricingService
{
    /**
     * procurement_status the quote editor gives a material line the Admin removes from the
     * quotation (Admin\BookingController::update). The line keeps its quantity and price for the
     * record; it is left off every official quotation.
     */
    public const REMOVED_FROM_QUOTATION = 'unmatched';

    /**
     * Calculate and update the Admin's working pricing totals for a booking.
     *
     * This service centralizes the logic for calculating:
     * - raw_materials_sum (based on quoted_unit_price of items)
     * - multiplier
     * - labor
     * - final_quoted_price
     *
     * Working totals count every material line, confirmed or not, so the Admin page has a
     * starting figure before materials are validated. An official quotation is priced from
     * quotableItems() and quotationTotals() instead. It does not issue quotations or handle payments.
     */
    public function calculateTotals(Booking $booking, bool $save = true): void
    {
        if (!empty($booking->package_id)) {
            // Package bookings have fixed pricing, do not recalculate based on materials
            return;
        }

        $booking->raw_materials_sum = $this->materialsSum($booking->bookingItems);

        $multiplier = $booking->multiplier ?? Booking::DEFAULT_QUOTATION_MARKUP;
        $booking->multiplier = $multiplier;

        $laborAmount = $this->laborAmount($booking, (float) $booking->raw_materials_sum);

        $finalPrice = ($booking->raw_materials_sum * $multiplier) + $laborAmount;
        $booking->final_quoted_price = round($finalPrice, 2);

        $booking->total_quoted = $booking->final_quoted_price;

        if ($save) {
            $booking->save();
        }
    }

    /**
     * The material lines that belong on an official quotation: confirmed by Admin, with a
     * quantity, and not removed in the quote editor. A quotation's price and its item snapshot
     * are both built from this one set, so they cannot disagree.
     *
     * @return Collection<int, BookingItem>
     */
    public function quotableItems(Booking $booking): Collection
    {
        return $booking->bookingItems
            ->filter(fn (BookingItem $item): bool => !is_null($item->confirmed_at)
                && (float) $item->quantity > 0
                && $item->procurement_status !== self::REMOVED_FROM_QUOTATION)
            ->values();
    }

    /**
     * Official figures for a quotation of $items (see quotableItems()):
     * Σ(quantity × unit price) × multiplier + labour. A package booking keeps its stored catalog
     * pricing, exactly as calculateTotals() leaves it.
     *
     * @param  Collection<int, BookingItem>  $items
     * @return array{raw_materials_sum: float, multiplier: float, labor_amount: float, total: float}
     */
    public function quotationTotals(Booking $booking, Collection $items): array
    {
        $multiplier = (float) ($booking->multiplier ?? Booking::DEFAULT_QUOTATION_MARKUP);

        if (!empty($booking->package_id)) {
            $rawMaterialsSum = (float) $booking->raw_materials_sum;

            return [
                'raw_materials_sum' => $rawMaterialsSum,
                'multiplier' => $multiplier,
                'labor_amount' => round($this->laborAmount($booking, $rawMaterialsSum), 2),
                'total' => (float) $booking->final_quoted_price,
            ];
        }

        $rawMaterialsSum = $this->materialsSum($items);
        $laborAmount = $this->laborAmount($booking, $rawMaterialsSum);

        return [
            'raw_materials_sum' => $rawMaterialsSum,
            'multiplier' => $multiplier,
            'labor_amount' => round($laborAmount, 2),
            'total' => round(($rawMaterialsSum * $multiplier) + $laborAmount, 2),
        ];
    }

    /**
     * @param  iterable<BookingItem>  $items
     */
    private function materialsSum(iterable $items): float
    {
        $rawMaterialsSum = 0.0;
        foreach ($items as $item) {
            $rawMaterialsSum += ($item->quantity * $item->quoted_unit_price);
        }

        return round($rawMaterialsSum, 2);
    }

    private function laborAmount(Booking $booking, float $rawMaterialsSum): float
    {
        if ($booking->labor_method === 'percentage') {
            return $rawMaterialsSum * ((float) $booking->labor_rate / 100);
        }

        if ($booking->labor_method === 'fixed') {
            return (float) $booking->labor_rate;
        }

        return 0.0;
    }
}

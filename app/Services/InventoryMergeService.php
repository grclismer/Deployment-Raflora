<?php

namespace App\Services;

use App\Models\AiAnalysisResult;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Merges a duplicate catalog item into the original. Every record that points at the duplicate
 * (booking lines, stock ledger, stock batches, return audit lines, packages, substitutes, alerts,
 * AI analysis rows) is moved to the original; lines that end up twice on one booking, return or
 * package are combined into one. The original keeps its name and unit cost and receives the
 * duplicate's stock; the duplicate is archived with zero stock. All or nothing.
 */
class InventoryMergeService
{
    /**
     * Active catalog items that are the same material: same words ignoring case, punctuation, word
     * order and singular/plural (InventoryItem::singularWordKey). The lowest id is the original.
     *
     * @return Collection<int, array{keep: InventoryItem, duplicates: Collection<int, InventoryItem>}>
     */
    public function duplicateGroups(): Collection
    {
        return InventoryItem::query()
            ->orderBy('id')
            ->get()
            ->filter(fn (InventoryItem $item): bool => InventoryItem::singularWordKey($item->name) !== '')
            ->groupBy(fn (InventoryItem $item): string => InventoryItem::singularWordKey($item->name))
            ->filter(fn (Collection $items): bool => $items->count() > 1)
            ->map(fn (Collection $items): array => ['keep' => $items->first(), 'duplicates' => $items->slice(1)->values()])
            ->values();
    }

    /**
     * Move everything from $duplicate onto $keep and archive $duplicate.
     *
     * @return array<string, int|float> what was moved, for the report
     */
    public function merge(InventoryItem $keep, InventoryItem $duplicate, ?int $userId = null): array
    {
        if ($keep->is($duplicate)) {
            throw new InvalidArgumentException('An item cannot be merged into itself.');
        }

        return DB::transaction(function () use ($keep, $duplicate, $userId): array {
            $keep = InventoryItem::query()->lockForUpdate()->findOrFail($keep->id);
            $duplicate = InventoryItem::query()->lockForUpdate()->findOrFail($duplicate->id);
            $keepId = $keep->id;
            $dupId = $duplicate->id;

            $report = [
                'booking_lines' => $this->mergeBookingItems($keepId, $dupId),
                'return_lines' => $this->mergeReturnItems($keepId, $dupId),
                'package_lines' => $this->mergePackageItems($keepId, $dupId),
                'ledger_entries' => DB::table('inventory_transactions')->where('inventory_item_id', $dupId)->update(['inventory_item_id' => $keepId]),
                'stock_batches' => DB::table('inventory_stocks')->where('inventory_item_id', $dupId)->update(['inventory_item_id' => $keepId]),
                'images' => DB::table('inventory_item_images')->where('inventory_item_id', $dupId)->update(['inventory_item_id' => $keepId, 'is_primary' => false]),
                'alerts' => DB::table('admin_alerts')->where('inventory_item_id', $dupId)->update(['inventory_item_id' => $keepId]),
                'substitutes' => $this->mergeSubstitutes($keepId, $dupId),
                'ai_rows' => $this->mergeAiAnalysisRows($keepId, $dupId),
                'stock_added' => (float) $duplicate->current_stock,
            ];

            $keep->update(['current_stock' => (float) $keep->current_stock + (float) $duplicate->current_stock]);

            $duplicate->update([
                'current_stock' => 0,
                'description' => trim("Merged into #{$keepId} {$keep->name} on " . now()->toDateString() . '. ' . (string) $duplicate->description),
            ]);
            $duplicate->delete();

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'inventory_items_merged',
                'module' => 'inventory',
                'details' => "Duplicate \"{$duplicate->name}\" (#{$dupId}) merged into \"{$keep->name}\" (#{$keepId}): "
                    . "{$report['booking_lines']} booking line(s), {$report['return_lines']} return line(s), "
                    . "{$report['ledger_entries']} stock movement(s) moved; {$report['stock_added']} stock added.",
                'entity_type' => InventoryItem::class,
                'entity_id' => $keepId,
            ]);

            return $report;
        });
    }

    /**
     * Booking lines move to the original. When a booking already has a line for the original, the
     * two are combined: quantities added, and the unit price set so the line total is unchanged.
     */
    private function mergeBookingItems(int $keepId, int $dupId): int
    {
        $moved = 0;
        foreach (DB::table('booking_items')->where('inventory_item_id', $dupId)->orderBy('id')->get() as $line) {
            $moved++;
            $target = DB::table('booking_items')
                ->where('booking_id', $line->booking_id)
                ->where('inventory_item_id', $keepId)
                ->orderBy('id')
                ->first();

            if (!$target) {
                DB::table('booking_items')->where('id', $line->id)->update(['inventory_item_id' => $keepId]);
                continue;
            }

            $quantity = (float) $target->quantity + (float) $line->quantity;
            $price = $target->quoted_unit_price;
            if ($target->quoted_unit_price !== null || $line->quoted_unit_price !== null) {
                $total = (float) $target->quantity * (float) $target->quoted_unit_price + (float) $line->quantity * (float) $line->quoted_unit_price;
                $price = $quantity > 0 ? round($total / $quantity, 2) : $target->quoted_unit_price;
            }

            DB::table('booking_items')->where('id', $target->id)->update([
                'quantity' => $quantity,
                'quoted_unit_price' => $price,
                'ai_recommended_price' => $target->ai_recommended_price ?? $line->ai_recommended_price,
                // Confirmed only when both lines were confirmed.
                'confirmed_at' => $target->confirmed_at && $line->confirmed_at ? $target->confirmed_at : null,
                'notes' => $this->joinNotes($target->notes, $line->notes),
                'updated_at' => now(),
            ]);
            DB::table('booking_items')->where('id', $line->id)->delete();
        }

        return $moved;
    }

    /**
     * Return audit lines move to the original; two lines on one return are added together and
     * keep the more cautious charge decision (pending, then charge, then no charge).
     */
    private function mergeReturnItems(int $keepId, int $dupId): int
    {
        $moved = 0;
        foreach (DB::table('return_items')->where('inventory_item_id', $dupId)->orderBy('id')->get() as $line) {
            $moved++;
            $target = DB::table('return_items')
                ->where('return_id', $line->return_id)
                ->where('inventory_item_id', $keepId)
                ->orderBy('id')
                ->first();

            if (!$target) {
                DB::table('return_items')->where('id', $line->id)->update(['inventory_item_id' => $keepId]);
                continue;
            }

            $decisions = [$target->charge_decision, $line->charge_decision];
            $decision = match (true) {
                in_array(null, $decisions, true) || in_array('pending', $decisions, true) => 'pending',
                (bool) array_intersect(['charge', 'charge_client'], $decisions) => 'charge',
                default => $target->charge_decision,
            };

            DB::table('return_items')->where('id', $target->id)->update([
                'quantity_returned' => (float) $target->quantity_returned + (float) $line->quantity_returned,
                'quantity_good' => (float) $target->quantity_good + (float) $line->quantity_good,
                'quantity_damaged' => (float) $target->quantity_damaged + (float) $line->quantity_damaged,
                'quantity_lost' => (float) $target->quantity_lost + (float) $line->quantity_lost,
                'final_amount' => $target->final_amount === null && $line->final_amount === null ? null : (float) $target->final_amount + (float) $line->final_amount,
                'damage_charge' => (float) $target->damage_charge + (float) $line->damage_charge,
                'condition' => $target->condition === $line->condition ? $target->condition : 'mixed',
                'charge_decision' => $decision,
                'charge_reason' => $this->joinNotes($target->charge_reason, $line->charge_reason),
                'notes' => $this->joinNotes($target->notes, $line->notes),
                'updated_at' => now(),
            ]);
            DB::table('return_items')->where('id', $line->id)->delete();
        }

        return $moved;
    }

    /** Package lines move to the original; a package listing both gets the quantities added. */
    private function mergePackageItems(int $keepId, int $dupId): int
    {
        $moved = 0;
        foreach (DB::table('inventory_item_package')->where('inventory_item_id', $dupId)->orderBy('id')->get() as $line) {
            $moved++;
            $target = DB::table('inventory_item_package')
                ->where('package_id', $line->package_id)
                ->where('inventory_item_id', $keepId)
                ->first();

            if (!$target) {
                DB::table('inventory_item_package')->where('id', $line->id)->update(['inventory_item_id' => $keepId]);
                continue;
            }

            DB::table('inventory_item_package')->where('id', $target->id)
                ->update(['quantity' => (float) $target->quantity + (float) $line->quantity, 'updated_at' => now()]);
            DB::table('inventory_item_package')->where('id', $line->id)->delete();
        }

        return $moved;
    }

    /** Substitute links move to the original, without self-links or repeated pairs. */
    private function mergeSubstitutes(int $keepId, int $dupId): int
    {
        $moved = DB::table('inventory_item_substitutes')->where('item_id', $dupId)->update(['item_id' => $keepId])
            + DB::table('inventory_item_substitutes')->where('substitute_id', $dupId)->update(['substitute_id' => $keepId]);

        DB::table('inventory_item_substitutes')->whereColumn('item_id', 'substitute_id')->delete();
        $seen = [];
        foreach (DB::table('inventory_item_substitutes')->where('item_id', $keepId)->orWhere('substitute_id', $keepId)->orderBy('id')->get() as $row) {
            $pair = $row->item_id . ':' . $row->substitute_id;
            if (isset($seen[$pair])) {
                DB::table('inventory_item_substitutes')->where('id', $row->id)->delete();
            }
            $seen[$pair] = true;
        }

        return $moved;
    }

    /** AI analysis rows that were matched to the duplicate now point at the original. */
    private function mergeAiAnalysisRows(int $keepId, int $dupId): int
    {
        $changed = 0;
        AiAnalysisResult::query()
            ->where('suggested_materials', 'like', '%inventory_item_id%')
            ->each(function (AiAnalysisResult $analysis) use ($keepId, $dupId, &$changed): void {
                $materials = $analysis->suggested_materials;
                if (!is_array($materials)) {
                    return;
                }

                $touched = false;
                foreach ($materials as $index => $material) {
                    if (is_array($material) && (int) ($material['inventory_item_id'] ?? 0) === $dupId) {
                        $materials[$index]['inventory_item_id'] = $keepId;
                        $touched = true;
                        $changed++;
                    }
                }

                if ($touched) {
                    $analysis->suggested_materials = $materials;
                    $analysis->saveQuietly();
                }
            });

        return $changed;
    }

    private function joinNotes(?string $first, ?string $second): ?string
    {
        $notes = array_values(array_unique(array_filter([trim((string) $first), trim((string) $second)], 'strlen')));

        return $notes === [] ? null : implode(' | ', $notes);
    }
}

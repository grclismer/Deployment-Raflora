<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Services\InventoryMergeService;
use Illuminate\Console\Command;

class MergeDuplicateInventoryItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:merge-duplicates
                            {--pair=* : Merge only these pairs, as original:duplicate ids (e.g. --pair=1:271)}
                            {--dry-run : List what would be merged without changing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merges duplicate inventory items (same words in any order, singular or plural) into the original (lowest id): bookings, stock and history move to the original and the duplicate is archived.';

    /**
     * Execute the console command.
     */
    public function handle(InventoryMergeService $merger): int
    {
        $pairs = [];
        if ($this->option('pair')) {
            foreach ($this->option('pair') as $pair) {
                if (!preg_match('/^(\d+):(\d+)$/', trim($pair), $ids)) {
                    $this->error("Invalid --pair \"{$pair}\". Use original:duplicate, e.g. --pair=1:271.");

                    return self::FAILURE;
                }
                $keep = InventoryItem::find((int) $ids[1]);
                $duplicate = InventoryItem::find((int) $ids[2]);
                if (!$keep || !$duplicate || $keep->is($duplicate)) {
                    $this->error("Pair {$pair}: both items must exist in active inventory and be different.");

                    return self::FAILURE;
                }
                $pairs[] = [$keep, $duplicate];
            }
        } else {
            foreach ($merger->duplicateGroups() as $group) {
                foreach ($group['duplicates'] as $duplicate) {
                    $pairs[] = [$group['keep'], $duplicate];
                }
            }
        }

        if ($pairs === []) {
            $this->info('No duplicate inventory items found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Original (kept)', 'Stock', 'Duplicate (archived)', 'Stock', 'Stock after'],
            array_map(fn (array $pair): array => [
                "#{$pair[0]->id} {$pair[0]->name}",
                (float) $pair[0]->current_stock,
                "#{$pair[1]->id} {$pair[1]->name}",
                (float) $pair[1]->current_stock,
                (float) $pair[0]->current_stock + (float) $pair[1]->current_stock,
            ], $pairs)
        );

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing was changed.');

            return self::SUCCESS;
        }

        foreach ($pairs as [$keep, $duplicate]) {
            $report = $merger->merge($keep, $duplicate);
            $this->line("Merged #{$duplicate->id} into #{$keep->id}: {$report['booking_lines']} booking line(s), {$report['return_lines']} return line(s), {$report['ledger_entries']} stock movement(s), {$report['stock_added']} stock added.");
        }

        $this->info(count($pairs) . ' duplicate item(s) merged.');

        return self::SUCCESS;
    }
}

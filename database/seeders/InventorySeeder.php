<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InventoryItem;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // ─── Flowers (Perishable) ──────────────────────────────────────
            ['name' => 'Red Roses',           'category' => 'Flowers',    'is_perishable' => true,  'current_stock' => 200, 'unit_cost' => 15.00,  'min_stock' => 50,  'unit' => 'stems'],
            ['name' => 'White Roses',         'category' => 'Flowers',    'is_perishable' => true,  'current_stock' => 150, 'unit_cost' => 18.00,  'min_stock' => 50,  'unit' => 'stems'],
            ['name' => 'Pink Roses',          'category' => 'Flowers',    'is_perishable' => true,  'current_stock' => 120, 'unit_cost' => 16.00,  'min_stock' => 40,  'unit' => 'stems'],
            ['name' => 'White Carnations',    'category' => 'Flowers',    'is_perishable' => true,  'current_stock' => 180, 'unit_cost' => 8.00,   'min_stock' => 60,  'unit' => 'stems'],
            ['name' => 'Pink Carnations',     'category' => 'Flowers',    'is_perishable' => true,  'current_stock' => 160, 'unit_cost' => 9.00,   'min_stock' => 60,  'unit' => 'stems'],
            ['name' => 'White Hydrangeas',    'category' => 'Flowers',    'is_perishable' => true,  'current_stock' => 80,  'unit_cost' => 45.00,  'min_stock' => 20,  'unit' => 'stems'],
            ['name' => 'Purple Hydrangeas',   'category' => 'Flowers',    'is_perishable' => true,  'current_stock' => 60,  'unit_cost' => 50.00,  'min_stock' => 15,  'unit' => 'stems'],
            ['name' => 'White Tulips',        'category' => 'Flowers',    'is_perishable' => true,  'current_stock' => 100, 'unit_cost' => 30.00,  'min_stock' => 30,  'unit' => 'stems'],
            ['name' => "Baby's Breath",       'category' => 'Flowers',    'is_perishable' => true,  'current_stock' => 300, 'unit_cost' => 5.00,   'min_stock' => 100, 'unit' => 'bunches'],
            ['name' => 'Sunflowers',          'category' => 'Flowers',    'is_perishable' => true,  'current_stock' => 90,  'unit_cost' => 25.00,  'min_stock' => 20,  'unit' => 'stems'],
            ['name' => 'Gerbera Daisy (Yellow)','category' => 'Flowers',  'is_perishable' => true,  'current_stock' => 110, 'unit_cost' => 12.00,  'min_stock' => 30,  'unit' => 'stems'],
            ['name' => 'Orchids (White)',      'category' => 'Flowers',    'is_perishable' => true,  'current_stock' => 50,  'unit_cost' => 60.00,  'min_stock' => 10,  'unit' => 'stems'],
            ['name' => 'Lily of the Valley',  'category' => 'Flowers',    'is_perishable' => true,  'current_stock' => 40,  'unit_cost' => 35.00,  'min_stock' => 10,  'unit' => 'stems'],

            // ─── Greenery / Fillers (Perishable) ─────────────────────────
            ['name' => 'Eucalyptus Foliage',  'category' => 'Greenery',   'is_perishable' => true,  'current_stock' => 100, 'unit_cost' => 20.00,  'min_stock' => 25,  'unit' => 'bunches'],
            ['name' => 'Fern Leaves',         'category' => 'Greenery',   'is_perishable' => true,  'current_stock' => 150, 'unit_cost' => 10.00,  'min_stock' => 30,  'unit' => 'bunches'],
            ['name' => 'Italian Ruscus',      'category' => 'Greenery',   'is_perishable' => true,  'current_stock' => 80,  'unit_cost' => 15.00,  'min_stock' => 20,  'unit' => 'bunches'],

            // ─── Non-Perishable Props ─────────────────────────────────────
            ['name' => 'Glass Vases (Tall)',  'category' => 'Props',      'is_perishable' => false, 'current_stock' => 30,  'unit_cost' => 250.00, 'min_stock' => 5,   'unit' => 'pcs'],
            ['name' => 'Glass Vases (Short)', 'category' => 'Props',      'is_perishable' => false, 'current_stock' => 40,  'unit_cost' => 150.00, 'min_stock' => 5,   'unit' => 'pcs'],
            ['name' => 'Metal Floral Stand',  'category' => 'Props',      'is_perishable' => false, 'current_stock' => 15,  'unit_cost' => 800.00, 'min_stock' => 3,   'unit' => 'pcs'],
            ['name' => 'Arch Frame (Metal)',  'category' => 'Props',      'is_perishable' => false, 'current_stock' => 5,   'unit_cost' => 2500.00,'min_stock' => 1,   'unit' => 'pcs'],
            ['name' => 'White Satin Ribbon',  'category' => 'Materials',  'is_perishable' => false, 'current_stock' => 50,  'unit_cost' => 80.00,  'min_stock' => 10,  'unit' => 'rolls'],
            ['name' => 'Floral Wire',         'category' => 'Materials',  'is_perishable' => false, 'current_stock' => 100, 'unit_cost' => 25.00,  'min_stock' => 20,  'unit' => 'pcs'],
            ['name' => 'Floral Foam (Oasis)', 'category' => 'Materials',  'is_perishable' => false, 'current_stock' => 60,  'unit_cost' => 35.00,  'min_stock' => 10,  'unit' => 'blocks'],
            ['name' => 'Decorative Branches', 'category' => 'Props',      'is_perishable' => false, 'current_stock' => 20,  'unit_cost' => 120.00, 'min_stock' => 5,   'unit' => 'pcs'],
        ];

        foreach ($items as $item) {
            InventoryItem::updateOrCreate(
                ['name' => $item['name']],
                $item
            );
        }
    }
}

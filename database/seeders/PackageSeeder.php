<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Package;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                'title' => 'Mixed Floral Basket',
                'description' => "Code: BSK-MFL\nMixed selection in a decorative basket",
                'price' => 3500.00,
                'included_items' => [
                    '5 x Sunflowers',
                    '10 x Variegated Carnations',
                    '12 x Burgundy Mums',
                    '5 x Orange Gerbera',
                    '18 x Red Roses',
                    '8 x Yellow Mums',
                    'Appropriate Greenery',
                    'Message Card',
                    'Basket (H-12" x C-40" x D-12")',
                ],
                'is_active' => true,
            ],
            [
                'title' => 'Orange & Yellow Tulip Bouquet',
                'description' => "Code: BQT-YOR\nBright tulip bouquet",
                'price' => 4950.00,
                'included_items' => [
                    '10 x Orange Premium Tulips',
                    '5 x Yellow Premium Tulips',
                    '6 x Orange Premium Gerberas',
                    'Appropriate Greenery',
                    'Wrap Ribbon',
                    'Message Card',
                ],
                'is_active' => true,
            ],
            [
                'title' => 'Premium Pink Tulips Round Box',
                'description' => "Code: RND-PKT\nPremium pink tulips in round box",
                'price' => 8520.00,
                'included_items' => [
                    '30 x Premium Tulips',
                    'Misty Yellow Fillers',
                    'Appropriate Greenery',
                    'Round Box',
                    'Ribbon',
                    'Message Card',
                ],
                'is_active' => true,
            ],
            [
                'title' => '12 Premium Red Roses Square Box',
                'description' => "Code: SQB-R12\nClassic boxed roses",
                'price' => 3500.00,
                'included_items' => [
                    '12 x Premium Red Rose',
                    'Misty Yellow Fillers',
                    'Appropriate Greenery',
                    'Square Box',
                    'Wrap',
                    'Ribbon',
                    'Personalized Message Card',
                ],
                'is_active' => true,
            ],
            [
                'title' => '36 Red Roses Bouquet with Hypericum',
                'description' => "Code: BQT-RHP\nLarge red rose bouquet with hypericum fillers",
                'price' => 5780.00,
                'included_items' => [
                    '36 x Premium Red Roses',
                    'Hypericum Berries Fillers',
                    'Appropriate Greenery',
                    'Wrap',
                    'Ribbon',
                    'Personalized Message Card',
                ],
                'is_active' => true,
            ],
            [
                'title' => '18 Peach Roses & Rice Flowers Square Box',
                'description' => "Code: SQB-PCH\nElegant peach rose box",
                'price' => 3650.00,
                'included_items' => [
                    '18 x Premium Peach Roses',
                    'Fillers of Burgundy Rice Flowers',
                    'Appropriate Greenery',
                    'Square Box',
                    'Ribbon',
                    'Personalized Message Card',
                ],
                'is_active' => true,
            ],
            [
                'title' => '13 Pink Roses Square Box',
                'description' => "Code: SQB-PNK\nCharming pink rose box",
                'price' => 3500.00,
                'included_items' => [
                    '13 x Premium Pink Roses',
                    'Appropriate Greenery',
                    'Square Box',
                    'Ribbon',
                    'Personalized Message Card',
                ],
                'is_active' => true,
            ],
            [
                'title' => 'Red & Pink Roses with Casablanca Lilies Round Box',
                'description' => "Code: RND-RPG\nMixed roses with Casablanca lilies",
                'price' => 4950.00,
                'included_items' => [
                    '8 x Premium Red Roses',
                    '6 x Premium Pink Roses',
                    '6 x Green Lisianthus',
                    '3 x Casablanca Lilies',
                    'Appropriate Greenery',
                    'Round Box',
                    'Ribbon',
                    'Personalized Message Card',
                ],
                'is_active' => true,
            ],
            [
                'title' => 'Red Roses, Carnations & Cymbidium Orchids Round Box',
                'description' => "Code: RND-CMB\nMixed premium arrangement",
                'price' => 5000.00,
                'included_items' => [
                    '20 x Premium Red Roses',
                    '10 x Premium Red Carnations',
                    '3 x Jade Cymbidium Orchids',
                    'Eucalyptus Greenery',
                    'White Gypsophila Filler',
                    'Round Box',
                    'Ribbon',
                    'Personalized Card',
                ],
                'is_active' => true,
            ],
            [
                'title' => 'Red & White Heart-Shaped Box with Crystals',
                'description' => "Code: BHR-RWT\nHeart-shaped luxury box with crystals",
                'price' => 5800.00,
                'included_items' => [
                    '20 x Premium Red Roses',
                    '20 x Premium White Roses',
                    'Appropriate Greenery',
                    'Crystal Studs',
                    'Heart-Shaped Box',
                    'Ribbon',
                    'Personalized Card',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($packages as $pkg) {
            $code = null;
            if (isset($pkg['description']) && preg_match('/Code:\s*([A-Z0-9\-]+)\b/i', $pkg['description'], $matches)) {
                $code = $matches[1];
                $pkg['description'] = trim(preg_replace('/Code:\s*[A-Z0-9\-]+\s*[\r\n]*/i', '', $pkg['description']));
            }
            if (!$code) {
                $code = 'PKG-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $pkg['title']), 0, 5)) . rand(10,99);
            }
            $pkg['package_code'] = $code;

            Package::updateOrCreate([
                'title' => $pkg['title'],
            ], $pkg);
        }
    }
}

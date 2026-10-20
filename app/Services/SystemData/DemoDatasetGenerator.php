<?php

namespace App\Services\SystemData;

use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class DemoDatasetGenerator
{
    /**
     * Generate a complete, internally coherent synthetic demo dataset and save to a temporary ZIP archive.
     *
     * @return string Absolute path to created ZIP file
     */
    public function generate(): string
    {
        $datasetId = 'raflora-demo-' . now()->format('Ymd-His') . '-' . Str::random(6);
        $tempDir = storage_path('app/tmp_system_data/demo_' . Str::random(12));
        File::ensureDirectoryExists($tempDir . '/data');

        try {
            $manifest = $this->buildDemoData($tempDir, $datasetId);

            $zipPath = storage_path('app/tmp_system_data/' . $datasetId . '.zip');
            File::ensureDirectoryExists(dirname($zipPath));

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create ZIP archive for demo dataset.');
            }

            // Add manifest
            $zip->addFile($tempDir . '/manifest.json', 'manifest.json');

            // Add data files
            $files = File::files($tempDir . '/data');
            foreach ($files as $file) {
                $zip->addFile($file->getPathname(), 'data/' . $file->getFilename());
            }

            $zip->close();

            return $zipPath;
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    /**
     * Build the synthetic demo dataset in the temporary directory.
     */
    protected function buildDemoData(string $tempDir, string $datasetId): array
    {
        $now = Carbon::now();
        $admin = User::where('role', 'admin')->first();
        $staff = User::where('role', 'staff')->first();

        $adminEmail = $admin?->email ?? 'admin@raflora.com';
        $adminName = $admin?->name ?? 'Raflora Admin';
        $staffEmail = $staff?->email ?? 'staff@raflora.com';
        $staffName = $staff?->name ?? 'Raflora Staff';

        $entities = [];

        // 1. Users (Safe references only)
        $users = [
            [
                'key' => 'user-admin',
                'email' => $adminEmail,
                'role' => 'admin',
                'name' => $adminName,
                'first_name' => 'Raflora',
                'last_name' => 'Admin',
                'username' => 'admin',
            ],
            [
                'key' => 'user-staff',
                'email' => $staffEmail,
                'role' => 'staff',
                'name' => $staffName,
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'username' => 'staff',
            ],
        ];
        $this->writeJson($tempDir . '/data/users.json', $users);
        $entities['users'] = count($users);

        // 2. Clients
        $clients = [
            [
                'key' => 'client-001',
                'full_name' => 'Juan Dela Cruz',
                'email' => 'client@demo.com',
                'phone' => '09170000003',
                'address' => '123 Ayala Avenue, Makati City',
                'notes' => 'Preferred corporate & wedding client.',
                'created_at' => $now->copy()->subDays(60)->toISOString(),
                'updated_at' => $now->copy()->subDays(60)->toISOString(),
            ],
            [
                'key' => 'client-002',
                'full_name' => 'Maria Clara Santos',
                'email' => 'clara.santos@demo.com',
                'phone' => '09181112233',
                'address' => '456 Timog Avenue, Quezon City',
                'notes' => 'Debut celebration coordinator.',
                'created_at' => $now->copy()->subDays(45)->toISOString(),
                'updated_at' => $now->copy()->subDays(45)->toISOString(),
            ],
            [
                'key' => 'client-003',
                'full_name' => 'Roberto Gomez',
                'email' => 'roberto.gomez@demo.com',
                'phone' => '09192223344',
                'address' => '789 Ortigas Center, Pasig City',
                'notes' => 'Gala and anniversary organizer.',
                'created_at' => $now->copy()->subDays(30)->toISOString(),
                'updated_at' => $now->copy()->subDays(30)->toISOString(),
            ],
            [
                'key' => 'client-004',
                'full_name' => 'Beatrice Tan',
                'email' => 'beatrice.tan@demo.com',
                'phone' => '09203334455',
                'address' => '101 BGC Boulevard, Taguig City',
                'notes' => 'Executive luncheon client.',
                'created_at' => $now->copy()->subDays(20)->toISOString(),
                'updated_at' => $now->copy()->subDays(20)->toISOString(),
            ],
        ];
        $this->writeJson($tempDir . '/data/clients.json', $clients);
        $entities['clients'] = count($clients);

        // 3. Inventory Items
        $inventory = [
            // Perishable Flowers (Normal Stock)
            [
                'key' => 'inventory-001',
                'item_code' => 'FLW-0001',
                'name' => 'Red Roses',
                'category' => 'Flowers',
                'is_perishable' => true,
                'current_stock' => 180.00,
                'unit_cost' => 15.00,
                'min_stock' => 50.00,
                'unit' => 'stems',
                'image_path' => null,
                'deleted_at' => null,
                'created_at' => $now->copy()->subDays(90)->toISOString(),
                'updated_at' => $now->copy()->subDays(5)->toISOString(),
            ],
            [
                'key' => 'inventory-002',
                'item_code' => 'FLW-0002',
                'name' => 'White Roses',
                'category' => 'Flowers',
                'is_perishable' => true,
                'current_stock' => 120.00,
                'unit_cost' => 18.00,
                'min_stock' => 40.00,
                'unit' => 'stems',
                'image_path' => null,
                'deleted_at' => null,
                'created_at' => $now->copy()->subDays(90)->toISOString(),
                'updated_at' => $now->copy()->subDays(5)->toISOString(),
            ],
            // Low Stock Scenario
            [
                'key' => 'inventory-003',
                'item_code' => 'FLW-0003',
                'name' => 'Pink Roses',
                'category' => 'Flowers',
                'is_perishable' => true,
                'current_stock' => 45.00,
                'unit_cost' => 16.00,
                'min_stock' => 50.00,
                'unit' => 'stems',
                'image_path' => null,
                'deleted_at' => null,
                'created_at' => $now->copy()->subDays(90)->toISOString(),
                'updated_at' => $now->copy()->subDays(2)->toISOString(),
            ],
            // Shortage Scenario
            [
                'key' => 'inventory-004',
                'item_code' => 'FLW-0004',
                'name' => 'Sunflowers',
                'category' => 'Flowers',
                'is_perishable' => true,
                'current_stock' => 8.00,
                'unit_cost' => 25.00,
                'min_stock' => 30.00,
                'unit' => 'stems',
                'image_path' => null,
                'deleted_at' => null,
                'created_at' => $now->copy()->subDays(90)->toISOString(),
                'updated_at' => $now->copy()->subDays(1)->toISOString(),
            ],
            // Fillers & Greenery
            [
                'key' => 'inventory-005',
                'item_code' => 'FLW-0005',
                'name' => "Baby's Breath",
                'category' => 'Flowers',
                'is_perishable' => true,
                'current_stock' => 95.00,
                'unit_cost' => 5.00,
                'min_stock' => 25.00,
                'unit' => 'bunches',
                'image_path' => null,
                'deleted_at' => null,
                'created_at' => $now->copy()->subDays(90)->toISOString(),
                'updated_at' => $now->copy()->subDays(5)->toISOString(),
            ],
            [
                'key' => 'inventory-006',
                'item_code' => 'GRN-0001',
                'name' => 'Eucalyptus Foliage',
                'category' => 'Greenery',
                'is_perishable' => true,
                'current_stock' => 60.00,
                'unit_cost' => 20.00,
                'min_stock' => 20.00,
                'unit' => 'bunches',
                'image_path' => null,
                'deleted_at' => null,
                'created_at' => $now->copy()->subDays(90)->toISOString(),
                'updated_at' => $now->copy()->subDays(5)->toISOString(),
            ],
            // Reusable Non-Perishable Props
            [
                'key' => 'inventory-007',
                'item_code' => 'PRP-0001',
                'name' => 'Tall Glass Vases',
                'category' => 'Props',
                'is_perishable' => false,
                'current_stock' => 30.00,
                'unit_cost' => 250.00,
                'min_stock' => 5.00,
                'unit' => 'pcs',
                'image_path' => null,
                'deleted_at' => null,
                'created_at' => $now->copy()->subDays(90)->toISOString(),
                'updated_at' => $now->copy()->subDays(5)->toISOString(),
            ],
            [
                'key' => 'inventory-008',
                'item_code' => 'PRP-0002',
                'name' => 'Metal Floral Stand',
                'category' => 'Props',
                'is_perishable' => false,
                'current_stock' => 15.00,
                'unit_cost' => 800.00,
                'min_stock' => 3.00,
                'unit' => 'pcs',
                'image_path' => null,
                'deleted_at' => null,
                'created_at' => $now->copy()->subDays(90)->toISOString(),
                'updated_at' => $now->copy()->subDays(5)->toISOString(),
            ],
            [
                'key' => 'inventory-009',
                'item_code' => 'PRP-0003',
                'name' => 'Arch Frame (Metal)',
                'category' => 'Props',
                'is_perishable' => false,
                'current_stock' => 5.00,
                'unit_cost' => 2500.00,
                'min_stock' => 1.00,
                'unit' => 'pcs',
                'image_path' => null,
                'deleted_at' => null,
                'created_at' => $now->copy()->subDays(90)->toISOString(),
                'updated_at' => $now->copy()->subDays(5)->toISOString(),
            ],
            [
                'key' => 'inventory-010',
                'item_code' => 'MAT-0001',
                'name' => 'White Satin Ribbon',
                'category' => 'Materials',
                'is_perishable' => false,
                'current_stock' => 40.00,
                'unit_cost' => 80.00,
                'min_stock' => 10.00,
                'unit' => 'rolls',
                'image_path' => null,
                'deleted_at' => null,
                'created_at' => $now->copy()->subDays(90)->toISOString(),
                'updated_at' => $now->copy()->subDays(5)->toISOString(),
            ],
        ];
        $this->writeJson($tempDir . '/data/inventory_items.json', $inventory);
        $entities['inventory_items'] = count($inventory);

        // 4. Inventory Substitutes
        $substitutes = [
            [
                'key' => 'substitute-001',
                'item_ref' => 'inventory-001', // Red Roses
                'substitute_ref' => 'inventory-003', // Pink Roses
            ],
            [
                'key' => 'substitute-002',
                'item_ref' => 'inventory-001', // Red Roses
                'substitute_ref' => 'inventory-002', // White Roses
            ],
        ];
        $this->writeJson($tempDir . '/data/inventory_item_substitutes.json', $substitutes);
        $entities['inventory_item_substitutes'] = count($substitutes);

        // 5. Packages
        $packages = [
            [
                'key' => 'package-001',
                'package_code' => 'PKG-WED-01',
                'title' => 'Grand Garden Wedding Arch Suite',
                'category' => 'Wedding',
                'description' => 'Comprehensive wedding floral package including ceremony arch and aisle stands.',
                'price' => 25000.00,
                'included_items' => [
                    '1x Arch Frame (Metal)',
                    '40x Red Roses',
                    '20x White Roses',
                    '2x Metal Floral Stand',
                    '4x Tall Glass Vases',
                    '5x Eucalyptus Foliage bunches',
                ],
                'image_path' => null,
                'is_active' => true,
                'is_archived' => false,
                'created_at' => $now->copy()->subDays(60)->toISOString(),
                'updated_at' => $now->copy()->subDays(60)->toISOString(),
            ],
            [
                'key' => 'package-002',
                'package_code' => 'PKG-DEB-01',
                'title' => 'Pastel Debutante Floral Table Suite',
                'category' => 'Debut',
                'description' => 'Soft pastel tablescape package with delicate roses and baby breath.',
                'price' => 15000.00,
                'included_items' => [
                    '24x Pink Roses',
                    '24x White Roses',
                    '10x Baby\'s Breath bunches',
                    '6x Tall Glass Vases',
                ],
                'image_path' => null,
                'is_active' => true,
                'is_archived' => false,
                'created_at' => $now->copy()->subDays(50)->toISOString(),
                'updated_at' => $now->copy()->subDays(50)->toISOString(),
            ],
            [
                'key' => 'package-003',
                'package_code' => 'PKG-CORP-01',
                'title' => 'Executive Corporate Gala Centerpieces',
                'category' => 'Corporate',
                'description' => 'Modern minimalist corporate centerpieces for conference and gala tables.',
                'price' => 12000.00,
                'included_items' => [
                    '30x White Roses',
                    '6x Tall Glass Vases',
                    '6x Eucalyptus Foliage bunches',
                ],
                'image_path' => null,
                'is_active' => true,
                'is_archived' => false,
                'created_at' => $now->copy()->subDays(40)->toISOString(),
                'updated_at' => $now->copy()->subDays(40)->toISOString(),
            ],
        ];
        $this->writeJson($tempDir . '/data/packages.json', $packages);
        $entities['packages'] = count($packages);

        // 6. Package Materials (BOM)
        $packageMaterials = [
            // Package 1 BOM
            ['key' => 'pkg-mat-001', 'package_ref' => 'package-001', 'inventory_item_ref' => 'inventory-009', 'quantity' => 1.00],
            ['key' => 'pkg-mat-002', 'package_ref' => 'package-001', 'inventory_item_ref' => 'inventory-001', 'quantity' => 40.00],
            ['key' => 'pkg-mat-003', 'package_ref' => 'package-001', 'inventory_item_ref' => 'inventory-002', 'quantity' => 20.00],
            ['key' => 'pkg-mat-004', 'package_ref' => 'package-001', 'inventory_item_ref' => 'inventory-008', 'quantity' => 2.00],
            ['key' => 'pkg-mat-005', 'package_ref' => 'package-001', 'inventory_item_ref' => 'inventory-007', 'quantity' => 4.00],
            ['key' => 'pkg-mat-006', 'package_ref' => 'package-001', 'inventory_item_ref' => 'inventory-006', 'quantity' => 5.00],
            // Package 2 BOM
            ['key' => 'pkg-mat-007', 'package_ref' => 'package-002', 'inventory_item_ref' => 'inventory-003', 'quantity' => 24.00],
            ['key' => 'pkg-mat-008', 'package_ref' => 'package-002', 'inventory_item_ref' => 'inventory-002', 'quantity' => 24.00],
            ['key' => 'pkg-mat-009', 'package_ref' => 'package-002', 'inventory_item_ref' => 'inventory-005', 'quantity' => 10.00],
            ['key' => 'pkg-mat-010', 'package_ref' => 'package-002', 'inventory_item_ref' => 'inventory-007', 'quantity' => 6.00],
            // Package 3 BOM
            ['key' => 'pkg-mat-011', 'package_ref' => 'package-003', 'inventory_item_ref' => 'inventory-002', 'quantity' => 30.00],
            ['key' => 'pkg-mat-012', 'package_ref' => 'package-003', 'inventory_item_ref' => 'inventory-007', 'quantity' => 6.00],
            ['key' => 'pkg-mat-013', 'package_ref' => 'package-003', 'inventory_item_ref' => 'inventory-006', 'quantity' => 6.00],
        ];
        $this->writeJson($tempDir . '/data/package_materials.json', $packageMaterials);
        $entities['package_materials'] = count($packageMaterials);

        // 7. Bookings spanning valid lifecycle states
        $bookings = [
            // State 1: pending
            [
                'key' => 'booking-001',
                'client_ref' => 'client-001',
                'package_ref' => 'package-001',
                'handled_by_ref' => 'user-admin',
                'staff_ref' => null,
                'event_type' => 'wedding',
                'event_date' => $now->copy()->addDays(45)->format('Y-m-d'),
                'event_time' => '14:00:00',
                'event_size' => '150 guests',
                'table_count' => 15,
                'venue' => 'Manila Polo Club, Makati',
                'special_requests' => 'Romantic floral arch with ivory and deep crimson roses.',
                'inspiration_image' => null,
                'status' => 'pending',
                'pre_cancellation_status' => null,
                'confirmed_at' => null,
                'downpayment_amount' => null,
                'downpayment_date' => null,
                'total_quoted' => null,
                'price_valid_until' => null,
                'suggested_procurement_date' => null,
                'preparation_start_date' => null,
                'preparation_status' => 'scheduled',
                'cancellation_reason' => null,
                'admin_notes' => 'New booking inquiry awaiting initial design review.',
                'raw_materials_sum' => 0.00,
                'multiplier' => 3.0,
                'final_quoted_price' => 0.00,
                'guest_name' => null,
                'guest_email' => null,
                'guest_phone' => null,
                'guest_address' => null,
                'ai_analysis_data' => null,
                'labor_method' => 'markup',
                'labor_rate' => 3.0,
                'created_at' => $now->copy()->subDays(2)->toISOString(),
                'updated_at' => $now->copy()->subDays(2)->toISOString(),
            ],
            // State 2: quotation_sent
            [
                'key' => 'booking-002',
                'client_ref' => 'client-002',
                'package_ref' => 'package-002',
                'handled_by_ref' => 'user-admin',
                'staff_ref' => null,
                'event_type' => 'debut',
                'event_date' => $now->copy()->addDays(30)->format('Y-m-d'),
                'event_time' => '18:00:00',
                'event_size' => '100 guests',
                'table_count' => 10,
                'venue' => 'Seda Vertis North, Quezon City',
                'special_requests' => 'Pastel tones with white and pink roses in glass vases.',
                'inspiration_image' => null,
                'status' => 'quotation_sent',
                'pre_cancellation_status' => null,
                'confirmed_at' => null,
                'downpayment_amount' => null,
                'downpayment_date' => null,
                'total_quoted' => 16500.00,
                'price_valid_until' => $now->copy()->addDays(14)->format('Y-m-d'),
                'suggested_procurement_date' => $now->copy()->addDays(27)->format('Y-m-d'),
                'preparation_start_date' => $now->copy()->addDays(29)->format('Y-m-d'),
                'preparation_status' => 'scheduled',
                'cancellation_reason' => null,
                'admin_notes' => 'Quotation v1 issued with 14-day validity.',
                'raw_materials_sum' => 5500.00,
                'multiplier' => 3.0,
                'final_quoted_price' => 16500.00,
                'guest_name' => null,
                'guest_email' => null,
                'guest_phone' => null,
                'guest_address' => null,
                'ai_analysis_data' => [
                    'style' => 'Pastel Romance',
                    'confidence' => 0.94,
                    'is_synthetic_demo' => true,
                ],
                'labor_method' => 'markup',
                'labor_rate' => 3.0,
                'created_at' => $now->copy()->subDays(5)->toISOString(),
                'updated_at' => $now->copy()->subDays(3)->toISOString(),
            ],
            // State 3: approved (quotation accepted by client)
            [
                'key' => 'booking-003',
                'client_ref' => 'client-003',
                'package_ref' => 'package-003',
                'handled_by_ref' => 'user-admin',
                'staff_ref' => null,
                'event_type' => 'corporate',
                'event_date' => $now->copy()->addDays(20)->format('Y-m-d'),
                'event_time' => '10:00:00',
                'event_size' => '80 guests',
                'table_count' => 8,
                'venue' => 'Crowne Plaza Galleria, Ortigas',
                'special_requests' => 'White roses with tall glass vases for corporate banquet.',
                'inspiration_image' => null,
                'status' => 'approved',
                'pre_cancellation_status' => null,
                'confirmed_at' => null,
                'downpayment_amount' => null,
                'downpayment_date' => null,
                'total_quoted' => 12000.00,
                'price_valid_until' => $now->copy()->addDays(10)->format('Y-m-d'),
                'suggested_procurement_date' => $now->copy()->addDays(18)->format('Y-m-d'),
                'preparation_start_date' => $now->copy()->addDays(19)->format('Y-m-d'),
                'preparation_status' => 'scheduled',
                'cancellation_reason' => null,
                'admin_notes' => 'Quotation accepted by client. Awaiting downpayment.',
                'raw_materials_sum' => 4000.00,
                'multiplier' => 3.0,
                'final_quoted_price' => 12000.00,
                'guest_name' => null,
                'guest_email' => null,
                'guest_phone' => null,
                'guest_address' => null,
                'ai_analysis_data' => null,
                'labor_method' => 'markup',
                'labor_rate' => 3.0,
                'created_at' => $now->copy()->subDays(10)->toISOString(),
                'updated_at' => $now->copy()->subDays(4)->toISOString(),
            ],
            // State 4: downpayment_received
            [
                'key' => 'booking-004',
                'client_ref' => 'client-004',
                'package_ref' => 'package-003',
                'handled_by_ref' => 'user-admin',
                'staff_ref' => 'user-staff',
                'event_type' => 'corporate',
                'event_date' => $now->copy()->addDays(14)->format('Y-m-d'),
                'event_time' => '11:30:00',
                'event_size' => '60 guests',
                'table_count' => 6,
                'venue' => 'Grand Hyatt Manila, BGC',
                'special_requests' => 'Modern white roses with lush eucalyptus.',
                'inspiration_image' => null,
                'status' => 'downpayment_received',
                'pre_cancellation_status' => null,
                'confirmed_at' => $now->copy()->subDays(3)->toISOString(),
                'downpayment_amount' => 6000.00,
                'downpayment_date' => $now->copy()->subDays(3)->format('Y-m-d'),
                'total_quoted' => 12000.00,
                'price_valid_until' => $now->copy()->addDays(10)->format('Y-m-d'),
                'suggested_procurement_date' => $now->copy()->addDays(12)->format('Y-m-d'),
                'preparation_start_date' => $now->copy()->addDays(13)->format('Y-m-d'),
                'preparation_status' => 'scheduled',
                'cancellation_reason' => null,
                'admin_notes' => '50% downpayment verified via GCash. Booking locked.',
                'raw_materials_sum' => 4000.00,
                'multiplier' => 3.0,
                'final_quoted_price' => 12000.00,
                'guest_name' => null,
                'guest_email' => null,
                'guest_phone' => null,
                'guest_address' => null,
                'ai_analysis_data' => null,
                'labor_method' => 'markup',
                'labor_rate' => 3.0,
                'created_at' => $now->copy()->subDays(12)->toISOString(),
                'updated_at' => $now->copy()->subDays(3)->toISOString(),
            ],
            // State 5: confirmed (preparations in progress)
            [
                'key' => 'booking-005',
                'client_ref' => 'client-001',
                'package_ref' => 'package-001',
                'handled_by_ref' => 'user-admin',
                'staff_ref' => 'user-staff',
                'event_type' => 'wedding',
                'event_date' => $now->copy()->addDays(5)->format('Y-m-d'),
                'event_time' => '15:00:00',
                'event_size' => '180 guests',
                'table_count' => 18,
                'venue' => 'The Blue Leaf Filipinas, Paranaque',
                'special_requests' => 'Arch setup by 1:00 PM before ceremony.',
                'inspiration_image' => null,
                'status' => 'confirmed',
                'pre_cancellation_status' => null,
                'confirmed_at' => $now->copy()->subDays(7)->toISOString(),
                'downpayment_amount' => 12500.00,
                'downpayment_date' => $now->copy()->subDays(7)->format('Y-m-d'),
                'total_quoted' => 25000.00,
                'price_valid_until' => $now->copy()->addDays(5)->format('Y-m-d'),
                'suggested_procurement_date' => $now->copy()->addDays(3)->format('Y-m-d'),
                'preparation_start_date' => $now->copy()->addDays(4)->format('Y-m-d'),
                'preparation_status' => 'in_preparation',
                'cancellation_reason' => null,
                'admin_notes' => 'Inventory props reserved. Staff preparation initiated.',
                'raw_materials_sum' => 8333.33,
                'multiplier' => 3.0,
                'final_quoted_price' => 25000.00,
                'guest_name' => null,
                'guest_email' => null,
                'guest_phone' => null,
                'guest_address' => null,
                'ai_analysis_data' => null,
                'labor_method' => 'markup',
                'labor_rate' => 3.0,
                'created_at' => $now->copy()->subDays(15)->toISOString(),
                'updated_at' => $now->copy()->subDays(1)->toISOString(),
            ],
            // State 6: event_completed / pending_return
            [
                'key' => 'booking-006',
                'client_ref' => 'client-002',
                'package_ref' => 'package-002',
                'handled_by_ref' => 'user-admin',
                'staff_ref' => 'user-staff',
                'event_type' => 'debut',
                'event_date' => $now->copy()->subDays(1)->format('Y-m-d'),
                'event_time' => '19:00:00',
                'event_size' => '100 guests',
                'table_count' => 10,
                'venue' => 'Oasis Manila, San Juan',
                'special_requests' => 'Teardown scheduled 11:00 PM.',
                'status' => 'pending_return',
                'pre_cancellation_status' => null,
                'confirmed_at' => $now->copy()->subDays(14)->toISOString(),
                'downpayment_amount' => 7500.00,
                'downpayment_date' => $now->copy()->subDays(14)->format('Y-m-d'),
                'total_quoted' => 15000.00,
                'price_valid_until' => $now->copy()->subDays(1)->format('Y-m-d'),
                'suggested_procurement_date' => $now->copy()->subDays(3)->format('Y-m-d'),
                'preparation_start_date' => $now->copy()->subDays(2)->format('Y-m-d'),
                'preparation_status' => 'ready',
                'cancellation_reason' => null,
                'admin_notes' => 'Event completed yesterday. Reusable props awaiting physical return inspection.',
                'raw_materials_sum' => 5000.00,
                'multiplier' => 3.0,
                'final_quoted_price' => 15000.00,
                'guest_name' => null,
                'guest_email' => null,
                'guest_phone' => null,
                'guest_address' => null,
                'ai_analysis_data' => null,
                'labor_method' => 'markup',
                'labor_rate' => 3.0,
                'created_at' => $now->copy()->subDays(25)->toISOString(),
                'updated_at' => $now->copy()->subDays(1)->toISOString(),
            ],
            // State 7: pending_resolution (Damaged/Lost props during return)
            [
                'key' => 'booking-007',
                'client_ref' => 'client-003',
                'package_ref' => 'package-001',
                'handled_by_ref' => 'user-admin',
                'staff_ref' => 'user-staff',
                'event_type' => 'wedding',
                'event_date' => $now->copy()->subDays(3)->format('Y-m-d'),
                'event_time' => '16:00:00',
                'event_size' => '120 guests',
                'table_count' => 12,
                'venue' => 'Sofitel Philippine Plaza, Pasay',
                'special_requests' => 'Outdoor garden setup.',
                'status' => 'pending_resolution',
                'pre_cancellation_status' => null,
                'confirmed_at' => $now->copy()->subDays(20)->toISOString(),
                'downpayment_amount' => 12500.00,
                'downpayment_date' => $now->copy()->subDays(20)->format('Y-m-d'),
                'total_quoted' => 25000.00,
                'price_valid_until' => $now->copy()->subDays(3)->format('Y-m-d'),
                'suggested_procurement_date' => $now->copy()->subDays(5)->format('Y-m-d'),
                'preparation_start_date' => $now->copy()->subDays(4)->format('Y-m-d'),
                'preparation_status' => 'ready',
                'cancellation_reason' => null,
                'admin_notes' => 'Return inspected. 1 Tall Glass Vase broken and 1 Metal Stand missing. Assessment pending settlement.',
                'raw_materials_sum' => 8333.33,
                'multiplier' => 3.0,
                'final_quoted_price' => 25000.00,
                'guest_name' => null,
                'guest_email' => null,
                'guest_phone' => null,
                'guest_address' => null,
                'ai_analysis_data' => null,
                'labor_method' => 'markup',
                'labor_rate' => 3.0,
                'created_at' => $now->copy()->subDays(35)->toISOString(),
                'updated_at' => $now->copy()->subDays(2)->toISOString(),
            ],
            // State 8: completed (Full return completed, balance settled)
            [
                'key' => 'booking-008',
                'client_ref' => 'client-004',
                'package_ref' => 'package-003',
                'handled_by_ref' => 'user-admin',
                'staff_ref' => 'user-staff',
                'event_type' => 'corporate',
                'event_date' => $now->copy()->subDays(8)->format('Y-m-d'),
                'event_time' => '13:00:00',
                'event_size' => '80 guests',
                'table_count' => 8,
                'venue' => 'Makati Shangri-La, Makati City',
                'special_requests' => 'VIP tables centerpiece installation.',
                'status' => 'completed',
                'pre_cancellation_status' => null,
                'confirmed_at' => $now->copy()->subDays(30)->toISOString(),
                'downpayment_amount' => 6000.00,
                'downpayment_date' => $now->copy()->subDays(30)->format('Y-m-d'),
                'total_quoted' => 12000.00,
                'price_valid_until' => $now->copy()->subDays(8)->format('Y-m-d'),
                'suggested_procurement_date' => $now->copy()->subDays(10)->format('Y-m-d'),
                'preparation_start_date' => $now->copy()->subDays(9)->format('Y-m-d'),
                'preparation_status' => 'ready',
                'cancellation_reason' => null,
                'admin_notes' => 'Event completed, all 6 vases returned in good condition. Final payment settled.',
                'raw_materials_sum' => 4000.00,
                'multiplier' => 3.0,
                'final_quoted_price' => 12000.00,
                'guest_name' => null,
                'guest_email' => null,
                'guest_phone' => null,
                'guest_address' => null,
                'ai_analysis_data' => null,
                'labor_method' => 'markup',
                'labor_rate' => 3.0,
                'created_at' => $now->copy()->subDays(40)->toISOString(),
                'updated_at' => $now->copy()->subDays(7)->toISOString(),
            ],
        ];
        $this->writeJson($tempDir . '/data/bookings.json', $bookings);
        $entities['bookings'] = count($bookings);

        // 8. Booking Items (linked only to real inventory items with consistent quantities)
        $bookingItems = [
            // booking-001 items
            ['key' => 'b-item-001', 'booking_ref' => 'booking-001', 'inventory_item_ref' => 'inventory-001', 'item_name' => 'Red Roses', 'quantity' => 40.00, 'quoted_unit_price' => 45.00, 'ai_recommended_price' => 45.00, 'is_ai_suggested' => false, 'confirmed_at' => null, 'procurement_status' => 'pending', 'suggested_order_date' => null, 'suggested_delivery_date' => null, 'notes' => 'Arch accent florals'],
            ['key' => 'b-item-002', 'booking_ref' => 'booking-001', 'inventory_item_ref' => 'inventory-009', 'item_name' => 'Arch Frame (Metal)', 'quantity' => 1.00, 'quoted_unit_price' => 2500.00, 'ai_recommended_price' => 2500.00, 'is_ai_suggested' => false, 'confirmed_at' => null, 'procurement_status' => 'confirmed', 'suggested_order_date' => null, 'suggested_delivery_date' => null, 'notes' => 'Ceremony centerpiece structure'],
            // booking-002 items
            ['key' => 'b-item-003', 'booking_ref' => 'booking-002', 'inventory_item_ref' => 'inventory-003', 'item_name' => 'Pink Roses', 'quantity' => 24.00, 'quoted_unit_price' => 48.00, 'ai_recommended_price' => 48.00, 'is_ai_suggested' => true, 'confirmed_at' => null, 'procurement_status' => 'pending', 'suggested_order_date' => $now->copy()->addDays(20)->format('Y-m-d'), 'suggested_delivery_date' => $now->copy()->addDays(27)->format('Y-m-d'), 'notes' => 'AI recommended pastel roses'],
            ['key' => 'b-item-004', 'booking_ref' => 'booking-002', 'inventory_item_ref' => 'inventory-007', 'item_name' => 'Tall Glass Vases', 'quantity' => 6.00, 'quoted_unit_price' => 250.00, 'ai_recommended_price' => 250.00, 'is_ai_suggested' => false, 'confirmed_at' => null, 'procurement_status' => 'confirmed', 'suggested_order_date' => null, 'suggested_delivery_date' => null, 'notes' => 'Table props'],
            // booking-003 items
            ['key' => 'b-item-005', 'booking_ref' => 'booking-003', 'inventory_item_ref' => 'inventory-002', 'item_name' => 'White Roses', 'quantity' => 30.00, 'quoted_unit_price' => 54.00, 'ai_recommended_price' => 54.00, 'is_ai_suggested' => false, 'confirmed_at' => null, 'procurement_status' => 'pending', 'suggested_order_date' => null, 'suggested_delivery_date' => null, 'notes' => 'Banquet arrangements'],
            ['key' => 'b-item-006', 'booking_ref' => 'booking-003', 'inventory_item_ref' => 'inventory-007', 'item_name' => 'Tall Glass Vases', 'quantity' => 6.00, 'quoted_unit_price' => 250.00, 'ai_recommended_price' => 250.00, 'is_ai_suggested' => false, 'confirmed_at' => null, 'procurement_status' => 'confirmed', 'suggested_order_date' => null, 'suggested_delivery_date' => null, 'notes' => 'Prop rental'],
            // booking-004 items
            ['key' => 'b-item-007', 'booking_ref' => 'booking-004', 'inventory_item_ref' => 'inventory-002', 'item_name' => 'White Roses', 'quantity' => 30.00, 'quoted_unit_price' => 54.00, 'ai_recommended_price' => 54.00, 'is_ai_suggested' => false, 'confirmed_at' => $now->copy()->subDays(3)->toISOString(), 'procurement_status' => 'confirmed', 'suggested_order_date' => null, 'suggested_delivery_date' => null, 'notes' => 'Stock reserved'],
            ['key' => 'b-item-008', 'booking_ref' => 'booking-004', 'inventory_item_ref' => 'inventory-007', 'item_name' => 'Tall Glass Vases', 'quantity' => 6.00, 'quoted_unit_price' => 250.00, 'ai_recommended_price' => 250.00, 'is_ai_suggested' => false, 'confirmed_at' => $now->copy()->subDays(3)->toISOString(), 'procurement_status' => 'confirmed', 'suggested_order_date' => null, 'suggested_delivery_date' => null, 'notes' => 'Prop reserved'],
            // booking-005 items
            ['key' => 'b-item-009', 'booking_ref' => 'booking-005', 'inventory_item_ref' => 'inventory-001', 'item_name' => 'Red Roses', 'quantity' => 40.00, 'quoted_unit_price' => 45.00, 'ai_recommended_price' => 45.00, 'is_ai_suggested' => false, 'confirmed_at' => $now->copy()->subDays(7)->toISOString(), 'procurement_status' => 'ready', 'suggested_order_date' => null, 'suggested_delivery_date' => null, 'notes' => 'Prepared in cooler'],
            ['key' => 'b-item-010', 'booking_ref' => 'booking-005', 'inventory_item_ref' => 'inventory-009', 'item_name' => 'Arch Frame (Metal)', 'quantity' => 1.00, 'quoted_unit_price' => 2500.00, 'ai_recommended_price' => 2500.00, 'is_ai_suggested' => false, 'confirmed_at' => $now->copy()->subDays(7)->toISOString(), 'procurement_status' => 'ready', 'suggested_order_date' => null, 'suggested_delivery_date' => null, 'notes' => 'Cleaned and packed'],
            // booking-006 items
            ['key' => 'b-item-011', 'booking_ref' => 'booking-006', 'inventory_item_ref' => 'inventory-003', 'item_name' => 'Pink Roses', 'quantity' => 24.00, 'quoted_unit_price' => 48.00, 'ai_recommended_price' => 48.00, 'is_ai_suggested' => false, 'confirmed_at' => $now->copy()->subDays(14)->toISOString(), 'procurement_status' => 'ready', 'suggested_order_date' => null, 'suggested_delivery_date' => null, 'notes' => 'Consumed during event'],
            ['key' => 'b-item-012', 'booking_ref' => 'booking-006', 'inventory_item_ref' => 'inventory-007', 'item_name' => 'Tall Glass Vases', 'quantity' => 6.00, 'quoted_unit_price' => 250.00, 'ai_recommended_price' => 250.00, 'is_ai_suggested' => false, 'confirmed_at' => $now->copy()->subDays(14)->toISOString(), 'procurement_status' => 'ready', 'suggested_order_date' => null, 'suggested_delivery_date' => null, 'notes' => 'Dispatched reusable prop'],
            // booking-007 items
            ['key' => 'b-item-013', 'booking_ref' => 'booking-007', 'inventory_item_ref' => 'inventory-007', 'item_name' => 'Tall Glass Vases', 'quantity' => 4.00, 'quoted_unit_price' => 250.00, 'ai_recommended_price' => 250.00, 'is_ai_suggested' => false, 'confirmed_at' => $now->copy()->subDays(20)->toISOString(), 'procurement_status' => 'ready', 'suggested_order_date' => null, 'suggested_delivery_date' => null, 'notes' => 'Dispatched prop'],
            ['key' => 'b-item-014', 'booking_ref' => 'booking-007', 'inventory_item_ref' => 'inventory-008', 'item_name' => 'Metal Floral Stand', 'quantity' => 2.00, 'quoted_unit_price' => 800.00, 'ai_recommended_price' => 800.00, 'is_ai_suggested' => false, 'confirmed_at' => $now->copy()->subDays(20)->toISOString(), 'procurement_status' => 'ready', 'suggested_order_date' => null, 'suggested_delivery_date' => null, 'notes' => 'Dispatched prop'],
            // booking-008 items
            ['key' => 'b-item-015', 'booking_ref' => 'booking-008', 'inventory_item_ref' => 'inventory-007', 'item_name' => 'Tall Glass Vases', 'quantity' => 6.00, 'quoted_unit_price' => 250.00, 'ai_recommended_price' => 250.00, 'is_ai_suggested' => false, 'confirmed_at' => $now->copy()->subDays(30)->toISOString(), 'procurement_status' => 'ready', 'suggested_order_date' => null, 'suggested_delivery_date' => null, 'notes' => 'Returned prop'],
        ];
        $this->writeJson($tempDir . '/data/booking_items.json', $bookingItems);
        $entities['booking_items'] = count($bookingItems);

        // 9. AI Analysis Results (synthetic demo only)
        $aiAnalyses = [
            [
                'key' => 'ai-001',
                'booking_ref' => 'booking-002',
                'raw_gemini_response' => json_encode([
                    'identified_elements' => ['pastel pink roses', 'white baby breath', 'clear glass cylinders'],
                    'color_palette' => ['#FAD2E1', '#FFFFFF', '#D8E2DC'],
                    'confidence_score' => 0.95,
                    'is_synthetic_demo' => true,
                ]),
                'suggested_materials' => [
                    ['name' => 'Pink Roses', 'quantity' => 24, 'unit' => 'stems'],
                    ['name' => 'White Roses', 'quantity' => 24, 'unit' => 'stems'],
                    ['name' => "Baby's Breath", 'quantity' => 10, 'unit' => 'bunches'],
                ],
                'analyzed_at' => $now->copy()->subDays(5)->toISOString(),
                'created_at' => $now->copy()->subDays(5)->toISOString(),
                'updated_at' => $now->copy()->subDays(5)->toISOString(),
            ],
        ];
        $this->writeJson($tempDir . '/data/ai_analysis_results.json', $aiAnalyses);
        $entities['ai_analysis_results'] = count($aiAnalyses);

        // 10. Quotations
        $quotations = [
            // booking-002: quotation issued
            [
                'key' => 'quotation-001',
                'booking_ref' => 'booking-002',
                'issued_by_ref' => 'user-admin',
                'suggested_florals' => ['Pink Roses', 'White Roses', "Baby's Breath"],
                'recommended_price' => 16500.00,
                'status' => 'issued',
                'valid_until' => $now->copy()->addDays(14)->format('Y-m-d'),
                'version' => 1,
                'raw_materials_sum' => 5500.00,
                'multiplier' => 3.0,
                'labor_method' => 'markup',
                'labor_rate' => 3.0,
                'labor_amount' => 11000.00,
                'final_quoted_price' => 16500.00,
                'downpayment_percentage' => 50.0,
                'items_snapshot' => [
                    ['name' => 'Pink Roses', 'qty' => 24, 'price' => 48.0],
                    ['name' => 'Tall Glass Vases', 'qty' => 6, 'price' => 250.0],
                ],
                'is_tentative' => false,
                'reconfirmed_at' => null,
                'created_at' => $now->copy()->subDays(5)->toISOString(),
                'updated_at' => $now->copy()->subDays(5)->toISOString(),
            ],
            // booking-003: quotation accepted
            [
                'key' => 'quotation-002',
                'booking_ref' => 'booking-003',
                'issued_by_ref' => 'user-admin',
                'suggested_florals' => ['White Roses', 'Tall Glass Vases'],
                'recommended_price' => 12000.00,
                'status' => 'accepted',
                'valid_until' => $now->copy()->addDays(10)->format('Y-m-d'),
                'version' => 1,
                'raw_materials_sum' => 4000.00,
                'multiplier' => 3.0,
                'labor_method' => 'markup',
                'labor_rate' => 3.0,
                'labor_amount' => 8000.00,
                'final_quoted_price' => 12000.00,
                'downpayment_percentage' => 50.0,
                'items_snapshot' => [
                    ['name' => 'White Roses', 'qty' => 30, 'price' => 54.0],
                ],
                'is_tentative' => false,
                'reconfirmed_at' => null,
                'created_at' => $now->copy()->subDays(10)->toISOString(),
                'updated_at' => $now->copy()->subDays(4)->toISOString(),
            ],
            // booking-004: quotation accepted
            [
                'key' => 'quotation-003',
                'booking_ref' => 'booking-004',
                'issued_by_ref' => 'user-admin',
                'suggested_florals' => ['White Roses', 'Tall Glass Vases'],
                'recommended_price' => 12000.00,
                'status' => 'accepted',
                'valid_until' => $now->copy()->addDays(10)->format('Y-m-d'),
                'version' => 1,
                'raw_materials_sum' => 4000.00,
                'multiplier' => 3.0,
                'labor_method' => 'markup',
                'labor_rate' => 3.0,
                'labor_amount' => 8000.00,
                'final_quoted_price' => 12000.00,
                'downpayment_percentage' => 50.0,
                'items_snapshot' => [],
                'is_tentative' => false,
                'reconfirmed_at' => null,
                'created_at' => $now->copy()->subDays(12)->toISOString(),
                'updated_at' => $now->copy()->subDays(3)->toISOString(),
            ],
            // booking-008: quotation accepted
            [
                'key' => 'quotation-004',
                'booking_ref' => 'booking-008',
                'issued_by_ref' => 'user-admin',
                'suggested_florals' => ['White Roses', 'Tall Glass Vases'],
                'recommended_price' => 12000.00,
                'status' => 'accepted',
                'valid_until' => $now->copy()->subDays(8)->format('Y-m-d'),
                'version' => 1,
                'raw_materials_sum' => 4000.00,
                'multiplier' => 3.0,
                'labor_method' => 'markup',
                'labor_rate' => 3.0,
                'labor_amount' => 8000.00,
                'final_quoted_price' => 12000.00,
                'downpayment_percentage' => 50.0,
                'items_snapshot' => [],
                'is_tentative' => false,
                'reconfirmed_at' => null,
                'created_at' => $now->copy()->subDays(40)->toISOString(),
                'updated_at' => $now->copy()->subDays(30)->toISOString(),
            ],
        ];
        $this->writeJson($tempDir . '/data/quotations.json', $quotations);
        $entities['quotations'] = count($quotations);

        // 11. Quotation History
        $quotationHistory = [
            [
                'key' => 'q-hist-001',
                'booking_ref' => 'booking-002',
                'changed_by_ref' => 'user-admin',
                'field_changed' => 'final_quoted_price',
                'old_value' => '18000.00',
                'new_value' => '16500.00',
                'reason' => 'Applied seasonal debut promotional discount.',
                'created_at' => $now->copy()->subDays(5)->toISOString(),
                'updated_at' => $now->copy()->subDays(5)->toISOString(),
            ],
        ];
        $this->writeJson($tempDir . '/data/quotation_history.json', $quotationHistory);
        $entities['quotation_history'] = count($quotationHistory);

        // 12. Payments (pending, verified downpayment, and fully paid examples)
        $payments = [
            // booking-003: Pending payment submission
            [
                'key' => 'payment-001',
                'booking_ref' => 'booking-003',
                'quotation_ref' => 'quotation-002',
                'verified_by_ref' => null,
                'recorded_by_ref' => 'user-admin',
                'amount' => 6000.00,
                'payment_type' => 'downpayment',
                'payment_option' => 'gcash',
                'amount_paid' => 6000.00,
                'remaining_balance' => 6000.00,
                'verified_at' => null,
                'reference_number' => 'GCASH-DEMO-001',
                'status' => 'pending',
                'created_at' => $now->copy()->subDays(1)->toISOString(),
                'updated_at' => $now->copy()->subDays(1)->toISOString(),
            ],
            // booking-004: Verified downpayment
            [
                'key' => 'payment-002',
                'booking_ref' => 'booking-004',
                'quotation_ref' => 'quotation-003',
                'verified_by_ref' => 'user-admin',
                'recorded_by_ref' => 'user-admin',
                'amount' => 6000.00,
                'payment_type' => 'downpayment',
                'payment_option' => 'gcash',
                'amount_paid' => 6000.00,
                'remaining_balance' => 6000.00,
                'verified_at' => $now->copy()->subDays(3)->toISOString(),
                'reference_number' => 'GCASH-DEMO-002',
                'status' => 'verified',
                'created_at' => $now->copy()->subDays(3)->toISOString(),
                'updated_at' => $now->copy()->subDays(3)->toISOString(),
            ],
            // booking-008: Fully paid (downpayment + final payment)
            [
                'key' => 'payment-003',
                'booking_ref' => 'booking-008',
                'quotation_ref' => 'quotation-004',
                'verified_by_ref' => 'user-admin',
                'recorded_by_ref' => 'user-admin',
                'amount' => 6000.00,
                'payment_type' => 'downpayment',
                'payment_option' => 'bank_transfer',
                'amount_paid' => 6000.00,
                'remaining_balance' => 6000.00,
                'verified_at' => $now->copy()->subDays(30)->toISOString(),
                'reference_number' => 'BDO-DEMO-001',
                'status' => 'verified',
                'created_at' => $now->copy()->subDays(30)->toISOString(),
                'updated_at' => $now->copy()->subDays(30)->toISOString(),
            ],
            [
                'key' => 'payment-004',
                'booking_ref' => 'booking-008',
                'quotation_ref' => 'quotation-004',
                'verified_by_ref' => 'user-admin',
                'recorded_by_ref' => 'user-admin',
                'amount' => 6000.00,
                'payment_type' => 'final_payment',
                'payment_option' => 'cash',
                'amount_paid' => 6000.00,
                'remaining_balance' => 0.00,
                'verified_at' => $now->copy()->subDays(7)->toISOString(),
                'reference_number' => 'CASH-DEMO-002',
                'status' => 'verified',
                'created_at' => $now->copy()->subDays(7)->toISOString(),
                'updated_at' => $now->copy()->subDays(7)->toISOString(),
            ],
        ];
        $this->writeJson($tempDir . '/data/payments.json', $payments);
        $entities['payments'] = count($payments);

        // 13. Inventory Transactions (coherent with booking states)
        $transactions = [
            // Initial procurements
            ['key' => 'tx-001', 'inventory_item_ref' => 'inventory-001', 'booking_ref' => null, 'reference_transaction_ref' => null, 'performed_by_ref' => 'user-admin', 'quantity_change' => 200.00, 'transaction_type' => 'procurement', 'reason' => 'Fresh stock batch received', 'created_at' => $now->copy()->subDays(60)->toISOString(), 'updated_at' => $now->copy()->subDays(60)->toISOString()],
            ['key' => 'tx-002', 'inventory_item_ref' => 'inventory-007', 'booking_ref' => null, 'reference_transaction_ref' => null, 'performed_by_ref' => 'user-admin', 'quantity_change' => 30.00, 'transaction_type' => 'procurement', 'reason' => 'Vases prop acquisition', 'created_at' => $now->copy()->subDays(60)->toISOString(), 'updated_at' => $now->copy()->subDays(60)->toISOString()],
            // Dispatch for booking-006 (pending return)
            ['key' => 'tx-003', 'inventory_item_ref' => 'inventory-007', 'booking_ref' => 'booking-006', 'reference_transaction_ref' => null, 'performed_by_ref' => 'user-staff', 'quantity_change' => -6.00, 'transaction_type' => 'dispatch', 'reason' => 'Dispatched to venue for booking 006', 'created_at' => $now->copy()->subDays(1)->toISOString(), 'updated_at' => $now->copy()->subDays(1)->toISOString()],
            // Dispatch for booking-007
            ['key' => 'tx-004', 'inventory_item_ref' => 'inventory-007', 'booking_ref' => 'booking-007', 'reference_transaction_ref' => null, 'performed_by_ref' => 'user-staff', 'quantity_change' => -4.00, 'transaction_type' => 'dispatch', 'reason' => 'Dispatched to Sofitel for booking 007', 'created_at' => $now->copy()->subDays(3)->toISOString(), 'updated_at' => $now->copy()->subDays(3)->toISOString()],
            ['key' => 'tx-005', 'inventory_item_ref' => 'inventory-008', 'booking_ref' => 'booking-007', 'reference_transaction_ref' => null, 'performed_by_ref' => 'user-staff', 'quantity_change' => -2.00, 'transaction_type' => 'dispatch', 'reason' => 'Dispatched floral stands for booking 007', 'created_at' => $now->copy()->subDays(3)->toISOString(), 'updated_at' => $now->copy()->subDays(3)->toISOString()],
            // Dispatch and return for completed booking-008
            ['key' => 'tx-006', 'inventory_item_ref' => 'inventory-007', 'booking_ref' => 'booking-008', 'reference_transaction_ref' => null, 'performed_by_ref' => 'user-staff', 'quantity_change' => -6.00, 'transaction_type' => 'dispatch', 'reason' => 'Dispatched to Makati Shangri-La', 'created_at' => $now->copy()->subDays(9)->toISOString(), 'updated_at' => $now->copy()->subDays(9)->toISOString()],
            ['key' => 'tx-007', 'inventory_item_ref' => 'inventory-007', 'booking_ref' => 'booking-008', 'reference_transaction_ref' => 'tx-006', 'performed_by_ref' => 'user-staff', 'quantity_change' => 6.00, 'transaction_type' => 'return', 'reason' => 'All 6 vases returned in good condition', 'created_at' => $now->copy()->subDays(7)->toISOString(), 'updated_at' => $now->copy()->subDays(7)->toISOString()],
        ];
        $this->writeJson($tempDir . '/data/inventory_transactions.json', $transactions);
        $entities['inventory_transactions'] = count($transactions);

        // 14. Staff Checklist Items (completed and incomplete examples)
        $checklists = [
            ['key' => 'chk-001', 'booking_ref' => 'booking-005', 'completed_by_ref' => 'user-staff', 'checklist_key' => 'materials_prepared', 'title' => 'Prepare Materials & Stems', 'is_completed' => true, 'notes' => 'Roses conditioned in cold water.', 'completed_at' => $now->copy()->subDays(1)->toISOString(), 'created_at' => $now->copy()->subDays(2)->toISOString(), 'updated_at' => $now->copy()->subDays(1)->toISOString()],
            ['key' => 'chk-002', 'booking_ref' => 'booking-005', 'completed_by_ref' => null, 'checklist_key' => 'vehicle_loaded', 'title' => 'Load Transport Vehicle', 'is_completed' => false, 'notes' => 'Scheduled morning of event date.', 'completed_at' => null, 'created_at' => $now->copy()->subDays(2)->toISOString(), 'updated_at' => $now->copy()->subDays(2)->toISOString()],
            ['key' => 'chk-003', 'booking_ref' => 'booking-006', 'completed_by_ref' => 'user-staff', 'checklist_key' => 'on_site_setup', 'title' => 'On-site Installation', 'is_completed' => true, 'notes' => 'Completed on time before debut.', 'completed_at' => $now->copy()->subDays(1)->toISOString(), 'created_at' => $now->copy()->subDays(2)->toISOString(), 'updated_at' => $now->copy()->subDays(1)->toISOString()],
        ];
        $this->writeJson($tempDir . '/data/staff_checklist_items.json', $checklists);
        $entities['staff_checklist_items'] = count($checklists);

        // 15. Booking Messages
        $messages = [
            [
                'key' => 'msg-001',
                'booking_ref' => 'booking-002',
                'sender_type' => 'client',
                'sender_ref' => 'client-002',
                'message' => 'Hello! Could we request extra baby breath fillers for the stage arrangements?',
                'visibility' => 'all',
                'related_quotation_version' => 1,
                'attachment_name' => null,
                'attachment_category' => null,
                'mime_type' => null,
                'file_size' => null,
                'created_at' => $now->copy()->subDays(4)->toISOString(),
                'updated_at' => $now->copy()->subDays(4)->toISOString(),
            ],
            [
                'key' => 'msg-002',
                'booking_ref' => 'booking-002',
                'sender_type' => 'admin',
                'sender_ref' => 'user-admin',
                'message' => 'Certainly, Maria! We have updated the quotation v1 items to include extra fillers.',
                'visibility' => 'all',
                'related_quotation_version' => 1,
                'attachment_name' => null,
                'attachment_category' => null,
                'mime_type' => null,
                'file_size' => null,
                'created_at' => $now->copy()->subDays(3)->toISOString(),
                'updated_at' => $now->copy()->subDays(3)->toISOString(),
            ],
        ];
        $this->writeJson($tempDir . '/data/booking_messages.json', $messages);
        $entities['booking_messages'] = count($messages);

        // 16. Presentations
        $presentations = [
            [
                'key' => 'pres-001',
                'booking_ref' => 'booking-002',
                'sent_by_ref' => 'user-admin',
                'version' => 'v1',
                'file_name' => 'Debut-Pastel-Suite-Proposal-v1.pdf',
                'file_path' => 'bookings/proposals/demo-proposal-v1.pdf',
                'sent_at' => $now->copy()->subDays(5)->toISOString(),
                'status' => 'sent',
                'approval_status' => 'pending',
                'feedback_text' => null,
                'created_at' => $now->copy()->subDays(5)->toISOString(),
                'updated_at' => $now->copy()->subDays(5)->toISOString(),
            ],
        ];
        $this->writeJson($tempDir . '/data/presentations.json', $presentations);
        $entities['presentations'] = count($presentations);

        // 17. Returns (pending, damaged, good cases)
        $returns = [
            // Return case 1: pending return (booking-006)
            [
                'key' => 'return-001',
                'booking_ref' => 'booking-006',
                'inspected_by_ref' => null,
                'return_date' => null,
                'status' => 'Pending',
                'total_damage_charge' => 0.00,
                'notes' => 'Awaiting arrival of props from venue teardown.',
                'created_at' => $now->copy()->subDays(1)->toISOString(),
                'updated_at' => $now->copy()->subDays(1)->toISOString(),
            ],
            // Return case 2: damaged & lost return with charge decisions (booking-007)
            [
                'key' => 'return-002',
                'booking_ref' => 'booking-007',
                'inspected_by_ref' => 'user-admin',
                'return_date' => $now->copy()->subDays(2)->format('Y-m-d'),
                'status' => 'Partially Returned',
                'total_damage_charge' => 1050.00,
                'notes' => '1 vase cracked during banquet, 1 metal stand unaccounted for.',
                'created_at' => $now->copy()->subDays(2)->toISOString(),
                'updated_at' => $now->copy()->subDays(2)->toISOString(),
            ],
            // Return case 3: fully good return (booking-008)
            [
                'key' => 'return-003',
                'booking_ref' => 'booking-008',
                'inspected_by_ref' => 'user-staff',
                'return_date' => $now->copy()->subDays(7)->format('Y-m-d'),
                'status' => 'Completed',
                'total_damage_charge' => 0.00,
                'notes' => 'All 6 vases returned in perfect condition.',
                'created_at' => $now->copy()->subDays(7)->toISOString(),
                'updated_at' => $now->copy()->subDays(7)->toISOString(),
            ],
        ];
        $this->writeJson($tempDir . '/data/returns.json', $returns);
        $entities['returns'] = count($returns);

        // 18. Return Items
        $returnItems = [
            // return-001 item (pending)
            [
                'key' => 'ret-item-001',
                'return_ref' => 'return-001',
                'inventory_item_ref' => 'inventory-007',
                'charge_decision_by_ref' => null,
                'quantity_returned' => 0.00,
                'quantity_good' => 0.00,
                'quantity_damaged' => 0.00,
                'quantity_lost' => 0.00,
                'condition' => 'pending',
                'final_amount' => 0.00,
                'damage_charge' => 0.00,
                'notes' => '6 Tall Glass Vases awaiting inspection',
                'charge_decision' => 'pending',
                'charge_reason' => null,
                'charge_decision_at' => null,
            ],
            // return-002 items (mixed condition: damaged vase + lost stand)
            [
                'key' => 'ret-item-002',
                'return_ref' => 'return-002',
                'inventory_item_ref' => 'inventory-007', // 4 vases dispatched: 3 good, 1 damaged
                'charge_decision_by_ref' => 'user-admin',
                'quantity_returned' => 4.00,
                'quantity_good' => 3.00,
                'quantity_damaged' => 1.00,
                'quantity_lost' => 0.00,
                'condition' => 'damaged',
                'final_amount' => 250.00,
                'damage_charge' => 250.00,
                'notes' => 'Deep crack along base of 1 cylinder vase.',
                'charge_decision' => 'charge',
                'charge_reason' => 'Item unrepairable and must be retired.',
                'charge_decision_at' => $now->copy()->subDays(2)->toISOString(),
            ],
            [
                'key' => 'ret-item-003',
                'return_ref' => 'return-002',
                'inventory_item_ref' => 'inventory-008', // 2 stands dispatched: 1 good, 1 lost
                'charge_decision_by_ref' => 'user-admin',
                'quantity_returned' => 2.00,
                'quantity_good' => 1.00,
                'quantity_damaged' => 0.00,
                'quantity_lost' => 1.00,
                'condition' => 'lost',
                'final_amount' => 800.00,
                'damage_charge' => 800.00,
                'notes' => '1 floral stand missing from event venue.',
                'charge_decision' => 'pending',
                'charge_reason' => 'Client currently coordinating with venue security.',
                'charge_decision_at' => null,
            ],
            // return-003 item (fully good)
            [
                'key' => 'ret-item-004',
                'return_ref' => 'return-003',
                'inventory_item_ref' => 'inventory-007', // 6 vases dispatched: 6 good
                'charge_decision_by_ref' => 'user-admin',
                'quantity_returned' => 6.00,
                'quantity_good' => 6.00,
                'quantity_damaged' => 0.00,
                'quantity_lost' => 0.00,
                'condition' => 'good',
                'final_amount' => 0.00,
                'damage_charge' => 0.00,
                'notes' => 'Cleaned and returned to warehouse shelves.',
                'charge_decision' => 'no_charge',
                'charge_reason' => 'All items intact.',
                'charge_decision_at' => $now->copy()->subDays(7)->toISOString(),
            ],
        ];
        $this->writeJson($tempDir . '/data/return_items.json', $returnItems);
        $entities['return_items'] = count($returnItems);

        // 19. Return Item Evidences (Metadata only)
        $evidences = [
            [
                'key' => 'ret-ev-001',
                'return_item_ref' => 'ret-item-002',
                'uploaded_by_ref' => 'user-admin',
                'file_name' => 'demo-damaged-vase-inspection.jpg',
                'file_path' => 'returns/evidence/demo-damaged-vase.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 245000,
                'created_at' => $now->copy()->subDays(2)->toISOString(),
                'updated_at' => $now->copy()->subDays(2)->toISOString(),
            ],
        ];
        $this->writeJson($tempDir . '/data/return_item_evidences.json', $evidences);
        $entities['return_item_evidences'] = count($evidences);

        // 20. Admin Alerts
        $alerts = [
            [
                'key' => 'alert-001',
                'booking_ref' => 'booking-001',
                'inventory_item_ref' => null,
                'type' => 'new_booking',
                'title' => 'New Booking Request',
                'message' => 'New booking inquiry received from Juan Dela Cruz.',
                'is_read' => false,
                'created_at' => $now->copy()->subDays(2)->toISOString(),
                'updated_at' => $now->copy()->subDays(2)->toISOString(),
            ],
            [
                'key' => 'alert-002',
                'booking_ref' => null,
                'inventory_item_ref' => 'inventory-004',
                'type' => 'low_stock',
                'title' => 'Critical Inventory Shortage',
                'message' => 'Sunflowers stock is critically low (8 stems remaining vs minimum 30).',
                'is_read' => false,
                'created_at' => $now->copy()->subDays(1)->toISOString(),
                'updated_at' => $now->copy()->subDays(1)->toISOString(),
            ],
        ];
        $this->writeJson($tempDir . '/data/admin_alerts.json', $alerts);
        $entities['admin_alerts'] = count($alerts);

        // 21. Client Notifications
        $notifications = [
            [
                'key' => 'c-notif-001',
                'user_ref' => 'user-admin',
                'booking_ref' => 'booking-002',
                'type' => 'quotation_issued',
                'title' => 'Quotation Ready',
                'message' => 'Your floral design quotation for Debut Celebration is ready for review.',
                'is_read' => false,
                'created_at' => $now->copy()->subDays(5)->toISOString(),
                'updated_at' => $now->copy()->subDays(5)->toISOString(),
            ],
        ];
        $this->writeJson($tempDir . '/data/client_notifications.json', $notifications);
        $entities['client_notifications'] = count($notifications);

        // 22. Audit Logs (Clearly marked demo logs)
        $auditLogs = [
            [
                'key' => 'audit-001',
                'user_ref' => 'user-admin',
                'action' => 'demo_dataset_seeded',
                'module' => 'admin_system_data',
                'event_type' => 'demo_dataset_seeded',
                'details' => ['message' => 'Synthetic demo dataset generated for Raflora testing & UAT'],
                'old_values' => null,
                'new_values' => ['dataset_id' => $datasetId],
                'ip_address' => '127.0.0.1',
                'created_at' => $now->copy()->subMinutes(5)->toISOString(),
                'updated_at' => $now->copy()->subMinutes(5)->toISOString(),
            ],
        ];
        $this->writeJson($tempDir . '/data/audit_logs.json', $auditLogs);
        $entities['audit_logs'] = count($auditLogs);

        // 23. Settings
        $settings = [
            'downpayment_percentage' => Setting::getSetting(Setting::KEY_DOWNPAYMENT_PERCENTAGE, Setting::DEFAULT_DOWNPAYMENT_PERCENTAGE),
            'long_term_booking_threshold_days' => Setting::getLongTermBookingThresholdDays(),
            'price_reconfirmation_threshold_days' => Setting::getPriceReconfirmationThresholdDays(),
        ];
        $this->writeJson($tempDir . '/data/settings.json', $settings);
        $entities['settings'] = count($settings);

        // Build Manifest
        $manifest = [
            'format' => 'raflora-system-data',
            'format_version' => 1,
            'dataset_id' => $datasetId,
            'created_at' => $now->toIso8601String(),
            'application' => 'Raflora Enterprises',
            'data_mode' => 'demo',
            'schema_fingerprint' => SystemDataExporter::computeSchemaFingerprint(),
            'entities' => $entities,
        ];

        $this->writeJson($tempDir . '/manifest.json', $manifest);

        return $manifest;
    }

    protected function writeJson(string $path, mixed $data): void
    {
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}

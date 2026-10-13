<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Models\AiAnalysisResult;
use App\Models\AdminAlert;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use App\Services\GeminiVisionService;
use App\Support\DecorAreaBreakdown;
use App\Support\EventDesignBrief;
use App\Models\InventoryItem;
use App\Models\Presentation;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    /**
     * Display a list of bookings for the authenticated client.
     */
    public function index(): View
    {
        $client = $this->resolveClient();
        $bookings = Booking::where('client_id', $client?->id)->latest()->get();

        return view('client.bookings', [
            'bookings' => $bookings,
        ]);
    }

    /**
     * Show the booking creation form.
     */
    public function create(): View
    {
        $packages = \App\Models\Package::where('is_active', true)->where('is_archived', false)->get();

        return view('client.booking-create', [
            'packages' => $packages,
        ]);
    }

    /**
     * Store a new booking.
     */
    public function store(BookingRequest $request): RedirectResponse
    {
        set_time_limit(120); // Prevent PHP from timing out while waiting for AI requests

        $validated = $request->validated();
        $client = $this->resolveClient();

        $duplicateKey = null;
        if ($client) {
            $duplicateKey = 'client_recent_booking_' . md5($client->id . '|' . $validated['event_date'] . '|' . $validated['venue']);
            if (\Illuminate\Support\Facades\Cache::has($duplicateKey)) {
                $cachedBookingId = \Illuminate\Support\Facades\Cache::get($duplicateKey);
                return redirect()->route('bookings.show', ['booking' => $cachedBookingId])
                    ->with('info', 'Your booking request has already been submitted.');
            }

            $recentBooking = Booking::where('client_id', $client->id)
                ->whereDate('event_date', $validated['event_date'])
                ->where('venue', $validated['venue'])
                ->latest()
                ->first();
            if ($recentBooking && $recentBooking->created_at && $recentBooking->created_at->diffInSeconds(now()) <= 10) {
                return redirect()->route('bookings.show', ['booking' => $recentBooking->id])
                    ->with('info', 'Your booking request has already been submitted.');
            }
        }

        $bookingType = $request->input('booking_type', 'custom_ai');
        $isPresetBooking = $bookingType === 'preset';
        if ($isPresetBooking) {
            $request->files->remove('inspiration_image');
            $request->merge([
                'analysis_token' => null,
                'analysis_temp_path' => null,
                'analysis_data' => null,
                'analysis_nonce' => null,
            ]);
            $validated['analysis_token'] = null;
            $validated['analysis_temp_path'] = null;
            $validated['analysis_data'] = null;
            $validated['analysis_nonce'] = null;
        } else {
            $request->merge(['package_id' => null]);
            $validated['package_id'] = null;
        }
        $analysisToken = $validated['analysis_token'] ?? null;
        $analysisNonce = $validated['analysis_nonce'] ?? null;
        $analysisTempPath = $validated['analysis_temp_path'] ?? null;
        $storedAnalysis = $analysisToken ? self::getStoredAnalysisPayload($analysisToken) : null;
        if ($storedAnalysis && ($storedAnalysis['request_nonce'] ?? null) && $analysisNonce && $storedAnalysis['request_nonce'] !== $analysisNonce) {
            $storedAnalysis = null;
        }
        $analysisUsed = false;

        $inspirationImagePath = null;
        if (!$isPresetBooking && $request->hasFile('inspiration_image')) {
            $imageFile = $request->file('inspiration_image');
            $uploadedHash = @sha1_file($imageFile->getRealPath()) ?: null;
            $inspirationImagePath = $imageFile->store('bookings/inspiration-images', 'local');

            if ($storedAnalysis && isset($storedAnalysis['image_hash'])) {
                if ($storedAnalysis['image_hash'] === $uploadedHash) {
                    $analysisUsed = true;
                } else {
                    $storedAnalysis = null;
                }
            } elseif ($storedAnalysis && isset($storedAnalysis['image_hash'])) {
                $analysisUsed = true;
            } else {
                $storedAnalysis = null;
            }
        } elseif (!$isPresetBooking && $analysisToken && $storedAnalysis) {
            $analysisUsed = true;
        }

        if ($bookingType === 'custom_ai' && (!$analysisUsed || !$analysisToken)) {
            return redirect()->back()
                ->withInput()
                ->with('analysis_error', GeminiVisionService::failureMessageForType('analysis'));
        }

        if ($analysisUsed) {
            try {
                (new GeminiVisionService())->validateAnalysisPayload($storedAnalysis['analysis'] ?? []);
            } catch (\Throwable $e) {
                return redirect()->back()
                    ->withInput()
                    ->with('analysis_error', GeminiVisionService::failureMessageForType(GeminiVisionService::classifyFailure($e)));
            }
        }

        $priceValidUntil = Carbon::now()->addDays(7)->toDateString();
        $suggestedProcurement = Carbon::parse($validated['event_date'])->subDays(7)->toDateString();

        $selectedPackage = null;
        if ($bookingType === 'preset' && !empty($validated['package_id'])) {
            $selectedPackage = \App\Models\Package::find($validated['package_id']);
        }

        $tableCount = $request->input('table_count');
        $guestCount = $request->input('guest_count');

        $eventType = $validated['event_type'];
        if ($eventType === 'other' && !empty($request->input('other_event_type'))) {
            $eventType = trim($request->input('other_event_type'));
        }

        $scaleContext = '';
        if (!empty($tableCount)) {
            $scaleContext .= "Tables: {$tableCount}. ";
        }
        if (!empty($guestCount)) {
            $scaleContext .= "Guest Count (Pax): {$guestCount}. ";
        }
        $scaleContext = trim($scaleContext) ?: "Single order/bouquet delivery - no scale multiplier applied";

        $specialRequests = $validated['special_requests'] ?? '';
        // The design brief only steers Smart AI analysis; a curated package has its own fixed design.
        $designBrief = $isPresetBooking ? null : EventDesignBrief::fromInput($validated);
        $venueSetting = EventDesignBrief::venueSettingFromInput($validated);
        if ($selectedPackage) {
            $booking = Booking::create([
                'client_id' => $client?->id,
                'handled_by' => null,
                'event_type' => $eventType,
                'event_date' => $validated['event_date'],
                'event_time' => $validated['event_time'] ?? null,
                'end_time' => $validated['end_time'] ?? null,
                'event_size' => $guestCount,
                'table_count' => $tableCount,
                'package_id' => $selectedPackage->id,
                'venue' => $validated['venue'],
                'venue_latitude' => $validated['venue_latitude'] ?? null,
                'venue_longitude' => $validated['venue_longitude'] ?? null,
                'venue_setting' => $venueSetting,
                'special_requests' => $specialRequests,
                'inspiration_image' => $inspirationImagePath ?? ($selectedPackage->image_path ?? null),
                'status' => 'pending',
                'total_quoted' => $selectedPackage->price,
                'raw_materials_sum' => $selectedPackage->price,
                'multiplier' => 1.0,
                'final_quoted_price' => $selectedPackage->price,
                'price_valid_until' => $priceValidUntil,
                'suggested_procurement_date' => $suggestedProcurement,
            ]);

            // Sync the physical inventory items from the package to this booking
            $packageItems = $selectedPackage->inventoryItems;
            if ($packageItems->count() > 0) {
                foreach ($packageItems as $item) {
                    $booking->inventoryItems()->attach($item->id, [
                        'quantity' => $item->pivot->quantity,
                        'quoted_unit_price' => $item->unit_cost,
                        'is_ai_suggested' => 0,
                        'confirmed_at' => now(),
                        'procurement_status' => 'pending',
                        'suggested_order_date' => $booking->suggested_procurement_date,
                        'suggested_delivery_date' => null,
                        'notes' => 'From Package: ' . $selectedPackage->title,
                    ]);
                }
                // Also ensure booking_items rows exist for package items for consistent UI rendering
                foreach ($packageItems as $item) {
                    $existing = $booking->bookingItems()->where('inventory_item_id', $item->id)->first();
                    if (! $existing) {
                        BookingItem::create([
                            'booking_id' => $booking->id,
                            'inventory_item_id' => $item->id,
                            'item_name' => $item->name,
                            'quantity' => $item->pivot->quantity,
                            'quoted_unit_price' => $item->unit_cost,
                            'is_ai_suggested' => false,
                            'confirmed_at' => now(),
                            'procurement_status' => 'pending',
                            'suggested_order_date' => $booking->suggested_procurement_date,
                            'suggested_delivery_date' => null,
                            'notes' => 'From Package: ' . $selectedPackage->title,
                        ]);
                    }
                }
                // Expand textual inclusions into individual booking_items as well, parsing quantities
                $inclusions = is_array($selectedPackage->included_items) ? $selectedPackage->included_items : [];
                if (count($inclusions) > 0) {
                    // Build name -> bookingItem map for matched inventory items
                    $normalizedInventory = [];
                    foreach ($booking->bookingItems()->get() as $bi) {
                        $normalizedInventory[strtolower(trim(preg_replace('/[^a-z0-9]+/', ' ', $bi->item_name ?? '')))][] = $bi;
                    }

                    $unmatchedUnits = 0;
                    $unmatchedList = [];

                    foreach ($inclusions as $inc) {
                        $raw = trim((string) $inc);
                        if ($raw === '') continue;

                        $qty = 1;
                        $name = $raw;
                        if (preg_match('/^(\d+)\s*[xX]\s*(.+)$/', $raw, $m)) {
                            $qty = (int) $m[1];
                            $name = trim($m[2]);
                        }

                        $norm = strtolower(trim(preg_replace('/[^a-z0-9]+/', ' ', $name)));
                        if (!empty($normalizedInventory[$norm])) {
                            // Update existing booking item quantity if needed
                            $bi = $normalizedInventory[$norm][0];
                            if ($bi->quantity != $qty) {
                                $bi->quantity = $qty;
                                $bi->save();
                            }
                            continue;
                        }

                        $unmatchedUnits += max(1, $qty);
                        $unmatchedList[] = ['name' => $name, 'qty' => max(1, $qty)];
                    }

                    // Determine remaining price after matched items
                    $matchedTotal = $booking->bookingItems()->get()->reduce(fn($carry, $b) => $carry + (($b->quoted_unit_price ?? 0) * ($b->quantity ?? 1)), 0);
                    $remainingPrice = max(0, (float) $selectedPackage->price - $matchedTotal);
                    $perUnitPrice = $unmatchedUnits > 0 ? round($remainingPrice / $unmatchedUnits, 2) : 0;

                    foreach ($unmatchedList as $u) {
                        BookingItem::create([
                            'booking_id' => $booking->id,
                            'inventory_item_id' => null,
                            'item_name' => $u['name'],
                            'quantity' => $u['qty'],
                            'quoted_unit_price' => $perUnitPrice,
                            'is_ai_suggested' => false,
                            'confirmed_at' => now(),
                            'procurement_status' => 'pending',
                            'suggested_order_date' => $booking->suggested_procurement_date,
                            'suggested_delivery_date' => null,
                            'notes' => 'From Package inclusions: ' . $selectedPackage->title,
                        ]);
                    }
                }
            } else {
                // Fallback: package has no linked inventory items, use included_items JSON or package title
                $inclusions = is_array($selectedPackage->included_items) ? $selectedPackage->included_items : [];
                if (count($inclusions) > 0) {
                    $perItemPrice = max(0, round(($selectedPackage->price / max(1, count($inclusions))), 2));
                    foreach ($inclusions as $inc) {
                        $name = trim((string) $inc);
                        if ($name === '') continue;
                        BookingItem::create([
                            'booking_id' => $booking->id,
                            'inventory_item_id' => null,
                            'item_name' => $name,
                            'quantity' => 1,
                            'quoted_unit_price' => $perItemPrice,
                            'is_ai_suggested' => false,
                            'confirmed_at' => now(),
                            'procurement_status' => 'pending',
                            'suggested_order_date' => $booking->suggested_procurement_date,
                            'suggested_delivery_date' => null,
                            'notes' => 'From Package (fallback): ' . $selectedPackage->title,
                        ]);
                    }
                } else {
                    // No inclusions listed; create a single summary booking item
                    BookingItem::create([
                        'booking_id' => $booking->id,
                        'inventory_item_id' => null,
                        'item_name' => $selectedPackage->title,
                        'quantity' => 1,
                        'quoted_unit_price' => $selectedPackage->price,
                        'is_ai_suggested' => false,
                        'procurement_status' => 'pending',
                        'suggested_order_date' => $booking->suggested_procurement_date,
                        'suggested_delivery_date' => null,
                        'notes' => 'From Package (fallback): ' . $selectedPackage->title,
                    ]);
                }
            }

            if ($duplicateKey && $booking) {
                \Illuminate\Support\Facades\Cache::put($duplicateKey, $booking->id, 10);
            }

            return redirect()->route('bookings.show', ['booking' => $booking->id])
                ->with('success', 'Package booking request submitted successfully!');
        }

        // Attempt real Gemini vision analysis; fall back to simulated if it fails
        try {
            if (empty($inspirationImagePath)) {
                throw new \Exception('No inspiration image available for analysis.');
            }

            $booking = null;
            $materials = [];
            $result = null;

            DB::transaction(function () use ($client, $validated, $eventType, $tableCount, $inspirationImagePath, $priceValidUntil, $suggestedProcurement, $specialRequests, $analysisUsed, $storedAnalysis, $scaleContext, $guestCount, $designBrief, $venueSetting, &$booking, &$materials, &$result) {
                $bookingData = [
                    'client_id' => $client?->id,
                    'handled_by' => null,
                    'event_type' => $eventType,
                    'event_date' => $validated['event_date'],
                    'event_time' => $validated['event_time'] ?? null,
                    'end_time' => $validated['end_time'] ?? null,
                    'event_size' => $guestCount,
                    'table_count' => $tableCount,
                    'venue' => $validated['venue'],
                    'venue_latitude' => $validated['venue_latitude'] ?? null,
                    'venue_longitude' => $validated['venue_longitude'] ?? null,
                    'venue_setting' => $venueSetting,
                    'design_brief' => $designBrief,
                    'special_requests' => $specialRequests,
                    'inspiration_image' => $inspirationImagePath,
                    'status' => 'pending',
                    'total_quoted' => 0,
                    'raw_materials_sum' => 0,
                    'multiplier' => 1.0,
                    'final_quoted_price' => 0,
                    'price_valid_until' => $priceValidUntil,
                    'suggested_procurement_date' => $suggestedProcurement,
                ];

                if ($analysisUsed && is_array($storedAnalysis['analysis'] ?? null)) {
                    $bookingData['ai_analysis_data'] = $storedAnalysis['analysis'];
                }

                $booking = Booking::create($bookingData);

                if (empty($inspirationImagePath) && !empty($storedAnalysis['temp_path']) && Storage::disk('local')->exists($storedAnalysis['temp_path'])) {
                    $finalTempPath = 'bookings/inspiration-images/' . basename($storedAnalysis['temp_path']);
                    Storage::disk('local')->move($storedAnalysis['temp_path'], $finalTempPath);
                    $inspirationImagePath = $finalTempPath;
                    $booking->inspiration_image = $finalTempPath;
                    $booking->save();
                }

                $fullPath = Storage::disk('local')->path($inspirationImagePath);
                if (!file_exists($fullPath)) {
                    throw new \Exception('No inspiration image available for analysis.');
                }

                $vision = app(\App\Services\GeminiVisionService::class);
                if ($analysisUsed && isset($storedAnalysis['analysis'])) {
                    $result = [
                        'analysis' => $storedAnalysis['analysis'],
                        'raw_response' => $storedAnalysis['raw_response'] ?? null,
                    ];
                } else {
                    $result = $vision->analyzeImageFromPath(
                        $fullPath,
                        $validated['special_requests'] ?? null,
                        $eventType,
                        $validated['event_time'] ?? null,
                        $validated['venue'] ?? null,
                        $scaleContext,
                        $validated['event_date'] ?? null,
                        $validated['end_time'] ?? null,
                        designBrief: $designBrief,
                        venueSetting: $venueSetting
                    );
                }

                Log::info('Gemini Raw Analysis Output: ', $result);

                if (!$analysisUsed) {
                    $result['analysis'] = $vision->normalizeAiAnalysis($result['analysis'] ?? [], $designBrief);
                    $result['analysis']['analysis_meta'] = GeminiVisionService::buildAnalysisMeta($result, 'gemini', [
                        'event_type' => $eventType,
                        'event_date' => $validated['event_date'] ?? null,
                        'event_time' => $validated['event_time'] ?? null,
                        'end_time' => $validated['end_time'] ?? null,
                        'venue' => $validated['venue'] ?? null,
                        'venue_setting' => $venueSetting,
                        'scale' => $scaleContext,
                        'design_brief' => $designBrief,
                    ]);
                }
                $vision->validateAnalysisPayload($result['analysis'] ?? []);
                $materials = $result['analysis']['suggested_materials'];

                [$totalCost, $persistedMaterials] = $this->persistAiSuggestedMaterials($booking, $materials);
                if (count($persistedMaterials) === 0) {
                    throw new \RuntimeException('empty_or_invalid_analysis');
                }

                AiAnalysisResult::create([
                    'booking_id' => $booking->id,
                    'raw_gemini_response' => is_array($result['raw_response']) ? json_encode($result['raw_response']) : $result['raw_response'],
                    'suggested_materials' => $persistedMaterials,
                    'analyzed_at' => Carbon::now(),
                ]);

                // Totals use Raflora's verified prices only (same rule as a claimed guest request);
                // AI price estimates are never written into the booking's quoted totals.
                $booking->multiplier = 3.0;
                $booking->load('bookingItems');
                app(\App\Services\QuotationPricingService::class)->calculateTotals($booking, false);
                $booking->ai_analysis_data = $result['analysis'];
                $booking->save();
            }, 5);

            if ($analysisUsed && $analysisToken) {
                if (!empty($storedAnalysis['temp_path'])
                    && Storage::disk('local')->exists($storedAnalysis['temp_path'])
                    && $storedAnalysis['temp_path'] !== $inspirationImagePath
                ) {
                    Storage::disk('local')->delete($storedAnalysis['temp_path']);
                }

                self::forgetStoredAnalysisPayload($analysisToken);
            }

            if ($duplicateKey && $booking) {
                \Illuminate\Support\Facades\Cache::put($duplicateKey, $booking->id, 10);
            }

            return redirect()->route('bookings.show', ['booking' => $booking->id])
                ->with('success', 'Booking request created. Preparing your quotation now.');
        } catch (\Throwable $e) {
            if ($inspirationImagePath) {
                Storage::disk('local')->delete($inspirationImagePath);
            }

            Log::error('Booking AI analysis transaction failed: ' . $e->getMessage(), ['exception' => $e]);
            $userError = $this->mapAiAnalysisErrorToUserMessage($e);

            return redirect()->back()
                ->withInput()
                ->with('analysis_error', $userError);
        }
    }

    protected function mapAiAnalysisErrorToUserMessage(\Throwable $exception): string
    {
        return GeminiVisionService::failureMessageForType(GeminiVisionService::classifyFailure($exception));
    }

    /**
     * Display the booking analysis page.
     */
    public function analysis(Booking $booking): View
    {
        $client = $this->resolveClient();

        if ($booking->client_id !== $client?->id) {
            abort(403, 'Unauthorized access to this booking.');
        }

        $booking = $booking->fresh();

        $analysis = $booking->aiAnalyses()->latest('analyzed_at')->first();
        $payment = $booking->payments()->latest()->first();
        $analysisService = app(\App\Services\GeminiVisionService::class);

        $activeQuotation = $booking->activeQuotation()->first();
        $quotationItems = $activeQuotation && is_array($activeQuotation->items_snapshot) 
            ? $activeQuotation->items_snapshot 
            : null;

        $bookingMessages = $booking->messages()->whereIn('visibility', ['client_admin', 'shared'])->orderBy('created_at')->get();
        $unreadMessageCount = $bookingMessages->where('sender_type', 'admin')->whereNull('read_at')->count();

        // Load attached inventory items and compute total cost from pivot data when available
        $items = $booking->inventoryItems()->get();
        $presentations = $booking->presentations()->orderBy('created_at')->get();
        $calculatedTotal = 0;
        foreach ($items as $it) {
            $qty = floatval($it->pivot->quantity ?? 0);
            $unit = floatval($it->pivot->quoted_unit_price ?? 0);
            $calculatedTotal += $qty * $unit;
        }


        // 1. Authoritative Admin Pricing
        $totalCost = (float) ($booking->final_quoted_price > 0 ? $booking->final_quoted_price : ($booking->total_quoted ?? 0));

        // 2. AI Pricing (Decision Support / Fallback only when Admin hasn't set a price)
        $analysisTotal = 0.0;
        $analysisMaterials = [];
        if ($analysis) {
            $analysisMaterials = $analysis->suggested_materials ?? [];
            if ($totalCost <= 0) {
                if (is_array($analysisMaterials)) {
                    foreach ($analysisMaterials as $material) {
                        $quantity = floatval($material['estimated_quantity'] ?? $material['quantity'] ?? 1);
                        $unitCost = floatval($material['estimated_unit_cost_php'] ?? $material['unit_cost_php'] ?? $material['estimated_unit_cost'] ?? 0);
                        $analysisTotal += $quantity * $unitCost;
                    }
                }

                $analysisPricing = $analysis->pricing_summary ?? null;
                if (is_array($analysisPricing) && !empty($analysisPricing)) {
                    $analysisTotal = floatval($analysisPricing['estimated_grand_total_php'] ?? $analysisPricing['raw_materials_total_php'] ?? $analysisTotal);
                }
            }
        }

        if ($totalCost <= 0 && $analysisTotal <= 0 && is_array($booking->ai_analysis_data ?? null)) {
            $pricingSummary = $booking->ai_analysis_data['pricing_summary'] ?? null;
            if (is_array($pricingSummary) && !empty($pricingSummary)) {
                $analysisTotal = floatval($pricingSummary['estimated_grand_total_php'] ?? $pricingSummary['raw_materials_total_php'] ?? 0);
            }
        }

        if ($totalCost <= 0 && $analysisTotal > 0) {
            $totalCost = $analysisTotal;
        }

        // Check whether the quotation window has expired
        $quotationValidUntil = $activeQuotation?->valid_until ?? $booking->price_valid_until;
        $isExpired = $booking->status === 'quotation_sent'
            && $quotationValidUntil
            && $quotationValidUntil->endOfDay()->isPast();

        if (! $analysis && is_array($booking->ai_analysis_data)) {
            $analysis = (object) ['suggested_materials' => $booking->ai_analysis_data['suggested_materials'] ?? []];
            $analysisMaterials = $analysisService->coerceAnalysisMaterials($booking->ai_analysis_data['suggested_materials'] ?? []);
        }

        $analysisMaterials = $analysisService->coerceAnalysisMaterials($analysisMaterials ?? []);

        return view('client.booking-analysis', [
            'booking'          => $booking,
            'activeQuotation'  => $activeQuotation,
            'quotationItems'   => $quotationItems,
            'totalCost'        => $totalCost,
            'analysis'         => $analysis,
            'analysisMaterials'=> $analysisMaterials,
            'items'            => $items,
            'paymentStatus'    => $payment?->status,
            'paymentReference' => $payment?->reference_number,
            'paymentMethod'    => $payment?->payment_type,
            'isExpired'        => $isExpired,
            'quotationValidUntil' => $quotationValidUntil,
            'presentations'    => $presentations,
            'bookingMessages'     => $bookingMessages,
            'unreadMessageCount'  => $unreadMessageCount,
        ]);
    }

    /**
     * Return the latest client booking timestamp for lightweight portal polling.
     */
    public function status(Booking $booking): JsonResponse
    {
        $client = $this->resolveClient();

        if ($booking->client_id !== $client?->id) {
            abort(403, 'Unauthorized access to this booking.');
        }

        $unreadMessages = $booking->messages()
            ->where('sender_type', 'admin')
            ->whereNull('read_at')
            ->count();

        $latestMessage = $booking->messages()
            ->whereIn('visibility', ['client_admin', 'shared'])
            ->latest('id')
            ->first();

        return response()->json([
            'status' => $booking->status,
            'updated_at' => $booking->updated_at?->toISOString(),
            'unread_messages' => $unreadMessages,
            'latest_message_id' => $latestMessage?->id,
            'latest_message_at' => $latestMessage?->created_at?->toISOString(),
        ]);
    }

    /**
     * Store Gemini's rows as unconfirmed AI suggestions for staff review. The quoted price comes from
     * Raflora's own inventory price record (0 when there is none, so Admin must price it before a
     * quotation can be issued); the AI's price is kept only as ai_recommended_price. Rows without an
     * AI quantity or price are kept with that value left for staff to supply, never guessed.
     */
    protected function persistAiSuggestedMaterials(Booking $booking, array $materials): array
    {
        $totalCost = 0.0;
        $persistedMaterials = [];

        foreach ($materials as $material) {
            $itemName = trim((string) ($material['item_name'] ?? ''));
            if ($itemName === '') {
                continue;
            }

            $rawQuantity = $material['quantity'] ?? $material['estimated_quantity'] ?? null;
            $quantity = is_numeric($rawQuantity) && (float) $rawQuantity > 0 ? (float) $rawQuantity : 0.0;
            $rawAiPrice = $material['unit_cost_php'] ?? $material['estimated_unit_cost_php'] ?? $material['estimated_unit_cost'] ?? null;
            $aiRecommendedPrice = is_numeric($rawAiPrice) && (float) $rawAiPrice > 0 ? round((float) $rawAiPrice, 2) : null;

            $inventoryItem = InventoryItem::query()
                ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($itemName)])
                ->first();

            if (!$inventoryItem) {
                $normalizedName = $this->normalizeInventoryName($itemName);
                $inventoryItem = InventoryItem::query()->get()->first(function ($candidate) use ($normalizedName): bool {
                    return $this->normalizeInventoryName($candidate->name) === $normalizedName
                        || str_contains($this->normalizeInventoryName($candidate->name), $normalizedName)
                        || str_contains($normalizedName, $this->normalizeInventoryName($candidate->name));
                });
            }

            $verifiedUnitPrice = $inventoryItem ? (float) $inventoryItem->unit_cost : 0.0;
            $totalCost += $quantity * $verifiedUnitPrice;

            $materialEntry = $material;
            if ($inventoryItem) {
                $materialEntry['inventory_item_id'] = $inventoryItem->id;

                $bookingItem = $booking->bookingItems()->where('inventory_item_id', $inventoryItem->id)->first();
                if ($bookingItem) {
                    // The same material in another area (one AI row per area): add its quantity.
                    $bookingItem->quantity = (float) $bookingItem->quantity + $quantity;
                    $bookingItem->save();
                } else {
                    $bookingItem = BookingItem::create([
                        'booking_id' => $booking->id,
                        'inventory_item_id' => $inventoryItem->id,
                        'item_name' => $itemName,
                        'quantity' => $quantity,
                        'quoted_unit_price' => $verifiedUnitPrice,
                        'ai_recommended_price' => $aiRecommendedPrice,
                        'is_ai_suggested' => true,
                        'procurement_status' => 'pending',
                    ]);
                }

                $booking->inventoryItems()->syncWithoutDetaching([
                    $inventoryItem->id => [
                        'quantity' => $bookingItem->quantity,
                        'quoted_unit_price' => $verifiedUnitPrice,
                        'ai_recommended_price' => $aiRecommendedPrice,
                        'is_ai_suggested' => true,
                        'procurement_status' => 'pending',
                        'suggested_order_date' => $booking->suggested_procurement_date,
                        'suggested_delivery_date' => null,
                        'notes' => 'AI suggested',
                    ],
                ]);

                $this->createInventoryShortageAlert($booking, $inventoryItem, (float) $bookingItem->quantity);
            } else {
                $bookingItem = BookingItem::create([
                    'booking_id' => $booking->id,
                    'inventory_item_id' => null,
                    'item_name' => $itemName,
                    'quantity' => $quantity,
                    'quoted_unit_price' => 0,
                    'ai_recommended_price' => $aiRecommendedPrice,
                    'is_ai_suggested' => true,
                    'procurement_status' => 'pending',
                ]);

            }

            // The booking line this AI row became, so per-area prices can be read from it.
            $materialEntry['booking_item_id'] = $bookingItem->id;
            $persistedMaterials[] = $materialEntry;
        }

        return [round($totalCost, 2), $persistedMaterials];
    }

    protected function normalizeInventoryName(?string $name): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/', ' ', (string) $name) ?? ''));
    }

    protected function createInventoryShortageAlert(Booking $booking, InventoryItem $inventoryItem, float $required): void
    {
        $available = max(0.0, (float) $inventoryItem->current_stock - (float) $inventoryItem->reserved_stock);
        if ($required <= 0 || $available >= $required) {
            return;
        }

        $shortfall = $required - $available;
        AdminAlert::firstOrCreate([
            'type' => 'inventory_shortage',
            'booking_id' => $booking->id,
            'inventory_item_id' => $inventoryItem->id,
            'is_read' => false,
        ], [
            'title' => 'Stock Shortage: Booking #' . $booking->id,
            'message' => sprintf(
                'Booking #%d requires %s units of "%s", but only %s are available after reservations. Shortfall: %s units.',
                $booking->id,
                number_format($required, 0),
                $inventoryItem->name,
                number_format($available, 0),
                number_format($shortfall, 0)
            ),
        ]);
    }

    public function submitPaymentReference(Request $request, Booking $booking): RedirectResponse
    {
        $client = $this->resolveClient();

        if ($booking->client_id !== $client?->id) {
            abort(403, 'Unauthorized access to this booking.');
        }

        $isInitialPayment = $booking->status === 'admin_approved';
        $isPostEventPayment = in_array($booking->status, ['event_completed', 'pending_resolution'], true);

        if ($booking->status === 'pending_return') {
            return back()->with('error', 'Payment reference cannot be submitted while material return reconciliation is in progress.');
        }

        if (!$isInitialPayment && !$isPostEventPayment) {
            return back()->with('error', 'Payment reference cannot be submitted at this time.');
        }

        if ($booking->remaining_balance <= 0) {
            return back()->with('error', 'No outstanding balance requires payment.');
        }

        if ($booking->payments()->where('status', 'pending')->exists()) {
            return back()->with('error', 'A payment reference is already awaiting Admin verification.');
        }

        $validated = $request->validate([
            'reference_number' => ['required', 'string', 'max:255'],
            'payment_type' => ['required', 'string', 'in:gcash,bank_transfer'],
            'payment_option' => ['required', 'string', 'in:downpayment,full_payment'],
        ], [
            'reference_number.required' => 'Please provide your payment reference number.',
            'payment_type.required' => 'Please select a payment method.',
            'payment_option.required' => 'Please select a payment option (Downpayment or Full Payment).',
        ]);

        if ($booking->payments()->where('reference_number', $validated['reference_number'])->exists()) {
            return back()->with('error', 'This payment reference has already been submitted for this booking.');
        }

        $booking->load('acceptedQuotation');
        $acceptedQuotation = $booking->acceptedQuotation;

        if ($isPostEventPayment) {
            $paymentAmount = $booking->remaining_balance;
            $quoteTotal = $booking->total_obligation;
            // Force the payment option to full_payment as they are clearing the remaining balance
            $validated['payment_option'] = 'full_payment';
        } else {
            $quoteTotal = (float) ($booking->final_quoted_price ?? $booking->total_quoted ?? 0);
            
            if ($acceptedQuotation) {
                $quoteTotal = (float) $acceptedQuotation->final_quoted_price;
            }

            if ($quoteTotal <= 0) {
                return back()->with('error', 'Payment reference cannot be submitted without a valid quotation amount.');
            }

            // Admin-configured downpayment percentage from the accepted quotation
            $downpaymentPct = $acceptedQuotation
                ? (float) ($acceptedQuotation->downpayment_percentage ?? 50.0)
                : 50.0;

            $paymentAmount = $validated['payment_option'] === 'full_payment'
                ? $quoteTotal
                : round($quoteTotal * ($downpaymentPct / 100), 2);
        }

        $error = DB::transaction(function () use ($booking, $acceptedQuotation, $paymentAmount, $quoteTotal, $validated) {
            $lockedBooking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($lockedBooking->status === 'pending_return') {
                return 'Payment reference cannot be submitted while material return reconciliation is in progress.';
            }

            $lockedIsInitial = $lockedBooking->status === 'admin_approved';
            $lockedIsPostEvent = in_array($lockedBooking->status, ['event_completed', 'pending_resolution'], true);

            if (!$lockedIsInitial && !$lockedIsPostEvent) {
                return 'Payment reference cannot be submitted at this time.';
            }

            if ($lockedBooking->remaining_balance <= 0) {
                return 'No outstanding balance requires payment.';
            }

            if ($lockedBooking->payments()->where('status', 'pending')->exists()) {
                return 'A payment reference is already awaiting Admin verification.';
            }

            if ($lockedBooking->payments()->where('reference_number', $validated['reference_number'])->exists()) {
                return 'This payment reference has already been submitted for this booking.';
            }

            Payment::create([
                'booking_id' => $lockedBooking->id,
                'quotation_id' => $acceptedQuotation?->id,
                'amount' => $paymentAmount,
                'payment_option' => $validated['payment_option'],
                'amount_paid' => 0.00,
                'remaining_balance' => max(0.0, $lockedBooking->remaining_balance - $paymentAmount),
                'payment_type' => $validated['payment_type'],
                'reference_number' => $validated['reference_number'],
                'status' => 'pending',
                'recorded_by' => null,
            ]);

            if ($lockedBooking->status === 'admin_approved') {
                $lockedBooking->status = 'payment_submitted';
                $lockedBooking->save();
            }

            return null;
        });

        if ($error) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Payment reference submitted. Admin will verify your payment shortly.');
    }

    /**
     * Display the booking analysis page.
     */
    public function history(): View
    {
        $client = $this->resolveClient();
        $bookings = $client ? $client->bookings()->latest('created_at')->get() : collect();

        return view('client.booking-history', [
            'bookings' => $bookings,
        ]);
    }

    /**
     * Accept the quotation for a booking.
     */
    public function acceptQuotation(Booking $booking): RedirectResponse
    {
        $client = $this->resolveClient();

        if ($booking->client_id !== $client?->id) {
            abort(403, 'Unauthorized access to this booking.');
        }

        if ($booking->status !== 'quotation_sent') {
            return back()->with('error', 'This booking cannot be accepted at this stage.');
        }

        // 1. Resolve active quotation
        $booking->load('activeQuotation');
        $activeQuotation = $booking->activeQuotation;

        // 2. Reject acceptance when no active quotation exists
        if (!$activeQuotation) {
            return back()->with('error', 'No active quotation found. Please wait for the admin to issue a quotation.');
        }

        // 3. Expiry guard: authoritative check against the quotation's validity date
        $validUntil = $activeQuotation->valid_until ?? $booking->price_valid_until;
        if ($validUntil && $validUntil->endOfDay()->isPast()) {
            return back()->with('error', 'This quotation has expired due to floral price volatility. Please wait for the admin to re-issue an updated quotation.');
        }

        // 4. Wrap updates in DB transaction to ensure consistency
        DB::transaction(function () use ($booking, $activeQuotation) {
            $hasVerifiedPayment = $booking->total_paid > 0;
            if ($hasVerifiedPayment) {
                $booking->status = $booking->remaining_balance <= 0 ? 'confirmed' : 'downpayment_received';
            } else {
                $booking->status = 'approved';
            }
            $booking->save();

            $activeQuotation->status = \App\Models\Quotation::STATUS_ACCEPTED;
            $activeQuotation->save();

            $this->logAuditEvent('booking', 'quotation_accepted', $booking, 'Quotation accepted by client', Auth::id());
        });

        $successMsg = $booking->total_paid > 0
            ? 'Revised quotation accepted. Your booking remains active with updated pricing.'
            : 'Quotation accepted. Administrative final review is pending. Payment options will become available once approved.';

        return redirect()->route('bookings.analysis', ['booking' => $booking->id])
            ->with('success', $successMsg);
    }

    public function requestChanges(Request $request, Booking $booking): RedirectResponse
    {
        $client = $this->resolveClient();

        if ($booking->client_id !== $client?->id) {
            abort(403, 'Unauthorized access to this booking.');
        }

        if ($booking->status !== 'quotation_sent') {
            return back()->with('error', 'This booking cannot be modified at this stage.');
        }

        $validated = $request->validate([
            'change_type' => ['required', 'string', 'in:material,item,schedule,other'],
            'change_item' => ['required', 'string', 'max:255'],
            'change_quantity' => ['required', 'integer', 'min:1'],
            'change_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($booking, $client, $validated) {
                $activeQuotation = $booking->activeQuotation()->first();

                $messageText = sprintf(
                    "Request Changes:\nType: %s\nItem/Material: %s\nQuantity: %d\nDetails: %s",
                    ucfirst($validated['change_type']),
                    $validated['change_item'],
                    $validated['change_quantity'],
                    $validated['change_reason'] ?? 'None provided'
                );

                \App\Models\BookingMessage::create([
                    'booking_id' => $booking->id,
                    'sender_type' => 'client',
                    'sender_id' => $client?->id,
                    'message' => $messageText,
                    'related_quotation_version' => $activeQuotation?->version,
                ]);

                $booking->status = 'change_requested';
                $booking->save();

                $this->logAuditEvent('booking', 'change_requested', $booking, 'Client requested changes to the quotation', \Illuminate\Support\Facades\Auth::id());
            });

            return redirect()->back()->with('success', 'Your change request has been sent to the admin.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to submit change request: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to process your request. Please try again.');
        }
    }

    public function replyToAdmin(Request $request, Booking $booking, \App\Services\BookingAttachmentService $attachmentService): RedirectResponse
    {
        $client = $this->resolveClient();

        if ($booking->client_id !== $client?->id) {
            abort(403, 'Unauthorized access to this booking.');
        }

        $rules = array_merge(
            [
                'message' => ['required', 'string', 'max:2000'],
                'visibility' => ['required', 'string', 'in:client_admin,shared'],
                'submission_key' => ['nullable', 'string', 'max:255'],
            ],
            \App\Services\BookingAttachmentService::getValidationRules(false)
        );

        $validated = $request->validate($rules);

        if (!empty($validated['submission_key'])) {
            $existing = $booking->messages()->where('submission_key', $validated['submission_key'])->first();
            if ($existing) {
                return redirect()->back()->with('success', 'Your message has been sent to the admin.');
            }
        }

        try {
            $messageData = [
                'booking_id' => $booking->id,
                'sender_type' => 'client',
                'sender_id' => $client->id,
                'message' => $validated['message'],
                'visibility' => $validated['visibility'],
                'submission_key' => $validated['submission_key'] ?? null,
            ];

            $attachmentService->storeMessage(
                $booking, 
                $messageData, 
                $request->file('attachment'), 
                $validated['attachment_category'] ?? null
            );

            AdminAlert::create([
                'type' => 'client_message',
                'title' => 'Client Message: Booking #' . $booking->id,
                'message' => Str::limit($validated['message'], 150),
                'booking_id' => $booking->id,
                'is_read' => false,
            ]);

            return redirect()->back()->with('success', 'Your message has been sent to the admin.');
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return redirect()->back()->with('success', 'Your message has been sent to the admin.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to submit client reply: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to process your request. Please try again.');
        }
    }

    /**
     * Return messages for client booking.
     */
    public function getMessages(Booking $booking): JsonResponse
    {
        $client = $this->resolveClient();

        if ($booking->client_id !== $client?->id) {
            abort(403, 'Unauthorized access to this booking.');
        }

        // Mark incoming admin messages as read upon opening the conversation
        $booking->messages()
            ->where('sender_type', 'admin')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = $booking->messages()
            ->whereIn('visibility', ['client_admin', 'shared'])
            ->orderBy('created_at', 'asc')
            ->get();

        $formatted = $messages->map(function ($msg) {
            return [
                'id' => $msg->id,
                'booking_id' => $msg->booking_id,
                'sender_type' => $msg->sender_type,
                'sender_id' => $msg->sender_id,
                'sender_label' => $msg->sender_type === 'client' ? 'You' : ($msg->sender_type === 'admin' ? 'Admin' : 'Staff'),
                'is_mine' => $msg->sender_type === 'client',
                'message' => $msg->message,
                'visibility' => $msg->visibility,
                'related_quotation_version' => $msg->related_quotation_version,
                'submission_key' => $msg->submission_key,
                'created_at' => $msg->created_at?->toISOString(),
                'created_at_human' => $msg->created_at?->format('M j, g:i A'),
                'read_at' => $msg->read_at?->toISOString(),
                'is_read' => !is_null($msg->read_at),
                'has_attachment' => !empty($msg->attachment_path),
                'attachment_name' => $msg->attachment_name,
                'attachment_category' => $msg->attachment_category,
                'attachment_category_label' => $msg->attachment_category ? ucwords(str_replace('_', ' ', $msg->attachment_category)) : null,
                'attachment_url' => $msg->attachment_path ? route('secure.attachment.show', $msg->id) : null,
            ];
        });

        $unreadCount = $booking->messages()
            ->where('sender_type', 'admin')
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'messages' => $formatted,
            'unread_count' => $unreadCount,
            'booking_status' => $booking->status,
        ]);
    }

    /**
     * Client sends a new message to Admin.
     */
    public function sendMessage(Request $request, Booking $booking, \App\Services\BookingAttachmentService $attachmentService): JsonResponse
    {
        $client = $this->resolveClient();

        if ($booking->client_id !== $client?->id) {
            abort(403, 'Unauthorized access to this booking.');
        }

        $rules = array_merge(
            [
                'message' => ['required', 'string', 'max:2000'],
                'visibility' => ['nullable', 'string', 'in:client_admin,shared'],
                'related_quotation_version' => ['nullable', 'integer'],
                'submission_key' => ['nullable', 'string', 'max:255'],
            ],
            \App\Services\BookingAttachmentService::getValidationRules(false)
        );

        $validated = $request->validate($rules);
        $visibility = $validated['visibility'] ?? 'client_admin';
        $submissionKey = $validated['submission_key'] ?? null;

        if (!empty($submissionKey)) {
            $existing = $booking->messages()->where('submission_key', $submissionKey)->first();
            if ($existing) {
                return response()->json([
                    'success' => true,
                    'is_duplicate' => true,
                    'message' => [
                        'id' => $existing->id,
                        'booking_id' => $existing->booking_id,
                        'sender_type' => $existing->sender_type,
                        'sender_label' => 'You',
                        'is_mine' => true,
                        'message' => $existing->message,
                        'visibility' => $existing->visibility,
                        'related_quotation_version' => $existing->related_quotation_version,
                        'submission_key' => $existing->submission_key,
                        'created_at' => $existing->created_at?->toISOString(),
                        'created_at_human' => $existing->created_at?->format('M j, g:i A'),
                        'read_at' => $existing->read_at?->toISOString(),
                        'is_read' => !is_null($existing->read_at),
                        'has_attachment' => !empty($existing->attachment_path),
                        'attachment_name' => $existing->attachment_name,
                        'attachment_category' => $existing->attachment_category,
                        'attachment_url' => $existing->attachment_path ? route('secure.attachment.show', $existing->id) : null,
                    ],
                ]);
            }
        }

        try {
            $messageData = [
                'booking_id' => $booking->id,
                'sender_type' => 'client', // SERVER-DERIVED
                'sender_id' => $client->id, // SERVER-DERIVED
                'message' => $validated['message'],
                'visibility' => $visibility,
                'related_quotation_version' => $validated['related_quotation_version'] ?? null,
                'submission_key' => $submissionKey,
            ];

            $message = $attachmentService->storeMessage(
                $booking,
                $messageData,
                $request->file('attachment'),
                $validated['attachment_category'] ?? null
            );

            AdminAlert::create([
                'type' => 'client_message',
                'title' => 'Client Message: Booking #' . $booking->id,
                'message' => Str::limit($validated['message'], 150),
                'booking_id' => $booking->id,
                'is_read' => false,
            ]);

            $this->logAuditEvent('booking', 'client_message_sent', $booking, 'Client sent a message', Auth::id());

            return response()->json([
                'success' => true,
                'message' => [
                    'id' => $message->id,
                    'booking_id' => $message->booking_id,
                    'sender_type' => $message->sender_type,
                    'sender_label' => 'You',
                    'is_mine' => true,
                    'message' => $message->message,
                    'visibility' => $message->visibility,
                    'related_quotation_version' => $message->related_quotation_version,
                    'submission_key' => $message->submission_key,
                    'created_at' => $message->created_at?->toISOString(),
                    'created_at_human' => $message->created_at?->format('M j, g:i A'),
                    'read_at' => null,
                    'is_read' => false,
                    'has_attachment' => !empty($message->attachment_path),
                    'attachment_name' => $message->attachment_name,
                    'attachment_category' => $message->attachment_category,
                    'attachment_url' => $message->attachment_path ? route('secure.attachment.show', $message->id) : null,
                ],
            ], 201);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $existing = $booking->messages()->where('submission_key', $submissionKey)->first();
            if ($existing) {
                return response()->json([
                    'success' => true,
                    'is_duplicate' => true,
                    'message' => [
                        'id' => $existing->id,
                        'booking_id' => $existing->booking_id,
                        'sender_type' => $existing->sender_type,
                        'sender_label' => 'You',
                        'is_mine' => true,
                        'message' => $existing->message,
                        'created_at' => $existing->created_at?->toISOString(),
                        'created_at_human' => $existing->created_at?->format('M j, g:i A'),
                    ],
                ]);
            }
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to send client message: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Unable to send message.'], 500);
        }
    }

    /**
     * Mark messages read for client.
     */
    public function markMessagesRead(Booking $booking): JsonResponse
    {
        $client = $this->resolveClient();

        if ($booking->client_id !== $client?->id) {
            abort(403, 'Unauthorized access to this booking.');
        }

        $affected = $booking->messages()
            ->where('sender_type', 'admin')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'marked_read' => $affected,
            'unread_count' => 0,
        ]);
    }

    public function requestCancellation(Request $request, Booking $booking): RedirectResponse
    {
        $client = $this->resolveClient();

        if ($booking->client_id !== $client?->id) {
            abort(403, 'Unauthorized access to this booking.');
        }

        if ($booking->status === 'cancellation_requested') {
            return redirect()->back()->with('error', 'A cancellation request is already pending review for this booking.');
        }

        $eligibleStatuses = [
            'pending',
            'quotation_sent',
            'change_requested',
            'approved',
            'admin_approved',
            'payment_pending',
            'payment_submitted',
            'downpayment_received',
            'confirmed',
            'in_preparation',
        ];

        if (!in_array($booking->status, $eligibleStatuses, true)) {
            return redirect()->back()->with('error', 'Cancellation cannot be requested for this booking stage.');
        }

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($booking, $validated, $client) {
            $booking->pre_cancellation_status = $booking->status;
            $booking->cancellation_reason = $validated['cancellation_reason'];
            $booking->status = 'cancellation_requested';
            $booking->save();

            \App\Models\BookingMessage::create([
                'booking_id' => $booking->id,
                'sender_type' => 'client',
                'sender_id' => $client?->id,
                'message' => 'Requested cancellation: ' . $validated['cancellation_reason'],
            ]);

            $this->logAuditEvent('booking', 'cancellation_requested', $booking, 'Client requested cancellation', Auth::id());
        });

        return redirect()->back()->with('success', 'Your cancellation request has been submitted to the admin for review.');
    }

    public function submitProposalFeedback(Request $request, Booking $booking, Presentation $presentation): RedirectResponse
    {
        $client = $this->resolveClient();

        if ($booking->client_id !== $client?->id || $presentation->booking_id !== $booking->id) {
            abort(403, 'Unauthorized access to this proposal.');
        }

        $validated = $request->validate([
            'approval_status' => ['nullable', 'string', 'in:approved,needs_revision'],
            'feedback_text' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($booking, $presentation, $client, $validated) {
                $presentation->approval_status = $validated['approval_status'] ?? 'approved';
                $presentation->feedback_text = $validated['feedback_text'] ?? null;
                $presentation->status = 'reviewed';
                $presentation->save();

                if (!empty($validated['feedback_text'])) {
                    $activeQuotation = $booking->activeQuotation()->first();
                    \App\Models\BookingMessage::create([
                        'booking_id' => $booking->id,
                        'sender_type' => 'client',
                        'sender_id' => $client?->id,
                        'message' => 'Proposal v' . $presentation->version . ' feedback: ' . $validated['feedback_text'],
                        'related_quotation_version' => $activeQuotation?->version,
                    ]);
                }

                $this->logAuditEvent('booking', 'proposal_feedback', $booking, 'Client submitted proposal feedback', \Illuminate\Support\Facades\Auth::id());
            });

            return redirect()->back()->with('success', 'Your feedback has been recorded.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to submit proposal feedback: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to process your feedback. Please try again.');
        }
    }

    protected function logAuditEvent(string $module, string $action, ?Booking $booking = null, ?string $details = null, ?int $userId = null, ?array $oldValues = null, ?array $newValues = null, ?string $eventType = null): void
    {
        AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'module' => $module,
            'event_type' => $eventType,
            'details' => $details,
            'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => request()->ip(),
            'entity_type' => $booking ? Booking::class : null,
            'entity_id' => $booking?->id,
        ]);
    }

    /**
     * Resolve the client record for the authenticated user.
     */
    protected function resolveClient(): ?Client
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        return Client::firstOrCreate(
            ['email' => $user->email],
            [
                'full_name' => $user->name,
                'phone' => $user->mobile_number,
                'address' => $user->address,
            ]
        );
    }

    protected function estimateQuote(string $eventType): float
    {
        return match ($eventType) {
            'wedding' => 45000.00,
            'corporate' => 28000.00,
            'birthday' => 18000.00,
            default => 20000.00,
        };
    }

    protected function analysisMaterialsFor(string $eventType): array
    {
        return match ($eventType) {
            'wedding' => [
                ['item_name' => 'White Roses', 'confidence' => 0.94, 'category' => 'flower', 'estimated_quantity' => 120, 'unit_cost' => 150.00],
                ['item_name' => 'Baby Breath', 'confidence' => 0.88, 'category' => 'flower', 'estimated_quantity' => 60, 'unit_cost' => 80.00],
                ['item_name' => 'Greenery Garlands', 'confidence' => 0.82, 'category' => 'foliage', 'estimated_quantity' => 20, 'unit_cost' => 220.00],
            ],
            'corporate' => [
                ['item_name' => 'Orchids', 'confidence' => 0.91, 'category' => 'flower', 'estimated_quantity' => 90, 'unit_cost' => 180.00],
                ['item_name' => 'Tropical Foliage', 'confidence' => 0.85, 'category' => 'foliage', 'estimated_quantity' => 70, 'unit_cost' => 110.00],
                ['item_name' => 'Black Calla Lilies', 'confidence' => 0.79, 'category' => 'flower', 'estimated_quantity' => 40, 'unit_cost' => 240.00],
            ],
            'birthday' => [
                ['item_name' => 'Pink Carnations', 'confidence' => 0.92, 'category' => 'flower', 'estimated_quantity' => 70, 'unit_cost' => 95.00],
                ['item_name' => 'Dried Pampas Grass', 'confidence' => 0.81, 'category' => 'foliage', 'estimated_quantity' => 30, 'unit_cost' => 140.00],
                ['item_name' => 'Ribbon Décor', 'confidence' => 0.75, 'category' => 'prop', 'estimated_quantity' => 15, 'unit_cost' => 180.00],
            ],
            default => [
                ['item_name' => 'Seasonal Blooms', 'confidence' => 0.78, 'category' => 'flower', 'estimated_quantity' => 80, 'unit_cost' => 110.00],
            ],
        };
    }

    /**
     * AJAX endpoint: validate an uploaded inspiration image using Gemini Vision.
     * Returns JSON with is_valid and rejection_reason.
     */
    public function validateImageAjax(Request $request)
    {
        $request->validate([
            'inspiration_image' => ['required', 'image', 'max:5120'],
        ]);

        $tempPath = $request->file('inspiration_image')->store('bookings/temp-validation', 'local');
        $fullPath = storage_path('app/private/' . $tempPath);

        try {
            $vision = new GeminiVisionService();
            $result = $vision->validateImage($fullPath);
        } catch (\Throwable $e) {
            Log::error('AJAX image validation error: ' . $e->getMessage());
            $result = [
                'is_valid' => false,
                'rejection_reason' => 'The image could not be analyzed. Please upload a clear floral or event image and try again.',
                'model_used' => null,
            ];
        } finally {
            // Always clean up the temp validation file
            Storage::disk('local')->delete($tempPath);
        }

        return response()->json([
            'is_valid' => $result['is_valid'],
            'rejection_reason' => $result['rejection_reason'],
        ]);
    }

    protected function findCompletedBookingTemplate(?string $imageHash): ?array
    {
        if ($imageHash === null || $imageHash === '') {
            return null;
        }

        $matchingBooking = Booking::query()
            ->where('status', 'completed')
            ->whereNotNull('inspiration_image')
            ->orderByDesc('id')
            ->get()
            ->first(function ($booking) use ($imageHash): bool {
                $path = storage_path('app/public/' . ltrim((string) $booking->inspiration_image, '/'));
                if (!is_file($path)) {
                    return false;
                }

                return @sha1_file($path) === $imageHash;
            });

        if (!$matchingBooking) {
            return null;
        }

        $templateItems = [];
        foreach ($matchingBooking->bookingItems()->with('inventoryItem')->get() as $bookingItem) {
            $inventoryItem = $bookingItem->inventoryItem ?? InventoryItem::find($bookingItem->inventory_item_id);
            $itemName = trim((string) ($bookingItem->item_name ?? $inventoryItem?->name ?? 'Unknown item'));
            if ($itemName === '') {
                continue;
            }

            $quantity = (float) ($bookingItem->quantity ?? 0);
            $baseCost = (float) ($inventoryItem?->unit_cost ?? $bookingItem->quoted_unit_price ?? 0);
            $seasonalMultiplier = now()->month >= 11 || now()->month <= 2 ? 1.12 : 1.05;
            $resolvedCost = round($baseCost * $seasonalMultiplier, 2);

            $templateItems[] = [
                'item_name' => $itemName,
                'quantity' => $quantity,
                'estimated_quantity' => $quantity,
                'unit_cost_php' => $resolvedCost,
                'estimated_unit_cost_php' => $resolvedCost,
                'unit_type' => $inventoryItem?->unit ?? 'pcs',
                'category' => 'flower',
                'is_custom_item' => false,
                'source' => 'completed_template',
                'template_booking_id' => $matchingBooking->id,
            ];
        }

        if (empty($templateItems)) {
            return null;
        }

        $rawMaterialsTotal = 0.0;
        $itemizedBreakdown = [];
        foreach ($templateItems as $material) {
            $quantity = (float) ($material['quantity'] ?? $material['estimated_quantity'] ?? 0);
            $unitCost = (float) ($material['unit_cost_php'] ?? $material['estimated_unit_cost_php'] ?? 0);
            if ($quantity <= 0 || $unitCost <= 0) {
                continue;
            }

            $subtotal = round($quantity * $unitCost, 2);
            $rawMaterialsTotal += $subtotal;
            $itemizedBreakdown[] = [
                'item_name' => $material['item_name'] ?? 'Unknown item',
                'unit_type' => $material['unit_type'] ?? 'pcs',
                'quantity' => $quantity,
                'estimated_quantity' => $quantity,
                'unit_cost_php' => round($unitCost, 2),
                'estimated_unit_cost_php' => round($unitCost, 2),
                'estimated_subtotal_php' => $subtotal,
            ];
        }

        $rawMaterialsTotal = round($rawMaterialsTotal, 2);
        $estimatedGrandTotal = round($rawMaterialsTotal * 3.0, 2);

        return [
            'analysis' => [
                'suggested_materials' => $templateItems,
                'pricing_summary' => [
                    'markup_multiplier' => 3.0,
                    'raw_materials_total_php' => $rawMaterialsTotal,
                    'estimated_grand_total_php' => $estimatedGrandTotal,
                    'itemized_breakdown' => $itemizedBreakdown,
                ],
                'template_source' => [
                    'booking_id' => $matchingBooking->id,
                    'matched' => true,
                ],
            ],
            'raw_response' => [
                'matched_completed_booking' => $matchingBooking->id,
                'template_source' => 'completed_booking',
            ],
        ];
    }

    public function analyzeTempImage(Request $request)
    {
        set_time_limit(60);

        $request->validate([
            'inspiration_image' => ['required', 'image', 'max:5120'],
            'event_type' => ['nullable', 'string'],
            'event_date' => ['nullable', 'date'],
            'event_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'venue' => ['nullable', 'string', 'max:500'],
            'venue_address' => ['nullable', 'string', 'max:500'],
            'table_count' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
        ] + EventDesignBrief::rules(), EventDesignBrief::messages());

        $venue = $request->input('venue', $request->input('venue_address'));
        $designBrief = EventDesignBrief::fromInput($request->all());
        $venueSetting = EventDesignBrief::venueSettingFromInput($request->all());

        $scaleContext = '';
        if ($request->filled('table_count')) {
            $scaleContext .= "Tables: {$request->input('table_count')}. ";
        }
        if ($request->filled('guest_count')) {
            $scaleContext .= "Guest Count (Pax): {$request->input('guest_count')}. ";
        }
        $scaleContext = trim($scaleContext) ?: "Single order/bouquet delivery - no scale multiplier applied";

        $tempPath = $request->file('inspiration_image')->store('bookings/temp-analysis', 'local');
        $fullPath = Storage::disk('local')->path($tempPath);
        $uploadedHash = @sha1_file($fullPath) ?: null;
        $originalFilename = $request->file('inspiration_image')->getClientOriginalName();

        try {
            $vision = new GeminiVisionService();

            $valCacheKey = $uploadedHash ? "gemini_val_{$uploadedHash}_{$originalFilename}" : null;
            if ($valCacheKey && Cache::has($valCacheKey)) {
                $validation = Cache::get($valCacheKey);
            } else {
                $validation = $vision->validateImage($fullPath);
                if ($valCacheKey && !empty($validation['is_valid'])) {
                    Cache::put($valCacheKey, $validation, now()->addHours(2));
                }
            }

            if (!$validation['is_valid']) {
                $errorType = $validation['error_type'] ?? 'image_quality';
                $isQuotaError = in_array($errorType, ['service', 'rate_limit', 'quota'], true);

                return response()->json([
                    'success' => false,
                    'error_type' => $errorType,
                    'is_quota_error' => $isQuotaError,
                    'message' => $validation['rejection_reason'] ?? 'The image is not clear enough for reliable analysis. Please upload a clearer image.',
                ], 422);
            }

            $templateResult = $this->findCompletedBookingTemplate($uploadedHash);
            $completedTemplateReused = false;
            $analysisContext = [
                'event_type' => $request->input('event_type'),
                'event_date' => $request->input('event_date'),
                'event_time' => $request->input('event_time'),
                'end_time' => $request->input('end_time'),
                'venue' => $venue,
                'venue_setting' => $venueSetting,
                'scale' => $scaleContext,
                'special_requests' => $request->input('special_requests'),
                'design_brief' => $designBrief,
            ];
            $contextHash = GeminiVisionService::analysisContextHash($analysisContext);
            $anaCacheKey = $uploadedHash ? "gemini_ana_{$uploadedHash}_{$originalFilename}_{$contextHash}" : null;

            if ($templateResult) {
                $analysisResult = $templateResult;
                $completedTemplateReused = true;
                $analysisSource = 'completed_booking_template';
            } elseif ($anaCacheKey && Cache::has($anaCacheKey)) {
                $analysisResult = Cache::get($anaCacheKey);
                $analysisSource = 'cache';
            } else {
                $analysisResult = $vision->analyzeImageFromPath(
                    $fullPath,
                    $request->input('special_requests'),
                    $request->input('event_type'),
                    $request->input('event_time'),
                    $venue,
                    $scaleContext,
                    $request->input('event_date'),
                    $request->input('end_time'),
                    designBrief: $designBrief,
                    venueSetting: $venueSetting
                );
                $analysisSource = 'gemini';
                if ($anaCacheKey && !empty($analysisResult['analysis']['suggested_materials'])) {
                    Cache::put($anaCacheKey, $analysisResult, now()->addHours(2));
                }
            }

            $imageMeta = [
                'mime_type' => mime_content_type($fullPath) ?: null,
                'width' => null,
                'height' => null,
                'prepared_mime_type' => $analysisResult['image_metadata']['prepared_mime_type'] ?? null,
                'prepared_width' => $analysisResult['image_metadata']['prepared_width'] ?? null,
                'prepared_height' => $analysisResult['image_metadata']['prepared_height'] ?? null,
            ];
            $sourceImageInfo = @getimagesize($fullPath);
            if (is_array($sourceImageInfo) && isset($sourceImageInfo[0], $sourceImageInfo[1])) {
                $imageMeta['width'] = (int) $sourceImageInfo[0];
                $imageMeta['height'] = (int) $sourceImageInfo[1];
            }

            $diagnostic = GeminiVisionService::buildAnalysisDiagnostic(
                'client',
                $analysisResult,
                [
                    'event_type' => $request->input('event_type'),
                    'event_date' => $request->input('event_date'),
                    'event_time' => $request->input('event_time'),
                    'end_time' => $request->input('end_time'),
                    'venue' => $venue,
                    'venue_setting' => $venueSetting,
                    'guest_count' => $request->input('guest_count'),
                    'table_count' => $request->input('table_count'),
                    'special_requests' => $request->input('special_requests'),
                    'design_brief' => $designBrief,
                ],
                $uploadedHash,
                $fullPath,
                $completedTemplateReused,
                $imageMeta,
                $analysisResult['generation_config'] ?? ['temperature' => 0.2, 'responseMimeType' => 'application/json']
            );

            $analysisResult['analysis'] = $vision->normalizeAiAnalysis($analysisResult['analysis'] ?? [], $designBrief);
            $vision->validateAnalysisPayload($analysisResult['analysis'] ?? []);
            $suggestedMaterials = $analysisResult['analysis']['suggested_materials'];
            if (!is_array($suggestedMaterials) || count($suggestedMaterials) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => '⚠ We couldn\'t detect clear event decor items in this photo. Please upload a clear, well-lit image.',
                ], 422);
            }

            $pricingSummary = $vision->buildPricingSummary($suggestedMaterials);
            $rawMaterialsTotal = (float) $pricingSummary['raw_materials_total_php'];
            $itemizedBreakdown = $pricingSummary['itemized_breakdown'];
            $markupMultiplier = (float) $pricingSummary['markup_multiplier'];
            $estimatedGrandTotal = (float) $pricingSummary['estimated_grand_total_php'];
            $analysisResult['analysis']['pricing_summary'] = $pricingSummary;
            // What each area the client picked needs, as quantities only: area prices come from
            // Raflora's own prices once the booking exists (only when areas were picked).
            if ($areaBreakdown = DecorAreaBreakdown::build($suggestedMaterials, $designBrief)) {
                $analysisResult['analysis']['area_breakdown'] = $areaBreakdown;
            }
            $analysisResult['analysis']['analysis_meta'] = GeminiVisionService::buildAnalysisMeta($analysisResult, $analysisSource, $analysisContext);

            $analysisDataJson = json_encode($analysisResult['analysis']);
            $token = (string) Str::uuid();
            $payload = [
                'image_hash' => $uploadedHash,
                'analysis' => $analysisResult['analysis'],
                'raw_response' => $analysisResult['raw_response'] ?? null,
                'analysis_data' => $analysisDataJson,
                'diagnostic' => $diagnostic,
                'raw_materials_total' => $rawMaterialsTotal,
                'markup_multiplier' => $markupMultiplier,
                'estimated_grand_total' => $estimatedGrandTotal,
                'temp_path' => $tempPath,
                'request_nonce' => $request->input('analysis_nonce', (string) Str::uuid()),
            ];
            session()->put("booking_analysis_payloads.{$token}", $payload);

            return response()->json([
                'success' => true,
                'analysis_token' => $token,
                'analysis_temp_path' => $tempPath,
                'analysis_data' => $analysisDataJson,
                'analysis_nonce' => $payload['request_nonce'] ?? null,
                'analysis' => $analysisResult['analysis'],
                'raw_materials_total' => $rawMaterialsTotal,
                'markup_multiplier' => $markupMultiplier,
                'estimated_grand_total' => $estimatedGrandTotal,
                'itemized_breakdown' => $itemizedBreakdown,
                'color_palette' => $analysisResult['analysis']['color_palette'] ?? null,
                'arrangement_style' => $analysisResult['analysis']['arrangement_style'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('AJAX analysis error: ' . $e->getMessage(), ['exception' => $e]);

            if (isset($tempPath)) {
                Storage::disk('local')->delete($tempPath);
            }

            $failureType = GeminiVisionService::classifyFailure($e);
            $isQuotaError = in_array($failureType, ['service', 'rate_limit', 'quota'], true);

            return response()->json([
                'success' => false,
                'error_type' => $failureType,
                'is_quota_error' => $isQuotaError,
                'message' => GeminiVisionService::failureMessageForType($failureType),
            ], 422);
        }
    }

    public static function getStoredAnalysisPayload(string $token): ?array
    {
        return session()->get("booking_analysis_payloads.{$token}");
    }

    public static function forgetStoredAnalysisPayload(string $token): void
    {
        session()->forget("booking_analysis_payloads.{$token}");
    }
}

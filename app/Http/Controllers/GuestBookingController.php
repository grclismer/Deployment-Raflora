<?php

namespace App\Http\Controllers;

use App\Models\AiAnalysisResult;
use App\Models\AdminAlert;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\InventoryItem;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Quotation;
use App\Services\AiEstimateService;
use App\Services\GeminiVisionService;
use App\Support\DecorAreaBreakdown;
use App\Support\EventDesignBrief;
use App\Support\InspirationPhotos;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GuestBookingController extends Controller
{
    /**
     * Show the guest booking creation form.
     */
    public function create(): View
    {
        $packages = Package::where('is_active', true)
            ->where('is_archived', false)
            ->with('images')
            ->get();

        return view('guest.booking-create', [
            'packages' => $packages,
        ]);
    }

    /**
     * Store a new guest booking.
     */
    public function store(Request $request): RedirectResponse
    {
        set_time_limit(120);

        if (!$request->has('venue') && $request->has('venue_address')) {
            $request->merge(['venue' => $request->input('venue_address')]);
        }

        // Support specific venue/address and venue city inputs
        $hasSeparateVenue = $request->has('venue_specific') || $request->has('venue_city') || $request->has('specific_venue');

        if ($request->has('specific_venue') && !$request->has('venue_specific')) {
            $request->merge(['venue_specific' => $request->input('specific_venue')]);
        }

        if (!$hasSeparateVenue && $request->filled('venue')) {
            $venueParts = explode(', ', $request->input('venue'), 2);
            $request->merge([
                'venue_specific' => $venueParts[0] ?? $request->input('venue'),
                'venue_city' => $venueParts[1] ?? 'METRO MANILA',
            ]);
        }

        if ($hasSeparateVenue && $request->filled('venue_specific') && $request->filled('venue_city')) {
            $request->merge(['venue' => trim($request->input('venue_specific') . ', ' . $request->input('venue_city'))]);
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
        } else {
            $request->merge(['package_id' => null]);
        }
        $analysisTokens = InspirationPhotos::tokensFrom($request->input('analysis_tokens'));
        if (empty($analysisTokens) && $request->filled('analysis_token')) {
            $analysisTokens = [$request->input('analysis_token')];
        }

        $analysisToken = $analysisTokens[0] ?? $request->input('analysis_token');
        $storedAnalysis = $analysisToken ? $this->getStoredAnalysisPayload($analysisToken) : null;
        $analysisUsed = false;

        $validated = $request->validate([
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', 'email:rfc', 'max:255'],
            'guest_phone' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'guest_address' => ['required', 'string', 'max:500'],
            'booking_type' => ['nullable', 'string', 'in:custom_ai,preset'],
            'package_id' => ['nullable', 'exists:packages,id'],
            'event_type' => ['required', 'string', 'max:255'],
            'other_event_type' => ['nullable', 'string', 'max:255', 'required_if:event_type,other'],
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'event_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'venue_city' => ['required', 'string', 'max:255'],
            'venue_specific' => ['required', 'string', 'max:500'],
            'venue' => ['required', 'string', 'max:500'],
            'venue_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:venue_longitude'],
            'venue_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:venue_latitude'],
            'table_count' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'inspiration_image' => ['nullable', 'image', 'mimes:' . implode(',', InspirationPhotos::MIMES), 'max:' . InspirationPhotos::MAX_KILOBYTES],
            'analysis_token' => ['nullable', 'string'],
            'analysis_tokens' => ['nullable'],
            'analysis_temp_path' => ['nullable', 'string'],
            'analysis_data' => ['nullable'],
            'analysis_nonce' => ['nullable', 'string'],
        ] + EventDesignBrief::rules(), [
            'guest_phone.required' => 'Mobile number is required.',
            'guest_phone.regex' => 'Mobile number must contain exactly 11 digits.',
            'event_date.after_or_equal' => 'The event date cannot be in the past.',
            'event_time.required' => 'Start time is required.',
            'end_time.required' => 'End time is required.',
            'venue_city.required' => 'Venue city or municipality is required.',
            'venue_specific.required' => 'Specific venue or address is required.',
            'venue_latitude' => 'The pinned venue location is invalid. Please pin the venue on the map again.',
            'venue_longitude' => 'The pinned venue location is invalid. Please pin the venue on the map again.',
            'other_event_type.required_if' => 'Please specify the event type when "Other" is selected.',
        ] + EventDesignBrief::messages());

        // The design brief only steers Smart AI analysis; a curated package has its own fixed design.
        $designBrief = $isPresetBooking ? null : EventDesignBrief::fromInput($validated);
        $venueSetting = EventDesignBrief::venueSettingFromInput($validated);

        $recentDuplicateKey = 'guest_recent_token_' . md5($validated['guest_email'] . '|' . $validated['event_date'] . '|' . $validated['venue']);
        if (\Illuminate\Support\Facades\Cache::has($recentDuplicateKey)) {
            $cachedToken = \Illuminate\Support\Facades\Cache::get($recentDuplicateKey);
            return redirect()->route('guest.bookings.show', ['token' => $cachedToken])
                ->with('info', 'Your booking request has already been submitted.');
        }

        $eventType = $validated['event_type'];
        if ($eventType === 'other' && !empty($request->input('other_event_type'))) {
            $eventType = trim($request->input('other_event_type'));
        }

        $scaleContext = '';
        if (!empty($validated['table_count'])) {
            $scaleContext .= "Tables: {$validated['table_count']}. ";
        }
        if (!empty($validated['guest_count'])) {
            $scaleContext .= "Guest Count (Pax): {$validated['guest_count']}. ";
        }
        $scaleContext = trim($scaleContext) ?: "Single order/bouquet delivery - no scale multiplier applied";

        $inspirationImagePath = null;
        $analysisData = null;

        if ($bookingType === 'custom_ai') {
            try {
                $vision = app(GeminiVisionService::class);

                $currentContext = [
                    'event_type' => $eventType,
                    'event_date' => $validated['event_date'] ?? null,
                    'event_time' => $validated['event_time'] ?? null,
                    'end_time' => $validated['end_time'] ?? null,
                    'venue' => $validated['venue'] ?? null,
                    'venue_setting' => $venueSetting,
                    'scale' => $scaleContext,
                    'special_requests' => $validated['special_requests'] ?? null,
                    'design_brief' => $designBrief,
                ];
                // Only photos analyzed in this session count: browser-supplied analysis is never trusted.
                $payloads = [];
                foreach ($analysisTokens as $tok) {
                    $payload = $this->getStoredAnalysisPayload($tok);
                    if (is_array($payload) && is_array($payload['analysis'] ?? null)) {
                        if (InspirationPhotos::analysisIsStale($payload, $currentContext)) {
                            return redirect()->back()
                                ->withInput()
                                ->with('analysis_error', 'Your event details were modified after your photos were analyzed. Please re-analyze your inspiration photos before submitting.');
                        }
                        $payloads[] = $payload + ['token' => $tok];
                    }
                }

                // Fallback for a single direct file upload without prior AJAX analysis (analyzed here,
                // on the server, for every picked area).
                if ($payloads === [] && $request->hasFile('inspiration_image')) {
                    $imageFile = $request->file('inspiration_image');
                    $inspirationImagePath = $imageFile->store('bookings/inspiration-images', 'local');
                    $fullPath = Storage::disk('local')->path($inspirationImagePath);

                    $validation = $vision->validateImage($fullPath);
                    if (!$validation['is_valid']) {
                        Storage::disk('local')->delete($inspirationImagePath);
                        return redirect()->back()
                            ->withInput()
                            ->with('analysis_error', $validation['rejection_reason'] ?? 'The uploaded image is not related to flowers or event decorations.');
                    }

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
                    $result['analysis'] = $vision->normalizeAiAnalysis($result['analysis'] ?? [], $designBrief);
                    $vision->validateAnalysisPayload($result['analysis']);
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

                    $payloads[] = [
                        'analysis' => $result['analysis'],
                        'temp_path' => $inspirationImagePath,
                        'original_filename' => $imageFile->getClientOriginalName(),
                        'token' => (string) Str::uuid(),
                    ];
                }

                if ($payloads === []) {
                    return redirect()->back()
                        ->withInput()
                        ->with('analysis_error', GeminiVisionService::failureMessageForType('analysis'));
                }

                // Photos of the same areas are views of one design: combined, never added up.
                $analysisData = InspirationPhotos::analysisData($payloads, $designBrief);
                // Recalculated by the application from the combined AI rows (no default quantities); Admin only.
                $analysisData['pricing_summary'] = $vision->buildPricingSummary($analysisData['suggested_materials']);
                $inspirationImagePath = $analysisData['images'][0]['image_path'] ?? null;

                foreach ($analysisTokens as $tok) {
                    $this->forgetStoredAnalysisPayload($tok);
                }
            } catch (\Illuminate\Validation\ValidationException $e) {
                // A photo linked to an area that is no longer selected: shown on the form to fix.
                throw $e;
            } catch (\Throwable $e) {
                if (isset($inspirationImagePath) && $inspirationImagePath) {
                    Storage::disk('local')->delete($inspirationImagePath);
                }
                return redirect()->back()
                    ->withInput()
                    ->with('analysis_error', 'We couldn\'t process this request. Please try again.');
            }
        }

        $tempBooking = new \App\Models\TemporaryGuestBooking();
        $tempBooking->fill([
            'guest_name' => $validated['guest_name'],
            'guest_email' => $validated['guest_email'],
            'guest_phone' => $validated['guest_phone'],
            'guest_address' => $validated['guest_address'],
            'booking_type' => $validated['booking_type'] ?? null,
            'package_id' => $validated['package_id'] ?? null,
            'event_type' => $eventType,
            'event_date' => $validated['event_date'],
            'event_time' => $validated['event_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'venue' => $validated['venue'],
            'venue_latitude' => $validated['venue_latitude'] ?? null,
            'venue_longitude' => $validated['venue_longitude'] ?? null,
            'venue_setting' => $venueSetting,
            'table_count' => $validated['table_count'] ?? null,
            'guest_count' => $validated['guest_count'] ?? null,
            'special_requests' => $validated['special_requests'] ?? null,
            'design_brief' => $designBrief,
            'inspiration_image_path' => $inspirationImagePath,
            'analysis_data' => $analysisData,
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        $rawToken = $tempBooking->generateToken();
        $tempBooking->save();

        \Illuminate\Support\Facades\Cache::put($recentDuplicateKey, $rawToken, now()->addSeconds(15));

        try {
            \Illuminate\Support\Facades\Mail::to($tempBooking->guest_email)
                ->send(new \App\Mail\TemporaryGuestBookingMail($tempBooking, $rawToken));
        } catch (\Throwable $e) {
            Log::error('Failed to send temporary guest booking email: ' . $e->getMessage());
        }

        if ($analysisUsed && $analysisToken) {
            $this->forgetStoredAnalysisPayload($analysisToken);
        }

        return redirect()->route('guest.bookings.show', ['token' => $rawToken])
            ->with('success', 'Booking request saved! Please login to claim your request.');
    }

    protected function persistAiSuggestedMaterials(Booking $booking, array $materials): array
    {
        $totalCost = 0.0;
        $persistedMaterials = [];

        foreach ($materials as $material) {
            $itemName = trim((string) ($material['item_name'] ?? ''));
            if ($itemName === '') {
                continue;
            }

            $quantity = (float) ($material['quantity'] ?? $material['estimated_quantity'] ?? 1);
            $unitCost = (float) ($material['unit_cost_php'] ?? $material['estimated_unit_cost_php'] ?? $material['estimated_unit_cost'] ?? 0);
            if ($quantity <= 0 || $unitCost <= 0) {
                continue;
            }

            $actualUnitPrice = 0;
            // $totalCost will be calculated after we find the inventory item
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

            $materialEntry = $material;
            if ($inventoryItem) {
                $materialEntry['inventory_item_id'] = $inventoryItem->id;
                $actualUnitPrice = (float) $inventoryItem->unit_cost;
            }

            // We do NOT manually compute totalCost here anymore, it's done in the pricing service.
            // But we keep this array update for historical returns if needed.
            $totalCost += $quantity * $actualUnitPrice;

            $existingItem = null;
            if ($inventoryItem) {
                $existingItem = BookingItem::where('booking_id', $booking->id)
                                           ->where('inventory_item_id', $inventoryItem->id)
                                           ->first();
            }

            if ($existingItem) {
                $existingItem->quantity += $quantity;
                $existingItem->save();
            } else {
                $existingItem = BookingItem::create([
                    'booking_id' => $booking->id,
                    'inventory_item_id' => $inventoryItem ? $inventoryItem->id : null,
                    'item_name' => $itemName,
                    'quantity' => $quantity,
                    'quoted_unit_price' => $actualUnitPrice,
                    'ai_recommended_price' => $unitCost,
                    'is_ai_suggested' => true,
                    'procurement_status' => 'pending',
                    'suggested_order_date' => $booking->suggested_procurement_date,
                    'suggested_delivery_date' => null,
                    'notes' => 'AI suggested',
                ]);
            }

            if ($inventoryItem) {
                $booking->inventoryItems()->syncWithoutDetaching([
                    $inventoryItem->id => [
                        'quantity' => $existingItem->quantity,
                        'quoted_unit_price' => $actualUnitPrice,
                        'ai_recommended_price' => $unitCost,
                        'is_ai_suggested' => true,
                        'procurement_status' => 'pending',
                        'suggested_order_date' => $booking->suggested_procurement_date,
                        'suggested_delivery_date' => null,
                        'notes' => 'AI suggested',
                    ],
                ]);

                $this->createInventoryShortageAlert($booking, $inventoryItem, (float) $existingItem->quantity);
            }

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

        return [
            'analysis' => [
                'suggested_materials' => $templateItems,
                // Admin-only summary at the AI markup setting; these rows are Raflora costs, so AI estimates never use them.
                'pricing_summary' => app(GeminiVisionService::class)->buildPricingSummary($templateItems),
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

    protected function guestAccessToken(Booking $booking): string
    {
        if ($booking->guest_access_token) {
            return $booking->guest_access_token;
        }

        $source = trim((string) ($booking->guest_email ?? ''));
        if ($source === '') {
            $source = trim((string) ($booking->guest_phone ?? ''));
        }
        if ($source === '') {
            $source = (string) $booking->id;
        }

        return hash('sha256', $booking->id . ':' . $source);
    }

    /**
     * Display a guest booking by its secure token.
     */
    public function show(string $token): View|\Illuminate\Http\Response
    {
        $booking = \App\Models\TemporaryGuestBooking::where('claim_token_hash', hash('sha256', $token))->first();

        if (!$booking) {
            abort(404, 'Booking request not found or token invalid.');
        }

        if ($booking->isClaimed()) {
            abort(403, 'This booking request has already been claimed. Please log in.');
        }

        $analysis = null;
        $analysisMaterials = [];
        $items = [];
        $aiEstimate = null;

        // No AI item prices or totals: the guest sees per-area AI ranges (worked out here from the
        // stored analysis) or, for a package, its public catalog price (rendered by the view).
        if ($booking->booking_type === 'preset' && $booking->package_id) {
            $package = \App\Models\Package::with('images')->find($booking->package_id);
            if ($package) {
                $items = collect($package->included_items ?? [])->map(function ($item) {
                    return (object) ['name' => $item, 'quantity' => 1];
                })->toArray();
            }
        } elseif (is_array($booking->analysis_data)) {
            $analysisService = app(\App\Services\GeminiVisionService::class);
            $analysisMaterials = GeminiVisionService::withoutPrices([
                'suggested_materials' => $analysisService->coerceAnalysisMaterials($booking->analysis_data['suggested_materials'] ?? []),
            ])['suggested_materials'];
            $aiEstimate = app(AiEstimateService::class)->forAnalysis($booking->analysis_data, $booking->design_brief);
        }

        if ($booking->isExpired()) {
            return response()->view('guest.booking-analysis', [
                'booking' => $booking,
                'analysis' => $analysis,
                'analysisMaterials' => $analysisMaterials,
                'aiEstimate' => $aiEstimate,
                'items' => $items,
                'token' => $token,
                'isExpired' => true,
            ], 403);
        }

        return view('guest.booking-analysis', [
            'booking' => $booking,
            'analysis' => $analysis,
            'analysisMaterials' => $analysisMaterials,
            'aiEstimate' => $aiEstimate,
            'items' => $items,
            'token' => $token,
            'isExpired' => false,
        ]);
    }

    /**
     * Return the latest guest booking timestamp for lightweight portal polling.
     */
    public function status(string $token): JsonResponse
    {
        // 1. Check if token belongs to a permanent guest booking
        $permanentBooking = Booking::where('guest_access_token', $token)->first();
        if ($permanentBooking) {
            if ($permanentBooking->client_id !== null) {
                return response()->json([
                    'status' => 'claimed',
                    'claimed' => true,
                    'redirect_url' => route('login'),
                    'message' => 'This booking request has been claimed by a registered account. Please log in to view it.',
                ]);
            }

            return response()->json([
                'status' => $permanentBooking->status,
                'updated_at' => $permanentBooking->updated_at?->toISOString(),
                'client_status_label' => $permanentBooking->status_display_label,
            ]);
        }

        // 2. Check if token belongs to an ephemeral TemporaryGuestBooking
        $tempBooking = \App\Models\TemporaryGuestBooking::where('claim_token_hash', hash('sha256', $token))->first();
        if ($tempBooking) {
            if ($tempBooking->isClaimed() || $tempBooking->client_id !== null) {
                return response()->json([
                    'status' => 'claimed',
                    'claimed' => true,
                    'redirect_url' => route('login'),
                    'message' => 'This booking request has been claimed by a registered account. Please log in to view it.',
                ]);
            }

            if ($tempBooking->isExpired()) {
                return response()->json([
                    'status' => 'expired',
                    'is_expired' => true,
                    'updated_at' => $tempBooking->updated_at?->toISOString(),
                ]);
            }

            return response()->json([
                'status' => 'pending',
                'is_expired' => false,
                'expires_at' => $tempBooking->expires_at?->toISOString(),
                'updated_at' => $tempBooking->updated_at?->toISOString(),
            ]);
        }

        return response()->json([
            'error' => 'Booking request not found or token invalid.',
        ], 404);
    }

    protected function getStoredAnalysisPayload(string $token): ?array
    {
        return session()->get("booking_analysis_payloads.{$token}");
    }

    protected function forgetStoredAnalysisPayload(string $token): void
    {
        session()->forget("booking_analysis_payloads.{$token}");
    }

    public function submitPaymentReference(Request $request, Booking $booking): RedirectResponse
    {
        $submittedToken = (string) ($request->input('guest_access_token') ?: $request->input('guest_token') ?: '');

        if ($booking->client_id !== null) {
            abort(403, 'This booking belongs to a registered client. Please log in.');
        }

        // Validate the submitted token matches the stored UUID strictly
        if ($submittedToken === '' || !hash_equals((string) $booking->guest_access_token, $submittedToken)) {
            abort(403, 'Unauthorized access to this booking.');
        }

        // Phase 2B-6 Boundary Enforcement:
        // Unclaimed guest bookings cannot submit payment references. Only registered clients may submit payment.
        return redirect()->route('guest.booking.analysis', ['token' => $submittedToken])
            ->with('error', 'Payment cannot be submitted for an unclaimed guest booking. Please log in to your registered client account to proceed.');
    }

    public function acceptQuotation(Request $request, Booking $booking): RedirectResponse
    {
        $submittedToken = (string) ($request->input('guest_token') ?: $request->input('guest_access_token') ?: '');
        if ($booking->client_id !== null) {
            abort(403, 'This booking belongs to a registered client. Please log in.');
        }

        if ($submittedToken === '' || !hash_equals((string) $booking->guest_access_token, $submittedToken)) {
            abort(403, 'Unauthorized access to this booking.');
        }

        // Phase 2B-6 Boundary Enforcement:
        // Unclaimed guest bookings cannot accept official quotations. Only registered clients may review and accept quotations.
        return redirect()->route('guest.booking.analysis', ['token' => $submittedToken])
            ->with('error', 'Official quotations cannot be accepted by an unclaimed guest. Please log in or create an account to claim your booking first.');
    }

    /**
     * AJAX endpoint: analyse an uploaded inspiration image using Gemini Vision.
     * Mirrors BookingController::analyzeTempImage() but works without an authenticated user.
     */
    public function analyzeTempImage(Request $request)
    {
        set_time_limit(60);

        $request->validate(InspirationPhotos::uploadRules() + [
            'event_date'        => ['nullable', 'date'],
            'event_type'        => ['nullable', 'string'],
            'event_time'        => ['nullable', 'date_format:H:i'],
            'end_time'          => ['nullable', 'date_format:H:i'],
            'venue'             => ['nullable', 'string', 'max:500'],
            'venue_address'     => ['nullable', 'string', 'max:500'],
            'table_count'       => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'guest_count'       => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'special_requests'  => ['nullable', 'string', 'max:2000'],
        ] + EventDesignBrief::rules(), EventDesignBrief::messages());

        $eventType = $request->input('event_type');
        if ($eventType === 'other' && !empty($request->input('other_event_type'))) {
            $eventType = trim($request->input('other_event_type'));
        }
        $venue    = $request->input('venue', $request->input('venue_address'));
        if (!$venue && $request->filled('venue_specific') && $request->filled('venue_city')) {
            $venue = trim($request->input('venue_specific') . ', ' . $request->input('venue_city'));
        }
        // The photo is analyzed only for the picked areas it is for (checked before anything is stored).
        $photoAreas = InspirationPhotos::areasFor($request->input('photo_areas'), EventDesignBrief::fromInput($request->all()));
        $designBrief = EventDesignBrief::forPhoto(EventDesignBrief::fromInput($request->all()), $photoAreas);
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
            $vision     = app(GeminiVisionService::class);

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
                Storage::disk('local')->delete($tempPath);

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
                'event_type' => $eventType,
                'event_date' => $request->input('event_date'),
                'event_time' => $request->input('event_time'),
                'end_time' => $request->input('end_time'),
                'venue' => $venue,
                'venue_setting' => $venueSetting,
                'scale' => $scaleContext,
                'special_requests' => $request->input('special_requests'),
                'design_brief' => $designBrief,
            ];
            $hasEventContext = !empty($eventType) || !empty($request->input('event_date')) || !empty($venue);
            $contextHash = $hasEventContext ? GeminiVisionService::analysisContextHash($analysisContext) : null;
            $anaCacheKey = $uploadedHash ? "gemini_ana_{$uploadedHash}_{$originalFilename}_{$contextHash}" : null;

            if ($templateResult) {
                $analysisResult = $templateResult;
                $completedTemplateReused = true;
                $analysisSource = 'completed_booking_template';
            } elseif ($anaCacheKey && Cache::has($anaCacheKey)) {
                $analysisResult = Cache::get($anaCacheKey);
                $analysisSource = 'cache';
            } else {
                $analysisSource = 'gemini';
                $analysisResult = $vision->analyzeImageFromPath(
                    $fullPath,
                    $request->input('special_requests'),
                    $eventType,
                    $request->input('event_time'),
                    $venue,
                    $scaleContext,
                    $request->input('event_date'),
                    $request->input('end_time'),
                    designBrief: $designBrief,
                    venueSetting: $venueSetting
                );
                if ($anaCacheKey && !empty($analysisResult['analysis']['suggested_materials'])) {
                    Cache::put($anaCacheKey, $analysisResult, now()->addHours(2));
                }
            }

            $imageMeta = [
                'mime_type' => Storage::disk('local')->exists($tempPath) ? Storage::disk('local')->mimeType($tempPath) : (@mime_content_type($fullPath) ?: null),
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
                'guest',
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
                Storage::disk('local')->delete($tempPath);

                return response()->json([
                    'success' => false,
                    'message' => '⚠ We couldn\'t detect clear event decor items in this photo. Please upload a clear, well-lit image.',
                ], 422);
            }

            $originalFilename = $request->file('inspiration_image')->getClientOriginalName();

            // Enrich materials with legitimate reference image URLs if available in Raflora inventory
            foreach ($suggestedMaterials as &$materialItem) {
                $mName = trim((string) ($materialItem['item_name'] ?? ''));
                $invItem = null;
                if ($mName !== '') {
                    $invItem = InventoryItem::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($mName)])->first();
                    if (!$invItem) {
                        $normMName = $this->normalizeInventoryName($mName);
                        $invItem = InventoryItem::get()->first(function ($candidate) use ($normMName) {
                            $candNorm = $this->normalizeInventoryName($candidate->name);
                            return $candNorm === $normMName || str_contains($candNorm, $normMName) || str_contains($normMName, $candNorm);
                        });
                    }
                }

                if ($invItem && !empty($invItem->image_path) && Storage::disk('local')->exists($invItem->image_path)) {
                    $materialItem['reference_image_url'] = Storage::url($invItem->image_path);
                } else {
                    $materialItem['reference_image_url'] = null;
                }

                $altName = trim((string) ($materialItem['suggested_alternative'] ?? ''));
                if ($altName !== '') {
                    $altInvItem = InventoryItem::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($altName)])->first();
                    if ($altInvItem && !empty($altInvItem->image_path) && Storage::disk('local')->exists($altInvItem->image_path)) {
                        $materialItem['alternative_image_url'] = Storage::url($altInvItem->image_path);
                    } else {
                        $materialItem['alternative_image_url'] = null;
                    }
                } else {
                    $materialItem['alternative_image_url'] = null;
                }
            }
            unset($materialItem);
            $analysisResult['analysis']['suggested_materials'] = $suggestedMaterials;

            $pricingSummary = $vision->buildPricingSummary($suggestedMaterials);
            $rawMaterialsTotal = (float) $pricingSummary['raw_materials_total_php'];
            $markupMultiplier = (float) $pricingSummary['markup_multiplier'];
            $estimatedGrandTotal = (float) $pricingSummary['estimated_grand_total_php'];

            $analysisResult['analysis']['pricing_summary'] = $pricingSummary;
            // What each area this photo is for needs, as quantities only. Price ranges are worked
            // out for the whole request on the server (AiEstimateService), never per photo.
            if ($areaBreakdown = DecorAreaBreakdown::build($suggestedMaterials, $designBrief)) {
                $analysisResult['analysis']['area_breakdown'] = $areaBreakdown;
            }
            $analysisResult['analysis']['analysis_meta'] = GeminiVisionService::buildAnalysisMeta($analysisResult, $analysisSource, $analysisContext);

            $analysisDataJson = json_encode($analysisResult['analysis']);
            $token            = (string) Str::uuid();
            $payload          = [
                'has_context'       => $hasEventContext,
                'context_hash'      => $contextHash,
                'image_hash'       => $uploadedHash,
                'original_filename' => $originalFilename,
                'analysis'         => $analysisResult['analysis'],
                'raw_response'     => $analysisResult['raw_response'] ?? null,
                'analysis_data'    => $analysisDataJson,
                'diagnostic'       => $diagnostic,
                'raw_materials_total' => $rawMaterialsTotal,
                'markup_multiplier'   => $markupMultiplier,
                'estimated_grand_total' => $estimatedGrandTotal,
                'temp_path'        => $tempPath,
                'photo_areas'      => $photoAreas,
                'request_nonce'    => $request->input('analysis_nonce', (string) Str::uuid()),
            ];
            session()->put("booking_analysis_payloads.{$token}", $payload);

            // The browser gets no AI prices; the session payload above keeps them for Admin.
            $guestAnalysis = GeminiVisionService::withoutPrices($analysisResult['analysis']);

            return response()->json([
                'success'           => true,
                'analysis_token'    => $token,
                'context_hash'      => $contextHash,
                'original_filename' => $originalFilename,
                'analysis_temp_path' => $tempPath,
                'analysis_data'     => json_encode($guestAnalysis),
                'analysis_nonce'    => $payload['request_nonce'] ?? null,
                'photo_areas'       => $photoAreas,
                'analysis'          => $guestAnalysis,
                'color_palette'       => $analysisResult['analysis']['color_palette'] ?? null,
                'arrangement_style'   => $analysisResult['analysis']['arrangement_style'] ?? null,
                'summary'             => $analysisResult['analysis']['summary'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Guest AJAX analysis error: ' . $e->getMessage(), ['exception' => $e]);

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

    /**
     * Display the guest booking analysis page.
     */
    public function analysis(string $token): View
    {
        $booking = Booking::where('guest_access_token', $token)->firstOrFail();

        if ($booking->client_id !== null) {
            abort(403, 'This booking belongs to a registered client. Please log in.');
        }

        $analysis = $booking->aiAnalyses()->latest('analyzed_at')->first();
        $items = $booking->inventoryItems()->get();
        
        $analysisMaterials = [];
        if ($analysis && is_array($analysis->suggested_materials)) {
            $analysisMaterials = $analysis->suggested_materials;
        } elseif (is_array($booking->ai_analysis_data) && is_array($booking->ai_analysis_data['suggested_materials'] ?? null)) {
            $analysisMaterials = $booking->ai_analysis_data['suggested_materials'];
        }
        $analysisMaterials = GeminiVisionService::withoutPrices(['suggested_materials' => $analysisMaterials])['suggested_materials'];

        $bookingMessages = \App\Models\BookingMessage::where('booking_id', $booking->id)->whereIn('visibility', ['shared', 'client_admin'])->orderBy('created_at', 'asc')->get();

        // The view prices the booking from the shared quotation only (Booking::sharedQuotation).
        $activeQuotation = $booking->activeQuotation ?? $booking->acceptedQuotation;

        $quotationValidUntil = $activeQuotation?->valid_until ?? $booking->price_valid_until;
        $isExpired = $booking->status === 'quotation_sent'
            && $quotationValidUntil
            && $quotationValidUntil->endOfDay()->isPast();

        return view('guest.booking-analysis', [
            'booking' => $booking,
            'analysis' => $analysis,
            'analysisMaterials' => $analysisMaterials,
            'aiEstimate' => $booking->package_id ? null : app(AiEstimateService::class)->forAnalysis($booking->ai_analysis_data, $booking->design_brief),
            'items' => $items,
            'activeQuotation' => $activeQuotation,
            'token' => $token,
            'isExpired' => $isExpired,
            'quotationValidUntil' => $quotationValidUntil,
            'bookingMessages' => $bookingMessages,
        ]);
    }

    /**
     * Securely stream temporary inspiration images for the active guest request.
     */
    public function showTemporaryImage(Request $request, string $token, ?int $imageIndex = null)
    {
        $tempBooking = \App\Models\TemporaryGuestBooking::where('claim_token_hash', hash('sha256', $token))->first();
        if (!$tempBooking) {
            abort(404, 'Booking request not found.');
        }

        $imagePath = null;
        if ($imageIndex !== null && is_array($tempBooking->analysis_data) && !empty($tempBooking->analysis_data['images'])) {
            $images = $tempBooking->analysis_data['images'];
            if (isset($images[$imageIndex]['image_path'])) {
                $imagePath = $images[$imageIndex]['image_path'];
            }
        }

        if (!$imagePath) {
            $imagePath = $tempBooking->inspiration_image_path;
        }

        if (!$imagePath || !Storage::disk('local')->exists($imagePath)) {
            abort(404, 'Image not found.');
        }

        $mime = Storage::disk('local')->mimeType($imagePath);
        $filename = basename($imagePath);

        return Storage::disk('local')->response($imagePath, $filename, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Securely stream temporary analyzed inspiration image from the active session.
     */
    public function showAnalysisTempImage(Request $request, string $token)
    {
        $payload = $this->getStoredAnalysisPayload($token);
        if (!$payload || empty($payload['temp_path'])) {
            abort(404, 'Analysis image not found or session expired.');
        }

        $tempPath = $payload['temp_path'];
        if (!Storage::disk('local')->exists($tempPath)) {
            abort(404, 'Image not found.');
        }

        $mime = Storage::disk('local')->mimeType($tempPath) ?: 'image/jpeg';
        $filename = basename($tempPath);

        return Storage::disk('local')->response($tempPath, $filename, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}


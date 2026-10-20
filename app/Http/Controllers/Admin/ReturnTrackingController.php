<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\ClientNotification;
use App\Models\ReturnItem;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ReturnRecordService;
use Illuminate\Support\Facades\DB;

class ReturnTrackingController extends Controller
{
    protected function dispatchedQuantitiesForBooking(Booking $booking): array
    {
        return app(ReturnRecordService::class)->dispatchedQuantities($booking);
    }

    /**
     * Whether a return audit belongs to this booking's stage: after the event, or a cancelled
     * booking that had materials dispatched (or already has a return record).
     */
    protected function isEligibleForReturnAudit(Booking $booking): bool
    {
        if (in_array($booking->status, ['event_completed', 'pending_return', 'pending_resolution', 'completed'], true)) {
            // Delivery orders are sold, not rented: no return audit (App\Services\DeliveryOrderService).
            return !$booking->isDelivery();
        }

        return $booking->status === 'cancelled'
            && ($booking->returns()->exists()
                || $booking->inventoryTransactions()->whereIn('transaction_type', ['dispatch', 'dispatch_correction'])->exists());
    }

    public function index(Request $request)
    {
        // Listing only: return records are opened when the event ends (Admin booking actions) or
        // by Admin's "Start return audit" (start()), never by viewing this page.
        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', 'all');
        $staffFilter = (string) $request->input('staff', 'all');
        $inspectorFilter = (string) $request->input('inspector', 'all');
        $approvalFilter = (string) $request->input('approval', 'all');
        $eventDate = $request->input('event_date');
        $sort = (string) $request->input('sort', 'default');

        $query = AssetReturn::with([
            'booking.client',
            'inspectedByUser',
            'assignedStaff',
            'inspector',
            'approver',
            'returnItems.inventoryItem',
            'returnItems.evidences',
        ]);

        // Search booking #, return reference (e.g. RT-008), or client name/email
        if ($search !== '') {
            $cleanSearch = ltrim($search, '#');
            $rtId = null;
            if (preg_match('/^rt[-_]?0*([0-9]+)$/i', $search, $matches)) {
                $rtId = (int) $matches[1];
            }

            $query->where(function ($q) use ($search, $cleanSearch, $rtId) {
                if ($rtId !== null) {
                    $q->where('id', $rtId);
                } elseif (is_numeric($cleanSearch)) {
                    $q->where('booking_id', (int) $cleanSearch)
                      ->orWhere('id', (int) $cleanSearch);
                } else {
                    $q->whereHas('booking', function ($bQuery) use ($search) {
                        $bQuery->where('event_type', 'like', "%{$search}%")
                            ->orWhere('booking_number', 'like', "%{$search}%")
                            ->orWhere('guest_name', 'like', "%{$search}%")
                            ->orWhere('guest_email', 'like', "%{$search}%")
                            ->orWhereHas('client', function ($cQuery) use ($search) {
                                $cQuery->where('full_name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    });
                }
            });
        }

        // Status filter: Pending, Partially Returned, Completed
        if ($status !== 'all' && in_array($status, ['Pending', 'Partially Returned', 'Completed'], true)) {
            $query->where('status', $status);
        }

        // Staff filter
        if ($staffFilter === 'unassigned') {
            $query->whereNull('assigned_staff_id');
        } elseif ($staffFilter !== 'all' && is_numeric($staffFilter)) {
            $query->where('assigned_staff_id', (int) $staffFilter);
        }

        // Inspector filter
        if ($inspectorFilter === 'unassigned') {
            $query->whereNull('inspected_by');
        } elseif ($inspectorFilter !== 'all' && is_numeric($inspectorFilter)) {
            $query->where('inspected_by', (int) $inspectorFilter);
        }

        // Approval filter
        if ($approvalFilter !== 'all' && in_array($approvalFilter, ['not_required', 'pending', 'approved', 'rejected'], true)) {
            $query->where('approval_status', $approvalFilter);
        }

        // Event date filter
        if (!empty($eventDate)) {
            $query->whereHas('booking', function ($bQuery) use ($eventDate) {
                $bQuery->whereDate('event_date', $eventDate);
            });
        }

        // Sort ordering
        switch ($sort) {
            case 'latest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'booking_id':
                $query->orderBy('booking_id', 'desc');
                break;
            case 'event_date_asc':
                $query->orderBy(
                    Booking::select('event_date')->whereColumn('bookings.id', 'returns.booking_id'),
                    'asc'
                );
                break;
            case 'event_date_desc':
                $query->orderBy(
                    Booking::select('event_date')->whereColumn('bookings.id', 'returns.booking_id'),
                    'desc'
                );
                break;
            case 'default':
            default:
                $statusOrder = "CASE status WHEN 'Pending' THEN 1 WHEN 'Partially Returned' THEN 2 WHEN 'Completed' THEN 3 ELSE 4 END";
                $query->orderByRaw($statusOrder)->orderBy('created_at', 'desc');
                break;
        }

        // Five returns per page (as on Client Records); the page links keep the search, filters and sort.
        $returns = $query->paginate(5)->withQueryString();

        // Operational metrics
        $totalReturnsCount = AssetReturn::count();
        $pendingInspectionCount = AssetReturn::where('status', 'Pending')->count();
        $forApprovalCount = AssetReturn::where('approval_status', 'pending')->count();
        $completedReturnsCount = AssetReturn::where('status', 'Completed')->count();

        // Eligible users for assignments
        $eligibleStaff = User::whereIn('role', ['staff', 'admin'])->orderBy('role', 'desc')->orderBy('name')->get();
        $eligibleInspectors = User::whereIn('role', ['admin', 'staff'])->orderBy('role', 'asc')->orderBy('name')->get();

        // Eager load Action History (AuditLog records) for the paginated returns
        $returnIds = $returns->pluck('id')->filter()->all();
        $bookingIds = $returns->pluck('booking_id')->filter()->all();
        $allReturnItemIds = ReturnItem::whereIn('return_id', $returnIds)->pluck('id')->all();

        $auditLogs = AuditLog::with('user')
            ->where(function ($q) use ($returnIds, $bookingIds, $allReturnItemIds) {
                $q->where(fn ($sub) => $sub->where('entity_type', AssetReturn::class)->whereIn('entity_id', $returnIds))
                  ->orWhere(fn ($sub) => $sub->where('entity_type', Booking::class)->whereIn('entity_id', $bookingIds)->where('module', 'return_tracking'))
                  ->orWhere(fn ($sub) => $sub->where('entity_type', ReturnItem::class)->whereIn('entity_id', $allReturnItemIds));
            })
            ->latest()
            ->get();

        foreach ($returns as $ret) {
            $itemIds = $ret->returnItems->pluck('id')->all();
            $retAuditLogs = $auditLogs->filter(function ($log) use ($ret, $itemIds) {
                if ($log->entity_type === AssetReturn::class && (int) $log->entity_id === (int) $ret->id) return true;
                if ($log->entity_type === Booking::class && (int) $log->entity_id === (int) $ret->booking_id) return true;
                if ($log->entity_type === ReturnItem::class && in_array((int) $log->entity_id, $itemIds, true)) return true;
                return false;
            })->values();
            $ret->setRelation('auditLogs', $retAuditLogs);
        }

        $currentSearch = $search;
        $currentStatus = $status;
        $currentStaff = $staffFilter;
        $currentInspector = $inspectorFilter;
        $currentApproval = $approvalFilter;
        $currentEventDate = $eventDate;
        $currentSort = $sort;
        $hasActiveFilters = ($currentStatus !== 'all')
            || ($currentStaff !== 'all')
            || ($currentInspector !== 'all')
            || ($currentApproval !== 'all')
            || !empty($currentEventDate)
            || ($currentSort !== 'default');

        return view('admin.return-tracking', compact(
            'returns',
            'totalReturnsCount',
            'pendingInspectionCount',
            'forApprovalCount',
            'completedReturnsCount',
            'eligibleStaff',
            'eligibleInspectors',
            'currentSearch',
            'currentStatus',
            'currentStaff',
            'currentInspector',
            'currentApproval',
            'currentEventDate',
            'currentSort',
            'hasActiveFilters'
        ));
    }

    public function manage(Booking $booking)
    {
        $booking = Booking::with(['client', 'returns.returnItems.inventoryItem'])->findOrFail($booking->id);

        if (!$this->isEligibleForReturnAudit($booking)) {
            return redirect()->route('admin.bookings')
                ->with('error', "Return audit cannot be opened for booking #{$booking->id} because it has not completed event execution or reached an eligible return stage.");
        }

        $return = $booking->returns()->with(['returnItems.inventoryItem'])->latest()->first();
        $dispatchedQuantities = $this->dispatchedQuantitiesForBooking($booking);

        // Viewing never creates the record; Admin starts the audit explicitly (start()).
        if (!$return) {
            return view('admin.return-tracking-start', compact('booking', 'dispatchedQuantities'));
        }

        $resolutionBlockers = $this->resolutionBlockers($return, $booking);

        return view('admin.return-tracking-manage', compact('booking', 'return', 'dispatchedQuantities', 'resolutionBlockers'));
    }

    /**
     * Open the booking's return audit (ReturnRecordService): the dispatched reusable items, or an
     * empty audit Admin can close when nothing was dispatched. Repeats reuse the same record.
     */
    public function start(Booking $booking)
    {
        if (!$this->isEligibleForReturnAudit($booking)) {
            return redirect()->route('admin.bookings')
                ->with('error', "Return audit cannot be opened for booking #{$booking->id} because it has not completed event execution or reached an eligible return stage.");
        }

        $hadReturn = $booking->returns()->exists();
        $return = app(ReturnRecordService::class)->openFor($booking, allowEmpty: true);

        if (!$hadReturn) {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'return_audit_started',
                'module' => 'return_tracking',
                'details' => "Return audit {$return->reference} started for booking #{$booking->id} with " . $return->returnItems()->count() . ' dispatched item(s).',
                'entity_type' => AssetReturn::class,
                'entity_id' => $return->id,
            ]);
        }

        return redirect()->route('admin.return-tracking.manage', $booking);
    }

    public function show(AssetReturn $return)
    {
        $return->load(['booking.client', 'returnItems.inventoryItem', 'inspectedByUser']);
        $booking = $return->booking;
        
        $dispatchedQuantities = $this->dispatchedQuantitiesForBooking($booking);

        $resolutionBlockers = $this->resolutionBlockers($return, $booking);

        return view('admin.return-tracking-manage', compact('booking', 'return', 'dispatchedQuantities', 'resolutionBlockers'));
    }

    public function update(Request $request, AssetReturn $return)
    {
        $booking = $return->booking ?? Booking::find($return->booking_id);
        if ($booking && ($booking->status === 'completed' || ($booking->status === 'cancelled' && $return->status === 'Completed'))) {
            return back()->with('error', 'This return audit is completed and archived. Completed return records cannot be modified.');
        }

        $validated = $request->validate([
            'items' => 'nullable|array',
            'items.*.quantity_returned' => 'nullable|numeric|min:0',
            'items.*.quantity_good' => 'nullable|numeric|min:0',
            'items.*.quantity_damaged' => 'nullable|numeric|min:0',
            'items.*.quantity_lost' => 'nullable|numeric|min:0',
            'items.*.condition' => 'nullable|string|in:good,damaged,lost,pending,mixed',
            'items.*.charge_decision' => 'nullable|string|in:charge,no_charge,pending,charge_client',
            'items.*.damage_charge' => 'nullable|numeric|min:0',
            'items.*.charge_reason' => 'nullable|string',
            'items.*.notes' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $items = $request->input('items', []);
        $validItemUpdates = [];

        foreach ($items as $itemId => $data) {
            if (!is_array($data)) {
                continue;
            }

            $hasCondition = array_key_exists('condition', $data);
            $hasQuantity = array_key_exists('quantity_returned', $data)
                || array_key_exists('quantity_good', $data)
                || array_key_exists('quantity_damaged', $data)
                || array_key_exists('quantity_lost', $data);
            $hasCharge = array_key_exists('damage_charge', $data) || array_key_exists('charge_decision', $data);

            if ($hasCondition || $hasQuantity || $hasCharge) {
                $validItemUpdates[$itemId] = $data;
            }
        }

        if (empty($validItemUpdates)) {
            // Check if this is an authorized zero-hardware return audit
            if ($return->returnItems->isEmpty() && empty($this->dispatchedQuantitiesForBooking($return->booking))) {
                DB::transaction(function () use ($request, $return) {
                    $return->update([
                        'status' => 'Completed',
                        'notes' => $request->notes,
                        'inspected_by' => auth()->id(),
                        'return_date' => now(),
                    ]);

                    $booking = Booking::with('payments')->find($return->booking_id);
                    if ($booking) {
                        if ($booking->status === 'cancelled') {
                            AuditLog::create([
                                'user_id' => auth()->id(),
                                'action' => 'return_completed',
                                'module' => 'return_tracking',
                                'details' => 'Zero-hardware return audit completed for cancelled booking #' . $booking->id . '. Cancellation status preserved.',
                                'entity_type' => Booking::class,
                                'entity_id' => $booking->id,
                            ]);
                        } else {
                            $targetStatus = ($booking->fresh()->remaining_balance <= 0) ? 'completed' : 'event_completed';
                            if ($booking->status !== $targetStatus) {
                                $booking->setNormalizedStatus($targetStatus)->save();

                                AuditLog::create([
                                    'user_id' => auth()->id(),
                                    'action' => 'return_audit_status_applied',
                                    'module' => 'booking',
                                    'details' => 'Booking status changed to ' . $booking->status_display_label . ' after zero-hardware return audit',
                                    'entity_type' => Booking::class,
                                    'entity_id' => $booking->id,
                                ]);
                            }
                        }
                    }
                });

                return redirect()->route('admin.return-tracking')->with('success', 'Zero-hardware return audit completed successfully.');
            }

            return back()->with('error', 'No return item updates were provided. Please adjust returned quantities, conditions, or damage charges before saving.');
        }

        $dispatchedQuantities = $this->dispatchedQuantitiesForBooking($return->booking);
        foreach ($validItemUpdates as $itemId => $data) {
            $returnItem = ReturnItem::where('return_id', $return->id)->whereKey($itemId)->first();
            if (!$returnItem) {
                return back()->with('error', 'The selected return item does not belong to this return record.');
            }

            $dispatchedQty = (float) ($dispatchedQuantities[$returnItem->inventory_item_id] ?? 0);

            if (isset($data['quantity_good']) || isset($data['quantity_damaged']) || isset($data['quantity_lost'])) {
                $qGood = (float) ($data['quantity_good'] ?? 0);
                $qDamaged = (float) ($data['quantity_damaged'] ?? 0);
                $qLost = (float) ($data['quantity_lost'] ?? 0);
                if ($qGood < 0 || $qDamaged < 0 || $qLost < 0) {
                    return back()->with('error', 'Return quantities cannot be negative.');
                }
                if (($qGood + $qDamaged + $qLost) > $dispatchedQty) {
                    return back()->with('error', 'Returned quantity cannot exceed the confirmed dispatched quantity for ' . ($returnItem->inventoryItem?->name ?? 'this item') . '.');
                }
            } else {
                $quantityReturned = (float) ($data['quantity_returned'] ?? 0);
                if ($quantityReturned < 0) {
                    return back()->with('error', 'Return quantities cannot be negative.');
                }
                if ($quantityReturned > $dispatchedQty) {
                    return back()->with('error', 'Returned quantity cannot exceed the confirmed dispatched quantity for ' . ($returnItem->inventoryItem?->name ?? 'this item') . '.');
                }
            }
        }

        $newStatus = 'Partially Returned';

        try {
            DB::transaction(function () use ($items, $validItemUpdates, $request, $return, $dispatchedQuantities, &$newStatus) {
                // Lock parent booking (if present) and return record first for consistent parent-first locking hierarchy
                $booking = $return->booking_id
                    ? Booking::with('payments')->whereKey($return->booking_id)->lockForUpdate()->first()
                    : null;
                $lockedReturn = AssetReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();

                $totalDamageCharge = 0;
                $dispatchedTotal = 0;
                $accountedTotal = 0;
                $hasOutstandingItems = false;

                $allReturnItems = ReturnItem::where('return_id', $return->id)->get();

                // Normalize and pre-lock all inventory items involved in this return in deterministic ascending PK order
                $inventoryItemIds = $allReturnItems->pluck('inventory_item_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->sort()
                    ->values()
                    ->toArray();

                $lockedInventoryItems = !empty($inventoryItemIds)
                    ? InventoryItem::whereIn('id', $inventoryItemIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id')
                    : collect();

                foreach ($allReturnItems as $returnItem) {
                    $dispatchedQty = (float) ($dispatchedQuantities[$returnItem->inventory_item_id] ?? 0);
                    $dispatchedTotal += $dispatchedQty;

                    if (isset($validItemUpdates[$returnItem->id])) {
                        $data = $validItemUpdates[$returnItem->id];

                        if (isset($data['quantity_good']) || isset($data['quantity_damaged']) || isset($data['quantity_lost'])) {
                            $qGood = min($dispatchedQty, max(0.0, (float) ($data['quantity_good'] ?? 0)));
                            $qDamaged = min($dispatchedQty, max(0.0, (float) ($data['quantity_damaged'] ?? 0)));
                            $qLost = min($dispatchedQty, max(0.0, (float) ($data['quantity_lost'] ?? 0)));
                            // RET-AUD-001: quantity_returned strictly represents physically returned items
                            $quantityReturned = $qGood + $qDamaged;

                            $conditionsCount = ($qGood > 0 ? 1 : 0) + ($qDamaged > 0 ? 1 : 0) + ($qLost > 0 ? 1 : 0);
                            if ($conditionsCount > 1) {
                                $condition = 'mixed';
                            } elseif ($qDamaged > 0) {
                                $condition = 'damaged';
                            } elseif ($qLost > 0) {
                                $condition = 'lost';
                            } elseif ($qGood > 0) {
                                $condition = 'good';
                            } else {
                                $condition = strtolower((string) ($data['condition'] ?? 'pending'));
                            }
                        } else {
                            // Legacy single condition and quantity_returned
                            $condition = strtolower((string) ($data['condition'] ?? 'good'));
                            $rawQty = min($dispatchedQty, max(0.0, (float) ($data['quantity_returned'] ?? 0)));
                            if ($condition === 'good') {
                                $qGood = $rawQty;
                                $qDamaged = 0.0;
                                $qLost = 0.0;
                                $quantityReturned = $rawQty;
                            } elseif ($condition === 'damaged') {
                                $qGood = 0.0;
                                $qDamaged = $rawQty;
                                $qLost = 0.0;
                                $quantityReturned = $rawQty;
                            } elseif ($condition === 'lost') {
                                $qGood = 0.0;
                                $qDamaged = 0.0;
                                $qLost = $rawQty;
                                $quantityReturned = 0.0; // Lost items are not physically returned
                            } else {
                                $qGood = 0.0;
                                $qDamaged = 0.0;
                                $qLost = 0.0;
                                $quantityReturned = 0.0;
                            }
                        }

                        $chargeDecision = $data['charge_decision'] ?? $returnItem->charge_decision ?? 'pending';
                        if ($chargeDecision === 'charge_client') {
                            $chargeDecision = 'charge';
                        }

                        $damageCharge = 0.0;
                        $chargeReason = null;

                        if ($qDamaged == 0 && $qLost == 0 && !in_array($condition, ['damaged', 'lost', 'mixed'], true)) {
                            // All good or none damaged/lost: no charge applies
                            $chargeDecision = 'no_charge';
                            $damageCharge = 0.0;
                            $chargeReason = null;
                        } else {
                            if ($chargeDecision === 'charge') {
                                $damageCharge = max(0.0, (float) ($data['damage_charge'] ?? $returnItem->damage_charge ?? 0));
                                $chargeReason = $data['charge_reason'] ?? $returnItem->charge_reason ?? null;
                            } elseif ($chargeDecision === 'no_charge') {
                                $damageCharge = 0.0;
                                $chargeReason = $data['charge_reason'] ?? $returnItem->charge_reason ?? null;
                            } else {
                                $damageCharge = 0.0;
                                $chargeReason = null;
                            }
                        }

                        // RET-AUD-002: good stock already credited back for this booking and item comes from
                        // the inventory ledger (return-tracking movements), not from the recorded counts.
                        // Staff record returned counts for Admin review without crediting inventory, so the
                        // counts alone cannot tell whether stock was restored.
                        $creditLedger = InventoryTransaction::where('booking_id', $return->booking_id)
                            ->where('inventory_item_id', $returnItem->inventory_item_id)
                            ->whereIn('transaction_type', ['return', 'damage']);

                        $isPreSplitLegacyRow = (float) $returnItem->quantity_good == 0.0
                            && (float) $returnItem->quantity_damaged == 0.0
                            && (float) $returnItem->quantity_lost == 0.0
                            && (float) $returnItem->quantity_returned > 0;

                        if ((clone $creditLedger)->exists()) {
                            $oldGood = (float) $creditLedger->sum('quantity_change');
                        } elseif ($isPreSplitLegacyRow) {
                            // Historical single-field record credited before ledger entries were recorded.
                            $oldGood = ($returnItem->condition === 'good') ? (float) $returnItem->quantity_returned : 0.0;
                        } else {
                            // Nothing credited yet (pending item, or counts recorded by Staff awaiting Admin review).
                            $oldGood = 0.0;
                        }
                        $newGood = $qGood;

                        $returnItem->update([
                            'quantity_returned' => $quantityReturned,
                            'quantity_good' => $qGood,
                            'quantity_damaged' => $qDamaged,
                            'quantity_lost' => $qLost,
                            'condition' => $condition,
                            'damage_charge' => $damageCharge,
                            'charge_decision' => $chargeDecision,
                            'charge_reason' => $chargeReason,
                            'charge_decision_by' => in_array($chargeDecision, ['charge', 'no_charge'], true) ? auth()->id() : null,
                            'charge_decision_at' => in_array($chargeDecision, ['charge', 'no_charge'], true) ? now() : null,
                            'notes' => $data['notes'] ?? $returnItem->notes,
                        ]);

                        if ($qDamaged > 0 || $qLost > 0 || in_array($condition, ['damaged', 'lost', 'mixed'], true)) {
                            AuditLog::create([
                                'user_id' => auth()->id(),
                                'action' => 'return_damage_assessed',
                                'module' => 'return_tracking',
                                'details' => "Item '{$returnItem->inventoryItem?->name}' condition assessed as '{$condition}' (good: {$qGood}, damaged: {$qDamaged}, lost: {$qLost}, decision: {$chargeDecision}, charge: ₱" . number_format($damageCharge, 2) . ") for booking #{$return->booking_id}",
                                'entity_type' => ReturnItem::class,
                                'entity_id' => $returnItem->id,
                            ]);
                        }

                        $totalDamageCharge += $damageCharge;

                        $itemAccounted = $qGood + $qDamaged + $qLost;
                        $accountedTotal += min($dispatchedQty, $itemAccounted);

                        if ($itemAccounted < $dispatchedQty) {
                            $hasOutstandingItems = true;
                        }

                        if (($qDamaged > 0 || $qLost > 0 || in_array($condition, ['lost', 'damaged', 'mixed'], true)) && $chargeDecision === 'pending') {
                            $hasOutstandingItems = true; // Still pending admin review
                        }

                        $stockDiff = $newGood - $oldGood;

                        if ($stockDiff != 0) {
                            $inventoryItem = $lockedInventoryItems->get($returnItem->inventory_item_id);
                            if ($inventoryItem) {
                                // RET-AUD-003: Prevent downward corrections from producing negative current_stock
                                if ($stockDiff < 0) {
                                    $availableStock = (float) $inventoryItem->current_stock;
                                    $requiredDecrement = abs($stockDiff);

                                    if ($availableStock < $requiredDecrement) {
                                        $unit = $inventoryItem->unit ?? 'piece';
                                        throw new \DomainException("Insufficient available inventory stock ({$availableStock} {$unit}) for '{$inventoryItem->name}' to process a downward return correction of {$requiredDecrement} {$unit}. Current stock cannot become negative.");
                                    }
                                }

                                $inventoryItem->current_stock = (float) $inventoryItem->current_stock + $stockDiff;
                                $inventoryItem->save();

                                InventoryTransaction::create([
                                    'inventory_item_id' => $inventoryItem->id,
                                    'booking_id' => $return->booking_id,
                                    'quantity_change' => $stockDiff,
                                    'transaction_type' => $stockDiff > 0 ? 'return' : 'damage',
                                    'reason' => 'Return tracking update: ' . $condition,
                                    'performed_by' => auth()->id() ?? 1,
                                ]);
                            }
                        }
                    } else {
                        // Item not in this update request
                        $existingAccounted = (float) $returnItem->accounted_quantity;
                        if ($existingAccounted == 0 && in_array($returnItem->condition, ['good', 'damaged', 'lost'], true)) {
                            $existingAccounted = (float) $returnItem->quantity_returned;
                        }
                        $accountedTotal += min($dispatchedQty, $existingAccounted);
                        $totalDamageCharge += (float) $returnItem->damage_charge;

                        if ($existingAccounted < $dispatchedQty) {
                            $hasOutstandingItems = true;
                        }

                        if (in_array($returnItem->condition, ['lost', 'damaged', 'mixed'], true) && $returnItem->charge_decision === 'pending') {
                            $hasOutstandingItems = true;
                        }
                    }
                }

                $newStatus = ($dispatchedTotal > 0 && $accountedTotal >= $dispatchedTotal && !$hasOutstandingItems)
                    ? 'Completed'
                    : 'Partially Returned';

                $hasDamagedOrLost = $return->returnItems()->where(function ($q) {
                    $q->where('quantity_damaged', '>', 0)
                      ->orWhere('quantity_lost', '>', 0)
                      ->orWhereIn('condition', ['damaged', 'lost', 'mixed']);
                })->exists();

                $approvalStatus = $return->approval_status ?? 'not_required';
                $approvedBy = $return->approved_by;
                $approvedAt = $return->approved_at;

                if (!$hasDamagedOrLost) {
                    $approvalStatus = 'not_required';
                } else {
                    if (!$hasOutstandingItems) {
                        $approvalStatus = 'approved';
                        $approvedBy = auth()->id();
                        $approvedAt = now();
                    } elseif ($approvalStatus === 'not_required') {
                        $approvalStatus = 'pending';
                    }
                }

                $return->update([
                    'status' => $newStatus,
                    'total_damage_charge' => $totalDamageCharge,
                    'notes' => $request->notes,
                    'inspected_by' => $return->inspected_by ?? auth()->id(),
                    'return_date' => now(),
                    'approval_status' => $approvalStatus,
                    'approved_by' => $approvedBy,
                    'approved_at' => $approvedAt,
                ]);

                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'return_reconciled',
                    'module' => 'return_tracking',
                    'details' => "Return {$return->reference} inventory reconciled (status: {$newStatus}, total damage charge: ₱" . number_format($totalDamageCharge, 2) . ")",
                    'entity_type' => AssetReturn::class,
                    'entity_id' => $return->id,
                ]);

                if ($booking) {
                    if ($booking->status === 'cancelled') {
                        // Decision C: Keep cancelled status for recovered cancelled booking
                        AuditLog::create([
                            'user_id' => auth()->id(),
                            'action' => 'cancelled_booking_return_reconciled',
                            'module' => 'return_tracking',
                            'details' => 'Return audit and inventory reconciliation updated for cancelled booking #' . $booking->id . '. Cancellation status preserved.',
                            'entity_type' => Booking::class,
                            'entity_id' => $booking->id,
                        ]);
                    } else {
                        $this->applyReturnOutcomeToBooking($booking, $newStatus);
                    }
                }
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('admin.return-tracking')->with('success', 'Return record updated successfully.');
    }

    public function assign(Request $request, AssetReturn $return)
    {
        $validated = $request->validate([
            'assigned_staff_id' => 'nullable|exists:users,id',
            'inspector_id' => 'nullable|exists:users,id',
        ]);

        if (!empty($validated['assigned_staff_id'])) {
            $staff = User::findOrFail($validated['assigned_staff_id']);
            if (!in_array($staff->role, ['staff', 'admin'], true)) {
                return back()->with('error', 'Assigned staff must have an authorized staff or administrator account.');
            }
        }

        if (!empty($validated['inspector_id'])) {
            $inspector = User::findOrFail($validated['inspector_id']);
            if (!in_array($inspector->role, ['staff', 'admin'], true)) {
                return back()->with('error', 'Inspector must have an authorized staff or administrator account.');
            }
        }

        $changes = [];
        $oldStaffId = $return->assigned_staff_id;
        $newStaffId = array_key_exists('assigned_staff_id', $validated)
            ? ($validated['assigned_staff_id'] ? (int) $validated['assigned_staff_id'] : null)
            : $oldStaffId;

        $oldInspectorId = $return->inspected_by;
        $newInspectorId = array_key_exists('inspector_id', $validated)
            ? ($validated['inspector_id'] ? (int) $validated['inspector_id'] : null)
            : $oldInspectorId;

        if ($oldStaffId !== $newStaffId) {
            $return->assigned_staff_id = $newStaffId;
            $newStaffUser = $newStaffId ? User::find($newStaffId) : null;
            $staffName = $newStaffUser ? $newStaffUser->name : 'Unassigned';

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'return_staff_assigned',
                'module' => 'return_tracking',
                'details' => "Assigned staff changed to '{$staffName}' for return {$return->reference}",
                'entity_type' => AssetReturn::class,
                'entity_id' => $return->id,
            ]);
            $changes[] = 'Staff assignment updated';
        }

        if ($oldInspectorId !== $newInspectorId) {
            $return->inspected_by = $newInspectorId;
            $newInspectorUser = $newInspectorId ? User::find($newInspectorId) : null;
            $inspectorName = $newInspectorUser ? $newInspectorUser->name : 'Unassigned';

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'return_inspector_assigned',
                'module' => 'return_tracking',
                'details' => "Inspector changed to '{$inspectorName}' for return {$return->reference}",
                'entity_type' => AssetReturn::class,
                'entity_id' => $return->id,
            ]);
            $changes[] = 'Inspector assignment updated';
        }

        if (!empty($changes)) {
            $return->save();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Return assignments updated successfully.',
                'assigned_staff' => $return->assignedStaff ? ['id' => $return->assignedStaff->id, 'name' => $return->assignedStaff->name] : null,
                'inspector' => $return->inspector ? ['id' => $return->inspector->id, 'name' => $return->inspector->name] : null,
            ]);
        }

        return back()->with('success', 'Return assignments updated successfully.');
    }

    public function approve(Request $request, AssetReturn $return)
    {
        $validated = $request->validate([
            'decision' => 'required|string|in:approved,rejected',
            'reason' => 'nullable|string|max:500',
        ]);

        $decision = $validated['decision'];
        $reason = $validated['reason'] ?? null;

        // One locked transaction: the decision, the return's status and the booking's next
        // status commit together, and a repeated click finds the decision already made.
        [$level, $message] = DB::transaction(function () use ($return, $decision, $reason): array {
            $booking = $return->booking_id ? Booking::whereKey($return->booking_id)->lockForUpdate()->first() : null;
            $return = AssetReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();

            if ($return->approval_status === 'approved') {
                return ['info', 'This return damage/loss adjudication has already been approved.'];
            }

            $pendingItems = $return->returnItems()->get()->filter(fn (ReturnItem $item): bool => $this->awaitsChargeDecision($item));
            if ($pendingItems->isEmpty()) {
                return ['error', 'No damaged or lost item on this return is waiting for a charge decision.'];
            }

            $return->update([
                'approval_status' => $decision,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            if ($decision === 'approved') {
                foreach ($pendingItems as $item) {
                    $item->update([
                        'charge_decision' => ((float) $item->damage_charge > 0) ? 'charge' : 'no_charge',
                        'charge_decision_by' => auth()->id(),
                        'charge_decision_at' => now(),
                    ]);
                }

                $return->update([
                    'status' => $this->returnIsComplete($return, $booking) ? 'Completed' : 'Partially Returned',
                    'total_damage_charge' => (float) $return->returnItems()->sum('damage_charge'),
                ]);
            }

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $decision === 'approved' ? 'return_damage_approved' : 'return_damage_rejected',
                'module' => 'return_tracking',
                'details' => ($decision === 'approved' ? 'Damage/loss adjudication approved' : 'Damage/loss adjudication rejected') .
                    " by " . auth()->user()->name . " for return {$return->reference}" .
                    ((float) $return->total_damage_charge > 0 ? " (Total damage charge: ₱" . number_format($return->total_damage_charge, 2) . ")" : "") .
                    ($reason ? " Reason: {$reason}" : ""),
                'entity_type' => AssetReturn::class,
                'entity_id' => $return->id,
            ]);

            if ($decision === 'approved' && $booking) {
                $this->notifyClientOfReturnReview($booking, (float) $return->total_damage_charge);

                if ($booking->status !== 'cancelled') {
                    $this->applyReturnOutcomeToBooking($booking, $return->status);
                }
            }

            return ['success', "Adjudication decision '{$decision}' recorded successfully."];
        });

        $return->refresh();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => $level !== 'error',
                'message' => $message,
                'approval_status' => $return->approval_status,
                'approved_by' => $return->approved_by ? User::find($return->approved_by)?->name : null,
                'approved_at' => $return->approved_at?->format('M d, Y h:i A'),
            ], $level === 'error' ? 422 : 200);
        }

        return back()->with($level, $message);
    }

    /**
     * Whether a return line has damage or loss still waiting for Admin's charge decision
     * (the same rule the booking's "pending resolution" status uses).
     */
    protected function awaitsChargeDecision(ReturnItem $item): bool
    {
        return $item->charge_decision === 'pending'
            && (in_array($item->condition, ['damaged', 'lost', 'mixed'], true)
                || (float) $item->quantity_damaged > 0
                || (float) $item->quantity_lost > 0);
    }

    /**
     * Whether every dispatched unit on the return is accounted for and no charge decision is
     * pending — the rule update() applies when the audit is saved.
     */
    /**
     * What still stands between a return with damaged/lost items and completion: items without a
     * charge decision, items not fully accounted, unpaid balance (charges included) and payments
     * awaiting verification. Empty when the issues can be marked resolved.
     *
     * @return list<string>
     */
    public function resolutionBlockers(AssetReturn $return, Booking $booking): array
    {
        $blockers = [];
        $items = $return->returnItems()->with('inventoryItem')->get();

        $undecided = $items->filter(fn (ReturnItem $item): bool => $this->awaitsChargeDecision($item));
        if ($undecided->isNotEmpty()) {
            $blockers[] = 'Choose Charge Client or No Charge for: ' . $undecided->map(fn ($item) => $item->inventoryItem?->name ?? 'Unknown item')->implode(', ') . ', then Save & Reconcile.';
        }

        $dispatched = $this->dispatchedQuantitiesForBooking($booking);
        $short = $items->filter(function (ReturnItem $item) use ($dispatched): bool {
            $accounted = (float) $item->accounted_quantity;
            if ($accounted == 0 && in_array($item->condition, ['good', 'damaged', 'lost'], true)) {
                $accounted = (float) $item->quantity_returned;
            }

            return $accounted < (float) ($dispatched[$item->inventory_item_id] ?? 0);
        });
        if ($short->isNotEmpty()) {
            $blockers[] = 'Not fully accounted yet: ' . $short->map(fn ($item) => $item->inventoryItem?->name ?? 'Unknown item')->implode(', ') . '.';
        }

        if ($booking->status !== 'cancelled') {
            $fresh = $booking->fresh();
            if ((float) $fresh->remaining_balance > 0) {
                $blockers[] = '₱' . number_format((float) $fresh->remaining_balance, 2) . ' is still unpaid (damage/loss charges included). Log or verify the payment first.';
            }
            if ($fresh->payments()->where('status', 'pending')->exists()) {
                $blockers[] = 'A client payment reference is waiting for verification. Verify or reject it first.';
            }
        }

        return $blockers;
    }

    /**
     * "Mark Issues Resolved": once every damaged/lost item has a charge decision, every item is
     * accounted and all charges are paid, approve the adjudication, complete the return and move
     * the booking on (completed; the client is notified). Nothing is decided on Admin's behalf.
     */
    public function resolveIssues(AssetReturn $return)
    {
        [$level, $message] = DB::transaction(function () use ($return): array {
            $booking = $return->booking_id ? Booking::whereKey($return->booking_id)->lockForUpdate()->first() : null;
            $return = AssetReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();

            if (!$booking) {
                return ['error', 'This return is not linked to a booking.'];
            }
            if ($return->status === 'Completed' && $return->approval_status === 'approved' && in_array($booking->status, ['completed', 'cancelled'], true)) {
                return ['info', 'The damage and loss issues on this return are already resolved.'];
            }

            $hasIssues = $return->returnItems()->where(function ($q) {
                $q->where('quantity_damaged', '>', 0)->orWhere('quantity_lost', '>', 0)->orWhereIn('condition', ['damaged', 'lost', 'mixed']);
            })->exists();
            if (!$hasIssues) {
                return ['error', 'No item on this return is damaged or lost, so there is nothing to resolve. Save & Reconcile completes it.'];
            }

            if ($blockers = $this->resolutionBlockers($return, $booking)) {
                return ['error', 'Cannot resolve yet: ' . implode(' ', $blockers)];
            }

            $return->update([
                'approval_status' => 'approved',
                'approved_by' => $return->approval_status === 'approved' ? $return->approved_by : auth()->id(),
                'approved_at' => $return->approval_status === 'approved' ? $return->approved_at : now(),
                'status' => 'Completed',
                'total_damage_charge' => (float) $return->returnItems()->sum('damage_charge'),
            ]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'return_issues_resolved',
                'module' => 'return_tracking',
                'details' => "Damage/loss issues on return {$return->reference} marked resolved by " . auth()->user()->name
                    . ' (charges ₱' . number_format((float) $return->total_damage_charge, 2) . ' settled).',
                'entity_type' => AssetReturn::class,
                'entity_id' => $return->id,
            ]);

            if ($booking->status === 'cancelled') {
                return ['success', 'Damage and loss issues resolved. The return audit is completed; the booking stays Cancelled.'];
            }

            $this->applyReturnOutcomeToBooking($booking, 'Completed');

            return ['success', 'Damage and loss issues resolved. Booking #' . $booking->id . ' is now ' . $booking->fresh()->status_display_label . '.'];
        });

        return back()->with($level, $message);
    }

    protected function returnIsComplete(AssetReturn $return, ?Booking $booking): bool
    {
        $dispatchedQuantities = $booking ? $this->dispatchedQuantitiesForBooking($booking) : [];
        $dispatchedTotal = 0.0;
        $accountedTotal = 0.0;

        foreach ($return->returnItems()->get() as $returnItem) {
            $dispatchedQty = (float) ($dispatchedQuantities[$returnItem->inventory_item_id] ?? 0);
            $accounted = (float) $returnItem->accounted_quantity;
            if ($accounted == 0 && in_array($returnItem->condition, ['good', 'damaged', 'lost'], true)) {
                $accounted = (float) $returnItem->quantity_returned;
            }

            $dispatchedTotal += $dispatchedQty;
            $accountedTotal += min($dispatchedQty, $accounted);

            if ($accounted < $dispatchedQty || $this->awaitsChargeDecision($returnItem)) {
                return false;
            }
        }

        return $dispatchedTotal > 0 && $accountedTotal >= $dispatchedTotal;
    }

    /**
     * Move the booking to the stage its returns allow: pending resolution while any charge
     * decision is open, completed (or event completed with a balance) once the audit is
     * complete, otherwise pending return. On completion, unfulfilled reservations are released
     * and the client is told.
     */
    protected function applyReturnOutcomeToBooking(Booking $booking, string $returnStatus): void
    {
        $remainingBalance = $booking->fresh()->remaining_balance;

        $hasPendingResolution = $booking->returns()
            ->whereHas('returnItems', function ($q) {
                $q->where('charge_decision', 'pending')
                    ->where(function ($sub) {
                        $sub->whereIn('condition', ['damaged', 'lost', 'mixed'])
                            ->orWhere('quantity_damaged', '>', 0)
                            ->orWhere('quantity_lost', '>', 0);
                    });
            })->exists();

        if ($hasPendingResolution) {
            $targetStatus = 'pending_resolution';
        } elseif ($returnStatus === 'Completed') {
            $targetStatus = ($remainingBalance <= 0) ? 'completed' : 'event_completed';
        } else {
            $targetStatus = 'pending_return';
        }

        if ($booking->status === $targetStatus) {
            return;
        }

        $booking->setNormalizedStatus($targetStatus)->save();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'return_audit_status_applied',
            'module' => 'booking',
            'details' => 'Booking status changed to ' . $booking->status_display_label . ' after return audit',
            'entity_type' => Booking::class,
            'entity_id' => $booking->id,
        ]);

        if ($targetStatus === 'completed') {
            app(\App\Services\InventoryDispatchService::class)->releaseUnfulfilledReservations(
                $booking,
                auth()->id(),
                'Released unfulfilled reservation upon return completion for booking #' . $booking->id
            );

            $this->notifyClient($booking, 'Booking completed', 'Your return audit is complete and your booking has been marked as completed.', 'booking_update');
        }
    }

    /**
     * Tell the client the outcome of Admin's damage/loss review and any charge it added.
     */
    protected function notifyClientOfReturnReview(Booking $booking, float $totalCharge): void
    {
        $message = $totalCharge > 0
            ? 'Raflora reviewed the materials returned for booking #' . $booking->id . '. Damage and loss charges of ₱' . number_format($totalCharge, 2)
                . ' were added; your remaining balance is ₱' . number_format((float) $booking->fresh()->remaining_balance, 2) . '.'
            : 'Raflora reviewed the materials returned for booking #' . $booking->id . '. No damage or loss charges apply.';

        $this->notifyClient($booking, 'Return review complete', $message, 'payment_update');
    }

    /**
     * client_id references clients.id; notifications belong to the client's user account.
     */
    protected function notifyClient(Booking $booking, string $title, string $message, string $type): void
    {
        $clientUser = $booking->client && filter_var($booking->client->email, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $booking->client->email)->first()
            : null;

        if ($clientUser) {
            ClientNotification::create([
                'user_id' => $clientUser->id,
                'booking_id' => $booking->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'is_read' => false,
            ]);
        }
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingController extends Controller
{
    /**
     * Display the admin settings screen.
     */
    public function index(): View
    {
        return view('admin.settings', [
            'accounts' => User::whereIn('role', ['admin', 'staff'])->orderBy('name')->get(),
            'auditLogs' => AuditLog::with('user')->latest()->limit(200)->get(),
        ]);
    }

    /**
     * Update business settings with validation, impact preview, and explicit confirmation.
     */
    public function update(Request $request): RedirectResponse
    {
        if (auth()->user()?->role !== 'admin') {
            abort(403, 'Unauthorized. Administrator access required.');
        }

        $labourMax = $request->input('ai_labour_mode') === 'percentage' ? Setting::MAX_AI_LABOUR_PERCENTAGE : Setting::MAX_AI_LABOUR_FIXED;
        $validated = $request->validate([
            'downpayment_percentage' => 'nullable|numeric|min:0|max:100',
            'long_term_booking_threshold_days' => 'nullable|integer|min:1|max:730',
            'price_reconfirmation_threshold_days' => 'nullable|integer|min:1|max:365',
            'ai_estimate_markup' => ['nullable', 'numeric', 'decimal:0,2', 'min:' . Setting::MIN_AI_ESTIMATE_MARKUP, 'max:' . Setting::MAX_AI_ESTIMATE_MARKUP],
            'ai_labour_mode' => ['nullable', Rule::in(array_keys(Setting::AI_LABOUR_MODES))],
            'ai_labour_value' => ['nullable', 'required_with:ai_labour_mode', 'numeric', 'decimal:0,2', 'min:0', 'max:' . $labourMax],
            'change_reason' => 'nullable|string|max:500',
            'confirmed' => 'nullable|boolean',
        ], [
            'ai_estimate_markup.min' => 'The AI estimate markup must be at least ' . Setting::MIN_AI_ESTIMATE_MARKUP . '×.',
            'ai_estimate_markup.max' => 'The AI estimate markup may not exceed ' . Setting::MAX_AI_ESTIMATE_MARKUP . '×.',
            'ai_estimate_markup.decimal' => 'The AI estimate markup may have at most two decimal places.',
            'ai_labour_mode.in' => 'Choose a fixed amount or a percentage for labour & setup.',
            'ai_labour_value.required_with' => 'Enter the labour & setup amount or percentage.',
            'ai_labour_value.max' => $request->input('ai_labour_mode') === 'percentage'
                ? 'The labour & setup percentage may not exceed 100%.'
                : 'The labour & setup amount is too large.',
            'ai_labour_value.decimal' => 'The labour & setup value may have at most two decimal places.',
        ]);

        $currDownpayment = (float) Setting::getSetting(Setting::KEY_DOWNPAYMENT_PERCENTAGE, Setting::DEFAULT_DOWNPAYMENT_PERCENTAGE);
        $currLongTerm = Setting::getLongTermBookingThresholdDays();
        $currPriceReconfirm = Setting::getPriceReconfirmationThresholdDays();

        $newLongTerm = isset($validated['long_term_booking_threshold_days']) ? (int) $validated['long_term_booking_threshold_days'] : $currLongTerm;
        $newPriceReconfirm = isset($validated['price_reconfirmation_threshold_days']) ? (int) $validated['price_reconfirmation_threshold_days'] : $currPriceReconfirm;

        // Logical range check: Reconfirmation threshold cannot exceed long-term booking threshold
        if ($newPriceReconfirm > $newLongTerm) {
            return back()->withErrors([
                'price_reconfirmation_threshold_days' => 'The price reconfirmation threshold (' . $newPriceReconfirm . ' days) cannot be greater than the long-term booking threshold (' . $newLongTerm . ' days).'
            ])->withInput();
        }

        $hasThresholdChange = ($newLongTerm !== $currLongTerm) || ($newPriceReconfirm !== $currPriceReconfirm);
        $isConfirmed = $request->boolean('confirmed');

        // If threshold changes are submitted without explicit confirmation, present impact preview
        if ($hasThresholdChange && !$isConfirmed) {
            $impacts = [];
            if ($newLongTerm !== $currLongTerm) {
                $impacts[] = "Long-term booking threshold will change from {$currLongTerm} days to {$newLongTerm} days. Events scheduled more than {$newLongTerm} days out will have newly issued quotations classified as tentative pricing. Existing historical quotation snapshots remain unchanged.";
            }
            if ($newPriceReconfirm !== $currPriceReconfirm) {
                $impacts[] = "Price reconfirmation will become due {$newPriceReconfirm} days before the event instead of {$currPriceReconfirm} days. This changes the timing of future scheduled reconfirmation alerts. It does not automatically change quotation prices or booking statuses.";
            }

            return back()->with([
                'impact_preview' => [
                    'impacts' => $impacts,
                    'pending_values' => [
                        'downpayment_percentage' => $validated['downpayment_percentage'] ?? $currDownpayment,
                        'long_term_booking_threshold_days' => $newLongTerm,
                        'price_reconfirmation_threshold_days' => $newPriceReconfirm,
                        'change_reason' => $validated['change_reason'] ?? '',
                    ],
                ],
            ])->withInput();
        }

        // Apply changes
        $changesMade = [];
        $reason = trim((string) ($validated['change_reason'] ?? ''));

        if (isset($validated['downpayment_percentage'])) {
            $newDownpayment = (float) $validated['downpayment_percentage'];
            if ($newDownpayment !== $currDownpayment) {
                Setting::setSetting(Setting::KEY_DOWNPAYMENT_PERCENTAGE, $newDownpayment, 'float');
                AuditLog::record(
                    auth()->id(),
                    'setting_updated',
                    "Downpayment percentage updated from {$currDownpayment}% to {$newDownpayment}%. " . ($reason ? "Reason: {$reason}" : ''),
                    'admin_security',
                    [
                        'setting' => Setting::KEY_DOWNPAYMENT_PERCENTAGE,
                        'old_value' => $currDownpayment,
                        'new_value' => $newDownpayment,
                        'reason' => $reason ?: null,
                    ]
                );
                $changesMade[] = 'Downpayment percentage';
            }
        }

        if (isset($validated['long_term_booking_threshold_days'])) {
            if ($newLongTerm !== $currLongTerm) {
                Setting::setSetting(Setting::KEY_LONG_TERM_THRESHOLD, $newLongTerm, 'integer');
                AuditLog::record(
                    auth()->id(),
                    'setting_updated',
                    "Long-term booking threshold updated from {$currLongTerm} days to {$newLongTerm} days. " . ($reason ? "Reason: {$reason}" : ''),
                    'admin_security',
                    [
                        'setting' => Setting::KEY_LONG_TERM_THRESHOLD,
                        'old_value' => $currLongTerm,
                        'new_value' => $newLongTerm,
                        'reason' => $reason ?: null,
                    ]
                );
                $changesMade[] = 'Long-term booking threshold';
            }
        }

        if (isset($validated['price_reconfirmation_threshold_days'])) {
            if ($newPriceReconfirm !== $currPriceReconfirm) {
                Setting::setSetting(Setting::KEY_PRICE_RECONFIRMATION_THRESHOLD, $newPriceReconfirm, 'integer');
                AuditLog::record(
                    auth()->id(),
                    'setting_updated',
                    "Price reconfirmation threshold updated from {$currPriceReconfirm} days to {$newPriceReconfirm} days. " . ($reason ? "Reason: {$reason}" : ''),
                    'admin_security',
                    [
                        'setting' => Setting::KEY_PRICE_RECONFIRMATION_THRESHOLD,
                        'old_value' => $currPriceReconfirm,
                        'new_value' => $newPriceReconfirm,
                        'reason' => $reason ?: null,
                    ]
                );
                $changesMade[] = 'Price reconfirmation threshold';
            }
        }

        if (isset($validated['ai_estimate_markup'])) {
            $currMarkup = Setting::aiEstimateMarkup();
            $newMarkup = round((float) $validated['ai_estimate_markup'], 2);
            if ($newMarkup !== $currMarkup) {
                Setting::setSetting(Setting::KEY_AI_ESTIMATE_MARKUP, $newMarkup, 'float');
                AuditLog::record(
                    auth()->id(),
                    'setting_updated',
                    'AI estimate markup updated from ' . ($currMarkup ?? 'an invalid value') . "× to {$newMarkup}×. Issued quotations are not affected. " . ($reason ? "Reason: {$reason}" : ''),
                    'admin_security',
                    [
                        'setting' => Setting::KEY_AI_ESTIMATE_MARKUP,
                        'old_value' => $currMarkup,
                        'new_value' => $newMarkup,
                        'reason' => $reason ?: null,
                    ]
                );
                $changesMade[] = 'AI estimate markup';
            }
        }

        if (isset($validated['ai_labour_mode'])) {
            $currLabour = Setting::aiLabourAllowance();
            $newLabour = ['mode' => $validated['ai_labour_mode'], 'value' => round((float) $validated['ai_labour_value'], 2)];
            if ($newLabour !== $currLabour) {
                Setting::setSetting(Setting::KEY_AI_LABOUR_MODE, $newLabour['mode'], 'string');
                Setting::setSetting(Setting::KEY_AI_LABOUR_VALUE, $newLabour['value'], 'float');
                $describe = fn (array $labour) => match ($labour['mode']) {
                    null => 'not set',
                    'invalid' => 'an invalid value',
                    'percentage' => $labour['value'] . '% of AI material cost',
                    default => '₱' . number_format((float) $labour['value'], 2) . ' fixed',
                };
                AuditLog::record(
                    auth()->id(),
                    'setting_updated',
                    'AI estimate labour & setup updated from ' . $describe($currLabour) . ' to ' . $describe($newLabour) . '. Issued quotations are not affected. ' . ($reason ? "Reason: {$reason}" : ''),
                    'admin_security',
                    [
                        'setting' => Setting::KEY_AI_LABOUR_MODE,
                        'old_value' => $currLabour,
                        'new_value' => $newLabour,
                        'reason' => $reason ?: null,
                    ]
                );
                $changesMade[] = 'AI estimate labour & setup';
            }
        }

        $msg = !empty($changesMade)
            ? 'Settings updated successfully (' . implode(', ', $changesMade) . ').'
            : 'Settings updated successfully.';

        return back()->with('success', $msg);
    }
}

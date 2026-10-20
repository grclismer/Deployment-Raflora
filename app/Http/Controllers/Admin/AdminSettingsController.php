<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\TrustedDeviceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSettingsController extends Controller
{
    /**
     * Display the Admin Account Settings screen.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $currentSessionId = $request->session()->getId();

        // Database-backed active sessions for the current authenticated Admin
        $sessions = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session) use ($currentSessionId) {
                return (object) [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address ?: 'Unknown IP',
                    'user_agent' => $session->user_agent,
                    'device_label' => self::parseUserAgent($session->user_agent),
                    'device_icon' => self::getDeviceIcon($session->user_agent),
                    'last_activity' => Carbon::createFromTimestamp($session->last_activity),
                    'is_current' => $session->id === $currentSessionId,
                ];
            });

        // Ensure the current session is visible even if not yet flushed to the database
        if ($currentSessionId && $sessions->where('is_current', true)->isEmpty()) {
            $sessions->prepend((object) [
                'id' => $currentSessionId,
                'ip_address' => $request->ip() ?: '127.0.0.1',
                'user_agent' => $request->userAgent(),
                'device_label' => self::parseUserAgent($request->userAgent()),
                'device_icon' => self::getDeviceIcon($request->userAgent()),
                'last_activity' => now(),
                'is_current' => true,
            ]);
        }

        // Trusted devices for the current authenticated Admin
        $rawCookie = $request->cookie(TrustedDeviceService::COOKIE_NAME);
        $currentDeviceHash = (!empty($rawCookie) && is_string($rawCookie))
            ? hash('sha256', $rawCookie)
            : null;

        $trustedDevices = $user->trustedDevices()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(function ($device) use ($currentDeviceHash) {
                $status = match (true) {
                    $device->revoked_at !== null => 'Revoked',
                    $device->expires_at->isPast() => 'Expired',
                    default => 'Active',
                };

                return (object) [
                    'id' => $device->id,
                    'device_name' => $device->device_name ?: 'Verified Device',
                    'last_used_at' => $device->last_used_at,
                    'expires_at' => $device->expires_at,
                    'revoked_at' => $device->revoked_at,
                    'status' => $status,
                    'is_usable' => $device->isUsable(),
                    'is_current' => ($currentDeviceHash !== null && $device->device_token_hash === $currentDeviceHash),
                ];
            });

        return view('admin.settings', [
            'user' => $user,
            'accounts' => User::whereIn('role', ['admin', 'staff'])->orderBy('name')->get(),
            'auditLogs' => AuditLog::with('user')->latest()->limit(200)->get(),
            'activeSessions' => $sessions,
            'trustedDevices' => $trustedDevices,
            'currentSessionId' => $currentSessionId,
        ]);
    }

    /**
     * The whole audit log as a CSV file (newest first), from the Audit Trail dialog.
     */
    public function exportAuditLog(): StreamedResponse
    {
        $filename = 'raflora_audit_log_' . now()->format('Y-m-d') . '.csv';
        // Cells starting with = + - @ would run as formulas in spreadsheet apps.
        $safe = fn ($value) => preg_match('/^[=\+\-@]/', (string) $value) ? "'" . $value : (string) $value;

        return response()->stream(function () use ($safe) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows names correctly.

            fputcsv($handle, ['Raflora Enterprises - Audit Log']);
            fputcsv($handle, ['Generated', now()->timezone('Asia/Manila')->format('M j, Y g:i A')]);
            fputcsv($handle, []);
            fputcsv($handle, ['Time', 'User', 'Role', 'Action', 'Module', 'Details', 'IP Address']);

            AuditLog::with('user')->orderByDesc('created_at')->orderByDesc('id')->chunk(500, function ($logs) use ($handle, $safe) {
                foreach ($logs as $log) {
                    $details = is_array($log->details) ? ($log->details['message'] ?? json_encode($log->details)) : ($log->details ?? '');
                    fputcsv($handle, [
                        optional($log->created_at)->timezone('Asia/Manila')->format('Y-m-d h:i A'),
                        $safe($log->user?->name ?? 'System'),
                        $log->user ? ucfirst((string) $log->user->role) : '',
                        $safe(ucwords(str_replace('_', ' ', $log->action ?? 'Activity'))),
                        $safe((string) $log->module),
                        $safe((string) $details),
                        $safe((string) $log->ip_address),
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Update the administrator's profile information.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'profile_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'remove_profile_image' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Full name is required.',
            'profile_image.image' => 'The uploaded file must be an image.',
            'profile_image.mimes' => 'The profile image must be a file of type: jpeg, png, jpg, webp.',
            'profile_image.max' => 'The profile image may not be greater than 2048 kilobytes.',
        ]);

        DB::transaction(function () use ($user, $request, $validated) {
            $oldImage = $user->profile_image;

            if ($request->boolean('remove_profile_image')) {
                if ($oldImage && Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }
                $user->profile_image = null;
            } elseif ($request->hasFile('profile_image')) {
                $newPath = $request->file('profile_image')->store('profile-images', 'public');
                if ($oldImage && Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }
                $user->profile_image = $newPath;
            }

            $nameParts = explode(' ', trim($validated['name']), 2);
            $user->name = $validated['name'];
            $user->first_name = $nameParts[0];
            $user->last_name = $nameParts[1] ?? '';
            $user->mobile_number = $validated['mobile_number'] ?? null;
            $user->address = $validated['address'] ?? null;
            $user->save();
        });

        AuditLog::record(
            $user->id,
            'profile_updated',
            'Administrator profile details updated.',
            'admin_security'
        );

        return redirect()->route('admin.settings')
            ->with('success', 'Profile information updated successfully.');
    }

    /**
     * Update the administrator's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8'],
        ], [
            'current_password.current_password' => 'The provided current password is incorrect.',
            'password.min' => 'The new password must be at least 8 characters.',
            'password.confirmed' => 'The new password confirmation does not match.',
        ]);

        $user = $request->user();
        $currentSessionId = $request->session()->getId();

        // Like OTP reset and recovery, a new password signs out the Admin's other sessions and
        // revokes their trusted devices; this session stays signed in. With the database session
        // driver all three writes share one transaction, so a failure leaves the old password,
        // sessions and devices in place.
        try {
            [$sessionCount, $deviceCount] = DB::transaction(function () use ($user, $validated, $currentSessionId) {
                $user->update(['password' => Hash::make($validated['password'])]);

                $sessionCount = DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $currentSessionId)
                    ->delete();

                $deviceCount = TrustedDevice::where('user_id', $user->id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);

                return [$sessionCount, $deviceCount];
            });
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.settings', ['tab' => 'security'])
                ->with('error', 'Your password was not changed because your other sessions and trusted devices could not be signed out. Please try again.');
        }

        // This browser's trust cookie no longer matches a usable device.
        app(TrustedDeviceService::class)->clearTrustedDeviceCookie();

        AuditLog::record($user->id, 'password_updated', 'Password updated via Account Settings.', 'admin_security');
        AuditLog::record($user->id, 'sessions_revoked', "Signed out {$sessionCount} other active session(s) after a password change.", 'admin_security');
        AuditLog::record($user->id, 'trusted_devices_revoked', "Revoked {$deviceCount} trusted device(s) after a password change.", 'admin_security');

        return redirect()->route('admin.settings')
            ->with('success', 'Your password has been updated. Your other sessions were signed out and your trusted devices were revoked.');
    }

    /**
     * Revoke all other active database sessions for the authenticated administrator.
     */
    public function revokeOtherSessions(Request $request): RedirectResponse
    {
        $user = $request->user();
        $currentSessionId = $request->session()->getId();

        $deletedCount = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        AuditLog::record(
            $user->id,
            'sessions_revoked',
            "Signed out {$deletedCount} other active session(s).",
            'admin_security'
        );

        return redirect()->route('admin.settings')
            ->with('success', 'All other active sessions have been signed out.');
    }

    /**
     * Revoke a specific trusted device owned by the authenticated administrator.
     */
    public function revokeTrustedDevice(Request $request): RedirectResponse
    {
        $request->validate([
            'device_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $device = TrustedDevice::where('id', $request->input('device_id'))
            ->where('user_id', $user->id)
            ->firstOrFail();

        $device->revoke();

        // Clear browser cookie if this was the current device
        $rawCookie = $request->cookie(TrustedDeviceService::COOKIE_NAME);
        if (!empty($rawCookie) && is_string($rawCookie) && hash('sha256', $rawCookie) === $device->device_token_hash) {
            app(TrustedDeviceService::class)->clearTrustedDeviceCookie();
        }

        AuditLog::record(
            $user->id,
            'trusted_device_revoked',
            "Trusted device '{$device->device_name}' revoked.",
            'admin_security'
        );

        return redirect()->route('admin.settings')
            ->with('success', 'Trusted device revoked successfully.');
    }

    /**
     * Store a new admin or staff team account.
     */
    public function storeAccount(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);

        // Own error bag: the Add Account form shares field names (name, password) with the
        // Admin's profile and password forms, so its errors must not be read as theirs.
        $validated = $request->validateWithBag('createAccount', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            // Raflora supports exactly one Admin (S-01D single-admin bootstrap & recovery);
            // the operations panel can only create Staff accounts.
            'role' => ['required', 'in:staff'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $newUser = User::create($validated);

        AuditLog::record(
            $request->user()->id,
            'team_account_created',
            "Created {$newUser->role} account for {$newUser->email}.",
            'admin_security'
        );

        return back()->with('success', 'Account created successfully.');
    }

    /**
     * Parse User-Agent into human-readable Operating System and Browser label.
     */
    public static function parseUserAgent(?string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'Unknown Device';
        }

        $browser = match (true) {
            str_contains($userAgent, 'Edg')     => 'Edge',
            str_contains($userAgent, 'Chrome')  => 'Chrome',
            str_contains($userAgent, 'Firefox') => 'Firefox',
            str_contains($userAgent, 'Safari')  => 'Safari',
            str_contains($userAgent, 'Opera')   => 'Opera',
            default                             => 'Browser',
        };

        $os = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac')     => 'macOS',
            str_contains($userAgent, 'Linux')   => 'Linux',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone')  => 'iOS',
            str_contains($userAgent, 'iPad')    => 'iPadOS',
            default                             => 'Device',
        };

        return "{$os} • {$browser}";
    }

    /**
     * Get appropriate FontAwesome icon for User-Agent.
     */
    public static function getDeviceIcon(?string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'fa-desktop';
        }
        if (str_contains($userAgent, 'Android') || str_contains($userAgent, 'iPhone')) {
            return 'fa-mobile-screen';
        }
        if (str_contains($userAgent, 'iPad')) {
            return 'fa-tablet-screen-button';
        }
        return 'fa-desktop';
    }
}

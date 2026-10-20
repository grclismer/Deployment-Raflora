<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingShortageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Booking Shortage Report (Admin Notifications → Resolve Inventory Shortages): one booking's
 * materials to canvass or procure, as a CSV download or a printable page (Save as PDF).
 */
class BookingShortageExportController extends Controller
{
    public function __construct(private BookingShortageService $shortages)
    {
    }

    public function csv(Booking $booking): StreamedResponse|RedirectResponse
    {
        $report = $this->reportData($booking);
        if (empty($report['rows'])) {
            return back()->with('info', 'Booking #' . $booking->id . ' has no shortage items to export.');
        }

        $filename = 'booking_' . $booking->id . '_shortage_report_' . now()->format('Y-m-d') . '.csv';

        return response()->stream(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows names correctly.

            fputcsv($handle, ['Booking Shortage Report']);
            fputcsv($handle, ['Booking No.', '#' . $report['booking']->id]);
            fputcsv($handle, ['Event / Booking', $this->safe($report['event'])]);
            fputcsv($handle, ['Client', $this->safe($report['client'])]);
            fputcsv($handle, ['Event Date', $report['eventDate']]);
            fputcsv($handle, ['Report Generated', $report['generatedAt']]);
            fputcsv($handle, ['Prepared By', $this->safe($report['preparedBy'])]);
            fputcsv($handle, ['Shortage Items', $report['counts']['total'], 'Items to Canvass', $report['counts']['canvass'], 'Items to Procure', $report['counts']['procure']]);
            fputcsv($handle, []);

            fputcsv($handle, ['No.', 'Material / Item', 'Category', 'Required Qty', 'Available Qty', 'Shortage Qty', 'Unit', 'Priority', 'Canvass / Supplier Notes', 'Status']);
            foreach ($report['rows'] as $index => $row) {
                fputcsv($handle, [
                    $index + 1,
                    $this->safe($row['name']),
                    $this->safe($row['category']),
                    $this->quantity($row['required']),
                    $this->quantity($row['available']),
                    $this->quantity($row['shortage']),
                    $this->safe($row['unit']),
                    $row['priority'],
                    $this->safe($row['supplier']),
                    $row['status'],
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }

    public function report(Booking $booking): View|RedirectResponse
    {
        $report = $this->reportData($booking);
        if (empty($report['rows'])) {
            return back()->with('info', 'Booking #' . $booking->id . ' has no shortage items to report.');
        }

        return view('admin.booking-shortage-report', $report);
    }

    private function reportData(Booking $booking): array
    {
        $booking->loadMissing(['client', 'package', 'bookingItems.inventoryItem']);
        $rows = $this->shortages->reportRowsFor($booking);
        $canvass = count(array_filter($rows, fn (array $row) => $row['status'] === 'To canvass'));

        return [
            'booking' => $booking,
            'event' => ucfirst((string) ($booking->event_type ?: 'Event')) . ($booking->package ? ' — ' . $booking->package->title : ''),
            'client' => $booking->client?->full_name ?? $booking->guest_name ?? '—',
            'eventDate' => $booking->event_date?->format('M j, Y') ?? '—',
            'generatedAt' => now()->format('M j, Y g:i A'),
            'preparedBy' => Auth::user()?->name ?? '—',
            'rows' => $rows,
            'counts' => ['total' => count($rows), 'canvass' => $canvass, 'procure' => count($rows) - $canvass],
        ];
    }

    private function quantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /**
     * Text a spreadsheet would read as a formula is prefixed with an apostrophe (as the inventory export does).
     */
    private function safe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=\+\-@]/', $value) ? "'" . $value : $value;
    }
}

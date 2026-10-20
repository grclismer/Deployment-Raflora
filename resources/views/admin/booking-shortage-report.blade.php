{{-- Booking Shortage Report: printable canvassing / procurement list for one booking (BookingShortageExportController). --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Shortage Report · Booking #{{ $booking->id }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('rafloralogo.ico') }}">
    <style>
        @page {
            size: A4 landscape;
            margin: 12mm 12mm 14mm;
            @bottom-left { content: "RAFLORA ENTERPRISES  •  BOOKING SHORTAGE REPORT"; font: 8px Arial, sans-serif; color: #64748b; }
            @bottom-right { content: "Page " counter(page); font: 8px Arial, sans-serif; color: #64748b; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef2f7; color: #0f172a; font: 11px/1.4 Arial, Helvetica, sans-serif; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; max-width: 1100px; margin: 16px auto 0; padding: 0 16px; }
        .toolbar a, .toolbar button { display: inline-flex; align-items: center; gap: 6px; border-radius: 8px; padding: 8px 14px; font: 600 13px Arial, sans-serif; cursor: pointer; text-decoration: none; }
        .btn-primary { background: #0b1e43; color: #fff; border: 1px solid #0b1e43; }
        .btn-secondary { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
        .sheet { max-width: 1100px; margin: 12px auto 24px; background: #fff; border-top: 10px solid #0b1e43; box-shadow: 0 2px 12px rgba(15, 23, 42, .12); padding: 22px 26px 18px; }
        .head { display: flex; gap: 20px; align-items: center; }
        .logo { width: 150px; height: 74px; display: flex; align-items: center; justify-content: center; border: 1.5px solid #8fbf8f; background: #f1f8ef; padding: 6px; flex-shrink: 0; }
        .logo img { max-width: 100%; max-height: 100%; object-fit: contain; }
        h1 { margin: 0; font-size: 24px; color: #0b1e43; }
        .sub { margin: 3px 0 0; color: #475569; }
        .tag { margin: 6px 0 0; font-size: 9px; font-weight: 700; letter-spacing: .08em; color: #0b1e43; }
        .grid { display: grid; border: 1px solid #dbe3ee; margin-top: 16px; }
        .grid.info { grid-template-columns: 1fr 2fr 1.6fr 1.1fr 1.1fr; }
        .grid.summary { grid-template-columns: repeat(4, 1fr); margin-top: 10px; }
        .grid > div { padding: 8px 10px; border-right: 1px solid #dbe3ee; }
        .grid > div:last-child { border-right: 0; }
        .grid.info > div { background: #f6f8fb; }
        .label { display: block; font-size: 9px; font-weight: 700; letter-spacing: .05em; color: #334155; text-transform: uppercase; }
        .value { display: block; margin-top: 4px; font-size: 12px; font-weight: 700; color: #0f172a; }
        h2 { margin: 20px 0 2px; font-size: 14px; color: #0b1e43; }
        .hint { margin: 0 0 8px; color: #64748b; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        thead th { background: #0b1e43; color: #fff; font-size: 9px; font-weight: 700; text-transform: uppercase; text-align: left; padding: 8px 6px; border: 1px solid #0b1e43; }
        tbody td { padding: 9px 6px; border: 1px solid #dbe3ee; vertical-align: top; }
        tbody tr { break-inside: avoid; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        td.short, th.short { background: #fdf0d5; }
        thead th.short { background: #0b1e43; }
        td.short { font-weight: 700; color: #9a3412; }
        .high { color: #be123c; font-weight: 700; }
        .notes-cell { min-width: 160px; color: #475569; }
        .checklist { margin-top: 10px; border: 1px solid #dbe3ee; }
        .checklist .bar { background: #eef6ea; padding: 7px 10px; font-size: 10px; }
        .checklist .bar strong { margin-right: 6px; }
        .checklist .bar span { margin-right: 10px; }
        .checklist .notes { padding: 7px 10px 18px; font-size: 10px; color: #475569; }
        .checklist .notes .line { border-bottom: 1px solid #cbd5e1; height: 18px; }
        .foot { display: flex; justify-content: space-between; border-top: 1px solid #dbe3ee; margin-top: 18px; padding-top: 6px; font-size: 9px; color: #64748b; }
        @media print {
            body { background: #fff; }
            .toolbar, .foot { display: none; }
            .sheet { max-width: none; margin: 0; box-shadow: none; padding: 0; border-top-width: 8px; }
            thead { display: table-header-group; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        @media (max-width: 760px) {
            .sheet { padding: 16px; overflow-x: auto; }
            .head { flex-direction: column; align-items: flex-start; }
            .grid.info, .grid.summary { grid-template-columns: 1fr 1fr; }
            .grid > div { border-bottom: 1px solid #dbe3ee; }
            table { min-width: 860px; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('admin.notifications') }}" class="btn-secondary">← Back to Notifications</a>
        <a href="{{ route('admin.notifications.booking-shortages.export', $booking) }}" class="btn-secondary">Export CSV</a>
        <button type="button" class="btn-primary" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <main class="sheet">
        <div class="head">
            <div class="logo"><img src="{{ asset('assets/images/raflora-logo-nobackground.png') }}" alt="Raflora Enterprises"></div>
            <div>
                <h1>Booking Shortage Report</h1>
                <p class="sub">Materials to canvass or procure because they are unavailable or insufficient in inventory.</p>
                <p class="tag">ADMIN / PROCUREMENT WORKING LIST</p>
            </div>
        </div>

        <div class="grid info">
            <div><span class="label">Booking No.</span><span class="value">#{{ $booking->id }}</span></div>
            <div><span class="label">Event / Booking Name</span><span class="value">{{ $event }}</span></div>
            <div><span class="label">Client</span><span class="value">{{ $client }}</span></div>
            <div><span class="label">Event Date</span><span class="value">{{ $eventDate }}</span></div>
            <div><span class="label">Report Generated</span><span class="value">{{ $generatedAt }}</span></div>
        </div>

        <div class="grid summary">
            <div><span class="label">Shortage Items</span><span class="value">{{ $counts['total'] }}</span></div>
            <div><span class="label">Items to Canvass</span><span class="value">{{ $counts['canvass'] }}</span></div>
            <div><span class="label">Items to Procure</span><span class="value">{{ $counts['procure'] }}</span></div>
            <div><span class="label">Prepared By</span><span class="value">{{ $preparedBy }}</span></div>
        </div>

        <h2>Items requiring canvassing / procurement</h2>
        <p class="hint">Only this booking's shortage items. Quantities are the remaining requirement after stock already dispatched to this booking and stock held for other bookings.</p>

        <table>
            <thead>
                <tr>
                    <th style="width:34px">No.</th>
                    <th>Material / Item</th>
                    <th>Category</th>
                    <th class="num">Required Qty</th>
                    <th class="num">Available Qty</th>
                    <th class="num short">Shortage Qty</th>
                    <th>Unit</th>
                    <th>Priority</th>
                    <th>Canvass / Supplier Notes</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @php $qty = fn (float $value) => rtrim(rtrim(number_format($value, 2), '0'), '.'); @endphp
                @foreach($rows as $index => $row)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td><strong>{{ $row['name'] }}</strong></td>
                        <td>{{ $row['category'] }}</td>
                        <td class="num">{{ $qty($row['required']) }}</td>
                        <td class="num">{{ $qty($row['available']) }}</td>
                        <td class="num short">{{ $qty($row['shortage']) }}</td>
                        <td>{{ $row['unit'] ?: '—' }}</td>
                        <td class="{{ $row['priority'] === 'High' ? 'high' : '' }}">{{ $row['priority'] }}</td>
                        <td class="notes-cell">{{ $row['supplier'] }}</td>
                        <td>{{ $row['status'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="checklist">
            <div class="bar"><strong>Canvassing checklist</strong>
                <span>■ Contact supplier(s)</span><span>■ Compare prices</span><span>■ Confirm availability</span><span>■ Check lead time</span><span>■ Record selected supplier</span>
            </div>
            <div class="notes">Notes / follow-up:<div class="line"></div><div class="line"></div></div>
        </div>

        <div class="foot"><span>RAFLORA ENTERPRISES • BOOKING SHORTAGE REPORT</span><span>Booking #{{ $booking->id }}</span></div>
    </main>
</body>
</html>

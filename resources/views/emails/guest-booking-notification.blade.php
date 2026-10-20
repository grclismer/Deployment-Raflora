<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Update - Raflora Enterprises</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f5f7;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;color:#333333;">

<table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout:fixed;background-color:#f4f5f7;padding:40px 0;">
    <tr>
        <td align="center">
            <!-- Main Email Card -->
            <table border="0" cellpadding="0" cellspacing="0" width="600" style="background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.05);border:1px solid #e5e7eb;">

                <!-- ─── Header ─── -->
                <tr>
                    <td align="center" style="background-color:#4b1b7f;padding:30px 20px;">
                        <div style="background-color:#ffffff;display:inline-block;padding:10px;border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,0.15);">
                            <img src="{{ $message->embed(public_path('assets/images/logo.jpg')) }}"
                                 alt="Raflora Logo" width="70" height="70"
                                 style="display:block;border-radius:50%;object-fit:cover;">
                        </div>
                        <h1 style="color:#ffffff;margin:14px 0 0 0;font-size:22px;font-weight:600;letter-spacing:0.5px;">Raflora Enterprises</h1>
                        <p style="color:#d8b4fe;margin:4px 0 0 0;font-size:13px;">Flowers &amp; Event Styling</p>
                    </td>
                </tr>

                <!-- ─── Body ─── -->
                <tr>
                    <td style="padding:35px 30px;">

                        <!-- Headline & greeting -->
                        <h2 style="margin:0 0 10px 0;font-size:20px;color:#1f2937;font-weight:600;">
                            @if($context === 'confirmation')
                                Booking Request Received!
                            @elseif($context === 'quote_updated')
                                Your Quotation Has Been Updated!
                            @else
                                Your Quotation is Ready!
                            @endif
                        </h2>
                        <p style="margin:0 0 22px 0;font-size:15px;line-height:1.6;color:#4b5563;">
                            Hello <strong>{{ $booking->guest_name }}</strong>,<br>
                            @if($context === 'confirmation')
                                Thank you for submitting your booking request. We have received your details and are currently preparing your quotation.
                            @elseif($context === 'quote_updated')
                                Admin has reviewed and updated the quotation for your event. Please find the latest details below.
                            @elseif($context === 'status_updated')
                                There is an update regarding your booking for your event on <strong>{{ $booking->event_date->format('M d, Y') }}</strong>. Please see the details below.
                            @else
                                Admin has reviewed your guest booking request and updated your quotation proposal. Below are the details of your official quote.
                            @endif
                        </p>

                        <!-- ─── Status Badge ─── -->
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f3e8ff;border-left:4px solid #7e22ce;border-radius:6px;margin-bottom:25px;">
                            <tr>
                                <td style="padding:14px 20px;">
                                    <span style="font-size:11px;font-weight:700;text-transform:uppercase;color:#6b21a8;letter-spacing:0.5px;display:block;margin-bottom:4px;">Current Status</span>
                                    <strong style="font-size:16px;color:#581c87;">{{ $booking->status_display_label }}</strong>
                                </td>
                            </tr>
                        </table>

                        <!-- ─── Quotation Overview table ─── -->
                        @php
                            // Only an issued quotation's price is shared, never a working or AI figure.
                            $sharedQuotation = $booking instanceof \App\Models\Booking ? $booking->sharedQuotation() : null;
                            $totalAmount = $sharedQuotation ? (float) $sharedQuotation->final_quoted_price : 0;
                        @endphp
                        @if($totalAmount > 0)
                        <h3 style="font-size:15px;color:#374151;margin:0 0 10px 0;font-weight:600;">Quotation Overview</h3>
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;margin-bottom:25px;border:1px solid #f3f4f6;border-radius:8px;overflow:hidden;">
                            <tr style="background-color:#f9fafb;">
                                <th align="left"  style="padding:10px 14px;font-size:13px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Item / Service</th>
                                <th align="right" style="padding:10px 14px;font-size:13px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Price</th>
                            </tr>
                            <tr>
                                <td style="padding:12px 14px;font-size:14px;color:#374151;border-bottom:1px solid #f3f4f6;">Total Event Package &amp; Materials</td>
                                <td align="right" style="padding:12px 14px;font-size:14px;color:#374151;border-bottom:1px solid #f3f4f6;">&#8369;{{ number_format($totalAmount, 2) }}</td>
                            </tr>
                            <tr style="background-color:#faf5ff;">
                                <td style="padding:12px 14px;font-size:15px;font-weight:700;color:#581c87;">Quoted Total</td>
                                <td align="right" style="padding:12px 14px;font-size:15px;font-weight:700;color:#581c87;">&#8369;{{ number_format($totalAmount, 2) }}</td>
                            </tr>
                        </table>
                        @endif

                        <!-- ─── Admin Notes (clean list) ─── -->
                        @if($booking->admin_notes)
                        @php
                            $parsed      = \App\Models\Booking::parseUpdateMessage($booking->admin_notes);
                            $customNote  = trim($parsed['custom_note'] ?? '');
                            $itemLines   = $parsed['items'] ?? [];
                            $removedLines = $parsed['removed_items'] ?? [];
                            $priceLines  = $parsed['price_changes'] ?? [];

                            // Strip leading "• " bullet added by parseUpdateMessage for clean display
                            $cleanItems = array_map(fn($l) => ltrim(trim($l), '•·- '), $itemLines);
                            $cleanRemoved = array_map(fn($l) => ltrim(trim($l), '•·- '), $removedLines);
                            $cleanPrices = array_map(fn($l) => ltrim(trim($l), '•·- '), $priceLines);
                        @endphp

                        <div style="background-color:#fffbeb;border:1px solid #fef3c7;border-radius:8px;padding:16px 18px;margin-bottom:25px;">
                            <strong style="font-size:13px;color:#92400e;display:block;margin-bottom:10px;">Note from Florist:</strong>

                            {{-- Free-text note --}}
                            @if($customNote !== '')
                                <p style="margin:0 0 {{ (count($cleanItems) + count($cleanRemoved) + count($cleanPrices)) > 0 ? '12px' : '0' }} 0;font-size:13px;color:#b45309;line-height:1.6;">
                                    {!! nl2br(e($customNote)) !!}
                                </p>
                            @endif

                            {{-- Item adjustments list --}}
                            @if(count($cleanItems) > 0)
                                <p style="margin:0 0 6px 0;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.4px;color:#92400e;">Item Adjustments:</p>
                                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                    @foreach($cleanItems as $line)
                                    <tr>
                                        <td width="16" valign="top" style="padding:3px 6px 3px 0;font-size:13px;color:#b45309;">&#9679;</td>
                                        <td style="padding:3px 0;font-size:13px;color:#b45309;line-height:1.5;">{{ $line }}</td>
                                    </tr>
                                    @endforeach
                                </table>
                            @endif

                            {{-- Removed items list --}}
                            @if(count($cleanRemoved) > 0)
                                <p style="margin:{{ count($cleanItems) > 0 ? '10px' : '0' }} 0 6px 0;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.4px;color:#92400e;">Removed Items:</p>
                                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                    @foreach($cleanRemoved as $line)
                                    <tr>
                                        <td width="16" valign="top" style="padding:3px 6px 3px 0;font-size:13px;color:#b45309;">&#10005;</td>
                                        <td style="padding:3px 0;font-size:13px;color:#b45309;line-height:1.5;text-decoration:line-through;">{{ $line }}</td>
                                    </tr>
                                    @endforeach
                                </table>
                            @endif

                            {{-- Price changes list --}}
                            @if(count($cleanPrices) > 0)
                                <p style="margin:{{ (count($cleanItems) + count($cleanRemoved)) > 0 ? '10px' : '0' }} 0 6px 0;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.4px;color:#92400e;">Price Changes:</p>
                                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                    @foreach($cleanPrices as $line)
                                    <tr>
                                        <td width="16" valign="top" style="padding:3px 6px 3px 0;font-size:13px;color:#b45309;">&#9654;</td>
                                        <td style="padding:3px 0;font-size:13px;color:#b45309;line-height:1.5;">{{ $line }}</td>
                                    </tr>
                                    @endforeach
                                </table>
                            @endif

                            {{-- Fallback: if parseUpdateMessage returned nothing structured, just show raw with line breaks --}}
                            @if($customNote === '' && count($cleanItems) === 0 && count($cleanRemoved) === 0 && count($cleanPrices) === 0)
                                <p style="margin:0;font-size:13px;color:#b45309;line-height:1.6;">{!! nl2br(e($booking->admin_notes)) !!}</p>
                            @endif
                        </div>
                        @endif

                        <!-- ─── Primary CTA ─── -->
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:12px;">
                            <tr>
                                <td align="center">
                                    <a href="{{ route('guest.booking.analysis', ['token' => $booking->guest_access_token]) }}"
                                       target="_blank"
                                       style="background-color:#059669;color:#ffffff;display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;text-decoration:none;border-radius:8px;box-shadow:0 2px 5px rgba(5,150,105,0.3);">
                                        Track My Booking &rarr;
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="font-size:12px;color:#9ca3af;text-align:center;margin:0 0 25px 0;">
                            Reference Code: <strong>{{ strtoupper(substr($booking->guest_access_token, 0, 8)) }}</strong>
                            &nbsp;(Bookmark this link to check status anytime)
                        </p>

                        <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 25px 0;">

                        <!-- ─── Account Conversion Box ─── -->
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f9fafb;border-radius:8px;border:1px dashed #d1d5db;">
                            <tr>
                                <td style="padding:20px;text-align:center;">
                                    <h4 style="margin:0 0 6px 0;font-size:14px;color:#1f2937;font-weight:600;">Want to save this booking to a profile?</h4>
                                    <p style="margin:0 0 14px 0;font-size:13px;color:#6b7280;line-height:1.5;">Create an account to manage payments, track returns, and view history in one dashboard.</p>
                                    <a href="{{ route('register', ['email' => $booking->guest_email, 'name' => $booking->guest_name, 'guest_token' => $booking->guest_access_token]) }}"
                                       target="_blank"
                                       style="background-color:#ffffff;color:#374151;border:1px solid #d1d5db;display:inline-block;padding:9px 18px;font-size:13px;font-weight:600;text-decoration:none;border-radius:6px;">
                                        Create Account to Save Booking
                                    </a>
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

                <!-- ─── Footer ─── -->
                <tr>
                    <td align="center" style="background-color:#f9fafb;padding:20px;border-top:1px solid #e5e7eb;">
                        <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.6;">
                            &copy; {{ date('Y') }} Raflora Enterprises. All rights reserved.<br>
                            Need help? Reply directly to this email or contact support.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>

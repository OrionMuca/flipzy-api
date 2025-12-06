@extends('emails.layouts.app')

@section('content')
    <p style="margin: 0 0 24px; color: #374151; font-size: 17px; line-height: 28px; font-weight: 400;">
        <strong style="color: #059669; font-weight: 600;">Great news!</strong> Your payment has been confirmed successfully.
    </p>
    
    <!-- Subscription Details Box - Enhanced design -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 28px 0;">
        <tr>
            <td style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-radius: 14px; padding: 28px; border: 1px solid #e5e7eb; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <h3 style="margin: 0 0 24px; color: #111827; font-size: 20px; font-weight: 700; letter-spacing: -0.3px;">
                    Subscription Details
                </h3>
                
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    @if(isset($entry->plan))
                    <tr>
                        <td style="padding: 14px 0; border-bottom: 1px solid #e5e7eb;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td>
                                        <span style="color: #6b7280; font-size: 14px; font-weight: 500;">Plan:</span>
                                    </td>
                                    <td align="right">
                                        <span style="color: #111827; font-size: 16px; font-weight: 600;">{{ $entry->plan->name ?? 'N/A' }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif
                    @if($entry->coupon_code)
                        <tr>
                            <td style="padding: 14px 0; border-bottom: 1px solid #e5e7eb;">
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                    <tr>
                                        <td>
                                            <span style="color: #6b7280; font-size: 14px; font-weight: 500;">Original Price:</span>
                                        </td>
                                        <td align="right">
                                            <span style="color: #111827; font-size: 16px;">${{ number_format($entry->original_price ?? 0, 2) }}</span>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 14px 0; border-bottom: 1px solid #e5e7eb;">
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                    <tr>
                                        <td>
                                            <span style="color: #6b7280; font-size: 14px; font-weight: 500;">Discount ({{ $entry->coupon_code }}):</span>
                                        </td>
                                        <td align="right">
                                            <span style="color: #059669; font-size: 16px; font-weight: 600;">-${{ number_format($entry->discount_amount ?? 0, 2) }}</span>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding: 20px 0 0;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td>
                                        <span style="color: #111827; font-size: 18px; font-weight: 700;">Amount Paid:</span>
                                    </td>
                                    <td align="right">
                                        <span style="color: #059669; font-size: 22px; font-weight: 700;">${{ number_format($entry->discounted_price ?? 0, 2) }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <!-- Next Steps Box - Enhanced design -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 28px 0;">
        <tr>
            <td style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-left: 4px solid #2563eb; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1);">
                <p style="margin: 0; color: #1e40af; font-size: 14px; line-height: 22px;">
                    <strong style="font-weight: 600;">Next Steps:</strong> Your account will be activated when we launch. You'll receive an email with your login credentials at that time.
                </p>
            </td>
        </tr>
    </table>
    
    <p style="margin: 0 0 24px; color: #374151; font-size: 15px; line-height: 24px;">
        Thank you for your early support! We're working hard to bring you an amazing experience.
    </p>
@endsection

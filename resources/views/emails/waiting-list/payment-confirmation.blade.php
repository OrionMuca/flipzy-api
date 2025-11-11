@extends('emails.layouts.app')

@section('content')
    <!-- Icon Container - Using table for email compatibility -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 24px;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td class="email-icon-container" style="width: 64px; height: 64px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-radius: 16px; text-align: center; vertical-align: middle;">
                            <span class="email-icon-size" style="font-size: 32px; line-height: 64px; display: inline-block;">✅</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <p style="margin: 0 0 20px; color: #374151; font-size: 17px; line-height: 26px;">
        <strong>Great news!</strong> Your payment has been confirmed successfully.
    </p>
    
    <div style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-radius: 12px; padding: 24px; margin: 24px 0; border: 1px solid #e5e7eb; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
        <h3 style="margin: 0 0 20px; color: #111827; font-size: 18px; font-weight: 700; letter-spacing: -0.3px;">
            📋 Subscription Details
        </h3>
        
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                    <span style="color: #6b7280; font-size: 14px; font-weight: 500;">Plan:</span>
                    <span style="color: #111827; font-size: 16px; font-weight: 600; float: right;">{{ $entry->plan->name }}</span>
                </td>
            </tr>
            @if($entry->coupon_code)
                <tr>
                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                        <span style="color: #6b7280; font-size: 14px; font-weight: 500;">Original Price:</span>
                        <span style="color: #111827; font-size: 16px; float: right;">${{ number_format($entry->original_price, 2) }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                        <span style="color: #6b7280; font-size: 14px; font-weight: 500;">Discount ({{ $entry->coupon_code }}):</span>
                        <span style="color: #10b981; font-size: 16px; font-weight: 600; float: right;">-${{ number_format($entry->discount_amount, 2) }}</span>
                    </td>
                </tr>
            @endif
            <tr>
                <td style="padding: 16px 0 0;">
                    <span style="color: #111827; font-size: 18px; font-weight: 700;">Amount Paid:</span>
                    <span style="color: #111827; font-size: 20px; font-weight: 700; float: right;">${{ number_format($entry->discounted_price, 2) }}</span>
                </td>
            </tr>
        </table>
    </div>
    
    <div style="background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 6px; padding: 16px; margin: 24px 0;">
        <p style="margin: 0; color: #1e40af; font-size: 14px; line-height: 20px;">
            <strong>⏳ Next Steps:</strong> Your account will be activated when we launch. You'll receive an email with your login credentials at that time.
        </p>
    </div>
    
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 24px;">
        Thank you for your early support! We're working hard to bring you an amazing experience.
    </p>
@endsection

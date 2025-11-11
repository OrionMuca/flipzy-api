@extends('emails.layouts.app')

@section('content')
    <!-- Icon Container - Using table for email compatibility -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 24px;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td class="email-icon-container" style="width: 64px; height: 64px; background: linear-gradient(135deg, #ddd6fe 0%, #c4b5fd 100%); border-radius: 16px; text-align: center; vertical-align: middle;">
                            <span class="email-icon-size" style="font-size: 32px; line-height: 64px; display: inline-block;">🎉</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <p style="margin: 0 0 20px; color: #374151; font-size: 17px; line-height: 26px;">
        Thank you for joining our waiting list! We're <strong>thrilled</strong> to have you on board.
    </p>
    
    <div style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-radius: 12px; padding: 24px; margin: 24px 0; border: 1px solid #e5e7eb;">
        <p style="margin: 0 0 12px; color: #6b7280; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
            Your Selected Plan
        </p>
        <p style="margin: 0; color: #111827; font-size: 20px; font-weight: 700;">
            {{ $entry->plan->name }}
        </p>
    </div>
    
    @if($entry->coupon_code)
        <div style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border: 2px solid #10b981; border-radius: 12px; padding: 20px; margin: 24px 0;">
            <p style="margin: 0 0 8px; color: #065f46; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                🎁 Special Offer Applied!
            </p>
            <p style="margin: 0; color: #047857; font-size: 16px; line-height: 24px;">
                You've used coupon code <strong style="color: #059669; font-size: 18px;">{{ $entry->coupon_code }}</strong> and saved <strong style="color: #059669; font-size: 18px;">${{ number_format($entry->discount_amount, 2) }}</strong>!
            </p>
        </div>
    @endif
    
    <p style="margin: 0 0 24px; color: #6b7280; font-size: 15px; line-height: 24px;">
        To verify your email and check your status anytime, click the button above or use the link below.
    </p>
    
    <div style="background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px; padding: 16px; margin: 24px 0;">
        <p style="margin: 0; color: #92400e; font-size: 14px; line-height: 20px;">
            <strong>📬 Stay Updated:</strong> We'll notify you as soon as we're ready to launch!
        </p>
    </div>
    
    <p style="margin: 24px 0 0; color: #9ca3af; font-size: 13px; line-height: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
        <strong style="color: #6b7280;">Status Link:</strong><br>
        <a href="{{ $verificationLink }}" style="color: #6366f1; word-break: break-all; text-decoration: none; border-bottom: 1px solid #c7d2fe;">{{ $verificationLink }}</a>
    </p>
@endsection

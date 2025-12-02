@extends('emails.layouts.app')

@section('content')
    <!-- Icon Container - Using table for email compatibility -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 32px;">
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
    
    <p style="margin: 0 0 24px; color: #374151; font-size: 17px; line-height: 28px; font-weight: 400;">
        Thank you for joining our waiting list! We're <strong style="color: #111827; font-weight: 600;">thrilled</strong> to have you on board.
    </p>
    
    @if($entry->coupon_code)
        <!-- Coupon Code Box - Improved mobile responsiveness -->
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
            <tr>
                <td style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border: 2px solid #10b981; border-radius: 12px; padding: 20px;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                        <tr>
                            <td style="padding-bottom: 8px;">
                                <p style="margin: 0; color: #065f46; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                    🎁 Special Offer Applied!
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <p style="margin: 0; color: #047857; font-size: 15px; line-height: 24px;">
                                    You've used coupon code <strong style="color: #059669; font-size: 17px; font-weight: 700;">{{ $entry->coupon_code }}</strong> for future use!
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    @endif
    
    <p style="margin: 0 0 32px; color: #6b7280; font-size: 15px; line-height: 24px;">
        Click the button above to verify your email address. You can also check your status anytime using the link below.
    </p>
    
    <!-- Info Box - Improved mobile layout -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td style="background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px; padding: 16px;">
                <p style="margin: 0; color: #92400e; font-size: 14px; line-height: 22px;">
                    <strong style="font-weight: 600;">📬 Stay Updated:</strong> We'll notify you as soon as we're ready to launch!
                </p>
            </td>
        </tr>
    </table>
    
    <!-- Status Link - Improved mobile text wrapping -->
    @if(isset($statusLink))
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb;">
        <tr>
            <td>
                <p style="margin: 0 0 8px; color: #6b7280; font-size: 13px; font-weight: 600; line-height: 18px;">
                    Status Link:
                </p>
                <p style="margin: 0; color: #9ca3af; font-size: 12px; line-height: 18px; word-break: break-all;">
                    <a href="{{ $statusLink }}" style="color: #6366f1; text-decoration: none; border-bottom: 1px solid #c7d2fe; word-break: break-all;">{{ $statusLink }}</a>
                </p>
            </td>
        </tr>
    </table>
    @endif
@endsection

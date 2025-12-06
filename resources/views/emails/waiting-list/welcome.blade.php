@extends('emails.layouts.app')

@section('content')
    <p style="margin: 0 0 24px; color: #374151; font-size: 17px; line-height: 28px; font-weight: 400;">
        Thank you for joining our waiting list! We're <strong style="color: #111827; font-weight: 600;">thrilled</strong> to have you on board and excited to share what we're building.
    </p>
    
    @if($entry->coupon_code)
        <!-- Coupon Code Box - Enhanced design -->
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 28px 0;">
            <tr>
                <td style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border: 2px solid #059669; border-radius: 14px; padding: 24px; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.1);">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                        <tr>
                            <td style="padding-bottom: 10px;">
                                <p style="margin: 0; color: #065f46; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;">
                                    Special Offer Applied!
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <p style="margin: 0; color: #047857; font-size: 16px; line-height: 26px;">
                                    You've used coupon code <strong style="color: #059669; font-size: 18px; font-weight: 700; letter-spacing: 0.5px;">{{ $entry->coupon_code }}</strong> for future use!
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
    
    <!-- Info Box - Enhanced design -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 28px 0;">
        <tr>
            <td style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left: 4px solid #f59e0b; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(245, 158, 11, 0.1);">
                <p style="margin: 0; color: #92400e; font-size: 14px; line-height: 22px;">
                    <strong style="font-weight: 600;">Stay Updated:</strong> We'll notify you as soon as we're ready to launch! Keep an eye on your inbox for exciting updates.
                </p>
            </td>
        </tr>
    </table>
    
    <!-- Status Link - Enhanced design -->
    @if(isset($statusLink))
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 36px; padding-top: 28px; border-top: 1px solid #e5e7eb;">
        <tr>
            <td>
                <p style="margin: 0 0 10px; color: #6b7280; font-size: 13px; font-weight: 600; line-height: 18px; text-transform: uppercase; letter-spacing: 0.5px;">
                    Check Your Status
                </p>
                <p style="margin: 0; color: #9ca3af; font-size: 12px; line-height: 18px; word-break: break-all;">
                    <a href="{{ $statusLink }}" style="color: #2563eb; text-decoration: none; border-bottom: 1px solid #bfdbfe; word-break: break-all; transition: color 0.2s ease;">{{ $statusLink }}</a>
                </p>
            </td>
        </tr>
    </table>
    @endif
@endsection

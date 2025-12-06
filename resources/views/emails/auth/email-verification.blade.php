@extends('emails.layouts.app')

@section('content')
    <p style="margin: 0 0 24px; color: #374151; font-size: 17px; line-height: 28px; font-weight: 400;">
        Thank you for registering with <strong style="color: #2563eb; font-weight: 600;">{{ config('app.name') }}</strong>! We're excited to have you on board.
    </p>
    
    <p style="margin: 0 0 28px; color: #6b7280; font-size: 15px; line-height: 24px;">
        Please verify your email address by clicking the button above. This helps us ensure the security of your account and enables important notifications.
    </p>
    
    <!-- Why Verify Box - Enhanced design -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 28px 0;">
        <tr>
            <td style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-left: 4px solid #059669; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(5, 150, 105, 0.1);">
                <p style="margin: 0; color: #065f46; font-size: 14px; line-height: 22px;">
                    <strong style="font-weight: 600;">Why verify?</strong> Email verification helps protect your account and ensures you receive important updates.
                </p>
            </td>
        </tr>
    </table>
    
    <p style="margin: 0 0 24px; color: #9ca3af; font-size: 13px; line-height: 20px;">
        If you didn't create an account, you can safely ignore this email.
    </p>
    
    <!-- Verification Link - Enhanced design -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 36px; padding-top: 28px; border-top: 1px solid #e5e7eb;">
        <tr>
            <td>
                <p style="margin: 0 0 10px; color: #6b7280; font-size: 13px; font-weight: 600; line-height: 18px; text-transform: uppercase; letter-spacing: 0.5px;">
                    Having trouble?
                </p>
                <p style="margin: 0; color: #9ca3af; font-size: 12px; line-height: 18px; word-break: break-all;">
                    Copy and paste this link into your browser:<br>
                    <a href="{{ $verificationUrl }}" style="color: #2563eb; word-break: break-all; text-decoration: none; border-bottom: 1px solid #bfdbfe; transition: color 0.2s ease;">{{ $verificationUrl }}</a>
                </p>
            </td>
        </tr>
    </table>
@endsection

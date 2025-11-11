@extends('emails.layouts.app')

@section('content')
    <!-- Icon Container - Using table for email compatibility -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 24px;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td class="email-icon-container" style="width: 64px; height: 64px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-radius: 16px; text-align: center; vertical-align: middle;">
                            <span class="email-icon-size" style="font-size: 32px; line-height: 64px; display: inline-block;">✉️</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <p style="margin: 0 0 20px; color: #374151; font-size: 17px; line-height: 26px;">
        Thank you for registering with <strong>{{ config('app.name') }}</strong>! We're excited to have you on board.
    </p>
    
    <p style="margin: 0 0 24px; color: #6b7280; font-size: 15px; line-height: 24px;">
        Please verify your email address by clicking the button above. This helps us ensure the security of your account and enables important notifications.
    </p>
    
    <div style="background: #f0fdf4; border-left: 4px solid #10b981; border-radius: 6px; padding: 16px; margin: 24px 0;">
        <p style="margin: 0; color: #065f46; font-size: 14px; line-height: 20px;">
            <strong>✅ Why verify?</strong> Email verification helps protect your account and ensures you receive important updates.
        </p>
    </div>
    
    <p style="margin: 0 0 20px; color: #9ca3af; font-size: 13px; line-height: 20px;">
        If you didn't create an account, you can safely ignore this email.
    </p>
    
    <p style="margin: 24px 0 0; color: #9ca3af; font-size: 13px; line-height: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
        <strong style="color: #6b7280;">Having trouble?</strong> Copy and paste this link into your browser:<br>
        <a href="{{ $verificationUrl }}" style="color: #10b981; word-break: break-all; text-decoration: none; border-bottom: 1px solid #86efac;">{{ $verificationUrl }}</a>
    </p>
@endsection

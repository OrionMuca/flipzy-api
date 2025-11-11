@extends('emails.layouts.app')

@section('content')
    <!-- Icon Container - Using table for email compatibility -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 24px;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td class="email-icon-container" style="width: 64px; height: 64px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 16px; text-align: center; vertical-align: middle;">
                            <span class="email-icon-size" style="font-size: 32px; line-height: 64px; display: inline-block;">🔐</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <p style="margin: 0 0 20px; color: #374151; font-size: 17px; line-height: 26px;">
        We received a request to reset your password for your <strong>{{ config('app.name') }}</strong> account.
    </p>
    
    <p style="margin: 0 0 24px; color: #6b7280; font-size: 15px; line-height: 24px;">
        Click the button above to reset your password. This link will expire in <strong>60 minutes</strong> for your security.
    </p>
    
    <div style="background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 6px; padding: 16px; margin: 24px 0;">
        <p style="margin: 0; color: #1e40af; font-size: 14px; line-height: 20px;">
            <strong>💡 Security Tip:</strong> If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged.
        </p>
    </div>
    
    <p style="margin: 24px 0 0; color: #9ca3af; font-size: 13px; line-height: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
        <strong style="color: #6b7280;">Having trouble?</strong> Copy and paste this link into your browser:<br>
        <a href="{{ $resetUrl }}" style="color: #6366f1; word-break: break-all; text-decoration: none; border-bottom: 1px solid #c7d2fe;">{{ $resetUrl }}</a>
    </p>
@endsection

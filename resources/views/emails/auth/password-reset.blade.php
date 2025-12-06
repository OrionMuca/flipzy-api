@extends('emails.layouts.app')

@section('content')
    <p style="margin: 0 0 24px; color: #374151; font-size: 17px; line-height: 28px; font-weight: 400;">
        We received a request to reset your password for your <strong style="color: #2563eb; font-weight: 600;">{{ config('app.name') }}</strong> account.
    </p>
    
    <p style="margin: 0 0 28px; color: #6b7280; font-size: 15px; line-height: 24px;">
        Click the button above to reset your password. This link will expire in <strong style="color: #111827; font-weight: 600;">60 minutes</strong> for your security.
    </p>
    
    <!-- Security Tip Box - Enhanced design -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 28px 0;">
        <tr>
            <td style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-left: 4px solid #2563eb; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1);">
                <p style="margin: 0; color: #1e40af; font-size: 14px; line-height: 22px;">
                    <strong style="font-weight: 600;">Security Tip:</strong> If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged.
                </p>
            </td>
        </tr>
    </table>
    
    <!-- Reset Link - Enhanced design -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 36px; padding-top: 28px; border-top: 1px solid #e5e7eb;">
        <tr>
            <td>
                <p style="margin: 0 0 10px; color: #6b7280; font-size: 13px; font-weight: 600; line-height: 18px; text-transform: uppercase; letter-spacing: 0.5px;">
                    Having trouble?
                </p>
                <p style="margin: 0; color: #9ca3af; font-size: 12px; line-height: 18px; word-break: break-all;">
                    Copy and paste this link into your browser:<br>
                    <a href="{{ $resetUrl }}" style="color: #2563eb; word-break: break-all; text-decoration: none; border-bottom: 1px solid #bfdbfe; transition: color 0.2s ease;">{{ $resetUrl }}</a>
                </p>
            </td>
        </tr>
    </table>
@endsection

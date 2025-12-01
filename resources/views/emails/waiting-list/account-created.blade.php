@extends('emails.layouts.app')

@section('content')
    <!-- Icon Container - Using table for email compatibility -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 24px;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td class="email-icon-container" style="width: 64px; height: 64px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 16px; text-align: center; vertical-align: middle;">
                            <span class="email-icon-size" style="font-size: 32px; line-height: 64px; display: inline-block;">🚀</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <p style="margin: 0 0 20px; color: #374151; font-size: 17px; line-height: 26px;">
        <strong>Exciting news!</strong> Your account has been created and you can now access <strong>{{ config('app.name') }}</strong>.
    </p>
    
    <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 2px solid #f59e0b; border-radius: 12px; padding: 20px; margin: 24px 0;">
        <p style="margin: 0 0 8px; color: #92400e; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
            ⚠️ Important Security Notice
        </p>
        <p style="margin: 0; color: #78350f; font-size: 15px; line-height: 22px;">
            Please change your password immediately after logging in to secure your account.
        </p>
    </div>
    
    <div style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-radius: 12px; padding: 24px; margin: 24px 0; border: 1px solid #e5e7eb; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
        <h3 style="margin: 0 0 20px; color: #111827; font-size: 18px; font-weight: 700; letter-spacing: -0.3px;">
            🔑 Your Login Credentials
        </h3>
        
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                    <span style="color: #6b7280; font-size: 14px; font-weight: 500;">Email:</span>
                    <span style="color: #111827; font-size: 15px; font-weight: 600; float: right; word-break: break-all;">{{ $user->email }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0;">
                    <span style="color: #6b7280; font-size: 14px; font-weight: 500;">Temporary Password:</span>
                    <span style="color: #111827; font-size: 15px; font-weight: 700; font-family: 'Courier New', monospace; float: right; letter-spacing: 1px; background: #f3f4f6; padding: 4px 8px; border-radius: 4px;">{{ $temporaryPassword }}</span>
                </td>
            </tr>
        </table>
    </div>
    
    <p style="margin: 0 0 24px; color: #6b7280; font-size: 15px; line-height: 24px;">
        Click the button above to reset your password and log in. Or use the link below:
    </p>
    
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 24px;">
        If you have any questions, feel free to reach out to our support team. We're here to help!
    </p>
    
    <p style="margin: 24px 0 0; color: #9ca3af; font-size: 13px; line-height: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
        <strong style="color: #6b7280;">Password Reset Link:</strong><br>
        <a href="{{ $resetUrl }}" style="color: #6366f1; word-break: break-all; text-decoration: none; border-bottom: 1px solid #c7d2fe;">{{ $resetUrl }}</a>
    </p>
@endsection

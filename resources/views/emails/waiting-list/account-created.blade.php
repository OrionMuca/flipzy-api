@extends('emails.layouts.app')

@section('content')
    <!-- Icon Container - Using table for email compatibility -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 32px;">
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
    
    <p style="margin: 0 0 24px; color: #374151; font-size: 17px; line-height: 28px; font-weight: 400;">
        <strong style="color: #111827; font-weight: 600;">Exciting news!</strong> Your account has been created and you can now access <strong style="color: #111827; font-weight: 600;">{{ config('app.name') }}</strong>.
    </p>
    
    <!-- Security Notice - Improved mobile layout -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 2px solid #f59e0b; border-radius: 12px; padding: 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="padding-bottom: 8px;">
                            <p style="margin: 0; color: #92400e; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                ⚠️ Important Security Notice
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <p style="margin: 0; color: #78350f; font-size: 15px; line-height: 22px;">
                                Please change your password immediately after logging in to secure your account.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <!-- Credentials Box - Improved mobile responsiveness -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-radius: 12px; padding: 24px; border: 1px solid #e5e7eb;">
                <h3 style="margin: 0 0 20px; color: #111827; font-size: 18px; font-weight: 700; letter-spacing: -0.3px;">
                    🔑 Your Login Credentials
                </h3>
                
                <!-- Email Row - Mobile-friendly -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 16px;">
                    <tr>
                        <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding-bottom: 4px;">
                                        <span style="color: #6b7280; font-size: 13px; font-weight: 500; display: block;">Email:</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <span style="color: #111827; font-size: 15px; font-weight: 600; word-break: break-all; display: block;">{{ $user->email }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                
                <!-- Password Row - Mobile-friendly -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="padding: 12px 0;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding-bottom: 8px;">
                                        <span style="color: #6b7280; font-size: 13px; font-weight: 500; display: block;">Temporary Password:</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <span style="color: #111827; font-size: 16px; font-weight: 700; font-family: 'Courier New', Courier, monospace; letter-spacing: 2px; background: #f3f4f6; padding: 8px 12px; border-radius: 6px; display: inline-block; word-break: break-all;">{{ $temporaryPassword }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <p style="margin: 0 0 32px; color: #6b7280; font-size: 15px; line-height: 24px;">
        Click the button above to reset your password and log in. Or use the link below:
    </p>
    
    <p style="margin: 0 0 24px; color: #374151; font-size: 15px; line-height: 24px;">
        If you have any questions, feel free to reach out to our support team. We're here to help!
    </p>
    
    <!-- Password Reset Link - Improved mobile text wrapping -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb;">
        <tr>
            <td>
                <p style="margin: 0 0 8px; color: #6b7280; font-size: 13px; font-weight: 600; line-height: 18px;">
                    Password Reset Link:
                </p>
                <p style="margin: 0; color: #9ca3af; font-size: 12px; line-height: 18px; word-break: break-all;">
                    <a href="{{ $resetUrl }}" style="color: #6366f1; text-decoration: none; border-bottom: 1px solid #c7d2fe; word-break: break-all;">{{ $resetUrl }}</a>
                </p>
            </td>
        </tr>
    </table>
@endsection

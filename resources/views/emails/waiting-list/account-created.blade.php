@extends('emails.layouts.app')

@section('content')
    <p style="margin: 0 0 24px; color: #374151; font-size: 17px; line-height: 28px; font-weight: 400;">
        <strong style="color: #111827; font-weight: 600;">Exciting news!</strong> Your account has been created and you can now access <strong style="color: #2563eb; font-weight: 600;">{{ config('app.name') }}</strong>.
    </p>
    
    <!-- Security Notice - Enhanced design -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 28px 0;">
        <tr>
            <td style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 2px solid #f59e0b; border-radius: 14px; padding: 24px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="padding-bottom: 10px;">
                            <p style="margin: 0; color: #92400e; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;">
                                Important Security Notice
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <p style="margin: 0; color: #78350f; font-size: 15px; line-height: 24px;">
                                Please change your password immediately after logging in to secure your account.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <!-- Credentials Box - Enhanced design -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 28px 0;">
        <tr>
            <td style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-radius: 14px; padding: 28px; border: 1px solid #e5e7eb; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <h3 style="margin: 0 0 24px; color: #111827; font-size: 20px; font-weight: 700; letter-spacing: -0.3px;">
                    Your Login Credentials
                </h3>
                
                <!-- Email Row - Mobile-friendly -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 16px 0; border-bottom: 1px solid #e5e7eb;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding-bottom: 6px;">
                                        <span style="color: #6b7280; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; display: block;">Email:</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <span style="color: #111827; font-size: 16px; font-weight: 600; word-break: break-all; display: block;">{{ $user->email }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                
                <!-- Password Row - Mobile-friendly -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="padding: 16px 0 0;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding-bottom: 10px;">
                                        <span style="color: #6b7280; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; display: block;">Temporary Password:</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <span style="color: #111827; font-size: 20px; font-weight: 600; letter-spacing: 0.5px; background: #f3f4f6; padding: 14px 20px; border-radius: 8px; display: inline-block; word-break: break-all; border: 2px solid #2563eb; color: #2563eb;">{{ $temporaryPassword }}</span>
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
    
    <!-- Password Reset Link - Enhanced design -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 36px; padding-top: 28px; border-top: 1px solid #e5e7eb;">
        <tr>
            <td>
                <p style="margin: 0 0 10px; color: #6b7280; font-size: 13px; font-weight: 600; line-height: 18px; text-transform: uppercase; letter-spacing: 0.5px;">
                    Password Reset Link
                </p>
                <p style="margin: 0; color: #9ca3af; font-size: 12px; line-height: 18px; word-break: break-all;">
                    <a href="{{ $resetUrl }}" style="color: #2563eb; text-decoration: none; border-bottom: 1px solid #bfdbfe; word-break: break-all; transition: color 0.2s ease;">{{ $resetUrl }}</a>
                </p>
            </td>
        </tr>
    </table>
@endsection

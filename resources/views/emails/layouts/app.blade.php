<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? config('app.name') }}</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td {font-family: Arial, Helvetica, sans-serif !important;}
        a {text-decoration: none;}
    </style>
    <![endif]-->
    <style>
        @media only screen and (max-width: 600px) {
            .email-container { 
                width: 100% !important; 
                max-width: 100% !important; 
                border-radius: 0 !important;
            }
            .email-padding { 
                padding: 24px 20px !important; 
            }
            .email-header-padding { 
                padding: 32px 20px 24px !important; 
            }
            .email-footer-padding { 
                padding: 24px 20px !important; 
            }
            .email-button-padding {
                padding: 0 20px 24px !important;
            }
            .email-greeting {
                font-size: 20px !important;
                line-height: 28px !important;
            }
            .email-content {
                font-size: 15px !important;
                line-height: 24px !important;
            }
            .email-icon-container {
                width: 56px !important;
                height: 56px !important;
            }
            .email-icon-size {
                font-size: 28px !important;
            }
            .email-container table[role="presentation"] {
                width: 100% !important;
            }
            .email-info-box {
                padding: 16px !important;
                margin: 20px 0 !important;
            }
            .email-info-box p {
                font-size: 13px !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;">
        <tr>
            <td align="center" style="padding: 20px 10px;">
                <!-- Main Container -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); overflow: hidden;">
                    
                    <!-- Header -->
                    @include('emails.components.header', [
                        'logoUrl' => $logoUrl ?? null,
                        'appName' => $appName ?? config('app.name'),
                        'headerColor' => $headerColor ?? '#6366f1',
                    ])
                    
                    <!-- Content -->
                    <tr>
                        <td class="email-padding" style="padding: 48px 48px 32px;">
                            <!-- Greeting -->
                            @if(isset($greeting))
                                <h1 class="email-greeting" style="margin: 0 0 24px; font-size: 24px; font-weight: 700; line-height: 32px; color: #111827; letter-spacing: -0.5px;">
                                    {{ $greeting }}
                                </h1>
                            @endif
                            
                            <!-- Main Content -->
                            <div class="email-content" style="color: #374151; font-size: 16px; line-height: 26px;">
                                @yield('content')
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Action Button (if provided) -->
                    @if(isset($actionUrl) && isset($actionText))
                        <tr>
                            <td align="center" class="email-button-padding" style="padding: 0 48px 32px;">
                                @include('emails.components.button', [
                                    'url' => $actionUrl,
                                    'text' => $actionText,
                                    'color' => $buttonColor ?? '#6366f1',
                                ])
                            </td>
                        </tr>
                    @endif
                    
                    <!-- Additional Content (if provided) -->
                    @if(isset($additionalContent))
                        <tr>
                            <td class="email-padding" style="padding: 0 48px 32px;">
                                <div style="color: #6b7280; font-size: 14px; line-height: 22px; border-top: 1px solid #e5e7eb; padding-top: 24px;">
                                    {!! $additionalContent !!}
                                </div>
                            </td>
                        </tr>
                    @endif
                    
                    <!-- Footer -->
                    @include('emails.components.footer', [
                        'appName' => $appName ?? config('app.name'),
                        'unsubscribeUrl' => $unsubscribeUrl ?? null,
                        'socialLinks' => $socialLinks ?? [],
                    ])
                    
                </table>
                
                <!-- Bottom Spacing -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width: 600px; margin-top: 20px;">
                    <tr>
                        <td align="center" style="padding: 16px 20px; color: rgba(255,255,255,0.9); font-size: 12px; line-height: 18px;">
                            <p style="margin: 0 0 8px;">
                                This email was sent to <strong style="color: #ffffff;">{{ $recipientEmail ?? 'you' }}</strong>.
                            </p>
                            <p style="margin: 0; opacity: 0.8;">
                                If you didn't request this email, you can safely ignore it.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

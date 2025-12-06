<tr>
    <td class="email-header-padding" style="background: linear-gradient(135deg, {{ $headerColor ?? '#2563eb' }} 0%, {{ $headerColor ?? '#1e40af' }} 100%); padding: 40px 32px; mso-padding-alt: 40px 32px;">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td align="center" style="padding: 0;">
                    @if(isset($logoUrl) && $logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $appName }}" style="max-width: 180px; width: auto; height: auto; display: block; margin: 0 auto; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                    @else
                        <h1 style="margin: 0; color: #ffffff; font-size: 32px; font-weight: 700; letter-spacing: -0.5px; line-height: 1.2; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            {{ $appName }}
                        </h1>
                    @endif
                </td>
            </tr>
        </table>
    </td>
</tr>
<!--[if mso]>
<style type="text/css">
    .email-header-padding {
        padding: 40px 32px !important;
    }
</style>
<![endif]-->
<style type="text/css">
    @media only screen and (max-width: 600px) {
        .email-header-padding {
            padding: 24px 20px !important;
        }
        .email-header-padding h1 {
            font-size: 26px !important;
            line-height: 1.2 !important;
        }
        .email-header-padding img {
            max-width: 140px !important;
            height: auto !important;
        }
    }
    @media only screen and (max-width: 480px) {
        .email-header-padding {
            padding: 20px 16px !important;
        }
        .email-header-padding h1 {
            font-size: 24px !important;
        }
        .email-header-padding img {
            max-width: 120px !important;
        }
    }
</style>

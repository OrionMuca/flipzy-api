<tr>
    <td class="email-header-padding" style="background: linear-gradient(135deg, {{ $headerColor ?? '#6366f1' }} 0%, {{ $headerColor ?? '#8b5cf6' }} 100%); padding: 32px 24px; mso-padding-alt: 32px 24px;">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td align="center" style="padding: 0;">
                    @if(isset($logoUrl) && $logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $appName }}" style="max-width: 140px; width: auto; height: auto; display: block; margin: 0 auto;">
                    @else
                        <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700; letter-spacing: -0.5px; line-height: 1.2;">
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
        padding: 32px 24px !important;
    }
</style>
<![endif]-->
<style type="text/css">
    @media only screen and (max-width: 600px) {
        .email-header-padding {
            padding: 12px 16px !important;
        }
        .email-header-padding h1 {
            font-size: 22px !important;
            line-height: 1.2 !important;
        }
        .email-header-padding img {
            max-width: 100px !important;
        }
    }
    @media only screen and (max-width: 480px) {
        .email-header-padding {
            padding: 10px 16px !important;
        }
        .email-header-padding h1 {
            font-size: 20px !important;
        }
        .email-header-padding img {
            max-width: 90px !important;
        }
    }
</style>

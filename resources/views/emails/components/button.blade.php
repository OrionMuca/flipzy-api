<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td align="center" style="padding: 0;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" class="email-button">
                <tr>
                    <td align="center" style="background: linear-gradient(135deg, {{ $color ?? '#2563eb' }} 0%, {{ $color ?? '#1e40af' }} 100%); border-radius: 12px; box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);">
                        <a href="{{ $url }}" style="display: inline-block; padding: 18px 40px; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; letter-spacing: 0.3px; border-radius: 12px; background: linear-gradient(135deg, {{ $color ?? '#2563eb' }} 0%, {{ $color ?? '#1e40af' }} 100%); transition: all 0.3s ease; min-width: 220px; text-align: center; line-height: 1.4;">
                            {{ $text }}
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<style>
    @media only screen and (max-width: 600px) {
        .email-button {
            width: 100% !important;
        }
        .email-button td {
            width: 100% !important;
            display: block !important;
        }
        .email-button a {
            padding: 16px 32px !important;
            font-size: 15px !important;
            min-width: auto !important;
            width: 100% !important;
            display: block !important;
            box-sizing: border-box !important;
        }
    }
</style>

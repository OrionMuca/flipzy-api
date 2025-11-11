<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td align="center" style="padding: 0;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" class="email-button">
                <tr>
                    <td align="center" style="background: linear-gradient(135deg, {{ $color ?? '#6366f1' }} 0%, {{ $color ?? '#8b5cf6' }} 100%); border-radius: 8px; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);">
                        <a href="{{ $url }}" style="display: inline-block; padding: 16px 32px; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; letter-spacing: 0.3px; border-radius: 8px; background: linear-gradient(135deg, {{ $color ?? '#6366f1' }} 0%, {{ $color ?? '#8b5cf6' }} 100%); transition: all 0.3s ease; min-width: 200px; text-align: center;">
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
            padding: 14px 24px !important;
            font-size: 15px !important;
            min-width: auto !important;
            width: 100% !important;
            display: block !important;
            box-sizing: border-box !important;
        }
    }
</style>

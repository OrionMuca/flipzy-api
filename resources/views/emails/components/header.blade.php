<tr>
    <td class="email-header-padding" style="background: linear-gradient(135deg, {{ $headerColor ?? '#6366f1' }} 0%, {{ $headerColor ?? '#8b5cf6' }} 100%); padding: 48px 48px 36px; position: relative; overflow: hidden;">
        <!-- Decorative background pattern -->
        <div style="position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
        <div style="position: absolute; bottom: -30px; left: -30px; width: 150px; height: 150px; background: rgba(255,255,255,0.08); border-radius: 50%;"></div>
        
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="position: relative; z-index: 1;">
            <tr>
                <td align="center">
                    @if(isset($logoUrl) && $logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $appName }}" style="max-width: 160px; width: 100%; height: auto; display: block; margin: 0 auto; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));">
                    @else
                        <div style="text-align: center;">
                            <h1 style="margin: 0 0 8px; color: #ffffff; font-size: 32px; font-weight: 800; letter-spacing: -1px; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                {{ $appName }}
                            </h1>
                            <div style="width: 60px; height: 4px; background: rgba(255,255,255,0.5); border-radius: 2px; margin: 0 auto;"></div>
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </td>
</tr>
<style>
    @media only screen and (max-width: 600px) {
        .email-header-padding {
            padding: 32px 20px 24px !important;
        }
        .email-header-padding h1 {
            font-size: 26px !important;
        }
        .email-header-padding img {
            max-width: 120px !important;
        }
    }
</style>

<tr>
    <td class="email-footer-padding" style="padding: 36px 48px; background: linear-gradient(to bottom, #f9fafb 0%, #f3f4f6 100%); border-top: 1px solid #e5e7eb;">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            @if(!empty($socialLinks))
                <tr>
                    <td align="center" style="padding-bottom: 20px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                @foreach($socialLinks as $link)
                                    <td style="padding: 0 8px;">
                                        <a href="{{ $link['url'] }}" style="display: inline-block; width: 40px; height: 40px; background-color: #ffffff; border-radius: 50%; text-align: center; line-height: 40px; text-decoration: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: all 0.3s ease;">
                                            <span style="font-size: 18px; color: #6366f1;">{{ $link['icon'] ?? '🔗' }}</span>
                                        </a>
                                    </td>
                                @endforeach
                            </tr>
                        </table>
                    </td>
                </tr>
            @endif
            
            <tr>
                <td align="center" style="padding-bottom: 16px;">
                    <p style="margin: 0; color: #6b7280; font-size: 14px; line-height: 20px; font-weight: 500;">
                        © {{ date('Y') }} {{ $appName }}. All rights reserved.
                    </p>
                </td>
            </tr>
            
            @if(isset($unsubscribeUrl) && $unsubscribeUrl)
                <tr>
                    <td align="center">
                        <p style="margin: 0;">
                            <a href="{{ $unsubscribeUrl }}" style="color: #9ca3af; text-decoration: none; font-size: 12px; border-bottom: 1px solid #d1d5db; padding-bottom: 2px;">
                                Unsubscribe
                            </a>
                        </p>
                    </td>
                </tr>
            @endif
        </table>
    </td>
</tr>
<style>
    @media only screen and (max-width: 600px) {
        .email-footer-padding {
            padding: 24px 20px !important;
        }
        .email-footer-padding p {
            font-size: 13px !important;
        }
    }
</style>

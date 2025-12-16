@extends('emails.layouts.app')

@section('content')
    <!-- Message Content -->
    <div style="color: #374151; font-size: 16px; line-height: 26px; white-space: pre-line;">
        {!! nl2br(e($emailContent ?? $content ?? '')) !!}
    </div>
    
    @if($actionUrl && $actionText)
        <!-- Action Button will be rendered by layout -->
    @endif
    
    <!-- Info Box -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 32px;">
        <tr>
            <td style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-left: 4px solid #2563eb; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1);">
                <p style="margin: 0; color: #1e40af; font-size: 14px; line-height: 22px;">
                    <strong style="font-weight: 600;">Need Help?</strong> If you have any questions about this message, please contact our support team.
                </p>
            </td>
        </tr>
    </table>
@endsection


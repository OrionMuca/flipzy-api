@extends('emails.layouts.app')

@section('content')
    <p style="margin: 0 0 24px; color: #374151; font-size: 17px; line-height: 28px; font-weight: 400;">
        We wanted to give you an update on our launch progress and share some exciting news!
    </p>
    
    <!-- Update Content Box - Enhanced design -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 28px 0;">
        <tr>
            <td style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-left: 4px solid #2563eb; border-radius: 12px; padding: 28px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <div style="color: #374151; font-size: 16px; line-height: 26px; white-space: pre-line;">
                    {!! nl2br(e($updateContent ?? 'We have exciting updates coming soon!')) !!}
                </div>
            </td>
        </tr>
    </table>
    
    <!-- Thank You Box - Enhanced design -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 28px 0;">
        <tr>
            <td style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-left: 4px solid #2563eb; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1);">
                <p style="margin: 0; color: #1e40af; font-size: 14px; line-height: 22px;">
                    <strong style="font-weight: 600;">Thank You:</strong> We appreciate your patience and support. We're working hard to bring you the best experience possible.
                </p>
            </td>
        </tr>
    </table>
    
    <p style="margin: 0 0 24px; color: #374151; font-size: 15px; line-height: 24px;">
        Stay tuned for more updates!
    </p>
@endsection

@extends('emails.layouts.app')

@section('content')
    <!-- Icon Container - Using table for email compatibility -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 24px;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td class="email-icon-container" style="width: 64px; height: 64px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 16px; text-align: center; vertical-align: middle;">
                            <span class="email-icon-size" style="font-size: 32px; line-height: 64px; display: inline-block;">📢</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <p style="margin: 0 0 20px; color: #374151; font-size: 17px; line-height: 26px;">
        We wanted to give you an update on our launch progress.
    </p>
    
    <div style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-left: 4px solid #3b82f6; border-radius: 12px; padding: 24px; margin: 24px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
        <div style="color: #374151; font-size: 16px; line-height: 26px; white-space: pre-line;">
            {!! nl2br(e($updateContent)) !!}
        </div>
    </div>
    
    <div style="background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 6px; padding: 16px; margin: 24px 0;">
        <p style="margin: 0; color: #1e40af; font-size: 14px; line-height: 20px;">
            <strong>🙏 Thank You:</strong> We appreciate your patience and support. We're working hard to bring you the best experience possible.
        </p>
    </div>
    
    <p style="margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 24px;">
        Stay tuned for more updates!
    </p>
@endsection

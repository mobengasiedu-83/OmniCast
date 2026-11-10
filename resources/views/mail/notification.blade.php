<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#1f1b2e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f7;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;padding:32px;">
                    <tr>
                        <td style="padding-bottom:16px;border-bottom:1px solid #ececf2;">
                            <span style="font-size:13px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#6d28d9;">
                                {{ config('app.name') }}
                            </span>
                            <span style="font-size:11px;color:#8b8798;">&nbsp;·&nbsp;{{ $categoryLabel }}</span>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding-top:24px;font-size:19px;line-height:1.35;font-weight:600;{{ $severity->value === 'critical' ? 'color:#b91c1c;' : '' }}">
                            {{ $title }}
                        </td>
                    </tr>

                    <tr>
                        <td style="padding-top:12px;font-size:15px;line-height:1.6;color:#3f3b52;">
                            {!! nl2br(e($body)) !!}
                        </td>
                    </tr>

                    @if ($actionUrl)
                        <tr>
                            <td style="padding-top:24px;">
                                <a href="{{ $actionUrl }}" style="display:inline-block;padding:11px 20px;border-radius:10px;background:#6d28d9;color:#ffffff;font-size:14px;font-weight:600;text-decoration:none;">
                                    {{ $actionLabel ?: 'Open '.config('app.name') }}
                                </a>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding-top:28px;border-top:1px solid #ececf2;font-size:12px;line-height:1.6;color:#8b8798;">
                            You are receiving this email because this notification category is
                            switched to email on your account.
                            <a href="{{ $preferencesUrl }}" style="color:#6d28d9;text-decoration:underline;">Manage notification preferences</a>.
                        </td>
                    </tr>
                </table>

                <p style="margin:16px 0 0;font-size:11px;color:#8b8798;">
                    {{ config('app.name') }} · notifications
                </p>
                <p style="margin:4px 0 0;font-size:11px;color:#8b8798;">
                    Powered by <a href="https://sysup360.com" style="color:#8b8798;text-decoration:underline;">SysUp360</a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>

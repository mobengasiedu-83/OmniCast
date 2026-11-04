<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#1f1b2e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f7;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;padding:32px;">
                    <tr>
                        <td style="font-size:15px;line-height:1.6;">
                            {!! $body !!}
                        </td>
                    </tr>
                </table>

                <p style="margin:16px 0 0;font-size:11px;color:#8b8798;">
                    {{ config('app.name') }} · sent for campaign {{ $reference }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>

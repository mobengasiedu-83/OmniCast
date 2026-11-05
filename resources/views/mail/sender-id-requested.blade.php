<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New sender ID request</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#1f1b2e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f7;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;padding:32px;">
                    <tr>
                        <td>
                            <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.16em;text-transform:uppercase;color:#6b4ae8;font-weight:600;">{{ config('app.name') }}</p>
                            <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;">New {{ $channelLabel }} sender ID request</h1>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;">
                                {{ $requesterName ?? 'A client user' }} at <strong>{{ $clientName }}</strong> requested a {{ strtolower($channelLabel) }} sender ID. Review it before it can be used in campaigns.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8f7fc;border-radius:12px;padding:16px;font-size:14px;line-height:1.6;">
                                <tr>
                                    <td style="padding:2px 0;color:#57526b;">{{ $identifierLabel }}</td>
                                    <td style="padding:2px 0;text-align:right;font-weight:600;">{{ $senderName }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:2px 0;color:#57526b;">Channel</td>
                                    <td style="padding:2px 0;text-align:right;font-weight:600;">{{ $channelLabel }}</td>
                                </tr>
                                @if ($country)
                                    <tr>
                                        <td style="padding:2px 0;color:#57526b;">Country</td>
                                        <td style="padding:2px 0;text-align:right;font-weight:600;">{{ $country }}</td>
                                    </tr>
                                @endif
                                @if ($requestedAt)
                                    <tr>
                                        <td style="padding:2px 0;color:#57526b;">Requested</td>
                                        <td style="padding:2px 0;text-align:right;font-weight:600;">{{ $requestedAt }}</td>
                                    </tr>
                                @endif
                            </table>

                            @if ($purpose)
                                <p style="margin:20px 0 0;font-size:14px;line-height:1.6;"><strong>Intended use</strong><br>{{ $purpose }}</p>
                            @endif

                            <p style="margin:24px 0 0;">
                                <a href="{{ $reviewUrl }}" style="display:inline-block;background:#6b4ae8;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 22px;border-radius:10px;">Review sender IDs</a>
                            </p>

                            <p style="margin:20px 0 0;font-size:13px;line-height:1.6;color:#57526b;">No action is needed if another reviewer has already approved the request.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top:24px;border-top:1px solid #eceaf3;">
                            <p style="margin:0;font-size:11px;line-height:1.6;color:#8b8798;">
                                Powered by <a href="https://sysup360.com" style="color:#6b4ae8;text-decoration:none;font-weight:600;">SysUp360</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

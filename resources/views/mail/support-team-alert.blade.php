<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $headline }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#1f1b2e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f7;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background:#ffffff;border-radius:16px;padding:32px;">
                    <tr>
                        <td>
                            <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.16em;text-transform:uppercase;color:#6b4ae8;font-weight:600;">{{ $reference }} · {{ $priority }}</p>
                            <h1 style="margin:0 0 8px;font-size:22px;line-height:1.3;">{{ $headline }}</h1>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#57526b;">{{ $subjectLine }}</p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8f7fc;border-radius:12px;padding:16px;font-size:14px;line-height:1.6;">
                                <tr>
                                    <td style="padding:2px 0;color:#57526b;">From</td>
                                    <td style="padding:2px 0;text-align:right;font-weight:600;">{{ $requesterName }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:2px 0;color:#57526b;">Email</td>
                                    <td style="padding:2px 0;text-align:right;font-weight:600;">{{ $requesterEmail }}</td>
                                </tr>
                                @if ($requesterPhone)
                                    <tr>
                                        <td style="padding:2px 0;color:#57526b;">Phone</td>
                                        <td style="padding:2px 0;text-align:right;font-weight:600;">{{ $requesterPhone }}</td>
                                    </tr>
                                @endif
                                @if ($company)
                                    <tr>
                                        <td style="padding:2px 0;color:#57526b;">Organisation</td>
                                        <td style="padding:2px 0;text-align:right;font-weight:600;">{{ $company }}</td>
                                    </tr>
                                @endif
                                @if ($category)
                                    <tr>
                                        <td style="padding:2px 0;color:#57526b;">Category</td>
                                        <td style="padding:2px 0;text-align:right;font-weight:600;">{{ $category }}</td>
                                    </tr>
                                @endif
                                @if ($team)
                                    <tr>
                                        <td style="padding:2px 0;color:#57526b;">Team</td>
                                        <td style="padding:2px 0;text-align:right;font-weight:600;">{{ $team }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:2px 0;color:#57526b;">Status</td>
                                    <td style="padding:2px 0;text-align:right;font-weight:600;">{{ $status }}</td>
                                </tr>
                            </table>

                            <p style="margin:20px 0 0;font-size:14px;line-height:1.6;"><strong>What they wrote</strong></p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8f7fc;border-radius:12px;padding:16px;font-size:15px;line-height:1.6;">
                                <tr>
                                    <td>{!! nl2br(e($body)) !!}</td>
                                </tr>
                            </table>

                            @if ($actionUrl)
                                <p style="margin:24px 0 0;">
                                    <a href="{{ $actionUrl }}" style="display:inline-block;background:#6b4ae8;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 22px;border-radius:10px;">Open in OmniCast</a>
                                </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top:24px;border-top:1px solid #eceaf3;">
                            <p style="margin:0;font-size:11px;line-height:1.6;color:#8b8798;">
                                Sent to the {{ $team ?? 'support' }} team. Sending of these alerts can be changed in Admin → Support → Notifications.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

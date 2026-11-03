<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>You have been invited</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#1f1b2e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f7;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;padding:32px;">
                    <tr>
                        <td>
                            <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.16em;text-transform:uppercase;color:#6b4ae8;font-weight:600;">
                                {{ config('app.name') }}
                            </p>

                            <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;">
                                @if ($organisation)
                                    Join {{ $organisation }}
                                @else
                                    Join the platform team
                                @endif
                            </h1>

                            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;">
                                @if ($inviterName)
                                    {{ $inviterName }} invited you
                                @else
                                    You have been invited
                                @endif
                                @if ($organisation)
                                    to the {{ $organisation }} account
                                @else
                                    to the {{ config('app.name') }} platform
                                @endif
                                as a <strong>{{ $roleLabel }}</strong>.
                            </p>

                            <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#57526b;">
                                This invitation was sent to <strong>{{ $email }}</strong>. Set a password to activate the account.
                            </p>

                            <p style="margin:0 0 24px;">
                                <a href="{{ $acceptUrl }}" style="display:inline-block;background:#6b4ae8;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 22px;border-radius:10px;">
                                    Accept invitation
                                </a>
                            </p>

                            <p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:#57526b;">
                                The link expires on {{ $expiresAt }}. If it lapses, ask an administrator to send a new invitation.
                            </p>

                            <p style="margin:0;font-size:12px;line-height:1.6;color:#8c87a1;word-break:break-all;">
                                {{ $acceptUrl }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

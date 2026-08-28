<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Your sign-in code</title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <![endif]-->
    <style>
        @media only screen and (max-width: 600px) {
            .sm-px { padding-left: 24px !important; padding-right: 24px !important; }
            .sm-py { padding-top: 32px !important; padding-bottom: 32px !important; }
            .sm-code { font-size: 34px !important; letter-spacing: 8px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; width:100%; background-color:#f4f5f7; -webkit-font-smoothing:antialiased; font-family:'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Your {{ $appName }} sign-in code is {{ $code }}. It expires in {{ $expiresInMinutes }} minutes.
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f4f5f7;">
        <tr>
            <td align="center" style="padding:40px 16px;">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:480px; width:100%;">

                    <!-- Brand -->
                    <tr>
                        <td style="padding:0 0 24px 8px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <span style="display:inline-block; width:12px; height:12px; background-color:#1d5d4b; border-radius:50%; vertical-align:middle;"></span>
                                    </td>
                                    <td style="vertical-align:middle; padding-left:10px;">
                                        <span style="font-size:18px; font-weight:700; color:#171d26; letter-spacing:-0.01em;">{{ $appName }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Card -->
                    <tr>
                        <td class="sm-px sm-py" style="background-color:#ffffff; border:1px solid #e2e5e9; border-radius:12px; padding:44px 44px; box-shadow:0 1px 2px rgba(23,29,38,0.06);">

                            <p style="margin:0 0 6px; font-size:13px; font-weight:600; letter-spacing:0.16em; text-transform:uppercase; color:#1d5d4b;">
                                Sign-in code
                            </p>
                            <h1 style="margin:0 0 8px; font-size:24px; line-height:1.2; font-weight:600; color:#171d26;">
                                Hi {{ $name }},
                            </h1>
                            <p style="margin:0 0 28px; font-size:15px; line-height:1.6; color:#606976;">
                                Enter the code below to finish signing in to your workspace.
                            </p>

                            <!-- Code block -->
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="background-color:#171d26; border-radius:8px; padding:24px 16px;">
                                        <div class="sm-code" style="font-size:40px; font-weight:700; letter-spacing:12px; color:#f4f5f7; font-family:'SF Mono', SFMono-Regular, Menlo, Consolas, monospace; padding-left:12px;">
                                            {{ $code }}
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:22px 0 0; font-size:14px; line-height:1.6; color:#606976;">
                                This code expires in <strong style="color:#171d26;">{{ $expiresInMinutes }} minutes</strong>. For your security, don&rsquo;t share it with anyone.
                            </p>

                            <hr style="border:none; border-top:1px solid #e2e5e9; margin:28px 0;">

                            <p style="margin:0; font-size:13px; line-height:1.6; color:#8a919b;">
                                Didn&rsquo;t try to sign in? You can safely ignore this email &mdash; your account is still secure.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:28px 8px 0; text-align:center;">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#8a919b;">
                                &copy; {{ $year }} {{ $appName }} &middot; This is an automated message, please don&rsquo;t reply.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>

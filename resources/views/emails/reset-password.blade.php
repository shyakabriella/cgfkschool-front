<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Reset Your Password</title>
</head>

<body style="
    margin: 0;
    padding: 0;
    background-color: #eef3fb;
    font-family: Arial, Helvetica, sans-serif;
    color: #0f172a;
">
    <table
        role="presentation"
        width="100%"
        cellspacing="0"
        cellpadding="0"
        border="0"
        style="background-color: #eef3fb;"
    >
        <tr>
            <td align="center" style="padding: 40px 16px;">
                <table
                    role="presentation"
                    width="100%"
                    cellspacing="0"
                    cellpadding="0"
                    border="0"
                    style="
                        max-width: 600px;
                        overflow: hidden;
                        background-color: #ffffff;
                        border-radius: 20px;
                        box-shadow: 0 15px 45px rgba(30, 64, 175, 0.12);
                    "
                >
                    <tr>
                        <td
                            align="center"
                            style="
                                padding: 32px;
                                background-color: #102a43;
                            "
                        >
                            <img
                                src="{{ $frontendUrl }}/lo.png"
                                alt="CGFK School logo"
                                width="80"
                                height="80"
                                style="
                                    display: block;
                                    max-width: 80px;
                                    max-height: 80px;
                                    object-fit: contain;
                                    padding: 6px;
                                    background-color: #ffffff;
                                    border-radius: 14px;
                                "
                            >

                            <h1 style="
                                margin: 18px 0 0;
                                color: #ffffff;
                                font-size: 24px;
                                line-height: 32px;
                            ">
                                CGFK School
                            </h1>

                            <p style="
                                margin: 5px 0 0;
                                color: #cbd5e1;
                                font-size: 13px;
                            ">
                                School Management System
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 38px 36px;">
                            <p style="
                                margin: 0 0 18px;
                                font-size: 16px;
                                line-height: 26px;
                            ">
                                Hello {{ $user->name }},
                            </p>

                            <p style="
                                margin: 0 0 18px;
                                color: #475569;
                                font-size: 15px;
                                line-height: 25px;
                            ">
                                We received a request to reset the password for
                                your CGFK School Management System account.
                            </p>

                            <p style="
                                margin: 0 0 28px;
                                color: #475569;
                                font-size: 15px;
                                line-height: 25px;
                            ">
                                Click the button below to create a new password.
                            </p>

                            <table
                                role="presentation"
                                width="100%"
                                cellspacing="0"
                                cellpadding="0"
                                border="0"
                            >
                                <tr>
                                    <td align="center">
                                        <a
                                            href="{{ $resetUrl }}"
                                            style="
                                                display: inline-block;
                                                padding: 14px 28px;
                                                background-color: #1d4ed8;
                                                color: #ffffff;
                                                text-decoration: none;
                                                font-size: 15px;
                                                font-weight: bold;
                                                border-radius: 10px;
                                            "
                                        >
                                            Reset My Password
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <div style="
                                margin-top: 30px;
                                padding: 16px;
                                background-color: #f8fafc;
                                border-left: 4px solid #3b82f6;
                                border-radius: 8px;
                            ">
                                <p style="
                                    margin: 0;
                                    color: #475569;
                                    font-size: 13px;
                                    line-height: 21px;
                                ">
                                    This password reset link will expire in
                                    {{ $expirationMinutes }} minutes.
                                </p>
                            </div>

                            <p style="
                                margin: 24px 0 0;
                                color: #64748b;
                                font-size: 13px;
                                line-height: 21px;
                            ">
                                If you did not request a password reset, you
                                can safely ignore this email. Your password
                                will remain unchanged.
                            </p>

                            <p style="
                                margin: 28px 0 0;
                                color: #475569;
                                font-size: 14px;
                                line-height: 22px;
                            ">
                                Regards,<br>
                                <strong>CGFK School Administration</strong>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td
                            align="center"
                            style="
                                padding: 20px 32px;
                                background-color: #f8fafc;
                                border-top: 1px solid #e2e8f0;
                            "
                        >
                            <p style="
                                margin: 0;
                                color: #94a3b8;
                                font-size: 11px;
                                line-height: 18px;
                            ">
                                This is an automatic security notification.
                                Please do not reply to this email.
                            </p>

                            <p style="
                                margin: 5px 0 0;
                                color: #94a3b8;
                                font-size: 11px;
                            ">
                                © {{ date('Y') }} CGFK School Management System
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

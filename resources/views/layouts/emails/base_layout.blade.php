@php
    $colors = config('theme.colors');
    $fonts = config('theme.fonts');
    $fontSans = "'{$fonts['sans']['family']}', {$fonts['sans']['fallback']}";
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
    <link href="{{ $fonts['url'] }}" rel="stylesheet">
    <style type="text/css">
        /* Reset styles */
        body, table, td, a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }
        body {
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }

        /* Base styles */
        body {
            {{-- Unescaped: a <style> element does not decode entities, unlike the style attributes below. --}}
            font-family: {!! $fontSans !!};
            background-color: {{ $colors['surface'] }};
            margin: 0;
            padding: 0;
        }

        /* Typography */
        h1 {
            margin: 0 0 24px 0;
            font-size: 28px;
            font-weight: 700;
            color: {{ $colors['ink'] }};
            line-height: 1.3;
        }

        p {
            margin: 0 0 16px 0;
            font-size: 16px;
            color: {{ $colors['ink'] }};
            line-height: 1.5;
        }

        a {
            color: {{ $colors['primary'] }};
            text-decoration: none;
        }

        /* Mobile styles */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                margin: 0 !important;
            }

            .header-content,
            .body-content,
            .footer-content {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }

            h1 {
                font-size: 24px !important;
            }

            p {
                font-size: 14px !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: {{ $colors['surface'] }}; font-family: {{ $fontSans }};">

    <!-- Main Container -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: {{ $colors['surface'] }};">
        <tr>
            <td style="padding: 20px 0;">

                <!-- Email Container -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container" style="margin: 0 auto; background-color: {{ $colors['paper'] }}; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px {{ $colors['border'] }};">

                    <!-- Header / Jumbo Section -->
                    <tr>
                        <td class="header-content" style="padding: 40px 40px 0; text-align: center; background-color: {{ $colors['paper'] }};">
                            <span style="font-size: 24px; font-weight: 700; color: {{ $colors['ink'] }}; letter-spacing: 0.5px;">{{ config('app.name') }}</span>
                        </td>
                    </tr>

                    <!-- Main Content Body -->
                    <tr>
                        <td class="body-content" style="padding: 60px 40px; background-color: {{ $colors['paper'] }};">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- Footer Section -->
                    <tr>
                        <td class="footer-content" style="padding: 0 40px 40px; background-color: {{ $colors['paper'] }}; text-align: center;">

                            <!-- Separator Line -->
                            <hr style="border: none; border-top: 1px solid {{ $colors['border'] }}; margin: 0 0 24px 0;">

                            <!-- Copyright and Links in Single Line -->
                            <p style="margin: 0; font-size: 13px; color: {{ $colors['gray'] }}; line-height: 1.5;">
                                @lang('emails.generic.copyright', ['year' => now()->year])
                                &nbsp;&nbsp;|&nbsp;&nbsp;
                                <a href="{{ rtrim(config('app.url'), '/') }}/{{ trans('emails.generic.privacy_policy_url') }}" style="color: {{ $colors['gray'] }}; text-decoration: underline;">@lang('emails.generic.privacy_policy')</a>
                                &nbsp;&nbsp;|&nbsp;&nbsp;
                                <a href="{{ rtrim(config('app.url'), '/') }}/{{ trans('emails.generic.terms_conditions_url') }}" style="color: {{ $colors['gray'] }}; text-decoration: underline;">@lang('emails.generic.terms_conditions')</a>
                            </p>

                        </td>
                    </tr>

                </table>
                <!-- End Email Container -->

            </td>
        </tr>
    </table>
    <!-- End Main Container -->

</body>
</html>

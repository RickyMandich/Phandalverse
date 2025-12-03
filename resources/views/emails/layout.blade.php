<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #343a40; border-radius: 8px; overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #212529; padding: 20px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px;">{{ config('app.name') }}</h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px; color: #e9ecef;">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- Footer -->
                    @unless(isset($hideDefaultFooter) && $hideDefaultFooter)
                    <tr>
                        <td style="background-color: #212529; padding: 15px; text-align: center;">
                            <p style="color: #6c757d; margin: 0; font-size: 12px;">
                                © {{ date('Y') }} {{ config('app.name') }}. Tutti i diritti riservati.
                            </p>
                        </td>
                    </tr>
                    @endunless
                </table>
            </td>
        </tr>
    </table>
</body>
</html>


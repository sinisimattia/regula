@props(['url', 'label'])
@php
    $colors = config('theme.colors');
    $fonts = config('theme.fonts');
@endphp

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 32px 0 0 0;">
    <tr>
        <td align="center" style="padding: 0;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td bgcolor="{{ $colors['primary'] }}" style="border-radius: 16px; background-color: {{ $colors['primary'] }}; padding: 0;">
                        <a href="{{ $url }}" target="_blank" style="display: block; padding: 16px 14px; font-family: '{{ $fonts['sans']['family'] }}', {{ $fonts['sans']['fallback'] }}; font-size: 16px; font-weight: 600; color: {{ $colors['paper'] }}; text-decoration: none;">
                            {{ $label }}
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

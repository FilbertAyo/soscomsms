@props(['url'])
@php
    $logoSrc = \App\Support\MailLogo::src($message ?? null);
@endphp
<tr>
<td class="header" align="center">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<table role="presentation" cellpadding="0" cellspacing="0" align="center">
<tr>
<td style="vertical-align: middle; padding-right: 10px;">
<img src="{{ $logoSrc }}" width="40" height="40" alt="" class="logo" style="display: block; border: 0; width: 40px; height: 40px;">
</td>
<td class="brand" style="vertical-align: middle;">
soscom<span class="brand-sms">sms</span>
</td>
</tr>
</table>
</a>
</td>
</tr>

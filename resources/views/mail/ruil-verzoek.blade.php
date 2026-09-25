<p style="margin:0 0 16px;">{!! nl2br(e($tekst)) !!}</p>
@if($herinnering)<p style="color:#b34700;font-weight:bold;">Dit is een herinnering: het verzoek is nog niet beantwoord.</p>@endif
<table cellpadding="0" cellspacing="0"><tr>
  <td style="padding-right:12px;"><a href="{{ $bevestigUrl }}" style="display:inline-block;background:#1F7A4D;color:#fff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:bold;">Bevestigen</a></td>
  <td><a href="{{ $afwijsUrl }}" style="display:inline-block;background:#fff;color:#a12;text-decoration:none;padding:11px 22px;border-radius:6px;font-weight:bold;border:1px solid #a12;">Afwijzen</a></td>
</tr></table>
<p style="margin:18px 0 0;color:#666;font-size:13px;">Of bekijk het verzoek in de app: <a href="{{ $appUrl }}">{{ $appUrl }}</a></p>

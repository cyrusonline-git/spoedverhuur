<!DOCTYPE html>
<html lang="nl"><head><meta charset="utf-8"><title>{{ $onderwerp }}</title></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#222;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:24px 0;"><tr><td align="center">
<table width="640" cellpadding="0" cellspacing="0" style="max-width:640px;width:100%;background:#fff;border-radius:8px;overflow:hidden;">
  <tr><td style="background:#FF6600;padding:16px 24px;">
    <table cellpadding="0" cellspacing="0"><tr>
      <td style="background:#fff;border-radius:6px;width:36px;height:36px;text-align:center;font-weight:bold;font-size:22px;color:#FF6600;font-family:Arial,sans-serif;">B</td>
      <td style="padding-left:12px;color:#fff;font-weight:bold;font-size:16px;">Boels Industrial · Spoedverhuur</td>
    </tr></table>
  </td></tr>
  <tr><td style="padding:24px;font-size:15px;line-height:1.5;">
    @if($html)
      {!! $inhoud !!}
    @else
      {!! nl2br(e($inhoud)) !!}
    @endif
  </td></tr>
  <tr><td style="padding:14px 24px;background:#fafafa;color:#888;font-size:12px;border-top:1px solid #eee;">
    Automatisch bericht van de Spoedverhuur-app · {{ rtrim(config('app.url'), '/') }}
  </td></tr>
</table>
</td></tr></table>
</body></html>

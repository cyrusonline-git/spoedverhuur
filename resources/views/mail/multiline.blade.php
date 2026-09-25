<p style="margin:0 0 14px;">{!! nl2br(e($intro)) !!}</p>
<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:14px;">
  <thead><tr style="background:#FF6600;color:#fff;"><th align="left">Dienst</th><th align="left">Naam</th><th align="left">Mobiel</th></tr></thead>
  <tbody>
  @foreach($rijen as $r)
    <tr style="border-bottom:1px solid #e5e5e5;">
      <td>{{ $r['dienst'] }}</td>
      <td>{{ $r['naam'] }}@if($r['dagen']) <span style="color:#777;">({{ $r['dagen'] }})</span>@endif</td>
      <td>{{ $r['telefoon'] ?: 'onbekend' }}</td>
    </tr>
  @endforeach
  </tbody>
</table>
<p style="margin:14px 0 0;color:#666;font-size:13px;">Geldig van maandag {{ $week->van->format('d-m-Y') }} t/m zondag {{ $week->tm->format('d-m-Y') }} (week {{ $week->weeknummer }}).</p>

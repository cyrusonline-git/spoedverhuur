@php($kleur = ['aangevraagd' => 'warning text-dark', 'bevestigd' => 'success', 'afgewezen' => 'danger', 'ingetrokken' => 'secondary', 'verlopen' => 'secondary', 'teruggedraaid' => 'dark'][$r->status] ?? 'light text-dark')
<span class="badge bg-{{ $kleur }}">{{ $r->statusLabel() }}</span>

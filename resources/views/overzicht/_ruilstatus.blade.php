@php($kleur = ['aangevraagd' => 'warning text-dark', 'bevestigd' => 'success', 'afgewezen' => 'danger', 'ingetrokken' => 'secondary', 'verlopen' => 'secondary', 'teruggedraaid' => 'dark'][$status] ?? 'secondary')
<span class="badge bg-{{ $kleur }} text-nowrap">{{ $label }}</span>

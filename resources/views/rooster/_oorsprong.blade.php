@php($oorsprongen = ['ruil' => ['arrow-left-right', 'Geruild', 'info'], 'overname' => ['person-down', 'Overgenomen', 'info'], 'deel' => ['scissors', 'Dagen gedeeld', 'info'], 'handmatig' => ['pencil', 'Handmatig gezet', 'secondary']])
@if(isset($oorsprongen[$t->oorsprong]))
@php($o = $oorsprongen[$t->oorsprong])
<span class="badge bg-{{ $o[2] }} ms-1" title="{{ $o[1] }}{{ $t->ruiling ? ' (ruiling #'.$t->ruiling->id.')' : '' }}" data-bs-toggle="tooltip"><i class="bi bi-{{ $o[0] }}"></i></span>
@endif

{{-- Eén regel in een ruilingen-tabel. Verwacht: $r, $service, $eigen, optioneel $beheer --}}
@php($eigenId = $eigen?->id)
@php($wachtOpMij = $r->isOpen() && $eigenId && $r->moetBevestigen()?->id === $eigenId)
@php($magIntrekken = $r->isOpen() && (($eigenId && $r->aangevraagd_door_id === $eigenId) || ! empty($beheer)))
<tr>
    <td class="text-nowrap small text-muted">{{ $r->created_at?->format('d-m-Y H:i') }}</td>
    <td>
        <a href="{{ route('ruilen.toon', $r) }}" class="text-decoration-none fw-semibold text-dark">{{ $service->omschrijving($r) }}</a>
        @if($r->dienst?->week)
            <div class="small text-muted">{{ $r->dienst->week->van->format('d-m') }} t/m {{ $r->dienst->week->tm->format('d-m-Y') }}
                @if($r->type === 'ruil' && $r->tegenDienst?->week) ⇄ {{ $r->tegenDienst->week->van->format('d-m') }} t/m {{ $r->tegenDienst->week->tm->format('d-m-Y') }} @endif
            </div>
        @endif
        @if($r->opmerking)<div class="small text-muted fst-italic"><i class="bi bi-chat-left-text me-1"></i>{{ \Illuminate\Support\Str::limit($r->opmerking, 90) }}</div>@endif
    </td>
    <td class="small">{{ $r->aangevraagdDoor?->naam ?? '—' }}</td>
    <td class="small">
        @if($r->isOpen())
            {{ $r->moetBevestigen()?->naam ?? '—' }}
        @else
            {{ $r->afgehandeld_door ?: '—' }}
        @endif
    </td>
    <td>@include('ruilen._badge', ['r' => $r])</td>
    <td class="text-end text-nowrap">
        @if($wachtOpMij)
            <form method="post" action="{{ route('ruilen.bevestig', $r) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success" onclick="return confirm('Bevestig je deze ruiling? Het rooster wordt direct aangepast.')"><i class="bi bi-check-lg me-1"></i>Bevestigen</button></form>
            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#afwijs-{{ $r->id }}"><i class="bi bi-x-lg me-1"></i>Afwijzen</button>
            <div class="modal fade" id="afwijs-{{ $r->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                <form method="post" action="{{ route('ruilen.afwijs', $r) }}">@csrf
                    <div class="modal-header"><h5 class="modal-title">Ruilverzoek afwijzen</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body text-start">
                        <p class="small text-muted">{{ $service->omschrijving($r) }}</p>
                        <label class="form-label">Reden (optioneel, gaat mee in de mail naar de aanvrager)</label>
                        <textarea name="reden" class="form-control" rows="3" maxlength="500"></textarea>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuleren</button><button class="btn btn-danger">Afwijzen</button></div>
                </form>
            </div></div></div>
        @elseif($magIntrekken)
            <form method="post" action="{{ route('ruilen.intrek', $r) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary" onclick="return confirm('Verzoek intrekken?')"><i class="bi bi-arrow-counterclockwise me-1"></i>Intrekken</button></form>
        @endif
        <a href="{{ route('ruilen.toon', $r) }}" class="btn btn-sm btn-light" title="Details"><i class="bi bi-eye"></i></a>
    </td>
</tr>

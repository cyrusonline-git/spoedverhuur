@extends('layouts.app')
@section('titel', 'Rooster importeren')
@section('inhoud')
<div class="page-header mb-3">
    <h1><i class="bi bi-upload me-2 text-boels"></i>Rooster importeren</h1>
    <p>Stap 1 van 3: kies het Excel-dienstrooster. Je krijgt daarna een voorbeeld en een koppelscherm; er wordt pas iets opgeslagen bij stap 3.</p>
</div>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-file-earmark-spreadsheet me-2 text-boels"></i>Bestand kiezen</div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.import.lees') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="bestand" class="form-label">Excel-bestand (.xlsx, max. 10 MB)</label>
                        <input type="file" class="form-control" id="bestand" name="bestand" accept=".xlsx" required>
                    </div>
                    <button class="btn btn-boels" type="submit"><i class="bi bi-eye me-1"></i>Inlezen en voorbeeld tonen</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-2 text-boels"></i>Verwacht formaat</div>
            <div class="card-body small">
                <p>Het eerste werkblad wordt gelezen, zoals "Dienstrooster-2026 Industrial.xlsx":</p>
                <ul>
                    <li><strong>Rij 1 = koppen:</strong> <code>Week #</code> | <code>Van</code> | <code>t/m</code> | daarna per kolom een dienstsoort (bijv. <em>Storingsdienst Energy West</em>, <em>Spoedverhuur 1e lijns</em>, …). Onbekende koppen worden als nieuwe dienstsoort aangemaakt.</li>
                    <li><strong>Rij 2 en verder:</strong> <code>Week 12</code> | maandag (datum) | zondag (datum) | per dienstsoort de naam van de dienstdoende.</li>
                    <li><strong>Twee personen in één cel</strong> (bijv. <em>Wouter &amp; Maurice</em> of <em>Patrick / David</em>) = gedeelde week; in het koppelscherm geef je aan wie welke dagen doet (standaard ma–wo en do–zo).</li>
                    <li>Namen worden herkend via de medewerkerkaart uit CORE en eerder geleerde schrijfwijzen. Onbekende namen koppel je in stap 2; die koppeling wordt onthouden.</li>
                    <li>Weken in het verleden en weken met een bevestigde ruiling worden niet overschreven (verleden optioneel wel).</li>
                    <li>De vaste tweede lijn h&amp;h staat niet in het Excel-bestand en blijft zoals ingesteld bij <a href="{{ route('admin.dienstsoorten') }}">Dienstsoorten</a>.</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history me-2 text-boels"></i>Laatste imports</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle small">
                    <thead><tr><th>Wanneer</th><th>Bestand</th><th>Jaar</th><th class="text-end">Weken</th><th class="text-end">Diensten</th><th>Door</th><th>Meldingen</th></tr></thead>
                    <tbody>
                        @forelse($imports as $imp)
                            <tr>
                                <td class="text-nowrap">{{ $imp->created_at->format('d-m-Y H:i') }}</td>
                                <td>{{ $imp->bestandsnaam }}<br><span class="text-muted">blad: {{ $imp->blad ?: '—' }}</span></td>
                                <td>{{ $imp->jaar ?: '—' }}</td>
                                <td class="text-end">{{ $imp->aantal_weken }}</td>
                                <td class="text-end">{{ $imp->aantal_diensten }}</td>
                                <td>{{ $imp->door_naam ?: '—' }}</td>
                                <td>
                                    @if(empty($imp->meldingen))<span class="text-success">geen</span>
                                    @else
                                        <details><summary>{{ count($imp->meldingen) }} melding(en)</summary><ul class="mb-0 ps-3">@foreach($imp->meldingen as $m)<li>{{ $m }}</li>@endforeach</ul></details>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">Nog geen imports uitgevoerd.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

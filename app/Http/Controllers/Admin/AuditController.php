<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Logboek: alle wijzigingen (rooster, ruilingen, instellingen, verzendingen), nieuwste eerst. */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        $f = [
            'actie' => trim((string) $request->input('actie', '')),
            'wie' => trim((string) $request->input('wie', '')),
            'van' => (string) $request->input('van', ''),
            'tot' => (string) $request->input('tot', ''),
            'zoek' => trim((string) $request->input('zoek', '')),
        ];
        $q = AuditLog::query()->orderByDesc('created_at')->orderByDesc('id');
        if ($f['actie'] !== '') {
            $q->where('actie', 'like', $f['actie'].'%');
        }
        if ($f['wie'] !== '') {
            $q->where('wie', 'like', '%'.$f['wie'].'%');
        }
        if ($f['van'] !== '') {
            $q->where('created_at', '>=', Carbon::parse($f['van'])->startOfDay());
        }
        if ($f['tot'] !== '') {
            $q->where('created_at', '<=', Carbon::parse($f['tot'])->endOfDay());
        }
        if ($f['zoek'] !== '') {
            $q->where(fn ($x) => $x->where('onderwerp', 'like', '%'.$f['zoek'].'%')->orWhere('details', 'like', '%'.$f['zoek'].'%'));
        }

        return view('admin.audit', [
            'lijst' => $q->paginate(50)->withQueryString(),
            'filters' => $f,
            'acties' => AuditLog::select('actie')->distinct()->orderBy('actie')->pluck('actie'),
            'namen' => AuditLog::select('wie')->whereNotNull('wie')->distinct()->orderBy('wie')->pluck('wie'),
        ]);
    }
}

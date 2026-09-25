<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_log';
    protected $fillable = ['wie', 'medewerker_id', 'actie', 'onderwerp', 'details', 'ip'];
    protected $casts = ['details' => 'array'];

    /** Snel loggen: audit('rooster.import', 'Dienstrooster 2026', [...]) */
    public static function schrijf(string $actie, ?string $onderwerp = null, array $details = [], ?int $medewerkerId = null): void
    {
        try {
            static::create([
                'wie' => core_gebruiker()['name'] ?? (app()->runningInConsole() ? 'scheduler' : null),
                'medewerker_id' => $medewerkerId ?? session('eigen_medewerker_id'),
                'actie' => $actie, 'onderwerp' => $onderwerp, 'details' => $details ?: null,
                'ip' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

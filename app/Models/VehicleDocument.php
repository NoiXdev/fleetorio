<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class VehicleDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'type',
        'title',
        'file_path',
        'original_filename',
        'file_size',
        'mime_type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'file_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Loescht die Datei mit, sobald das Dokument geloescht wird - egal ob
        // einzeln (RelationManager-Aktion) oder in Folge einer
        // Fahrzeugloeschung (siehe Vehicle::booted()). Ohne das saemmeln sich
        // Dateien auf dem privaten Disk an, die kein Datensatz mehr
        // referenziert - bei personenbezogenen Daten (Fahrgestellnummer,
        // Halterdaten im Fahrzeugschein) ein echtes Aufbewahrungsproblem.
        static::deleting(function (VehicleDocument $document): void {
            Storage::disk('local')->delete($document->file_path);
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Lesbare Groessenangabe (z.B. "2,4 MB"). Deutsches Dezimalkomma per
     * number_format(), damit es zur Oberflaechensprache passt.
     */
    public function formattedFileSize(): string
    {
        $bytes = $this->file_size;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 ** 2) {
            return number_format($bytes / 1024, 1, ',', '.').' KB';
        }

        return number_format($bytes / (1024 ** 2), 1, ',', '.').' MB';
    }
}

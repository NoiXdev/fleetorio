<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Erste Verteidigungslinie gegen ein boesartiges SVG-Logo: eine SVG-Datei
 * ist eine XML-Datei und kann eingebettetes Skript enthalten, das der
 * Browser ausfuehrt, sobald jemand direkt zur ausliefernden URL navigiert
 * (siehe Kommentar an BrandLogoController fuer die zweite, unabhaengige
 * Verteidigungslinie: den Content-Security-Policy-Header dort).
 *
 * Das Risiko ist gering - nur Administratoren duerfen ueberhaupt ein Logo
 * hochladen -, soll aber nicht unbehandelt bleiben. Bewusst eine grobe
 * Blockliste statt eines vollstaendigen XML-Parsers/-Sanitizers: fuer eine
 * von einem Administrator hochgeladene Bilddatei genuegt das, ein echter
 * Sanitizer (der z. B. auch verschachtelte <use>-Referenzen auf externe
 * Ressourcen unterbindet) waere fuer dieses Risiko unverhaeltnismaessiger
 * Aufwand.
 */
class RejectsUnsafeSvg implements ValidationRule
{
    private const DANGEROUS_PATTERN = '/<\s*script|<\s*foreignobject|<\s*iframe|<\s*embed|<\s*object|on\w+\s*=|javascript:/i';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        if (! $this->isSvg($value)) {
            return;
        }

        $contents = file_get_contents($value->getRealPath());

        if ($contents === false) {
            $fail('Die Datei konnte nicht gelesen werden.');

            return;
        }

        if (preg_match(self::DANGEROUS_PATTERN, $contents) === 1) {
            $fail('Diese SVG-Datei enthält nicht erlaubte Inhalte (Skripte oder Ereignis-Attribute).');
        }
    }

    private function isSvg(UploadedFile $file): bool
    {
        return $file->getClientOriginalExtension() === 'svg'
            || $file->getMimeType() === 'image/svg+xml';
    }
}

<?php

namespace App\Filament\Resources\FuelCardProviders\Pages;

use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\FuelCardProviders\FuelCardProviderResource;
use App\Models\FuelCard;
use Filament\Resources\Pages\EditRecord;

class EditFuelCardProvider extends EditRecord
{
    protected static string $resource = FuelCardProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Beim Tankkartenanbieter ist die naechste Stufe eine Tankkarte,
            // kein Fahrzeug - siehe Kommentar an GuardedDeleteAction.
            GuardedDeleteAction::make()
                ->guardedBy(
                    'fuelCards',
                    fn (FuelCard $fuelCard): string => $fuelCard->card_number,
                    fn (int $count, string $label): string => $count === 1
                        ? "Der Eintrag ist noch der Tankkarte {$label} zugeordnet."
                        : "Der Eintrag ist noch {$count} Tankkarten zugeordnet, unter anderem {$label}.",
                ),
        ];
    }
}

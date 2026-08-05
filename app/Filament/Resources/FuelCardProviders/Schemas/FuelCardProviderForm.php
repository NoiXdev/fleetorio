<?php

namespace App\Filament\Resources\FuelCardProviders\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FuelCardProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    // Ein inaktiver Anbieter belegt seinen Namen weiterhin.
                    // Ohne diesen Hinweis sucht man in der Liste nach einem
                    // Eintrag, der dort standardmaessig nicht sichtbar ist.
                    ->validationMessages([
                        'unique' => 'Dieser Name ist bereits vergeben — möglicherweise von einem '
                            .'inaktiven Anbieter. Über den Statusfilter in der Liste sind diese sichtbar.',
                    ]),
                Toggle::make('active')
                    ->label('Aktiv')
                    ->default(true)
                    ->helperText('Inaktive Einträge tauchen nicht mehr in Auswahllisten auf. Gelöscht werden kann nur, was inaktiv ist.'),
                Textarea::make('notes')
                    ->label('Notizen')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}

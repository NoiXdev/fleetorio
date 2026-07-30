<?php

use App\Enums\DocumentType;
use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\Vehicles\Pages\ViewVehicle;
use App\Filament\Resources\Vehicles\RelationManagers\VehicleDocumentsRelationManager;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

it('shows the documents relation manager on the vehicle edit page', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $vehicle = Vehicle::factory()->create();

    // Dokumente und Termine stehen untereinander statt in Tabs (siehe
    // VehicleResource::getRelations(), eine RelationGroup statt zwei
    // einzelner Eintraege) und sind beide nicht lazy (siehe
    // VehicleDocumentsRelationManager::$isLazy) - "Dokumente" steht daher
    // als Tabellenueberschrift direkt in der Erstauslieferung, unabhaengig
    // von einem aktiven Tab (den es hier gar nicht mehr gibt).
    livewire(EditVehicle::class, ['record' => $vehicle->id])
        ->assertOk()
        ->assertSee('Dokumente');
});

it('lists documents for the vehicle', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $vehicle = Vehicle::factory()->create();
    $document = VehicleDocument::factory()->for($vehicle)->create();

    livewire(VehicleDocumentsRelationManager::class, [
        'ownerRecord' => $vehicle,
        'pageClass' => EditVehicle::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$document]);
});

describe('uploading a document as an admin', function () {
    it('stores the file on the local disk, not the public one, with a random name and the original name kept separately', function () {
        Storage::fake('local');
        Storage::fake('public');

        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();

        $file = UploadedFile::fake()->create('Fahrzeugschein Original.pdf', 500, 'application/pdf');

        livewire(VehicleDocumentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => EditVehicle::class,
        ])
            ->callTableAction(CreateAction::class, data: [
                'file_path' => $file,
                'type' => DocumentType::Registration->value,
                'title' => 'Fahrzeugschein PKW',
                'notes' => 'Testnotiz',
            ])
            ->assertHasNoTableActionErrors();

        $document = VehicleDocument::where('vehicle_id', $vehicle->id)->firstOrFail();

        expect($document->original_filename)->toBe('Fahrzeugschein Original.pdf')
            ->and($document->file_path)->not->toBe('Fahrzeugschein Original.pdf')
            ->and($document->file_path)->not->toContain('Fahrzeugschein')
            ->and($document->mime_type)->toBe('application/pdf')
            ->and($document->file_size)->toBeGreaterThan(0)
            ->and($document->type)->toBe(DocumentType::Registration)
            ->and($document->title)->toBe('Fahrzeugschein PKW');

        Storage::disk('local')->assertExists($document->file_path);
        Storage::disk('public')->assertMissing($document->file_path);
    });
});

describe('upload restrictions with german error messages', function () {
    // Laravels eigene validation.php liegt in diesem Projekt nur auf Englisch
    // vor (kein lang/de, kein laravel-lang-Paket) - ohne die eigenen
    // ->validationMessages() auf dem Feld waeren diese beiden Meldungen
    // englisch, obwohl der Rest der Oberflaeche deutsch ist.
    it('rejects a disallowed file type with a german message', function () {
        Storage::fake('local');

        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();

        $file = UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload');

        $component = livewire(VehicleDocumentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => EditVehicle::class,
        ])
            ->callTableAction(CreateAction::class, data: [
                'file_path' => $file,
                'type' => DocumentType::Other->value,
                'title' => 'Unzulaessiger Typ',
            ]);

        // assertHasTableActionErrors() prueft wahlweise auf den Regelnamen
        // oder die Meldung - da die Meldung durch validationMessages() unten
        // vollstaendig ersetzt ist ("mimetypes" kommt darin nicht mehr vor),
        // wird hier direkt die tatsaechliche deutsche Meldung im Error Bag
        // geprueft (Feld-Pfad wie im Vendor-Code fuer gemountete
        // Tabellen-Aktionen: mountedActions.{index}.data.{feld}).
        expect($component->instance()->getErrorBag()->first('mountedActions.0.data.file_path'))
            ->toBe('Es sind nur PDF-, JPG- und PNG-Dateien erlaubt.');

        assertDatabaseMissing(VehicleDocument::class, ['vehicle_id' => $vehicle->id]);
    });

    it('rejects a file larger than 10 mb with a german message', function () {
        Storage::fake('local');

        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();

        $file = UploadedFile::fake()->create('gross.pdf', 11 * 1024, 'application/pdf');

        $component = livewire(VehicleDocumentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => EditVehicle::class,
        ])
            ->callTableAction(CreateAction::class, data: [
                'file_path' => $file,
                'type' => DocumentType::Other->value,
                'title' => 'Zu gross',
            ]);

        expect($component->instance()->getErrorBag()->first('mountedActions.0.data.file_path'))
            ->toBe('Die Datei darf höchstens 10 MB groß sein.');

        assertDatabaseMissing(VehicleDocument::class, ['vehicle_id' => $vehicle->id]);
    });
});

it('keeps the download action visible to a read-only user', function () {
    // Lesen/Herunterladen bleibt jedem angemeldeten Benutzer erlaubt -
    // anders als Create/Edit/Delete ist "download" eine generische Action
    // ohne eigene Policy-Bindung und muss daher auch fuer einen Viewer
    // sichtbar bleiben.
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $vehicle = Vehicle::factory()->create();
    $document = VehicleDocument::factory()->for($vehicle)->create();

    livewire(VehicleDocumentsRelationManager::class, [
        'ownerRecord' => $vehicle,
        'pageClass' => EditVehicle::class,
    ])
        ->assertTableActionVisible('download', $document)
        ->assertTableActionHasUrl('download', route('vehicle-documents.download', $document), $document);
});

describe('write access is restricted to admins', function () {
    it('hides the write actions from a viewer', function () {
        actingAs(User::factory()->viewer()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();
        $document = VehicleDocument::factory()->for($vehicle)->create();

        livewire(VehicleDocumentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => EditVehicle::class,
        ])
            ->assertTableActionHidden(CreateAction::class)
            ->assertTableActionHidden(EditAction::class, $document)
            ->assertTableActionHidden(DeleteAction::class, $document);
    });

    // Wie im Vorbild VehicleAppointmentsRelationManagerTest: nicht nur eine
    // versteckte Schaltflaeche, sondern die tatsaechliche Autorisierung der
    // Aktion wird direkt geprueft (mountTableAction() ohne den
    // Sichtbarkeits-Vorabcheck von callTableAction()).
    it('refuses a direct upload attempt by a viewer, not just a hidden button', function () {
        Storage::fake('local');

        actingAs(User::factory()->viewer()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();

        $component = livewire(VehicleDocumentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => EditVehicle::class,
        ])->mountTableAction(CreateAction::class);

        expect($component->instance()->mountedActions)->toBeEmpty();

        assertDatabaseMissing(VehicleDocument::class, ['vehicle_id' => $vehicle->id]);
    });

    it('refuses a direct edit attempt by a viewer, not just a hidden button', function () {
        $vehicle = Vehicle::factory()->create();
        $document = VehicleDocument::factory()->for($vehicle)->create(['title' => 'original']);

        actingAs(User::factory()->viewer()->withTwoFactor()->create());

        $component = livewire(VehicleDocumentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => EditVehicle::class,
        ])->mountTableAction(EditAction::class, $document);

        expect($component->instance()->mountedActions)->toBeEmpty();
        expect($document->refresh()->title)->toBe('original');
    });

    it('refuses a direct delete attempt by a viewer, not just a hidden button', function () {
        $vehicle = Vehicle::factory()->create();
        $document = VehicleDocument::factory()->for($vehicle)->create();

        actingAs(User::factory()->viewer()->withTwoFactor()->create());

        $component = livewire(VehicleDocumentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => EditVehicle::class,
        ])->mountTableAction(DeleteAction::class, $document);

        expect($component->instance()->mountedActions)->toBeEmpty();
        expect(VehicleDocument::query()->find($document->id))->not->toBeNull();
    });
});

it('deletes the stored file when a document is deleted via the relation manager as an admin', function () {
    Storage::fake('local');

    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $vehicle = Vehicle::factory()->create();
    $document = VehicleDocument::factory()->for($vehicle)->create();
    Storage::disk('local')->put($document->file_path, 'dummy content');

    livewire(VehicleDocumentsRelationManager::class, [
        'ownerRecord' => $vehicle,
        'pageClass' => EditVehicle::class,
    ])
        ->callTableAction(DeleteAction::class, $document)
        ->assertHasNoTableActionErrors();

    expect(VehicleDocument::count())->toBe(0);
    Storage::disk('local')->assertMissing($document->file_path);
});

describe('on the view page', function () {
    it('shows the documents relation manager read-only', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();
        $document = VehicleDocument::factory()->for($vehicle)->create();

        livewire(ViewVehicle::class, ['record' => $vehicle->id])
            ->assertOk()
            ->assertSee('Dokumente');

        livewire(VehicleDocumentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => ViewVehicle::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords([$document])
            ->assertTableActionHidden(CreateAction::class)
            ->assertTableActionHidden(EditAction::class, $document)
            ->assertTableActionHidden(DeleteAction::class, $document);
    });

    it('lets a read-only user see documents on the view page', function () {
        actingAs(User::factory()->viewer()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();
        $document = VehicleDocument::factory()->for($vehicle)->create();

        livewire(VehicleDocumentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => ViewVehicle::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords([$document]);
    });
});

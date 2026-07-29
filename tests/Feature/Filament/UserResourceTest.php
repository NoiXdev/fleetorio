<?php

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

it('lets an admin list users', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();
    actingAs($admin);

    livewire(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$admin]);
});

it('hides user administration from viewers', function () {
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    expect(UserResource::canViewAny())->toBeFalse();
});

it('creates a user with a hashed password', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Neue Kollegin',
            'email' => 'neu@example.com',
            'password' => 'geheim-genug-123',
            'role' => UserRole::Viewer->value,
            'can_view_costs' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::where('email', 'neu@example.com')->first();

    expect($created)->not->toBeNull()
        ->and($created->password)->not->toBe('geheim-genug-123')
        ->and(Hash::check('geheim-genug-123', $created->password))->toBeTrue()
        ->and($created->can_view_costs)->toBeTrue()
        ->and($created->role)->toBe(UserRole::Viewer);
});

it('requires a password when creating', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Ohne Passwort',
            'email' => 'ohne@example.com',
            'password' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['password' => 'required']);
});

it('keeps the password when editing without entering a new one', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();
    actingAs($admin);

    $target = User::factory()->create(['password' => Hash::make('altes-passwort')]);
    $hashBefore = $target->password;

    livewire(EditUser::class, ['record' => $target->id])
        ->fillForm(['name' => 'Neuer Name', 'password' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    $target->refresh();

    expect($target->name)->toBe('Neuer Name')
        ->and($target->password)->toBe($hashBefore);
});

it('rejects a duplicate email', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());
    User::factory()->create(['email' => 'belegt@example.com']);

    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Doppelt',
            'email' => 'belegt@example.com',
            'password' => 'geheim-genug-123',
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);
});

// Sicherheitspunkt: role und can_view_costs sind absichtlich fillable, damit
// Admins sie hier pflegen koennen. Gefaehrlich waere nur, wenn ein Nutzer ohne
// Adminrechte per direktem HTTP-Zugriff an diese Formulare kaeme - nicht bloss
// die Navigation ausgeblendet waere. Diese Tests belegen den direkten Zugriff,
// nicht nur die Sichtbarkeit.
it('denies a viewer direct http access to the user list', function () {
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $this->get('/admin/users')->assertForbidden();
});

it('denies a viewer direct http access to the create-user page', function () {
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $this->get('/admin/users/create')->assertForbidden();
});

it('denies a viewer direct http access to the edit-user page', function () {
    actingAs(User::factory()->viewer()->withTwoFactor()->create());
    $target = User::factory()->create();

    $this->get("/admin/users/{$target->id}/edit")->assertForbidden();
});

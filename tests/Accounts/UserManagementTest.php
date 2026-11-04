<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->admin = User::factory()->superUser()->create(['name' => 'Admin']);
});

/**
 * A driver account: its user plus the driver record.
 */
function driverAccount(array $attributes = []): User
{
    $user = User::factory()->create(['is_super_user' => false, 'enabled' => true, ...$attributes]);
    Driver::factory()->create(['user_id' => $user->id, 'enabled' => $user->enabled]);

    return $user;
}

/**
 * @return array<string, string>
 */
function newDriverData(array $overrides = []): array
{
    return [
        'name' => 'Juan Pérez',
        'username' => 'juanp',
        'phone_number' => '3874000000',
        'password' => 'secret-pass',
        'password_confirmation' => 'secret-pass',
        ...$overrides,
    ];
}

test('the users page lists only driver accounts, never admins', function (): void {
    $juan = driverAccount(['name' => 'Juan']);
    $off = driverAccount(['name' => 'Ana', 'enabled' => false]);

    $this->actingAs($this->admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Accounts/Users')
            ->has('users', 2)
            ->where('users.0.id', $off->id)
            ->where('users.0.enabled', false)
            ->where('users.1.id', $juan->id)
            ->where('users.1.username', $juan->username)
            ->missing('users.1.email')
            ->missing('users.0.password'));
});

test('the users page filters by name or username and by status', function (): void {
    $juan = driverAccount(['name' => 'Juan Pérez', 'username' => 'jperez']);
    $carlos = driverAccount(['name' => 'Carlos', 'username' => 'cgomez']);
    $off = driverAccount(['name' => 'Ana', 'enabled' => false]);

    $this->actingAs($this->admin)->get(route('users.index', ['search' => 'juan']))
        ->assertInertia(fn (Assert $page) => $page->has('users', 1)->where('users.0.id', $juan->id));
    $this->actingAs($this->admin)->get(route('users.index', ['search' => 'cgom']))
        ->assertInertia(fn (Assert $page) => $page->has('users', 1)->where('users.0.id', $carlos->id));
    $this->actingAs($this->admin)->get(route('users.index', ['status' => 'disabled']))
        ->assertInertia(fn (Assert $page) => $page->has('users', 1)->where('users.0.id', $off->id));
});

test('an admin creates a driver account that can log into the app right away', function (): void {
    $this->actingAs($this->admin)
        ->post(route('users.store'), newDriverData())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user = User::query()->where('username', 'juanp')->sole();
    expect($user->name)->toBe('Juan Pérez')
        ->and($user->email)->toBeNull()
        ->and($user->phone_number)->toBe('387 400-0000')
        ->and($user->is_super_user)->toBeFalse()
        ->and($user->enabled)->toBeTrue()
        ->and(Hash::check('secret-pass', $user->password))->toBeTrue()
        ->and($user->driver->enabled)->toBeTrue();

    $this->postJson('/api/v1/auth/login', ['username' => 'juanp', 'password' => 'secret-pass'])->assertOk();
});

test('a new account needs a unique username and a confirmed password of 8 characters', function (array $overrides, string $field): void {
    driverAccount(['username' => 'taken']);

    $this->actingAs($this->admin)
        ->post(route('users.store'), newDriverData($overrides))
        ->assertSessionHasErrors($field);

    expect(User::query()->where('name', 'Juan Pérez')->exists())->toBeFalse();
})->with([
    'missing name' => [['name' => ''], 'name'],
    'missing username' => [['username' => ''], 'username'],
    'taken username' => [['username' => 'taken'], 'username'],
    'taken username in another letter case' => [['username' => 'TAKEN'], 'username'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'unconfirmed password' => [['password_confirmation' => 'other-pass'], 'password'],
]);

test('validation messages are in Spanish', function (): void {
    driverAccount(['username' => 'taken']);

    $this->actingAs($this->admin)
        ->post(route('users.store'), newDriverData(['username' => 'Taken']))
        ->assertSessionHasErrors(['username' => 'Ya hay una cuenta con ese usuario.']);
    $this->actingAs($this->admin)
        ->post(route('users.store'), newDriverData(['username' => '']))
        ->assertSessionHasErrors(['username' => 'Ingresá el usuario.']);
});

test('a driver account never stores an email, even if one is sent', function (): void {
    $this->actingAs($this->admin)
        ->post(route('users.store'), newDriverData(['email' => 'juan@marilin.com']))
        ->assertSessionHasNoErrors();

    $user = User::query()->where('username', 'juanp')->sole();
    expect($user->email)->toBeNull();

    $this->actingAs($this->admin)
        ->patch(route('users.update', $user), ['name' => 'Juan', 'username' => 'juanp', 'email' => 'juan@marilin.com'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->email)->toBeNull();
});

test('an admin edits the name, username and phone of a driver account', function (): void {
    $user = driverAccount(['email' => null, 'username' => 'old']);
    $password = $user->password;

    $this->actingAs($this->admin)
        ->patch(route('users.update', $user), [
            'name' => 'Nombre nuevo',
            'username' => 'new',
            'phone_number' => '3870000001',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->name)->toBe('Nombre nuevo')
        ->and($user->email)->toBeNull()
        ->and($user->username)->toBe('new')
        ->and($user->phone_number)->toBe('387 000-0001')
        ->and($user->password)->toBe($password);
});

test('editing keeps the account own username valid, in any letter case', function (): void {
    $user = driverAccount(['username' => 'same']);
    driverAccount(['username' => 'other']);

    $this->actingAs($this->admin)
        ->patch(route('users.update', $user), ['name' => 'Otro', 'username' => 'Same', 'phone_number' => null])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Otro')
        ->and($user->fresh()->username)->toBe('Same');

    $this->actingAs($this->admin)
        ->patch(route('users.update', $user), ['name' => 'Otro', 'username' => 'OTHER'])
        ->assertSessionHasErrors(['username' => 'Ya hay una cuenta con ese usuario.']);

    expect($user->fresh()->username)->toBe('Same');
});

test('admin accounts cannot be edited from the users screen', function (): void {
    $other = User::factory()->superUser()->create();

    $this->actingAs($this->admin)
        ->patch(route('users.update', $other), ['name' => 'X', 'username' => 'x'])
        ->assertNotFound();
});

test('only an admin can see or manage users', function (): void {
    $driver = driverAccount();

    $this->actingAs($driver)->get(route('users.index'))->assertForbidden();
    $this->actingAs($driver)->post(route('users.store'), newDriverData())->assertForbidden();
    $this->actingAs($driver)->patch(route('users.update', $driver), ['name' => 'X', 'username' => 'x'])->assertForbidden();

    expect(User::query()->where('username', 'juanp')->exists())->toBeFalse();
});

test('accounts cannot be deleted', function (): void {
    $user = driverAccount();

    $this->actingAs($this->admin)->delete('/users/'.$user->id)->assertStatus(405);

    expect($user->fresh())->not->toBeNull();
});

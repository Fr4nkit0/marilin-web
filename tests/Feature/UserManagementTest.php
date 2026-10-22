<?php

declare(strict_types=1);

use App\Models\Driver;
use App\Models\User;
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
        'email' => 'juan@marilin.com',
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
            ->component('Users/Index')
            ->has('users', 2)
            ->where('users.0.id', $off->id)
            ->where('users.0.enabled', false)
            ->where('users.1.id', $juan->id)
            ->where('users.1.email', $juan->email)
            ->missing('users.0.password'));
});

test('the users page filters by name or email and by status', function (): void {
    $juan = driverAccount(['name' => 'Juan Pérez', 'email' => 'juan@marilin.com']);
    driverAccount(['name' => 'Carlos', 'email' => 'carlos@marilin.com']);
    $off = driverAccount(['name' => 'Ana', 'enabled' => false]);

    $this->actingAs($this->admin)->get(route('users.index', ['search' => 'juan@']))
        ->assertInertia(fn (Assert $page) => $page->has('users', 1)->where('users.0.id', $juan->id));
    $this->actingAs($this->admin)->get(route('users.index', ['status' => 'disabled']))
        ->assertInertia(fn (Assert $page) => $page->has('users', 1)->where('users.0.id', $off->id));
});

test('an admin creates a driver account that can log into the app right away', function (): void {
    $this->actingAs($this->admin)
        ->post(route('users.store'), newDriverData())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'juan@marilin.com')->sole();
    expect($user->name)->toBe('Juan Pérez')
        ->and($user->username)->toBe('juanp')
        ->and($user->phone_number)->toBe('3874000000')
        ->and($user->is_super_user)->toBeFalse()
        ->and($user->enabled)->toBeTrue()
        ->and(Hash::check('secret-pass', $user->password))->toBeTrue()
        ->and($user->driver->enabled)->toBeTrue();

    $this->postJson('/api/v1/auth/login', ['email' => 'juan@marilin.com', 'password' => 'secret-pass'])->assertOk();
});

test('a new account needs a unique email, a unique username and a confirmed password of 8 characters', function (array $overrides, string $field): void {
    driverAccount(['email' => 'taken@marilin.com', 'username' => 'taken']);

    $this->actingAs($this->admin)
        ->post(route('users.store'), newDriverData($overrides))
        ->assertSessionHasErrors($field);

    expect(User::query()->where('name', 'Juan Pérez')->exists())->toBeFalse();
})->with([
    'missing name' => [['name' => ''], 'name'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'taken email' => [['email' => 'taken@marilin.com'], 'email'],
    'taken username' => [['username' => 'taken'], 'username'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'unconfirmed password' => [['password_confirmation' => 'other-pass'], 'password'],
]);

test('validation messages are in Spanish', function (): void {
    driverAccount(['email' => 'taken@marilin.com']);

    $this->actingAs($this->admin)
        ->post(route('users.store'), newDriverData(['email' => 'taken@marilin.com']))
        ->assertSessionHasErrors(['email' => 'Ya hay una cuenta con ese email.']);
});

test('an admin edits the name, email, username and phone of a driver account', function (): void {
    $user = driverAccount(['email' => 'old@marilin.com', 'username' => 'old']);
    $password = $user->password;

    $this->actingAs($this->admin)
        ->patch(route('users.update', $user), [
            'name' => 'Nombre nuevo',
            'email' => 'new@marilin.com',
            'username' => 'new',
            'phone_number' => '3870000001',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->name)->toBe('Nombre nuevo')
        ->and($user->email)->toBe('new@marilin.com')
        ->and($user->username)->toBe('new')
        ->and($user->phone_number)->toBe('3870000001')
        ->and($user->password)->toBe($password);
});

test('editing keeps the account own email and username valid', function (): void {
    $user = driverAccount(['email' => 'same@marilin.com', 'username' => 'same']);

    $this->actingAs($this->admin)
        ->patch(route('users.update', $user), ['name' => 'Otro', 'email' => 'same@marilin.com', 'username' => 'same', 'phone_number' => null])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Otro');
});

test('admin accounts cannot be edited from the users screen', function (): void {
    $other = User::factory()->superUser()->create();

    $this->actingAs($this->admin)
        ->patch(route('users.update', $other), ['name' => 'X', 'email' => 'x@marilin.com'])
        ->assertNotFound();
});

test('only an admin can see or manage users', function (): void {
    $driver = driverAccount();

    $this->actingAs($driver)->get(route('users.index'))->assertForbidden();
    $this->actingAs($driver)->post(route('users.store'), newDriverData())->assertForbidden();
    $this->actingAs($driver)->patch(route('users.update', $driver), ['name' => 'X', 'email' => 'x@marilin.com'])->assertForbidden();

    expect(User::query()->where('email', 'juan@marilin.com')->exists())->toBeFalse();
});

test('accounts cannot be deleted', function (): void {
    $user = driverAccount();

    $this->actingAs($this->admin)->delete('/users/'.$user->id)->assertStatus(405);

    expect($user->fresh())->not->toBeNull();
});

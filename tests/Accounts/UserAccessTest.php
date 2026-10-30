<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->admin = User::factory()->superUser()->create();
    $this->user = User::factory()->create(['email' => 'juan@marilin.com', 'password' => 'old-password', 'is_super_user' => false, 'enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
});

/**
 * Logs the driver into the app and returns the status code.
 */
function driverAppLogin(string $password): int
{
    return test()->postJson('/api/v1/auth/login', ['email' => 'juan@marilin.com', 'password' => $password])->status();
}

/**
 * An open web session of the driver, as the database session driver stores it.
 */
function openWebSession(User $user): void
{
    DB::table('sessions')->insert([
        'id' => 'session-'.$user->id,
        'user_id' => $user->id,
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);
}

test('an admin changes the password of a driver and ends its app sessions', function (): void {
    $this->user->createToken('phone');

    $this->actingAs($this->admin)
        ->patch(route('users.password', $this->user), ['password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Hash::check('new-password', $this->user->fresh()->password))->toBeTrue()
        ->and($this->user->tokens()->count())->toBe(0)
        ->and(driverAppLogin('old-password'))->toBe(422)
        ->and(driverAppLogin('new-password'))->toBe(200);
});

test('a new password needs 8 characters and the confirmation', function (array $data): void {
    $this->actingAs($this->admin)
        ->patch(route('users.password', $this->user), $data)
        ->assertSessionHasErrors('password');

    expect(Hash::check('old-password', $this->user->fresh()->password))->toBeTrue();
})->with([
    'short' => [['password' => 'short', 'password_confirmation' => 'short']],
    'unconfirmed' => [['password' => 'new-password', 'password_confirmation' => 'other']],
]);

test('disabling an account blocks the user and the driver and ends every session', function (): void {
    $this->user->createToken('phone');
    openWebSession($this->user);

    $this->actingAs($this->admin)
        ->patch(route('users.status', $this->user), ['enabled' => false])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->user->fresh()->enabled)->toBeFalse()
        ->and($this->driver->fresh()->enabled)->toBeFalse()
        ->and($this->user->tokens()->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $this->user->id)->exists())->toBeFalse()
        ->and(driverAppLogin('old-password'))->toBe(422);
});

test('enabling an account turns the user and the driver back on', function (): void {
    $this->user->update(['enabled' => false]);
    $this->driver->update(['enabled' => false]);

    $this->actingAs($this->admin)
        ->patch(route('users.status', $this->user), ['enabled' => true])
        ->assertSessionHasNoErrors();

    expect($this->user->fresh()->enabled)->toBeTrue()
        ->and($this->driver->fresh()->enabled)->toBeTrue()
        ->and(driverAppLogin('old-password'))->toBe(200);
});

test('a disabled user cannot log into the web', function (): void {
    $this->user->update(['enabled' => false]);

    $this->post('/login', ['email' => 'juan@marilin.com', 'password' => 'old-password'])
        ->assertSessionHasErrors(['email' => 'Tu cuenta está deshabilitada.']);

    $this->assertGuest();
});

test('an enabled user still logs into the web', function (): void {
    $this->post('/login', ['email' => 'juan@marilin.com', 'password' => 'old-password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($this->user);
});

test('a user disabled while logged in is logged out on the next request', function (): void {
    $this->actingAs($this->user);
    $this->user->update(['enabled' => false]);

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Tu cuenta está deshabilitada.']);

    $this->assertGuest();
});

test('a disabled user whose next request is an Inertia form send is redirected with 303', function (): void {
    $this->actingAs($this->user);
    $this->user->update(['enabled' => false]);

    $this->patch(route('profile.update'), ['name' => 'X', 'email' => 'juan@marilin.com'], ['X-Inertia' => 'true'])
        ->assertStatus(303)
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect($this->user->fresh()->name)->not->toBe('X');
});

test('admin accounts cannot be disabled or have their password changed from the users screen', function (): void {
    $other = User::factory()->superUser()->create();

    $this->actingAs($this->admin)->patch(route('users.status', $other), ['enabled' => false])->assertNotFound();
    $this->actingAs($this->admin)->patch(route('users.password', $other), ['password' => 'new-password', 'password_confirmation' => 'new-password'])->assertNotFound();

    expect($other->fresh()->enabled)->toBeTrue();
});

test('only an admin can change passwords or switch accounts on and off', function (): void {
    $this->actingAs($this->user)->patch(route('users.status', $this->user), ['enabled' => false])->assertForbidden();
    $this->actingAs($this->user)->patch(route('users.password', $this->user), ['password' => 'new-password', 'password_confirmation' => 'new-password'])->assertForbidden();

    expect($this->user->fresh()->enabled)->toBeTrue();
});

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateDriverAccountAction;
use App\Actions\SetDriverAccountEnabledAction;
use App\Http\Requests\DriverPasswordRequest;
use App\Http\Requests\StoreDriverAccountRequest;
use App\Http\Requests\UpdateDriverAccountRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Driver accounts managed by the admin. Admin accounts are not listed or touched here.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $users = User::query()
            ->where('is_super_user', false)
            ->whereHas('driver')
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")
                ->orWhereLike('username', "%{$search}%")))
            ->when($status === 'enabled', fn ($query) => $query->where('enabled', true))
            ->when($status === 'disabled', fn ($query) => $query->where('enabled', false))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'username', 'phone_number', 'enabled', 'created_at'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'phone_number' => $user->phone_number,
                'enabled' => $user->enabled,
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function store(StoreDriverAccountRequest $request, CreateDriverAccountAction $create): RedirectResponse
    {
        $create->execute($request->validated());

        return back()->with('success', 'Repartidor creado');
    }

    public function update(UpdateDriverAccountRequest $request, User $user): RedirectResponse
    {
        $this->ensureDriverAccount($user);

        $user->update($request->validated());

        return back()->with('success', 'Datos actualizados');
    }

    /**
     * A new password also revokes the app tokens, so sessions opened with the old one end.
     */
    public function password(DriverPasswordRequest $request, User $user): RedirectResponse
    {
        $this->ensureDriverAccount($user);

        DB::transaction(function () use ($request, $user): void {
            $user->update(['password' => $request->validated('password')]);
            $user->tokens()->delete();
        });

        return back()->with('success', 'Contraseña actualizada');
    }

    public function status(Request $request, User $user, SetDriverAccountEnabledAction $setEnabled): RedirectResponse
    {
        $this->ensureDriverAccount($user);

        $enabled = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];
        $setEnabled->execute($user, (bool) $enabled);

        return back()->with('success', $enabled ? 'Cuenta habilitada' : 'Cuenta deshabilitada');
    }

    /**
     * Admin accounts are out of reach of this screen.
     */
    private function ensureDriverAccount(User $user): void
    {
        abort_if($user->is_super_user || $user->driver === null, 404);
    }
}

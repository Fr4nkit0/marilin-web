<?php

namespace App\Http\Middleware;

use App\Dashboard\DriverSync;
use App\Review\ReviewInbox;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // The bell in the header, for whoever sees the review menu; a closure so only Inertia page responses pay its queries.
            ...($request->user()?->can('review-inbox') ? [
                'notifications' => fn (): array => [
                    'unsyncedDrivers' => array_map(
                        fn (array $driver): array => ['id' => $driver['id'], 'name' => $driver['name'], 'lastSyncAt' => $driver['lastSyncAt']],
                        app(DriverSync::class)->stale(),
                    ),
                    'pendingReview' => app(ReviewInbox::class)->pendingCount(),
                ],
            ] : []),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Sync\Http\Controllers;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Http\Controllers\Controller;
use App\Sync\BootstrapService;
use App\Sync\Http\Requests\PullRequest;
use App\Sync\Http\Requests\PushRequest;
use App\Sync\PullService;
use App\Sync\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    private function resolveDriver(Request $request): Driver
    {
        /** @var User $user */
        $user = $request->user();
        $driver = $user->driver;

        if (! $driver || ! $driver->enabled) {
            abort(403, 'Authenticated user is not an active driver.');
        }

        return $driver;
    }

    public function bootstrap(Request $request, BootstrapService $bootstrap): JsonResponse
    {
        $driver = $this->resolveDriver($request);
        $data = $bootstrap->build($driver);
        $driver->markSynced();

        return response()->json([
            'data' => $data,
            'message' => null,
        ]);
    }

    public function pull(PullRequest $request, PullService $pull): JsonResponse
    {
        $driver = $this->resolveDriver($request);
        $result = $pull->page($driver, $request->cursor(), $request->until(), $request->page());
        $driver->markSynced();

        return response()->json([
            'data' => $result['data'],
            'meta' => $result['meta'],
            'message' => null,
        ]);
    }

    public function push(PushRequest $request, PushService $push): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $driver = $this->resolveDriver($request);
        $data = $push->push($driver, $user, $request->records());
        $driver->markSynced();

        return response()->json([
            'data' => $data,
            'message' => null,
        ]);
    }
}

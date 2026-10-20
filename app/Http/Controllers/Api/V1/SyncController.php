<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PullRequest;
use App\Http\Requests\Api\V1\PushRequest;
use App\Models\Driver;
use App\Models\User;
use App\Services\Sync\BootstrapService;
use App\Services\Sync\PullService;
use App\Services\Sync\PushService;
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
        return response()->json([
            'data' => $bootstrap->build($this->resolveDriver($request)),
            'message' => null,
        ]);
    }

    public function pull(PullRequest $request, PullService $pull): JsonResponse
    {
        $result = $pull->page(
            $this->resolveDriver($request),
            $request->cursor(),
            $request->until(),
            $request->page(),
        );

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

        return response()->json([
            'data' => $push->push($this->resolveDriver($request), $user, $request->records()),
            'message' => null,
        ]);
    }
}

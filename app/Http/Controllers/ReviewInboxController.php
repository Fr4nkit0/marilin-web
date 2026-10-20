<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReviewInboxRequest;
use App\Models\User;
use App\Services\Review\ReviewInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewInboxController extends Controller
{
    public function markReviewed(Request $request, ReviewInbox $inbox, string $type, int $id): RedirectResponse
    {
        /** @var User $reviewer */
        $reviewer = $request->user();

        $inbox->markReviewed($type, $id, $reviewer);

        return back();
    }

    public function index(ReviewInboxRequest $request, ReviewInbox $inbox): Response
    {
        $filters = $request->filters();

        return Inertia::render('Review/Index', [
            'items' => $inbox->page($filters),
            'filters' => $filters,
            'drivers' => $inbox->drivers(),
        ]);
    }
}

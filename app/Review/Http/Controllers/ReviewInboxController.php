<?php

declare(strict_types=1);

namespace App\Review\Http\Controllers;

use App\Accounts\Models\User;
use App\Http\Controllers\Controller;
use App\Review\Http\Requests\CorrectTruckLoadRequest;
use App\Review\Http\Requests\ReviewInboxRequest;
use App\Review\ReviewInbox;
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

    public function correctTruckLoad(CorrectTruckLoadRequest $request, ReviewInbox $inbox, int $id): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $inbox->correctTruckLoad($id, $request->quantities(), $request->string('reason')->toString(), $admin);

        return back()->with('success', 'Carga corregida y venta revisada');
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

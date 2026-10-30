<?php

declare(strict_types=1);

namespace App\Review;

use App\Accounts\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * A record the app uploaded or the system flagged that the admin must look at (ADR 0001): accepted and marked, never rejected.
 *
 * The columns stay on each table (needs_review, review_reason, reviewed_at, reviewed_by); the model only declares its review source.
 */
trait Reviewable
{
    /**
     * Where the model keeps its occurrence date and how to reach its driver; the review inbox builds its query from this.
     */
    abstract public static function reviewSource(): ReviewSource;

    public function initializeReviewable(): void
    {
        $this->mergeCasts(['needs_review' => 'boolean', 'reviewed_at' => 'datetime']);
    }

    /**
     * Flagged and not reviewed yet.
     *
     * @param  Builder<static>  $query
     */
    public function scopePendingReview(Builder $query): void
    {
        $query->where($this->qualifyColumn('needs_review'), true)->whereNull($this->qualifyColumn('reviewed_at'));
    }

    /**
     * Neither flagged nor reviewed: the only records a nightly flag may mark.
     *
     * @param  Builder<static>  $query
     */
    public function scopeUnflagged(Builder $query): void
    {
        $query->where($this->qualifyColumn('needs_review'), false)->whereNull($this->qualifyColumn('reviewed_at'));
    }

    public function isPendingReview(): bool
    {
        return (bool) $this->needs_review && $this->reviewed_at === null;
    }

    /**
     * The reason the record is waiting for review, or null when it is not (or the admin already reviewed it).
     */
    public function pendingReviewReason(): ?string
    {
        return $this->isPendingReview() ? $this->review_reason : null;
    }

    public function canBeFlagged(): bool
    {
        return ! $this->needs_review && $this->reviewed_at === null;
    }

    /**
     * Marks the record for review. A record already flagged keeps its first reason and one the admin reviewed is never flagged again.
     */
    public function flag(ReviewReason $reason): void
    {
        if (! $this->canBeFlagged()) {
            return;
        }

        $this->forceFill(ReviewReason::columns($reason))->save();
    }

    /**
     * A new event on the record (a driver's cancellation) always reaches the inbox: its reason replaces the earlier one and an earlier review is cleared.
     */
    public function reopenReview(ReviewReason $reason): void
    {
        $this->forceFill([...ReviewReason::columns($reason), 'reviewed_at' => null, 'reviewed_by' => null])->save();
    }

    /**
     * The admin looked at the record: who and when are saved, nothing else about the record changes.
     *
     * @throws ModelNotFoundException when the record is not waiting for review (a reviewed one keeps its first reviewer)
     */
    public function markReviewed(User $reviewer): void
    {
        if (! $this->isPendingReview()) {
            throw (new ModelNotFoundException)->setModel(static::class, [$this->getKey()]);
        }

        $this->forceFill(['reviewed_at' => now(), 'reviewed_by' => $reviewer->id])->save();
    }
}

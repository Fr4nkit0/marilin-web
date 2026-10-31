<?php

declare(strict_types=1);

namespace App\Review;

use App\Accounts\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * A record the app uploaded or the system flagged that the admin must look at (ADR 0001): accepted and marked, never rejected.
 *
 * The columns stay on each table (needs_review, review_reasons, reviewed_at, reviewed_by); the model only declares its review source.
 * A record keeps every reason it was flagged for while it waits, so the admin sees them all.
 */
trait Reviewable
{
    /**
     * Where the model keeps its occurrence date and how to reach its driver; the review inbox builds its query from this.
     */
    abstract public static function reviewSource(): ReviewSource;

    public function initializeReviewable(): void
    {
        $this->mergeCasts(['needs_review' => 'boolean', 'review_reasons' => 'array', 'reviewed_at' => 'datetime']);
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
     * The reasons the record is waiting for review, empty when it is not (or the admin already reviewed it).
     *
     * @return list<string>
     */
    public function pendingReviewReasons(): array
    {
        return $this->isPendingReview() ? $this->reviewReasons() : [];
    }

    /**
     * Only a record the admin already reviewed can no longer be flagged; a flagged one takes more reasons.
     */
    public function canBeFlagged(): bool
    {
        return $this->reviewed_at === null;
    }

    /**
     * Marks the record for review, adding the reason to the ones it already waits for (never twice).
     * One the admin reviewed is never flagged again.
     */
    public function flag(ReviewReason $reason): void
    {
        if (! $this->canBeFlagged() || ($this->needs_review && in_array($reason->value, $this->reviewReasons(), true))) {
            return;
        }

        $earlier = $this->needs_review ? $this->reviewReasons() : [];

        $this->forceFill(['needs_review' => true, 'review_reasons' => $this->withReason($earlier, $reason)])->save();
    }

    /**
     * A new event on the record (a driver's cancellation) always reaches the inbox: its reason joins the ones still waiting,
     * or starts a new list when the admin had already reviewed the earlier ones, and an earlier review is cleared.
     */
    public function reopenReview(ReviewReason $reason): void
    {
        $earlier = $this->isPendingReview() ? $this->reviewReasons() : [];

        $this->forceFill(['needs_review' => true, 'review_reasons' => $this->withReason($earlier, $reason), 'reviewed_at' => null, 'reviewed_by' => null])->save();
    }

    /**
     * @return list<string>
     */
    private function reviewReasons(): array
    {
        return array_values((array) ($this->review_reasons ?? []));
    }

    /**
     * @param  list<string>  $earlier
     * @return list<string>
     */
    private function withReason(array $earlier, ReviewReason $reason): array
    {
        return array_values(array_unique([...$earlier, $reason->value]));
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

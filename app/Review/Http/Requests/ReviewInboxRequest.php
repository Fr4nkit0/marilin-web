<?php

declare(strict_types=1);

namespace App\Review\Http\Requests;

use App\Review\ReviewInbox;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewInboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', Rule::in(ReviewInbox::TYPES)],
            'status' => ['nullable', Rule::in(['pending', 'reviewed', 'all'])],
        ];
    }

    /**
     * @return array{driver_id: int|null, from: string|null, to: string|null, type: string|null, status: string}
     */
    public function filters(): array
    {
        return [
            'driver_id' => $this->filled('driver_id') ? $this->integer('driver_id') : null,
            'from' => $this->input('from') ?: null,
            'to' => $this->input('to') ?: null,
            'type' => $this->input('type') ?: null,
            'status' => $this->input('status') ?: 'pending',
        ];
    }
}

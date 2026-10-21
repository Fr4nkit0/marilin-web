<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Support\ClientTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class PullRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'date'],
            'until' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function cursor(): ?Carbon
    {
        return $this->filled('cursor') ? ClientTime::parse($this->string('cursor')->toString()) : null;
    }

    /**
     * Upper bound frozen by the first page so later pages see the same snapshot, never ahead of the server clock.
     */
    public function until(): Carbon
    {
        $now = now()->startOfSecond();

        if (! $this->filled('until')) {
            return $now;
        }

        return ClientTime::parse($this->string('until')->toString())->min($now);
    }

    public function page(): int
    {
        return max(1, $this->integer('page', 1));
    }
}

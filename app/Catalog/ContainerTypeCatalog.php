<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Catalog\Models\ContainerType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The admin's changes to the container type catalog. The code identifies the type across the app and the reports,
 * so it is generated once from the description and never changes. Nothing is deleted: a disabled type keeps
 * reaching the app, so the app hides it.
 */
final class ContainerTypeCatalog
{
    private const int CODE_LENGTH = 50;

    public function add(string $description): ContainerType
    {
        return DB::transaction(fn (): ContainerType => ContainerType::query()->create([
            'code' => $this->freeCode($description),
            'description' => $description,
            'enabled' => true,
        ]));
    }

    public function describe(ContainerType $type, string $description): void
    {
        $type->update(['description' => $description]);
    }

    public function setEnabled(ContainerType $type, bool $enabled): void
    {
        $type->update(['enabled' => $enabled]);
    }

    /**
     * The description in uppercase snake case without accents ("Bidón 12L" is BIDON_12L); when another type has it,
     * the first free numeric suffix (BIDON_12L_2), always within the column's 50 characters.
     */
    private function freeCode(string $description): string
    {
        $base = trim((string) preg_replace('/[^A-Z0-9]+/', '_', Str::upper(Str::ascii($description))), '_');
        $base = $base === '' ? 'CONTAINER' : $base;

        for ($attempt = 1; ; $attempt++) {
            $suffix = $attempt === 1 ? '' : '_'.$attempt;
            $code = rtrim(substr($base, 0, self::CODE_LENGTH - strlen($suffix)), '_').$suffix;

            if (! ContainerType::query()->where('code', $code)->exists()) {
                return $code;
            }
        }
    }
}

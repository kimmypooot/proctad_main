<?php

namespace App\Casts;

use App\Enums\ExamRole;
use App\Support\DesignationValue;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a stored designation key to a DesignationValue.
 *
 * Replaces the previous `ExamRole::class` cast, which threw on hydration the
 * moment a record carried a designation that was not one of the seventeen
 * built-in cases — the reason custom designations were impossible before.
 *
 * @implements CastsAttributes<DesignationValue|null, DesignationValue|ExamRole|string|null>
 */
class AsDesignation implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?DesignationValue
    {
        return $value === null ? null : new DesignationValue((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof DesignationValue => $value->value,
            $value instanceof ExamRole => $value->value,
            default => (string) $value,
        };
    }
}

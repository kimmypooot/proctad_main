<?php

namespace App\Models;

use App\Enums\ExamRole;
use App\Enums\PayeeType;
use App\Enums\PersonnelType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

#[Fillable(['payee_type', 'payee_value', 'amount_cents', 'updated_by'])]
class FeeSchedule extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'payee_type' => PayeeType::class,
            'amount_cents' => 'integer',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function amount(): float
    {
        return $this->amount_cents / 100;
    }

    public static function rateForRole(ExamRole $role): ?self
    {
        return static::where('payee_type', PayeeType::ExamRole)
            ->where('payee_value', $role->value)
            ->first();
    }

    public static function rateForPersonnelType(PersonnelType $type): ?self
    {
        return static::where('payee_type', PayeeType::PersonnelType)
            ->where('payee_value', $type->value)
            ->first();
    }

    /**
     * @return Collection<string,int> keyed "exam_role:xxx" / "personnel_type:xxx" => amount_cents
     */
    public static function allRatesIndexed(): Collection
    {
        return static::all()
            ->keyBy(fn (self $rate) => "{$rate->payee_type->value}:{$rate->payee_value}")
            ->map(fn (self $rate) => $rate->amount_cents);
    }
}

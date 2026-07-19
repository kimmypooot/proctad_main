<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['field_office_id', 'name', 'position', 'signature_path', 'active'])]
class Signatory extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function fieldOffice(): BelongsTo
    {
        return $this->belongsTo(FieldOffice::class);
    }

    /**
     * The signatory currently in effect for a field office:
     * an active FO-specific entry wins, else the active region-wide default.
     */
    public static function currentFor(?int $fieldOfficeId): ?self
    {
        if ($fieldOfficeId !== null) {
            $specific = self::where('active', true)
                ->where('field_office_id', $fieldOfficeId)
                ->latest('id')
                ->first();

            if ($specific) {
                return $specific;
            }
        }

        return self::where('active', true)
            ->whereNull('field_office_id')
            ->latest('id')
            ->first();
    }
}

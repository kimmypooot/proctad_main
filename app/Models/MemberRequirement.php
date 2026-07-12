<?php

namespace App\Models;

use App\Enums\EligibilityRequirement;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['requirement', 'complied', 'file_path', 'remarks'])]
class MemberRequirement extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'requirement' => EligibilityRequirement::class,
            'complied' => 'boolean',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A committee or grouping that designations are filed under — the Regional and
 * Local Examination Committees, Special and School-level roles, and the two
 * personnel groupings.
 *
 * Built-in categories may be renamed and reordered but not deleted: ExamRole's
 * coverage rule is decided by which committee a designation sits in, so
 * removing one would leave its designations with no answer.
 */
#[Fillable(['section', 'key', 'label', 'sort_order'])]
class DesignationCategory extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'is_builtin' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function designations(): HasMany
    {
        return $this->hasMany(Designation::class);
    }
}

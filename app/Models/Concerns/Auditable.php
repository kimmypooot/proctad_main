<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes an audit_logs row for every create / update / delete of the model,
 * per the spec's "audit logs must track all edits" rule.
 */
trait Auditable
{
    private const AUDIT_HIDDEN = ['password', 'remember_token', 'created_at', 'updated_at'];

    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->recordAudit('created', [
            'new' => $model->auditAttributes($model->getAttributes()),
        ]));

        static::updated(function (Model $model) {
            $new = $model->auditAttributes($model->getChanges());

            if ($new === []) {
                return;
            }

            $old = array_intersect_key($model->auditAttributes($model->getOriginal()), $new);
            $model->recordAudit('updated', ['old' => $old, 'new' => $new]);
        });

        static::deleted(fn (Model $model) => $model->recordAudit('deleted', null));
    }

    protected function recordAudit(string $action, ?array $changes): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'field_office_id' => $this->getAttribute('field_office_id'),
            'changes' => $changes,
        ]);
    }

    protected function auditAttributes(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(self::AUDIT_HIDDEN));
    }
}

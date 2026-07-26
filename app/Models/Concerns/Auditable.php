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
    /**
     * Dropped from the audit row entirely. Credentials, and anything that can
     * be replayed as one: ScannerSession::$token is the sole credential for a
     * public scanner link, so writing it here would turn read access to
     * audit_logs into a working link into a live examination venue.
     */
    private const AUDIT_HIDDEN = [
        'password', 'remember_token', 'created_at', 'updated_at',
        'token', 'google_id',
    ];

    /**
     * Recorded as having changed, but with the value masked. These are PII
     * under RA 10173 — date_of_birth is deliberately encrypted at rest on the
     * Member model, and copying its plaintext into the audit trail would undo
     * that. The audit still answers "who changed what, and when", which is what
     * the spec asks of it; it no longer doubles as a second copy of the record.
     */
    private const AUDIT_MASKED = ['date_of_birth', 'mobile_number', 'email'];

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
            // auth()->id() falls back to the raw, unvalidated session value
            // when auth()->user() resolves to null — a stale session
            // referencing a since-deleted user would otherwise violate this
            // column's FK constraint and crash the entire request. user()
            // performs the real DB-backed lookup, so it's null exactly when
            // there's truly no valid actor.
            'user_id' => auth()->user()?->id,
            'action' => $action,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'field_office_id' => $this->getAttribute('field_office_id'),
            'changes' => $changes,
        ]);
    }

    protected function auditAttributes(array $attributes): array
    {
        $visible = array_diff_key($attributes, array_flip(self::AUDIT_HIDDEN));

        foreach (self::AUDIT_MASKED as $key) {
            if (array_key_exists($key, $visible)) {
                $visible[$key] = '[redacted]';
            }
        }

        return $visible;
    }
}

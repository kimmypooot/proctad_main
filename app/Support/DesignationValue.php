<?php

namespace App\Support;

use App\Enums\ExamRole;
use App\Enums\ExamRoleGroup;
use App\Enums\PayeeType;
use App\Enums\UserRole;
use JsonSerializable;
use Stringable;

/**
 * The designation stored on an assignment, as read back from the database.
 *
 * Designations used to be an enum, and this stands in its place so that the
 * dozens of call sites reading `$assignment->role->value` or
 * `->label()` keep working now that a designation can be a custom row rather
 * than an ExamRole case.
 *
 * Where a value corresponds to a built-in it simply delegates to the enum, so
 * the structural rules — coverage, evaluability, alternate cover, the ex officio
 * committee seats — behave exactly as before. A custom designation answers
 * "no" to all of them: those rules are about specific duties the system models,
 * and a designation the system has never heard of cannot be one of them.
 */
final class DesignationValue implements JsonSerializable, Stringable
{
    public function __construct(public readonly string $value) {}

    /** The built-in case this value refers to, or null when it is a custom one. */
    public function builtin(): ?ExamRole
    {
        return ExamRole::tryFrom($this->value);
    }

    public function isCustom(): bool
    {
        return $this->builtin() === null;
    }

    public function label(): string
    {
        return DesignationRegistry::label(PayeeType::ExamRole, $this->value);
    }

    /**
     * Strict comparison replacement. `$assignment->role === ExamRole::Proctor`
     * cannot work once the attribute is an object, so comparisons go through
     * here instead.
     */
    public function is(ExamRole|DesignationValue|string $other): bool
    {
        return $this->value === match (true) {
            $other instanceof ExamRole => $other->value,
            $other instanceof self => $other->value,
            default => $other,
        };
    }

    /** @param  array<int, ExamRole|string>  $others */
    public function isAnyOf(array $others): bool
    {
        foreach ($others as $other) {
            if ($this->is($other)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The built-in committee enum, or null for a custom designation. Prefer
     * categoryKey()/categoryLabel(), which answer for both — this exists for the
     * structural rules that are defined in terms of the enum.
     */
    public function group(): ?ExamRoleGroup
    {
        return $this->builtin()?->group();
    }

    /** The committee this designation is filed under, built-in or not. */
    public function categoryKey(): string
    {
        return DesignationRegistry::categoryKey(PayeeType::ExamRole, $this->value);
    }

    public function categoryLabel(): string
    {
        return DesignationRegistry::categoryLabel(PayeeType::ExamRole, $this->value);
    }

    /**
     * Derived from the committee rather than the enum case, so re-filing a
     * designation into the REC or an LEC makes it a coverage duty exactly as a
     * built-in one is — which is the point of being able to move them.
     */
    public function isCoverageRole(): bool
    {
        return match ($this->categoryKey()) {
            ExamRoleGroup::Regional->value, ExamRoleGroup::TestingCenter->value => true,
            ExamRoleGroup::Special->value => $this->is(ExamRole::CeForInvestigation),
            default => false,
        };
    }

    public function isEvaluable(): bool
    {
        return $this->builtin()?->isEvaluable() ?? false;
    }

    public function isCoverable(): bool
    {
        return $this->builtin()?->isCoverable() ?? false;
    }

    public function reservedForRole(): ?UserRole
    {
        return $this->builtin()?->reservedForRole();
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /** Serialises as the bare value, so Inertia payloads are unchanged. */
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}

<?php

namespace App\Support;

use App\Enums\PayeeType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-side cache over the designations and designation_categories tables.
 *
 * Labels are resolved on nearly every payroll line, assignment row and service
 * record, so the whole set is held in one cached map rather than queried per
 * lookup. Writes go through the controller, which flushes.
 *
 * A designation that no longer exists in the table still resolves to something
 * readable — its own key — so a historical assignment referring to a deleted
 * custom designation renders as text rather than blowing up. Deletion is
 * blocked while any assignment references it, so this is a backstop, not a
 * routine path.
 */
class DesignationRegistry
{
    private const CACHE_KEY = 'designations_map';

    public static function label(PayeeType $type, string $value): string
    {
        return self::map()["{$type->value}|{$value}"]['label'] ?? $value;
    }

    public static function isActive(PayeeType $type, string $value): bool
    {
        return self::map()["{$type->value}|{$value}"]['is_active'] ?? false;
    }

    public static function categoryKey(PayeeType $type, string $value): string
    {
        return self::map()["{$type->value}|{$value}"]['category_key'] ?? 'uncategorised';
    }

    public static function categoryLabel(PayeeType $type, string $value): string
    {
        return self::map()["{$type->value}|{$value}"]['category_label'] ?? 'Uncategorised';
    }

    public static function exists(PayeeType $type, string $value): bool
    {
        return isset(self::map()["{$type->value}|{$value}"]);
    }

    /**
     * Every designation in a section, in committee then display order.
     *
     * @return list<array<string, mixed>>
     */
    public static function forSection(PayeeType $type, bool $activeOnly = true): array
    {
        return collect(self::map())
            ->filter(fn (array $row) => $row['section'] === $type->value && (! $activeOnly || $row['is_active']))
            ->sortBy([['category_sort', 'asc'], ['sort_order', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Active designation keys for a section, in display order.
     *
     * @return list<string>
     */
    public static function activeKeys(PayeeType $type): array
    {
        return collect(self::map())
            ->filter(fn (array $row) => $row['section'] === $type->value && $row['is_active'])
            ->sortBy([['category_sort', 'asc'], ['sort_order', 'asc']])
            ->pluck('key')
            ->values()
            ->all();
    }

    /**
     * Active designations for a section as value/label pairs grouped by
     * committee — the shape assignment dropdowns and optgroups need.
     *
     * @return list<array{name: string, options: list<array{value: string, label: string}>}>
     */
    public static function activeGrouped(PayeeType $type): array
    {
        return collect(self::map())
            ->filter(fn (array $row) => $row['section'] === $type->value && $row['is_active'])
            ->sortBy([['category_sort', 'asc'], ['sort_order', 'asc']])
            ->groupBy('category_label')
            ->map(fn ($rows, $category) => [
                'name' => $category,
                'options' => $rows->map(fn (array $row) => [
                    'value' => $row['key'],
                    'label' => $row['label'],
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Active designations that take part in per-room staffing, in display
     * order. This is what builds the room grid's columns — a custom designation
     * given a rooms_per_slot appears there alongside the built-in three.
     *
     * @return list<array{key: string, label: string, rooms_per_slot: int, is_anchored: bool}>
     */
    public static function roomDesignations(): array
    {
        return collect(self::forSection(PayeeType::ExamRole))
            ->filter(fn (array $row) => ($row['rooms_per_slot'] ?? 0) >= 1)
            ->map(fn (array $row) => [
                'key' => $row['key'],
                'label' => $row['label'],
                'rooms_per_slot' => (int) $row['rooms_per_slot'],
                // Covering several rooms means one assignment anchored at the
                // group's first room, so only that row is editable.
                'is_anchored' => (int) $row['rooms_per_slot'] > 1,
            ])
            ->values()
            ->all();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Guarded against missing tables: labels are read by console commands that
     * may run before migrations on a fresh clone.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function map(): array
    {
        if (! Schema::hasTable('designations')) {
            return [];
        }

        return Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('designations as d')
            ->join('designation_categories as c', 'c.id', '=', 'd.designation_category_id')
            ->orderBy('c.sort_order')
            ->orderBy('d.sort_order')
            ->get([
                'd.section', 'd.key', 'd.label', 'd.is_active', 'd.sort_order', 'd.rooms_per_slot',
                'c.label as category_label', 'c.key as category_key', 'c.sort_order as category_sort',
            ])
            ->mapWithKeys(fn ($row) => ["{$row->section}|{$row->key}" => [
                'section' => $row->section,
                'key' => $row->key,
                'label' => $row->label,
                'is_active' => (bool) $row->is_active,
                'sort_order' => (int) $row->sort_order,
                'rooms_per_slot' => $row->rooms_per_slot === null ? null : (int) $row->rooms_per_slot,
                'category_key' => $row->category_key,
                'category_label' => $row->category_label,
                'category_sort' => (int) $row->category_sort,
            ]])
            ->all());
    }
}

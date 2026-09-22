<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Shared list pagination helpers (Leads-style: selectable per_page + allowed sizes).
 */
final class ListPagination
{
    /**
     * @param  list<int>  $allowed
     */
    public static function perPage(
        Request $request,
        int $default = 20,
        array $allowed = [10, 20, 50],
    ): int {
        $perPage = (int) $request->input('per_page', $default);

        if (! in_array($perPage, $allowed, true)) {
            return $default;
        }

        return $perPage;
    }

    /**
     * Inertia-friendly paginator payload used by customer and admin lists.
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param  LengthAwarePaginator<TKey, TValue>  $paginator
     * @return array{data: list<TValue>, meta: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}}
     */
    public static function inertia(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => array_values($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * Validate `sort` + `direction` query params (Leads-style).
     *
     * Also accepts legacy compound keys such as `name_asc` / `updated_desc`
     * when `direction` is omitted, so existing bookmarks keep working.
     *
     * @param  list<string>  $allowed
     * @return array{column: string, direction: 'asc'|'desc'}
     */
    public static function sort(
        Request $request,
        array $allowed,
        string $defaultColumn,
        string $defaultDirection = 'desc',
    ): array {
        $rawSort = trim((string) $request->input('sort', ''));
        $rawDirection = $request->input('direction');
        $hasDirection = $rawDirection !== null && $rawDirection !== '';

        if (! $hasDirection && preg_match('/^(.*)_(asc|desc)$/', $rawSort, $matches) === 1) {
            $legacyColumn = $matches[1];
            if (in_array($legacyColumn, $allowed, true)) {
                return [
                    'column' => $legacyColumn,
                    'direction' => $matches[2] === 'asc' ? 'asc' : 'desc',
                ];
            }
        }

        $column = in_array($rawSort, $allowed, true)
            ? $rawSort
            : (in_array($defaultColumn, $allowed, true)
                ? $defaultColumn
                : (string) ($allowed[0] ?? $defaultColumn));

        $direction = match (true) {
            $rawDirection === 'asc' => 'asc',
            $rawDirection === 'desc' => 'desc',
            default => $defaultDirection === 'asc' ? 'asc' : 'desc',
        };

        return [
            'column' => $column,
            'direction' => $direction,
        ];
    }
}

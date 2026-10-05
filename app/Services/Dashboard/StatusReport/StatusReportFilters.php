<?php

namespace App\Services\Dashboard\StatusReport;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Resolves and normalizes status-report request filters.
 *
 * Supported inputs:
 * - from / to: date range on COALESCE(claims.intimation_date, claims.accident_date) (default: current calendar year)
 * - entity: role | user (default: role)
 * - entity_ids:
 *     - entity=role → channel names: internal | external | legal (default: [])
 *     - entity=user → numeric user IDs (default: [])
 * - status: subset of cleansed | collect | outstanding (default: all)
 *
 * Invalid values fall back safely (entity → role, bad dates → year defaults,
 * unknown statuses/channels dropped, collect aliased to collected).
 */
class StatusReportFilters
{
    private const ALLOWED_ENTITIES = ['role', 'user'];

    private const ALLOWED_STATUSES = ['cleansed', 'collected', 'outstanding'];

    private const ALLOWED_CHANNELS = ['internal', 'external', 'legal'];

    private const STATUS_ALIASES = [
        'collect' => 'collected',
    ];

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     entity: string,
     *     entity_ids: list<int|string>,
     *     statuses: list<string>
     * }
     */
    public function resolve(Request $request): array
    {
        $entity = $this->resolveEntity($request);

        return [
            'from' => $this->resolveFrom($request),
            'to' => $this->resolveTo($request),
            'entity' => $entity,
            'entity_ids' => $this->resolveEntityIds($request, $entity),
            'statuses' => $this->resolveStatuses($request),
        ];
    }

    private function resolveFrom(Request $request): string
    {
        $from = $request->input('from');

        if ($this->isValidDate($from)) {
            return Carbon::parse($from)->toDateString();
        }

        return Carbon::now()->startOfYear()->toDateString();
    }

    private function resolveTo(Request $request): string
    {
        $to = $request->input('to');

        if ($this->isValidDate($to)) {
            return Carbon::parse($to)->toDateString();
        }

        return Carbon::now()->endOfYear()->toDateString();
    }

    private function resolveEntity(Request $request): string
    {
        $entity = $request->input('entity', 'role');

        if (! is_string($entity) || ! in_array($entity, self::ALLOWED_ENTITIES, true)) {
            return 'role';
        }

        return $entity;
    }

    /**
     * @return list<int>|list<string>
     */
    private function resolveEntityIds(Request $request, string $entity): array
    {
        $ids = $request->input('entity_ids', []);

        if (! is_array($ids)) {
            return [];
        }

        if ($entity === 'role') {
            return $this->resolveChannelNames($ids);
        }

        return array_values(array_map('intval', array_filter($ids, fn ($id) => is_numeric($id))));
    }

    /**
     * @return list<string>
     */
    private function resolveChannelNames(array $ids): array
    {
        $channels = [];

        foreach ($ids as $id) {
            if (! is_string($id)) {
                continue;
            }

            $channel = strtolower(trim($id));

            if (in_array($channel, self::ALLOWED_CHANNELS, true)) {
                $channels[] = $channel;
            }
        }

        return array_values(array_unique($channels));
    }

    /**
     * @return list<string>
     */
    private function resolveStatuses(Request $request): array
    {
        $statuses = $request->input('status', []);

        if (! is_array($statuses)) {
            return [];
        }

        $normalized = [];

        foreach ($statuses as $status) {
            if (! is_string($status)) {
                continue;
            }

            $key = self::STATUS_ALIASES[$status] ?? $status;

            if (in_array($key, self::ALLOWED_STATUSES, true)) {
                $normalized[] = $key;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function isValidDate(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        try {
            Carbon::parse($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}

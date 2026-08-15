<?php

declare(strict_types=1);

namespace App\Services\Dictionary;

/**
 * Search and flatten the eHealth services dictionary tree for referral pickers.
 */
final class ServiceSearch
{
    /**
     * Codes in NK 025 look like 37003-00. Digit-only queries need that suffix
     * because GET /api/services?code= is an exact match, not a prefix.
     *
     * @param  callable(array{page: int, page_size: int, code?: string, name?: string}): list<array<string, mixed>>  $fetch
     * @return list<array<string, mixed>>
     */
    public static function search(string $query, callable $fetch, int $page = 1, int $pageSize = 15): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $base = [
            'page' => $page,
            'page_size' => $pageSize,
        ];

        if (self::isCodeQuery($query)) {
            foreach (self::codeCandidates($query) as $code) {
                $results = self::filterByCodeNeedle(
                    self::flattenRequestable($fetch($base + ['code' => $code])),
                    $query
                );
                if ($results !== []) {
                    return $results;
                }
            }
        }

        return self::flattenRequestable($fetch($base + ['name' => $query]));
    }

    /**
     * Service-request category from an eHealth catalog node (same codes as
     * eHealth/SNOMED/service_request_categories).
     *
     * @param  array<string, mixed>  $service
     */
    public static function requestCategory(array $service): ?string
    {
        $category = $service['category'] ?? null;
        if (!is_string($category) || trim($category) === '') {
            return null;
        }

        return strtolower(trim($category));
    }

    public static function isCodeQuery(string $query): bool
    {
        return (bool) preg_match('/^[\p{L}0-9\-\.]+$/u', $query)
            && (bool) preg_match('/[0-9]/', $query)
            && !str_contains($query, ' ');
    }

    /**
     * @return list<string>
     */
    public static function codeCandidates(string $query): array
    {
        $candidates = [$query];
        if (preg_match('/^\d+$/', $query) === 1) {
            $candidates[] = $query.'-00';
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    public static function flattenRequestable(array $nodes): array
    {
        $services = [];
        self::collectRequestable($nodes, $services);

        return array_values($services);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<string, array<string, mixed>>  $services
     */
    private static function collectRequestable(array $nodes, array &$services): void
    {
        foreach ($nodes as $node) {
            if (!is_array($node) || empty($node['id'])) {
                continue;
            }

            $isInactive = isset($node['is_active']) && $node['is_active'] === false;
            $hasCode = !empty($node['code']);
            $isContainer = !empty($node['groups']) || !empty($node['services']);
            $requestAllowed = (bool) ($node['request_allowed'] ?? false);

            if (!$isInactive && $hasCode && ($requestAllowed || !$isContainer)) {
                $services[$node['id']] = $node;
            }

            if (!empty($node['services']) && is_array($node['services'])) {
                foreach ($node['services'] as $service) {
                    if (!is_array($service) || empty($service['id'])) {
                        continue;
                    }
                    if (isset($service['is_active']) && $service['is_active'] === false) {
                        continue;
                    }
                    $services[$service['id']] = $service;
                }
            }

            if (!empty($node['groups']) && is_array($node['groups'])) {
                self::collectRequestable($node['groups'], $services);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $services
     * @return list<array<string, mixed>>
     */
    private static function filterByCodeNeedle(array $services, string $needle): array
    {
        return array_values(array_filter(
            $services,
            static fn (array $service): bool => mb_stripos((string) ($service['code'] ?? ''), $needle) !== false
        ));
    }
}

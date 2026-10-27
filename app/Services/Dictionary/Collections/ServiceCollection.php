<?php

declare(strict_types=1);

namespace App\Services\Dictionary\Collections;

use Illuminate\Support\Collection;

class ServiceCollection extends Collection
{
    protected static ?self $flattenedCache = null;

    /** @var Collection<string, string|null>|null */
    protected static ?Collection $categoriesCache = null;

    /** @var Collection<string, array<string, mixed>>|null */
    protected static ?Collection $byIdCache = null;

    /**
     * Get flattened service structure with in-memory memoization.
     *
     * Converts hierarchical service structure into a flat collection,
     * processing nested groups and services while filtering out inactive
     * elements and removing duplicates by service ID.
     *
     * @return self Flattened collection of unique active services
     */
    public function flattened(): self
    {
        if (static::$flattenedCache !== null) {
            return static::$flattenedCache;
        }

        $items = $this->flatMap(function (array $item) {
            return $this->flattenServiceItem($item);
        });
        $seenIds = [];

        return static::$flattenedCache = $items->filter(static function (array $item) use (&$seenIds): bool {
            $id = serialize($item['id']);
            if (isset($seenIds[$id])) {
                return false;
            }

            $seenIds[$id] = true;

            return true;
        });
    }

    /**
     * Get a cached map of [service_id => category].
     *
     * @return Collection<string, string|null>
     */
    public function categories(): Collection
    {
        return static::$categoriesCache ??= $this->flattened()->pluck('category', 'id');
    }

    /**
     * Fast O(1) service lookup by ID.
     *
     * @param  string|null  $id
     * @return array<string, mixed>|null
     */
    public function findById(?string $id): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }

        static::$byIdCache ??= $this->flattened()->keyBy('id');

        return static::$byIdCache->get($id);
    }

    /**
     * Flush memoized caches (useful for testing or after refreshing dictionary cache).
     */
    public static function flushFlattenedCache(): void
    {
        static::$flattenedCache = null;
        static::$categoriesCache = null;
        static::$byIdCache = null;
    }

    /**
     * Recursively flatten the structure of a service element.
     *
     * Processes nested service groups and services recursively,
     * filtering out inactive elements and normalizing the structure.
     * Handles both 'groups' and 'services' nested properties.
     *
     * @param  array  $item  Service item to flatten
     * @return Collection Collection of flattened service items
     */
    protected function flattenServiceItem(array $item): Collection
    {
        // Remove inactive elements
        if (isset($item['is_active']) && $item['is_active'] === false) {
            return collect();
        }

        $result = collect([
            [
                'code' => $item['code'],
                'name' => $item['name'],
                'id' => $item['id'],
                'category' => $item['category'] ?? null
            ]
        ]);

        // Process groups if they exist
        if (isset($item['groups'])) {
            $groupItems = collect($item['groups'])
                ->flatMap(fn (array $group) => $this->flattenServiceItem($group));
            $result = $result->merge($groupItems);
        }

        // Process services if they exist
        if (isset($item['services'])) {
            $serviceItems = collect($item['services'])
                ->flatMap(fn (array $service) => $this->flattenServiceItem($service));
            $result = $result->merge($serviceItems);
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Dictionary;

use App\Services\Dictionary\ServiceSearch;
use Tests\TestCase;

class ServiceSearchTest extends TestCase
{
    public function test_digit_only_query_retries_nk025_suffix_and_keeps_leaf_without_request_allowed(): void
    {
        $calls = [];
        $fetch = static function (array $params) use (&$calls): array {
            $calls[] = $params['code'] ?? null;
            if (($params['code'] ?? null) === '37003-00') {
                return [[
                    'id' => 'svc-1',
                    'code' => '37003-00',
                    'name' => 'Обстеження',
                    'is_active' => true,
                ]];
            }

            return [];
        };

        $results = ServiceSearch::search('37003', $fetch);

        $this->assertSame(['37003', '37003-00'], $calls);
        $this->assertCount(1, $results);
        $this->assertSame('37003-00', $results[0]['code']);
    }

    public function test_flatten_keeps_nested_services_and_skips_inactive_leaves(): void
    {
        $flat = ServiceSearch::flattenRequestable([
            [
                'id' => 'group-1',
                'code' => '37000',
                'name' => 'Група',
                'groups' => [],
                'services' => [
                    [
                        'id' => 'svc-active',
                        'code' => '37003-00',
                        'name' => 'Активна',
                        'is_active' => true,
                    ],
                    [
                        'id' => 'svc-inactive',
                        'code' => '37004-00',
                        'name' => 'Неактивна',
                        'is_active' => false,
                    ],
                ],
            ],
        ]);

        $this->assertSame(['svc-active'], array_column($flat, 'id'));
    }

    public function test_name_query_does_not_send_code(): void
    {
        $paramsSeen = [];
        $fetch = static function (array $params) use (&$paramsSeen): array {
            $paramsSeen = $params;

            return [];
        };

        ServiceSearch::search('обстеж', $fetch);

        $this->assertArrayHasKey('name', $paramsSeen);
        $this->assertArrayNotHasKey('code', $paramsSeen);
        $this->assertSame('обстеж', $paramsSeen['name']);
    }

    public function test_request_category_normalizes_catalog_value(): void
    {
        $this->assertSame(
            'diagnostic_procedure',
            ServiceSearch::requestCategory(['category' => 'diagnostic_procedure'])
        );
        $this->assertSame(
            'laboratory_procedure',
            ServiceSearch::requestCategory(['category' => 'Laboratory_Procedure'])
        );
        $this->assertNull(ServiceSearch::requestCategory(['name' => 'Без категорії']));
    }
}

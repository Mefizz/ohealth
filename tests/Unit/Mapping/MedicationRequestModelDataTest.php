<?php

declare(strict_types=1);

namespace Tests\Unit\Mapping;

use App\Dto\MedicationRequest\ModelData;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Tests\TestCase;

class MedicationRequestModelDataTest extends TestCase
{
    public static function metadata(): iterable
    {
        yield 'partial does not clear' => [['status' => 'ACTIVE', 'medication_qty' => null], ['status' => 'ACTIVE']];
        yield 'zero and empty are supplied values' => [['medication_qty' => 0, 'request_number' => ''], ['request_number' => '', 'medication_qty' => 0]];
        yield 'null primary falls back to aliases' => [[
            'request_number' => null, 'requisition' => 'RX-1', 'medication_id' => null,
            'medication_info' => ['id' => 'medication-id'], 'medical_program' => ['id' => 'program-id'],
        ], ['request_number' => 'RX-1', 'medication_id' => 'medication-id', 'medication_program_id' => 'program-id']];
        yield 'primary wins and unrelated data is excluded' => [[
            'medication_id' => 'primary', 'medication_info' => ['id' => 'alias'],
            'medical_program_id' => 'program', 'medical_program' => ['id' => 'alias-program'],
            'employee_id' => 'foreign-author', 'context' => ['identifier' => ['value' => 'foreign-context']],
            'dosage_instruction' => [], 'signed_content' => 'raw',
        ], ['medication_id' => 'primary', 'medication_program_id' => 'program']];
    }

    #[DataProvider('metadata')]
    public function test_partial_metadata_matches_the_existing_cache_contract_without_queries(array $input, array $expected): void
    {
        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->assertSame($expected, app(ObjectMapperInterface::class)->map((object) $input, ModelData::class)->toSyncPatch());
        $this->assertSame([], $queries);
    }
}

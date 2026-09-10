<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MedicalEvents;

use App\Services\MedicalEvents\Icd10AmSpecialityConditionGate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Icd10AmSpecialityConditionGateTest extends TestCase
{
    public function test_unrestricted_icd10_code_is_allowed_for_endocrinology(): void
    {
        $this->assertTrue(
            Icd10AmSpecialityConditionGate::isCodeAllowedForOfficioSpecialities('E10.9', ['ENDOCRINOLOGY'])
        );
        $this->assertTrue(
            Icd10AmSpecialityConditionGate::isCodeAllowedForOfficioSpecialities('E10.02', ['ENDOCRINOLOGY'])
        );
    }

    public function test_unrestricted_icd10_code_is_allowed_when_asserter_has_no_officio_speciality(): void
    {
        $this->assertTrue(
            Icd10AmSpecialityConditionGate::isCodeAllowedForOfficioSpecialities('E10.9', [])
        );
    }

    public function test_psychiatry_reserved_code_requires_psychiatry_speciality(): void
    {
        $psychiatryCodes = config('ehealth.icd10am_speciality_conditions_allowed.PSYCHIATRY', []);
        $this->assertNotEmpty($psychiatryCodes);

        $reservedCode = $psychiatryCodes[0];

        $this->assertFalse(
            Icd10AmSpecialityConditionGate::isCodeAllowedForOfficioSpecialities($reservedCode, ['ENDOCRINOLOGY'])
        );
        $this->assertTrue(
            Icd10AmSpecialityConditionGate::isCodeAllowedForOfficioSpecialities($reservedCode, ['PSYCHIATRY'])
        );
    }

    public function test_narcology_reserved_code_allows_narcology_speciality(): void
    {
        $narcologyCodes = config('ehealth.icd10am_speciality_conditions_allowed.NARCOLOGY', []);
        $this->assertNotEmpty($narcologyCodes);

        $reservedCode = $narcologyCodes[0];

        $this->assertTrue(
            Icd10AmSpecialityConditionGate::isCodeAllowedForOfficioSpecialities($reservedCode, ['NARCOLOGY'])
        );
        $this->assertFalse(
            Icd10AmSpecialityConditionGate::isCodeAllowedForOfficioSpecialities($reservedCode, ['ENDOCRINOLOGY'])
        );
    }

    #[DataProvider('specialitiesAllowedForCodeProvider')]
    public function test_specialities_allowed_for_code(string $code, array $expectedSpecialities): void
    {
        $this->assertSame(
            $expectedSpecialities,
            Icd10AmSpecialityConditionGate::specialitiesAllowedForCode($code)
        );
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function specialitiesAllowedForCodeProvider(): array
    {
        return [
            'diabetes is unrestricted' => ['E10.9', []],
            'psychiatry F-code lists psychiatry charts' => ['F20.0', ['PSYCHIATRY', 'PEDIATRIC_PSYCHIATRY']],
        ];
    }
}

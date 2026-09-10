<?php

declare(strict_types=1);

namespace App\Services\MedicalEvents;

/**
 * eHealth Submit Encounter Package — specialty gate for ICD-10-AM conditions.
 *
 * Chart variables ICD10_AM_<SPECIALITY_TYPE>_SPECIALITY_CONDITIONS_ALLOWED reserve
 * certain codes for listed specialities. Codes absent from every chart are unrestricted.
 *
 * @see https://e-health-ua.atlassian.net/wiki/spaces/EH/pages/16816013426/Submit+Encounter+Data+Package+CR-207
 */
final class Icd10AmSpecialityConditionGate
{
    /**
     * Specialities whose SPECIALITY_CONDITIONS_ALLOWED chart contains the code.
     *
     * @return list<string>
     */
    public static function specialitiesAllowedForCode(string $code): array
    {
        $allowed = [];

        foreach (config('ehealth.icd10am_speciality_conditions_allowed', []) as $speciality => $codes) {
            if (is_array($codes) && in_array($code, $codes, true)) {
                $allowed[] = (string) $speciality;
            }
        }

        return $allowed;
    }

    /**
     * Whether an asserter with the given officio specialities may set the ICD-10-AM code.
     *
     * @param  iterable<int, string|null>  $officioSpecialities
     */
    public static function isCodeAllowedForOfficioSpecialities(string $code, iterable $officioSpecialities): bool
    {
        $allowedSpecialities = self::specialitiesAllowedForCode($code);

        // Code is not reserved for any specialty → any qualifying asserter may set it.
        if ($allowedSpecialities === []) {
            return true;
        }

        foreach ($officioSpecialities as $speciality) {
            if ($speciality !== null && $speciality !== '' && in_array((string) $speciality, $allowedSpecialities, true)) {
                return true;
            }
        }

        return false;
    }
}

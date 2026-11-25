<?php

declare(strict_types=1);

namespace Tests\Unit\CarePlan;

use Tests\TestCase;

class EHealthWriteCallSitesTest extends TestCase
{
    public function test_care_plan_show_owns_its_activity_form(): void
    {
        $show = file_get_contents(app_path('Livewire/CarePlan/CarePlanShow.php'));

        $this->assertIsString($show);
        $this->assertStringContainsString('function saveActivity', $show);
        $this->assertStringContainsString('function initActivityForm', $show);
    }

    public function test_device_writes_keep_the_existing_domain_boundary(): void
    {
        $writePattern = '/EHealth::(?:deviceRequest)\(\)->(?:create|createSigned|cancel|complete|recall)\s*\(/';

        foreach ($this->clinicalLivewirePaths() as $path) {
            $source = file_get_contents($path);
            $this->assertIsString($source);
            $this->assertDoesNotMatchRegularExpression(
                $writePattern,
                $source,
                basename($path).' still posts a clinical write through EHealth::'
            );
        }
    }

    /**
     * @return list<string>
     */
    private function clinicalLivewirePaths(): array
    {
        return [
            app_path('Livewire/CarePlan/CarePlanCreate.php'),
            app_path('Livewire/CarePlan/CarePlanUpdate.php'),
            app_path('Livewire/CarePlan/CarePlanShow.php'),
            app_path('Livewire/CarePlan/CarePlanComponent.php'),
            app_path('Livewire/CarePlan/CarePlanApprovals.php'),
            app_path('Livewire/Person/Records/PatientReferrals.php'),
            app_path('Livewire/EncounterPackage/Concerns/ManagesEncounterReferrals.php'),
        ];
    }
}

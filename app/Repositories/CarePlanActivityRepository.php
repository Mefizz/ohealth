<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\CarePlanActivity;

class CarePlanActivityRepository
{
    public function getByCarePlanId(int $carePlanId)
    {
        return CarePlanActivity::where('care_plan_id', $carePlanId)->get();
    }

    public function create(array $data): CarePlanActivity
    {
        return CarePlanActivity::create($data);
    }

    public function update(CarePlanActivity $activity, array $data): bool
    {
        return $activity->update($data);
    public function updateById(int $id, array $data): bool
    {
        $activity = CarePlanActivity::find($id);
        if (!$activity) return false;
        return $activity->update($data);
    }

    public function formatCarePlanActivityRequest(CarePlanActivity $activity): array
    {
        return \App\Core\Arr::removeEmptyKeys([
            'detail' => \App\Core\Arr::removeEmptyKeys([
                'kind' => $activity->kind,
                'description' => $activity->description ?: null,
                'product_reference' => $activity->product_reference ? ['identifier' => ['value' => $activity->product_reference]] : null,
                'scheduled_period' => array_filter([
                    'start' => $activity->scheduled_period_start ? convertToYmd($activity->scheduled_period_start->format('d.m.Y')) : null,
                    'end' => $activity->scheduled_period_end ? convertToYmd($activity->scheduled_period_end->format('d.m.Y')) : null,
                ]),
                'quantity' => $activity->quantity ? ['value' => $activity->quantity, 'system' => $activity->quantity_system ?? null, 'code' => $activity->quantity_code ?? null] : null,
                'daily_amount' => $activity->daily_amount ? ['value' => $activity->daily_amount, 'system' => $activity->daily_amount_system ?? null, 'code' => $activity->daily_amount_code ?? null] : null,
                'reason_code' => $activity->reason_code ? [['coding' => [['code' => $activity->reason_code]]]] : null,
                'reason_reference' => !empty($activity->reason_reference) ? array_map(fn($r) => ['identifier' => ['value' => $r]], $activity->reason_reference) : null,
                'goal' => !empty($activity->goal) ? array_map(fn($g) => ['identifier' => ['value' => $g]], $activity->goal) : null,
            ]),
            'program' => $activity->program ? ['identifier' => ['value' => $activity->program]] : null,
        ]);
    }
}

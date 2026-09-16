# TV 3.17 Service Request — implementation prompt v3

Прочитай: `.cursor/tv-prompts/00-base-v3.md`  
**Запускати після W1 (3.10), паралельно з 3.9/3.20.**

## Контекст
- Звіт: `.cursor/tv-compliance/3.17-service-request.md`

## P0
1. Заборона DOCTOR + counselling перед qualify і use (Livewire + controller + encounter redemption).
2. Specimen-context + `service_request:read_impersonal`, без PII пацієнта.
3. Не тримати сирий eHealth response; не рендерити `subject.identifier.value` у `wire:click`.

## P1
Reuse minutes; complete з program + warning `remaining_quantity>0`; qualify без program якщо вимагає контракт; edit draft lifecycle.

## Git
Не push до OK користувача після UAT.

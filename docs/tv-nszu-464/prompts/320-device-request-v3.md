# TV 3.20 Device Request — implementation prompt v3

Прочитай: `.cursor/tv-prompts/00-base-v3.md`  
**Запускати після W1 (3.10), паралельно з 3.9/3.17.**  
**Не чіпати 3.22** — відпуск МВ веде інший розробник.

## Контекст
- Звіт: `.cursor/tv-compliance/3.20-device-request.md`

## P0
1. Signed payload: `inform_with`, reason, parameters — персистенція + mapper.
2. Standalone encounter (сьогодні+self) включно ДЗР без програми.
3. Revoke / Complete / реальні dispenses list (не стаб UI).
4. Active-only + today/self guards серверно.
5. Звести/прибрати legacy `/device-requests` placeholder.

## P1
request_max_period_day; OTP block texts; eHealth search/details; A5 printout; success messages auth×program; participation fail-closed.

## Git
Не push до OK користувача після UAT.

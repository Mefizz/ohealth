# TV 3.8 Compositions — UAT checklist (не агент)

PR: https://github.com/openhealths/nationHealth/pull/645  
Branch: `i644_composition_medical_conclusions`

Ручний прогін (агент не може закрити КЕП):

1. OUTPATIENT + SPECIALIST → створити МВН, підписати КЕП, print form без реклами.
2. PRIMARY_CARE + DOCTOR або OUTPATIENT + SPECIALIST → МВТН.
3. Заборонені комбінації роль×заклад — відмова.
4. Скасування МВТН з причиною + КЕП → статус після DONE = entered-in-error.
5. ERLN ERROR → retry → статус оновлюється після job.
6. Pregnancy — лише дозволені періоди з конфігу.
7. Неідентифікований + SICKNESS — warning про ЕРЛН.

Якщо щось падає — коментар у PR #645 з кроками відтворення.

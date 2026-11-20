# Контракт електронних направлень

Джерело: офіційний OpenAPI Medical Events **preprod 9.15.6**, локальний snapshot від
22.10.2026. Це звірка датованої документації, а не перевірка живого production.
[Офіційна документація](https://docs-preprod.ehealth.gov.ua/).

У таблиці `P` = `/api/patients/{patient_id}`, `S` = `/api/service_requests`.
Patient client — `App\Classes\eHealth\Api\Patient\ServiceRequest`, executor client —
`App\Classes\eHealth\Api\ServiceRequest`. Кожен client повертає EHealthResponse із
налаштованим validator; споживач викликає `validate()` перед використанням даних.

| Method/path | Scope із документації | Client method | Успішна відповідь / validator |
| --- | --- | --- | --- |
| GET P/service_requests | service_request:read | Patient.getBySearchParams | 200 list + paging / validateMany |
| GET P/service_requests/{id} | service_request:read | Patient.getById | 200 detail / validateDetails |
| GET P/episodes/{episode}/service_requests | service_request:read | Patient.getByEpisode | 200 list + paging / validateMany |
| GET P/episodes/{episode}/service_requests/{id} | service_request:read | Patient.getByEpisodeId | 200 detail / validateDetails |
| GET P/compositions/{composition}/service_requests/{id} | Не зазначений у snapshot | Patient.getByComposition | 200 detail / validateDetails |
| GET S | service_request:read | Patient.searchForServiceRequestsByParams; Executor делегує | 200 list + identity / validateMany |
| GET /api/specimens/{specimen}/service_requests | service_request:impersonal_read | Patient.getBySpecimen | 200 list + identity / validateMany |
| POST P/service_requests/prequalify | service_request:write | Patient.prequalify | 200 verdict list + urgent authentication / validatePrequalify |
| POST P/service_requests | service_request:write | Patient.createSigned | 202 pending / validateOperation |
| PATCH P/service_requests/{id}/actions/cancel | service_request:cancel | Patient.cancel | 201 detail + status_reason, 202 pending / validateOperation |
| PATCH P/service_requests/{id}/actions/recall | service_request:recall | Patient.recall | 202 pending / validateOperation |
| PATCH P/service_requests/{id}/actions/resend | service_request:use | Patient.resendSms | 200 empty, 201 detail, 202 pending / validateResend |
| POST S/{id}/actions/qualify | service_request:use | Executor.qualify; Patient делегує | 200 verdict list / validateQualification |
| PATCH S/{id}/actions/use | service_request:use | Executor.process; Patient делегує | 202 pending / validateOperation |
| PATCH S/{id}/actions/process | service_request:makeinprogress | Executor.processLegacy | 202 pending / validateOperation; deprecated endpoint |
| PATCH S/{id}/actions/release | service_request:use | Executor.cancelUsage; Patient делегує | 202 pending / validateOperation; deprecated endpoint, без body |
| PATCH S/{id}/actions/complete | service_request:complete | Executor.complete; Patient делегує | 201 detail, 202 pending / validateOperation |

Сторінки `decoded-payload` документують вміст підпису й не є окремими транспортними
операціями. Create містить поля DTO `ServiceRequest/Ehealth`; cancel/recall підписують
повний свіжий native snapshot із status_reason/explanatory_letter, зберігаючи extensions.
КЕП envelope містить лише signed_data та signed_data_encoding.

Валідатори розрізняють list/detail, додаткову identity, обов'язкові/необов'язкові поля,
Quantity з нульовим value, Reference і history. ЕСОЗ може повернути лише назву послуги у code.display_value. Імпорт зберігає її без підстановки ідентифікатора. Для створення направлення потрібен code.identifier.value. Paging використовує `page`, а не
`page_number`. Відсутність occurrence у відповіді дозволена контрактом; створення
окремо перевіряє дату/період у Form. Некоректний елемент відхиляє всю сторінку.

202 не підтверджує клінічну зміну. Durable submission блокує повторне відправлення;
job link відстежується у наявній черзі. Pending без job link лишає результат
непідтвердженим до синхронізації. Перед фіналізацією embedded job resource проходить
validateJobResult/validateResource; processed job без ресурсу потребує validated GET detail.
SMS оновлює tracking, не клінічний snapshot. Policy повторно перевіряється перед
відправленням під блокуванням запису; зміна запису під час КЕП зупиняє відправлення.

## Інтеграція та розгортання

Завдання ЕСОЗ може завершитися з порожнім вкладеним результатом. У такому разі клієнт
запитує деталі направлення за ідентифікаторами пацієнта й направлення. Якщо деталі
тимчасово недоступні, черга повторює запит. До отримання валідованого ресурсу
локальний клінічний стан зберігається.

Перегляд і редагування чернетки мають окремі маршрути з Policy. Реєстр, імпорт
та підготовка підпису використовують DTO й Symfony ObjectMapper. Адаптер чернеток
зберігає сумісність із формами взаємодії й плану лікування. Їхній спільний код
також обслуговує запити на медичні вироби.

Перед розгортанням застосуйте міграцію `2026_11_17_140000_preserve_service_request_metadata`.
Вона додає `resource_metadata` і зв’язки з ідентифікаторами виконавців, результату
та послуги програми. На робочій базі міграцію не виконували.

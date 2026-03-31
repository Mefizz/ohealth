<?php

declare(strict_types=1);

namespace App\Livewire\Encounter\Forms;

use App\Core\BaseForm;
use App\Rules\Cyrillic;
use App\Rules\InDictionary;
use App\Rules\OnlyOnePrimaryDiagnosis;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Fluent;
use Illuminate\Validation\ConditionalRules;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\ValidationException;

class EncounterForm extends BaseForm
{
    public array $encounter = [
        'status' => 'finished',
        'visit' => [
            'identifier' => [
                'type' => ['coding' => [['system' => 'eHealth/resources', 'code' => 'visit']]]
            ]
        ],
        'episode' => [
            'identifier' => [
                'type' => ['coding' => [['system' => 'eHealth/resources', 'code' => 'episode']]]
            ]
        ],
        'class' => [
            'system' => 'eHealth/encounter_classes'
        ],
        'type' => [
            'coding' => [['system' => 'eHealth/encounter_types']]
        ],
        'performer' => [
            'identifier' => [
                'type' => ['coding' => [['system' => 'eHealth/resources', 'code' => 'employee']]]
            ]
        ],
        'reasons' => [],
        'diagnoses' => [],
        'actions' => []
    ];

    public array $episode = [
        'type' => [
            'system' => 'eHealth/episode_types'
        ],
        'status' => 'active',
        'managingOrganization' => [
            'identifier' => [
                'type' => [
                    'coding' => [['system' => 'eHealth/resources', 'code' => 'legal_entity']]
                ]
            ]
        ],
        'careManager' => [
            'identifier' => [
                'type' => [
                    'coding' => [['system' => 'eHealth/resources', 'code' => 'employee']]
                ]
            ]
        ]
    ];

    public array $conditions;

    public array $immunizations;

    public array $observations;

    public array $diagnosticReports;

    public array $procedures;

    public array $clinicalImpressions;

    protected function rules(): array
    {
        return [
            'encounter.period.date' => ['required', 'date', 'before_or_equal:today'],
            'encounter.period.start' => ['required', 'date_format:H:i'],
            'encounter.period.end' => ['required', 'date_format:H:i', 'after:encounter.period.start'],
            'encounter.class.code' => ['required', 'string', new InDictionary('eHealth/encounter_classes')],
            'encounter.type.coding.*.code' => ['required', 'string', new InDictionary('eHealth/encounter_types')],
            'encounter.priority' => ['required_if:encounter.class.code,INPATIENT', 'array'],
            'encounter.priority.coding.*.code' => [
                'required',
                'string',
                new InDictionary('eHealth/encounter_priority')
            ],
            'encounter.reasons' => ['required_if:encounter.class.code,PHC', 'array'],
            'encounter.reasons.*.coding.*.code' => ['required', 'string', new InDictionary('eHealth/ICPC2/reasons')],
            'encounter.reasons.*.text' => ['nullable', 'string', new Cyrillic()],
            'encounter.diagnoses.*.role.coding.*.code' => [
                'required',
                'string',
                new InDictionary('eHealth/diagnosis_roles')
            ],
            'encounter.diagnoses' => [
                'required_unless:encounter.type.coding.0.code,intervention',
                new OnlyOnePrimaryDiagnosis(),
                'array'
            ],
            'encounter.diagnoses.*.rank' => ['nullable', 'integer', 'min:1', 'max:10'],
            'encounter.actions' => [
                'required_if:encounter.class.code,PHC',
                'prohibited_unless:encounter.class.code,PHC',
                'array'
            ],
            'encounter.actions.*.coding.*.code' => ['required', 'string', new InDictionary('eHealth/ICPC2/actions')],
            'encounter.actions.*.text' => ['nullable', 'string', new Cyrillic()],
            'encounter.division' => [
                Rule::prohibitedIf(in_array(data_get($this->encounter, 'division'), ['field', 'home']))
            ],
            'encounter.division.identifier.value' => ['nullable', 'uuid'],

            'episode.type.code' => ['nullable', 'string', new InDictionary('eHealth/episode_types')],
            'episode.name' => ['nullable', 'string', new Cyrillic()],
            'episode.period.start' => ['nullable', 'date', 'before_or_equal:now'],

            'conditions' => ['nullable', 'array'],
            'conditions.*.primarySource' => ['required_with:conditions', 'boolean'],
            'conditions.*.asserter' => ['required_if:conditions.*.primarySource,true', 'array'],
            'conditions.*.reportOrigin' => ['required_if:conditions.*.primarySource,false', 'array'],
            'conditions.*.reportOrigin.coding.*.code' => ['required_if:conditions.*.primarySource,false', 'string'],
            'conditions.*.code.coding.*.code' => ['required_with:conditions', 'string'],
            'conditions.*.code.coding.*.system' => [
                'required_with:conditions',
                'string',
                'in:eHealth/ICPC2/condition_codes,eHealth/ICD10_AM/condition_codes'
            ],
            'conditions.*.clinicalStatus' => ['required_with:conditions', 'string'],
            'conditions.*.verificationStatus' => ['required_with:conditions', 'string', 'not_in:entered_in_error'],
            'conditions.*.severity.coding.*.code' => [
                'nullable',
                'string',
                new InDictionary('eHealth/condition_severities')
            ],
            'conditions.*.onsetDate' => ['required_with:conditions', 'before:tomorrow', 'date'],
            'conditions.*.assertedDate' => ['nullable', 'before:tomorrow', 'date'],
            'conditions.*.evidences.codes.*.coding.*.code' => [
                'nullable',
                'string',
                new InDictionary('eHealth/ICPC2/reasons')
            ],

            'immunizations' => ['nullable', 'array'],
            'immunizations.*.primarySource' => ['required_with:immunizations', 'boolean'],
            'immunizations.*.performer' => [
                'required_if:immunizations.*.primarySource,true',
                'prohibited_if:immunizations.*.primarySource,false',
                'array'
            ],
            'immunizations.*.reportOrigin' => [
                'required_if:immunizations.*.primarySource,false',
                'prohibited_if:immunizations.*.primarySource,true',
                'array'
            ],
            'immunizations.*.reportOrigin.coding.*.code' => [
                'string',
                new InDictionary('eHealth/immunization_report_origins')
            ],
            'immunizations.*.notGiven' => ['required_with:immunizations', 'boolean'],
            'immunizations.*.vaccineCode.coding.*.code' => [
                'required_with:immunizations',
                'string',
                new InDictionary('eHealth/vaccine_codes')
            ],
            'immunizations.*.date' => ['required_with:immunizations', 'before:tomorrow', 'date'],

            'observations' => ['nullable', 'array'],
            'observations.*.primarySource' => ['required_with:observations', 'boolean'],
            'observations.*.performer' => [
                'required_if:observations.*.primarySource,true',
                'prohibited_if:observations.*.primarySource,false',
                'array'
            ],
            'observations.*.reportOrigin' => [
                'required_if:observations.*.primarySource,false',
                'array'
            ],
            'observations.*.reportOrigin.coding.*.code' => [
                'required_if:observations.*.primarySource,false',
                'prohibited_if:observations.*.primarySource,true',
                'string'
            ],
            'observations.*.categories' => ['required_with:observations', 'array'],
            'observations.*.categories.coding.*.code' => [
                'required',
                'string',
                new InDictionary(['eHealth/observation_categories', 'eHealth/ICF/observation_categories'])
            ],
            'observations.*.code' => ['required_with:observations', 'array'],
            'observations.*.code.coding.*.code' => [
                'required',
                'string',
                new InDictionary(['eHealth/LOINC/observation_codes', 'eHealth/ICF/classifiers'])
            ],
            'observations.*.issuedDate' => ['required_with:observations', 'date', 'before_or_equal:now'],
            'observations.*.issuedTime' => ['required_with:observations', 'date_format:H:i'],
            'observations.*.effectiveDate' => ['nullable', 'date', 'before_or_equal:now'],
            'observations.*.effectiveTime' => ['nullable', 'date_format:H:i'],

            'diagnosticReports' => ['nullable', 'array'],
            'diagnosticReports.*.category.*.coding.*.code' => [
                'required_with:diagnosticReports',
                'string',
                new InDictionary('eHealth/diagnostic_report_categories')
            ],
            'diagnosticReports.*.resultsInterpreter.text' => ['required_with:diagnosticReports', 'string', 'max:255'],
            'diagnosticReports.*.issued' => ['required_with:diagnosticReports', 'date', 'before_or_equal:now'],
            'diagnosticReports.*.effectivePeriod.start' => [
                'required_with:diagnosticReports',
                'date',
                'before_or_equal:now'
            ],
            'diagnosticReports.*.effectivePeriod.end' => [
                'required_with:diagnosticReports',
                'date',
                'after:diagnosticReports.*.effectivePeriod.start'
            ],

            'procedures' => ['nullable', 'array'],
            'procedures.*.code.identifier.value' => ['required_with:procedures', 'uuid', 'max:255'],
            'procedures.*.category.coding.*.code' => [
                'required_with:procedures',
                'string',
                new InDictionary('eHealth/procedure_categories')
            ],
            'procedures.*.performedPeriod.start' => ['required_with:procedures', 'date', 'before_or_equal:now'],
            'procedures.*.performedPeriod.end' => [
                'required_with:procedures',
                'date',
                'before_or_equal:now',
                'after:procedures.*.performedPeriod.start'
            ],

            'clinicalImpressions' => ['nullable', 'array'],
            'clinicalImpressions.*.code.coding.*.code' => [
                'required_with:clinicalImpressions',
                'string',
                'max:255',
                new InDictionary('eHealth/clinical_impression_patient_categories')
            ],
            'clinicalImpressions.*.description' => ['nullable', 'string', 'max:1000'],
            'clinicalImpressions.*.effectivePeriod.start' => [
                'required_with:clinicalImpressions',
                'date',
                'before_or_equal:now'
            ],
            'clinicalImpressions.*.effectivePeriod.end' => [
                'required_with:clinicalImpressions',
                'date',
                'before_or_equal:now',
                'after:clinicalImpressions.*.effectivePeriod.start'
            ]
        ];
    }

    /**
     * Validate form by name.
     *
     * @param  string  $formName
     * @param  array  $formData
     * @return void
     * @throws ValidationException
     */
    public function validateForm(string $formName, array $formData): void
    {
        $rules = $this->rulesForModel($formName)->toArray();

        $this->customizeRulesForModel($formName, $rules);

        Validator::make([$formName => $formData], $rules)->validate();
    }

    /**
     * Add custom rules.
     *
     * @param  string  $formName
     * @param  array  $rules
     * @return void
     */
    protected function customizeRulesForModel(string $formName, array &$rules): void
    {
        if ($formName === 'encounter') {
            $this->addAllowedEncounterClasses($rules);
            $this->addAllowedEncounterTypes($rules);
        }

        if ($formName === 'episode') {
            $this->addAllowedEpisodeCareManagerEmployeeTypes($rules);
        }
    }

    /**
     * Add allowed values for episode type code.
     *
     * @param  array  $rules
     * @return void
     */
    private function addAllowedEpisodeCareManagerEmployeeTypes(array &$rules): void
    {
        $allowedValues = $this->getAllowedValues(
            'ehealth.legal_entity_episode_types',
            'ehealth.employee_episode_types'
        );
        $this->addAllowedRule($rules, 'episode.type.code', $allowedValues);
    }

    /**
     * Add allowed values for encounter classes.
     *
     * @param  array  $rules
     * @return void
     */
    private function addAllowedEncounterClasses(array &$rules): void
    {
        $allowedValues = $this->getAllowedValues(
            'ehealth.legal_entity_encounter_classes',
            'ehealth.employee_encounter_classes'
        );
        $this->addAllowedRule($rules, 'encounter.class.code', $allowedValues);
    }

    /**
     * Add allowed values for encounter types.
     *
     * @param  array  $rules
     * @return void
     */
    private function addAllowedEncounterTypes(array &$rules): void
    {
        $allowedValues = config('ehealth.encounter_class_encounter_types')[key(
            $this->component->dictionaries['eHealth/encounter_classes']
        )];
        $this->addAllowedRule($rules, 'encounter.type.coding.code', $allowedValues);
    }

    /**
     * Get allowed values by config keys.
     *
     * @param  string  $configKey
     * @param  string|null  $additionalConfigKey
     * @return array
     */
    private function getAllowedValues(string $configKey, ?string $additionalConfigKey = null): array
    {
        $allowedValues = config($configKey);

        if ($additionalConfigKey) {
            $additionalValues = config($additionalConfigKey);
            $allowedValues = array_intersect(
                $allowedValues[legalEntity()->type->name],
                $additionalValues[Auth::user()?->getEncounterWriterEmployee()->employeeType]
            );
        }

        return $allowedValues;
    }

    /**
     * Add 'in' rule by key and with allowed values.
     *
     * @param  array  $rules
     * @param  string  $ruleKey
     * @param  array  $allowedValues
     * @return void
     */
    private function addAllowedRule(array &$rules, string $ruleKey, array $allowedValues): void
    {
        $rules[$ruleKey][] = 'in:' . implode(',', $allowedValues);
    }

    /**
     * Add a rule that makes the field required, based on primarySource and notGiven.
     *
     * @param  bool  $primarySource
     * @param  bool  $notGiven
     * @return ConditionalRules
     */
    private function requiredIfPrimarySourceAndNotGiven(bool $primarySource, bool $notGiven): ConditionalRules
    {
        return Rule::when(
            static fn (Fluent $input) => data_get($input, 'immunizations.primarySource') === $primarySource &&
                data_get($input, 'immunizations.notGiven') === $notGiven,
            'required'
        );
    }

    /**
     * Required if vaccinationProtocols.authority.coding.*.code === MoH
     *
     * @return RequiredIf
     */
    private function requiredIfHasMoHAuthority(): RequiredIf
    {
        return Rule::requiredIf(function () {
            return collect($this->immunizations)
                ->flatMap(static fn (array $immunization) => $immunization['vaccinationProtocols'])
                ->flatMap(static fn (array $protocol) => $protocol['authority']['coding'])
                ->contains(static fn (array $coding) => $coding['code'] === 'MoH');
        });
    }
}

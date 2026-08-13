<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'personId' => null,
    'prepersonId' => null,
    'patientFullName',
    'hideNavigation' => false,
    'title' => null,
    'breadcrumbs' => [],
    'activeTab' => null
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'personId' => null,
    'prepersonId' => null,
    'patientFullName',
    'hideNavigation' => false,
    'title' => null,
    'breadcrumbs' => [],
    'activeTab' => null
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    use App\Models\DeclarationRequest;
    use App\Models\MedicalEvents\Sql\Encounter;
    use App\Models\Person\Person;

    $routePrefix = !is_null($prepersonId) ? 'prepersons' : 'persons';
    $routeParamKey = !is_null($prepersonId) ? 'preperson' : 'person';
    $recordId = $prepersonId ?? $personId;
?>

<section>
    <?php if (isset($component)) { $__componentOriginal66cfe0cbbf6c425a3bd889176e755171 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal66cfe0cbbf6c425a3bd889176e755171 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.header-navigation','data' => ['xData' => '{ showFilter: true }','class' => 'breadcrumb-form','breadcrumbs' => $breadcrumbs]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('header-navigation'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['x-data' => '{ showFilter: true }','class' => 'breadcrumb-form','breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($breadcrumbs)]); ?>
         <?php $__env->slot('title', null, []); ?> <?php echo e($title ?? $patientFullName); ?> <?php $__env->endSlot(); ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($headerActions)): ?>
            <?php echo e($headerActions); ?>

        <?php elseif($personId): ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('create', Encounter::class)): ?>
                <a href="<?php echo e(route('encounter.create', [legalEntity(), 'person' => $personId])); ?>"
                   class="flex items-center gap-2 button-primary px-5 py-2 text-sm shadow-sm"
                >
                    <?php
                // Parse arguments from the directive
                $iconArgs = ['plus', 'w-4 h-4'];
                $iconName = trim($iconArgs[0], "'\"");
                $iconClass = $iconArgs[1] ?? '';
                $iconFile = resource_path('icons/' . $iconName . '.svg');
                $svgContent = file_exists($iconFile) ? file_get_contents($iconFile) : '';
                if ($iconClass && $svgContent) {
                    // Inject class attribute into SVG tag
                    $svgContent = preg_replace(
                        '/<svg(.*?)(class=".*?")?(.*?)>/',
                        '<svg$1 class="' . e($iconClass) . '"$3>',
                        $svgContent
                    );
                }
                echo $svgContent;
            ?>
                    <?php echo e(__('patients.starts_interacting')); ?>

                </a>
            <?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

         <?php $__env->slot('description', null, []); ?> 
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($description)): ?>
                <?php echo e($description); ?>

            <?php elseif(isset($this->declarationNumber) && $this->declarationNumber): ?>
                <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm font-semibold rounded-lg mt-1 border border-gray-100 dark:border-gray-700">
                    <?php
                // Parse arguments from the directive
                $iconArgs = ['file-text', 'w-4 h-4 text-gray-400'];
                $iconName = trim($iconArgs[0], "'\"");
                $iconClass = $iconArgs[1] ?? '';
                $iconFile = resource_path('icons/' . $iconName . '.svg');
                $svgContent = file_exists($iconFile) ? file_get_contents($iconFile) : '';
                if ($iconClass && $svgContent) {
                    // Inject class attribute into SVG tag
                    $svgContent = preg_replace(
                        '/<svg(.*?)(class=".*?")?(.*?)>/',
                        '<svg$1 class="' . e($iconClass) . '"$3>',
                        $svgContent
                    );
                }
                echo $svgContent;
            ?>
                    Декларація №<?php echo e($this->declarationNumber); ?>

                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
         <?php $__env->endSlot(); ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$hideNavigation): ?>
             <?php $__env->slot('navigation', null, []); ?> 
                <div class="space-y-1">
                    <div class="summary-nav-row">
                        <a href="<?php echo e(route("$routePrefix.patient-data", [legalEntity(), $routeParamKey => $recordId])); ?>"
                           class="summary-tab <?php echo e(($activeTab === 'patient-data' || request()->routeIs("$routePrefix.patient-data")) ? 'summary-tab-active' : 'summary-tab-inactive'); ?>"
                        >
                            <?php echo e(__('patients.patient_data')); ?>

                        </a>

                        <a href="<?php echo e(route("$routePrefix.verification", [legalEntity(), $routeParamKey => $recordId])); ?>"
                           class="summary-tab <?php echo e(($activeTab === 'verification' || request()->routeIs("$routePrefix.verification")) ? 'summary-tab-active' : 'summary-tab-inactive'); ?>"
                        >
                            <?php echo e(__('patients.verification')); ?>

                        </a>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view', Person::class)): ?>
                            <a href="<?php echo e(route("$routePrefix.summary", [legalEntity(), $routeParamKey => $recordId])); ?>"
                               class="summary-tab <?php echo e(request()->routeIs("$routePrefix.summary") ? 'summary-tab-active' : 'summary-tab-inactive'); ?>"
                            >
                                <?php echo e(__('patients.summary')); ?>

                            </a>
                        <?php endif; ?>

                        <a href="<?php echo e(route("$routePrefix.episodes", [legalEntity(), $routeParamKey => $recordId])); ?>"
                           class="summary-tab <?php echo e(request()->routeIs("$routePrefix.episodes") ? 'summary-tab-active' : 'summary-tab-inactive'); ?>"
                        >
                            <?php echo e(__('episodes.plural')); ?>

                        </a>

                        <a href="<?php echo e(route("$routePrefix.observations", [legalEntity(), $routeParamKey => $recordId])); ?>"
                           class="summary-tab <?php echo e(request()->routeIs("$routePrefix.observations") ? 'summary-tab-active' : 'summary-tab-inactive'); ?>"
                        >
                            <?php echo e(__('patients.observation')); ?>

                        </a>

                        <a href="<?php echo e(route("$routePrefix.immunizations", [legalEntity(), $routeParamKey => $recordId])); ?>"
                           class="summary-tab <?php echo e(request()->routeIs("$routePrefix.immunization") ? 'summary-tab-active' : 'summary-tab-inactive'); ?>"
                        >
                            <?php echo e(__('patients.immunizations')); ?>

                        </a>

                        <a href="<?php echo e(route("$routePrefix.conditions", [legalEntity(), $routeParamKey => $recordId])); ?>"
                           class="summary-tab <?php echo e(request()->routeIs("$routePrefix.condition") ? 'summary-tab-active' : 'summary-tab-inactive'); ?>"
                        >
                            <?php echo e(__('patients.conditions')); ?>

                        </a>

                        <a href="<?php echo e(route("$routePrefix.diagnoses", [legalEntity(), $routeParamKey => $recordId])); ?>"
                           class="summary-tab <?php echo e(request()->routeIs("$routePrefix.diagnoses") ? 'summary-tab-active' : 'summary-tab-inactive'); ?>"
                        >
                            <?php echo e(__('patients.diagnoses')); ?>

                        </a>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($prepersonId): ?>
                            <a href="javascript:void(0)"
                               class="summary-tab summary-tab-inactive cursor-not-allowed opacity-60"
                            >
                                <?php echo e(__('patients.prescriptions')); ?>

                            </a>
                        <?php else: ?>
                            <a href="<?php echo e(route('persons.medication-requests', [legalEntity(), 'person' => $personId])); ?>"
                               class="summary-tab <?php echo e(request()->routeIs('persons.medication-requests') ? 'summary-tab-active' : 'summary-tab-inactive'); ?>"
                            >
                                <?php echo e(__('patients.prescriptions')); ?>

                            </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <a href="<?php echo e(route("$routePrefix.diagnostic-reports", [legalEntity(), $routeParamKey => $recordId])); ?>"
                           class="summary-tab <?php echo e(request()->routeIs("$routePrefix.diagnostic-reports") ? 'summary-tab-active' : 'summary-tab-inactive'); ?>"
                        >
                            <?php echo e(__('patients.diagnostic_reports')); ?>

                        </a>
                    </div>

                    <div class="summary-nav-row">
                        <a href="<?php echo e(route("$routePrefix.clinical-impressions", [legalEntity(), $routeParamKey => $recordId])); ?>"
                           class="summary-tab <?php echo e(request()->routeIs("$routePrefix.clinical-impressions") ? 'summary-tab-active' : 'summary-tab-inactive'); ?>"
                        >
                            <?php echo e(__('patients.clinical_impressions')); ?>

                        </a>

                        <a href="javascript:void(0)"
                           class="summary-tab summary-tab-inactive cursor-not-allowed opacity-60"
                        >
                            <?php echo e(__('patients.medical_reports')); ?>

                        </a>

                        <a href="javascript:void(0)"
                           class="summary-tab summary-tab-inactive cursor-not-allowed opacity-60"
                        >
                            <?php echo e(__('patients.referrals')); ?>

                        </a>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($prepersonId): ?>
                            <a href="javascript:void(0)"
                               class="summary-tab summary-tab-inactive cursor-not-allowed opacity-60"
                            >
                                <?php echo e(__('patients.care_plans')); ?>

                            </a>
                        <?php else: ?>
                            <a href="<?php echo e(route('persons.care-plans', [legalEntity(), 'person' => $personId])); ?>"
                               class="summary-tab <?php echo e(request()->routeIs('persons.care-plans') ? 'summary-tab-active' : 'summary-tab-inactive'); ?>"
                            >
                                <?php echo e(__('patients.care_plans')); ?>

                            </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <a href="<?php echo e(route("$routePrefix.encounters", [legalEntity(), $routeParamKey => $recordId])); ?>"
                           class="summary-tab <?php echo e(request()->routeIs("$routePrefix.encounters") ? 'summary-tab-active' : 'summary-tab-inactive'); ?>"
                        >
                            <?php echo e(__('patients.encounters')); ?>

                        </a>

                        <a href="<?php echo e(route("$routePrefix.procedures", [legalEntity(), $routeParamKey => $recordId])); ?>"
                           class="summary-tab <?php echo e(request()->routeIs("$routePrefix.procedures") ? 'summary-tab-active' : 'summary-tab-inactive'); ?>"
                        >
                            <?php echo e(__('patients.procedures')); ?>

                        </a>

                        <div class="flex-1"></div>
                    </div>
                </div>
             <?php $__env->endSlot(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal66cfe0cbbf6c425a3bd889176e755171)): ?>
<?php $attributes = $__attributesOriginal66cfe0cbbf6c425a3bd889176e755171; ?>
<?php unset($__attributesOriginal66cfe0cbbf6c425a3bd889176e755171); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal66cfe0cbbf6c425a3bd889176e755171)): ?>
<?php $component = $__componentOriginal66cfe0cbbf6c425a3bd889176e755171; ?>
<?php unset($__componentOriginal66cfe0cbbf6c425a3bd889176e755171); ?>
<?php endif; ?>

    <?php echo e($slot); ?>

    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('components.x-message', []);

$__key = time();

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-3015485560-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
</section>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/components/layouts/patient.blade.php ENDPATH**/ ?>
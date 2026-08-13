<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'title' => __('forms.nothing_found'),
    'description' => __('forms.changing_search_parameters'),
    'maxWidth' => 'max-w-2xl',
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
    'title' => __('forms.nothing_found'),
    'description' => __('forms.changing_search_parameters'),
    'maxWidth' => 'max-w-2xl',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<fieldset <?php echo e($attributes->merge(['class' => 'fieldset pl-[3.5px] ml-0 mr-auto w-full !max-w-full'])); ?>>
    <legend class="legend relative -top-5 ml-0">
        <?php
                // Parse arguments from the directive
                $iconArgs = ['nothing-found', 'w-28 h-28'];
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
    </legend>

    <div class="p-4 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-start mb-4 <?php echo e($maxWidth); ?>">
        <div class="flex items-start gap-3">
            <div class="shrink-0 mt-0.5">
                <?php
                // Parse arguments from the directive
                $iconArgs = ['alert-circle', 'w-5 h-5 text-blue-500 dark:text-blue-400 mr-3 mt-1'];
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
            </div>
            <div class="flex-1">
                <p class="font-bold text-blue-800 dark:text-blue-300">
                    <?php echo e($title); ?>

                </p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($description): ?>
                    <p class="text-sm text-blue-600 dark:text-blue-400">
                        <?php echo e($description); ?>

                    </p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>
</fieldset>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/components/nothing-found.blade.php ENDPATH**/ ?>
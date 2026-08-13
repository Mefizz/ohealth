<div x-data="message" @flash-message.window="handleFlash($event)" x-init="setupListeners()">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error') || session('success') || session('status') || session('info') || session('warning')): ?>
        <div class="alert-message flex fixed top-[1.5rem] w-auto z-[99999] right-2"
            x-show="showAlertMessage"
        >
            <?php $__sessionArgs = ['error'];
if (session()->has($__sessionArgs[0])) :
if (isset($value)) { $__sessionPrevious[] = $value; }
$value = session()->get($__sessionArgs[0]); ?>
                <div role="alert"
                    class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400 border border-red-200 dark:border-red-900"
                >
                    <span class="font-medium whitespace-pre-line"><?php echo e(session('error')); ?></span>
                </div>
            <?php unset($value);
if (isset($__sessionPrevious) && !empty($__sessionPrevious)) { $value = array_pop($__sessionPrevious); }
if (isset($__sessionPrevious) && empty($__sessionPrevious)) { unset($__sessionPrevious); }
endif;
unset($__sessionArgs); ?>

            <?php $__sessionArgs = ['success'];
if (session()->has($__sessionArgs[0])) :
if (isset($value)) { $__sessionPrevious[] = $value; }
$value = session()->get($__sessionArgs[0]); ?>
                <div role="alert"
                    class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 dark:bg-gray-800 dark:text-green-400 border border-green-200 dark:border-green-900"
                >
                    <span class="font-medium whitespace-pre-line"><?php echo e(session('success')); ?></span>
                </div>

                <?php
                    session()->forget('success');
                ?>
            <?php unset($value);
if (isset($__sessionPrevious) && !empty($__sessionPrevious)) { $value = array_pop($__sessionPrevious); }
if (isset($__sessionPrevious) && empty($__sessionPrevious)) { unset($__sessionPrevious); }
endif;
unset($__sessionArgs); ?>

            <?php $__sessionArgs = ['info'];
if (session()->has($__sessionArgs[0])) :
if (isset($value)) { $__sessionPrevious[] = $value; }
$value = session()->get($__sessionArgs[0]); ?>
                <div role="alert"
                    class="p-4 mb-4 text-sm text-blue-800 rounded-lg bg-blue-50 dark:bg-gray-800 dark:text-blue-300 border border-blue-200 dark:border-blue-900"
                >
                    <span class="font-medium whitespace-pre-line"><?php echo e(session('info')); ?></span>
                </div>
            <?php unset($value);
if (isset($__sessionPrevious) && !empty($__sessionPrevious)) { $value = array_pop($__sessionPrevious); }
if (isset($__sessionPrevious) && empty($__sessionPrevious)) { unset($__sessionPrevious); }
endif;
unset($__sessionArgs); ?>

            <?php $__sessionArgs = ['warning'];
if (session()->has($__sessionArgs[0])) :
if (isset($value)) { $__sessionPrevious[] = $value; }
$value = session()->get($__sessionArgs[0]); ?>
                <div role="alert"
                    class="p-4 mb-4 text-sm text-yellow-800 rounded-lg bg-yellow-50 dark:bg-gray-800 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-900"
                >
                    <span class="font-medium whitespace-pre-line"><?php echo e(session('warning')); ?></span>
                </div>
            <?php unset($value);
if (isset($__sessionPrevious) && !empty($__sessionPrevious)) { $value = array_pop($__sessionPrevious); }
if (isset($__sessionPrevious) && empty($__sessionPrevious)) { unset($__sessionPrevious); }
endif;
unset($__sessionArgs); ?>

            <?php $__sessionArgs = ['status'];
if (session()->has($__sessionArgs[0])) :
if (isset($value)) { $__sessionPrevious[] = $value; }
$value = session()->get($__sessionArgs[0]); ?>
                <?php if (isset($component)) { $__componentOriginal96a5dd60665eed187e89027e4de4293b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal96a5dd60665eed187e89027e4de4293b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.message.successes','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('message.successes'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
                     <?php $__env->slot('status', null, []); ?> <?php echo e(session('status')); ?> <?php $__env->endSlot(); ?>
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal96a5dd60665eed187e89027e4de4293b)): ?>
<?php $attributes = $__attributesOriginal96a5dd60665eed187e89027e4de4293b; ?>
<?php unset($__attributesOriginal96a5dd60665eed187e89027e4de4293b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal96a5dd60665eed187e89027e4de4293b)): ?>
<?php $component = $__componentOriginal96a5dd60665eed187e89027e4de4293b; ?>
<?php unset($__componentOriginal96a5dd60665eed187e89027e4de4293b); ?>
<?php endif; ?>
            <?php unset($value);
if (isset($__sessionPrevious) && !empty($__sessionPrevious)) { $value = array_pop($__sessionPrevious); }
if (isset($__sessionPrevious) && empty($__sessionPrevious)) { unset($__sessionPrevious); }
endif;
unset($__sessionArgs); ?>

            <button
                type="button"
                @click="showAlertMessage= false"
                aria-label="Close"
                class="absolute -top-2 -right-1 inline-flex items-center justify-center rounded-full border border-red-300 hover:border-2 hover:border-red-400 active:border-red-600 bg-white/90 hover:bg-white drop-shadow-sm shadow-lg text-gray-600 hover:text-gray-800 w-6 h-6 cursor-pointer transition-all z-[100000]"
            >
                <?php
                // Parse arguments from the directive
                $iconArgs = ['close', 'w-3.5 h-3.5'];
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
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="alert-message flex fixed top-[1.5rem] w-auto z-[99999] right-2"
        x-show="showDynamicMessage"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-[-20px]"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-[-20px]"
        style="display: none;"
    >
        <div role="alert"
            :class="{
                'text-red-800 bg-red-50 dark:bg-gray-800 dark:text-red-400 border border-red-200 dark:border-red-900': dynamicType === 'error',
                'text-green-800 bg-green-50 dark:bg-gray-800 dark:text-green-400 border border-green-200 dark:border-green-900': dynamicType === 'success',
                'text-blue-800 bg-blue-50 dark:bg-gray-800 dark:text-blue-300 border border-blue-200 dark:border-blue-900': dynamicType === 'info',
                'text-yellow-800 bg-yellow-50 dark:bg-gray-800 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-900': dynamicType === 'warning'
            }"
            class="p-4 mb-4 text-sm rounded-lg shadow-xl pr-10"
        >
            <span class="font-medium whitespace-pre-line" x-text="dynamicText"></span>
        </div>
        <button
            type="button"
            @click="showDynamicMessage = false"
            aria-label="Close"
            class="absolute -top-2 -right-1 inline-flex items-center justify-center rounded-full border border-red-300 hover:border-2 hover:border-red-400 active:border-red-600 bg-white/90 hover:bg-white drop-shadow-sm shadow-lg text-gray-600 hover:text-gray-800 w-6 h-6 cursor-pointer transition-all z-[100000]"
        >
            <?php
                // Parse arguments from the directive
                $iconArgs = ['close', 'w-3.5 h-3.5'];
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
        </button>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('message', () => ({
            showAlertMessage: <?php echo e((session('error') || session('success') || session('status') || session('info') || session('warning')) ? 'true' : 'false'); ?>,
            showDynamicMessage: false,
            dynamicText: '',
            dynamicType: 'success',
            init() {
                if (this.showAlertMessage) {
                    setTimeout(() => this.showAlertMessage = false, 30000);
                }
            },
            setupListeners() {
                let handler = (data) => {
                    let item = Array.isArray(data) ? data[0] : (data.detail ? (Array.isArray(data.detail) ? data.detail[0] : data.detail) : data);
                    if (item && (item.message || item.text)) {
                        this.showAlertMessage = false;
                        this.dynamicText = item.message || item.text;
                        this.dynamicType = item.type || 'success';
                        this.showDynamicMessage = true;
                        setTimeout(() => this.showDynamicMessage = false, 30000);
                    }
                };
                window.addEventListener('flashMessage', handler);
                window.addEventListener('flash-message', handler);
                if (typeof Livewire !== 'undefined') {
                    Livewire.on('flashMessage', handler);
                } else {
                    document.addEventListener('livewire:init', () => {
                        Livewire.on('flashMessage', handler);
                    });
                }
            },
            handleFlash(event) {
                let item = event.detail ? (Array.isArray(event.detail) ? event.detail[0] : event.detail) : event;
                if (item && (item.message || item.text)) {
                    this.showAlertMessage = false;
                    this.dynamicText = item.message || item.text;
                    this.dynamicType = item.type || 'success';
                    this.showDynamicMessage = true;
                    setTimeout(() => this.showDynamicMessage = false, 30000);
                }
            }
        }))
    });
</script>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/components/x-message.blade.php ENDPATH**/ ?>
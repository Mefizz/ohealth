<fieldset
    class="fieldset !mb-6 !max-w-full !rounded-xl !border-gray-100 bg-white !p-6 !shadow-none dark:!border-gray-700 dark:bg-gray-800"
    x-data="{
    localEpisodes: $wire.entangle('form.episodes'),
    localMedicalRecords: $wire.entangle('form.medicalRecords'),
    availableEpisodes: <?php echo \Illuminate\Support\Js::from($availableEpisodes ?? [])->toHtml() ?>,

    openModal: false,
    openMedicalModal: false,
    searchType: 'ehealth',
    modalTarget: '',
    isNew: true,
    itemIndex: 0,
    modalForm: { date: '', name: '', uuid: '' },

    initAdd(target) {
        this.modalTarget = target;
        this.isNew = true;
        this.modalForm = {
            date: new Date().toISOString().split('T')[0],
            name: '',
            uuid: ''
        };
        this.openModal = true;
    },

    initEdit(target, index) {
        this.modalTarget = target;
        this.isNew = false;
        this.itemIndex = index;
        let source = target === 'episode' ? this.localEpisodes : this.localMedicalRecords;
        this.modalForm = { ...source[index] };
        this.openModal = true;
    },

    save() {
        let list = this.modalTarget === 'episode' ? this.localEpisodes : this.localMedicalRecords;
        if (this.isNew) {
            list.push({...this.modalForm});
        } else {
            list[this.itemIndex] = {...this.modalForm};
        }
        this.openModal = false;
    },

    saveMedical() {
        if (this.searchType === 'current') {
            this.localMedicalRecords.push({
                date: new Date().toLocaleDateString('uk-UA'),
                name: '<?php echo e(__('care-plan.current_interaction')); ?>'
            });
        } else {
            this.localMedicalRecords.push({
                date: new Date().toLocaleDateString('uk-UA'),
                name: '<?php echo e(__('care-plan.medical_record_from_ehealth')); ?>'
            });
        }
        this.openMedicalModal = false;
    },

    removeEntry(type, index) {
        if (type === 'episode') this.localEpisodes.splice(index, 1);
        else this.localMedicalRecords.splice(index, 1);
    }
}"
>
    <legend class="legend"><?php echo e(__('care-plan.supporting_information')); ?></legend>

    <div class="mt-4 space-y-10">
        <div class="space-y-4">
            <template x-if="localEpisodes && localEpisodes.length > 0">
                <div class="index-table-wrapper overflow-x-auto">
                    <table class="index-table">
                        <thead class="index-table-thead">
                            <tr>
                                <th class="index-table-th w-32"><?php echo e(__('care-plan.date')); ?></th>
                                <th class="index-table-th"><?php echo e(__('care-plan.name_episode')); ?></th>
                                <th class="index-table-th w-24 text-right"><?php echo e(__('forms.action')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in localEpisodes" :key="'ep-' + index">
                                <tr class="index-table-tr group cursor-pointer" @click="initEdit('episode', index)">
                                    <td class="index-table-td" x-text="item.date"></td>
                                    <td class="index-table-td-primary" x-text="item.name"></td>
                                    <td class="index-table-td text-right">
                                        <button
                                            type="button"
                                            @click.stop="removeEntry('episode', index)"
                                            class="svg-hover-action"
                                        >
                                            <?php
                // Parse arguments from the directive
                $iconArgs = ['delete', 'w-5 h-5 text-red-600'];
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
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
            <button type="button" @click="initAdd('episode')" class="item-add ml-1 flex items-center">
                <?php echo e(__('care-plan.add_episode')); ?>

            </button>
        </div>

        <div class="space-y-4">
            <template x-if="localMedicalRecords && localMedicalRecords.length > 0">
                <div class="index-table-wrapper overflow-x-auto">
                    <table class="index-table">
                        <thead class="index-table-thead">
                            <tr>
                                <th class="index-table-th w-32"><?php echo e(__('care-plan.date')); ?></th>
                                <th class="index-table-th"><?php echo e(__('care-plan.medical_record')); ?></th>
                                <th class="index-table-th w-24 text-right"><?php echo e(__('forms.action')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in localMedicalRecords" :key="'mr-' + index">
                                <tr class="index-table-tr group cursor-pointer" @click="initEdit('medical', index)">
                                    <td class="index-table-td" x-text="item.date"></td>
                                    <td class="index-table-td-primary" x-text="item.name"></td>
                                    <td class="index-table-td text-right">
                                        <button
                                            type="button"
                                            @click.stop="removeEntry('medical', index)"
                                            class="svg-hover-action"
                                        >
                                            <?php
                // Parse arguments from the directive
                $iconArgs = ['delete', 'w-5 h-5 text-red-600'];
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
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
            <button type="button" @click="openMedicalModal = true" class="item-add ml-1 flex items-center">
                <?php echo e(__('care-plan.add_medical_record')); ?>

            </button>
        </div>
    </div>

    <template x-teleport="body">
        <div x-show="openModal" style="display: none" class="modal" @keydown.escape.prevent.stop="openModal = false">
            <div
                x-show="openModal"
                x-transition.opacity
                class="fixed inset-0 z-[100] bg-black/25 backdrop-blur-sm"
            ></div>
            <div
                x-show="openModal"
                x-transition
                @click="openModal = false"
                class="relative z-[101] flex min-h-screen items-center justify-center p-4"
            >
                <div @click.stop
                x-trap.noscroll.inert="openModal"
                class="modal-content h-fit w-full max-w-2xl rounded-2xl bg-white shadow-lg dark:bg-gray-800"
            >
                <h3
                    class="modal-header !flex !justify-start gap-2 border-b border-gray-100 p-6 dark:border-gray-700"
                >
                    <span x-text="isNew ? '<?php echo e(__('forms.add')); ?>' : '<?php echo e(__('forms.edit')); ?>'"></span>
                    <span
                        x-text="modalTarget === 'episode' ? '<?php echo e(mb_strtolower(__('care-plan.episode'))); ?>' : '<?php echo e(mb_strtolower(__('care-plan.medical_record'))); ?>'"
                    ></span>
                </h3>
                <form @submit.prevent="save()">
                    <div class="space-y-4 p-6">
                        <div class="form-group group">
                            <label for="modalDate" class="label-modal"
                                ><?php echo e(__('forms.date')); ?> <span class="text-red-600">*</span></label>
                                <input
                                    type="text"
                                    id="modalDate"
                                    x-model="modalForm.date"
                                    class="input-modal datepicker-input w-full"
                                    datepicker-format="<?php echo e(frontendDateFormat()); ?>"
                                    autocomplete="off"
                                    required
                                />
                            </div>
                            <div
                                x-show="modalTarget === 'episode' && availableEpisodes.length > 0"
                                class="form-group group"
                            >
                                <label class="label-modal"><?php echo e(__('care-plan.episode')); ?> <span class="text-red-600">*</span></label>
                                <select
                                    x-model="modalForm.uuid"
                                    class="input-select peer w-full"
                                    @change="
                                        let ep = availableEpisodes.find((e) => e.uuid === modalForm.uuid);
                                        if (ep) {
                                            modalForm.name = ep.name;
                                            modalForm.date = ep.date;
                                        }
                                    "
                                >
                                    <option value=""><?php echo e(__('forms.select')); ?></option>
                                    <template x-for="ep in availableEpisodes" :key="ep.uuid">
                                        <option :value="ep.uuid" x-text="ep.name + ' (' + ep.date + ')'"></option>
                                    </template>
                                </select>
                            </div>
                            <div
                                x-show="modalTarget !== 'episode' || availableEpisodes.length === 0"
                                class="form-group group"
                            >
                                <label class="label-modal"><?php echo e(__('care-plan.name_description')); ?> <span class="text-red-600">*</span></label>
                                <input
                                    type="text"
                                    x-model="modalForm.name"
                                    :placeholder="modalTarget === 'episode' ? '<?php echo e(__('care-plan.episode_name_placeholder')); ?>' : '<?php echo e(__('care-plan.record_name_placeholder')); ?>'"
                                    class="input-modal w-full"
                                />
                            </div>
                        </div>
                        <div class="mt-6 flex flex-row items-center gap-4 border-t border-gray-100 p-6 dark:border-gray-700">
                            <button type="button" @click="openModal = false" class="button-minor">
                                <?php echo e(__('forms.cancel')); ?>

                            </button>
                            <button
                                type="submit"
                                class="button-primary"
                                :disabled="(modalTarget === 'episode' &&
                                    availableEpisodes.length > 0 &&
                                    ! modalForm.uuid) ||
                                ! modalForm.date ||
                                (! modalForm.name && ! modalForm.uuid)"
                            >
                                <?php echo e(__('forms.save')); ?>

                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div
            x-show="openMedicalModal"
            style="display: none"
            class="modal"
            @keydown.escape.prevent.stop="openMedicalModal = false"
        >
            <div
                x-show="openMedicalModal"
                x-transition.opacity
                class="fixed inset-0 z-[100] bg-black/25 backdrop-blur-sm"
            ></div>
            <div
                x-show="openMedicalModal"
                x-transition
                @click="openMedicalModal = false"
                class="relative z-[101] flex min-h-screen items-center justify-center p-4"
            >
                <div
                    @click.stop
                    x-trap.noscroll.inert="openMedicalModal"
                    class="modal-content h-fit w-full max-w-2xl rounded-2xl bg-white shadow-lg dark:bg-gray-800"
                >
                    <div class="p-6">
                        <form @submit.prevent="saveMedical()">
                            <fieldset class="fieldset !border-gray-100 !shadow-none dark:!border-gray-700">
                                <legend class="legend">
                                    <?php echo e(__('care-plan.search_medical_records') ?? 'Пошук медичних записів'); ?>

                                </legend>

                                <div class="mt-2 flex">
                                    <div class="me-6 flex items-center">
                                        <input
                                            id="current-interaction"
                                            type="radio"
                                            value="current"
                                            x-model="searchType"
                                            name="search-type"
                                            class="text-neutral-primary border-default-medium bg-neutral-secondary-medium checked:border-brand focus:ring-brand-subtle border-default h-4 w-4 appearance-none rounded-full border focus:ring-2 focus:outline-none"
                                        />
                                        <label
                                            for="current-interaction"
                                            class="text-heading ms-2 text-sm font-medium whitespace-nowrap text-gray-700 select-none dark:text-gray-300"
                                        >
                                            <?php echo e(__('care-plan.current_interaction')); ?>

                                        </label>
                                    </div>

                                    <div class="flex items-center">
                                        <input
                                            id="search-ehealth"
                                            type="radio"
                                            value="ehealth"
                                            x-model="searchType"
                                            name="search-type"
                                            class="text-neutral-primary border-default-medium bg-neutral-secondary-medium checked:border-brand focus:ring-brand-subtle border-default h-4 w-4 appearance-none rounded-full border focus:ring-2 focus:outline-none"
                                        />
                                        <label
                                            for="search-ehealth"
                                            class="text-heading ms-2 text-sm font-medium whitespace-nowrap text-gray-700 select-none dark:text-gray-300"
                                        >
                                            <?php echo e(__('care-plan.search_in_ehealth')); ?>

                                        </label>
                                    </div>
                                </div>
                            </fieldset>

                            <div x-show="searchType === 'ehealth'" class="mt-4">
                                <fieldset class="fieldset !border-gray-100 !shadow-none dark:!border-gray-700">
                                    <div class="mb-4 flex items-center gap-1 font-semibold text-gray-900 dark:text-white">
                                        <?php
                // Parse arguments from the directive
                $iconArgs = ['search-outline', 'w-4.5 h-4.5'];
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
                                        <p><?php echo e(__('care-plan.search')); ?></p>
                                    </div>

                                    <div
                                        class="form-row-2"
                                        x-data="{
                                   open: false,
                                   selectedType: $wire.entangle('medicalRecordType'),
                                   types: {
                                   'CONDITION': '<?php echo e(__('care-plan.conditions/diagnoses')); ?>',
                                   'OBSERVATION': '<?php echo e(__('care-plan.observations')); ?>'
                                   }
                                   }"
                                    >
                                        <div class="relative">
                                            <input
                                                type="text"
                                                id="recordTypeFilter"
                                                class="input peer w-full cursor-pointer text-gray-500 dark:text-gray-400"
                                                x-on:click="open = ! open"
                                                :value="types[selectedType] || '<?php echo e(__('forms.select_type')); ?>'"
                                                readonly
                                            />

                                            <svg
                                                class="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-gray-500 dark:text-gray-400"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                viewBox="0 0 24 24"
                                            >
                                                <path d="M19 9l-7 7-7-7"></path>
                                            </svg>

                                            <div
                                                x-show="open"
                                                x-on:click.away="open = false"
                                                x-transition:enter="transition ease-out duration-100"
                                                x-transition:enter-start="transform opacity-0 scale-95"
                                                x-transition:enter-end="transform opacity-100 scale-100"
                                                x-transition:leave="transition ease-in duration-75"
                                                x-transition:leave-start="transform opacity-100 scale-100"
                                                x-transition:leave-end="transform opacity-0 scale-95"
                                                class="absolute z-10 mt-2 w-full rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-700"
                                                x-cloak
                                            >
                                                <ul class="py-2 text-sm text-gray-700 dark:text-gray-200">
                                                    <li
                                                        class="cursor-pointer px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600"
                                                        @click="
                                                            selectedType = 'CONDITION';
                                                            open = false;
                                                        "
                                                    >
                                                        <?php echo e(__('care-plan.conditions/diagnoses')); ?>

                                                    </li>

                                                    <li
                                                        class="cursor-pointer px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600"
                                                        @click="
                                                            selectedType = 'OBSERVATION';
                                                            open = false;
                                                        "
                                                    >
                                                        <?php echo e(__('care-plan.observations')); ?>

                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="form-group group">
                                            <select id="episode" name="episode" class="input-select peer">
                                                <option selected value=""><?php echo e(__('forms.select')); ?></option>
                                            </select>
                                            <label for="episode" class="label"> <?php echo e(__('care-plan.episode')); ?> </label>

                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['care-plan.episode'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                                <p class="text-error"><?php echo e($message); ?></p>
                                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    </div>
                                </fieldset>
                            </div>

                            <div class="mt-6 flex flex-row items-center gap-4 border-t border-gray-100 pt-4 dark:border-gray-700">
                                <button type="button" @click="openMedicalModal = false" class="button-minor">
                                    <?php echo e(__('forms.cancel')); ?>

                                </button>
                                <button type="submit" class="button-primary"><?php echo e(__('forms.save')); ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </template>
</fieldset>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/parts/supporting_information.blade.php ENDPATH**/ ?>
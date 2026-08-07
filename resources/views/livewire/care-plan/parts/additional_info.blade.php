<fieldset class="fieldset bg-white dark:bg-gray-800 !rounded-xl !shadow-none !border-gray-100 dark:!border-gray-700 !max-w-full !p-6 !mb-6">
    <legend class="legend">
        {{ __('forms.additional_info') }}
    </legend>

    <div class="form-row-2">
        <div class="form-group group">
            <select id="based_on" name="based_on" class="input-select peer" wire:model="form.based_on">
                <option value="">{{ __('care-plan.choose_care_plan') }}</option>
            </select>
            <label for="based_on" class="label">
                {{ __('care-plan.based_care_plan') }}
            </label>
        </div>

        <div class="form-group group">
            <select id="part_of" name="part_of" class="input-select peer" wire:model="form.part_of">
                <option value="">{{ __('care-plan.choose_care_plan') }}</option>
            </select>
            <label for="part_of" class="label">
                {{ __('care-plan.part_care_plan') }}
            </label>
        </div>
    </div>

    <div class="mt-6">
        <label for="description" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
            {{ __('care-plan.extended_description') }}
        </label>
        <textarea
            id="description"
            rows="4"
            class="textarea w-full dark:bg-gray-700/50 dark:border-gray-600 dark:text-white"
            placeholder="{{ __('forms.write_comment_here') }}"
            wire:model="form.description"></textarea>
        @error('form.description') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="mt-6">
        <label for="note" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
            {{ __('care-plan.notes') }}
        </label>
        <textarea
            id="note"
            rows="4"
            class="textarea w-full dark:bg-gray-700/50 dark:border-gray-600 dark:text-white"
            placeholder="{{ __('forms.write_comment_here') }}"
            wire:model="form.note"></textarea>
        @error('form.note') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
    </div>
</fieldset>

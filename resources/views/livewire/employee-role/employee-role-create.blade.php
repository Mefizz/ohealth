<section class="section-form">
    <x-header-navigation class="breadcrumb-form">
        <x-slot name="title">{{ __('employee-roles.label') }}</x-slot>
    </x-header-navigation>

    <form class="form shift-content">
        <fieldset class="fieldset">
            <legend class="legend">{{ __('employee-roles.new') }}</legend>

            <div class="form-row-2">
                <x-forms.combobox
                    :options="$healthcareServices"
                    bind="form.healthcareServiceId"
                    bindValue="uuid"
                    bindParam="label"
                    :isRequired="true"
                    :label="__('employee-roles.healthcareServiceId')"
                />

                <x-forms.combobox
                    :options="$employees"
                    bind="form.employeeId"
                    bindValue="uuid"
                    bindParam="label"
                    :isRequired="true"
                    :label="__('employee-roles.employeeId')"
                />
            </div>
        </fieldset>

        <div class="flex gap-8">
            <a href="{{ route('employee-role.index', legalEntity()) }}" wire:navigate class="button-minor">
                {{ __('forms.cancel') }}
            </a>
            <button wire:click.prevent="create" type="submit" class="button-primary">
                {{ __('forms.create') }}
            </button>
        </div>
    </form>

    <livewire:components.x-message :key="time()" />
    <x-forms.loading />
</section>

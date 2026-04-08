<x-layouts.patient :id="$id" :uuid="$uuid" :patientFullName="$patientFullName">
    <div x-data="{ activeSection: 'encounter' }" class="flex flex-col lg:flex-row gap-6 relative" @scroll.window.throttle.50ms="
        const sections = ['encounter', 'patient_data', 'visit_info', 'diagnoses', 'referral'];
        for (const section of sections) {
            const el = document.getElementById(section);
            if (el) {
                const rect = el.getBoundingClientRect();
                if (rect.top <= 150 && rect.bottom >= 150) {
                    activeSection = section;
                    break;
                }
            }
        }
    ">
        {{-- Sidebar Navigation --}}
        <div class="lg:w-1/4">
            <div class="summary-sidebar sticky top-24">
                <nav class="space-y-1">
                    <a @click.prevent="document.getElementById('encounter').scrollIntoView({behavior: 'smooth'})"
                       href="#encounter"
                       class="summary-tab w-full text-left"
                       :class="activeSection === 'encounter' ? 'summary-tab-active' : 'summary-tab-inactive'">
                        {{ __('encounter.encounter') }}
                    </a>
                    <a @click.prevent="document.getElementById('patient_data').scrollIntoView({behavior: 'smooth'})"
                       href="#patient_data"
                       class="summary-tab w-full text-left"
                       :class="activeSection === 'patient_data' ? 'summary-tab-active' : 'summary-tab-inactive'">
                        {{ __('patients.patient_data') }}
                    </a>
                    <a @click.prevent="document.getElementById('visit_info').scrollIntoView({behavior: 'smooth'})"
                       href="#visit_info"
                       class="summary-tab w-full text-left"
                       :class="activeSection === 'visit_info' ? 'summary-tab-active' : 'summary-tab-inactive'">
                        {{ __('encounter.visit_info') }}
                    </a>
                    <a @click.prevent="document.getElementById('diagnoses').scrollIntoView({behavior: 'smooth'})"
                       href="#diagnoses"
                       class="summary-tab w-full text-left"
                       :class="activeSection === 'diagnoses' ? 'summary-tab-active' : 'summary-tab-inactive'">
                        {{ __('encounter.diagnoses') }}
                    </a>
                    <a @click.prevent="document.getElementById('referral').scrollIntoView({behavior: 'smooth'})"
                       href="#referral"
                       class="summary-tab w-full text-left"
                       :class="activeSection === 'referral' ? 'summary-tab-active' : 'summary-tab-inactive'">
                        {{ __('encounter.referral') }}
                    </a>
                </nav>

                <div class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-700">
                    <button type="button" @click="$wire.save()" class="w-full button-primary-outline flex items-center justify-center gap-2 mb-3">
                        @icon('archive', 'w-4 h-4')
                        {{ __('forms.save') }}
                    </button>
                    <button type="button" @click="$wire.set('showSignatureModal', true)" class="w-full button-primary flex items-center justify-center gap-2">
                        @icon('edit-linear', 'w-4 h-4')
                        {{ __('forms.sign_with_KEP') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Main Content --}}
        <div class="lg:w-3/4 space-y-6 pb-24">
            <div id="encounter" class="record-inner-card scroll-mt-24">
                <div class="record-inner-header">
                    <h3>@icon('pencil-clipboard', 'w-5 h-5 inline mr-2') {{ __('encounter.encounter') }}</h3>
                </div>
                <div class="p-6">
                    @include('livewire.encounter.parts.encounter')
                </div>
            </div>

            <div id="patient_data" class="record-inner-card scroll-mt-24">
                <div class="record-inner-header">
                    <h3>@icon('patients', 'w-5 h-5 inline mr-2') {{ __('patients.patient_data') }}</h3>
                </div>
                <div class="p-6">
                    @include('livewire.encounter.parts.patient_data')
                </div>
            </div>

            <div id="visit_info" class="record-inner-card scroll-mt-24">
                <div class="record-inner-header">
                    <h3>@icon('details', 'w-5 h-5 inline mr-2') {{ __('visti-info.label') }}</h3>
                </div>
                <div class="p-6">
                    @include('livewire.encounter.parts.visit_info')
                </div>
            </div>

            <div id="diagnoses" class="record-inner-card scroll-mt-24">
                <div class="record-inner-header">
                    <h3>@icon('alert-circle', 'w-5 h-5 inline mr-2') {{ __('encounter.diagnoses') }}</h3>
                </div>
                <div class="p-6">
                    @include('livewire.encounter.parts.diagnoses')
                </div>
            </div>

            <div id="referral" class="record-inner-card scroll-mt-24">
                <div class="record-inner-header">
                    <h3>@icon('hugeicons-contracts', 'w-5 h-5 inline mr-2') {{ __('encounter.referral') }}</h3>
                </div>
                <div class="p-6">
                    @include('livewire.encounter.parts.referrals')
                </div>
            </div>
        </div>

        @include('components.signature-modal', ['method' => 'sign'])
    </div>
</x-layouts.patient>

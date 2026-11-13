<div>
    @if ($editingReferral)
        <livewire:referral.e-referral-edit
            :legal-entity-id="(int) legalEntity()->id"
            :person-id="$personId"
            :referral-id="$selectedReferralId"
            :key="'referral-edit-'.$selectedReferralId"
        />
    @else
        <livewire:referral.e-referral-view
            :legal-entity-id="(int) legalEntity()->id"
            :person-id="$personId"
            :referral-id="$selectedReferralId"
            :key="'referral-view-'.$selectedReferralId"
        />
    @endif
</div>

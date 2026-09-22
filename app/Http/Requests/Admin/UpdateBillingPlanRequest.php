<?php

namespace App\Http\Requests\Admin;

use App\Support\Billing\BillingFeatureCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBillingPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $featureKeys = BillingFeatureCatalog::keys();

        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'currency' => ['required', 'string', 'size:3'],
            'monthly_amount' => ['nullable', 'integer', 'min:0'],
            'annual_amount' => ['nullable', 'integer', 'min:0'],
            'yearly_monthly_equivalent' => ['nullable', 'integer', 'min:0'],
            'monthly_stripe_price_id' => ['nullable', 'string', 'max:191'],
            'annual_stripe_price_id' => ['nullable', 'string', 'max:191'],
            'screen_limit' => ['nullable', 'integer', 'min:0'],
            'storage_gb' => ['nullable', 'integer', 'min:0'],
            'team_member_limit' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', Rule::in($featureKeys)],
            'feature_labels' => ['nullable', 'array'],
            'feature_labels.*' => ['string', 'max:120'],
            'badge' => ['nullable', 'string', 'max:60'],
            'popular' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
            'public' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'cta' => ['required', 'string', 'max:60'],
            'sync_stripe_prices' => ['sometimes', 'boolean'],
            'confirm' => ['accepted'],
        ];
    }
}

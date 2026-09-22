<?php

namespace App\Support\Billing;

/**
 * Canonical billing feature keys that can be gated or marketed.
 * Aliases map marketing / legacy labels onto a single canonical key.
 */
final class BillingFeatureCatalog
{
    /**
     * Canonical key => human label for admin checklists.
     *
     * @return array<string, string>
     */
    public static function definitions(): array
    {
        return [
            'full_template_library' => 'Full template library',
            'premium_templates' => 'Premium templates',
            'images_videos' => 'Images & videos',
            'playlists' => 'Playlists',
            'brand_kit' => 'Brand Kit',
            'advanced_scheduling' => 'Advanced scheduling',
            'locations' => 'Locations & Screen groups',
            'basic_analytics' => 'Basic Analytics',
            'advanced_analytics' => 'Advanced Analytics',
            'custom_branding' => 'Custom branding',
            'priority_email_support' => 'Priority email support',
            'priority_support' => 'Priority support',
            'ai_text' => 'AI text generation',
            'ai_images' => 'AI image generation',
            'ai_design_generation' => 'AI Screen Design generation',
            'unlimited_screens' => 'Unlimited Screens',
            'custom_storage' => 'Custom storage',
            'dedicated_account_manager' => 'Dedicated account manager',
            'custom_onboarding' => 'Custom onboarding',
            'advanced_permissions' => 'Advanced permissions',
            'sla' => 'SLA',
            'custom_pricing' => 'Custom pricing',
            'all_business_features' => 'Everything in Business',
        ];
    }

    /**
     * Alias → canonical key.
     *
     * @return array<string, string>
     */
    public static function aliases(): array
    {
        return [
            'core_templates' => 'full_template_library',
            'screen_groups' => 'locations',
            'analytics' => 'basic_analytics',
            // priority_email_support stays distinct from priority_support
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * Resolve a feature key (or alias) to its canonical form.
     */
    public static function canonicalize(string $feature): string
    {
        $aliases = self::aliases();

        return $aliases[$feature] ?? $feature;
    }

    /**
     * Whether a plan feature list includes the requested capability.
     *
     * @param  list<string>  $planFeatures
     */
    public static function planHas(array $planFeatures, string $feature): bool
    {
        $wanted = self::canonicalize($feature);
        $normalized = array_map(
            fn (string $key): string => self::canonicalize($key),
            $planFeatures,
        );

        if (in_array('all_business_features', $normalized, true)) {
            // Enterprise-style "everything in Business" covers all gated product features
            // except dedicated enterprise-only marketing keys.
            $enterpriseOnly = [
                'unlimited_screens',
                'custom_storage',
                'dedicated_account_manager',
                'custom_onboarding',
                'advanced_permissions',
                'sla',
                'custom_pricing',
            ];

            if (! in_array($wanted, $enterpriseOnly, true)) {
                return true;
            }
        }

        return in_array($wanted, $normalized, true);
    }

    /**
     * Admin checklist payload.
     *
     * @return list<array{key: string, label: string}>
     */
    public static function checklist(): array
    {
        $rows = [];

        foreach (self::definitions() as $key => $label) {
            $rows[] = [
                'key' => $key,
                'label' => $label,
            ];
        }

        return $rows;
    }
}

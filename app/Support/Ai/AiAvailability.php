<?php

namespace App\Support\Ai;

use App\Enums\TemplateOrientation;
use App\Enums\TemplateTheme;
use App\Models\FeatureFlag;
use App\Models\Workspace;
use App\Support\Ai\Contracts\AiImageProvider;
use App\Support\Ai\Contracts\AiTextProvider;
use App\Support\Ai\Providers\FakeAiImageProvider;
use App\Support\Ai\Providers\FakeAiTextProvider;
use App\Support\Ai\Providers\OpenAiCompatibleImageProvider;
use App\Support\Ai\Providers\OpenAiCompatibleTextProvider;
use App\Support\Billing\BillingEntitlement;

final class AiAvailability
{
    public static function featureEnabled(): bool
    {
        return FeatureFlag::isEnabled('ai_content_generation', (bool) config('ai.enabled', false));
    }

    public static function isConfigured(): bool
    {
        if (! self::featureEnabled()) {
            return false;
        }

        $provider = (string) config('ai.provider', 'fake');

        return match ($provider) {
            'fake' => true,
            'openai_compatible' => filled(config('ai.openai.api_key')),
            default => false,
        };
    }

    /**
     * Soft-gate AI capabilities when billing.enforce is on and the plan
     * lacks the matching ai_* feature key.
     */
    public static function planAllows(Workspace $workspace, string $feature): bool
    {
        if (! BillingEntitlement::enforce()) {
            return true;
        }

        return BillingEntitlement::hasFeature($workspace, $feature);
    }

    public static function canGenerateText(?Workspace $workspace): bool
    {
        if (! self::isConfigured()) {
            return false;
        }

        if ($workspace === null) {
            return true;
        }

        return self::planAllows($workspace, 'ai_text')
            || self::planAllows($workspace, 'ai_design_generation');
    }

    public static function canGenerateImages(?Workspace $workspace): bool
    {
        if (! self::isConfigured()) {
            return false;
        }

        if ($workspace === null) {
            return true;
        }

        return self::planAllows($workspace, 'ai_images')
            || self::planAllows($workspace, 'ai_design_generation');
    }

    public static function canGenerateDesigns(?Workspace $workspace): bool
    {
        if (! self::isConfigured()) {
            return false;
        }

        if ($workspace === null) {
            return true;
        }

        return self::planAllows($workspace, 'ai_design_generation');
    }

    /**
     * Public, non-secret status for the frontend.
     *
     * @return array{
     *     available: bool,
     *     feature_enabled: bool,
     *     configured: bool,
     *     provider: string,
     *     video_enabled: bool,
     *     text_available: bool,
     *     images_available: bool,
     *     designs_available: bool,
     *     message: string|null
     * }
     */
    public static function status(?Workspace $workspace = null): array
    {
        $feature = self::featureEnabled();
        $configured = self::isConfigured();
        $baseAvailable = $feature && $configured;

        $text = $baseAvailable && ($workspace === null || self::canGenerateText($workspace));
        $images = $baseAvailable && ($workspace === null || self::canGenerateImages($workspace));
        $designs = $baseAvailable && ($workspace === null || self::canGenerateDesigns($workspace));
        $available = $text || $images || $designs;

        $message = null;
        if (! $feature || ! $configured) {
            $message = 'AI generation is currently unavailable.';
        } elseif ($workspace !== null && ! $available) {
            $message = 'AI generation is not included in your current plan.';
        }

        return [
            'available' => $available,
            'feature_enabled' => $feature,
            'configured' => $configured,
            'provider' => (string) config('ai.provider', 'fake'),
            'video_enabled' => (bool) config('ai.video.enabled', false),
            'text_available' => $text,
            'images_available' => $images,
            'designs_available' => $designs,
            'message' => $message,
        ];
    }

    public static function textProvider(): AiTextProvider
    {
        return match ((string) config('ai.provider', 'fake')) {
            'openai_compatible' => app(OpenAiCompatibleTextProvider::class),
            default => app(FakeAiTextProvider::class),
        };
    }

    public static function imageProvider(): AiImageProvider
    {
        return match ((string) config('ai.provider', 'fake')) {
            'openai_compatible' => app(OpenAiCompatibleImageProvider::class),
            default => app(FakeAiImageProvider::class),
        };
    }

    /**
     * @return array{width: int, height: int, label: string}
     */
    public static function aspect(string $key): array
    {
        $aspects = config('ai.image.aspects', []);
        if (! isset($aspects[$key]) || ! is_array($aspects[$key])) {
            $key = (string) config('ai.image.default_aspect', 'landscape');
        }

        /** @var array{width: int, height: int, label: string} */
        return $aspects[$key];
    }

    public static function orientationCanvas(TemplateOrientation $orientation): TemplateOrientation
    {
        return $orientation;
    }

    public static function defaultTheme(): TemplateTheme
    {
        return TemplateTheme::Blank;
    }
}

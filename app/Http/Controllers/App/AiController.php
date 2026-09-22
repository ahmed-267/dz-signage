<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\AcceptAiDesignConceptRequest;
use App\Http\Requests\Ai\ConfirmAiAgentRequest;
use App\Http\Requests\Ai\GenerateAiDesignRequest;
use App\Http\Requests\Ai\GenerateAiImageRequest;
use App\Http\Requests\Ai\GenerateAiTextRequest;
use App\Http\Requests\Ai\ProposeAiAgentRequest;
use App\Http\Requests\Ai\RewriteAiTextRequest;
use App\Http\Requests\Ai\SaveAiImageRequest;
use App\Models\AiGeneration;
use App\Support\Ai\AiAvailability;
use App\Support\Ai\AiContentService;
use App\Support\Ai\AiException;
use App\Support\Ai\AiSignageAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AiController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        Gate::authorize('create', AiGeneration::class);

        return response()->json([
            'ai' => AiAvailability::status($request->user()->currentWorkspace),
            'options' => [
                'image_aspects' => config('ai.image.aspects'),
                'image_styles' => array_keys(config('ai.image.styles', [])),
                'image_refinements' => array_keys(config('ai.image.refinements', [])),
                'text_modes' => config('ai.text.modes'),
                'text_purposes' => config('ai.text.purposes'),
                'text_tones' => config('ai.text.tones'),
                'text_lengths' => config('ai.text.lengths'),
                'rewrite_actions' => config('ai.text.rewrite_actions'),
                'design_purposes' => config('ai.design.purposes'),
                'design_styles' => config('ai.design.styles'),
                'design_archetypes' => config('ai.design.archetypes'),
                'agent_intents' => config('ai.agent.intents'),
                'prompt_max' => config('ai.limits.prompt_max'),
                'quality_mode' => config('ai.quality_mode'),
            ],
        ]);
    }

    public function proposeAgent(ProposeAiAgentRequest $request, AiSignageAgent $agent): JsonResponse
    {
        Gate::authorize('create', AiGeneration::class);

        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        try {
            $proposal = $agent->propose(
                $request->user(),
                $workspace,
                (string) $request->validated('prompt'),
                [
                    'orientation' => $request->validated('orientation'),
                    'purpose' => $request->validated('purpose'),
                    'style' => $request->validated('style'),
                    'name' => $request->validated('name'),
                    'preferred_intent' => $request->validated('preferred_intent'),
                    'screen_hints' => $request->validated('screen_hints'),
                    'activate' => $request->boolean('activate', false),
                    'deploy' => $request->boolean('deploy', false),
                    'generate_matching_image' => $request->boolean('generate_matching_image', false),
                    'idempotency_key' => $request->validated('idempotency_key'),
                ],
            );
        } catch (AiException $e) {
            return $this->aiError($e);
        }

        return response()->json($proposal);
    }

    public function confirmAgent(ConfirmAiAgentRequest $request, AiSignageAgent $agent): JsonResponse
    {
        Gate::authorize('create', AiGeneration::class);

        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $generation = AiGeneration::query()->findOrFail((int) $request->validated('proposal_id'));
        Gate::authorize('view', $generation);

        try {
            $result = $agent->confirm(
                $request->user(),
                $workspace,
                $generation,
                [
                    'confirm' => $request->boolean('confirm'),
                    'activate' => $request->boolean('activate', false),
                    'deploy' => $request->boolean('deploy', false),
                ],
            );
        } catch (AiException $e) {
            return $this->aiError($e);
        }

        return response()->json([
            'proposal_id' => $generation->id,
            ...$result,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AiGeneration::class);

        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $items = AiGeneration::query()
            ->where('workspace_id', $workspace->id)
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (AiGeneration $g) => [
                'id' => $g->id,
                'type' => $g->type->value,
                'status' => $g->status->value,
                'prompt' => mb_substr($g->prompt, 0, 120),
                'created_at' => $g->created_at?->toIso8601String(),
                'media_asset_id' => $g->media_asset_id,
                'screen_design_id' => $g->screen_design_id,
                'error_message' => $g->error_message,
                'has_preview' => $g->type->value === 'image'
                    && $g->status->value === 'completed'
                    && $g->hasTempFile(),
            ]);

        return response()->json(['generations' => $items]);
    }

    public function generateText(GenerateAiTextRequest $request, AiContentService $ai): JsonResponse
    {
        Gate::authorize('create', AiGeneration::class);

        try {
            $result = $ai->generateText(
                $request->user(),
                $request->user()->currentWorkspace,
                (string) $request->validated('prompt'),
                [
                    'purpose' => $request->validated('purpose'),
                    'mode' => $request->validated('mode'),
                    'tone' => $request->validated('tone'),
                    'length' => $request->validated('length') ?? 'short',
                    'variants' => $request->validated('variants') ?? 3,
                    'idempotency_key' => $request->validated('idempotency_key'),
                ],
            );
        } catch (AiException $e) {
            return $this->aiError($e);
        }

        return response()->json([
            'generation_id' => $result['generation']->id,
            'text' => $result['text'],
            'texts' => $result['texts'],
            'status' => $result['generation']->status->value,
        ]);
    }

    public function rewriteText(RewriteAiTextRequest $request, AiContentService $ai): JsonResponse
    {
        Gate::authorize('create', AiGeneration::class);

        try {
            $result = $ai->rewriteText(
                $request->user(),
                $request->user()->currentWorkspace,
                (string) $request->validated('text'),
                [
                    'action' => (string) $request->validated('action'),
                    'variants' => $request->validated('variants') ?? 1,
                    'idempotency_key' => $request->validated('idempotency_key'),
                ],
            );
        } catch (AiException $e) {
            return $this->aiError($e);
        }

        return response()->json([
            'generation_id' => $result['generation']->id,
            'text' => $result['text'],
            'texts' => $result['texts'],
            'status' => $result['generation']->status->value,
        ]);
    }

    public function generateImage(GenerateAiImageRequest $request, AiContentService $ai): JsonResponse
    {
        Gate::authorize('create', AiGeneration::class);

        try {
            $result = $ai->generateImage(
                $request->user(),
                $request->user()->currentWorkspace,
                (string) $request->validated('prompt'),
                [
                    'aspect' => $request->validated('aspect') ?? 'landscape',
                    'style' => $request->validated('style'),
                    'refinement' => $request->validated('refinement'),
                    'variants' => $request->validated('variants') ?? config('ai.image.default_variants', 2),
                    'width' => $request->validated('width'),
                    'height' => $request->validated('height'),
                    'idempotency_key' => $request->validated('idempotency_key'),
                ],
            );
        } catch (AiException $e) {
            return $this->aiError($e);
        }

        $generation = $result['generation'];

        return response()->json([
            'generation_id' => $generation->id,
            'status' => $generation->status->value,
            'preview_url' => $result['preview_url'],
            'variants' => $result['variants'],
            'output' => $generation->output,
        ]);
    }

    public function saveImage(SaveAiImageRequest $request, AiGeneration $generation, AiContentService $ai): JsonResponse
    {
        Gate::authorize('saveMedia', $generation);

        try {
            $asset = $ai->saveImageToMedia(
                $request->user(),
                $request->user()->currentWorkspace,
                $generation,
                $request->validated('name'),
                $request->validated('variant_index'),
            );
        } catch (AiException $e) {
            return $this->aiError($e);
        }

        return response()->json([
            'media' => [
                'id' => $asset->id,
                'name' => $asset->name,
                'type' => $asset->type->value,
                'url' => $asset->publicUrl(),
                'width' => $asset->width,
                'height' => $asset->height,
            ],
            'generation_id' => $generation->id,
        ]);
    }

    public function generateDesign(GenerateAiDesignRequest $request, AiContentService $ai): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', AiGeneration::class);

        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace && ($request->user()->roleIn($workspace)?->canManageScreenDesigns() ?? false), 403);

        $variantCount = (int) ($request->validated('variant_count')
            ?? $request->validated('concept_count')
            ?? 1);

        try {
            $result = $ai->generateDesign(
                $request->user(),
                $workspace,
                (string) $request->validated('prompt'),
                [
                    'orientation' => (string) $request->validated('orientation'),
                    'purpose' => (string) $request->validated('purpose'),
                    'style' => $request->validated('style') ?? 'professional',
                    'name' => $request->validated('name'),
                    'brand_colors' => $request->validated('brand_colors'),
                    'use_brand_kit' => $request->boolean('use_brand_kit', true),
                    'generate_matching_image' => $request->boolean('generate_matching_image', false),
                    'variant_count' => $variantCount,
                    'idempotency_key' => $request->validated('idempotency_key'),
                ],
            );
        } catch (AiException $e) {
            if ($request->expectsJson()) {
                return $this->aiError($e);
            }

            return back()->with('error', $e->getMessage());
        }

        if (isset($result['concepts'])) {
            return response()->json([
                'generation_id' => $result['generation']->id,
                'concepts' => $result['concepts'],
                'status' => $result['generation']->status->value,
            ]);
        }

        if (! isset($result['design'])) {
            $missing = AiException::invalidOutput('AI design generation did not return a screen design.');

            if ($request->expectsJson()) {
                return $this->aiError($missing);
            }

            return back()->with('error', $missing->getMessage());
        }

        $design = $result['design'];

        if ($request->expectsJson()) {
            return response()->json([
                'generation_id' => $result['generation']->id,
                'screen_design_id' => $design->id,
                'edit_url' => route('app.screen_designs.edit', $design),
            ]);
        }

        return redirect()
            ->route('app.screen_designs.edit', $design)
            ->with('success', 'AI draft created. Everything is editable.');
    }

    public function acceptDesign(AcceptAiDesignConceptRequest $request, AiContentService $ai): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', AiGeneration::class);

        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace && ($request->user()->roleIn($workspace)?->canManageScreenDesigns() ?? false), 403);

        $generation = AiGeneration::query()->findOrFail((int) $request->validated('generation_id'));
        Gate::authorize('view', $generation);

        try {
            $result = $ai->acceptDesignConcept(
                $request->user(),
                $workspace,
                $generation,
                (int) $request->validated('concept_index'),
                $request->validated('name'),
            );
        } catch (AiException $e) {
            if ($request->expectsJson()) {
                return $this->aiError($e);
            }

            return back()->with('error', $e->getMessage());
        }

        $design = $result['design'];

        if ($request->expectsJson()) {
            return response()->json([
                'generation_id' => $result['generation']->id,
                'screen_design_id' => $design->id,
                'edit_url' => route('app.screen_designs.edit', $design),
            ]);
        }

        return redirect()
            ->route('app.screen_designs.edit', $design)
            ->with('success', 'AI draft created. Everything is editable.');
    }

    public function preview(Request $request, AiGeneration $generation): Response
    {
        Gate::authorize('view', $generation);

        $disk = (string) $generation->temp_disk;
        $path = (string) $generation->temp_path;
        $mime = (string) ($generation->output['mime_type'] ?? 'image/png');

        $variant = $request->query('variant');
        if ($variant !== null && is_array($generation->output['variants'] ?? null)) {
            $index = (int) $variant;
            foreach ($generation->output['variants'] as $item) {
                if ((int) ($item['index'] ?? -1) === $index && ! empty($item['path'])) {
                    $path = (string) $item['path'];
                    $mime = (string) ($item['mime_type'] ?? $mime);
                    break;
                }
            }
        }

        if ($disk === '' || $path === '' || ! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return response(Storage::disk($disk)->get($path), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function aiError(AiException $e): JsonResponse
    {
        $status = match ($e->codeKey) {
            'rate_limited' => 429,
            'unavailable' => 503,
            'refused' => 422,
            default => 422,
        };

        return response()->json([
            'message' => $e->getMessage(),
            'code' => $e->codeKey,
        ], $status);
    }
}

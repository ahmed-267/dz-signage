import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { aiRequest } from '@/lib/ai-client';
import { cn } from '@/lib/utils';
import aiRoutes from '@/routes/app/ai';

const PURPOSES = [
    'promotion',
    'menu',
    'announcement',
    'welcome',
    'event',
    'information',
] as const;

const STYLES = [
    'minimal',
    'professional',
    'bold',
    'elegant',
    'vibrant',
] as const;

type Concept = {
    index: number;
    id: string;
    name: string;
    archetype: string;
    purpose: string;
    style: string;
    orientation: string;
    suggested_name: string | null;
    element_count: number;
    preview: {
        background?: { type?: string; value?: string } | null;
        headline?: string;
        cta?: string;
        element_types?: string[];
    } | null;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    available: boolean;
    unavailableMessage?: string | null;
};

export default function AiDesignDialog({
    open,
    onOpenChange,
    available,
    unavailableMessage,
}: Props) {
    const { brandKit } = usePage().props;
    const [step, setStep] = useState<'form' | 'concepts'>('form');
    const [prompt, setPrompt] = useState('');
    const [name, setName] = useState('');
    const [orientation, setOrientation] = useState<'landscape' | 'portrait'>(
        'landscape',
    );
    const [purpose, setPurpose] = useState<string>('promotion');
    const [style, setStyle] = useState<string>('professional');
    const [useBrandKit, setUseBrandKit] = useState(true);
    const [generateMatchingImage, setGenerateMatchingImage] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [generationId, setGenerationId] = useState<number | null>(null);
    const [concepts, setConcepts] = useState<Concept[]>([]);

    const reset = () => {
        setStep('form');
        setPrompt('');
        setName('');
        setOrientation('landscape');
        setPurpose('promotion');
        setStyle('professional');
        setUseBrandKit(true);
        setGenerateMatchingImage(false);
        setBusy(false);
        setError(null);
        setGenerationId(null);
        setConcepts([]);
    };

    const generate = async () => {
        if (!available || busy) {
            return;
        }

        setBusy(true);
        setError(null);

        try {
            const brandColors =
                useBrandKit && brandKit?.colors
                    ? brandKit.colors.filter((color) =>
                          /^#[0-9A-Fa-f]{6}$/.test(color),
                      )
                    : undefined;

            const result = await aiRequest<{
                generation_id: number;
                concepts?: Concept[];
                edit_url?: string;
            }>(aiRoutes.design.url(), 'POST', {
                prompt: prompt.trim(),
                orientation,
                purpose,
                style,
                name: name.trim() || undefined,
                use_brand_kit: useBrandKit,
                generate_matching_image: generateMatchingImage,
                variant_count: 3,
                ...(brandColors && brandColors.length > 0
                    ? { brand_colors: brandColors }
                    : {}),
            });

            if (result.concepts && result.concepts.length > 0) {
                setGenerationId(result.generation_id);
                setConcepts(result.concepts);
                setStep('concepts');
                setBusy(false);
                return;
            }

            if (result.edit_url) {
                onOpenChange(false);
                reset();
                router.visit(result.edit_url);
                return;
            }

            setError('No design concepts were returned.');
            setBusy(false);
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Generation failed.');
            setBusy(false);
        }
    };

    const accept = async (concept: Concept) => {
        if (!generationId || busy) {
            return;
        }

        setBusy(true);
        setError(null);

        try {
            const result = await aiRequest<{ edit_url: string }>(
                aiRoutes.design.accept.url(),
                'POST',
                {
                    generation_id: generationId,
                    concept_index: concept.index,
                    name: name.trim() || concept.suggested_name || undefined,
                },
            );

            onOpenChange(false);
            reset();
            router.visit(result.edit_url);
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Could not open design.');
            setBusy(false);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    reset();
                }
                onOpenChange(next);
            }}
        >
            <DialogContent className="sm:max-w-lg" data-test="ai-design-dialog">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Sparkles className="text-primary size-4" />
                        Create with AI
                    </DialogTitle>
                    <DialogDescription>
                        {step === 'concepts'
                            ? 'Pick a concept. We will open an editable Screen draft.'
                            : 'Describe the screen. AI prefers your existing Media, published Templates, and Brand Kit before generating new images. Nothing is published until you choose to publish.'}
                    </DialogDescription>
                </DialogHeader>

                {!available ? (
                    <p
                        className="text-muted-foreground text-sm"
                        data-test="ai-unavailable"
                    >
                        {unavailableMessage ??
                            'AI generation is currently unavailable.'}
                    </p>
                ) : step === 'concepts' ? (
                    <div className="space-y-3" data-test="ai-design-concepts">
                        {concepts.map((concept) => (
                            <button
                                key={concept.id}
                                type="button"
                                data-test={`ai-design-concept-${concept.index}`}
                                disabled={busy}
                                onClick={() => accept(concept)}
                                className="border-border hover:border-primary/50 w-full rounded-lg border p-3 text-left transition-colors"
                                style={{
                                    background:
                                        concept.preview?.background?.value ??
                                        undefined,
                                }}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-medium">
                                            {concept.name}
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            {concept.preview?.headline ||
                                                concept.suggested_name ||
                                                'Layout concept'}
                                        </p>
                                        {concept.preview?.cta ? (
                                            <p className="text-primary mt-2 text-xs font-medium">
                                                {concept.preview.cta}
                                            </p>
                                        ) : null}
                                    </div>
                                    <span className="text-muted-foreground text-[10px] tracking-wide uppercase">
                                        {concept.element_count} elements
                                    </span>
                                </div>
                            </button>
                        ))}
                    </div>
                ) : (
                    <div className="space-y-4">
                        <div className="space-y-1.5">
                            <Label>Purpose</Label>
                            <div className="flex flex-wrap gap-2">
                                {PURPOSES.map((item) => (
                                    <button
                                        key={item}
                                        type="button"
                                        data-test={`ai-design-purpose-${item}`}
                                        disabled={busy}
                                        onClick={() => setPurpose(item)}
                                        className={cn(
                                            'rounded-full border px-3 py-1 text-xs capitalize',
                                            purpose === item
                                                ? 'border-primary bg-primary/10 text-primary'
                                                : 'border-border text-muted-foreground',
                                        )}
                                    >
                                        {item}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="ai-design-prompt">Prompt</Label>
                            <textarea
                                id="ai-design-prompt"
                                data-test="ai-design-prompt"
                                className="border-input focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 w-full rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
                                value={prompt}
                                onChange={(e) => setPrompt(e.target.value)}
                                disabled={busy}
                                maxLength={2000}
                                placeholder="Landscape summer sale for a clothing store with a large 40% OFF heading, product image area and Shop Now CTA"
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label>Orientation</Label>
                            <div className="flex gap-2">
                                {(['landscape', 'portrait'] as const).map(
                                    (item) => (
                                        <button
                                            key={item}
                                            type="button"
                                            data-test={`ai-design-orientation-${item}`}
                                            disabled={busy}
                                            onClick={() => setOrientation(item)}
                                            className={cn(
                                                'rounded-full border px-3 py-1 text-xs capitalize',
                                                orientation === item
                                                    ? 'border-primary bg-primary/10 text-primary'
                                                    : 'border-border text-muted-foreground',
                                            )}
                                        >
                                            {item}
                                        </button>
                                    ),
                                )}
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label>Style</Label>
                            <div className="flex flex-wrap gap-2">
                                {STYLES.map((item) => (
                                    <button
                                        key={item}
                                        type="button"
                                        data-test={`ai-design-style-${item}`}
                                        disabled={busy}
                                        onClick={() => setStyle(item)}
                                        className={cn(
                                            'rounded-full border px-3 py-1 text-xs capitalize',
                                            style === item
                                                ? 'border-primary bg-primary/10 text-primary'
                                                : 'border-border text-muted-foreground',
                                        )}
                                    >
                                        {item}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    data-test="ai-design-use-brand-kit"
                                    checked={useBrandKit}
                                    disabled={busy || !brandKit}
                                    onChange={(e) =>
                                        setUseBrandKit(e.target.checked)
                                    }
                                />
                                Use Brand Kit
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    data-test="ai-design-matching-image"
                                    checked={generateMatchingImage}
                                    disabled={busy}
                                    onChange={(e) =>
                                        setGenerateMatchingImage(
                                            e.target.checked,
                                        )
                                    }
                                />
                                Prefer existing Media; generate only if none
                                match
                            </label>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="ai-design-name">
                                Name (optional)
                            </Label>
                            <Input
                                id="ai-design-name"
                                data-test="ai-design-name"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                disabled={busy}
                            />
                        </div>
                    </div>
                )}

                {error ? (
                    <p
                        className="text-destructive text-sm"
                        role="alert"
                        data-test="ai-design-error"
                    >
                        {error}
                    </p>
                ) : null}

                {busy ? (
                    <p
                        className="text-muted-foreground flex items-center gap-2 text-sm"
                        aria-live="polite"
                        data-test="ai-design-loading"
                    >
                        <Spinner />
                        {step === 'concepts'
                            ? 'Opening editor…'
                            : 'Creating concepts…'}
                    </p>
                ) : null}

                <DialogFooter className="gap-2">
                    {step === 'concepts' ? (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={busy}
                            onClick={() => setStep('form')}
                        >
                            Back
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={busy}
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                    )}
                    {step === 'form' ? (
                        <Button
                            type="button"
                            data-test="ai-design-generate"
                            disabled={!available || busy || !prompt.trim()}
                            onClick={generate}
                        >
                            <Sparkles className="size-4" />
                            Generate
                        </Button>
                    ) : null}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

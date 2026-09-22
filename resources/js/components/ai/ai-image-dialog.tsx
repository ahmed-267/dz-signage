import { useState } from 'react';
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

const ASPECTS = [
    { id: 'landscape', label: 'Landscape' },
    { id: 'portrait', label: 'Portrait' },
    { id: 'square', label: 'Square' },
] as const;

const STYLES = [
    'professional',
    'minimal',
    'vibrant',
    'elegant',
    'photographic',
    'illustration',
] as const;

const REFINEMENTS = [
    { id: '', label: 'None' },
    { id: 'more_minimal', label: 'More minimal' },
    { id: 'brighter', label: 'Brighter' },
    { id: 'darker', label: 'Darker' },
    { id: 'more_contrast', label: 'More contrast' },
    { id: 'warmer', label: 'Warmer' },
    { id: 'cooler', label: 'Cooler' },
] as const;

type GeneratedMedia = {
    id: number;
    name: string;
    type: string;
    url: string | null;
    width: number | null;
    height: number | null;
};

type ImageVariant = {
    index: number;
    preview_url: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    available: boolean;
    unavailableMessage?: string | null;
    /** Suggested aspect from an editor image element. */
    defaultAspect?: 'landscape' | 'portrait' | 'square';
    onSaved?: (media: GeneratedMedia) => void;
};

export default function AiImageDialog({
    open,
    onOpenChange,
    available,
    unavailableMessage,
    defaultAspect = 'landscape',
    onSaved,
}: Props) {
    const [prompt, setPrompt] = useState('');
    const [aspect, setAspect] = useState(defaultAspect);
    const [style, setStyle] = useState<string>('professional');
    const [refinement, setRefinement] = useState<string>('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [generationId, setGenerationId] = useState<number | null>(null);
    const [variants, setVariants] = useState<ImageVariant[]>([]);
    const [selectedVariant, setSelectedVariant] = useState(0);
    const [name, setName] = useState('');

    const reset = () => {
        setPrompt('');
        setAspect(defaultAspect);
        setStyle('professional');
        setRefinement('');
        setBusy(false);
        setError(null);
        setGenerationId(null);
        setVariants([]);
        setSelectedVariant(0);
        setName('');
    };

    const generate = async (nextRefinement?: string) => {
        if (!available || busy) {
            return;
        }

        setBusy(true);
        setError(null);

        const activeRefinement =
            nextRefinement !== undefined ? nextRefinement : refinement;

        try {
            const result = await aiRequest<{
                generation_id: number;
                preview_url: string;
                variants?: ImageVariant[];
            }>(aiRoutes.image.url(), 'POST', {
                prompt: prompt.trim(),
                aspect,
                style,
                variants: 2,
                ...(activeRefinement ? { refinement: activeRefinement } : {}),
            });

            setGenerationId(result.generation_id);
            const nextVariants =
                result.variants && result.variants.length > 0
                    ? result.variants
                    : [{ index: 0, preview_url: result.preview_url }];
            setVariants(nextVariants);
            setSelectedVariant(0);
            if (!name.trim()) {
                setName(`AI · ${prompt.trim().slice(0, 40)}`);
            }
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Generation failed.');
        } finally {
            setBusy(false);
        }
    };

    const save = async () => {
        if (!generationId || busy) {
            return;
        }

        setBusy(true);
        setError(null);

        try {
            const result = await aiRequest<{ media: GeneratedMedia }>(
                aiRoutes.image.save.url(generationId),
                'POST',
                {
                    name: name.trim() || undefined,
                    variant_index: selectedVariant,
                },
            );
            onSaved?.(result.media);
            onOpenChange(false);
            reset();
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Could not save image.');
        } finally {
            setBusy(false);
        }
    };

    const selectedPreview =
        variants.find((v) => v.index === selectedVariant)?.preview_url ??
        variants[0]?.preview_url ??
        null;

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
            <DialogContent className="sm:max-w-lg" data-test="ai-image-dialog">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Sparkles className="text-primary size-4" />
                        Generate with AI
                    </DialogTitle>
                    <DialogDescription>
                        Describe the image. Compare variants, refine, then save
                        one to Media.
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
                ) : (
                    <div className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="ai-image-prompt">
                                Describe your image
                            </Label>
                            <textarea
                                id="ai-image-prompt"
                                data-test="ai-image-prompt"
                                className="border-input focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 w-full rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
                                value={prompt}
                                onChange={(e) => setPrompt(e.target.value)}
                                maxLength={2000}
                                placeholder="A premium coffee promotion with a dark elegant background and warm café lighting"
                                disabled={busy}
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label>Format</Label>
                            <div className="flex flex-wrap gap-2">
                                {ASPECTS.map((item) => (
                                    <button
                                        key={item.id}
                                        type="button"
                                        data-test={`ai-image-aspect-${item.id}`}
                                        disabled={busy}
                                        onClick={() => setAspect(item.id)}
                                        className={cn(
                                            'rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                                            aspect === item.id
                                                ? 'border-primary bg-primary/10 text-primary'
                                                : 'border-border text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        {item.label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label>Style</Label>
                            <div className="flex flex-wrap gap-2">
                                {STYLES.map((item) => (
                                    <button
                                        key={item}
                                        type="button"
                                        data-test={`ai-image-style-${item}`}
                                        disabled={busy}
                                        onClick={() => setStyle(item)}
                                        className={cn(
                                            'rounded-full border px-3 py-1 text-xs capitalize transition-colors',
                                            style === item
                                                ? 'border-primary bg-primary/10 text-primary'
                                                : 'border-border text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        {item}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {variants.length > 0 ? (
                            <div className="space-y-2">
                                <p className="text-muted-foreground font-mono text-[10px] tracking-wider uppercase">
                                    Variants
                                </p>
                                <div
                                    className="grid grid-cols-2 gap-2"
                                    data-test="ai-image-variants"
                                >
                                    {variants.map((variant) => (
                                        <button
                                            key={variant.index}
                                            type="button"
                                            data-test={`ai-image-variant-${variant.index}`}
                                            disabled={busy}
                                            onClick={() =>
                                                setSelectedVariant(
                                                    variant.index,
                                                )
                                            }
                                            className={cn(
                                                'overflow-hidden rounded-lg border',
                                                selectedVariant ===
                                                    variant.index
                                                    ? 'border-primary ring-primary/30 ring-2'
                                                    : 'border-border',
                                            )}
                                        >
                                            <img
                                                src={variant.preview_url}
                                                alt={`AI variant ${variant.index + 1}`}
                                                className="bg-muted max-h-40 w-full object-contain"
                                            />
                                        </button>
                                    ))}
                                </div>

                                <div className="space-y-1.5">
                                    <Label>Refine & regenerate</Label>
                                    <div className="flex flex-wrap gap-2">
                                        {REFINEMENTS.filter(
                                            (r) => r.id !== '',
                                        ).map((item) => (
                                            <button
                                                key={item.id}
                                                type="button"
                                                data-test={`ai-image-refine-${item.id}`}
                                                disabled={busy}
                                                onClick={() => {
                                                    setRefinement(item.id);
                                                    void generate(item.id);
                                                }}
                                                className={cn(
                                                    'rounded-full border px-3 py-1 text-xs transition-colors',
                                                    refinement === item.id
                                                        ? 'border-primary bg-primary/10 text-primary'
                                                        : 'border-border text-muted-foreground',
                                                )}
                                            >
                                                {item.label}
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                <div className="space-y-1">
                                    <Label htmlFor="ai-image-name">
                                        Media name
                                    </Label>
                                    <Input
                                        id="ai-image-name"
                                        data-test="ai-image-name"
                                        value={name}
                                        onChange={(e) =>
                                            setName(e.target.value)
                                        }
                                        disabled={busy}
                                    />
                                </div>
                            </div>
                        ) : null}

                        {error ? (
                            <p
                                className="text-destructive text-sm"
                                data-test="ai-image-error"
                                role="alert"
                            >
                                {error}
                            </p>
                        ) : null}

                        {busy ? (
                            <p
                                className="text-muted-foreground flex items-center gap-2 text-sm"
                                data-test="ai-image-loading"
                                aria-live="polite"
                            >
                                <Spinner />
                                Generating image…
                            </p>
                        ) : null}
                    </div>
                )}

                <DialogFooter className="gap-2 sm:gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={busy}
                    >
                        Cancel
                    </Button>
                    {selectedPreview ? (
                        <>
                            <Button
                                type="button"
                                variant="secondary"
                                data-test="ai-image-regenerate"
                                disabled={!available || busy || !prompt.trim()}
                                onClick={() => generate()}
                            >
                                Regenerate
                            </Button>
                            <Button
                                type="button"
                                data-test="ai-image-save"
                                disabled={!available || busy || !generationId}
                                onClick={save}
                            >
                                Save to Media
                            </Button>
                        </>
                    ) : (
                        <Button
                            type="button"
                            data-test="ai-image-generate"
                            disabled={!available || busy || !prompt.trim()}
                            onClick={() => generate()}
                        >
                            <Sparkles className="size-4" />
                            Generate
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

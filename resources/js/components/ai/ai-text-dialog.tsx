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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { aiRequest } from '@/lib/ai-client';
import { cn } from '@/lib/utils';
import aiRoutes from '@/routes/app/ai';

const MODES = [
    'headline',
    'promotion',
    'cta',
    'announcement',
    'event',
    'menu_product',
    'welcome',
    'information',
] as const;

const TONES = [
    'professional',
    'friendly',
    'bold',
    'minimal',
    'elegant',
] as const;

const LENGTHS = ['short', 'medium', 'detailed'] as const;

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    available: boolean;
    unavailableMessage?: string | null;
    mode?: 'write' | 'rewrite';
    initialText?: string;
    onInsert: (text: string) => void;
};

export default function AiTextDialog({
    open,
    onOpenChange,
    available,
    unavailableMessage,
    mode = 'write',
    initialText = '',
    onInsert,
}: Props) {
    const [prompt, setPrompt] = useState(initialText);
    const [textMode, setTextMode] = useState<string>('headline');
    const [tone, setTone] = useState<string>('professional');
    const [length, setLength] = useState<string>('short');
    const [action, setAction] = useState<string>('shorten');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [texts, setTexts] = useState<string[]>([]);
    const [selected, setSelected] = useState(0);

    const rewriteActions = [
        { id: 'shorten', label: 'Shorten' },
        { id: 'expand', label: 'Expand' },
        { id: 'make_professional', label: 'Professional' },
        { id: 'make_friendlier', label: 'Friendlier' },
        { id: 'make_promotional', label: 'Promotional' },
        { id: 'simplify', label: 'Simplify' },
        { id: 'fix_grammar', label: 'Fix grammar' },
    ];

    const reset = () => {
        setPrompt(mode === 'rewrite' ? initialText : '');
        setBusy(false);
        setError(null);
        setTexts([]);
        setSelected(0);
    };

    const generate = async () => {
        if (!available || busy) {
            return;
        }

        setBusy(true);
        setError(null);

        try {
            if (mode === 'rewrite') {
                const data = await aiRequest<{
                    text: string;
                    texts?: string[];
                }>(aiRoutes.text.rewrite.url(), 'POST', {
                    text: (prompt || initialText).trim(),
                    action,
                    variants: 3,
                });
                const options =
                    data.texts && data.texts.length > 0
                        ? data.texts
                        : [data.text];
                setTexts(options);
                setSelected(0);
            } else {
                const data = await aiRequest<{
                    text: string;
                    texts?: string[];
                }>(aiRoutes.text.url(), 'POST', {
                    prompt: prompt.trim(),
                    mode: textMode,
                    tone,
                    length,
                    variants: 3,
                });
                const options =
                    data.texts && data.texts.length > 0
                        ? data.texts
                        : [data.text];
                setTexts(options);
                setSelected(0);
            }
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Generation failed.');
        } finally {
            setBusy(false);
        }
    };

    const result = texts[selected] ?? null;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    reset();
                } else if (mode === 'rewrite') {
                    setPrompt(initialText);
                }
                onOpenChange(next);
            }}
        >
            <DialogContent className="sm:max-w-md" data-test="ai-text-dialog">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Sparkles className="text-primary size-4" />
                        {mode === 'rewrite'
                            ? 'Rewrite with AI'
                            : 'Write with AI'}
                    </DialogTitle>
                    <DialogDescription>
                        {mode === 'rewrite'
                            ? 'Preview rewrite options, then replace the selected text.'
                            : 'Generate concise signage copy options, then insert one as editable text.'}
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
                            <Label htmlFor="ai-text-prompt">
                                {mode === 'rewrite' ? 'Current text' : 'Prompt'}
                            </Label>
                            <textarea
                                id="ai-text-prompt"
                                data-test="ai-text-prompt"
                                className="border-input focus-visible:border-ring focus-visible:ring-ring/50 min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
                                value={prompt}
                                onChange={(e) => setPrompt(e.target.value)}
                                disabled={busy}
                                maxLength={2000}
                            />
                        </div>

                        {mode === 'write' ? (
                            <>
                                <ChipRow
                                    label="Mode"
                                    values={MODES}
                                    value={textMode}
                                    onChange={setTextMode}
                                    disabled={busy}
                                    testPrefix="ai-text-mode"
                                />
                                <ChipRow
                                    label="Tone"
                                    values={TONES}
                                    value={tone}
                                    onChange={setTone}
                                    disabled={busy}
                                    testPrefix="ai-text-tone"
                                />
                                <ChipRow
                                    label="Length"
                                    values={LENGTHS}
                                    value={length}
                                    onChange={setLength}
                                    disabled={busy}
                                    testPrefix="ai-text-length"
                                />
                            </>
                        ) : (
                            <div className="flex flex-wrap gap-2">
                                {rewriteActions.map((item) => (
                                    <button
                                        key={item.id}
                                        type="button"
                                        data-test={`ai-rewrite-action-${item.id}`}
                                        disabled={busy}
                                        onClick={() => setAction(item.id)}
                                        className={cn(
                                            'rounded-full border px-3 py-1 text-xs transition-colors',
                                            action === item.id
                                                ? 'border-primary bg-primary/10 text-primary'
                                                : 'border-border text-muted-foreground',
                                        )}
                                    >
                                        {item.label}
                                    </button>
                                ))}
                            </div>
                        )}

                        {texts.length > 0 ? (
                            <div
                                className="space-y-2"
                                data-test="ai-text-options"
                            >
                                <p className="text-muted-foreground font-mono text-[10px] tracking-wider uppercase">
                                    Choose an option
                                </p>
                                {texts.map((option, index) => (
                                    <button
                                        key={`${index}-${option.slice(0, 12)}`}
                                        type="button"
                                        data-test={`ai-text-option-${index}`}
                                        disabled={busy}
                                        onClick={() => setSelected(index)}
                                        className={cn(
                                            'w-full rounded-lg border p-3 text-left text-sm whitespace-pre-wrap transition-colors',
                                            selected === index
                                                ? 'border-primary bg-primary/10'
                                                : 'border-border bg-muted/40',
                                        )}
                                    >
                                        {option}
                                    </button>
                                ))}
                            </div>
                        ) : null}

                        {error ? (
                            <p
                                className="text-destructive text-sm"
                                role="alert"
                                data-test="ai-text-error"
                            >
                                {error}
                            </p>
                        ) : null}

                        {busy ? (
                            <p
                                className="text-muted-foreground flex items-center gap-2 text-sm"
                                aria-live="polite"
                            >
                                <Spinner />
                                {mode === 'rewrite'
                                    ? 'Rewriting…'
                                    : 'Writing copy…'}
                            </p>
                        ) : null}
                    </div>
                )}

                <DialogFooter className="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={busy}
                    >
                        Cancel
                    </Button>
                    {result ? (
                        <Button
                            type="button"
                            data-test="ai-text-insert"
                            disabled={busy}
                            onClick={() => {
                                onInsert(result);
                                onOpenChange(false);
                                reset();
                            }}
                        >
                            {mode === 'rewrite' ? 'Replace' : 'Insert'}
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            data-test="ai-text-generate"
                            disabled={
                                !available ||
                                busy ||
                                !(prompt.trim() || initialText.trim())
                            }
                            onClick={generate}
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

function ChipRow({
    label,
    values,
    value,
    onChange,
    disabled,
    testPrefix,
}: {
    label: string;
    values: readonly string[];
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    testPrefix: string;
}) {
    return (
        <div className="space-y-1.5">
            <Label>{label}</Label>
            <div className="flex flex-wrap gap-2">
                {values.map((item) => (
                    <button
                        key={item}
                        type="button"
                        data-test={`${testPrefix}-${item}`}
                        disabled={disabled}
                        onClick={() => onChange(item)}
                        className={cn(
                            'rounded-full border px-3 py-1 text-xs capitalize transition-colors',
                            value === item
                                ? 'border-primary bg-primary/10 text-primary'
                                : 'border-border text-muted-foreground',
                        )}
                    >
                        {item.replaceAll('_', ' ')}
                    </button>
                ))}
            </div>
        </div>
    );
}

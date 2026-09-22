import { useState, type ReactNode } from 'react';
import { router, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    LayoutTemplate,
    ListVideo,
    Monitor,
    Sparkles,
} from 'lucide-react';
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

type AgentIntent = 'design' | 'playlist' | 'schedule' | 'compound';

type ProposalStep = {
    type: string;
    index: number;
    name?: string;
    purpose?: string;
    orientation?: string;
    screen_names?: string[];
    start_time?: string;
    end_time?: string;
    days_of_week?: number[];
    duration_seconds?: number;
    loop_count?: number;
    publish_playlist?: boolean;
    status?: string;
};

type Proposal = {
    proposal_id: number;
    intent: AgentIntent;
    summary: string;
    steps: ProposalStep[];
    resolved_screens: Array<{
        id: number;
        name: string;
        location: string | null;
    }>;
    warnings: string[];
    ambiguous_screens?: Array<{ id: number; name: string }>;
    will_activate: boolean;
    will_deploy: boolean;
};

type ConfirmResult = {
    proposal_id: number;
    intent?: string;
    designs?: Array<{
        id: number;
        name: string;
        edit_url: string;
        status: string;
    }>;
    playlists?: Array<{
        id: number;
        name: string;
        edit_url: string;
        status: string;
    }>;
    schedules?: Array<{
        id: number;
        name: string;
        edit_url: string;
        status: string;
    }>;
    activated?: boolean;
    deployed?: boolean;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    available: boolean;
    unavailableMessage?: string | null;
    preferredIntent?: AgentIntent;
    title?: string;
};

const INTENT_LABEL: Record<AgentIntent, string> = {
    design: 'Screen',
    playlist: 'Playlist',
    schedule: 'Schedule',
    compound: 'Design + Playlist + Schedule',
};

const DAY_LABELS = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

export default function AiAgentDialog({
    open,
    onOpenChange,
    available,
    unavailableMessage,
    preferredIntent,
    title = 'Ask AI',
}: Props) {
    const { brandKit } = usePage().props;
    const [step, setStep] = useState<'form' | 'review' | 'done'>('form');
    const [prompt, setPrompt] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [proposal, setProposal] = useState<Proposal | null>(null);
    const [result, setResult] = useState<ConfirmResult | null>(null);

    const reset = () => {
        setStep('form');
        setPrompt('');
        setBusy(false);
        setError(null);
        setProposal(null);
        setResult(null);
    };

    const propose = async () => {
        if (!available || busy || prompt.trim() === '') {
            return;
        }

        setBusy(true);
        setError(null);

        try {
            const data = await aiRequest<Proposal>(
                aiRoutes.agent.propose.url(),
                'POST',
                {
                    prompt: prompt.trim(),
                    preferred_intent: preferredIntent,
                    generate_matching_image: false,
                },
            );
            setProposal(data);
            setStep('review');
            setBusy(false);
        } catch (e) {
            setError(
                e instanceof Error ? e.message : 'Could not build a plan.',
            );
            setBusy(false);
        }
    };

    const confirm = async () => {
        if (!proposal || busy) {
            return;
        }

        setBusy(true);
        setError(null);

        try {
            const data = await aiRequest<ConfirmResult>(
                aiRoutes.agent.confirm.url(),
                'POST',
                {
                    proposal_id: proposal.proposal_id,
                    confirm: true,
                    activate: false,
                    deploy: false,
                },
            );
            setResult(data);
            setStep('done');
            setBusy(false);
        } catch (e) {
            setError(
                e instanceof Error ? e.message : 'Could not create drafts.',
            );
            setBusy(false);
        }
    };

    const primaryResultUrl = (): string | null => {
        if (!result) {
            return null;
        }
        if (result.schedules?.[0]?.edit_url) {
            return result.schedules[0].edit_url;
        }
        if (result.playlists?.[0]?.edit_url) {
            return result.playlists[0].edit_url;
        }
        if (result.designs?.[0]?.edit_url) {
            return result.designs[0].edit_url;
        }
        return null;
    };

    const designSteps =
        proposal?.steps.filter((s) => s.type === 'create_design') ?? [];
    const playlistSteps =
        proposal?.steps.filter((s) => s.type === 'create_playlist') ?? [];
    const scheduleSteps =
        proposal?.steps.filter((s) => s.type === 'create_schedule') ?? [];

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
            <DialogContent className="sm:max-w-lg" data-test="ai-agent-dialog">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Sparkles className="text-primary size-4" />
                        {title}
                    </DialogTitle>
                    <DialogDescription>
                        {step === 'review'
                            ? 'Review the plan. Confirm creates drafts only — nothing is activated or sent to TVs.'
                            : step === 'done'
                              ? 'Drafts are ready. Open them to edit, publish, or schedule when you are ready.'
                              : 'Describe what you want. AI reuses your Media, Templates, and Brand Kit when they fit, then proposes Designs, Playlists, and Schedules for your review.'}
                    </DialogDescription>
                </DialogHeader>

                {!available ? (
                    <p
                        className="text-muted-foreground text-sm"
                        data-test="ai-agent-unavailable"
                    >
                        {unavailableMessage ??
                            'AI generation is currently unavailable.'}
                    </p>
                ) : step === 'form' ? (
                    <div className="space-y-4">
                        {brandKit?.name ? (
                            <p className="text-muted-foreground text-xs">
                                Brand Kit “{brandKit.name}” will be applied when
                                relevant.
                            </p>
                        ) : null}
                        <div className="space-y-1.5">
                            <Label htmlFor="ai-agent-prompt">Prompt</Label>
                            <textarea
                                id="ai-agent-prompt"
                                data-test="ai-agent-prompt"
                                value={prompt}
                                onChange={(e) => setPrompt(e.target.value)}
                                disabled={busy}
                                rows={4}
                                placeholder={
                                    preferredIntent === 'playlist'
                                        ? 'Weekend promo playlist with two screens…'
                                        : preferredIntent === 'schedule'
                                          ? 'Schedule lunch menu weekdays 11am–2pm on Lobby TV…'
                                          : 'Promotion poster for spring sale, then playlist and schedule for Lobby…'
                                }
                                className={cn(
                                    'border-input placeholder:text-muted-foreground bg-transparent',
                                    'focus-visible:border-ring focus-visible:ring-ring/50',
                                    'flex w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none',
                                    'focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
                                )}
                            />
                        </div>
                    </div>
                ) : step === 'review' && proposal ? (
                    <div className="space-y-4" data-test="ai-agent-review">
                        <div className="rounded-lg border p-3">
                            <p className="text-muted-foreground text-xs tracking-wide uppercase">
                                Intent
                            </p>
                            <p className="mt-1 text-sm font-medium">
                                {INTENT_LABEL[proposal.intent] ??
                                    proposal.intent}
                            </p>
                            <p className="text-muted-foreground mt-2 text-sm">
                                {proposal.summary}
                            </p>
                        </div>

                        {designSteps.length > 0 ? (
                            <ReviewBlock
                                icon={<LayoutTemplate className="size-4" />}
                                title="Designs"
                                items={designSteps.map(
                                    (s) =>
                                        `${s.name ?? 'Design'} · ${s.purpose ?? 'promotion'} · ${s.orientation ?? 'landscape'}`,
                                )}
                            />
                        ) : null}

                        {playlistSteps.length > 0 ? (
                            <ReviewBlock
                                icon={<ListVideo className="size-4" />}
                                title="Playlist"
                                items={playlistSteps.map((s) => {
                                    const seconds = s.duration_seconds ?? 10;
                                    const loops = s.loop_count ?? 1;
                                    const state = s.publish_playlist
                                        ? 'published version for the schedule'
                                        : 'draft';
                                    return `${s.name ?? 'Playlist'} · ${seconds}s × ${loops} · ${state}`;
                                })}
                            />
                        ) : null}

                        {scheduleSteps.length > 0 ? (
                            <ReviewBlock
                                icon={<CalendarClock className="size-4" />}
                                title="Schedules"
                                items={scheduleSteps.map((s) => {
                                    const screens =
                                        s.screen_names &&
                                        s.screen_names.length > 0
                                            ? s.screen_names.join(', ')
                                            : 'no screens matched';
                                    const days = (s.days_of_week ?? [])
                                        .map(
                                            (day) =>
                                                DAY_LABELS[day] ?? String(day),
                                        )
                                        .join(', ');
                                    return `${s.name ?? 'Schedule'} · ${days || 'days not set'} · ${s.start_time ?? ''}–${s.end_time ?? ''} · ${screens} · Draft`;
                                })}
                            />
                        ) : null}

                        {proposal.resolved_screens.length > 0 ? (
                            <div className="text-muted-foreground flex items-start gap-2 text-xs">
                                <Monitor className="mt-0.5 size-3.5 shrink-0" />
                                <span>
                                    Matched screens:{' '}
                                    {proposal.resolved_screens
                                        .map((s) => s.name)
                                        .join(', ')}
                                </span>
                            </div>
                        ) : null}

                        {proposal.warnings.map((warning) => (
                            <p
                                key={warning}
                                className="text-xs text-amber-700 dark:text-amber-400"
                            >
                                {warning}
                            </p>
                        ))}
                    </div>
                ) : step === 'done' && result ? (
                    <div className="space-y-3" data-test="ai-agent-done">
                        {result.designs?.map((d) => (
                            <ResultLink
                                key={`d-${d.id}`}
                                label="Design"
                                item={d}
                            />
                        ))}
                        {result.playlists?.map((p) => (
                            <ResultLink
                                key={`p-${p.id}`}
                                label="Playlist"
                                item={p}
                            />
                        ))}
                        {result.schedules?.map((s) => (
                            <ResultLink
                                key={`s-${s.id}`}
                                label="Schedule"
                                item={s}
                            />
                        ))}
                    </div>
                ) : null}

                {error ? (
                    <p
                        className="text-destructive text-sm"
                        data-test="ai-agent-error"
                    >
                        {error}
                    </p>
                ) : null}

                <DialogFooter className="gap-2 sm:gap-0">
                    {step === 'form' ? (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={busy}
                                onClick={() => onOpenChange(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                data-test="ai-agent-propose"
                                disabled={
                                    !available || busy || prompt.trim() === ''
                                }
                                onClick={() => void propose()}
                            >
                                {busy ? <Spinner className="size-4" /> : null}
                                Propose plan
                            </Button>
                        </>
                    ) : null}
                    {step === 'review' ? (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={busy}
                                onClick={() => {
                                    setStep('form');
                                    setProposal(null);
                                }}
                            >
                                Back
                            </Button>
                            <Button
                                type="button"
                                data-test="ai-agent-confirm"
                                disabled={
                                    busy ||
                                    (proposal?.ambiguous_screens?.length ?? 0) >
                                        0
                                }
                                onClick={() => void confirm()}
                            >
                                {busy ? <Spinner className="size-4" /> : null}
                                Confirm drafts
                            </Button>
                        </>
                    ) : null}
                    {step === 'done' ? (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    onOpenChange(false);
                                    reset();
                                    router.reload();
                                }}
                            >
                                Close
                            </Button>
                            {primaryResultUrl() ? (
                                <Button
                                    type="button"
                                    data-test="ai-agent-open"
                                    onClick={() => {
                                        const url = primaryResultUrl();
                                        onOpenChange(false);
                                        reset();
                                        if (url) {
                                            router.visit(url);
                                        }
                                    }}
                                >
                                    Open
                                </Button>
                            ) : null}
                        </>
                    ) : null}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ReviewBlock({
    icon,
    title,
    items,
}: {
    icon: ReactNode;
    title: string;
    items: string[];
}) {
    return (
        <div className="rounded-lg border p-3">
            <div className="flex items-center gap-2 text-sm font-medium">
                {icon}
                {title}
            </div>
            <ul className="text-muted-foreground mt-2 space-y-1 text-sm">
                {items.map((item) => (
                    <li key={item}>{item}</li>
                ))}
            </ul>
        </div>
    );
}

function ResultLink({
    label,
    item,
}: {
    label: string;
    item: { name: string; edit_url: string; status: string };
}) {
    return (
        <button
            type="button"
            className="border-border hover:border-primary/50 flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left text-sm transition-colors"
            onClick={() => router.visit(item.edit_url)}
        >
            <span>
                <span className="text-muted-foreground">{label} · </span>
                {item.name}
            </span>
            <span className="text-muted-foreground text-xs capitalize">
                {item.status}
            </span>
        </button>
    );
}

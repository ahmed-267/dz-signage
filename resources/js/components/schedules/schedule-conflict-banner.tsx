import { AlertTriangle } from 'lucide-react';
import {
    formatTimeRange,
    describeDays,
} from '@/components/schedules/schedule-format';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import type { ScheduleConflict, ScheduleDayLabels } from '@/types/schedule';

type ScheduleConflictBannerProps = {
    conflicts: ScheduleConflict[];
    shortLabels: ScheduleDayLabels;
    /** Priority of the schedule being edited, for the "wins" hint. */
    priority?: number;
};

/**
 * Overlaps never block a save — priority exists so two schedules may cover the
 * same moment. `other_wins` comes from `Schedule::outranks()`, so the winner
 * shown here is the one the evaluator will actually pick.
 */
export function ScheduleConflictBanner({
    conflicts,
    shortLabels,
    priority,
}: ScheduleConflictBannerProps) {
    if (conflicts.length === 0) {
        return null;
    }

    const hasPriorityConflict = conflicts.some(
        (conflict) => conflict.same_priority,
    );

    return (
        <Alert
            className="border-warning/40 bg-warning/10"
            data-test="schedule-conflict-banner"
            data-conflict-count={conflicts.length}
            data-priority-conflict={hasPriorityConflict ? 'true' : 'false'}
        >
            <AlertTriangle className="text-warning" />
            <AlertTitle>
                {hasPriorityConflict
                    ? 'Priority conflict'
                    : 'Overlapping schedule'}
            </AlertTitle>
            <AlertDescription>
                <p>
                    {hasPriorityConflict
                        ? 'This schedule overlaps another active schedule at the same priority. Raise priority to take precedence, or activation time will decide the winner.'
                        : 'This schedule overlaps another active schedule. Higher priority content will play.'}
                </p>
                <ul className="mt-1 w-full space-y-1.5">
                    {conflicts.map((conflict) => (
                        <li
                            key={conflict.id}
                            data-test={`schedule-conflict-${conflict.id}`}
                            className="flex flex-wrap items-center gap-x-2 gap-y-1"
                        >
                            <span className="text-foreground font-medium">
                                {conflict.name}
                            </span>
                            <Badge
                                variant={
                                    conflict.same_priority
                                        ? 'warning'
                                        : 'neutral'
                                }
                            >
                                Priority {conflict.priority}
                                {conflict.same_priority ? ' · same' : ''}
                            </Badge>
                            <span className="font-mono text-xs">
                                {formatTimeRange(
                                    conflict.start_time,
                                    conflict.end_time,
                                )}
                            </span>
                            <span className="text-xs">
                                {describeDays(
                                    conflict.days_of_week,
                                    shortLabels,
                                )}
                            </span>
                            <span className="text-xs">
                                on {conflict.screen_names.join(', ')}
                            </span>
                            <Badge
                                variant={
                                    conflict.other_wins ? 'warning' : 'success'
                                }
                            >
                                {conflict.other_wins
                                    ? `“${conflict.name}” wins`
                                    : 'This schedule wins'}
                            </Badge>
                        </li>
                    ))}
                </ul>
                {priority != null ? (
                    <p className="text-xs">
                        This schedule has priority {priority}. Raise it to take
                        precedence.
                    </p>
                ) : null}
            </AlertDescription>
        </Alert>
    );
}

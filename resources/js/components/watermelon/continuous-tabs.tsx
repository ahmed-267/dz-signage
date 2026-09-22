import { useEffect, useState, type FC } from 'react';
import { motion, LayoutGroup } from 'motion/react';

/* ---------- Types ---------- */
interface TabItem {
    id: string;
    label: string;
}

interface ContinuousTabsProps {
    tabs?: TabItem[];
    defaultActiveId?: string;
    onChange?: (id: string) => void;
    /** Accessible name for the tab list. */
    label?: string;
    /** `id` of the panel these tabs control, for `aria-controls`. */
    panelId?: string;
}

/* ---------- Defaults ---------- */
const DEFAULT_TABS: TabItem[] = [
    { id: 'home', label: 'Home' },
    { id: 'interactions', label: 'Interactions' },
    { id: 'resources', label: 'Resources' },
    { id: 'docs', label: 'Docs' },
];

export const ContinuousTabs: FC<ContinuousTabsProps> = ({
    tabs = DEFAULT_TABS,
    defaultActiveId = 'home',
    onChange,
    label = 'Tabs',
    panelId,
}) => {
    const [active, setActive] = useState<string>(defaultActiveId);
    const [isMounted, setIsMounted] = useState<boolean>(false);

    useEffect(() => {
        requestAnimationFrame(() => setIsMounted(true));
    }, []);

    const handleChange = (id: string) => {
        setActive(id);
        onChange?.(id);
    };

    if (!isMounted) return null;

    return (
        <LayoutGroup>
            <div
                role="tablist"
                aria-label={label}
                aria-orientation="horizontal"
                className="border-border/70 bg-card relative flex items-center gap-0.5 rounded-full border p-1 shadow-sm sm:gap-1 sm:p-1.5"
            >
                {tabs.map((tab) => {
                    const isActive = active === tab.id;

                    return (
                        <button
                            key={tab.id}
                            type="button"
                            role="tab"
                            aria-selected={isActive}
                            aria-controls={panelId}
                            onClick={() => handleChange(tab.id)}
                            className="focus-visible:ring-ring relative rounded-full px-3 py-2 whitespace-nowrap outline-none focus-visible:ring-2 sm:px-4"
                        >
                            {/* Active pill */}
                            {isActive && (
                                <motion.span
                                    aria-hidden
                                    layoutId="active-pill"
                                    transition={{
                                        type: 'spring',
                                        stiffness: 380,
                                        damping: 30,
                                        mass: 0.9,
                                    }}
                                    className="bg-primary absolute inset-0 rounded-full shadow-sm"
                                />
                            )}

                            {/* Text */}
                            <motion.span
                                layout="position"
                                className={`relative z-10 text-sm font-semibold transition-colors duration-200 ${
                                    isActive
                                        ? 'text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {tab.label}
                            </motion.span>
                        </button>
                    );
                })}
            </div>
        </LayoutGroup>
    );
};

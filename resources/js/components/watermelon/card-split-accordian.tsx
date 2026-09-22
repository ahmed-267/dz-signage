import React, { useState, type FC } from 'react';
import { motion, MotionConfig, type Transition } from 'motion/react';
import { ChevronDown, HelpCircle } from 'lucide-react';
import useMeasure from 'react-use-measure';
import { cn } from '@/lib/utils';

export interface AccordionItemData {
    id: number;
    title: string;
    icon: React.ReactNode;
    content: React.ReactNode;
}

interface AccordionItemProps {
    item: AccordionItemData;

    setOpenId: (id: number | null) => void;
    index: number;
    total: number;
    openIndex: number;
}
interface AccordionProps {
    items?: AccordionItemData[];
    className?: string;
    listClassName?: string;
    /** Collapse animation to a static open/close when reduced motion is set. */
    reducedMotion?: boolean;
}

const springTransition: Transition = {
    type: 'spring',
    stiffness: 600,
    damping: 50,
    mass: 1,
};

const DEFAULT_ITEMS: AccordionItemData[] = [
    {
        id: 1,
        title: 'What is this?',
        icon: <HelpCircle className="size-4" />,
        content: 'Pass an `items` array to replace this placeholder content.',
    },
];

const AccordionItem: FC<AccordionItemProps> = ({
    item,
    setOpenId,
    index,
    total,
    openIndex,
}) => {
    const [ref, bounds] = useMeasure();
    const isOpen = index === openIndex;

    const isFirst = index === 0;
    const isLast = index === total - 1;

    const isBeforeOpen = index === openIndex - 1;
    const isAfterOpen = index === openIndex + 1;

    const isAlone = (isAfterOpen && isLast) || (isBeforeOpen && isFirst);

    const BORDER_WIDTH = '1px';
    const BORDER_STYLE = 'solid';
    const borderTopWidth =
        isFirst || isAfterOpen || isOpen ? BORDER_WIDTH : '0px';
    const borderBottomWidth =
        isLast || isBeforeOpen || isOpen ? BORDER_WIDTH : '0px';
    const borderLeftWidth = BORDER_WIDTH;
    const borderRightWidth = BORDER_WIDTH;

    let borderTopLeftRadius = 0;
    let borderTopRightRadius = 0;
    let borderBottomLeftRadius = 0;
    let borderBottomRightRadius = 0;

    if (isOpen || isAlone) {
        borderTopLeftRadius = 20;
        borderTopRightRadius = 20;
        borderBottomLeftRadius = 20;
        borderBottomRightRadius = 20;
    } else if (isBeforeOpen) {
        borderBottomLeftRadius = 20;
        borderBottomRightRadius = 20;
    } else if (isAfterOpen) {
        borderTopLeftRadius = 20;
        borderTopRightRadius = 20;
    } else if (isFirst) {
        borderTopLeftRadius = 20;
        borderTopRightRadius = 20;
    } else if (isLast) {
        borderBottomLeftRadius = 20;
        borderBottomRightRadius = 20;
    }

    const panelId = `accordion-panel-${item.id}`;
    const buttonId = `accordion-button-${item.id}`;

    return (
        <motion.li layout>
            <motion.div
                animate={{
                    borderTopLeftRadius,
                    borderTopRightRadius,
                    borderBottomLeftRadius,
                    borderBottomRightRadius,
                }}
                className="border-border/70 bg-card overflow-hidden border-solid will-change-transform"
                style={{
                    borderTopWidth,
                    borderBottomWidth,
                    borderLeftWidth,
                    borderRightWidth,
                    borderStyle: BORDER_STYLE,
                    marginBlock: isOpen ? '10px' : '0px',
                }}
            >
                <button
                    type="button"
                    id={buttonId}
                    aria-expanded={isOpen}
                    aria-controls={panelId}
                    onClick={() => setOpenId(isOpen ? null : item.id)}
                    className="focus-visible:ring-ring flex w-full cursor-pointer items-center justify-between gap-4 px-4 py-3.5 text-left focus-visible:ring-2 focus-visible:outline-none"
                >
                    <div className="flex items-center gap-3">
                        <span className="text-primary shrink-0">
                            {item.icon}
                        </span>

                        <span className="text-foreground text-sm font-semibold md:text-base">
                            {item.title}
                        </span>
                    </div>

                    <motion.span
                        aria-hidden
                        animate={{ rotate: isOpen ? 180 : 0 }}
                    >
                        <ChevronDown className="text-muted-foreground size-5" />
                    </motion.span>
                </button>

                <motion.div
                    id={panelId}
                    role="region"
                    aria-labelledby={buttonId}
                    initial={false}
                    animate={{
                        height: isOpen ? bounds.height : 0,
                        opacity: isOpen ? 1 : 0,
                    }}
                    className="overflow-hidden will-change-transform"
                >
                    <div ref={ref}>
                        <div className="text-muted-foreground px-4 pb-4 text-sm leading-relaxed">
                            {item.content}
                        </div>
                    </div>
                </motion.div>
            </motion.div>
        </motion.li>
    );
};

export const AccordionApp: FC<AccordionProps> = ({
    items,
    className,
    listClassName,
    reducedMotion = false,
}) => {
    const defaultItems = items ?? DEFAULT_ITEMS;

    const [openId, setOpenId] = useState<number | null>(null);

    const openIndex = defaultItems.findIndex((item) => item.id === openId);

    return (
        <MotionConfig
            transition={reducedMotion ? { duration: 0 } : springTransition}
            reducedMotion={reducedMotion ? 'always' : 'never'}
        >
            <div
                className={cn(
                    'flex w-full flex-col items-center justify-center',
                    className,
                )}
            >
                <ul className={cn('w-full max-w-2xl', listClassName)}>
                    {defaultItems.map((item, index) => (
                        <AccordionItem
                            key={item.id}
                            item={item}
                            setOpenId={setOpenId}
                            index={index}
                            total={defaultItems.length}
                            openIndex={openIndex}
                        />
                    ))}
                </ul>
            </div>
        </MotionConfig>
    );
};

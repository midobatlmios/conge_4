import { useState } from 'react';

export type Bar = {
    label: string;
    value: number | null;
    /** Lines shown in the tooltip, the first one in bold. */
    details: string[];
};

interface MonthlyBarsProps {
    bars: Bar[];
    /** Accessible name of the chart. */
    title: string;
    /** How a value reads on the axis and above the highest bar. */
    format: (value: number) => string;
}

const HEIGHT = 160;

/**
 * Single-series column chart, one column per month: 24px max, 4px rounded top,
 * square at the baseline, a recessive baseline and gridline, a tooltip on hover
 * or keyboard focus. Only the highest value is labelled; the table below the
 * charts gives every number.
 */
export default function MonthlyBars({ bars, title, format }: MonthlyBarsProps) {
    const [active, setActive] = useState<number | null>(null);
    const max = Math.max(0, ...bars.map((bar) => bar.value ?? 0));
    const top = max > 0 ? max : 1;
    const peak = bars.findIndex((bar) => bar.value === max && max > 0);

    return (
        <figure className="relative" aria-label={title}>
            <div className="flex">
                {/* Axis: zero and the maximum only. */}
                <div className="text-muted-foreground flex w-10 shrink-0 flex-col justify-between pr-2 text-right text-xs" style={{ height: HEIGHT }}>
                    <span>{format(top)}</span>
                    <span>{format(0)}</span>
                </div>
                <div className="relative flex-1">
                    <div className="border-border/60 absolute inset-x-0 top-0 border-t border-dashed" aria-hidden="true" />
                    <div className="border-border absolute inset-x-0 border-t" style={{ top: HEIGHT }} aria-hidden="true" />
                    <ul className="relative flex items-end gap-0.5" style={{ height: HEIGHT }}>
                        {bars.map((bar, index) => {
                            const height = bar.value ? Math.max(2, (bar.value / top) * HEIGHT) : 0;
                            return (
                                <li
                                    key={bar.label}
                                    className="relative flex h-full flex-1 cursor-default items-end justify-center outline-none"
                                    tabIndex={0}
                                    aria-label={`${bar.label} : ${bar.details.join(', ')}`}
                                    onMouseEnter={() => setActive(index)}
                                    onMouseLeave={() => setActive(null)}
                                    onFocus={() => setActive(index)}
                                    onBlur={() => setActive(null)}
                                >
                                    {index === peak && (
                                        <span className="text-foreground absolute text-xs font-medium" style={{ bottom: height + 4 }}>
                                            {format(max)}
                                        </span>
                                    )}
                                    <span
                                        className={`block w-full max-w-6 rounded-t bg-[#2a78d6] transition-opacity dark:bg-[#3987e5] ${
                                            active !== null && active !== index ? 'opacity-50' : ''
                                        }`}
                                        style={{ height }}
                                    />
                                    {active === index && (
                                        <span
                                            role="tooltip"
                                            className="bg-popover text-popover-foreground absolute z-10 w-max max-w-48 rounded-md border px-2.5 py-1.5 text-xs shadow-md"
                                            style={{ bottom: Math.min(height + 8, HEIGHT - 20) }}
                                        >
                                            <span className="block font-semibold">{bar.label}</span>
                                            {bar.details.map((line, i) => (
                                                <span key={line} className={`block ${i === 0 ? 'font-medium' : 'text-muted-foreground'}`}>
                                                    {line}
                                                </span>
                                            ))}
                                        </span>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                    <div className="text-muted-foreground mt-1 flex gap-0.5 text-center text-[11px]">
                        {bars.map((bar) => (
                            <span key={bar.label} className="flex-1 truncate">
                                {bar.label}
                            </span>
                        ))}
                    </div>
                </div>
            </div>
        </figure>
    );
}

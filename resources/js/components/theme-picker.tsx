import { router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import { update as updateTheme } from '@/routes/appearance';
import type { ThemeOption } from '@/types';

type Props = {
    themes: ThemeOption[];
    currentTheme: string;
    canChooseTheme: boolean;
};

/**
 * The colour themes a client can skin its whole workspace with.
 *
 * Each card carries the theme's `data-theme` attribute, so the swatches inside
 * it are painted by the very palette in `app.css` the app will use -- no colour
 * values are duplicated in TypeScript. Choosing previews instantly by writing
 * the attribute on `<html>`, then persists it for the whole tenant.
 */
export default function ThemePicker({
    themes,
    currentTheme,
    canChooseTheme,
}: Props) {
    const [selected, setSelected] = useState(currentTheme);
    const [pending, setPending] = useState<string | null>(null);

    const choose = (value: string): void => {
        if (!canChooseTheme || pending !== null || value === selected) {
            return;
        }

        const previous = selected;

        setSelected(value);
        setPending(value);
        document.documentElement.dataset.theme = value;

        router.put(
            updateTheme().url,
            { theme: value },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    setSelected(previous);
                    document.documentElement.dataset.theme = previous;
                },
                onFinish: () => setPending(null),
            },
        );
    };

    return (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {themes.map((theme) => {
                const isSelected = selected === theme.value;

                return (
                    <button
                        key={theme.value}
                        type="button"
                        onClick={() => choose(theme.value)}
                        disabled={!canChooseTheme || pending !== null}
                        aria-pressed={isSelected}
                        data-theme={theme.value}
                        className={cn(
                            'rounded-xl border bg-card p-3 text-left transition-colors',
                            isSelected
                                ? 'border-brand ring-1 ring-brand/40'
                                : 'border-border hover:border-brand/50',
                            !canChooseTheme && 'cursor-not-allowed opacity-60',
                        )}
                    >
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-sm font-medium text-foreground">
                                {theme.label}
                            </span>
                            {isSelected ? (
                                <span className="flex size-5 items-center justify-center rounded-full bg-brand text-brand-foreground">
                                    <Check className="size-3" />
                                </span>
                            ) : (
                                theme.is_default && (
                                    <span className="rounded-full border border-border px-2 py-0.5 text-[10px] tracking-wide text-muted-foreground uppercase">
                                        Default
                                    </span>
                                )
                            )}
                        </div>

                        <div className="mt-3 flex items-center gap-1.5">
                            <span className="h-6 w-10 rounded-md bg-brand" />
                            <span className="h-6 w-6 rounded-md bg-brand-soft" />
                            <span className="h-6 w-6 rounded-md bg-brand-highlight" />
                            <span className="h-6 w-6 rounded-md bg-accent" />
                            <span className="h-6 w-6 rounded-md border border-sidebar-border bg-sidebar" />
                        </div>

                        <div className="mt-3 flex items-center gap-2">
                            <span className="rounded-md bg-brand px-2 py-1 text-[10px] font-medium text-brand-foreground">
                                {theme.label}
                            </span>
                            <span className="h-2 w-16 rounded-full bg-brand/30" />
                            <span className="h-2 w-8 rounded-full bg-brand/15" />
                        </div>

                        <p className="mt-3 text-xs text-muted-foreground">
                            {theme.description}
                        </p>
                    </button>
                );
            })}
        </div>
    );
}

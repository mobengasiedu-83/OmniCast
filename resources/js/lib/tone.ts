/**
 * The badge variants `StatusBadge` and `Badge` share.
 *
 * The backend enums answer `tone()` with these names, except for `danger`: the
 * policy screens call a bad state "danger" while the design system calls the
 * same colour "destructive", so the two are mapped here rather than in each page.
 */
export type BadgeTone =
    | 'default'
    | 'secondary'
    | 'destructive'
    | 'outline'
    | 'success'
    | 'warning'
    | 'info'
    | 'muted';

export function badgeTone(tone: string): BadgeTone {
    return (tone === 'danger' ? 'destructive' : tone) as BadgeTone;
}

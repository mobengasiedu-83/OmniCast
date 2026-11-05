import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

/**
 * One-tap light/dark switch for the app header. The full light/system/dark
 * choice lives on the appearance settings page.
 */
export default function ThemeToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <Button
            variant="ghost"
            size="icon"
            className="relative overflow-hidden text-muted-foreground hover:text-foreground"
            onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
            aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
            title={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
            data-test="theme-toggle"
        >
            <Sun
                className={cn(
                    'size-4 transition-all duration-300',
                    isDark ? 'scale-0 -rotate-90' : 'scale-100 rotate-0',
                )}
            />
            <Moon
                className={cn(
                    'absolute size-4 transition-all duration-300',
                    isDark ? 'scale-100 rotate-0' : 'scale-0 rotate-90',
                )}
            />
        </Button>
    );
}

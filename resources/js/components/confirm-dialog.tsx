import { AlertTriangle, ShieldQuestion } from 'lucide-react';
import {
    createContext,
    useCallback,
    useContext,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

/**
 * Copy and styling for one confirmation prompt.
 *
 * `tone` only changes the emphasis of the dialog header and the confirm
 * button: use `danger` for anything that deletes, revokes or cancels.
 */
export type ConfirmOptions = {
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    tone?: 'default' | 'danger';
};

type ConfirmRequest = ConfirmOptions & {
    resolve: (confirmed: boolean) => void;
};

type Confirm = (options: ConfirmOptions) => Promise<boolean>;

const ConfirmContext = createContext<Confirm | null>(null);

/**
 * Replaces `window.confirm()` with the application's own dialog.
 *
 * Mounted once in `app.tsx`, so every page — app, settings, admin, auth and
 * marketing — asks for confirmation the same way:
 *
 * ```tsx
 * const confirm = useConfirm();
 *
 * const archive = async (): Promise<void> => {
 *     if (!(await confirm({ title: 'Archive this list?' }))) {
 *         return;
 *     }
 *     // ...perform the action
 * };
 * ```
 */
export function ConfirmDialogProvider({ children }: { children: ReactNode }) {
    const [request, setRequest] = useState<ConfirmRequest | null>(null);
    const [open, setOpen] = useState(false);
    const pending = useRef<((confirmed: boolean) => void) | null>(null);

    const confirm = useCallback((options: ConfirmOptions): Promise<boolean> => {
        // A prompt is already waiting: the newer request wins and the older
        // promise settles as a decline so no caller is left hanging.
        pending.current?.(false);

        return new Promise<boolean>((resolve) => {
            pending.current = resolve;
            setRequest({ ...options, resolve });
            setOpen(true);
        });
    }, []);

    const settle = useCallback((confirmed: boolean): void => {
        const resolve = pending.current;
        pending.current = null;
        setOpen(false);
        resolve?.(confirmed);
    }, []);

    const value = useMemo<Confirm>(() => confirm, [confirm]);
    const tone = request?.tone ?? 'default';
    const isDanger = tone === 'danger';

    return (
        <ConfirmContext.Provider value={value}>
            {children}
            <Dialog
                open={open}
                onOpenChange={(next) => {
                    if (!next) {
                        settle(false);
                    }
                }}
            >
                <DialogContent
                    className="sm:max-w-md"
                    data-test="confirm-dialog"
                >
                    <DialogHeader className="gap-3">
                        <span
                            className={cn(
                                'flex size-10 items-center justify-center rounded-xl',
                                isDanger
                                    ? 'bg-destructive-soft text-destructive'
                                    : 'bg-brand-soft text-brand',
                            )}
                        >
                            {isDanger ? (
                                <AlertTriangle className="size-5" />
                            ) : (
                                <ShieldQuestion className="size-5" />
                            )}
                        </span>
                        <DialogTitle>{request?.title}</DialogTitle>
                        {request?.description && (
                            <DialogDescription>
                                {request.description}
                            </DialogDescription>
                        )}
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            data-test="confirm-dialog-cancel"
                            onClick={() => settle(false)}
                        >
                            {request?.cancelLabel ?? 'Cancel'}
                        </Button>
                        <Button
                            variant={isDanger ? 'destructive' : 'default'}
                            data-test="confirm-dialog-confirm"
                            onClick={() => settle(true)}
                        >
                            {request?.confirmLabel ?? 'Confirm'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </ConfirmContext.Provider>
    );
}

export function useConfirm(): Confirm {
    const confirm = useContext(ConfirmContext);

    if (confirm === null) {
        throw new Error(
            'useConfirm must be used inside <ConfirmDialogProvider>.',
        );
    }

    return confirm;
}

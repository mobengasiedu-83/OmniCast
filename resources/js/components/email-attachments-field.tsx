import { FileText, Image as ImageIcon, Paperclip, Undo2, X } from 'lucide-react';
import { type ChangeEvent, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { EmailAttachmentRow } from '@/types';

/**
 * The caps the server enforces, mirrored here so the author learns the limit
 * while picking files rather than after a round trip.
 */
const MAX_FILES = 5;
const MAX_FILE_BYTES = 5 * 1024 * 1024;
const MAX_TOTAL_BYTES = 10 * 1024 * 1024;

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * The files a campaign delivers with its message.
 *
 * Files already stored against the campaign are listed from the page props;
 * new ones are held in the form as `File` objects, so the browser uploads them
 * with the same request that saves the campaign. Removals are collected as ids
 * and only applied when the form is submitted.
 */
export default function EmailAttachmentsField({
    files,
    onChange,
    existing = [],
    removedIds,
    onToggleExisting,
    invalid = false,
}: {
    files: File[];
    onChange: (files: File[]) => void;
    existing?: EmailAttachmentRow[];
    removedIds: number[];
    onToggleExisting: (attachment: number) => void;
    invalid?: boolean;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [notice, setNotice] = useState<string | null>(null);

    const kept = existing.filter(
        (attachment) => !removedIds.includes(attachment.id),
    );
    const slots = Math.max(MAX_FILES - kept.length - files.length, 0);
    const pendingBytes = files.reduce((total, file) => total + file.size, 0);
    const overTotal = pendingBytes > MAX_TOTAL_BYTES;

    const addFiles = (event: ChangeEvent<HTMLInputElement>): void => {
        const picked = Array.from(event.target.files ?? []);

        event.target.value = '';

        if (picked.length === 0) {
            return;
        }

        const tooLarge = picked.filter((file) => file.size > MAX_FILE_BYTES);
        const next = [...files];

        for (const file of picked) {
            const room = MAX_FILES - kept.length - next.length;

            if (file.size > MAX_FILE_BYTES || room <= 0) {
                continue;
            }

            const duplicate = next.some(
                (queued) =>
                    queued.name === file.name && queued.size === file.size,
            );

            if (!duplicate) {
                next.push(file);
            }
        }

        if (tooLarge.length > 0) {
            setNotice(
                `${tooLarge.map((file) => file.name).join(', ')} ${tooLarge.length === 1 ? 'is' : 'are'} over 5 MB and ${tooLarge.length === 1 ? 'was' : 'were'} skipped.`,
            );
        } else if (next.length === files.length) {
            setNotice(`A message carries at most ${MAX_FILES} files.`);
        } else {
            setNotice(null);
        }

        onChange(next);
    };

    const removeQueued = (file: File): void => {
        onChange(files.filter((queued) => queued !== file));
        setNotice(null);
    };

    const total = kept.length + files.length;

    return (
        <div className="grid gap-2">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-sm font-medium">Attachments</span>
                <span className="text-xs text-muted-foreground tabular-nums">
                    {total} of {MAX_FILES} files
                </span>
            </div>

            <div
                className={cn(
                    'divide-y rounded-lg border bg-muted/20',
                    invalid && 'border-destructive',
                )}
            >
                {existing.map((attachment) => {
                    const removed = removedIds.includes(attachment.id);

                    return (
                        <div
                            key={attachment.id}
                            className={cn(
                                'flex items-center gap-2 px-2 py-2',
                                removed && 'opacity-50',
                            )}
                        >
                            {attachment.is_image ? (
                                <ImageIcon className="size-4 shrink-0 text-muted-foreground" />
                            ) : (
                                <FileText className="size-4 shrink-0 text-muted-foreground" />
                            )}
                            <span
                                className={cn(
                                    'min-w-0 flex-1 truncate text-sm',
                                    removed && 'line-through',
                                )}
                                title={attachment.name}
                            >
                                {attachment.name}
                            </span>
                            <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                {attachment.size}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="shrink-0"
                                aria-label={
                                    removed
                                        ? `Keep ${attachment.name}`
                                        : `Remove ${attachment.name}`
                                }
                                title={removed ? 'Keep this file' : 'Remove this file'}
                                onClick={() => onToggleExisting(attachment.id)}
                            >
                                {removed ? <Undo2 /> : <X />}
                            </Button>
                        </div>
                    );
                })}

                {files.map((file) => (
                    <div
                        key={`${file.name}-${file.size}`}
                        className="flex items-center gap-2 px-2 py-2"
                    >
                        <Paperclip className="size-4 shrink-0 text-muted-foreground" />
                        <span
                            className="min-w-0 flex-1 truncate text-sm"
                            title={file.name}
                        >
                            {file.name}
                        </span>
                        <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                            {formatBytes(file.size)}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="shrink-0"
                            aria-label={`Remove ${file.name}`}
                            title="Remove this file"
                            onClick={() => removeQueued(file)}
                        >
                            <X />
                        </Button>
                    </div>
                ))}

                {total === 0 && (
                    <p className="px-2 py-3 text-sm text-muted-foreground">
                        No files attached. Most messages read better without one.
                    </p>
                )}
            </div>
        </div>
    );
}

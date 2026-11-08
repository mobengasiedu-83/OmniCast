import { type Editor, EditorContent, useEditor } from '@tiptap/react';
import Highlight from '@tiptap/extension-highlight';
import Image from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import { Color, FontFamily, FontSize, TextStyle } from '@tiptap/extension-text-style';
import StarterKit from '@tiptap/starter-kit';
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    Bold,
    Highlighter,
    ImagePlus,
    Italic,
    Link2,
    Link2Off,
    List,
    ListOrdered,
    Loader2,
    Palette,
    Redo2,
    RemoveFormatting,
    Strikethrough,
    Underline as UnderlineIcon,
    Undo2,
} from 'lucide-react';
import {
    type ChangeEvent,
    type CSSProperties,
    type ReactNode,
    useEffect,
    useRef,
    useState,
} from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Toggle } from '@/components/ui/toggle';
import { cn } from '@/lib/utils';

/**
 * The point sizes the composer offers. Email clients only honour inline sizes,
 * which is what the font-size mark writes.
 */
const fontSizes = ['12px', '14px', '16px', '18px', '24px', '32px'];

/**
 * The typefaces a mail client is sure to have. Only web-safe stacks, because
 * `@font-face` and web fonts are dropped by most inboxes.
 */
const fontFamilies = [
    { value: 'default', label: 'Default', stack: '' },
    { value: 'sans', label: 'Sans', stack: 'Arial, Helvetica, sans-serif' },
    { value: 'serif', label: 'Serif', stack: 'Georgia, Times New Roman, serif' },
    {
        value: 'mono',
        label: 'Mono',
        stack: 'Courier New, Courier, monospace',
    },
];

/**
 * Reads the CSRF cookie Laravel sets, so an image upload can post outside
 * Inertia without a token meta tag on every page.
 */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match === null ? '' : decodeURIComponent(match[1]);
}

/**
 * One toolbar button. It reads `pressed` from the editor state, so the author
 * can see which marks already apply to the selection.
 */
function ToolbarButton({
    label,
    pressed = false,
    disabled = false,
    onClick,
    children,
}: {
    label: string;
    pressed?: boolean;
    disabled?: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <Toggle
            type="button"
            size="sm"
            pressed={pressed}
            disabled={disabled}
            onPressedChange={() => onClick()}
            aria-label={label}
            title={label}
            className="size-8 rounded-md text-muted-foreground data-[state=on]:text-foreground"
        >
            {children}
        </Toggle>
    );
}

/**
 * The rich text writer used by the email channel.
 *
 * It emits the HTML the server expects on `email_body`. The platform sanitises
 * that markup against an allow-list before it is stored or sent, so the toolbar
 * only offers formatting that survives the allow-list.
 */
export default function RichTextEditor({
    value,
    onChange,
    placeholder = 'Write the message…',
    uploadUrl,
    disabled = false,
    minHeight = '14rem',
    invalid = false,
    inputId,
}: {
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
    /** Where inline images are posted. Without it the image tools stay hidden. */
    uploadUrl?: string;
    disabled?: boolean;
    minHeight?: string;
    invalid?: boolean;
    inputId?: string;
}) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);

    const editor = useEditor(
        {
            immediatelyRender: false,
            shouldRerenderOnTransaction: true,
            editable: !disabled,
            extensions: [
                StarterKit.configure({
                    heading: { levels: [1, 2, 3] },
                    link: {
                        openOnClick: false,
                        autolink: true,
                        defaultProtocol: 'https',
                        HTMLAttributes: {
                            target: '_blank',
                            rel: 'noopener noreferrer nofollow',
                        },
                    },
                }),
                TextStyle,
                Color,
                FontSize,
                FontFamily,
                Highlight.configure({ multicolor: true }),
                TextAlign.configure({ types: ['heading', 'paragraph'] }),
                Image.configure({ inline: false, allowBase64: false }),
                Placeholder.configure({ placeholder }),
            ],
            content: value,
            editorProps: {
                attributes: {
                    ...(inputId === undefined ? {} : { id: inputId }),
                    class: 'tiptap px-3 py-2 text-sm leading-relaxed',
                    role: 'textbox',
                    'aria-multiline': 'true',
                },
            },
            onUpdate: ({ editor: current }) => {
                onChange(current.isEmpty ? '' : current.getHTML());
            },
        },
        [placeholder],
    );

    /**
     * Accept copy the form replaces behind the author's back: applying a
     * template, or a validation round trip.
     */
    useEffect(() => {
        if (editor === null) {
            return;
        }

        const current = editor.isEmpty ? '' : editor.getHTML();

        if (current === value) {
            return;
        }

        editor.commands.setContent(value === '' ? '<p></p>' : value, {
            emitUpdate: false,
        });
    }, [editor, value]);

    useEffect(() => {
        editor?.setEditable(!disabled);
    }, [editor, disabled]);

    if (editor === null) {
        return (
            <div className="grid min-h-40 place-items-center rounded-lg border bg-muted/30">
                <Loader2 className="size-4 animate-spin text-muted-foreground" />
            </div>
        );
    }

    const typed: Editor = editor;
    const textStyle = typed.getAttributes('textStyle') as {
        color?: string;
        fontSize?: string;
        fontFamily?: string;
    };
    const highlightColor =
        (typed.getAttributes('highlight').color as string | undefined) ?? '';

    const askForLink = (): void => {
        const previous =
            (typed.getAttributes('link').href as string | undefined) ?? '';

        const href = window.prompt(
            'Link address',
            previous === '' ? 'https://' : previous,
        );

        if (href === null) {
            return;
        }

        const trimmed = href.trim();

        if (trimmed === '' || trimmed === 'https://') {
            typed.chain().focus().extendMarkRange('link').unsetLink().run();

            return;
        }

        typed
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: trimmed })
            .run();
    };

    const uploadImage = async (
        event: ChangeEvent<HTMLInputElement>,
    ): Promise<void> => {
        const file = event.target.files?.[0];

        event.target.value = '';

        if (file === undefined || uploadUrl === undefined) {
            return;
        }

        setUploading(true);
        setUploadError(null);

        try {
            const body = new FormData();
            body.append('image', file);

            const response = await fetch(uploadUrl, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
            });

            if (!response.ok) {
                throw new Error(`Upload failed with ${response.status}`);
            }

            const payload = (await response.json()) as { url?: string };

            if (typeof payload.url !== 'string' || payload.url === '') {
                throw new Error('The server did not return an image URL.');
            }

            typed
                .chain()
                .focus()
                .setImage({ src: payload.url, alt: file.name })
                .run();
        } catch {
            setUploadError(
                'That image could not be uploaded. Use a PNG, JPG, GIF or WebP under 2 MB.',
            );
        } finally {
            setUploading(false);
        }
    };

    return (
        <div className="grid gap-1.5">
            <div
                className={cn(
                    'email-editor overflow-hidden rounded-lg border bg-background shadow-xs transition-colors',
                    invalid && 'border-destructive',
                    disabled && 'opacity-60',
                )}
            >
                <div className="flex flex-wrap items-center gap-1 border-b bg-muted/40 p-1">
                    <ToolbarButton
                        label="Bold"
                        pressed={typed.isActive('bold')}
                        onClick={() => typed.chain().focus().toggleBold().run()}
                    >
                        <Bold />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Italic"
                        pressed={typed.isActive('italic')}
                        onClick={() => typed.chain().focus().toggleItalic().run()}
                    >
                        <Italic />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Underline"
                        pressed={typed.isActive('underline')}
                        onClick={() =>
                            typed.chain().focus().toggleUnderline().run()
                        }
                    >
                        <UnderlineIcon />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Strikethrough"
                        pressed={typed.isActive('strike')}
                        onClick={() => typed.chain().focus().toggleStrike().run()}
                    >
                        <Strikethrough />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Clear colour"
                        onClick={() => typed.chain().focus().unsetColor().run()}
                    >
                        <Palette />
                    </ToolbarButton>
                    <label
                        className="relative inline-flex size-8 cursor-pointer items-center justify-center rounded-md hover:bg-muted"
                        title="Text colour"
                        aria-label="Choose a text colour"
                    >
                        <span
                            aria-hidden="true"
                            className="size-4 rounded-full border border-input"
                            style={{
                                backgroundColor: textStyle.color ?? 'transparent',
                            }}
                        />
                        <input
                            type="color"
                            aria-label="Text colour"
                            value={textStyle.color ?? '#111827'}
                            onChange={(event) =>
                                typed
                                    .chain()
                                    .focus()
                                    .setColor(event.target.value)
                                    .run()
                            }
                            className="absolute inset-0 size-full cursor-pointer opacity-0"
                        />
                    </label>

                    <ToolbarButton
                        label="Highlight"
                        pressed={typed.isActive('highlight')}
                        onClick={() =>
                            typed
                                .chain()
                                .focus()
                                .toggleHighlight({
                                    color:
                                        highlightColor === ''
                                            ? '#fef08a'
                                            : highlightColor,
                                })
                                .run()
                        }
                    >
                        <Highlighter />
                    </ToolbarButton>
                    <label
                        className="relative inline-flex size-8 cursor-pointer items-center justify-center rounded-md hover:bg-muted"
                        title="Highlight colour"
                        aria-label="Choose a highlight colour"
                    >
                        <span
                            aria-hidden="true"
                            className="size-4 rounded-sm border border-input"
                            style={{
                                backgroundColor:
                                    highlightColor === ''
                                        ? '#fef08a'
                                        : highlightColor,
                            }}
                        />
                        <input
                            type="color"
                            aria-label="Highlight colour"
                            value={
                                highlightColor === '' ? '#fef08a' : highlightColor
                            }
                            onChange={(event) =>
                                typed
                                    .chain()
                                    .focus()
                                    .setHighlight({ color: event.target.value })
                                    .run()
                            }
                            className="absolute inset-0 size-full cursor-pointer opacity-0"
                        />
                    </label>

                    <Select
                        value={
                            fontFamilies.find(
                                (family) =>
                                    family.stack === textStyle.fontFamily,
                            )?.value ?? 'default'
                        }
                        onValueChange={(value) => {
                            const family = fontFamilies.find(
                                (candidate) => candidate.value === value,
                            );

                            if (family === undefined) {
                                return;
                            }

                            if (family.stack === '') {
                                typed.chain().focus().unsetFontFamily().run();

                                return;
                            }

                            typed
                                .chain()
                                .focus()
                                .setFontFamily(family.stack)
                                .run();
                        }}
                    >
                        <SelectTrigger
                            size="sm"
                            aria-label="Font family"
                            className="h-8 w-[5.4rem] text-xs"
                        >
                            <SelectValue placeholder="Font" />
                        </SelectTrigger>
                        <SelectContent>
                            {fontFamilies.map((family) => (
                                <SelectItem
                                    key={family.value}
                                    value={family.value}
                                >
                                    {family.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={textStyle.fontSize ?? '16px'}
                        onValueChange={(size) =>
                            typed.chain().focus().setFontSize(size).run()
                        }
                    >
                        <SelectTrigger
                            size="sm"
                            aria-label="Font size"
                            className="h-8 w-[4.9rem] text-xs"
                        >
                            <SelectValue placeholder="Size" />
                        </SelectTrigger>
                        <SelectContent>
                            {fontSizes.map((size) => (
                                <SelectItem key={size} value={size}>
                                    {size}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <span
                        className="mx-0.5 h-5 w-px bg-border"
                        aria-hidden="true"
                    />

                    <ToolbarButton
                        label="Align left"
                        pressed={typed.isActive({ textAlign: 'left' })}
                        onClick={() =>
                            typed.chain().focus().setTextAlign('left').run()
                        }
                    >
                        <AlignLeft />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Align centre"
                        pressed={typed.isActive({ textAlign: 'center' })}
                        onClick={() =>
                            typed.chain().focus().setTextAlign('center').run()
                        }
                    >
                        <AlignCenter />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Align right"
                        pressed={typed.isActive({ textAlign: 'right' })}
                        onClick={() =>
                            typed.chain().focus().setTextAlign('right').run()
                        }
                    >
                        <AlignRight />
                    </ToolbarButton>

                    <span
                        className="mx-0.5 h-5 w-px bg-border"
                        aria-hidden="true"
                    />

                    <ToolbarButton
                        label="Bulleted list"
                        pressed={typed.isActive('bulletList')}
                        onClick={() =>
                            typed.chain().focus().toggleBulletList().run()
                        }
                    >
                        <List />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Numbered list"
                        pressed={typed.isActive('orderedList')}
                        onClick={() =>
                            typed.chain().focus().toggleOrderedList().run()
                        }
                    >
                        <ListOrdered />
                    </ToolbarButton>

                    <span
                        className="mx-0.5 h-5 w-px bg-border"
                        aria-hidden="true"
                    />

                    <ToolbarButton
                        label={typed.isActive('link') ? 'Edit link' : 'Add link'}
                        pressed={typed.isActive('link')}
                        onClick={askForLink}
                    >
                        <Link2 />
                    </ToolbarButton>
                    {typed.isActive('link') && (
                        <ToolbarButton
                            label="Remove link"
                            onClick={() => typed.chain().focus().unsetLink().run()}
                        >
                            <Link2Off />
                        </ToolbarButton>
                    )}

                    {uploadUrl !== undefined && (
                        <>
                            <input
                                ref={fileInput}
                                type="file"
                                accept="image/png,image/jpeg,image/gif,image/webp"
                                className="hidden"
                                onChange={(event) => void uploadImage(event)}
                            />
                            <ToolbarButton
                                label="Insert an image"
                                disabled={uploading}
                                onClick={() => fileInput.current?.click()}
                            >
                                {uploading ? (
                                    <Loader2 className="animate-spin" />
                                ) : (
                                    <ImagePlus />
                                )}
                            </ToolbarButton>
                        </>
                    )}

                    <span
                        className="mx-0.5 h-5 w-px bg-border"
                        aria-hidden="true"
                    />

                    <ToolbarButton
                        label="Clear formatting"
                        onClick={() =>
                            typed.chain().focus().unsetAllMarks().clearNodes().run()
                        }
                    >
                        <RemoveFormatting />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Undo"
                        disabled={!typed.can().undo()}
                        onClick={() => typed.chain().focus().undo().run()}
                    >
                        <Undo2 />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Redo"
                        disabled={!typed.can().redo()}
                        onClick={() => typed.chain().focus().redo().run()}
                    >
                        <Redo2 />
                    </ToolbarButton>
                </div>

                <EditorContent
                    editor={editor}
                    className="cursor-text"
                    style={
                        {
                            '--email-editor-min-height': minHeight,
                        } as CSSProperties
                    }
                />
            </div>

            {uploadError !== null && (
                <p className="text-xs text-destructive">{uploadError}</p>
            )}
        </div>
    );
}

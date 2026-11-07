import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { CampaignTemplateOption } from '@/types';

/**
 * The label the picker files templates under when the library never gave them a
 * category, so every starting point is reachable from the filter.
 */
const uncategorised = 'Uncategorised';

/**
 * Offers the templates a campaign may start from.
 *
 * Choosing one copies its wording straight into the campaign body; the operator
 * keeps editing from there, so the picker stays a starting point rather than a
 * link the campaign can drift away from.
 */
export default function TemplatePicker({
    templates,
    value,
    onSelect,
}: {
    templates: CampaignTemplateOption[];
    value: string;
    onSelect: (template: CampaignTemplateOption | null) => void;
}) {
    const [category, setCategory] = useState<string>('all');

    if (templates.length === 0) {
        return null;
    }

    const categories = Array.from(
        new Set(
            templates.map((template) => template.category ?? uncategorised),
        ),
    ).sort((left, right) => left.localeCompare(right));

    const visibleTemplates =
        category === 'all'
            ? templates
            : templates.filter(
                  (template) =>
                      (template.category ?? uncategorised) === category,
              );

    const selected =
        templates.find((template) => String(template.id) === value) ?? null;

    return (
        <div className="grid gap-3 rounded-lg border border-dashed bg-muted/30 p-3">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div className="grid gap-1.5 sm:w-52">
                    <Label htmlFor="template_category">Category</Label>
                    <Select value={category} onValueChange={setCategory}>
                        <SelectTrigger id="template_category">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All categories</SelectItem>
                            {categories.map((name) => (
                                <SelectItem key={name} value={name}>
                                    {name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid flex-1 gap-1.5">
                    <Label htmlFor="message_template_id">
                        Start from a template
                    </Label>
                    <Select
                        value={value}
                        onValueChange={(next) =>
                            onSelect(
                                templates.find(
                                    (template) => String(template.id) === next,
                                ) ?? null,
                            )
                        }
                    >
                        <SelectTrigger id="message_template_id">
                            <SelectValue placeholder="Write from scratch" />
                        </SelectTrigger>
                        <SelectContent>
                            {visibleTemplates.map((template) => (
                                <SelectItem
                                    key={template.id}
                                    value={String(template.id)}
                                >
                                    {template.name}
                                    {template.category
                                        ? ` · ${template.category}`
                                        : ''}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {selected && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => onSelect(null)}
                    >
                        Clear
                    </Button>
                )}
            </div>

            {selected ? (
                <div className="grid gap-2 rounded-md border bg-background p-3">
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                        <span className="font-medium text-foreground">
                            {selected.name}
                        </span>
                        <span>· {selected.channel_label}</span>
                        <span>
                            · {selected.is_system ? 'System' : 'Your copy'}
                        </span>
                        <span>· used {selected.usage_count}×</span>
                    </div>

                    {selected.subject && (
                        <p className="text-sm font-medium">
                            {selected.subject}
                        </p>
                    )}

                    <p className="max-h-32 overflow-y-auto text-sm whitespace-pre-wrap">
                        {selected.body}
                    </p>

                    {selected.variables.length > 0 && (
                        <p className="text-xs text-muted-foreground">
                            Fills in: {selected.variables.join(', ')}
                        </p>
                    )}
                </div>
            ) : (
                <p className="text-xs text-muted-foreground">
                    Picking a template copies its wording into the fields below.
                    You can still edit it before saving.
                </p>
            )}
        </div>
    );
}

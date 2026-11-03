import { Head, router, useForm } from '@inertiajs/react';
import { Copy, KeyRound, Plus, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { urls } from '@/lib/urls';
import type { ApiTokenRow } from '@/types';

type PageProps = {
    tokens: ApiTokenRow[];
    abilities: string[];
    newToken: string | null;
};

export default function AccountApiTokens({
    tokens,
    abilities,
    newToken,
}: PageProps) {
    const [copied, setCopied] = useState(false);

    const form = useForm<{ name: string; abilities: string[] }>({
        name: '',
        abilities: ['read'],
    });

    const toggleAbility = (ability: string): void => {
        form.setData(
            'abilities',
            form.data.abilities.includes(ability)
                ? form.data.abilities.filter((value) => value !== ability)
                : [...form.data.abilities, ability],
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.post(urls.account.tokens.store, {
            preserveScroll: true,
            onSuccess: () => form.reset('name'),
        });
    };

    const revoke = (token: ApiTokenRow): void => {
        if (!window.confirm(`Revoke the token "${token.name}"?`)) {
            return;
        }

        router.delete(urls.account.tokens.destroy(token.id), {
            preserveScroll: true,
        });
    };

    const copy = async (value: string): Promise<void> => {
        await navigator.clipboard.writeText(value);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    };

    return (
        <>
            <Head title="API tokens" />

            <h1 className="sr-only">API tokens</h1>

            <Heading
                variant="small"
                title="API tokens"
                description="Personal access tokens for the platform HTTP API. Treat them like passwords."
            />

            {newToken && (
                <Card className="border-success/30 bg-success-soft">
                    <CardHeader className="py-3">
                        <CardTitle className="text-sm font-medium">
                            Copy your new token
                        </CardTitle>
                        <CardDescription className="text-xs">
                            This is the only time the token is shown.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center gap-2">
                        <Input readOnly value={newToken} className="flex-1" />
                        <Button
                            variant="secondary"
                            onClick={() => void copy(newToken)}
                        >
                            <Copy />
                            {copied ? 'Copied' : 'Copy'}
                        </Button>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader className="border-b border-border/60 py-3">
                    <CardTitle className="flex items-center gap-2 text-sm font-medium">
                        <KeyRound className="size-4" />
                        {tokens.length}{' '}
                        {tokens.length === 1 ? 'token' : 'tokens'}
                    </CardTitle>
                    <CardDescription className="text-xs">
                        Tokens act as you. Revoke any you no longer use.
                    </CardDescription>
                </CardHeader>

                <CardContent className="divide-y divide-border/60 px-0">
                    {tokens.length === 0 ? (
                        <p className="text-muted-foreground px-4 py-6 text-sm">
                            You have no API tokens yet.
                        </p>
                    ) : (
                        tokens.map((token) => (
                            <div
                                key={token.id}
                                className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                            >
                                <div className="space-y-1">
                                    <p className="text-sm font-medium">
                                        {token.name}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        Created{' '}
                                        {token.created_at
                                            ? new Date(
                                                  token.created_at,
                                              ).toLocaleDateString()
                                            : '—'}
                                        {token.last_used_at
                                            ? ` · last used ${new Date(
                                                  token.last_used_at,
                                              ).toLocaleString()}`
                                            : ' · never used'}
                                    </p>
                                </div>

                                <div className="flex items-center gap-2">
                                    {token.abilities.map((ability) => (
                                        <Badge
                                            key={ability}
                                            variant="secondary"
                                        >
                                            {ability}
                                        </Badge>
                                    ))}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="text-destructive hover:text-destructive"
                                        onClick={() => revoke(token)}
                                    >
                                        <Trash2 />
                                        Revoke
                                    </Button>
                                </div>
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>


            <form onSubmit={submit} className="space-y-4">
                <Heading
                    variant="small"
                    title="Create a token"
                    description="Name it after the integration that will use it."
                />

                <div className="grid gap-2">
                    <Label htmlFor="token-name">Token name</Label>
                    <Input
                        id="token-name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        placeholder="Reporting pipeline"
                    />
                    {form.errors.name && (
                        <p className="text-destructive text-sm">
                            {form.errors.name}
                        </p>
                    )}
                </div>

                <div className="flex flex-wrap gap-4">
                    {abilities.map((ability) => (
                        <label
                            key={ability}
                            className="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                checked={form.data.abilities.includes(ability)}
                                onCheckedChange={() => toggleAbility(ability)}
                            />
                            {ability}
                        </label>
                    ))}
                </div>

                <Button type="submit" disabled={form.processing}>
                    <Plus />
                    Create token
                </Button>
            </form>
        </>
    );
}

AccountApiTokens.layout = {
    breadcrumbs: [
        {
            title: 'API tokens',
            href: urls.account.tokens.index,
        },
    ],
};


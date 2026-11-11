import { Head, Link, router, useForm } from '@inertiajs/react';
import { CircleCheck, CircleSlash, PackageSearch, Wallet } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { updateDetails } from '@/actions/App/Http/Controllers/Admin/PackageController';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { urls } from '@/lib/urls';
import { cn } from '@/lib/utils';
import { pricing } from '@/routes';
import type { WalletPackage } from '@/types';

type WalletPackageRow = WalletPackage & { enabled: boolean };
type PackageService = 'sms' | 'voice' | 'email';

type PackageEditForm = {
    name: string;
    price: string;
    popular: boolean;
    tagline: string;
    units: Record<PackageService, string>;
};

type Props = {
    packages: WalletPackageRow[];
    currency: string;
    limits: { min: number; max: number };
};

const packageServices: { key: PackageService; label: string }[] = [
    { key: 'sms', label: 'SMS parts' },
    { key: 'voice', label: 'Voice minutes' },
    { key: 'email', label: 'Emails' },
];

function money(value: number, currency: string): string {
    return `${currency} ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

export default function WalletPackages({ packages, currency, limits }: Props) {
    const [pending, setPending] = useState<string | null>(null);
    const [editing, setEditing] = useState<WalletPackageRow | null>(null);
    const [allocationsChanged, setAllocationsChanged] = useState(false);
    const editForm = useForm<PackageEditForm>({
        name: '',
        price: '',
        popular: false,
        tagline: '',
        units: { sms: '', voice: '', email: '' },
    });

    const onSale = packages.filter((pkg) => pkg.enabled).length;

    const toggle = (pkg: WalletPackageRow): void => {
        setPending(pkg.key);

        router.put(
            urls.admin.packages.update(pkg.key),
            { enabled: !pkg.enabled },
            {
                preserveScroll: true,
                onFinish: () => setPending(null),
            },
        );
    };

    const openEditor = (pkg: WalletPackageRow): void => {
        editForm.setData({
            name: pkg.name,
            price: pkg.price.toFixed(2),
            popular: pkg.popular,
            tagline: pkg.tagline,
            units: {
                sms: String(
                    pkg.units.find((unit) => unit.service === 'sms')
                        ?.quantity ?? 0,
                ),
                voice: String(
                    pkg.units.find((unit) => unit.service === 'voice')
                        ?.quantity ?? 0,
                ),
                email: String(
                    pkg.units.find((unit) => unit.service === 'email')
                        ?.quantity ?? 0,
                ),
            },
        });
        editForm.clearErrors();
        setAllocationsChanged(false);
        setEditing(pkg);
    };

    const closeEditor = (): void => {
        setEditing(null);
        setAllocationsChanged(false);
        editForm.reset();
        editForm.clearErrors();
    };

    const submitEdit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (editing === null) {
            return;
        }

        editForm.transform((data) => {
            const details = {
                ...data,
                price: Number(data.price),
            };

            if (!allocationsChanged) {
                return {
                    name: details.name,
                    price: details.price,
                    popular: details.popular,
                    tagline: details.tagline,
                };
            }

            return {
                ...details,
                units: {
                    sms: Number(data.units.sms),
                    voice: Number(data.units.voice),
                    email: Number(data.units.email),
                },
            };
        });

        editForm.patch(updateDetails.url({ package: editing.key }), {
            preserveScroll: true,
            onSuccess: closeEditor,
        });
    };

    return (
        <>
            <Head title="Wallet packages" />

            <div className="grid gap-6">
                <PageHeader
                    icon={PackageSearch}
                    title="Wallet packages"
                    description="The bundles a client buys to fund their wallet. Hiding a package keeps its price and allocation configured, but takes it off the price list, out of the wallet grid and out of checkout."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={pricing()}>View the price list</Link>
                        </Button>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard
                        title="Packages"
                        value={packages.length}
                        description="Configured in the billing config."
                        icon={Wallet}
                    />
                    <StatCard
                        title="On sale"
                        value={onSale}
                        description="Visible to clients today."
                        icon={CircleCheck}
                        tone="success"
                    />
                    <StatCard
                        title="Hidden"
                        value={packages.length - onSale}
                        description="Configured but not sold."
                        icon={CircleSlash}
                        tone="neutral"
                    />
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Catalogue</CardTitle>
                        <CardDescription>
                            Prices are quoted in {currency}. A package credits
                            its price to the wallet and buys the same money on
                            any one channel.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Package</TableHead>
                                    <TableHead>Price</TableHead>
                                    <TableHead>Includes</TableHead>
                                    <TableHead>Availability</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {packages.map((pkg) => (
                                    <TableRow
                                        key={pkg.key}
                                        className={cn(
                                            'transition-opacity',
                                            !pkg.enabled && 'opacity-70',
                                        )}
                                    >
                                        <TableCell>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">
                                                    {pkg.name}
                                                </span>
                                                {pkg.popular && (
                                                    <Badge variant="info">
                                                        Popular
                                                    </Badge>
                                                )}
                                                {!pkg.enabled && (
                                                    <Badge variant="muted">
                                                        Hidden
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {pkg.key}
                                                {pkg.tagline
                                                    ? ` · ${pkg.tagline}`
                                                    : ''}
                                            </p>
                                        </TableCell>

                                        <TableCell className="font-medium whitespace-nowrap tabular-nums">
                                            {money(pkg.price, currency)}
                                        </TableCell>

                                        <TableCell className="text-sm text-muted-foreground">
                                            {pkg.units.length > 0
                                                ? pkg.units
                                                      .map(
                                                          (unit) =>
                                                              `${unit.formatted} ${unit.label}`,
                                                      )
                                                      .join(' · ')
                                                : 'No allocation'}
                                        </TableCell>

                                        <TableCell>
                                            <Badge
                                                variant={
                                                    pkg.enabled
                                                        ? 'success'
                                                        : 'muted'
                                                }
                                            >
                                                <span className="size-1.5 rounded-full bg-current" />
                                                {pkg.enabled
                                                    ? 'On sale'
                                                    : 'Hidden'}
                                            </Badge>
                                        </TableCell>

                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        openEditor(pkg)
                                                    }
                                                >
                                                    Edit
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant={
                                                        pkg.enabled
                                                            ? 'outline'
                                                            : 'default'
                                                    }
                                                    aria-pressed={pkg.enabled}
                                                    disabled={
                                                        pending === pkg.key
                                                    }
                                                    onClick={() => toggle(pkg)}
                                                >
                                                    {pending === pkg.key
                                                        ? 'Saving…'
                                                        : pkg.enabled
                                                          ? 'Disable'
                                                          : 'Enable'}
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Dialog
                    open={editing !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeEditor();
                        }
                    }}
                >
                    <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                        {editing !== null && (
                            <>
                                <DialogHeader>
                                    <DialogTitle>
                                        Edit {editing.name}
                                    </DialogTitle>
                                    <DialogDescription>
                                        Update the package shown to clients.
                                        Allocations stay rate-based or retain
                                        their configured values unless you
                                        change an allocation below.
                                    </DialogDescription>
                                </DialogHeader>

                                <form
                                    className="grid gap-4"
                                    onSubmit={submitEdit}
                                >
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="package-name">
                                                Package name
                                            </Label>
                                            <Input
                                                id="package-name"
                                                value={editForm.data.name}
                                                maxLength={120}
                                                required
                                                onChange={(event) =>
                                                    editForm.setData(
                                                        'name',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={editForm.errors.name}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="package-price">
                                                Price ({currency})
                                            </Label>
                                            <Input
                                                id="package-price"
                                                type="number"
                                                min="0.01"
                                                max={limits.max}
                                                step="0.01"
                                                value={editForm.data.price}
                                                required
                                                onChange={(event) =>
                                                    editForm.setData(
                                                        'price',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={editForm.errors.price}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="package-tagline">
                                            Tagline
                                        </Label>
                                        <Input
                                            id="package-tagline"
                                            value={editForm.data.tagline}
                                            maxLength={255}
                                            required
                                            onChange={(event) =>
                                                editForm.setData(
                                                    'tagline',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={editForm.errors.tagline}
                                        />
                                    </div>

                                    <div className="flex items-start gap-3 rounded-lg border p-3">
                                        <Checkbox
                                            id="package-popular"
                                            checked={editForm.data.popular}
                                            className="mt-1"
                                            onCheckedChange={(checked) =>
                                                editForm.setData(
                                                    'popular',
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <div className="grid gap-1">
                                            <Label htmlFor="package-popular">
                                                Mark as popular
                                            </Label>
                                            <p className="text-sm text-muted-foreground">
                                                Highlight this package on the
                                                public price list.
                                            </p>
                                        </div>
                                    </div>

                                    <fieldset className="grid gap-3 rounded-lg border p-4">
                                        <legend className="px-1 text-sm font-medium">
                                            Custom allocations
                                        </legend>
                                        <p className="text-sm text-muted-foreground">
                                            Leave these unchanged to preserve
                                            the current allocation behavior.
                                            Editing any value saves all three as
                                            fixed quantities.
                                        </p>
                                        <div className="grid gap-3 sm:grid-cols-3">
                                            {packageServices.map((service) => (
                                                <div
                                                    key={service.key}
                                                    className="grid gap-2"
                                                >
                                                    <Label
                                                        htmlFor={`package-${service.key}`}
                                                    >
                                                        {service.label}
                                                    </Label>
                                                    <Input
                                                        id={`package-${service.key}`}
                                                        type="number"
                                                        min="0"
                                                        step="1"
                                                        required
                                                        value={
                                                            editForm.data.units[
                                                                service.key
                                                            ]
                                                        }
                                                        onChange={(event) => {
                                                            editForm.setData(
                                                                'units',
                                                                {
                                                                    ...editForm
                                                                        .data
                                                                        .units,
                                                                    [service.key]:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                },
                                                            );
                                                            setAllocationsChanged(
                                                                true,
                                                            );
                                                        }}
                                                    />
                                                    <InputError
                                                        message={
                                                            editForm.errors[
                                                                `units.${service.key}`
                                                            ]
                                                        }
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                        <InputError
                                            message={editForm.errors.units}
                                        />
                                    </fieldset>

                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={closeEditor}
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={editForm.processing}
                                        >
                                            {editForm.processing
                                                ? 'Saving…'
                                                : 'Save package'}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </>
                        )}
                    </DialogContent>
                </Dialog>

                <p className="text-xs leading-relaxed text-muted-foreground">
                    A disabled package is refused at checkout, so an open
                    browser tab cannot buy it once it has been hidden. Clients
                    can still top up a custom amount between{' '}
                    {money(limits.min, currency)} and{' '}
                    {money(limits.max, currency)}.
                </p>
            </div>
        </>
    );
}

WalletPackages.layout = {
    breadcrumbs: [
        { title: 'Wallet packages', href: urls.admin.packages.index },
    ],
};

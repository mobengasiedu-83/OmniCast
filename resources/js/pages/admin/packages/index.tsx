import { Head, Link, router } from '@inertiajs/react';
import { CircleCheck, CircleSlash, PackageSearch, Wallet } from 'lucide-react';
import { useState } from 'react';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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

type Props = {
    packages: WalletPackageRow[];
    currency: string;
    limits: { min: number; max: number };
};

function money(value: number, currency: string): string {
    return `${currency} ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

export default function WalletPackages({ packages, currency, limits }: Props) {
    const [pending, setPending] = useState<string | null>(null);

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
                                        Change
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
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant={
                                                    pkg.enabled
                                                        ? 'outline'
                                                        : 'default'
                                                }
                                                aria-pressed={pkg.enabled}
                                                disabled={pending === pkg.key}
                                                onClick={() => toggle(pkg)}
                                            >
                                                {pending === pkg.key
                                                    ? 'Saving…'
                                                    : pkg.enabled
                                                      ? 'Disable'
                                                      : 'Enable'}
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

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

import { usePage } from '@inertiajs/react';
import BrandMark from '@/components/brand-mark';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <BrandMark
                className="size-8 rounded-[0.6rem]"
                glyphClassName="size-4"
            />
            <div className="ml-1 grid flex-1 text-left">
                <span className="mb-0.5 truncate text-sm leading-tight font-semibold">
                    {name}
                </span>
                <span className="truncate text-[10px] font-medium tracking-[0.16em] text-muted-foreground uppercase">
                    Voice &amp; SMS
                </span>
            </div>
        </>
    );
}

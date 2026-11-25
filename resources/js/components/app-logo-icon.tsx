import { useBrand } from '@/lib/brand';
import { cn } from '@/lib/utils';
import { CalendarCheck } from 'lucide-react';

/**
 * The company's logo, or a badge with its initials in the accent color when no
 * logo is uploaded (a calendar icon before the company name is known).
 */
export default function AppLogoIcon({ className }: { className?: string }) {
    const brand = useBrand();

    if (brand.logo) {
        return <img src={brand.logo} alt={brand.name ? `Logo ${brand.name}` : 'Logo'} className={cn('object-contain', className)} />;
    }

    return (
        <span
            aria-hidden="true"
            className={cn('flex shrink-0 items-center justify-center rounded-lg font-bold text-white', className)}
            style={{ backgroundColor: brand.color }}
        >
            {brand.initials || <CalendarCheck className="h-1/2 w-1/2" />}
        </span>
    );
}

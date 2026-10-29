import { Link } from '@inertiajs/react';
import { CircleDollarSign, Receipt } from 'lucide-react';

/** The driver filter value for records made at home (Venta/Cobro en casa); see DayScope::HOUSE_FILTER. */
export const HOUSE_FILTER = 'house';

/** The filters both tabs share, so switching tabs keeps the same day, driver and customer. */
export interface SharedFilters {
    search?: string | null;
    date?: string | null;
    repartidor_id?: string | null;
}

export function SalesPaymentsTabs({ current, filters }: { current: 'sales' | 'payments'; filters: SharedFilters }) {
    const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '' && value != null));
    const tabs = [
        { key: 'sales', label: 'Ventas', icon: Receipt, href: route('sales.index', query) },
        { key: 'payments', label: 'Cobros', icon: CircleDollarSign, href: route('payments.index', query) },
    ] as const;

    return (
        <div className="flex items-center gap-6 mb-6 border-b border-hairline">
            {tabs.map((tab) => (
                <Link
                    key={tab.key}
                    href={tab.href}
                    aria-current={current === tab.key ? 'page' : undefined}
                    className={`pb-3 text-sm font-medium tracking-wide transition-colors border-b-4 ${
                        current === tab.key
                            ? 'border-sky-600 text-ink'
                            : 'border-transparent text-ink-muted hover:text-ink hover:border-hairline/60'
                    }`}
                >
                    <span className="flex items-center gap-2">
                        <tab.icon className="w-4 h-4" />
                        {tab.label}
                    </span>
                </Link>
            ))}
        </div>
    );
}

export function DriverFilterSelect({
    value,
    onChange,
    drivers,
}: {
    value: string;
    onChange: (value: string) => void;
    drivers: { id: number; name: string }[];
}) {
    return (
        <select
            value={value}
            onChange={(e) => onChange(e.target.value)}
            aria-label="Repartidor"
            className="w-full py-2 px-3 text-sm font-medium border border-hairline/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent bg-canvas text-ink shadow-sm transition-all"
        >
            <option value="">Todos los repartidores</option>
            {drivers.map((driver) => (
                <option key={driver.id} value={driver.id}>
                    {driver.name}
                </option>
            ))}
            <option value={HOUSE_FILTER}>En casa</option>
        </select>
    );
}

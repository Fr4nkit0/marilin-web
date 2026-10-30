import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Filters = Record<string, string>;

/**
 * A list page's filters kept in the URL. Values left at their default are dropped from the query,
 * so the server applies its own default (for instance, today's date).
 */
export function useListFilters<F extends Filters>(routeName: string, initial: F, defaults: F, options: { preserveScroll?: boolean } = {}) {
    const [values, setValues] = useState<F>(initial);

    // After a visit the server's echo is the truth (e.g. an empty date comes back as today).
    const echoed = JSON.stringify(initial);
    useEffect(() => setValues(JSON.parse(echoed) as F), [echoed]);

    const visit = (next: F) => {
        const query = Object.fromEntries(
            Object.entries(next).map(([key, value]) => [key, value === '' || value === defaults[key] ? undefined : value]),
        );

        router.get(route(routeName), query, {
            preserveState: true,
            ...(options.preserveScroll ? { preserveScroll: true } : {}),
            replace: true,
        });
    };

    return {
        values,
        /** Changes a filter and applies it right away (selects, dates). */
        set: (key: keyof F, value: string) => {
            const next = { ...values, [key]: value };
            setValues(next);
            visit(next);
        },
        /** Changes several filters and applies them together (a "clear filters" button). */
        setMany: (changes: Partial<F>) => {
            const next = { ...values, ...changes };
            setValues(next);
            visit(next);
        },
        /** Changes a filter without applying it (a search box while typing). */
        draft: (key: keyof F, value: string) => setValues({ ...values, [key]: value }),
        /** Applies every filter as it is now (the search form's submit). */
        submit: () => visit(values),
    };
}

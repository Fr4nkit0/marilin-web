import { Link } from '@inertiajs/react';
import type { PaginatedData } from '@/types';

interface Props {
    page: PaginatedData<unknown>;
    /** What the list holds, for the total ("ventas", "cobros"); left out when not given. */
    noun?: string;
}

/** The page links of a paginated list; nothing at all when everything fits in one page. */
export default function Pagination({ page, noun }: Props) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <div className="p-4 flex items-center justify-between text-sm text-ink-muted bg-canvas border-t border-hairline">
            <span>
                Página {page.current_page} de {page.last_page}
                {noun ? ` (${page.total} ${noun})` : ''}
            </span>
            <div className="flex items-center gap-1">
                {page.links.map((link, index) => (
                    <Link
                        key={index}
                        href={link.url || '#'}
                        preserveState
                        aria-current={link.active ? 'page' : undefined}
                        className={`px-3 py-1.5 rounded-lg border text-sm font-medium transition-colors ${
                            link.active
                                ? 'bg-sky-600 text-white border-sky-600 shadow-sm'
                                : !link.url
                                  ? 'text-ink-subtle border-hairline pointer-events-none'
                                  : 'text-ink-secondary border-hairline/60 hover:bg-canvas-soft'
                        }`}
                        // Laravel's labels carry entities such as &laquo;.
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
            </div>
        </div>
    );
}

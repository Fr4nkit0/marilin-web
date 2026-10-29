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

    const lastIndex = page.links.length - 1;

    // Laravel always sends "previous" first and "next" last, labeled in the server's locale; the panel speaks Spanish.
    const labelOf = (index: number, label: string): string => {
        if (index === 0) return '&laquo; Anterior';
        if (index === lastIndex) return 'Siguiente &raquo;';
        return label;
    };

    return (
        <div className="p-4 flex flex-wrap items-center justify-between gap-3 text-sm text-ink-muted bg-canvas border-t border-hairline">
            <span className="whitespace-nowrap">
                Página {page.current_page} de {page.last_page}
                {noun ? ` (${page.total} ${noun})` : ''}
            </span>
            <div className="flex items-center gap-1">
                {page.links.map((link, index) => {
                    const isPageNumber = index !== 0 && index !== lastIndex;

                    return (
                        <Link
                            key={index}
                            href={link.url || '#'}
                            preserveState
                            aria-current={link.active ? 'page' : undefined}
                            className={`px-3 py-1.5 rounded-lg border text-sm font-medium whitespace-nowrap transition-colors ${
                                // On a phone the numbers other than the current page give way to previous/next.
                                isPageNumber && !link.active ? 'hidden sm:inline-block' : 'inline-block'
                            } ${
                                link.active
                                    ? 'bg-sky-600 text-white border-sky-600 shadow-sm dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400'
                                    : !link.url
                                      ? 'text-ink-subtle border-hairline pointer-events-none'
                                      : 'text-ink-secondary border-hairline/60 hover:bg-canvas-soft'
                            }`}
                            // Page labels may carry entities such as &laquo;.
                            dangerouslySetInnerHTML={{ __html: labelOf(index, link.label) }}
                        />
                    );
                })}
            </div>
        </div>
    );
}

/** One page of a list that is already whole in the browser; the page is kept between 1 and the last page. */
export function pageOf<T>(items: T[], page: number, perPage: number): { rows: T[]; page: number; lastPage: number } {
    const lastPage = Math.max(1, Math.ceil(items.length / perPage));
    const current = Math.min(Math.max(1, page), lastPage);

    return { rows: items.slice((current - 1) * perPage, current * perPage), page: current, lastPage };
}

interface Props {
    page: number;
    lastPage: number;
    total: number;
    /** What the list holds, for the total ("paradas"). */
    noun: string;
    onPage: (page: number) => void;
}

const LINK = 'inline-flex min-h-11 sm:min-h-0 items-center px-3 py-1.5 rounded-lg border text-sm font-medium whitespace-nowrap transition-colors cursor-pointer';

/**
 * The page buttons of a list paginated in the browser, looking like `ui/Pagination` (the server's pages).
 * Nothing at all when everything fits in one page.
 */
export default function LocalPagination({ page, lastPage, total, noun, onPage }: Props) {
    if (lastPage <= 1) {
        return null;
    }

    const pages = Array.from({ length: lastPage }, (_, index) => index + 1);

    return (
        <div className="p-4 flex flex-wrap items-center justify-between gap-3 text-sm text-ink-muted bg-canvas border-t border-hairline">
            <span className="whitespace-nowrap">
                Página {page} de {lastPage} ({total} {noun})
            </span>
            <div className="flex items-center gap-1">
                <button
                    type="button"
                    onClick={() => onPage(page - 1)}
                    disabled={page === 1}
                    className={`${LINK} text-ink-secondary border-hairline/60 hover:bg-canvas-soft disabled:text-ink-subtle disabled:border-hairline disabled:pointer-events-none`}
                >
                    « Anterior
                </button>
                {pages.map((number) => (
                    <button
                        key={number}
                        type="button"
                        onClick={() => onPage(number)}
                        aria-current={number === page ? 'page' : undefined}
                        className={`${LINK} ${number === page ? 'bg-sky-600 text-white border-sky-600 shadow-sm dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500' : 'hidden sm:inline-flex text-ink-secondary border-hairline/60 hover:bg-canvas-soft'}`}
                    >
                        {number}
                    </button>
                ))}
                <button
                    type="button"
                    onClick={() => onPage(page + 1)}
                    disabled={page === lastPage}
                    className={`${LINK} text-ink-secondary border-hairline/60 hover:bg-canvas-soft disabled:text-ink-subtle disabled:border-hairline disabled:pointer-events-none`}
                >
                    Siguiente »
                </button>
            </div>
        </div>
    );
}

import { Fragment, ReactNode } from 'react';

export function PageHeader({
    title,
    description,
    actions,
    breadcrumbs,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
    breadcrumbs?: { label: string; href?: string }[];
}) {
    return (
        <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                {breadcrumbs && breadcrumbs.length > 0 && (
                    <div className="mb-2 flex items-center gap-2 text-xs font-medium text-slate-500">
                        {breadcrumbs.map((b, idx) => (
                            <Fragment key={idx}>
                                {idx > 0 && <span className="text-slate-300">/</span>}
                                {b.href ? (
                                    <a href={b.href} className="hover:text-slate-900 transition-colors">
                                        {b.label}
                                    </a>
                                ) : (
                                    <span className="text-slate-700">{b.label}</span>
                                )}
                            </Fragment>
                        ))}
                    </div>
                )}
                <h1 className="text-2xl sm:text-3xl font-semibold text-slate-900 tracking-tight">{title}</h1>
                {description && <p className="text-sm text-slate-500 mt-1">{description}</p>}
            </div>
            {actions && <div className="flex items-center gap-3">{actions}</div>}
        </div>
    );
}

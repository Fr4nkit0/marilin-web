import React, { ReactNode } from 'react';
import { X } from 'lucide-react';
import Modal from '@/ui/Modal';
import {
    DashboardContainerRow,
    DashboardDetailKind,
    DashboardDetails,
    DashboardPaymentRow,
    DashboardSaleRow,
    DashboardStopRow,
} from '../types';
import { money, stopStatusLabel, type StopStatus } from '@/lib/format';

interface Props {
    kind: DashboardDetailKind | null;
    details: DashboardDetails;
    onClose: () => void;
}

const TITLES: Record<DashboardDetailKind, { title: string; description: string }> = {
    sales: { title: 'Ventas del día', description: 'Cada venta registrada, con su cliente y repartidor.' },
    payments: { title: 'Cobranza recaudada', description: 'Cobros confirmados en efectivo y por transferencia.' },
    stops: { title: 'Paradas de ruta', description: 'Las paradas de cada repartidor y en qué estado están.' },
    containers: { title: 'Movimiento de envases', description: 'Envases entregados y retirados en el día.' },
};

const STOP_STATUS_STYLE: Record<StopStatus, string> = {
    PENDING: 'bg-canvas-soft text-ink-secondary border-hairline/60',
    IN_PROGRESS: 'bg-sky-50 dark:bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-200/60 dark:border-sky-500/30',
    FINISHED: 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-200/60 dark:border-emerald-500/30',
    RESCHEDULED: 'bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-200/60 dark:border-amber-500/30',
    CANCELLED: 'bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-200/60 dark:border-rose-500/30',
};

const thClass = 'px-4 py-3 text-xs font-medium text-ink tracking-wide';
const tdClass = 'px-4 py-3 text-ink-secondary';


function time(at: string | null): string {
    return at ? at.slice(11, 16) : '—';
}

function Badge({ label, style }: { label: string; style: string }) {
    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border ${style}`}>
            {label}
        </span>
    );
}

function Table({ head, children, foot }: { head: ReactNode; children: ReactNode; foot?: ReactNode }) {
    return (
        <div className="table-scroll">
            <table className="w-full text-left text-sm">
                <thead className="border-b border-hairline">{head}</thead>
                <tbody className="divide-y divide-hairline">{children}</tbody>
                {foot && <tfoot className="border-t border-hairline">{foot}</tfoot>}
            </table>
        </div>
    );
}

function EmptyRow({ colSpan }: { colSpan: number }) {
    return (
        <tr>
            <td colSpan={colSpan} className="px-4 py-8 text-center text-sm text-ink-muted">
                <span className="table-empty">No hay registros en este día.</span>
            </td>
        </tr>
    );
}

function TotalRow({ label, value, colSpan }: { label: string; value: string; colSpan: number }) {
    return (
        <tr>
            <td colSpan={colSpan} className="px-4 py-3 text-sm font-medium text-ink-secondary">
                {label}
            </td>
            <td className="px-4 py-3 text-right font-semibold text-ink whitespace-nowrap">{value}</td>
        </tr>
    );
}

function SalesTable({ rows }: { rows: DashboardSaleRow[] }) {
    const total = rows.reduce((sum, row) => sum + row.amount, 0);

    return (
        <Table
            head={
                <tr>
                    <th className={thClass}>Hora</th>
                    <th className={thClass}>Cliente</th>
                    <th className={thClass}>Repartidor</th>
                    <th className={thClass}>Pago</th>
                    <th className={`${thClass} text-right`}>Monto</th>
                </tr>
            }
            foot={<TotalRow colSpan={4} label={`Total (${rows.length} ${rows.length === 1 ? 'venta' : 'ventas'})`} value={money(total)} />}
        >
            {rows.length === 0 ? (
                <EmptyRow colSpan={5} />
            ) : (
                rows.map((row) => (
                    <tr key={row.id}>
                        <td className={`${tdClass} whitespace-nowrap`}>{time(row.at)}</td>
                        <td className={`${tdClass} font-medium text-ink`}>{row.customer ?? '—'}</td>
                        <td className={tdClass}>{row.driver ?? '—'}</td>
                        <td className={tdClass}>
                            {row.credit ? (
                                <Badge label="Cta Cte" style="bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-200/60 dark:border-amber-500/30" />
                            ) : (
                                <Badge label="Contado" style="bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-200/60 dark:border-emerald-500/30" />
                            )}
                        </td>
                        <td className={`${tdClass} text-right font-semibold text-ink`}>{money(row.amount)}</td>
                    </tr>
                ))
            )}
        </Table>
    );
}

function PaymentsTable({ rows }: { rows: DashboardPaymentRow[] }) {
    const cash = rows.filter((row) => row.method === 'CASH').reduce((sum, row) => sum + row.amount, 0);
    const transfer = rows.filter((row) => row.method === 'TRANSFER').reduce((sum, row) => sum + row.amount, 0);

    return (
        <Table
            head={
                <tr>
                    <th className={thClass}>Hora</th>
                    <th className={thClass}>Cliente</th>
                    <th className={thClass}>Repartidor</th>
                    <th className={thClass}>Medio</th>
                    <th className={`${thClass} text-right`}>Monto</th>
                </tr>
            }
            foot={
                <>
                    <TotalRow colSpan={4} label="Efectivo" value={money(cash)} />
                    <TotalRow colSpan={4} label="Transferencias" value={money(transfer)} />
                    <TotalRow colSpan={4} label="Total recaudado" value={money(cash + transfer)} />
                </>
            }
        >
            {rows.length === 0 ? (
                <EmptyRow colSpan={5} />
            ) : (
                rows.map((row) => (
                    <tr key={row.id}>
                        <td className={`${tdClass} whitespace-nowrap`}>{time(row.at)}</td>
                        <td className={`${tdClass} font-medium text-ink`}>{row.customer ?? '—'}</td>
                        <td className={tdClass}>{row.driver ?? '—'}</td>
                        <td className={tdClass}>
                            {row.method === 'CASH' ? (
                                <Badge label="Efectivo" style="bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-200/60 dark:border-emerald-500/30" />
                            ) : (
                                <Badge label="Transferencia" style="bg-sky-50 dark:bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-200/60 dark:border-sky-500/30" />
                            )}
                        </td>
                        <td className={`${tdClass} text-right font-semibold text-ink`}>{money(row.amount)}</td>
                    </tr>
                ))
            )}
        </Table>
    );
}

function StopsTable({ rows }: { rows: DashboardStopRow[] }) {
    const finished = rows.filter((row) => row.done).length;

    return (
        <Table
            head={
                <tr>
                    <th className={thClass}>Repartidor</th>
                    <th className={thClass}>Hora</th>
                    <th className={thClass}>Cliente</th>
                    <th className={`${thClass} text-right`}>Estado</th>
                </tr>
            }
            foot={<TotalRow colSpan={3} label="Terminadas" value={`${finished} / ${rows.length}`} />}
        >
            {rows.length === 0 ? (
                <EmptyRow colSpan={4} />
            ) : (
                rows.map((row) => {
                    const status = { label: stopStatusLabel(row.status), style: STOP_STATUS_STYLE[row.status] };

                    return (
                        <tr key={row.id}>
                            <td className={tdClass}>{row.driver ?? '—'}</td>
                            <td className={`${tdClass} whitespace-nowrap`}>{row.time ?? '—'}</td>
                            <td className={`${tdClass} font-medium text-ink`}>{row.customer ?? '—'}</td>
                            <td className={`${tdClass} text-right`}>
                                <Badge label={status.label} style={status.style} />
                            </td>
                        </tr>
                    );
                })
            )}
        </Table>
    );
}

function ContainersTable({ rows }: { rows: DashboardContainerRow[] }) {
    const delivered = rows.filter((row) => row.movement === 'DELIVERED').reduce((sum, row) => sum + row.quantity, 0);
    const returned = rows.filter((row) => row.movement === 'RETURNED').reduce((sum, row) => sum + row.quantity, 0);

    return (
        <Table
            head={
                <tr>
                    <th className={thClass}>Hora</th>
                    <th className={thClass}>Cliente</th>
                    <th className={thClass}>Repartidor</th>
                    <th className={thClass}>Envase</th>
                    <th className={`${thClass} text-right`}>Cantidad</th>
                </tr>
            }
            foot={<TotalRow colSpan={4} label="Entregados / retirados" value={`+${delivered} / -${returned}`} />}
        >
            {rows.length === 0 ? (
                <EmptyRow colSpan={5} />
            ) : (
                rows.map((row) => {
                    const isDelivered = row.movement === 'DELIVERED';

                    return (
                        <tr key={row.id}>
                            <td className={`${tdClass} whitespace-nowrap`}>{time(row.at)}</td>
                            <td className={`${tdClass} font-medium text-ink`}>{row.customer ?? '—'}</td>
                            <td className={tdClass}>{row.driver ?? '—'}</td>
                            <td className={tdClass}>{row.container ?? '—'}</td>
                            <td
                                className={`${tdClass} text-right font-semibold whitespace-nowrap ${
                                    isDelivered ? 'text-emerald-700 dark:text-emerald-300' : 'text-orange-700 dark:text-orange-300'
                                }`}
                            >
                                {isDelivered ? `Entregó +${row.quantity}` : `Retiró -${row.quantity}`}
                            </td>
                        </tr>
                    );
                })
            )}
        </Table>
    );
}

export default function DashboardDetailModal({ kind, details, onClose }: Props) {
    return (
        <Modal show={kind !== null} onClose={onClose} maxWidth="2xl">
            {kind && (
                <div>
                    <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-hairline">
                        <div>
                            <h2 className="text-lg font-semibold text-ink">{TITLES[kind].title}</h2>
                            <p className="text-sm text-ink-muted mt-1">{TITLES[kind].description}</p>
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            aria-label="Cerrar"
                            className="p-1.5 rounded-lg text-ink-subtle hover:text-ink-secondary hover:bg-surface-1 transition cursor-pointer"
                        >
                            <X className="w-5 h-5" />
                        </button>
                    </div>
                    <div className="max-h-[60vh] overflow-auto">
                        {kind === 'sales' && <SalesTable rows={details.sales} />}
                        {kind === 'payments' && <PaymentsTable rows={details.payments} />}
                        {kind === 'stops' && <StopsTable rows={details.stops} />}
                        {kind === 'containers' && <ContainersTable rows={details.containers} />}
                    </div>
                </div>
            )}
        </Modal>
    );
}

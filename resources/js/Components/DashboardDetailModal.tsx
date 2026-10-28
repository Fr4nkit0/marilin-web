import React, { ReactNode } from 'react';
import { X } from 'lucide-react';
import Modal from '@/Components/Modal';
import {
    DashboardContainerRow,
    DashboardDetailKind,
    DashboardDetails,
    DashboardPaymentRow,
    DashboardSaleRow,
    DashboardStopRow,
} from '@/types/dashboard';
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
    PENDING: 'bg-slate-50 text-slate-600 border-gray-200/60',
    IN_PROGRESS: 'bg-blue-50 text-blue-700 border-blue-200/60',
    FINISHED: 'bg-emerald-50 text-emerald-700 border-emerald-200/60',
    RESCHEDULED: 'bg-amber-50 text-amber-700 border-amber-200/60',
    CANCELLED: 'bg-rose-50 text-rose-700 border-rose-200/60',
};

const thClass = 'px-4 py-3 text-xs font-medium text-slate-900 tracking-wide';
const tdClass = 'px-4 py-3 text-slate-700';


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
        <table className="w-full text-left text-sm">
            <thead className="border-b border-gray-100">{head}</thead>
            <tbody className="divide-y divide-gray-50">{children}</tbody>
            {foot && <tfoot className="border-t border-gray-200">{foot}</tfoot>}
        </table>
    );
}

function EmptyRow({ colSpan }: { colSpan: number }) {
    return (
        <tr>
            <td colSpan={colSpan} className="px-4 py-8 text-center text-sm text-slate-500">
                No hay registros en este día.
            </td>
        </tr>
    );
}

function TotalRow({ label, value, colSpan }: { label: string; value: string; colSpan: number }) {
    return (
        <tr>
            <td colSpan={colSpan} className="px-4 py-3 text-sm font-medium text-slate-600">
                {label}
            </td>
            <td className="px-4 py-3 text-right font-semibold text-slate-900 whitespace-nowrap">{value}</td>
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
                        <td className={`${tdClass} font-medium text-slate-900`}>{row.customer ?? '—'}</td>
                        <td className={tdClass}>{row.driver ?? '—'}</td>
                        <td className={tdClass}>
                            {row.credit ? (
                                <Badge label="Cta Cte" style="bg-amber-50 text-amber-700 border-amber-200/60" />
                            ) : (
                                <Badge label="Contado" style="bg-emerald-50 text-emerald-700 border-emerald-200/60" />
                            )}
                        </td>
                        <td className={`${tdClass} text-right font-semibold text-slate-900`}>{money(row.amount)}</td>
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
                        <td className={`${tdClass} font-medium text-slate-900`}>{row.customer ?? '—'}</td>
                        <td className={tdClass}>{row.driver ?? '—'}</td>
                        <td className={tdClass}>
                            {row.method === 'CASH' ? (
                                <Badge label="Efectivo" style="bg-emerald-50 text-emerald-700 border-emerald-200/60" />
                            ) : (
                                <Badge label="Transferencia" style="bg-sky-50 text-sky-700 border-sky-200/60" />
                            )}
                        </td>
                        <td className={`${tdClass} text-right font-semibold text-slate-900`}>{money(row.amount)}</td>
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
                            <td className={`${tdClass} font-medium text-slate-900`}>{row.customer ?? '—'}</td>
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
                            <td className={`${tdClass} font-medium text-slate-900`}>{row.customer ?? '—'}</td>
                            <td className={tdClass}>{row.driver ?? '—'}</td>
                            <td className={tdClass}>{row.container ?? '—'}</td>
                            <td
                                className={`${tdClass} text-right font-semibold whitespace-nowrap ${
                                    isDelivered ? 'text-emerald-700' : 'text-orange-700'
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
                    <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-gray-100">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">{TITLES[kind].title}</h2>
                            <p className="text-sm text-slate-500 mt-1">{TITLES[kind].description}</p>
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            aria-label="Cerrar"
                            className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition cursor-pointer"
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

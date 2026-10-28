import React from 'react';
import { X } from 'lucide-react';
import Modal from '@/ui/Modal';
import {
    CashCloseContainerRow,
    CashCloseCreditRow,
    CashCloseDetailKind,
    CashCloseDetails,
    CashCloseOrigin,
    CashClosePaymentRow,
} from '../types';
import { money } from '@/lib/format';

interface Props {
    kind: CashCloseDetailKind | null;
    details: CashCloseDetails;
    onClose: () => void;
}

const TITLES: Record<CashCloseDetailKind, { title: string; description: string }> = {
    cash: { title: 'Rendición en efectivo', description: 'Cobros en efectivo que forman el total a rendir.' },
    transfer: { title: 'Transferencias verificadas', description: 'Cobros por transferencia de la jornada.' },
    credit: { title: 'Ventas a crédito', description: 'Ventas fiadas que suman al saldo deudor de cada cliente.' },
    containers: { title: 'Balance de envases', description: 'Envases entregados y retirados en la jornada.' },
};

const ORIGIN_LABELS: Record<CashCloseOrigin, string> = {
    stop: 'Parada de ruta',
    loose: 'Ficha del cliente',
};

const thClass = 'px-4 py-3 text-xs font-medium text-slate-900 tracking-wide';
const tdClass = 'px-4 py-3 text-slate-700';


function time(at: string | null): string {
    return at ? at.slice(11, 16) : '—';
}

function OriginBadge({ origin }: { origin: CashCloseOrigin }) {
    const style =
        origin === 'stop'
            ? 'bg-sky-50 text-sky-700 border-sky-200/60'
            : 'bg-slate-50 text-slate-600 border-gray-200/60';

    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border ${style}`}>
            {ORIGIN_LABELS[origin]}
        </span>
    );
}

function EmptyRow({ colSpan }: { colSpan: number }) {
    return (
        <tr>
            <td colSpan={colSpan} className="px-4 py-8 text-center text-sm text-slate-500">
                No hay registros en esta jornada.
            </td>
        </tr>
    );
}

function PaymentTable({ rows }: { rows: CashClosePaymentRow[] }) {
    const total = rows.reduce((sum, row) => sum + row.amount, 0);

    return (
        <table className="w-full text-left text-sm">
            <thead className="border-b border-gray-100">
                <tr>
                    <th className={thClass}>Hora</th>
                    <th className={thClass}>Cliente</th>
                    <th className={thClass}>Concepto</th>
                    <th className={thClass}>Origen</th>
                    <th className={`${thClass} text-right`}>Monto</th>
                </tr>
            </thead>
            <tbody className="divide-y divide-gray-50">
                {rows.length === 0 ? (
                    <EmptyRow colSpan={5} />
                ) : (
                    rows.map((row) => (
                        <tr key={row.id}>
                            <td className={`${tdClass} whitespace-nowrap`}>{time(row.at)}</td>
                            <td className={`${tdClass} font-medium text-slate-900`}>{row.customer ?? '—'}</td>
                            <td className={tdClass}>{row.concept}</td>
                            <td className={tdClass}>
                                <OriginBadge origin={row.origin} />
                            </td>
                            <td className={`${tdClass} text-right font-semibold text-slate-900`}>{money(row.amount)}</td>
                        </tr>
                    ))
                )}
            </tbody>
            <tfoot className="border-t border-gray-200">
                <tr>
                    <td colSpan={4} className="px-4 py-3 text-sm font-medium text-slate-600">
                        Total ({rows.length} {rows.length === 1 ? 'cobro' : 'cobros'})
                    </td>
                    <td className="px-4 py-3 text-right font-semibold text-slate-900">{money(total)}</td>
                </tr>
            </tfoot>
        </table>
    );
}

function CreditTable({ rows }: { rows: CashCloseCreditRow[] }) {
    const total = rows.reduce((sum, row) => sum + row.amount, 0);

    return (
        <table className="w-full text-left text-sm">
            <thead className="border-b border-gray-100">
                <tr>
                    <th className={thClass}>Hora</th>
                    <th className={thClass}>Cliente</th>
                    <th className={thClass}>Venta</th>
                    <th className={thClass}>Origen</th>
                    <th className={`${thClass} text-right`}>Monto</th>
                </tr>
            </thead>
            <tbody className="divide-y divide-gray-50">
                {rows.length === 0 ? (
                    <EmptyRow colSpan={5} />
                ) : (
                    rows.map((row) => (
                        <tr key={row.id}>
                            <td className={`${tdClass} whitespace-nowrap`}>{time(row.at)}</td>
                            <td className={`${tdClass} font-medium text-slate-900`}>{row.customer ?? '—'}</td>
                            <td className={tdClass}>#{row.id}</td>
                            <td className={tdClass}>
                                <OriginBadge origin={row.origin} />
                            </td>
                            <td className={`${tdClass} text-right font-semibold text-slate-900`}>{money(row.amount)}</td>
                        </tr>
                    ))
                )}
            </tbody>
            <tfoot className="border-t border-gray-200">
                <tr>
                    <td colSpan={4} className="px-4 py-3 text-sm font-medium text-slate-600">
                        Total ({rows.length} {rows.length === 1 ? 'venta' : 'ventas'})
                    </td>
                    <td className="px-4 py-3 text-right font-semibold text-slate-900">{money(total)}</td>
                </tr>
            </tfoot>
        </table>
    );
}

function ContainerTable({ rows }: { rows: CashCloseContainerRow[] }) {
    const delivered = rows.filter((row) => row.movement === 'DELIVERED').reduce((sum, row) => sum + row.quantity, 0);
    const returned = rows.filter((row) => row.movement === 'RETURNED').reduce((sum, row) => sum + row.quantity, 0);

    return (
        <table className="w-full text-left text-sm">
            <thead className="border-b border-gray-100">
                <tr>
                    <th className={thClass}>Hora</th>
                    <th className={thClass}>Cliente</th>
                    <th className={thClass}>Envase</th>
                    <th className={thClass}>Origen</th>
                    <th className={`${thClass} text-right`}>Cantidad</th>
                </tr>
            </thead>
            <tbody className="divide-y divide-gray-50">
                {rows.length === 0 ? (
                    <EmptyRow colSpan={5} />
                ) : (
                    rows.map((row) => {
                        const isDelivered = row.movement === 'DELIVERED';

                        return (
                            <tr key={row.id}>
                                <td className={`${tdClass} whitespace-nowrap`}>{time(row.at)}</td>
                                <td className={`${tdClass} font-medium text-slate-900`}>{row.customer ?? '—'}</td>
                                <td className={tdClass}>{row.container ?? '—'}</td>
                                <td className={tdClass}>
                                    <OriginBadge origin={row.origin} />
                                </td>
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
            </tbody>
            <tfoot className="border-t border-gray-200">
                <tr>
                    <td colSpan={4} className="px-4 py-3 text-sm font-medium text-slate-600">
                        Entregados / retirados
                    </td>
                    <td className="px-4 py-3 text-right font-semibold text-slate-900 whitespace-nowrap">
                        +{delivered} / -{returned}
                    </td>
                </tr>
            </tfoot>
        </table>
    );
}

export default function CashCloseDetailModal({ kind, details, onClose }: Props) {
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
                        {(kind === 'cash' || kind === 'transfer') && <PaymentTable rows={details[kind]} />}
                        {kind === 'credit' && <CreditTable rows={details.credit} />}
                        {kind === 'containers' && <ContainerTable rows={details.containers} />}
                    </div>
                </div>
            )}
        </Modal>
    );
}

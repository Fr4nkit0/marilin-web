import React from 'react';
import { X } from 'lucide-react';
import Modal, { ModalTitle } from '@/ui/Modal';
import SortableTh from '@/ui/SortableTh';
import { useSort, type SortDirection } from '@/lib/sort';
import {
    CashCloseContainerRow,
    CashCloseCreditRow,
    CashCloseDetailKind,
    CashCloseDetails,
    CashCloseOrigin,
    CashClosePaymentRow,
    CashClosePostCloseRow,
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
    postClose: {
        title: 'Ventas post-cierre',
        description: 'Ventas hechas después de completar la ruta. Descontaron stock del camión y ya están en el total de ventas.',
    },
    containers: { title: 'Balance de envases', description: 'Envases entregados y retirados en la jornada.' },
};

const ORIGIN_LABELS: Record<CashCloseOrigin, string> = {
    stop: 'Parada de ruta',
    loose: 'Ficha del cliente',
};

const thClass = 'px-4 py-3 text-xs font-medium text-ink tracking-wide';
const tdClass = 'px-4 py-3 text-ink-secondary tabular-nums';


/** A sortable header of these tables. */
function Th({ label, direction, onSort, right = false }: { label: string; direction: SortDirection | null; onSort: () => void; right?: boolean }) {
    return (
        <SortableTh direction={direction} onSort={onSort} align={right ? 'right' : 'left'} className={thClass}>
            {label}
        </SortableTh>
    );
}

function time(at: string | null): string {
    return at ? at.slice(11, 16) : '—';
}

function OriginBadge({ origin }: { origin: CashCloseOrigin }) {
    const style =
        origin === 'stop'
            ? 'bg-sky-50 dark:bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-200/60 dark:border-sky-500/30'
            : 'bg-canvas-soft text-ink-secondary border-hairline/60';

    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border ${style}`}>
            {ORIGIN_LABELS[origin]}
        </span>
    );
}

function EmptyRow({ colSpan }: { colSpan: number }) {
    return (
        <tr>
            <td colSpan={colSpan} className="px-4 py-8 text-center text-sm text-ink-muted">
                <span className="table-empty">No hay registros en esta jornada.</span>
            </td>
        </tr>
    );
}

function PaymentTable({ rows }: { rows: CashClosePaymentRow[] }) {
    const total = rows.reduce((sum, row) => sum + row.amount, 0);
    const table = useSort(rows, {
        at: (row) => row.at,
        customer: (row) => row.customer,
        concept: (row) => row.concept,
        origin: (row) => ORIGIN_LABELS[row.origin],
        amount: (row) => row.amount,
    });

    return (
        <div className="table-scroll">
            <table className="w-full text-left text-sm">
                <thead className="border-b border-hairline">
                    <tr>
                        <Th label="Hora" direction={table.directionOf('at')} onSort={() => table.toggle('at')} />
                        <Th label="Cliente" direction={table.directionOf('customer')} onSort={() => table.toggle('customer')} />
                        <Th label="Concepto" direction={table.directionOf('concept')} onSort={() => table.toggle('concept')} />
                        <Th label="Origen" direction={table.directionOf('origin')} onSort={() => table.toggle('origin')} />
                        <Th label="Monto" direction={table.directionOf('amount')} onSort={() => table.toggle('amount')} right />
                    </tr>
                </thead>
                <tbody className="divide-y divide-hairline">
                    {rows.length === 0 ? (
                        <EmptyRow colSpan={5} />
                    ) : (
                        table.rows.map((row) => (
                            <tr key={row.id}>
                                <td className={`${tdClass} whitespace-nowrap`}>{time(row.at)}</td>
                                <td className={`${tdClass} font-medium text-ink`}>{row.customer ?? '—'}</td>
                                <td className={tdClass}>{row.concept}</td>
                                <td className={tdClass}>
                                    <OriginBadge origin={row.origin} />
                                </td>
                                <td className={`${tdClass} text-right font-semibold text-ink`}>{money(row.amount)}</td>
                            </tr>
                        ))
                    )}
                </tbody>
                <tfoot className="border-t border-hairline">
                    <tr>
                        <td colSpan={4} className="px-4 py-3 text-sm font-medium text-ink-secondary">
                            Total ({rows.length} {rows.length === 1 ? 'cobro' : 'cobros'})
                        </td>
                        <td className="px-4 py-3 text-right font-semibold text-ink">{money(total)}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

function CreditTable({ rows }: { rows: CashCloseCreditRow[] }) {
    const total = rows.reduce((sum, row) => sum + row.amount, 0);
    const table = useSort(rows, {
        at: (row) => row.at,
        customer: (row) => row.customer,
        sale: (row) => row.id,
        origin: (row) => ORIGIN_LABELS[row.origin],
        amount: (row) => row.amount,
    });

    return (
        <div className="table-scroll">
            <table className="w-full text-left text-sm">
                <thead className="border-b border-hairline">
                    <tr>
                        <Th label="Hora" direction={table.directionOf('at')} onSort={() => table.toggle('at')} />
                        <Th label="Cliente" direction={table.directionOf('customer')} onSort={() => table.toggle('customer')} />
                        <Th label="Venta" direction={table.directionOf('sale')} onSort={() => table.toggle('sale')} />
                        <Th label="Origen" direction={table.directionOf('origin')} onSort={() => table.toggle('origin')} />
                        <Th label="Monto" direction={table.directionOf('amount')} onSort={() => table.toggle('amount')} right />
                    </tr>
                </thead>
                <tbody className="divide-y divide-hairline">
                    {rows.length === 0 ? (
                        <EmptyRow colSpan={5} />
                    ) : (
                        table.rows.map((row) => (
                            <tr key={row.id}>
                                <td className={`${tdClass} whitespace-nowrap`}>{time(row.at)}</td>
                                <td className={`${tdClass} font-medium text-ink`}>{row.customer ?? '—'}</td>
                                <td className={tdClass}>#{row.id}</td>
                                <td className={tdClass}>
                                    <OriginBadge origin={row.origin} />
                                </td>
                                <td className={`${tdClass} text-right font-semibold text-ink`}>{money(row.amount)}</td>
                            </tr>
                        ))
                    )}
                </tbody>
                <tfoot className="border-t border-hairline">
                    <tr>
                        <td colSpan={4} className="px-4 py-3 text-sm font-medium text-ink-secondary">
                            Total ({rows.length} {rows.length === 1 ? 'venta' : 'ventas'})
                        </td>
                        <td className="px-4 py-3 text-right font-semibold text-ink">{money(total)}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

function PostCloseTable({ rows }: { rows: CashClosePostCloseRow[] }) {
    const total = rows.reduce((sum, row) => sum + row.amount, 0);
    const table = useSort(rows, {
        at: (row) => row.at,
        customer: (row) => row.customer,
        sale: (row) => row.id,
        type: (row) => (row.credit ? 'Fiado' : 'Contado'),
        origin: (row) => ORIGIN_LABELS[row.origin],
        amount: (row) => row.amount,
    });

    return (
        <div className="table-scroll">
            <table className="w-full text-left text-sm">
                <thead className="border-b border-hairline">
                    <tr>
                        <Th label="Hora" direction={table.directionOf('at')} onSort={() => table.toggle('at')} />
                        <Th label="Cliente" direction={table.directionOf('customer')} onSort={() => table.toggle('customer')} />
                        <Th label="Venta" direction={table.directionOf('sale')} onSort={() => table.toggle('sale')} />
                        <Th label="Tipo" direction={table.directionOf('type')} onSort={() => table.toggle('type')} />
                        <Th label="Origen" direction={table.directionOf('origin')} onSort={() => table.toggle('origin')} />
                        <Th label="Monto" direction={table.directionOf('amount')} onSort={() => table.toggle('amount')} right />
                    </tr>
                </thead>
                <tbody className="divide-y divide-hairline">
                    {rows.length === 0 ? (
                        <EmptyRow colSpan={6} />
                    ) : (
                        table.rows.map((row) => (
                            <tr key={row.id}>
                                <td className={`${tdClass} whitespace-nowrap`}>{time(row.at)}</td>
                                <td className={`${tdClass} font-medium text-ink`}>{row.customer ?? '—'}</td>
                                <td className={tdClass}>#{row.id}</td>
                                <td className={tdClass}>{row.credit ? 'Fiado' : 'Contado'}</td>
                                <td className={tdClass}>
                                    <OriginBadge origin={row.origin} />
                                </td>
                                <td className={`${tdClass} text-right font-semibold text-ink`}>{money(row.amount)}</td>
                            </tr>
                        ))
                    )}
                </tbody>
                <tfoot className="border-t border-hairline">
                    <tr>
                        <td colSpan={5} className="px-4 py-3 text-sm font-medium text-ink-secondary">
                            Total ({rows.length} {rows.length === 1 ? 'venta' : 'ventas'})
                        </td>
                        <td className="px-4 py-3 text-right font-semibold text-ink">{money(total)}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

function ContainerTable({ rows }: { rows: CashCloseContainerRow[] }) {
    const delivered = rows.filter((row) => row.movement === 'DELIVERED').reduce((sum, row) => sum + row.quantity, 0);
    const returned = rows.filter((row) => row.movement === 'RETURNED').reduce((sum, row) => sum + row.quantity, 0);
    const table = useSort(rows, {
        at: (row) => row.at,
        customer: (row) => row.customer,
        container: (row) => row.container,
        origin: (row) => ORIGIN_LABELS[row.origin],
        // Deliveries count up and returns down, as the column reads ("Entregó +3", "Retiró -2").
        quantity: (row) => (row.movement === 'DELIVERED' ? row.quantity : -row.quantity),
    });

    return (
        <div className="table-scroll">
            <table className="w-full text-left text-sm">
                <thead className="border-b border-hairline">
                    <tr>
                        <Th label="Hora" direction={table.directionOf('at')} onSort={() => table.toggle('at')} />
                        <Th label="Cliente" direction={table.directionOf('customer')} onSort={() => table.toggle('customer')} />
                        <Th label="Envase" direction={table.directionOf('container')} onSort={() => table.toggle('container')} />
                        <Th label="Origen" direction={table.directionOf('origin')} onSort={() => table.toggle('origin')} />
                        <Th label="Cantidad" direction={table.directionOf('quantity')} onSort={() => table.toggle('quantity')} right />
                    </tr>
                </thead>
                <tbody className="divide-y divide-hairline">
                    {rows.length === 0 ? (
                        <EmptyRow colSpan={5} />
                    ) : (
                        table.rows.map((row) => {
                            const isDelivered = row.movement === 'DELIVERED';

                            return (
                                <tr key={row.id}>
                                    <td className={`${tdClass} whitespace-nowrap`}>{time(row.at)}</td>
                                    <td className={`${tdClass} font-medium text-ink`}>{row.customer ?? '—'}</td>
                                    <td className={tdClass}>{row.container ?? '—'}</td>
                                    <td className={tdClass}>
                                        <OriginBadge origin={row.origin} />
                                    </td>
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
                </tbody>
                <tfoot className="border-t border-hairline">
                    <tr>
                        <td colSpan={4} className="px-4 py-3 text-sm font-medium text-ink-secondary">
                            Entregados / retirados
                        </td>
                        <td className="px-4 py-3 text-right font-semibold text-ink whitespace-nowrap">
                            +{delivered} / -{returned}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

export default function CashCloseDetailModal({ kind, details, onClose }: Props) {
    return (
        <Modal show={kind !== null} onClose={onClose} maxWidth="2xl">
            {kind && (
                <div>
                    <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-hairline">
                        <div>
                            <ModalTitle className="text-lg font-semibold text-ink">{TITLES[kind].title}</ModalTitle>
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
                        {(kind === 'cash' || kind === 'transfer') && <PaymentTable rows={details[kind]} />}
                        {kind === 'credit' && <CreditTable rows={details.credit} />}
                        {kind === 'postClose' && <PostCloseTable rows={details.postClose} />}
                        {kind === 'containers' && <ContainerTable rows={details.containers} />}
                    </div>
                </div>
            )}
        </Modal>
    );
}

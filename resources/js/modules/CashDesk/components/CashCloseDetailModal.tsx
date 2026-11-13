import type { ReactNode } from 'react';
import { X } from 'lucide-react';
import Modal, { ModalTitle } from '@/ui/Modal';
import SortableTh from '@/ui/SortableTh';
import SortBar from '@/ui/SortBar';
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
    transfer: { title: 'Transferencias', description: 'Cobros por transferencia de la jornada.' },
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


interface SortColumn<K extends string> {
    key: K;
    label: string;
    right?: boolean;
}

interface DetailTableProps<K extends string> {
    columns: SortColumn<K>[];
    directionOf: (key: K) => SortDirection | null;
    toggle: (key: K) => void;
    /** The body rows, or the empty row. */
    children: ReactNode;
    /** The total line: its label spans the first columns and its value sits under the last one. */
    total: { label: string; value: ReactNode };
}

/**
 * A sortable detail table with its total. On a phone each row is a card (`.table-cards`): the headers are hidden,
 * so the sort bar takes their place, and the total reads as one "label: value" line.
 */
function DetailTable<K extends string>({ columns, directionOf, toggle, children, total }: DetailTableProps<K>) {
    return (
        <div className="table-scroll">
            <SortBar options={columns.map((column) => ({ label: column.label, direction: directionOf(column.key), onSort: () => toggle(column.key) }))} />
            <table className="table-cards w-full text-left text-sm">
                <thead className="border-b border-hairline">
                    <tr>
                        {columns.map((column) => (
                            <SortableTh
                                key={column.key}
                                direction={directionOf(column.key)}
                                onSort={() => toggle(column.key)}
                                align={column.right ? 'right' : 'left'}
                                className={thClass}
                            >
                                {column.label}
                            </SortableTh>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-hairline">{children}</tbody>
                <tfoot className="border-t border-hairline">
                    <tr>
                        {/* On a phone the value's own label says it, so the spanning cell would only repeat it. */}
                        <td colSpan={columns.length - 1} className="px-4 py-3 text-sm font-medium text-ink-secondary max-sm:!hidden">
                            {total.label}
                        </td>
                        <td data-label={total.label} className="px-4 py-3 text-right font-semibold text-ink whitespace-nowrap">
                            {total.value}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
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

/** The customer cell: the card's title on a phone, so it moves to the top there. */
function CustomerCell({ customer }: { customer: string | null }) {
    return (
        <td data-primary className={`${tdClass} font-medium text-ink max-sm:order-first`}>
            {customer ?? '—'}
        </td>
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
        <DetailTable
            columns={[
                { key: 'at', label: 'Hora' },
                { key: 'customer', label: 'Cliente' },
                { key: 'concept', label: 'Concepto' },
                { key: 'origin', label: 'Origen' },
                { key: 'amount', label: 'Monto', right: true },
            ]}
            directionOf={table.directionOf}
            toggle={table.toggle}
            total={{ label: `Total (${rows.length} ${rows.length === 1 ? 'cobro' : 'cobros'})`, value: money(total) }}
        >
            {rows.length === 0 ? (
                <EmptyRow colSpan={5} />
            ) : (
                table.rows.map((row) => (
                    <tr key={row.id}>
                        <td data-label="Hora" className={`${tdClass} whitespace-nowrap`}>{time(row.at)}</td>
                        <CustomerCell customer={row.customer} />
                        <td data-label="Concepto" className={tdClass}>{row.concept}</td>
                        <td data-label="Origen" className={tdClass}>
                            <OriginBadge origin={row.origin} />
                        </td>
                        <td data-label="Monto" className={`${tdClass} text-right font-semibold text-ink`}>{money(row.amount)}</td>
                    </tr>
                ))
            )}
        </DetailTable>
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
        <DetailTable
            columns={[
                { key: 'at', label: 'Hora' },
                { key: 'customer', label: 'Cliente' },
                { key: 'sale', label: 'Venta' },
                { key: 'origin', label: 'Origen' },
                { key: 'amount', label: 'Monto', right: true },
            ]}
            directionOf={table.directionOf}
            toggle={table.toggle}
            total={{ label: `Total (${rows.length} ${rows.length === 1 ? 'venta' : 'ventas'})`, value: money(total) }}
        >
            {rows.length === 0 ? (
                <EmptyRow colSpan={5} />
            ) : (
                table.rows.map((row) => (
                    <tr key={row.id}>
                        <td data-label="Hora" className={`${tdClass} whitespace-nowrap`}>{time(row.at)}</td>
                        <CustomerCell customer={row.customer} />
                        <td data-label="Venta" className={tdClass}>#{row.id}</td>
                        <td data-label="Origen" className={tdClass}>
                            <OriginBadge origin={row.origin} />
                        </td>
                        <td data-label="Monto" className={`${tdClass} text-right font-semibold text-ink`}>{money(row.amount)}</td>
                    </tr>
                ))
            )}
        </DetailTable>
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
        <DetailTable
            columns={[
                { key: 'at', label: 'Hora' },
                { key: 'customer', label: 'Cliente' },
                { key: 'sale', label: 'Venta' },
                { key: 'type', label: 'Tipo' },
                { key: 'origin', label: 'Origen' },
                { key: 'amount', label: 'Monto', right: true },
            ]}
            directionOf={table.directionOf}
            toggle={table.toggle}
            total={{ label: `Total (${rows.length} ${rows.length === 1 ? 'venta' : 'ventas'})`, value: money(total) }}
        >
            {rows.length === 0 ? (
                <EmptyRow colSpan={6} />
            ) : (
                table.rows.map((row) => (
                    <tr key={row.id}>
                        <td data-label="Hora" className={`${tdClass} whitespace-nowrap`}>{time(row.at)}</td>
                        <CustomerCell customer={row.customer} />
                        <td data-label="Venta" className={tdClass}>#{row.id}</td>
                        <td data-label="Tipo" className={tdClass}>{row.credit ? 'Fiado' : 'Contado'}</td>
                        <td data-label="Origen" className={tdClass}>
                            <OriginBadge origin={row.origin} />
                        </td>
                        <td data-label="Monto" className={`${tdClass} text-right font-semibold text-ink`}>{money(row.amount)}</td>
                    </tr>
                ))
            )}
        </DetailTable>
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
        <DetailTable
            columns={[
                { key: 'at', label: 'Hora' },
                { key: 'customer', label: 'Cliente' },
                { key: 'container', label: 'Envase' },
                { key: 'origin', label: 'Origen' },
                { key: 'quantity', label: 'Cantidad', right: true },
            ]}
            directionOf={table.directionOf}
            toggle={table.toggle}
            total={{ label: 'Entregados / retirados', value: `+${delivered} / -${returned}` }}
        >
            {rows.length === 0 ? (
                <EmptyRow colSpan={5} />
            ) : (
                table.rows.map((row) => {
                    const isDelivered = row.movement === 'DELIVERED';

                    return (
                        <tr key={row.id}>
                            <td data-label="Hora" className={`${tdClass} whitespace-nowrap`}>{time(row.at)}</td>
                            <CustomerCell customer={row.customer} />
                            <td data-label="Envase" className={tdClass}>{row.container ?? '—'}</td>
                            <td data-label="Origen" className={tdClass}>
                                <OriginBadge origin={row.origin} />
                            </td>
                            <td
                                data-label="Cantidad"
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
        </DetailTable>
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
                            className="inline-flex items-center justify-center min-h-11 min-w-11 sm:min-h-0 sm:min-w-0 p-1.5 rounded-lg text-ink-subtle hover:text-ink-secondary hover:bg-surface-1 transition cursor-pointer"
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

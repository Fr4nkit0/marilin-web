import { Pencil, Power } from 'lucide-react';
import Button from '@/ui/Button';

interface Props {
    label: string;
    enabled: boolean;
    onEdit: () => void;
    onToggle: () => void;
}

/** A settings catalog row's edit and enable/disable buttons. */
export default function RowActions({ label, enabled, onEdit, onToggle }: Props) {
    return (
        <div className="flex justify-end gap-1">
            <Button variant="ghost" size="sm" onClick={onEdit} aria-label={`Editar ${label}`}>
                <Pencil className="w-3.5 h-3.5" />
                Editar
            </Button>
            <Button
                variant="ghost"
                size="sm"
                onClick={onToggle}
                aria-label={`${enabled ? 'Deshabilitar' : 'Habilitar'} ${label}`}
                className={enabled ? 'text-rose-600 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-500/15' : ''}
            >
                <Power className="w-3.5 h-3.5" />
                {enabled ? 'Deshabilitar' : 'Habilitar'}
            </Button>
        </div>
    );
}

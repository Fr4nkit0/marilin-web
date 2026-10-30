import { render, screen } from '@testing-library/react';
import { DollarSign } from 'lucide-react';
import { describe, expect, it } from 'vitest';
import { KpiCard } from './KpiCard';

describe('KpiCard', () => {
    it('names a clickable card by its title and value', () => {
        render(<KpiCard title="Cobranza Recaudada" value="$114.950" icon={DollarSign} onClick={() => {}} />);

        expect(screen.getByRole('button', { name: 'Cobranza Recaudada: $114.950' })).toBeTruthy();
    });

    it('stays a plain block when it is not clickable', () => {
        render(<KpiCard title="Ventas" value="$10" icon={DollarSign} />);

        expect(screen.queryByRole('button')).toBeNull();
    });
});

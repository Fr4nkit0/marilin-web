import { fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import InitialConditionsFields from './InitialConditionsFields';

const types = [
    { id: 3, description: 'Bidón 20 Litros Retornable' },
    { id: 4, description: 'Sifón Soda 1.5 Litros Retornable' },
];

function Harness({ onChange }: { onChange: (containers: Record<string, string>) => void }) {
    const [containers, setContainers] = useState<Record<string, string>>({});

    return (
        <InitialConditionsFields
            balance="0"
            containers={containers}
            containerTypes={types}
            onBalanceChange={() => {}}
            onContainersChange={(next) => {
                setContainers(next);
                onChange(next);
            }}
            errors={{}}
        />
    );
}

describe('InitialConditionsFields', () => {
    it('counts each container type on its own row and never goes below zero', () => {
        const onChange = vi.fn();
        render(<Harness onChange={onChange} />);

        const bidones = screen.getByLabelText('Bidón 20 Litros Retornable') as HTMLInputElement;
        expect(bidones.value).toBe('0');
        expect((screen.getByRole('button', { name: 'Restar un Bidón 20 Litros Retornable' }) as HTMLButtonElement).disabled).toBe(true);

        fireEvent.click(screen.getByRole('button', { name: 'Sumar un Bidón 20 Litros Retornable' }));
        fireEvent.click(screen.getByRole('button', { name: 'Sumar un Bidón 20 Litros Retornable' }));
        fireEvent.click(screen.getByRole('button', { name: 'Restar un Bidón 20 Litros Retornable' }));
        fireEvent.change(screen.getByLabelText('Sifón Soda 1.5 Litros Retornable'), { target: { value: '6' } });

        expect(bidones.value).toBe('1');
        expect(onChange).toHaveBeenLastCalledWith({ 3: '1', 4: '6' });
    });

    it('keeps the initial debt as its own money field', () => {
        const onBalanceChange = vi.fn();
        render(
            <InitialConditionsFields
                balance="0"
                containers={{}}
                containerTypes={types}
                onBalanceChange={onBalanceChange}
                onContainersChange={() => {}}
                errors={{ 'initial_containers.4': 'La cantidad no puede ser negativa.' }}
            />,
        );

        fireEvent.change(screen.getByLabelText('Deuda inicial'), { target: { value: '1500' } });

        expect(onBalanceChange).toHaveBeenCalledWith('1500');
        expect(screen.getByText('La cantidad no puede ser negativa.')).toBeTruthy();
    });
});

import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import SortableTh from './SortableTh';

function header(direction: 'asc' | 'desc' | null, onSort = () => {}) {
    return render(
        <table>
            <thead>
                <tr>
                    <SortableTh direction={direction} onSort={onSort}>
                        Cliente
                    </SortableTh>
                </tr>
            </thead>
        </table>,
    );
}

describe('SortableTh', () => {
    it('tells assistive technology how the column is sorted', () => {
        const { rerender } = header(null);
        expect(screen.getByRole('columnheader').getAttribute('aria-sort')).toBe('none');

        rerender(
            <table>
                <thead>
                    <tr>
                        <SortableTh direction="desc" onSort={() => {}}>
                            Cliente
                        </SortableTh>
                    </tr>
                </thead>
            </table>,
        );
        expect(screen.getByRole('columnheader').getAttribute('aria-sort')).toBe('descending');
    });

    it('sorts when the label is pressed', () => {
        const onSort = vi.fn();
        header('asc', onSort);

        fireEvent.click(screen.getByRole('button', { name: 'Cliente' }));

        expect(onSort).toHaveBeenCalledOnce();
        expect(screen.getByRole('columnheader').getAttribute('aria-sort')).toBe('ascending');
    });
});

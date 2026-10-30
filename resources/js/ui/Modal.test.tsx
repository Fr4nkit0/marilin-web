import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import Modal, { ModalTitle } from './Modal';

describe('Modal', () => {
    it('is a modal dialog named by its title', () => {
        render(
            <Modal show onClose={() => {}}>
                <ModalTitle className="text-lg">Venta en casa</ModalTitle>
                <p>Contenido</p>
            </Modal>,
        );

        const dialog = screen.getByRole('dialog', { name: 'Venta en casa' });
        expect(dialog.getAttribute('aria-modal')).toBe('true');
        expect(screen.getByRole('heading', { name: 'Venta en casa' }).tagName).toBe('H2');
    });

    it('renders nothing while hidden', () => {
        render(
            <Modal show={false} onClose={() => {}}>
                <ModalTitle>Venta en casa</ModalTitle>
            </Modal>,
        );

        expect(screen.queryByRole('dialog')).toBeNull();
    });
});

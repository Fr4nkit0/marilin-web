import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import CoveredRouteNote, { coverSentence } from './CoveredRouteNote';

describe('coverSentence', () => {
    it('lists two drivers joined with "y"', () => {
        expect(
            coverSentence(20, [
                { driverName: 'Juan Pérez', stops: 13 },
                { driverName: 'Esteban Benítez', stops: 7 },
            ]),
        ).toBe('Sus 20 paradas las cubren Juan Pérez (13) y Esteban Benítez (7).');
    });

    it('joins three drivers with commas and "y" before the last', () => {
        expect(
            coverSentence(6, [
                { driverName: 'Ana', stops: 3 },
                { driverName: 'Beto', stops: 2 },
                { driverName: 'Carla', stops: 1 },
            ]),
        ).toBe('Sus 6 paradas las cubren Ana (3), Beto (2) y Carla (1).');
    });

    it('uses "cubre" when one driver covers them', () => {
        expect(coverSentence(5, [{ driverName: 'Juan Pérez', stops: 5 }])).toBe(
            'Sus 5 paradas las cubre Juan Pérez (5).',
        );
    });

    it('speaks of a single stop', () => {
        expect(coverSentence(1, [{ driverName: 'Juan Pérez', stops: 1 }])).toBe('Su parada la cubre Juan Pérez (1).');
    });
});

describe('CoveredRouteNote', () => {
    it('shows the sentence', () => {
        render(<CoveredRouteNote coveredStops={5} coveredBy={[{ driverName: 'Juan Pérez', stops: 5 }]} />);

        expect(screen.getByText('Sus 5 paradas las cubre Juan Pérez (5).')).toBeTruthy();
    });
});

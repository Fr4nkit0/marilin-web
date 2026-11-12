import { describe, expect, it } from 'vitest';
import type { SplitGroup, SplitSuggestion } from '../types';
import { assignLabel, preselectedDriverId, stopsToAssign, suggestionText, toggleStop } from './splitGroup';

function group(stopIds: number[], suggestions: SplitSuggestion[] = []): SplitGroup {
    return {
        neighborhood: 'Grand Bourg',
        name: 'Grand Bourg',
        stops: stopIds.map((id) => ({ id, customer: `Cliente ${id}`, street: null, neighborhood: 'Grand Bourg', scheduledTime: null })),
        suggestions,
    };
}

const franco = { driverId: 7, driverName: 'Franco', stops: 8 };
const pedro = { driverId: 9, driverName: 'Pedro', stops: 1 };
const drivers = [
    { id: 7, name: 'Franco' },
    { id: 9, name: 'Pedro' },
];

describe('splitGroup', () => {
    it('tells who already delivers in the neighborhood, or that nobody does', () => {
        expect(suggestionText(group([1], [franco, pedro]))).toBe('Franco ya reparte aquí hoy (8 paradas)');
        expect(suggestionText(group([1], [pedro]))).toBe('Pedro ya reparte aquí hoy (1 parada)');
        expect(suggestionText(group([1]))).toBe('Nadie reparte aquí hoy');
    });

    it('preselects the busiest suggested driver, only when it can be picked', () => {
        expect(preselectedDriverId(group([1], [franco, pedro]), drivers)).toBe('7');
        expect(preselectedDriverId(group([1]), drivers)).toBe('');
        expect(preselectedDriverId(group([1], [{ ...franco, driverId: 99 }]), drivers)).toBe('');
    });

    it('assigns the chosen stops of the group, or all of them when none is chosen', () => {
        expect(stopsToAssign(group([1, 2, 3]), [])).toEqual([1, 2, 3]);
        // In the group's order; a stale choice from another group is ignored.
        expect(stopsToAssign(group([1, 2, 3]), [3, 1, 40])).toEqual([1, 3]);
        expect(stopsToAssign(group([1, 2, 3]), [40])).toEqual([1, 2, 3]);
    });

    it('labels the assign button with what it moves', () => {
        expect(assignLabel(group([1, 2, 3]), [])).toBe('Asignar las 3 paradas');
        expect(assignLabel(group([1]), [])).toBe('Asignar la parada');
        expect(assignLabel(group([1, 2, 3]), [2])).toBe('Asignar 1 parada');
        expect(assignLabel(group([1, 2, 3]), [1, 2])).toBe('Asignar 2 paradas');
    });

    it('toggles a stop in or out of the choice', () => {
        expect(toggleStop([1], 2)).toEqual([1, 2]);
        expect(toggleStop([1, 2], 1)).toEqual([2]);
    });
});

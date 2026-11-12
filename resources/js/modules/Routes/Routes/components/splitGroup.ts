import type { SplitGroup } from '../types';

interface DriverOption {
    id: number;
    name: string;
}

function stopsCount(count: number): string {
    return count === 1 ? '1 parada' : `${count} paradas`;
}

/** Who already delivers in the neighborhood that day (the busiest), or that nobody does. */
export function suggestionText(group: SplitGroup): string {
    const top = group.suggestions[0];

    return top ? `${top.driverName} ya reparte aquí hoy (${stopsCount(top.stops)})` : 'Nadie reparte aquí hoy';
}

/** The busiest suggested driver, as the select's value; none when there is no suggestion or it cannot be picked. */
export function preselectedDriverId(group: SplitGroup, drivers: DriverOption[]): string {
    const top = group.suggestions[0];

    return top && drivers.some((driver) => driver.id === top.driverId) ? String(top.driverId) : '';
}

/** The chosen stops of the group in its order, or every stop of it when none is chosen. */
export function stopsToAssign(group: SplitGroup, selected: number[]): number[] {
    const ids = group.stops.map((stop) => stop.id);
    const chosen = ids.filter((id) => selected.includes(id));

    return chosen.length > 0 ? chosen : ids;
}

export function assignLabel(group: SplitGroup, selected: number[]): string {
    const chosen = group.stops.filter((stop) => selected.includes(stop.id)).length;

    if (chosen > 0) {
        return `Asignar ${stopsCount(chosen)}`;
    }

    return group.stops.length === 1 ? 'Asignar la parada' : `Asignar las ${group.stops.length} paradas`;
}

export function toggleStop(selected: number[], id: number): number[] {
    return selected.includes(id) ? selected.filter((s) => s !== id) : [...selected, id];
}

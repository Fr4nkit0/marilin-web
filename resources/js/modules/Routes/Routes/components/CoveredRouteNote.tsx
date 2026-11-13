import { Users } from 'lucide-react';

/** A driver holding stops moved from an absent driver's route, and how many. */
export interface Coverer {
    driverName: string;
    stops: number;
}

/** "Sus 20 paradas las cubren Juan Pérez (13) y Esteban Benítez (7)." */
export function coverSentence(coveredStops: number, coveredBy: Coverer[]): string {
    const names = coveredBy.map((coverer) => `${coverer.driverName} (${coverer.stops})`);
    const list = names.length > 1 ? `${names.slice(0, -1).join(', ')} y ${names[names.length - 1]}` : names.join('');
    const verb = coveredBy.length === 1 ? 'cubre' : 'cubren';
    const subject = coveredStops === 1 ? 'Su parada la' : `Sus ${coveredStops} paradas las`;

    return `${subject} ${verb} ${list}.`;
}

interface Props {
    coveredStops: number;
    coveredBy: Coverer[];
}

/** "Cobertura": every stop of the route went to other drivers; it stays planned so a stop can come back. */
export default function CoveredRouteNote({ coveredStops, coveredBy }: Props) {
    return (
        <p className="flex items-start gap-2 text-sm text-ink-secondary">
            <Users className="w-4 h-4 mt-0.5 shrink-0 text-ink-muted" aria-hidden="true" />
            <span>{coverSentence(coveredStops, coveredBy)}</span>
        </p>
    );
}

export interface VisitOutcomeItem {
    id: number;
    /** Set only on the seeded outcomes the app relies on; never editable. */
    code: string | null;
    name: string;
    triggers_reschedule: boolean;
    enabled: boolean;
}

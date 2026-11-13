import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const MODULES = join(__dirname, '..', 'modules');

function tsxFiles(dir: string): string[] {
    return readdirSync(dir).flatMap((name) => {
        const path = join(dir, name);
        if (statSync(path).isDirectory()) return tsxFiles(path);
        return name.endsWith('.tsx') && !name.endsWith('.test.tsx') ? [path] : [];
    });
}

describe('tables on a phone', () => {
    it('every hand-rolled table joins the card layout (DataTable does it by itself)', () => {
        const missing = tsxFiles(MODULES).flatMap((file) =>
            [...readFileSync(file, 'utf8').matchAll(/<table\b[^>]*>/g)]
                .filter(([tag]) => !tag.includes('table-cards'))
                .map(([tag]) => `${file.slice(MODULES.length + 1)}: ${tag}`),
        );

        expect(missing).toEqual([]);
    });
});

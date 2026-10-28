import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

// Without Vitest globals Testing Library cannot clean up on its own.
afterEach(() => cleanup());

import { afterEach, describe, expect, it, vi } from 'vitest';
import { openPickerOnTap } from './datePicker';

function field(type: string): HTMLInputElement {
    const input = document.createElement('input');
    input.type = type;
    document.body.appendChild(input);
    return input;
}

describe('openPickerOnTap', () => {
    let stop: () => void = () => {};

    afterEach(() => {
        stop();
        document.body.innerHTML = '';
    });

    it.each(['date', 'time', 'datetime-local', 'month'])('opens the picker of a %s field when it is tapped', (type) => {
        stop = openPickerOnTap(document);
        const input = field(type);
        input.showPicker = vi.fn();

        input.click();

        expect(input.showPicker).toHaveBeenCalledTimes(1);
    });

    it('leaves other fields alone', () => {
        stop = openPickerOnTap(document);
        const input = field('text');
        input.showPicker = vi.fn();

        input.click();

        expect(input.showPicker).not.toHaveBeenCalled();
    });

    it('does nothing for a disabled or read-only field', () => {
        stop = openPickerOnTap(document);
        const disabled = field('date');
        disabled.disabled = true;
        disabled.showPicker = vi.fn();
        const readOnly = field('date');
        readOnly.readOnly = true;
        readOnly.showPicker = vi.fn();

        disabled.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        readOnly.click();

        expect(disabled.showPicker).not.toHaveBeenCalled();
        expect(readOnly.showPicker).not.toHaveBeenCalled();
    });

    it('keeps working when the browser refuses to open the picker', () => {
        stop = openPickerOnTap(document);
        const input = field('date');
        input.showPicker = vi.fn(() => {
            throw new DOMException('Not allowed', 'NotAllowedError');
        });

        expect(() => input.click()).not.toThrow();
    });

    it('stops listening once stopped', () => {
        openPickerOnTap(document)();
        const input = field('date');
        input.showPicker = vi.fn();

        input.click();

        expect(input.showPicker).not.toHaveBeenCalled();
    });
});

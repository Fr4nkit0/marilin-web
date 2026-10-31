import {
    Dialog,
    DialogPanel,
    DialogTitle,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import { PropsWithChildren } from 'react';

/** The modal's heading; it names the dialog (aria-labelledby) for screen readers. */
export function ModalTitle({ className = '', children }: PropsWithChildren<{ className?: string }>) {
    return (
        <DialogTitle as="h2" className={className}>
            {children}
        </DialogTitle>
    );
}

/**
 * A modal dialog (role="dialog", aria-modal); put a ModalTitle inside so it has a name.
 * It never grows past the viewport: the panel is a column that scrolls when its content is taller. A long form keeps
 * its title and buttons in view by being a `flex min-h-0 flex-col` child whose body scrolls (`min-h-0 flex-1 overflow-y-auto`).
 */
export default function Modal({
    children,
    show = false,
    maxWidth = '2xl',
    closeable = true,
    onClose = () => {},
}: PropsWithChildren<{
    show: boolean;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
    closeable?: boolean;
    onClose: CallableFunction;
}>) {
    const close = () => {
        if (closeable) {
            onClose();
        }
    };

    const maxWidthClass = {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[maxWidth];

    return (
        <Transition show={show} leave="duration-200">
            <Dialog
                as="div"
                id="modal"
                className="fixed inset-0 z-50 flex transform items-center overflow-y-auto px-4 py-6 transition-all sm:px-0"
                onClose={close}
            >
                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="absolute inset-0 bg-gray-500/75 dark:bg-black/70" />
                </TransitionChild>

                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    enterTo="opacity-100 translate-y-0 sm:scale-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                    leaveTo="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                >
                    <DialogPanel
                        className={`flex max-h-[calc(100dvh-3rem)] w-full transform flex-col overflow-y-auto rounded-xl bg-canvas shadow-xl transition-all sm:mx-auto ${maxWidthClass}`}
                    >
                        {children}
                    </DialogPanel>
                </TransitionChild>
            </Dialog>
        </Transition>
    );
}

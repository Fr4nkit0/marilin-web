import ApplicationLogo from '@/ui/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import { useTheme } from '@/lib/theme';

export default function Guest({ children }: PropsWithChildren) {
    // No switch here, but the saved theme (or the system's) still applies and follows the system.
    useTheme();

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-4 sm:px-6 lg:px-8">
            <div className="w-full max-w-md">


                <div className="bg-white px-8 py-10 shadow-[0_8px_30px_rgb(0,0,0,0.04)] sm:rounded-2xl border border-gray-200/60 w-full">
                    {children}
                </div>

            </div>
        </div>
    );
}

import React, { PropsWithChildren, ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Map, ListTodo, Package, UserCircle } from 'lucide-react';

export default function AuthenticatedLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage().props.auth.user;

    const navItems = [
        {
            name: 'Ruta',
            href: route('dashboard'),
            icon: Map,
            active: route().current('dashboard') || route().current('routes.*'),
        },
        {
            name: 'Paradas',
            href: '#', // TODO: create route
            icon: ListTodo,
            active: false,
        },
        {
            name: 'Carga',
            href: '#', // TODO: create route
            icon: Package,
            active: false,
        },
        {
            name: 'Perfil',
            href: route('profile.edit'),
            icon: UserCircle,
            active: route().current('profile.*'),
        },
    ];

    return (
        <div className="min-h-screen bg-slate-50 flex flex-col font-sans antialiased text-slate-900 pb-[84px]">
            {/* Top Header */}
            <header className="bg-white/80 backdrop-blur-md border-b border-gray-200/60 shadow-sm sticky top-0 z-30 flex items-center justify-between px-4 h-16">
                <div className="flex items-center gap-3">
                    <div className="flex flex-col">
                        <span className="text-xs font-medium text-slate-500">
                            En reparto
                        </span>
                        <span className="text-sm text-slate-900 font-semibold">
                            {user.name}
                        </span>
                    </div>
                </div>
                {header && (
                    <div className="text-right font-medium text-sm text-slate-900">
                        {header}
                    </div>
                )}
            </header>

            {/* Main Content Area */}
            <main className="flex-1 w-full max-w-lg mx-auto p-4">
                {children}
            </main>

            {/* Bottom Navigation */}
            <nav className="fixed bottom-0 w-full bg-white/90 backdrop-blur-md border-t border-gray-200/60 z-40 pb-safe shadow-[0_-4px_12px_-1px_rgba(0,0,0,0.05)]">
                <div className="flex justify-around items-center h-[84px] max-w-lg mx-auto px-2 gap-2">
                    {navItems.map((item) => {
                        const Icon = item.icon;
                        return (
                            <Link
                                key={item.name}
                                href={item.href}
                                className={`flex flex-col items-center justify-center w-full h-full rounded-xl transition-colors ${
                                    item.active 
                                        ? 'bg-slate-50' 
                                        : 'bg-transparent hover:bg-slate-50'
                                }`}
                            >
                                <div className="p-2 flex flex-col items-center gap-1.5">
                                    <Icon 
                                        className={`w-6 h-6 ${item.active ? 'text-slate-900' : 'text-slate-400'}`} 
                                        strokeWidth={item.active ? 2.5 : 2} 
                                    />
                                    <span className={`text-xs font-medium ${item.active ? 'text-slate-900' : 'text-slate-500'}`}>
                                        {item.name}
                                    </span>
                                </div>
                            </Link>
                        );
                    })}
                </div>
            </nav>
        </div>
    );
}

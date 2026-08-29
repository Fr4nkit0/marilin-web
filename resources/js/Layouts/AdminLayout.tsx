import React, { PropsWithChildren, ReactNode, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    Truck,
    Users,
    CalendarCheck,
    Receipt,
    CircleDollarSign,
    Package,
    SlidersHorizontal,
    LogOut,
    Menu,
    X,
    Bell,
    ChevronDown,
    MapPin,
    AlertTriangle,
} from 'lucide-react';

interface NavItem {
    name: string;
    href: string;
    icon: React.ElementType;
    active: boolean;
    badge?: string | number;
}

export default function AdminLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage().props.auth.user;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const [showLogoutModal, setShowLogoutModal] = useState(false);

    const handleConfirmLogout = () => {
        router.post(route('logout'));
    };

    const navigation: NavItem[] = [
        {
            name: 'Dashboard',
            href: route('dashboard'),
            icon: LayoutDashboard,
            active: route().current('dashboard'),
        },
        {
            name: 'Repartidores',
            href: route('repartidores.index'),
            icon: Truck,
            active: route().current('repartidores.*') || false,
        },
        {
            name: 'Clientes',
            href: route('customers.index'),
            icon: Users,
            active: route().current('customers.*') || false,
        },
        {
            name: 'Despacho y Rutas',
            href: '#',
            icon: CalendarCheck,
            active: route().current('routes.*') || false,
        },
        {
            name: 'Ventas',
            href: route('sales.index'),
            icon: Receipt,
            active: route().current('sales.*') || false,
        },
        {
            name: 'Cobros y Caja',
            href: route('payments.index'),
            icon: CircleDollarSign,
            active: route().current('payments.*') || false,
        },
        {
            name: 'Cierre y Arqueo',
            href: route('cash-close.index'),
            icon: Package,
            active: route().current('cash-close.*') || false,
        },
    ];

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900 flex font-sans antialiased">
            {/* Mobile Sidebar Backdrop */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-xs lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Sidebar (Fixed 240px) */}
            <aside
                className={`fixed inset-y-0 left-0 z-50 w-60 bg-white border-r border-slate-200 flex flex-col transition-transform duration-200 ease-in-out lg:translate-x-0 ${
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                {/* Brand Logo & Header - Modern Clean Uppercase Branding */}
                <div className="h-16 flex items-center justify-between px-5 border-b border-slate-100">
                    <Link href={route('dashboard')} className="flex items-center gap-2.5 group">
                        <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-600 to-blue-700 flex items-center justify-center text-white shadow-xs shadow-blue-500/20 group-hover:scale-105 transition-transform">
                            <svg
                                className="w-4.5 h-4.5 text-white"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2.2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            >
                                <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" fill="currentColor" fillOpacity="0.2" />
                            </svg>
                        </div>
                        <div className="flex flex-col">
                            <div className="flex items-center tracking-wider">
                                <span className="font-brand text-[17px] font-black tracking-[0.12em] text-slate-900 group-hover:text-blue-600 transition-colors">
                                    MARILIN
                                </span>
                            </div>
                            <span className="text-[9px] font-bold tracking-[0.18em] uppercase text-blue-600 -mt-1 font-sans">
                                DISTRIBUIDORA
                            </span>
                        </div>
                    </Link>
                    <button
                        className="lg:hidden p-1 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                        onClick={() => setSidebarOpen(false)}
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                {/* Navigation Items */}
                <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                    <div className="px-3 pb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        Operaciones
                    </div>
                    {navigation.map((item) => {
                        const Icon = item.icon;
                        return (
                            <Link
                                key={item.name}
                                href={item.href}
                                className={`flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                                    item.active
                                        ? 'bg-blue-50 text-blue-700 font-semibold'
                                        : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                                }`}
                            >
                                <Icon
                                    className={`w-4 h-4 ${
                                        item.active ? 'text-blue-600' : 'text-slate-400'
                                    }`}
                                />
                                <span className="flex-1">{item.name}</span>
                                {item.badge && (
                                    <span className="px-1.5 py-0.5 text-xs font-semibold bg-blue-100 text-blue-700 rounded-full">
                                        {item.badge}
                                    </span>
                                )}
                            </Link>
                        );
                    })}
                </nav>

                {/* Operational Status Box */}
                <div className="p-3 m-3 bg-slate-50 rounded-lg border border-slate-200 text-xs">
                    <div className="flex items-center gap-2 text-slate-700 font-medium mb-1">
                        <MapPin className="w-3.5 h-3.5 text-blue-600" />
                        <span>Base Central Jujuy</span>
                    </div>
                    <p className="text-[11px] text-slate-500">Distribución de Agua & Soda</p>
                </div>

                {/* User Footer */}
                <div className="p-3 border-t border-slate-100 flex items-center justify-between">
                    <div className="flex items-center gap-2.5 min-w-0">
                        <div className="w-8 h-8 rounded-full bg-blue-50 border border-blue-100 flex items-center justify-center text-xs font-bold text-blue-700">
                            {user.name.charAt(0).toUpperCase()}
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-semibold text-slate-900 truncate">{user.name}</p>
                            <p className="text-[11px] text-slate-500 truncate">Administrador</p>
                        </div>
                    </div>
                    <button
                        onClick={() => setShowLogoutModal(true)}
                        className="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer"
                        title="Cerrar sesión"
                    >
                        <LogOut className="w-4 h-4" />
                    </button>
                </div>
            </aside>

            {/* Main Area */}
            <div className="flex-1 lg:pl-60 flex flex-col min-w-0">
                {/* Topbar */}
                <header className="h-16 bg-white border-b border-slate-200 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => setSidebarOpen(true)}
                            className="lg:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                        >
                            <Menu className="w-5 h-5" />
                        </button>
                        {header}
                    </div>

                    <div className="flex items-center gap-3">
                        {/* Notifications */}
                        <button className="relative p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition">
                            <Bell className="w-5 h-5" />
                            <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-orange-500 rounded-full"></span>
                        </button>

                        <div className="h-5 w-[1px] bg-slate-200"></div>

                        {/* User Menu */}
                        <div className="relative">
                            <button
                                onClick={() => setUserMenuOpen(!userMenuOpen)}
                                className="flex items-center gap-2 text-sm font-medium text-slate-700 hover:text-slate-900"
                            >
                                <span className="hidden sm:inline-block text-xs font-semibold">{user.name}</span>
                                <ChevronDown className="w-3.5 h-3.5 text-slate-400" />
                            </button>

                            {userMenuOpen && (
                                <div className="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-slate-100 py-1 z-50">
                                    <Link
                                        href={route('profile.edit')}
                                        className="block px-4 py-2 text-xs text-slate-700 hover:bg-slate-50"
                                        onClick={() => setUserMenuOpen(false)}
                                    >
                                        Mi Perfil
                                    </Link>
                                    <button
                                        type="button"
                                        className="w-full text-left block px-4 py-2 text-xs text-rose-600 hover:bg-rose-50 cursor-pointer"
                                        onClick={() => {
                                            setUserMenuOpen(false);
                                            setShowLogoutModal(true);
                                        }}
                                    >
                                        Cerrar Sesión
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </header>

                {/* Page Content */}
                <main className="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
                    {children}
                </main>
            </div>

            {/* Confirmation Logout Modal */}
            {showLogoutModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    {/* Backdrop */}
                    <div
                        className="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity"
                        onClick={() => setShowLogoutModal(false)}
                    />

                    {/* Modal Card */}
                    <div className="relative bg-white rounded-xl shadow-2xl border border-slate-200 max-w-md w-full p-6 overflow-hidden z-10 animate-in fade-in zoom-in-95 duration-150">
                        <div className="flex items-start gap-4">
                            <div className="w-11 h-11 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600 shrink-0">
                                <AlertTriangle className="w-5 h-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-base font-bold font-display text-slate-900">
                                    ¿Estás seguro de cerrar sesión?
                                </h3>
                                <p className="mt-1.5 text-xs text-slate-500 leading-relaxed">
                                    Finalizarás tu sesión administrativa actual en Marilin Web. Para volver a acceder deberás ingresar tus credenciales nuevamente.
                                </p>
                            </div>
                        </div>

                        {/* Action Buttons */}
                        <div className="mt-6 flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setShowLogoutModal(false)}
                                className="px-4 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition cursor-pointer"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={handleConfirmLogout}
                                className="px-4 py-2 rounded-lg bg-rose-600 text-xs font-semibold text-white hover:bg-rose-700 transition shadow-xs cursor-pointer flex items-center gap-1.5"
                            >
                                <LogOut className="w-3.5 h-3.5" />
                                Cerrar Sesión
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

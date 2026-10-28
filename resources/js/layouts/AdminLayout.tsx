import React, { PropsWithChildren, ReactNode, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { useTheme } from '@/lib/theme';
import ThemeSwitch from '@/ui/ThemeSwitch';
import {
    LayoutDashboard,
    Truck,
    Users,
    CalendarCheck,
    Receipt,
    CircleDollarSign,
    Package,
    SlidersHorizontal,
    Tag,
    UserCog,
    LogOut,
    Menu,
    X,
    Bell,
    ChevronDown,
    MapPin,
    AlertTriangle,
    ClipboardCheck,
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
    // Applies the saved theme and follows the system while the choice is "system", menu open or not.
    useTheme();
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
            name: 'Choferes',
            href: route('drivers.index'),
            icon: Truck,
            active: route().current('drivers.*') || route().current('repartidores.*') || false,
        },
        {
            name: 'Clientes',
            href: route('customers.index'),
            icon: Users,
            active: route().current('customers.*') || false,
        },
        {
            name: 'Despacho y Rutas',
            href: route('routes.index'),
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
        ...(user.is_super_user
            ? [
                  {
                      name: 'Productos',
                      href: route('products.index'),
                      icon: Tag,
                      active: route().current('products.*') || false,
                  },
                  {
                      name: 'Revisión',
                      href: route('review.index'),
                      icon: ClipboardCheck,
                      active: route().current('review.*') || false,
                  },
                  {
                      name: 'Usuarios',
                      href: route('users.index'),
                      icon: UserCog,
                      active: route().current('users.*') || false,
                  },
              ]
            : []),
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
                    className="fixed inset-0 z-40 bg-gray-900/40 backdrop-blur-sm lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Sidebar */}
            <aside
                className={`fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200/60 flex flex-col transition-transform duration-200 ease-in-out lg:translate-x-0 ${
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                {/* Brand Logo & Header */}
                <div className="flex items-center justify-between px-6 bg-white border-b border-gray-200/60 h-16 lg:hidden">
                    <div className="flex-1"></div>
                    <button
                        className="p-1.5 rounded-lg text-slate-500 hover:bg-slate-100"
                        onClick={() => setSidebarOpen(false)}
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                {/* Navigation Items */}
                <nav className="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                    <div className="px-3 pb-3 text-xs font-semibold text-slate-500">
                        Operaciones
                    </div>
                    {navigation.map((item) => {
                        const Icon = item.icon;
                        return (
                            <Link
                                key={item.name}
                                href={item.href}
                                className={`flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors ${
                                    item.active
                                        ? 'bg-slate-100 text-slate-900 font-medium'
                                        : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium'
                                }`}
                            >
                                <Icon
                                    className={`w-5 h-5 ${
                                        item.active ? 'text-slate-900' : 'text-slate-500 group-hover:text-slate-700'
                                    }`}
                                    strokeWidth={2}
                                />
                                <span className="flex-1">{item.name}</span>
                                {item.badge && (
                                    <span className={`px-2 py-0.5 text-xs font-medium rounded-full ${
                                        item.active ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600'
                                    }`}>
                                        {item.badge}
                                    </span>
                                )}
                            </Link>
                        );
                    })}
                </nav>

                {/* User Footer */}
                <div className="p-4 bg-white border-t border-gray-200/60 flex items-center justify-between">
                    <div className="flex items-center gap-3 min-w-0">
                        <div className="w-10 h-10 rounded-full bg-slate-100 border border-gray-200/60 flex items-center justify-center text-sm font-semibold text-slate-700">
                            {user.name.charAt(0).toUpperCase()}
                        </div>
                        <div className="min-w-0">
                            <p className="text-sm font-medium text-slate-900 truncate">{user.name}</p>
                            <p className="text-xs text-slate-500 truncate">Administrador</p>
                        </div>
                    </div>
                    <button
                        onClick={() => setShowLogoutModal(true)}
                        className="p-2 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition cursor-pointer"
                        title="Cerrar sesión"
                    >
                        <LogOut className="w-5 h-5" strokeWidth={2} />
                    </button>
                </div>
            </aside>

            {/* Main Area */}
            <div className="flex-1 lg:pl-64 flex flex-col min-w-0">
                {/* Topbar */}
                <header className="h-16 bg-white/80 backdrop-blur-md border-b border-gray-200/60 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6 lg:px-8 shadow-sm">
                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => setSidebarOpen(true)}
                            className="lg:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                        >
                            <Menu className="w-5 h-5" />
                        </button>
                        <div className="text-slate-900 font-medium text-sm">
                            {header}
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        {/* Notifications */}
                        <button className="relative p-2 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition">
                            <Bell className="w-5 h-5" strokeWidth={2} />
                            <span className="absolute top-2 right-2 w-2 h-2 bg-rose-500 rounded-full border-2 border-white"></span>
                        </button>

                        <div className="h-6 w-px bg-slate-200"></div>

                        {/* User Menu */}
                        <div className="relative">
                            <button
                                onClick={() => setUserMenuOpen(!userMenuOpen)}
                                className="flex items-center gap-2 text-sm font-medium text-slate-700 hover:text-slate-900"
                            >
                                <span className="hidden sm:inline-block">{user.name}</span>
                                <ChevronDown className="w-4 h-4 text-slate-500" strokeWidth={2} />
                            </button>

                            {userMenuOpen && (
                                <div className="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-200/60 py-1.5 z-50">
                                    <Link
                                        href={route('profile.edit')}
                                        className="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                                        onClick={() => setUserMenuOpen(false)}
                                    >
                                        Mi perfil
                                    </Link>
                                    <div className="px-4 py-2">
                                        <p className="text-xs text-slate-500 mb-1.5">Tema</p>
                                        <ThemeSwitch />
                                    </div>
                                    <button
                                        type="button"
                                        className="w-full text-left block px-4 py-2 text-sm text-rose-600 hover:bg-rose-50 cursor-pointer"
                                        onClick={() => {
                                            setUserMenuOpen(false);
                                            setShowLogoutModal(true);
                                        }}
                                    >
                                        Cerrar sesión
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </header>

                {/* Page Content */}
                <main className="flex-1 p-4 sm:p-6 lg:p-8 w-full max-w-none mx-auto bg-slate-50">
                    {children}
                </main>
            </div>

            {/* Confirmation Logout Modal */}
            {showLogoutModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    {/* Backdrop */}
                    <div
                        className="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity"
                        onClick={() => setShowLogoutModal(false)}
                    />

                    {/* Modal Card */}
                    <div className="relative bg-white rounded-2xl shadow-xl border border-gray-200/60 max-w-md w-full p-6 overflow-hidden z-10">
                        <div className="flex items-start gap-4">
                            <div className="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 shrink-0">
                                <AlertTriangle className="w-5 h-5" strokeWidth={2} />
                            </div>
                            <div className="flex-1 pt-1">
                                <h3 className="text-lg font-semibold text-slate-900">
                                    Cerrar sesión
                                </h3>
                                <p className="mt-2 text-sm text-slate-500">
                                    Finalizarás tu sesión actual. Deberás ingresar credenciales nuevamente para acceder.
                                </p>
                            </div>
                        </div>

                        {/* Action Buttons */}
                        <div className="mt-8 flex items-center justify-end gap-3">
                            <button
                                type="button"
                                onClick={() => setShowLogoutModal(false)}
                                className="px-4 py-2.5 rounded-lg text-sm font-medium text-slate-700 bg-white border border-gray-200/60 hover:bg-slate-50 transition cursor-pointer"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={handleConfirmLogout}
                                className="px-4 py-2.5 rounded-lg bg-sky-600 text-sm font-medium text-white hover:bg-sky-700 transition shadow-sm cursor-pointer flex items-center gap-2"
                            >
                                <LogOut className="w-4 h-4" strokeWidth={2} />
                                Cerrar sesión
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

import React, { PropsWithChildren, ReactNode, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { useTheme } from '@/lib/theme';
import ThemeSwitch from '@/ui/ThemeSwitch';
import NotificationsMenu, { NotificationItem } from '@/ui/NotificationsMenu';
import Toast from '@/ui/Toast';
import Modal, { ModalTitle } from '@/ui/Modal';
import { useFlashToast } from '@/lib/useFlashToast';
import { time } from '@/lib/format';
import type { Notifications } from '@/types';
import {
    LayoutDashboard,
    Truck,
    Users,
    CalendarCheck,
    Receipt,
    Package,
    SlidersHorizontal,
    Tag,
    UserCog,
    LogOut,
    Menu,
    X,
    ChevronDown,
    MapPin,
    AlertTriangle,
    ClipboardCheck,
} from 'lucide-react';

function notificationItems(notifications: Notifications | undefined): NotificationItem[] {
    if (!notifications) return [];

    const drivers = notifications.unsyncedDrivers.map((driver) => ({
        key: `driver-${driver.id}`,
        label: driver.lastSyncAt
            ? `${driver.name} está en ruta y no sincroniza desde las ${time(driver.lastSyncAt)}`
            : `${driver.name} está en ruta y nunca sincronizó`,
        href: route('drivers.show', driver.id),
    }));
    const pending = notifications.pendingReview;
    const review =
        pending > 0
            ? [{
                  key: 'review',
                  label: pending === 1 ? '1 registro espera revisión' : `${pending} registros esperan revisión`,
                  href: route('review.index'),
              }]
            : [];

    return [...drivers, ...review];
}

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
    const { auth, notifications } = usePage().props;
    const user = auth.user;
    // Applies the saved theme and follows the system while the choice is "system", menu open or not.
    useTheme();
    const [toast, dismissToast] = useFlashToast();
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const [showLogoutModal, setShowLogoutModal] = useState(false);

    const handleConfirmLogout = () => {
        router.post(route('logout'));
    };

    const navigation: NavItem[] = [
        {
            name: 'Panel',
            href: route('dashboard'),
            icon: LayoutDashboard,
            active: route().current('dashboard'),
        },
        {
            name: 'Repartidores',
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
            name: 'Despacho y rutas',
            href: route('routes.index'),
            icon: CalendarCheck,
            active: route().current('routes.*') || false,
        },
        {
            name: 'Ventas y cobros',
            href: route('sales.index'),
            icon: Receipt,
            active: route().current('sales.*') || route().current('payments.*') || false,
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
            name: 'Cierre de caja',
            href: route('cash-close.index'),
            icon: Package,
            active: route().current('cash-close.*') || false,
        },
    ];

    return (
        <div className="min-h-screen bg-canvas-soft dark:bg-transparent text-ink flex font-sans antialiased">
            {/* Mobile Sidebar Backdrop */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-gray-900/40 dark:bg-black/70 backdrop-blur-sm lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Sidebar */}
            <aside
                className={`fixed inset-y-0 left-0 z-50 w-64 bg-canvas border-r border-hairline/60 flex flex-col transition-transform duration-200 ease-in-out lg:translate-x-0 ${
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                {/* Brand Logo & Header */}
                <div className="flex items-center justify-between px-6 bg-canvas border-b border-hairline/60 h-16 lg:hidden">
                    <div className="flex-1"></div>
                    <button
                        type="button"
                        aria-label="Cerrar menú"
                        className="p-1.5 rounded-lg text-ink-muted hover:bg-surface-1"
                        onClick={() => setSidebarOpen(false)}
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                {/* Navigation Items */}
                <nav className="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                    <div className="px-3 pb-3 text-xs font-semibold text-ink-muted">
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
                                        ? 'bg-surface-1 text-ink font-medium'
                                        : 'text-ink-secondary hover:bg-canvas-soft hover:text-ink font-medium'
                                }`}
                            >
                                <Icon
                                    className={`w-5 h-5 ${
                                        item.active ? 'text-ink' : 'text-ink-muted group-hover:text-ink-secondary'
                                    }`}
                                    strokeWidth={2}
                                />
                                <span className="flex-1">{item.name}</span>
                                {item.badge && (
                                    <span className={`px-2 py-0.5 text-xs font-medium rounded-full ${
                                        item.active ? 'bg-sky-50 dark:bg-sky-500/15 text-sky-700 dark:text-sky-300' : 'bg-surface-1 text-ink-secondary'
                                    }`}>
                                        {item.badge}
                                    </span>
                                )}
                            </Link>
                        );
                    })}
                </nav>

                {/* User Footer */}
                <div className="p-4 bg-canvas border-t border-hairline/60 flex items-center justify-between">
                    <div className="flex items-center gap-3 min-w-0">
                        <div className="w-10 h-10 rounded-full bg-surface-1 border border-hairline/60 flex items-center justify-center text-sm font-semibold text-ink-secondary">
                            {user.name.charAt(0).toUpperCase()}
                        </div>
                        <div className="min-w-0">
                            <p className="text-sm font-medium text-ink truncate">{user.name}</p>
                            <p className="text-xs text-ink-muted truncate">Administrador</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => setShowLogoutModal(true)}
                        className="p-2 rounded-lg text-ink-muted hover:text-ink hover:bg-surface-1 transition cursor-pointer"
                        aria-label="Cerrar sesión"
                        title="Cerrar sesión"
                    >
                        <LogOut className="w-5 h-5" strokeWidth={2} />
                    </button>
                </div>
            </aside>

            {/* Main Area */}
            <div className="flex-1 lg:pl-64 flex flex-col min-w-0">
                {/* Topbar */}
                <header className="h-16 bg-canvas/80 backdrop-blur-md border-b border-hairline/60 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6 lg:px-8 shadow-sm ">
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={() => setSidebarOpen(true)}
                            aria-label="Abrir menú"
                            aria-expanded={sidebarOpen}
                            className="lg:hidden p-2 rounded-lg text-ink-muted hover:bg-surface-1 hover:text-ink"
                        >
                            <Menu className="w-5 h-5" />
                        </button>
                        <div className="text-ink font-medium text-sm">
                            {header}
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        {notifications && (
                            <>
                                <NotificationsMenu items={notificationItems(notifications)} />
                                <div className="h-6 w-px bg-surface-2"></div>
                            </>
                        )}

                        {/* User Menu */}
                        <div className="relative">
                            <button
                                type="button"
                                onClick={() => setUserMenuOpen(!userMenuOpen)}
                                // On a phone the name is hidden, so the button carries it for screen readers too.
                                aria-label={`Menú de ${user.name}`}
                                aria-haspopup="true"
                                aria-expanded={userMenuOpen}
                                className="flex items-center gap-2 text-sm font-medium text-ink-secondary hover:text-ink"
                            >
                                <span className="hidden sm:inline-block">{user.name}</span>
                                <ChevronDown className="w-4 h-4 text-ink-muted" strokeWidth={2} />
                            </button>

                            {userMenuOpen && (
                                <div className="absolute right-0 mt-2 w-48 bg-canvas rounded-xl shadow-lg border border-hairline/60 py-1.5 z-50 ">
                                    <Link
                                        href={route('profile.edit')}
                                        className="block px-4 py-2 text-sm text-ink-secondary hover:bg-canvas-soft"
                                        onClick={() => setUserMenuOpen(false)}
                                    >
                                        Mi perfil
                                    </Link>
                                    <div className="px-4 py-2">
                                        <p className="text-xs text-ink-muted mb-1.5">Tema</p>
                                        <ThemeSwitch />
                                    </div>
                                    <button
                                        type="button"
                                        className="w-full text-left block px-4 py-2 text-sm text-rose-600 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-500/15 cursor-pointer"
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
                <main className="flex-1 p-4 sm:p-6 lg:p-8 w-full max-w-none mx-auto bg-canvas-soft">
                    {children}
                </main>
            </div>

            <Toast toast={toast} onDismiss={dismissToast} />

            {/* Confirmation Logout Modal */}
            <Modal show={showLogoutModal} onClose={() => setShowLogoutModal(false)} maxWidth="md">
                <div className="p-6">
                    <div className="flex items-start gap-4">
                        <div className="w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-500/15 flex items-center justify-center text-rose-600 dark:text-rose-300 shrink-0">
                            <AlertTriangle className="w-5 h-5" strokeWidth={2} />
                        </div>
                        <div className="flex-1 pt-1">
                            <ModalTitle className="text-lg font-semibold text-ink">
                                Cerrar sesión
                            </ModalTitle>
                            <p className="mt-2 text-sm text-ink-muted">
                                Finalizarás tu sesión actual. Deberás ingresar credenciales nuevamente para acceder.
                            </p>
                        </div>
                    </div>

                    {/* Action Buttons */}
                    <div className="mt-8 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => setShowLogoutModal(false)}
                            className="px-4 py-2.5 rounded-lg text-sm font-medium text-ink-secondary bg-canvas border border-hairline/60 hover:bg-canvas-soft transition cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            onClick={handleConfirmLogout}
                            className="px-4 py-2.5 rounded-lg bg-sky-600 text-sm font-medium text-white hover:bg-sky-700 transition shadow-sm cursor-pointer flex items-center gap-2 dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400"
                        >
                            <LogOut className="w-4 h-4" strokeWidth={2} />
                            Cerrar sesión
                        </button>
                    </div>
                </div>
            </Modal>
        </div>
    );
}

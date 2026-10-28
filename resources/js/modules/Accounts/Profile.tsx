import AdminLayout from '@/layouts/AdminLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';

import UpdatePasswordForm from './components/UpdatePasswordForm';
import UpdateProfileInformationForm from './components/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
}: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-ink">
                        Configuración
                    </span>
                </div>
            }
        >
            <Head title="Configuración de Cuenta" />

            <div className="max-w-5xl">
                <div className="mb-8">
                    <h1 className="text-3xl font-semibold text-ink tracking-tight">
                        Cuenta
                    </h1>
                    <p className="text-sm text-ink-muted mt-2">
                        Administrá tu información personal, correo electrónico y la seguridad de tu perfil.
                    </p>
                </div>

                <div className="divide-y divide-hairline/60">
                    
                    {/* Sección 1: Perfil */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-8 py-8">
                        <div className="md:col-span-1">
                            <h2 className="text-base font-semibold text-ink">Información del perfil</h2>
                            <p className="mt-1 text-sm text-ink-muted">
                                Actualizá tu nombre y la dirección de correo electrónico con la que ingresás al sistema.
                            </p>
                        </div>
                        <div className="md:col-span-2 bg-canvas shadow-[0_1px_3px_rgb(0,0,0,0.02)] sm:rounded-2xl border border-hairline/60 overflow-hidden">
                            <div className="px-6 py-6 sm:p-8">
                                <UpdateProfileInformationForm
                                    mustVerifyEmail={mustVerifyEmail}
                                    status={status}
                                    className="max-w-xl"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Sección 2: Contraseña */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-8 py-8">
                        <div className="md:col-span-1">
                            <h2 className="text-base font-semibold text-ink">Seguridad</h2>
                            <p className="mt-1 text-sm text-ink-muted">
                                Asegurate de que tu cuenta use una contraseña larga y aleatoria para mantenerte seguro.
                            </p>
                        </div>
                        <div className="md:col-span-2 bg-canvas shadow-[0_1px_3px_rgb(0,0,0,0.02)] sm:rounded-2xl border border-hairline/60 overflow-hidden">
                            <div className="px-6 py-6 sm:p-8">
                                <UpdatePasswordForm className="max-w-xl" />
                            </div>
                        </div>
                    </div>



                </div>
            </div>
        </AdminLayout>
    );
}

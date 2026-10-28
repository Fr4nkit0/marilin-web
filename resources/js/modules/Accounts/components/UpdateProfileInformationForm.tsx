import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import Button from '@/ui/Button';
import { FieldError, Input, Label } from '@/ui/Field';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}: {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <section className={className}>
            <form onSubmit={submit} className="space-y-6">
                <div>
                    <Label htmlFor="name" value="Nombre" className="text-slate-700 font-medium" />

                    <Input
                        id="name"
                        className="mt-2 block w-full transition-all border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        autoFocus
                        autoComplete="name"
                    />

                    <FieldError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <Label htmlFor="email" value="Correo electrónico" className="text-slate-700 font-medium" />

                    <Input
                        id="email"
                        type="email"
                        className="mt-2 block w-full transition-all border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <FieldError className="mt-2" message={errors.email} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-slate-800">
                            Tu dirección de correo electrónico no está verificada.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="rounded-md text-sm text-indigo-600 underline hover:text-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ml-1"
                            >
                                Hacé clic acá para reenviar el correo de verificación.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-emerald-600">
                                Se ha enviado un nuevo enlace de verificación a tu dirección de correo electrónico.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <Button type="submit" processing={processing}>Guardar</Button>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-emerald-600 font-medium">
                            Guardado.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}

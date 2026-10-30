import GuestLayout from '@/layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import Button from '@/ui/Button';
import { Checkbox, FieldError, Input, Label } from '@/ui/Field';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Iniciar sesión" />

            {status && (
                <div className="mb-4 text-sm font-medium text-emerald-600 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-500/15 p-3 rounded-lg border border-emerald-100 dark:border-emerald-500/30">
                    {status}
                </div>
            )}
            
            <div className="mb-8">
                <h2 className="text-2xl font-semibold text-ink tracking-tight text-center">Bienvenido de nuevo</h2>
                <p className="text-sm text-ink-muted mt-2 text-center">Ingresá tus credenciales para acceder al sistema</p>
            </div>

            <form onSubmit={submit} noValidate className="space-y-6">
                <div>
                    <Label htmlFor="email" value="Correo electrónico" className="text-ink-secondary font-medium" />

                    <Input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-2 block w-full transition-all border-hairline focus:border-sky-500 focus:ring-sky-500 rounded-lg shadow-sm"
                        autoComplete="username"
                        autoFocus
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="ejemplo@marilin.com"
                    />

                    <FieldError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <div className="flex items-center justify-between">
                        <Label htmlFor="password" value="Contraseña" className="text-ink-secondary font-medium" />
                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="text-sm font-medium text-sky-600 dark:text-sky-300 hover:text-sky-500 transition-colors"
                            >
                                ¿Olvidaste tu contraseña?
                            </Link>
                        )}
                    </div>

                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-2 block w-full transition-all border-hairline focus:border-sky-500 focus:ring-sky-500 rounded-lg shadow-sm"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder="••••••••"
                    />

                    <FieldError message={errors.password} className="mt-2" />
                </div>

                <div className="flex items-center justify-between">
                    <label className="flex items-center gap-2 cursor-pointer group">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData(
                                    'remember',
                                    (e.target.checked || false) as false,
                                )
                            }
                            className="rounded border-hairline-strong text-sky-600 dark:text-sky-300 shadow-sm focus:ring-sky-500 cursor-pointer"
                        />
                        <span className="text-sm text-ink-secondary group-hover:text-ink transition-colors">
                            Recordarme
                        </span>
                    </label>
                </div>

                <Button type="submit" className="w-full justify-center py-3 text-base" processing={processing}>
                    Iniciar sesión
                </Button>
            </form>
        </GuestLayout>
    );
}

import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';
import Button from '@/ui/Button';
import { FieldError, Input, Label } from '@/ui/Field';

export default function UpdatePasswordForm({
    className = '',
}: {
    className?: string;
}) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <section className={className}>
            <form onSubmit={updatePassword} className="space-y-6">
                <div>
                    <Label
                        htmlFor="current_password"
                        value="Contraseña actual"
                        className="text-slate-700 font-medium"
                    />

                    <Input
                        id="current_password"
                        ref={currentPasswordInput}
                        value={data.current_password}
                        onChange={(e) =>
                            setData('current_password', e.target.value)
                        }
                        type="password"
                        className="mt-2 block w-full transition-all border-gray-200 focus:border-sky-500 focus:ring-sky-500 rounded-lg shadow-sm"
                        autoComplete="current-password"
                    />

                    <FieldError
                        message={errors.current_password}
                        className="mt-2"
                    />
                </div>

                <div>
                    <Label htmlFor="password" value="Nueva contraseña" className="text-slate-700 font-medium" />

                    <Input
                        id="password"
                        ref={passwordInput}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        type="password"
                        className="mt-2 block w-full transition-all border-gray-200 focus:border-sky-500 focus:ring-sky-500 rounded-lg shadow-sm"
                        autoComplete="new-password"
                    />

                    <FieldError message={errors.password} className="mt-2" />
                </div>

                <div>
                    <Label
                        htmlFor="password_confirmation"
                        value="Confirmar contraseña"
                        className="text-slate-700 font-medium"
                    />

                    <Input
                        id="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                        type="password"
                        className="mt-2 block w-full transition-all border-gray-200 focus:border-sky-500 focus:ring-sky-500 rounded-lg shadow-sm"
                        autoComplete="new-password"
                    />

                    <FieldError
                        message={errors.password_confirmation}
                        className="mt-2"
                    />
                </div>

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

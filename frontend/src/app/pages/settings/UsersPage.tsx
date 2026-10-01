import { useQuery } from '@tanstack/react-query';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { Avatar, Badge, PageHeader, Panel } from '@/components/ui/Display';
import { FormAlert, PasswordField, SelectField, TextAreaField, TextField } from '@/components/ui/Field';
import { QueryState, useToast } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useForm } from '@/hooks/useForm';
import { get, patch, post } from '@/lib/api';
import { formatDateTime } from '@/lib/format';
import { labelOf, useMeta } from '@/lib/meta';
import type { User } from '@/lib/types';
import { useSession } from '@/session/SessionProvider';
import { useAction, useInvalidate } from '../../useAction';

function NewUserForm() {
  const { data: meta } = useMeta();
  const toast = useToast();
  const invalidate = useInvalidate();
  const empty = { full_name: '', username: '', email: '', password: '', role: 'psychologist', license_number: '', specialty: '' };
  const form = useForm({
    initial: empty,
    validate: (values) => {
      const errors: Record<string, string> = {};
      if (!values.full_name.trim()) errors.full_name = 'Escribe el nombre.';
      if (!values.username.trim()) errors.username = 'Elige un usuario.';
      if (!values.email.trim()) errors.email = 'Escribe el correo.';
      if (values.password.length < 10) errors.password = 'Usa al menos 10 caracteres.';
      return errors;
    },
    onSubmit: async (values) => {
      const result = await post('/api/users', values);
      toast.success(result.message ?? 'Usuario creado.');
      form.setValues(empty);
      await invalidate(['users'], ['meta']);
    },
  });

  return (
    <form className="inline-form" onSubmit={form.handleSubmit} noValidate>
      <FormAlert message={form.formError} />
      <TextField label="Nombre completo" {...form.bind('full_name')} />
      <TextField label="Usuario" autoCapitalize="none" {...form.bind('username')} />
      <TextField label="Correo" type="email" {...form.bind('email')} />
      <PasswordField label="Contraseña inicial" autoComplete="new-password" hint="Mínimo 10 caracteres. Pide que la cambie en su primer ingreso." {...form.bind('password')} />
      <SelectField label="Rol" options={meta?.roles ?? []} {...form.bind('role')} />
      <TextField label="Registro profesional" optional {...form.bind('license_number')} />
      <TextField label="Especialidad" optional {...form.bind('specialty')} />
      <div>
        <Button type="submit" variant="primary" loading={form.submitting}>
          Crear usuario
        </Button>
      </div>
    </form>
  );
}

function PublicProfileDialog({ user, onClose }: { user: User; onClose: () => void }) {
  const toast = useToast();
  const invalidate = useInvalidate();
  const [show, setShow] = useState(user.show_on_site);
  const form = useForm({
    initial: { specialty: user.specialty ?? '', license_number: user.license_number ?? '', public_bio: user.public_bio ?? '' },
    onSubmit: async (values) => {
      const result = await patch(`/api/users/${user.id}`, { ...values, show_on_site: show });
      toast.success(result.message ?? 'Perfil actualizado.');
      await invalidate(['users'], ['public-site']);
      onClose();
    },
  });

  return (
    <form className="inline-form" onSubmit={form.handleSubmit} noValidate>
      <FormAlert message={form.formError} />
      <label className="check">
        <input type="checkbox" checked={show} onChange={(event) => setShow(event.target.checked)} />
        <span>Mostrar a {user.full_name.split(' ')[0]} en la sección “Equipo” del sitio público</span>
      </label>
      <TextField label="Especialidad" {...form.bind('specialty')} />
      <TextField label="Registro profesional" {...form.bind('license_number')} />
      <TextAreaField label="Presentación breve" optional rows={3} maxLength={400} hint="Escrita en tercera persona, sin promesas de resultados." {...form.bind('public_bio')} />
      <div className="cluster">
        <Button type="submit" variant="primary" loading={form.submitting}>
          Guardar
        </Button>
        <Button variant="quiet" onClick={onClose}>
          Cancelar
        </Button>
      </div>
    </form>
  );
}

export default function UsersPage() {
  const { data: meta } = useMeta();
  const { user: me } = useSession();
  const isAdmin = me?.role === 'admin';
  const [editing, setEditing] = useState<User | null>(null);
  const editorRef = useRef<HTMLDivElement>(null);
  useDocumentTitle('Usuarios');

  const query = useQuery({ queryKey: ['users'], queryFn: () => get<User[]>('/api/users') });
  const toggle = useAction((id: number) => post(`/api/users/${id}/toggle`), { invalidate: [['users'], ['meta']] });

  const openEditor = (user: User) => {
    setEditing(user);
    window.requestAnimationFrame(() => editorRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
  };

  return (
    <>
      <PageHeader title="Usuarios" subtitle="Cuentas del equipo y de los pacientes con acceso al portal." />
      <div className="layout-aside">
        <Panel flush title="Cuentas" titleId="cuentas">
          <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
            <ul className="list">
              {query.data?.map((user) => (
                <li key={user.id} className="list__item">
                  <Avatar name={user.full_name} size="sm" />
                  <span className="list__main">
                    <span className="list__title">
                      {user.full_name} {user.id === me?.id && <span className="muted">(tú)</span>}
                    </span>
                    <span className="list__meta">
                      {user.role === 'patient' ? `Paciente · ${user.record_number ?? user.username}` : labelOf(meta?.roles, user.role)}
                      {' · último ingreso '}
                      {formatDateTime(user.last_login_at)}
                    </span>
                  </span>
                  {user.show_on_site && <Badge tone="primary">En el sitio</Badge>}
                  {!user.is_active && <Badge>Inactivo</Badge>}
                  {isAdmin && user.role !== 'patient' && (
                    <Button size="sm" variant="quiet" onClick={() => openEditor(user)}>
                      Perfil público
                    </Button>
                  )}
                  {isAdmin && user.id !== me?.id && (
                    <Button size="sm" variant="quiet" onClick={() => toggle.run(user.id).catch(() => undefined)} disabled={toggle.pending}>
                      {user.is_active ? 'Desactivar' : 'Activar'}
                    </Button>
                  )}
                </li>
              ))}
            </ul>
          </QueryState>
        </Panel>

        {isAdmin && (
          <div className="section-gap" ref={editorRef}>
            {editing && (
              <Panel title={`Perfil público de ${editing.full_name}`} titleId="perfil-publico">
                <PublicProfileDialog key={editing.id} user={editing} onClose={() => setEditing(null)} />
              </Panel>
            )}
            <Panel title="Nuevo usuario" titleId="nuevo-usuario">
              <NewUserForm />
            </Panel>
          </div>
        )}
      </div>
    </>
  );
}

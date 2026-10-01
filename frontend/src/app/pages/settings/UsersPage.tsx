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

const DOCUMENT_HINT = 'Reported in RIPS for every consultation this professional attends.';

function NewUserForm() {
  const { data: meta } = useMeta();
  const toast = useToast();
  const invalidate = useInvalidate();
  const empty = {
    full_name: '',
    username: '',
    email: '',
    password: '',
    role: 'psychologist',
    license_number: '',
    specialty: '',
    document_type: 'CC',
    document_number: '',
  };
  const form = useForm({
    initial: empty,
    validate: (values) => {
      const errors: Record<string, string> = {};
      if (!values.full_name.trim()) errors.full_name = 'Enter the name.';
      if (!values.username.trim()) errors.username = 'Choose a username.';
      if (!values.email.trim()) errors.email = 'Enter the email.';
      if (values.password.length < 10) errors.password = 'Use at least 10 characters.';
      return errors;
    },
    onSubmit: async (values) => {
      const result = await post('/api/users', values);
      toast.success(result.message ?? 'User created.');
      form.setValues(empty);
      await invalidate(['users'], ['meta']);
    },
  });

  return (
    <form className="inline-form" onSubmit={form.handleSubmit} noValidate>
      <FormAlert message={form.formError} />
      <TextField label="Full name" {...form.bind('full_name')} />
      <TextField label="Username" autoCapitalize="none" {...form.bind('username')} />
      <TextField label="Email" type="email" {...form.bind('email')} />
      <PasswordField label="Initial password" autoComplete="new-password" hint="At least 10 characters. Ask them to change it the first time they sign in." {...form.bind('password')} />
      <SelectField label="Role" options={meta?.roles ?? []} {...form.bind('role')} />
      <TextField label="Professional license" optional {...form.bind('license_number')} />
      <TextField label="Specialty" optional {...form.bind('specialty')} />
      {form.values.role !== 'assistant' && (
        <>
          <SelectField label="ID document type" options={meta?.rips.documentTypes ?? []} {...form.bind('document_type')} />
          <TextField label="ID document number" optional inputMode="numeric" hint={DOCUMENT_HINT} {...form.bind('document_number')} />
        </>
      )}
      <div>
        <Button type="submit" variant="primary" loading={form.submitting}>
          Create user
        </Button>
      </div>
    </form>
  );
}

function ProfessionalProfileForm({ user, onClose }: { user: User; onClose: () => void }) {
  const { data: meta } = useMeta();
  const toast = useToast();
  const invalidate = useInvalidate();
  const [show, setShow] = useState(user.show_on_site);
  const form = useForm({
    initial: {
      specialty: user.specialty ?? '',
      license_number: user.license_number ?? '',
      public_bio: user.public_bio ?? '',
      document_type: user.document_type ?? 'CC',
      document_number: user.document_number ?? '',
    },
    onSubmit: async (values) => {
      const result = await patch(`/api/users/${user.id}`, { ...values, show_on_site: show });
      toast.success(result.message ?? 'Profile updated.');
      await invalidate(['users'], ['public-site'], ['session']);
      onClose();
    },
  });

  // Distinct ids: this form shares the page with the new-user form.
  const bind = (name: keyof typeof form.values & string) => ({ ...form.bind(name), id: `profile-${name}` });

  return (
    <form className="inline-form" onSubmit={form.handleSubmit} noValidate>
      <FormAlert message={form.formError} />
      <SelectField label="ID document type" options={meta?.rips.documentTypes ?? []} {...bind('document_type')} />
      <TextField label="ID document number" optional inputMode="numeric" hint={DOCUMENT_HINT} {...bind('document_number')} />
      <label className="check">
        <input type="checkbox" checked={show} onChange={(event) => setShow(event.target.checked)} />
        <span>Show {user.full_name.split(' ')[0]} in the “Team” section of the public site</span>
      </label>
      <TextField label="Specialty" {...bind('specialty')} />
      <TextField label="Professional license" {...bind('license_number')} />
      <TextAreaField label="Short introduction" optional rows={3} maxLength={400} hint="Written in the third person, with no promises of results." {...bind('public_bio')} />
      <div className="cluster">
        <Button type="submit" variant="primary" loading={form.submitting}>
          Save
        </Button>
        <Button variant="quiet" onClick={onClose}>
          Cancel
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
  useDocumentTitle('Users');

  const query = useQuery({ queryKey: ['users'], queryFn: () => get<User[]>('/api/users') });
  const toggle = useAction((id: number) => post(`/api/users/${id}/toggle`), { invalidate: [['users'], ['meta']] });

  const openEditor = (user: User) => {
    setEditing(user);
    window.requestAnimationFrame(() => editorRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
  };

  return (
    <>
      <PageHeader title="Users" subtitle="Team accounts and patients with access to the portal." />
      <div className="layout-aside">
        <Panel flush title="Accounts" titleId="accounts">
          <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
            <ul className="list">
              {query.data?.map((user) => (
                <li key={user.id} className="list__item">
                  <Avatar name={user.full_name} size="sm" />
                  <span className="list__main">
                    <span className="list__title">
                      {user.full_name} {user.id === me?.id && <span className="muted">(you)</span>}
                    </span>
                    <span className="list__meta">
                      {user.role === 'patient' ? `Patient · ${user.record_number ?? user.username}` : labelOf(meta?.roles, user.role)}
                      {user.role !== 'patient' && user.document_number && ` · ${user.document_type} ${user.document_number}`}
                      {' · last sign-in '}
                      {formatDateTime(user.last_login_at)}
                    </span>
                  </span>
                  {user.show_on_site && <Badge tone="primary">On the site</Badge>}
                  {user.role !== 'patient' && user.role !== 'assistant' && !user.document_number && <Badge tone="warning">No ID for RIPS</Badge>}
                  {!user.is_active && <Badge>Inactive</Badge>}
                  {isAdmin && user.role !== 'patient' && (
                    <Button size="sm" variant="quiet" onClick={() => openEditor(user)}>
                      Profile
                    </Button>
                  )}
                  {isAdmin && user.id !== me?.id && (
                    <Button size="sm" variant="quiet" onClick={() => toggle.run(user.id).catch(() => undefined)} disabled={toggle.pending}>
                      {user.is_active ? 'Deactivate' : 'Activate'}
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
              <Panel title={`Profile of ${editing.full_name}`} titleId="professional-profile">
                <ProfessionalProfileForm key={editing.id} user={editing} onClose={() => setEditing(null)} />
              </Panel>
            )}
            <Panel title="New user" titleId="new-user">
              <NewUserForm />
            </Panel>
          </div>
        )}
      </div>
    </>
  );
}

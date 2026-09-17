import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import './app.css';

const tokenKey = 'rekon_token';

async function api(path, options = {}) {
  const token = localStorage.getItem(tokenKey);
  const headers = { Accept: 'application/json', ...(options.headers || {}) };
  if (options.body && typeof options.body !== 'string') {
    headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(options.body);
  }
  if (token) headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`/api${path}`, { ...options, headers });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.message || 'Permintaan gagal.');
  return data;
}

function Login({ onLogin }) {
  const [email, setEmail] = useState('admin@example.test');
  const [password, setPassword] = useState('password');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  async function submit(event) {
    event.preventDefault(); setError(''); setBusy(true);
    try { const data = await api('/auth/login', { method: 'POST', body: { email, password } }); localStorage.setItem(tokenKey, data.token); onLogin(data.user); }
    catch (e) { setError(e.message); } finally { setBusy(false); }
  }
  return <main className="login-shell"><div className="login-card"><div className="brand-mark">RA</div><span className="eyebrow">REKON AKUNTANSI</span><h1>Masuk ke aplikasi</h1><p>Kelola sumber pengesahan, rekonsiliasi, dan Berita Acara.</p><form onSubmit={submit} className="form-stack"><label>Email<input type="email" value={email} onChange={e => setEmail(e.target.value)} required /></label><label>Password<input type="password" value={password} onChange={e => setPassword(e.target.value)} required /></label>{error && <div className="alert error">{error}</div>}<button className="primary" disabled={busy}>{busy ? 'Memproses…' : 'Masuk'}</button></form></div></main>;
}

function UserManagement({ onMessage }) {
  const [users, setUsers] = useState([]); const [skpds, setSkpds] = useState([]); const [editing, setEditing] = useState(null); const [busy, setBusy] = useState(false);
  const empty = { name: '', email: '', password: '', role: 'skpd', skpd_id: '', is_active: true };
  const [form, setForm] = useState(empty);
  async function load() { const [u, s] = await Promise.all([api('/users'), api('/skpds')]); setUsers(u); setSkpds(s); }
  useEffect(() => { load().catch(e => onMessage(e.message)); }, []);
  function startEdit(user) { setEditing(user.id); setForm({ name: user.name, email: user.email, password: '', role: user.role, skpd_id: user.skpd_id ?? '', is_active: !!user.is_active }); }
  function startNew() { setEditing(null); setForm(empty); }
  async function submit(e) { e.preventDefault(); setBusy(true); try { const body = { ...form, skpd_id: form.role === 'skpd' ? Number(form.skpd_id) : null }; if (!body.password) delete body.password; const data = editing ? await api(`/users/${editing}`, { method: 'PUT', body }) : await api('/users', { method: 'POST', body }); onMessage(editing ? 'Pengguna diperbarui.' : 'Pengguna dibuat.'); setEditing(null); setForm(empty); await load(); } catch (e) { onMessage(e.message); } finally { setBusy(false); } }
  return <div className="admin-grid"><section className="panel"><div className="panel-heading"><div><h2>{editing ? 'Edit pengguna' : 'Pengguna baru'}</h2><p>Kelola akun dan pembatasan akses berdasarkan SKPD.</p></div>{editing && <button className="secondary" onClick={startNew}>Batal</button>}</div><form className="form-grid" onSubmit={submit}><label>Nama<input value={form.name} onChange={e => setForm({...form,name:e.target.value})} required /></label><label>Email<input type="email" value={form.email} onChange={e => setForm({...form,email:e.target.value})} required /></label><label>Password<input type="password" value={form.password} onChange={e => setForm({...form,password:e.target.value})} placeholder={editing ? 'Kosongkan jika tidak berubah' : ''} required={!editing} /></label><label>Role<select value={form.role} onChange={e => setForm({...form,role:e.target.value,skpd_id:e.target.value === 'skpd' ? form.skpd_id : ''})}><option value="skpd">SKPD</option><option value="skpkd">SKPKD</option><option value="admin">Admin</option></select></label>{form.role === 'skpd' && <label>SKPD<select value={form.skpd_id} onChange={e => setForm({...form,skpd_id:e.target.value})} required><option value="">Pilih SKPD</option>{skpds.map(s => <option key={s.id} value={s.id}>{s.code} — {s.name}</option>)}</select></label>}<label className="checkbox"><input type="checkbox" checked={form.is_active} onChange={e => setForm({...form,is_active:e.target.checked})} /> Aktif</label><div className="form-actions"><button className="primary" disabled={busy}>{busy ? 'Menyimpan…' : editing ? 'Simpan perubahan' : 'Buat pengguna'}</button></div></form></section><section className="panel"><div className="panel-heading"><div><h2>Daftar pengguna</h2><p>{users.length} akun terdaftar.</p></div></div><div className="table-wrap"><table><thead><tr><th>Pengguna</th><th>Role</th><th>SKPD</th><th>Status</th><th></th></tr></thead><tbody>{users.map(u => <tr key={u.id}><td><strong>{u.name}</strong><span>{u.email}</span></td><td><span className="badge">{u.role}</span></td><td>{u.skpd?.code || '—'}</td><td><span className={`badge ${u.is_active ? 'active' : 'inactive'}`}>{u.is_active ? 'Aktif' : 'Nonaktif'}</span></td><td><button className="link-button" onClick={() => startEdit(u)}>Edit</button></td></tr>)}</tbody></table></div></section></div>;
}

function Dashboard({ user, onLogout }) {
  const [years, setYears] = useState([]); const [reconciliations, setReconciliations] = useState({ data: [] }); const [health, setHealth] = useState('checking'); const [view, setView] = useState('dashboard'); const [message, setMessage] = useState('');
  const admin = user.role === 'admin' || user.role === 'skpkd';
  async function load() { try { const [yearData, reconData] = await Promise.all([api('/years'), api('/reconciliations')]); setYears(yearData); setReconciliations(reconData); setHealth('ok'); } catch (e) { setHealth('error'); if (e.message.includes('Unauthenticated') || e.message.includes('tidak aktif')) onLogout(); else setMessage(e.message); } }
  useEffect(() => { load(); }, []);
  const activeYear = useMemo(() => years.find(y => y.is_active)?.year, [years]); const rows = reconciliations.data || []; const finalized = rows.filter(r => r.status === 'finalized').length; const drafts = rows.filter(r => r.status !== 'finalized').length;
  async function logout() { try { await api('/auth/logout', { method: 'POST' }); } catch (_) {} localStorage.removeItem(tokenKey); onLogout(); }
  const title = { dashboard:'Dashboard', reconciliations:'Rekonsiliasi', sources:'Sumber Pengesahan', users:'Pengguna', settings:'Administrasi' }[view];
  return <div className="app-shell"><aside className="sidebar"><div className="side-brand"><div className="brand-mark small">RA</div><div><strong>Rekon Akuntansi</strong><span>Pemerintah Daerah</span></div></div><nav><button className={view==='dashboard'?'nav-active':''} onClick={()=>setView('dashboard')}>Dashboard</button><button className={view==='reconciliations'?'nav-active':''} onClick={()=>setView('reconciliations')}>Rekonsiliasi</button><button className={view==='sources'?'nav-active':''} onClick={()=>setView('sources')}>Sumber Pengesahan</button>{admin && <><button className={view==='users'?'nav-active':''} onClick={()=>setView('users')}>Pengguna</button><button className={view==='settings'?'nav-active':''} onClick={()=>setView('settings')}>Administrasi</button></>}</nav><div className="side-user"><strong>{user.name}</strong><span>{user.role.toUpperCase()}</span><button onClick={logout}>Keluar</button></div></aside><main className="content"><header className="topbar"><div><span className="eyebrow">REKON AKUNTANSI</span><h1>{title}</h1></div><span className={`status ${health}`}>● {health==='ok'?'Terhubung':health==='checking'?'Memeriksa…':'Gangguan'}</span></header>{message && <div className="alert error page-alert">{message}</div>}
  {view==='dashboard' && <><section className="hero"><div><span className="eyebrow">PERIODE AKTIF</span><strong>{activeYear ?? '—'}</strong><p>Tahun anggaran yang sedang digunakan.</p></div><div className="hero-flow"><span>Sumber</span><i>→</i><span>Rekonsiliasi</span><i>→</i><span>BA & Snapshot</span></div></section><section className="stats"><article><small>TAHUN AKTIF</small><strong>{activeYear ?? '—'}</strong><span>Periode akuntansi</span></article><article><small>REKONSILIASI</small><strong>{rows.length}</strong><span>Total pada halaman aktif</span></article><article><small>SELESAI</small><strong>{finalized}</strong><span>Telah difinalisasi</span></article><article><small>DRAFT</small><strong>{drafts}</strong><span>Masih dapat diperiksa</span></article></section><section className="panel"><div className="panel-heading"><div><h2>Rekonsiliasi terbaru</h2><p>Snapshot dibuat ketika Berita Acara difinalisasi.</p></div><button className="secondary" onClick={()=>setView('reconciliations')}>Lihat semua</button></div><ReconTable rows={rows.slice(0,8)} /></section></>}
  {view==='reconciliations' && <section className="panel"><div className="panel-heading"><div><h2>Daftar rekonsiliasi</h2><p>Data mengikuti kewenangan pengguna berdasarkan SKPD.</p></div>{admin && <button className="primary" onClick={()=>setMessage('Form pembuatan rekonsiliasi akan dihubungkan setelah master dan sumber data siap.')}>+ Rekonsiliasi baru</button>}</div><ReconTable rows={rows} /></section>}
  {view==='sources' && <section className="panel empty"><h2>Sumber Pengesahan</h2><p>Endpoint sumber pengesahan sudah tersedia. Tampilan input dan pencocokan akan dibangun pada tahap workflow sumber data.</p></section>}
  {view==='users' && admin && <UserManagement onMessage={setMessage} />}
  {view==='settings' && admin && <section className="panel empty"><h2>Administrasi</h2><p>Master tahun aktif, SKPD, dan pejabat akan disatukan di area administrasi. User management sudah tersedia.</p></section>}
  </main></div>;
}

function ReconTable({ rows }) { if (!rows.length) return <div className="empty-table">Belum ada data rekonsiliasi.</div>; return <div className="table-wrap"><table><thead><tr><th>SKPD</th><th>Periode</th><th>Status</th><th>Finalisasi</th></tr></thead><tbody>{rows.map(row=><tr key={row.id}><td><strong>{row.skpd?.code||'—'}</strong><span>{row.skpd?.name||'—'}</span></td><td>{row.period_start||'—'} s/d {row.period_end||'—'}</td><td><span className={`badge ${row.status}`}>{row.status}</span></td><td>{row.finalized_at?new Date(row.finalized_at).toLocaleString('id-ID'):'Belum'}</td></tr>)}</tbody></table></div>; }

function App() { const [user,setUser]=useState(null); const [checking,setChecking]=useState(true); useEffect(()=>{ if(!localStorage.getItem(tokenKey)) return setChecking(false); api('/auth/me').then(setUser).catch(()=>localStorage.removeItem(tokenKey)).finally(()=>setChecking(false)); },[]); if(checking)return <main className="splash">Memuat aplikasi…</main>; return user?<Dashboard user={user} onLogout={()=>setUser(null)}/>:<Login onLogin={setUser}/>; }
createRoot(document.getElementById('app')).render(<App />);

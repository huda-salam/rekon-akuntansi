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
    try {
      const data = await api('/auth/login', { method: 'POST', body: { email, password } });
      localStorage.setItem(tokenKey, data.token); onLogin(data.user);
    } catch (e) { setError(e.message); } finally { setBusy(false); }
  }

  return <main className="login-shell">
    <div className="login-card">
      <div className="brand-mark">RA</div>
      <span className="eyebrow">REKON AKUNTANSI</span>
      <h1>Masuk ke aplikasi</h1>
      <p>Kelola sumber pengesahan, rekonsiliasi, dan Berita Acara.</p>
      <form onSubmit={submit} className="form-stack">
        <label>Email<input type="email" value={email} onChange={e => setEmail(e.target.value)} required /></label>
        <label>Password<input type="password" value={password} onChange={e => setPassword(e.target.value)} required /></label>
        {error && <div className="alert error">{error}</div>}
        <button className="primary" disabled={busy}>{busy ? 'Memproses…' : 'Masuk'}</button>
      </form>
    </div>
  </main>;
}

function Dashboard({ user, onLogout }) {
  const [years, setYears] = useState([]);
  const [reconciliations, setReconciliations] = useState({ data: [] });
  const [health, setHealth] = useState('checking');
  const [view, setView] = useState('dashboard');
  const [message, setMessage] = useState('');

  async function load() {
    try {
      const [yearData, reconData] = await Promise.all([api('/years'), api('/reconciliations')]);
      setYears(yearData); setReconciliations(reconData); setHealth('ok');
    } catch (e) {
      setHealth('error');
      if (e.message.includes('Unauthenticated')) onLogout(); else setMessage(e.message);
    }
  }

  useEffect(() => { load(); }, []);

  const activeYear = useMemo(() => years.find(y => y.is_active)?.year, [years]);
  const rows = reconciliations.data || [];
  const finalized = rows.filter(r => r.status === 'finalized').length;
  const drafts = rows.filter(r => r.status !== 'finalized').length;

  async function logout() {
    try { await api('/auth/logout', { method: 'POST' }); } catch (_) {}
    localStorage.removeItem(tokenKey); onLogout();
  }

  return <div className="app-shell">
    <aside className="sidebar">
      <div className="side-brand"><div className="brand-mark small">RA</div><div><strong>Rekon Akuntansi</strong><span>Pemerintah Daerah</span></div></div>
      <nav>
        <button className={view === 'dashboard' ? 'nav-active' : ''} onClick={() => setView('dashboard')}>Dashboard</button>
        <button className={view === 'reconciliations' ? 'nav-active' : ''} onClick={() => setView('reconciliations')}>Rekonsiliasi</button>
        <button className={view === 'sources' ? 'nav-active' : ''} onClick={() => setView('sources')}>Sumber Pengesahan</button>
        {user.role === 'admin' || user.role === 'skpkd' ? <button onClick={() => setView('settings')}>Administrasi</button> : null}
      </nav>
      <div className="side-user"><strong>{user.name}</strong><span>{user.role.toUpperCase()}</span><button onClick={logout}>Keluar</button></div>
    </aside>

    <main className="content">
      <header className="topbar"><div><span className="eyebrow">REKON AKUNTANSI</span><h1>{view === 'dashboard' ? 'Dashboard' : view === 'reconciliations' ? 'Rekonsiliasi' : view === 'sources' ? 'Sumber Pengesahan' : 'Administrasi'}</h1></div><span className={`status ${health}`}>● {health === 'ok' ? 'Terhubung' : health === 'checking' ? 'Memeriksa…' : 'Gangguan'}</span></header>
      {message && <div className="alert error">{message}</div>}

      {view === 'dashboard' && <>
        <section className="hero"><div><span className="eyebrow">PERIODE AKTIF</span><strong>{activeYear ?? '—'}</strong><p>Tahun anggaran yang sedang digunakan.</p></div><div className="hero-flow"><span>Sumber</span><i>→</i><span>Rekonsiliasi</span><i>→</i><span>BA & Snapshot</span></div></section>
        <section className="stats">
          <article><small>TAHUN AKTIF</small><strong>{activeYear ?? '—'}</strong><span>Periode akuntansi</span></article>
          <article><small>REKONSILIASI</small><strong>{rows.length}</strong><span>Total pada halaman aktif</span></article>
          <article><small>SELESAI</small><strong>{finalized}</strong><span>Telah difinalisasi</span></article>
          <article><small>DRAFT</small><strong>{drafts}</strong><span>Masih dapat diperiksa</span></article>
        </section>
        <section className="panel"><div className="panel-heading"><div><h2>Rekonsiliasi terbaru</h2><p>Snapshot dibuat ketika Berita Acara difinalisasi.</p></div><button className="secondary" onClick={() => setView('reconciliations')}>Lihat semua</button></div><ReconTable rows={rows.slice(0, 8)} /></section>
      </>}

      {view === 'reconciliations' && <section className="panel"><div className="panel-heading"><div><h2>Daftar rekonsiliasi</h2><p>Data mengikuti kewenangan pengguna berdasarkan SKPD.</p></div><button className="primary" onClick={() => setMessage('Form pembuatan rekonsiliasi siap pada tahap berikutnya.')}>+ Rekonsiliasi baru</button></div><ReconTable rows={rows} /></section>}
      {view === 'sources' && <section className="panel empty"><h2>Sumber Pengesahan</h2><p>Endpoint sumber pengesahan sudah tersedia. Tampilan impor/detail akan disambungkan pada iterasi berikutnya.</p></section>}
      {view === 'settings' && <section className="panel empty"><h2>Administrasi</h2><p>Pengelolaan tahun aktif, SKPD, pejabat, dan pengguna akan menggunakan endpoint admin yang sudah dipisahkan dari akses SKPD.</p></section>}
    </main>
  </div>;
}

function ReconTable({ rows }) {
  if (!rows.length) return <div className="empty-table">Belum ada data rekonsiliasi.</div>;
  return <div className="table-wrap"><table><thead><tr><th>SKPD</th><th>Periode</th><th>Status</th><th>Finalisasi</th></tr></thead><tbody>{rows.map(row => <tr key={row.id}><td><strong>{row.skpd?.code || '—'}</strong><span>{row.skpd?.name || '—'}</span></td><td>{row.period_start || '—'} s/d {row.period_end || '—'}</td><td><span className={`badge ${row.status}`}>{row.status}</span></td><td>{row.finalized_at ? new Date(row.finalized_at).toLocaleString('id-ID') : 'Belum'}</td></tr>)}</tbody></table></div>;
}

function App() {
  const [user, setUser] = useState(null);
  const [checking, setChecking] = useState(true);
  useEffect(() => { if (!localStorage.getItem(tokenKey)) return setChecking(false); api('/auth/me').then(setUser).catch(() => localStorage.removeItem(tokenKey)).finally(() => setChecking(false)); }, []);
  if (checking) return <main className="splash">Memuat aplikasi…</main>;
  return user ? <Dashboard user={user} onLogout={() => setUser(null)} /> : <Login onLogin={setUser} />;
}

createRoot(document.getElementById('app')).render(<App />);

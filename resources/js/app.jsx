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
  const empty = { name: '', email: '', password: '', role: 'skpd', skpd_id: '', is_active: true };
  const [users, setUsers] = useState([]); const [skpds, setSkpds] = useState([]); const [editing, setEditing] = useState(null); const [form, setForm] = useState(empty); const [busy, setBusy] = useState(false);
  async function load() { const [u, s] = await Promise.all([api('/users'), api('/skpds')]); setUsers(u); setSkpds(s.data || s); }
  useEffect(() => { load().catch(e => onMessage(e.message)); }, []);
  function startEdit(u) { setEditing(u.id); setForm({ name:u.name, email:u.email, password:'', role:u.role, skpd_id:u.skpd_id ?? '', is_active:!!u.is_active }); }
  function submit(e) { e.preventDefault(); setBusy(true); const body={...form,skpd_id:form.role==='skpd'?Number(form.skpd_id):null}; if(!body.password)delete body.password; (editing?api(`/users/${editing}`,{method:'PUT',body}):api('/users',{method:'POST',body})).then(()=>{onMessage(editing?'Pengguna diperbarui.':'Pengguna dibuat.');setEditing(null);setForm(empty);return load();}).catch(e=>onMessage(e.message)).finally(()=>setBusy(false)); }
  return <div className="admin-grid"><section className="panel"><div className="panel-heading"><div><h2>{editing?'Edit pengguna':'Pengguna baru'}</h2><p>Akun dan pembatasan akses berdasarkan SKPD.</p></div>{editing&&<button className="secondary" onClick={()=>{setEditing(null);setForm(empty)}}>Batal</button>}</div><form className="form-grid" onSubmit={submit}><label>Nama<input value={form.name} onChange={e=>setForm({...form,name:e.target.value})} required /></label><label>Email<input type="email" value={form.email} onChange={e=>setForm({...form,email:e.target.value})} required /></label><label>Password<input type="password" value={form.password} onChange={e=>setForm({...form,password:e.target.value})} placeholder={editing?'Kosongkan jika tidak berubah':''} required={!editing} /></label><label>Role<select value={form.role} onChange={e=>setForm({...form,role:e.target.value,skpd_id:e.target.value==='skpd'?form.skpd_id:''})}><option value="skpd">SKPD</option><option value="skpkd">SKPKD</option><option value="admin">Admin</option></select></label>{form.role==='skpd'&&<label>SKPD<select value={form.skpd_id} onChange={e=>setForm({...form,skpd_id:e.target.value})} required><option value="">Pilih SKPD</option>{skpds.filter(s=>s.is_active).map(s=><option key={s.id} value={s.id}>{s.code} — {s.name}</option>)}</select></label>}<label className="checkbox"><input type="checkbox" checked={form.is_active} onChange={e=>setForm({...form,is_active:e.target.checked})}/> Aktif</label><div className="form-actions"><button className="primary" disabled={busy}>{busy?'Menyimpan…':editing?'Simpan perubahan':'Buat pengguna'}</button></div></form></section><section className="panel"><div className="panel-heading"><div><h2>Daftar pengguna</h2><p>{users.length} akun terdaftar.</p></div></div><div className="table-wrap"><table><thead><tr><th>Pengguna</th><th>Role</th><th>SKPD</th><th>Status</th><th></th></tr></thead><tbody>{users.map(u=><tr key={u.id}><td><strong>{u.name}</strong><span>{u.email}</span></td><td><span className="badge">{u.role}</span></td><td>{u.skpd?.code||'—'}</td><td><span className={`badge ${u.is_active?'active':'inactive'}`}>{u.is_active?'Aktif':'Nonaktif'}</span></td><td><button className="link-button" onClick={()=>startEdit(u)}>Edit</button></td></tr>)}</tbody></table></div></section></div>;
}

function MasterData({ onMessage }) {
  const tabs=['Tahun Anggaran','SKPD','Pejabat']; const [tab,setTab]=useState(tabs[0]);
  return <section><div className="tabs">{tabs.map(t=><button key={t} className={tab===t?'tab-active':''} onClick={()=>setTab(t)}>{t}</button>)}</div>{tab==='Tahun Anggaran'&&<YearManagement onMessage={onMessage}/>} {tab==='SKPD'&&<SkpdManagement onMessage={onMessage}/>} {tab==='Pejabat'&&<OfficialManagement onMessage={onMessage}/>}</section>;
}

function YearManagement({ onMessage }) {
  const [years,setYears]=useState([]); const [year,setYear]=useState(new Date().getFullYear()); const [busy,setBusy]=useState(false);
  async function load(){setYears(await api('/years'));}
  useEffect(()=>{load().catch(e=>onMessage(e.message));},[]);
  async function add(e){e.preventDefault();setBusy(true);try{await api('/years',{method:'POST',body:{year:Number(year)}});onMessage('Tahun anggaran ditambahkan.');await load();}catch(e){onMessage(e.message)}finally{setBusy(false)}}
  async function activate(id){setBusy(true);try{await api(`/years/${id}/activate`,{method:'POST'});onMessage('Tahun anggaran aktif diperbarui.');await load();}catch(e){onMessage(e.message)}finally{setBusy(false)}}
  return <div className="admin-grid"><section className="panel"><div className="panel-heading"><div><h2>Tambah tahun</h2><p>Tahun pertama otomatis menjadi aktif.</p></div></div><form className="form-stack" onSubmit={add}><label>Tahun anggaran<input type="number" min="2000" max="2100" value={year} onChange={e=>setYear(e.target.value)} required /></label><button className="primary" disabled={busy}>Tambah tahun</button></form></section><section className="panel"><div className="panel-heading"><div><h2>Daftar tahun anggaran</h2><p>Tepat satu tahun aktif melalui mekanisme aktivasi.</p></div></div><div className="table-wrap"><table><thead><tr><th>Tahun</th><th>Status</th><th></th></tr></thead><tbody>{years.map(y=><tr key={y.id}><td><strong>{y.year}</strong></td><td><span className={`badge ${y.is_active?'active':''}`}>{y.is_active?'Aktif':'Tidak aktif'}</span></td><td>{!y.is_active&&<button className="link-button" disabled={busy} onClick={()=>activate(y.id)}>Aktifkan</button>}</td></tr>)}</tbody></table></div></section></div>;
}

function SkpdManagement({ onMessage }) {
  const empty={code:'',name:'',is_active:true}; const [items,setItems]=useState([]); const [editing,setEditing]=useState(null); const [form,setForm]=useState(empty); const [busy,setBusy]=useState(false);
  async function load(){const d=await api('/skpds');setItems(d.data||d)} useEffect(()=>{load().catch(e=>onMessage(e.message))},[]);
  function edit(x){setEditing(x.id);setForm({code:x.code,name:x.name,is_active:!!x.is_active})} function reset(){setEditing(null);setForm(empty)}
  async function submit(e){e.preventDefault();setBusy(true);try{await api(editing?`/skpds/${editing}`:'/skpds',{method:editing?'PUT':'POST',body:form});onMessage(editing?'SKPD diperbarui.':'SKPD dibuat.');reset();await load()}catch(e){onMessage(e.message)}finally{setBusy(false)}}
  return <div className="admin-grid"><section className="panel"><div className="panel-heading"><div><h2>{editing?'Edit SKPD':'SKPD baru'}</h2><p>Kode dan nama organisasi.</p></div>{editing&&<button className="secondary" onClick={reset}>Batal</button>}</div><form className="form-stack" onSubmit={submit}><label>Kode<input value={form.code} onChange={e=>setForm({...form,code:e.target.value})} required /></label><label>Nama<input value={form.name} onChange={e=>setForm({...form,name:e.target.value})} required /></label><label className="checkbox"><input type="checkbox" checked={form.is_active} onChange={e=>setForm({...form,is_active:e.target.checked})}/> Aktif</label><button className="primary" disabled={busy}>{busy?'Menyimpan…':editing?'Simpan perubahan':'Buat SKPD'}</button></form></section><section className="panel"><div className="panel-heading"><div><h2>Daftar SKPD</h2><p>{items.length} SKPD pada halaman ini.</p></div></div><div className="table-wrap"><table><thead><tr><th>Kode</th><th>Nama</th><th>Status</th><th></th></tr></thead><tbody>{items.map(x=><tr key={x.id}><td><strong>{x.code}</strong></td><td>{x.name}</td><td><span className={`badge ${x.is_active?'active':'inactive'}`}>{x.is_active?'Aktif':'Nonaktif'}</span></td><td><button className="link-button" onClick={()=>edit(x)}>Edit</button></td></tr>)}</tbody></table></div></section></div>;
}

function OfficialManagement({ onMessage }) {
  const empty={skpd_id:'',name:'',nip:'',position:'',is_active:true}; const [items,setItems]=useState([]); const [skpds,setSkpds]=useState([]); const [editing,setEditing]=useState(null); const [form,setForm]=useState(empty); const [busy,setBusy]=useState(false);
  async function load(){const [o,s]=await Promise.all([api('/officials'),api('/skpds')]);setItems(o.data||o);setSkpds(s.data||s)} useEffect(()=>{load().catch(e=>onMessage(e.message))},[]);
  function edit(x){setEditing(x.id);setForm({skpd_id:x.skpd_id,name:x.name,nip:x.nip||'',position:x.position,is_active:!!x.is_active})} function reset(){setEditing(null);setForm(empty)}
  async function submit(e){e.preventDefault();setBusy(true);try{await api(editing?`/officials/${editing}`:'/officials',{method:editing?'PUT':'POST',body:{...form,skpd_id:Number(form.skpd_id)}});onMessage(editing?'Pejabat diperbarui.':'Pejabat dibuat.');reset();await load()}catch(e){onMessage(e.message)}finally{setBusy(false)}}
  return <div className="admin-grid"><section className="panel"><div className="panel-heading"><div><h2>{editing?'Edit pejabat':'Pejabat baru'}</h2><p>Data pejabat disimpan sebagai master dan dapat diperbarui.</p></div>{editing&&<button className="secondary" onClick={reset}>Batal</button>}</div><form className="form-stack" onSubmit={submit}><label>SKPD<select value={form.skpd_id} onChange={e=>setForm({...form,skpd_id:e.target.value})} required><option value="">Pilih SKPD</option>{skpds.filter(s=>s.is_active).map(s=><option key={s.id} value={s.id}>{s.code} — {s.name}</option>)}</select></label><label>Nama<input value={form.name} onChange={e=>setForm({...form,name:e.target.value})} required /></label><label>NIP<input value={form.nip} onChange={e=>setForm({...form,nip:e.target.value})} /></label><label>Jabatan<input value={form.position} onChange={e=>setForm({...form,position:e.target.value})} required /></label><label className="checkbox"><input type="checkbox" checked={form.is_active} onChange={e=>setForm({...form,is_active:e.target.checked})}/> Aktif</label><button className="primary" disabled={busy}>{busy?'Menyimpan…':editing?'Simpan perubahan':'Buat pejabat'}</button></form></section><section className="panel"><div className="panel-heading"><div><h2>Daftar pejabat</h2><p>{items.length} data pada halaman ini.</p></div></div><div className="table-wrap"><table><thead><tr><th>Pejabat</th><th>SKPD</th><th>Jabatan</th><th>Status</th><th></th></tr></thead><tbody>{items.map(x=><tr key={x.id}><td><strong>{x.name}</strong><span>{x.nip||'NIP tidak diisi'}</span></td><td>{x.skpd?.code||'—'}</td><td>{x.position}</td><td><span className={`badge ${x.is_active?'active':'inactive'}`}>{x.is_active?'Aktif':'Nonaktif'}</span></td><td><button className="link-button" onClick={()=>edit(x)}>Edit</button></td></tr>)}</tbody></table></div></section></div>;
}

function Dashboard({ user, onLogout }) {
  const [years,setYears]=useState([]); const [reconciliations,setReconciliations]=useState({data:[]}); const [health,setHealth]=useState('checking'); const [view,setView]=useState('dashboard'); const [message,setMessage]=useState(''); const admin=user.role==='admin'||user.role==='skpkd';
  async function load(){try{const [y,r]=await Promise.all([api('/years'),api('/reconciliations')]);setYears(y);setReconciliations(r);setHealth('ok')}catch(e){setHealth('error');if(e.message.includes('Unauthenticated')||e.message.includes('tidak aktif'))onLogout();else setMessage(e.message)}} useEffect(()=>{load()},[]);
  const activeYear=useMemo(()=>years.find(y=>y.is_active)?.year,[years]); const rows=reconciliations.data||[]; const finalized=rows.filter(r=>r.status==='finalized').length; const drafts=rows.filter(r=>r.status!=='finalized').length;
  async function logout(){try{await api('/auth/logout',{method:'POST'})}catch(_){}localStorage.removeItem(tokenKey);onLogout()}
  const title={dashboard:'Dashboard',reconciliations:'Rekonsiliasi',sources:'Sumber Pengesahan',users:'Pengguna',settings:'Administrasi'}[view];
  return <div className="app-shell"><aside className="sidebar"><div className="side-brand"><div className="brand-mark small">RA</div><div><strong>Rekon Akuntansi</strong><span>Pemerintah Daerah</span></div></div><nav><button className={view==='dashboard'?'nav-active':''} onClick={()=>setView('dashboard')}>Dashboard</button><button className={view==='reconciliations'?'nav-active':''} onClick={()=>setView('reconciliations')}>Rekonsiliasi</button><button className={view==='sources'?'nav-active':''} onClick={()=>setView('sources')}>Sumber Pengesahan</button>{admin&&<><button className={view==='users'?'nav-active':''} onClick={()=>setView('users')}>Pengguna</button><button className={view==='settings'?'nav-active':''} onClick={()=>setView('settings')}>Administrasi</button></>}</nav><div className="side-user"><strong>{user.name}</strong><span>{user.role.toUpperCase()}</span><button onClick={logout}>Keluar</button></div></aside><main className="content"><header className="topbar"><div><span className="eyebrow">REKON AKUNTANSI</span><h1>{title}</h1></div><span className={`status ${health}`}>● {health==='ok'?'Terhubung':health==='checking'?'Memeriksa…':'Gangguan'}</span></header>{message&&<div className="alert error page-alert">{message}</div>}
  {view==='dashboard'&&<><section className="hero"><div><span className="eyebrow">PERIODE AKTIF</span><strong>{activeYear??'—'}</strong><p>Tahun anggaran yang sedang digunakan.</p></div><div className="hero-flow"><span>Sumber</span><i>→</i><span>Rekonsiliasi</span><i>→</i><span>BA & Snapshot</span></div></section><section className="stats"><article><small>TAHUN AKTIF</small><strong>{activeYear??'—'}</strong><span>Periode akuntansi</span></article><article><small>REKONSILIASI</small><strong>{rows.length}</strong><span>Total pada halaman aktif</span></article><article><small>SELESAI</small><strong>{finalized}</strong><span>Telah difinalisasi</span></article><article><small>DRAFT</small><strong>{drafts}</strong><span>Masih dapat diperiksa</span></article></section><section className="panel"><div className="panel-heading"><div><h2>Rekonsiliasi terbaru</h2><p>Snapshot dibuat ketika Berita Acara difinalisasi.</p></div><button className="secondary" onClick={()=>setView('reconciliations')}>Lihat semua</button></div><ReconTable rows={rows.slice(0,8)}/></section></>}
  {view==='reconciliations'&&<section className="panel"><div className="panel-heading"><div><h2>Daftar rekonsiliasi</h2><p>Data mengikuti kewenangan pengguna berdasarkan SKPD.</p></div>{admin&&<button className="primary" onClick={()=>setMessage('Form pembuatan rekonsiliasi akan dihubungkan setelah master dan sumber data siap.')}>+ Rekonsiliasi baru</button>}</div><ReconTable rows={rows}/></section>}
  {view==='sources'&&<section className="panel empty"><h2>Sumber Pengesahan</h2><p>Endpoint sumber pengesahan sudah tersedia. Tampilan input dan pencocokan akan dibangun pada tahap workflow sumber data.</p></section>}
  {view==='users'&&admin&&<UserManagement onMessage={setMessage}/>} {view==='settings'&&admin&&<MasterData onMessage={setMessage}/>} 
  </main></div>;
}

function ReconTable({rows}){if(!rows.length)return <div className="empty-table">Belum ada data rekonsiliasi.</div>;return <div className="table-wrap"><table><thead><tr><th>SKPD</th><th>Periode</th><th>Status</th><th>Finalisasi</th></tr></thead><tbody>{rows.map(row=><tr key={row.id}><td><strong>{row.skpd?.code||'—'}</strong><span>{row.skpd?.name||'—'}</span></td><td>{row.period_start||'—'} s/d {row.period_end||'—'}</td><td><span className={`badge ${row.status}`}>{row.status}</span></td><td>{row.finalized_at?new Date(row.finalized_at).toLocaleString('id-ID'):'Belum'}</td></tr>)}</tbody></table></div>}

function App(){const[user,setUser]=useState(null);const[checking,setChecking]=useState(true);useEffect(()=>{if(!localStorage.getItem(tokenKey))return setChecking(false);api('/auth/me').then(setUser).catch(()=>localStorage.removeItem(tokenKey)).finally(()=>setChecking(false))},[]);if(checking)return <main className="splash">Memuat aplikasi…</main>;return user?<Dashboard user={user} onLogout={()=>setUser(null)}/>:<Login onLogin={setUser}/>}
createRoot(document.getElementById('app')).render(<App/>);

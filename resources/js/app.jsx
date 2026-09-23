import React,{useEffect,useMemo,useState}from'react';
import{createRoot}from'react-dom/client';
import'./app.css';
import{AuthorizationSources,ReconciliationWorkflow}from'./ReconciliationWorkflow';
import{AdminManagement}from'./AdminManagement';

const tokenKey='rekon_token';

async function api(path,options={}){
  const token=localStorage.getItem(tokenKey),headers={Accept:'application/json',...(options.headers||{})};
  const config={...options,headers};
  if(config.body&&typeof config.body!=='string'){headers['Content-Type']='application/json';config.body=JSON.stringify(config.body)}
  if(token)headers.Authorization=`Bearer ${token}`;
  const r=await fetch(`/api${path}`,config),d=await r.json().catch(()=>({}));
  if(!r.ok)throw Error(d.message||'Permintaan gagal.');
  return d;
}

const roleLabel={admin:'Administrator',skpkd:'SKPKD',skpd:'Pengelola SKPD'};

const statusLabel={
  draft:'Draft',
  processing:'Diproses',
  finalized:'Final',
  final:'Final',
  active:'Aktif',
  inactive:'Tidak aktif',
  matched:'Cocok',
  unmatched:'Belum cocok',
  partial:'Sebagian',
  exception:'Perlu review'
};

function StatusBadge({status}){
  return <span className={`badge ${status||''}`}>{statusLabel[status]||status||'—'}</span>;
}

function Login({onLogin}){
  const[email,setEmail]=useState('admin@example.test'),[password,setPassword]=useState('password'),[error,setError]=useState(''),[busy,setBusy]=useState(false);
  async function submit(e){
    e.preventDefault();setError('');setBusy(true);
    try{
      const d=await api('/auth/login',{method:'POST',body:{email,password}});
      localStorage.setItem(tokenKey,d.token);onLogin(d.user);
    }catch(e){setError(e.message)}finally{setBusy(false)}
  }
  return <main className="login-shell">
    <div className="login-card">
      <div className="brand-mark">RA</div>
      <span className="eyebrow">REKON AKUNTANSI</span>
      <h1>Masuk ke aplikasi</h1>
      <p>Kelola sumber pengesahan, proses rekonsiliasi, dan Berita Acara dalam satu alur kerja.</p>
      <form onSubmit={submit} className="form-stack">
        <label>Email<input type="email" value={email} onChange={e=>setEmail(e.target.value)} required/></label>
        <label>Password<input type="password" value={password} onChange={e=>setPassword(e.target.value)} required/></label>
        {error&&<div className="alert error">{error}</div>}
        <button className="primary" disabled={busy}>{busy?'Memproses…':'Masuk'}</button>
      </form>
    </div>
  </main>;
}

function ReconTable({rows}){
  if(!rows.length)return <div className="empty-table">Belum ada data rekonsiliasi.</div>;
  return <div className="table-wrap"><table>
    <thead><tr><th>SKPD</th><th>Periode</th><th>Status</th><th>Finalisasi</th></tr></thead>
    <tbody>{rows.map(x=><tr key={x.id}>
      <td><strong>{x.skpd?.code||'—'}</strong><span>{x.skpd?.name||'—'}</span></td>
      <td>{x.period_start||'—'} s/d {x.period_end||'—'}</td>
      <td><StatusBadge status={x.status}/></td>
      <td>{x.finalized_at?new Date(x.finalized_at).toLocaleString('id-ID'):'Belum'}</td>
    </tr>)}</tbody>
  </table></div>;
}

function Dashboard({user,onLogout}){
  const[years,setYears]=useState([]),[recons,setRecons]=useState({data:[]}),[health,setHealth]=useState('checking'),[view,setView]=useState('dashboard'),[message,setMessage]=useState('');
  const admin=user.role==='admin',processor=admin||user.role==='skpkd';

  async function load(){
    try{
      const[y,r]=await Promise.all([api('/years'),api('/reconciliations')]);
      setYears(y);setRecons(r);setHealth('ok');
    }catch(e){
      setHealth('error');
      if(/Unauthenticated|tidak aktif/i.test(e.message))onLogout();else setMessage(e.message);
    }
  }

  useEffect(()=>{load()},[]);

  const activeYear=useMemo(()=>years.find(x=>x.is_active)?.year,[years]);
  const rows=recons.data||[];
  const finalized=rows.filter(x=>x.status==='finalized').length;
  const drafts=rows.length-finalized;
  const title={dashboard:'Dashboard',reconciliations:'Rekonsiliasi',sources:'Sumber Pengesahan',admin:'Administrasi'}[view];

  async function logout(){
    try{await api('/auth/logout',{method:'POST'})}catch{}
    localStorage.removeItem(tokenKey);onLogout();
  }

  return <div className="app-shell">
    <aside className="sidebar">
      <div className="side-brand">
        <div className="brand-mark small">RA</div>
        <div><strong>Rekon Akuntansi</strong><span>Pemerintah Daerah</span></div>
      </div>
      <nav aria-label="Navigasi utama">
        <button className={view==='dashboard'?'nav-active':''} onClick={()=>setView('dashboard')}><span className="nav-icon">⌂</span>Dashboard</button>
        {processor&&<button className={view==='reconciliations'?'nav-active':''} onClick={()=>setView('reconciliations')}><span className="nav-icon">⇄</span>Rekonsiliasi</button>}
        {admin&&<button className={view==='sources'?'nav-active':''} onClick={()=>setView('sources')}><span className="nav-icon">▤</span>Sumber Pengesahan</button>}
        {admin&&<button className={view==='admin'?'nav-active':''} onClick={()=>setView('admin')}><span className="nav-icon">⚙</span>Administrasi</button>}
      </nav>
      <div className="side-user">
        <strong>{user.name}</strong>
        <span>{roleLabel[user.role]||user.role?.toUpperCase()}</span>
        <button onClick={logout}>Keluar</button>
      </div>
    </aside>

    <main className="content">
      <header className="topbar">
        <div><span className="eyebrow">REKON AKUNTANSI</span><h1>{title}</h1></div>
        <span className={`status ${health}`}><i/> {health==='ok'?'Terhubung':health==='checking'?'Memeriksa…':'Gangguan'}</span>
      </header>

      {message&&<div className="alert error page-alert">{message}</div>}

      {view==='dashboard'&&<>
        <section className="hero">
          <div>
            <span className="eyebrow">PERIODE AKTIF</span>
            <strong>{activeYear??'—'}</strong>
            <p>Tahun anggaran yang sedang digunakan.</p>
          </div>
          <div className="hero-flow">
            <span>Sumber</span><i>→</i><span>Rekonsiliasi</span><i>→</i><span>BA &amp; Snapshot</span>
          </div>
        </section>

        <section className="stats">
          <article><small>TAHUN AKTIF</small><strong>{activeYear??'—'}</strong><span>Periode akuntansi</span></article>
          <article><small>REKONSILIASI</small><strong>{rows.length}</strong><span>Total pada halaman</span></article>
          <article><small>SELESAI</small><strong>{finalized}</strong><span>Telah difinalisasi</span></article>
          <article><small>DRAFT</small><strong>{drafts}</strong><span>Masih dapat diperiksa</span></article>
        </section>

        {admin&&<section className="quick-actions">
          <div><small>AKSI CEPAT</small><strong>Mulai pekerjaan</strong><span>Pilih tahap yang ingin dikerjakan.</span></div>
          <button className="secondary" onClick={()=>setView('sources')}><b>＋</b><span>Input sumber</span><small>Sumber pengesahan</small></button>
          <button className="secondary" onClick={()=>setView('reconciliations')}><b>⇄</b><span>Rekonsiliasi baru</span><small>Pencocokan data</small></button>
          <button className="secondary" onClick={()=>setView('admin')}><b>⚙</b><span>Kelola master</span><small>Administrasi</small></button>
        </section>}

        <section className="panel">
          <div className="panel-heading">
            <div><h2>Rekonsiliasi terbaru</h2><p>Snapshot dibuat ketika Berita Acara difinalisasi.</p></div>
            <button className="secondary" onClick={()=>setView('reconciliations')}>Lihat semua</button>
          </div>
          <ReconTable rows={rows.slice(0,8)}/>
        </section>
      </>}

      {view==='sources'&&admin&&<AuthorizationSources user={user} onMessage={setMessage}/>}
      {view==='reconciliations'&&processor&&<ReconciliationWorkflow user={user} onMessage={setMessage} onChanged={load}/>}
      {view==='admin'&&admin&&<AdminManagement user={user} onMessage={setMessage}/>}
    </main>
  </div>;
}

function App(){
  const[user,setUser]=useState(null),[checking,setChecking]=useState(true);
  useEffect(()=>{
    if(!localStorage.getItem(tokenKey))return setChecking(false);
    api('/auth/me').then(setUser).catch(()=>localStorage.removeItem(tokenKey)).finally(()=>setChecking(false));
  },[]);
  if(checking)return <main className="splash">Memuat aplikasi…</main>;
  return user?<Dashboard user={user} onLogout={()=>setUser(null)}/>:<Login onLogin={setUser}/>;
}

createRoot(document.getElementById('app')).render(<App/>);

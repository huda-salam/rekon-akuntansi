import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import './style.css';

function App() {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch('/api/reconciliations')
      .then((r) => r.ok ? r.json() : Promise.reject(r))
      .then((data) => setItems(data.data ?? []))
      .catch(() => setItems([]))
      .finally(() => setLoading(false));
  }, []);

  return <div className="app">
    <header>
      <div><strong>Rekon Akuntansi</strong><span>Rekonsiliasi akuntansi pemerintah daerah</span></div>
      <div className="year">Tahun Aktif</div>
    </header>
    <main>
      <section className="hero">
        <div><p className="eyebrow">DASHBOARD</p><h1>Rekonsiliasi</h1><p>Kelola sumber pengesahan, proses pencocokan, dan terbitkan BA dengan snapshot yang immutable.</p></div>
        <button>+ Rekonsiliasi Baru</button>
      </section>
      <section className="cards">
        <article><small>Draft</small><b>{loading ? '—' : items.filter(x => x.status === 'draft').length}</b></article>
        <article><small>Diproses</small><b>{loading ? '—' : items.filter(x => x.status === 'processing').length}</b></article>
        <article><small>Final</small><b>{loading ? '—' : items.filter(x => x.status === 'final').length}</b></article>
      </section>
      <section className="panel"><div className="panel-head"><h2>Rekonsiliasi Terbaru</h2><span>{items.length} data</span></div>
        {items.length === 0 ? <div className="empty">Belum ada data rekonsiliasi.</div> : <table><thead><tr><th>SKPD</th><th>Tahun</th><th>Status</th><th>Periode</th></tr></thead><tbody>{items.map(x => <tr key={x.id}><td>{x.skpd?.name ?? '-'}</td><td>{x.accounting_year?.year ?? '-'}</td><td><span className={`badge ${x.status}`}>{x.status}</span></td><td>{x.period ?? '-'}</td></tr>)}</tbody></table>}
      </section>
    </main>
  </div>;
}

createRoot(document.getElementById('root')).render(<App />);

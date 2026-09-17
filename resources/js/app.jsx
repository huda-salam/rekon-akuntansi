import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import './app.css';

function App() {
  const [health, setHealth] = useState('checking');
  const [years, setYears] = useState([]);

  useEffect(() => {
    fetch('/api/health').then(r => r.json()).then(() => setHealth('ok')).catch(() => setHealth('error'));
    fetch('/api/years', { headers: { Accept: 'application/json' } })
      .then(r => r.ok ? r.json() : null)
      .then(data => setYears(Array.isArray(data) ? data : []))
      .catch(() => {});
  }, []);

  return <main className="shell">
    <header><div><span className="eyebrow">REKON AKUNTANSI</span><h1>Rekonsiliasi Akuntansi</h1><p>Pengelolaan rekonsiliasi dan Berita Acara secara sederhana dan terkontrol.</p></div><span className={`status ${health}`}>● {health}</span></header>
    <section className="cards">
      <article><small>TAHUN AKTIF</small><strong>{years.find(y => y.active)?.year ?? '—'}</strong><span>Periode akuntansi</span></article>
      <article><small>REKONSILIASI</small><strong>—</strong><span>Menunggu data</span></article>
      <article><small>BERITA ACARA</small><strong>—</strong><span>Snapshot immutable</span></article>
    </section>
    <section className="panel"><h2>Alur kerja</h2><div className="flow"><b>1. Sumber Pengesahan</b><i>→</i><b>2. Rekonsiliasi</b><i>→</i><b>3. BA & Snapshot</b></div></section>
  </main>;
}

createRoot(document.getElementById('app')).render(<App />);

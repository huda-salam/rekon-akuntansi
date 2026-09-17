import React, { useEffect, useMemo, useState } from 'react';

const tokenKey = 'rekon_token';

async function api(path, options = {}) {
  const token = localStorage.getItem(tokenKey);
  const headers = { Accept: 'application/json', ...(options.headers || {}) };
  const config = { ...options, headers };
  if (config.body && typeof config.body !== 'string') {
    headers['Content-Type'] = 'application/json';
    config.body = JSON.stringify(config.body);
  }
  if (token) headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`/api${path}`, config);
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.message || 'Permintaan gagal.');
  return data;
}

const emptySourceDetail = () => ({
  account_code: '', account_name: '', description: '', quantity: '', unit: '', amount: '', source_reference: '',
});

function SourceDetailForm({ detail, index, onChange, onRemove, canRemove }) {
  const set = (key, value) => onChange(index, { ...detail, [key]: value });
  return <div className="detail-row">
    <div className="detail-row-heading"><strong>Detail {index + 1}</strong>{canRemove && <button type="button" className="link-button" onClick={() => onRemove(index)}>Hapus</button>}</div>
    <div className="form-grid">
      <label>Kode rekening<input value={detail.account_code} onChange={e => set('account_code', e.target.value)} /></label>
      <label>Nama rekening<input value={detail.account_name} onChange={e => set('account_name', e.target.value)} /></label>
      <label>Uraian<input value={detail.description} onChange={e => set('description', e.target.value)} /></label>
      <label>Jumlah<input type="number" min="0" step="any" value={detail.quantity} onChange={e => set('quantity', e.target.value)} /></label>
      <label>Satuan<input value={detail.unit} onChange={e => set('unit', e.target.value)} /></label>
      <label>Nilai<input type="number" min="0" step="0.01" value={detail.amount} onChange={e => set('amount', e.target.value)} required /></label>
      <label>Referensi sumber<input value={detail.source_reference} onChange={e => set('source_reference', e.target.value)} /></label>
    </div>
  </div>;
}

export function AuthorizationSources({ user, onMessage }) {
  const admin = user.role === 'admin' || user.role === 'skpkd';
  const [years, setYears] = useState([]);
  const [skpds, setSkpds] = useState([]);
  const [items, setItems] = useState({ data: [] });
  const [busy, setBusy] = useState(false);
  const [details, setDetails] = useState([emptySourceDetail()]);
  const [form, setForm] = useState({ accounting_year_id: '', skpd_id: user.skpd_id ?? '', authorization_number: '', authorization_date: '', type: 'pendapatan', description: '' });

  async function load() {
    const [y, a] = await Promise.all([api('/years'), api('/authorizations')]);
    setYears(y); setItems(a);
    if (!form.accounting_year_id) setForm(current => ({ ...current, accounting_year_id: y.find(x => x.is_active)?.id ?? '' }));
    if (admin) { const s = await api('/skpds'); setSkpds(s.data || s); }
  }
  useEffect(() => { load().catch(e => onMessage(e.message)); }, []);

  const total = useMemo(() => details.reduce((sum, d) => sum + (Number(d.amount) || 0), 0), [details]);
  function changeDetail(index, value) { setDetails(current => current.map((d, i) => i === index ? value : d)); }
  function reset() { setForm({ accounting_year_id: years.find(x => x.is_active)?.id ?? '', skpd_id: user.skpd_id ?? '', authorization_number: '', authorization_date: '', type: 'pendapatan', description: '' }); setDetails([emptySourceDetail()]); }
  async function submit(e) {
    e.preventDefault(); setBusy(true);
    try {
      await api('/authorizations', { method: 'POST', body: { ...form, accounting_year_id: Number(form.accounting_year_id), skpd_id: Number(form.skpd_id), authorization_date: form.authorization_date || null, details: details.map(d => ({ ...d, quantity: d.quantity === '' ? null : Number(d.quantity), amount: Number(d.amount), })) } });
      onMessage('Sumber pengesahan berhasil disimpan.'); reset(); await load();
    } catch (e) { onMessage(e.message); } finally { setBusy(false); }
  }

  return <div className="admin-grid">
    <section className="panel">
      <div className="panel-heading"><div><h2>Sumber pengesahan baru</h2><p>Input sumber yang akan menjadi dasar pencocokan rekonsiliasi.</p></div></div>
      <form className="form-stack" onSubmit={submit}>
        <div className="form-grid">
          <label>Tahun anggaran<select value={form.accounting_year_id} onChange={e => setForm({ ...form, accounting_year_id: e.target.value })} required><option value="">Pilih tahun</option>{years.map(y => <option key={y.id} value={y.id}>{y.year}{y.is_active ? ' — aktif' : ''}</option>)}</select></label>
          <label>Jenis<select value={form.type} onChange={e => setForm({ ...form, type: e.target.value })}><option value="pendapatan">Pendapatan</option><option value="belanja">Belanja</option></select></label>
          {admin ? <label>SKPD<select value={form.skpd_id} onChange={e => setForm({ ...form, skpd_id: e.target.value })} required><option value="">Pilih SKPD</option>{skpds.filter(s => s.is_active).map(s => <option key={s.id} value={s.id}>{s.code} — {s.name}</option>)}</select></label> : <label>SKPD<input value={user.skpd?.code ? `${user.skpd.code} — ${user.skpd.name}` : 'SKPD pengguna'} disabled /></label>}
          <label>Nomor pengesahan<input value={form.authorization_number} onChange={e => setForm({ ...form, authorization_number: e.target.value })} /></label>
          <label>Tanggal pengesahan<input type="date" value={form.authorization_date} onChange={e => setForm({ ...form, authorization_date: e.target.value })} /></label>
          <label>Uraian<input value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} /></label>
        </div>
        {details.map((detail, index) => <SourceDetailForm key={index} detail={detail} index={index} onChange={changeDetail} onRemove={i => setDetails(current => current.filter((_, x) => x !== i))} canRemove={details.length > 1} />)}
        <div className="form-actions"><button type="button" className="secondary" onClick={() => setDetails([...details, emptySourceDetail()])}>+ Tambah detail</button><strong>Total: Rp {total.toLocaleString('id-ID', { minimumFractionDigits: 2 })}</strong><button className="primary" disabled={busy}>{busy ? 'Menyimpan…' : 'Simpan sumber'}</button></div>
      </form>
    </section>
    <section className="panel"><div className="panel-heading"><div><h2>Daftar sumber</h2><p>{items.total ?? items.data?.length ?? 0} sumber pengesahan.</p></div><button className="secondary" onClick={() => load().catch(e => onMessage(e.message))}>Muat ulang</button></div><div className="table-wrap"><table><thead><tr><th>Nomor</th><th>SKPD</th><th>Jenis</th><th>Tahun</th><th>Total</th></tr></thead><tbody>{(items.data || []).map(x => <tr key={x.id}><td><strong>{x.authorization_number || 'Tanpa nomor'}</strong><span>{x.authorization_date || 'Tanggal tidak diisi'}</span></td><td>{x.skpd?.code || '—'}</td><td><span className="badge">{x.type}</span></td><td>{x.accounting_year?.year || '—'}</td><td>Rp {Number(x.total_amount || 0).toLocaleString('id-ID', { minimumFractionDigits: 2 })}</td></tr>)}</tbody></table></div></section>
  </div>;
}

function MatchRow({ detail, onChange }) {
  const status = detail.match_status || 'unmatched';
  return <div className="detail-row"><div className="detail-row-heading"><div><strong>{detail.source_type} #{detail.source_id ?? '—'}</strong><span>Nilai sumber: Rp {Number(detail.source_amount || 0).toLocaleString('id-ID', { minimumFractionDigits: 2 })}</span></div><select value={status} onChange={e => onChange({ ...detail, match_status: e.target.value })}><option value="unmatched">Belum cocok</option><option value="matched">Cocok</option><option value="partial">Sebagian</option><option value="exception">Perlu review</option></select></div><div className="form-grid"><label>Nilai cocok<input type="number" min="0" step="0.01" value={detail.matched_amount ?? 0} onChange={e => { const matched = Number(e.target.value) || 0; onChange({ ...detail, matched_amount: matched, difference_amount: (Number(detail.source_amount) || 0) - matched }); }} /></label><label>Selisih<input value={Number(detail.difference_amount || 0).toLocaleString('id-ID', { minimumFractionDigits: 2 })} disabled /></label><label>Catatan<input value={detail.notes || ''} onChange={e => onChange({ ...detail, notes: e.target.value })} /></label></div></div>;
}

export function ReconciliationWorkflow({ user, onMessage, onChanged }) {
  const admin = user.role === 'admin' || user.role === 'skpkd';
  const [years, setYears] = useState([]); const [skpds, setSkpds] = useState([]); const [officials, setOfficials] = useState([]); const [sources, setSources] = useState({ data: [] }); const [items, setItems] = useState({ data: [] });
  const [selected, setSelected] = useState(null); const [busy, setBusy] = useState(false);
  const [createForm, setCreateForm] = useState({ accounting_year_id: '', skpd_id: '', period_start: '', period_end: '', notes: '' });
  const [finalizeForm, setFinalizeForm] = useState({ number: '', date: '', signatory_official_name: '', signatory_official_nip: '', signatory_official_position: '', document_path: '' });

  async function load() {
    const [y, r] = await Promise.all([api('/years'), api('/reconciliations')]); setYears(y); setItems(r);
    if (admin) { const [s, o] = await Promise.all([api('/skpds'), api('/officials')]); setSkpds(s.data || s); setOfficials(o.data || o); }
    const a = await api('/authorizations'); setSources(a);
    setCreateForm(current => ({ ...current, accounting_year_id: current.accounting_year_id || y.find(x => x.is_active)?.id || '', skpd_id: current.skpd_id || user.skpd_id || (s?.data || s)?.[0]?.id || '' }));
  }
  useEffect(() => { load().catch(e => onMessage(e.message)); }, []);

  async function open(id) { try { setSelected(await api(`/reconciliations/${id}`)); } catch (e) { onMessage(e.message); } }
  function selectedSourceIds() { return new Set((selected?.details || []).filter(d => d.source_type === 'authorization').map(d => Number(d.source_id))); }
  function toggleSource(source) { const current = selectedSourceIds(); if (current.has(source.id)) current.delete(source.id); else current.add(source.id); setSelected({ ...selected, details: [...current].map(id => { const old = selected.details?.find(d => Number(d.source_id) === id); return old || { source_type: 'authorization', source_id: id, match_status: 'unmatched', source_amount: Number(source.total_amount || 0), matched_amount: 0, difference_amount: Number(source.total_amount || 0), notes: null }; }) }); }

  async function create(e) {
    e.preventDefault(); setBusy(true);
    try { const chosen = [...selectedCreateSources]; const record = await api('/reconciliations', { method: 'POST', body: { ...createForm, accounting_year_id: Number(createForm.accounting_year_id), skpd_id: Number(createForm.skpd_id), details: chosen.map(s => ({ source_type: 'authorization', source_id: s.id, match_status: 'unmatched', source_amount: Number(s.total_amount || 0), matched_amount: 0, difference_amount: Number(s.total_amount || 0) })) } }); onMessage('Rekonsiliasi dibuat.'); setCreateSelection(new Set()); await load(); setSelected(record); onChanged?.(); } catch (e) { onMessage(e.message); } finally { setBusy(false); }
  }
  const [createSelection, setCreateSelection] = useState(new Set());
  const setCreateSelection = undefined;
  const selectedCreateSources = useMemo(() => (sources.data || []).filter(s => createSelection.has(s.id) && Number(s.skpd_id) === Number(createForm.skpd_id)), [sources, createSelection, createForm.skpd_id]);
  function toggleCreate(id) { setCreateSelection(current => { const next = new Set(current); next.has(id) ? next.delete(id) : next.add(id); return next; }); }

  async function saveSelected() {
    if (!selected || selected.status === 'finalized') return; setBusy(true);
    try { const details = (selected.details || []).map(d => ({ source_type: d.source_type, source_id: d.source_id, match_status: d.match_status, source_amount: Number(d.source_amount || 0), matched_amount: Number(d.matched_amount || 0), difference_amount: Number(d.difference_amount || 0), notes: d.notes || null, match_payload: d.match_payload || null })); const saved = await api(`/reconciliations/${selected.id}`, { method: 'PUT', body: { period_start: selected.period_start, period_end: selected.period_end, notes: selected.notes, details } }); setSelected(saved); onMessage('Pencocokan rekonsiliasi disimpan.'); await load(); onChanged?.(); } catch (e) { onMessage(e.message); } finally { setBusy(false); }
  }
  async function finalize() {
    if (!selected) return; setBusy(true);
    try { const ba = await api(`/reconciliations/${selected.id}/finalize`, { method: 'POST', body: finalizeForm }); setSelected({ ...selected, status: 'finalized', finalized_at: ba.created_at || selected.finalized_at }); onMessage('Rekonsiliasi difinalisasi dan snapshot dibuat.'); await load(); onChanged?.(); } catch (e) { onMessage(e.message); } finally { setBusy(false); }
  }

  const availableSources = (sources.data || []).filter(s => Number(s.skpd_id) === Number(createForm.skpd_id) && Number(s.accounting_year_id) === Number(createForm.accounting_year_id));
  const sourceSelectedCount = selectedCreateSources.length;

  return <div className="workflow-stack">
    {admin && !selected && <section className="panel"><div className="panel-heading"><div><h2>Rekonsiliasi baru</h2><p>Pilih periode dan sumber pengesahan yang akan direkonsiliasi.</p></div></div><form className="form-stack" onSubmit={create}><div className="form-grid"><label>Tahun anggaran<select value={createForm.accounting_year_id} onChange={e => { setCreateForm({ ...createForm, accounting_year_id: e.target.value }); setCreateSelection(new Set()); }} required><option value="">Pilih tahun</option>{years.map(y => <option key={y.id} value={y.id}>{y.year}{y.is_active ? ' — aktif' : ''}</option>)}</select></label><label>SKPD<select value={createForm.skpd_id} onChange={e => { setCreateForm({ ...createForm, skpd_id: e.target.value }); setCreateSelection(new Set()); }} required><option value="">Pilih SKPD</option>{skpds.filter(s => s.is_active).map(s => <option key={s.id} value={s.id}>{s.code} — {s.name}</option>)}</select></label><label>Mulai periode<input type="date" value={createForm.period_start} onChange={e => setCreateForm({ ...createForm, period_start: e.target.value })} /></label><label>Akhir periode<input type="date" value={createForm.period_end} onChange={e => setCreateForm({ ...createForm, period_end: e.target.value })} /></label><label>Catatan<input value={createForm.notes} onChange={e => setCreateForm({ ...createForm, notes: e.target.value })} /></label></div><div className="panel-heading compact"><div><h3>Sumber tersedia</h3><p>{availableSources.length} sumber untuk kombinasi tahun dan SKPD.</p></div><strong>{sourceSelectedCount} dipilih</strong></div>{availableSources.length ? <div className="source-picker">{availableSources.map(s => <label className="source-option" key={s.id}><input type="checkbox" checked={createSelection.has(s.id)} onChange={() => toggleCreate(s.id)} /><span><strong>{s.authorization_number || `Sumber #${s.id}`}</strong><small>{s.type} · Rp {Number(s.total_amount || 0).toLocaleString('id-ID', { minimumFractionDigits: 2 })}</small></span></label>)}</div> : <div className="empty-table">Belum ada sumber pengesahan untuk filter ini.</div>}<button className="primary" disabled={busy || sourceSelectedCount === 0}>{busy ? 'Menyimpan…' : 'Buat rekonsiliasi'}</button></form></section>}
    <section className="panel"><div className="panel-heading"><div><h2>Daftar rekonsiliasi</h2><p>{items.total ?? items.data?.length ?? 0} data.</p></div><button className="secondary" onClick={() => { setSelected(null); load().catch(e => onMessage(e.message)); }}>Muat ulang</button></div><div className="table-wrap"><table><thead><tr><th>SKPD</th><th>Periode</th><th>Status</th><th></th></tr></thead><tbody>{(items.data || []).map(row => <tr key={row.id}><td><strong>{row.skpd?.code || '—'}</strong><span>{row.skpd?.name || '—'}</span></td><td>{row.period_start || '—'} s/d {row.period_end || '—'}</td><td><span className={`badge ${row.status}`}>{row.status}</span></td><td><button className="link-button" onClick={() => open(row.id)}>Buka</button></td></tr>)}</tbody></table></div></section>
    {selected && <section className="panel"><div className="panel-heading"><div><h2>Rekonsiliasi #{selected.id}</h2><p>{selected.skpd?.code || '—'} · {selected.period_start || '—'} s/d {selected.period_end || '—'}</p></div><button className="secondary" onClick={() => setSelected(null)}>Kembali</button></div><div className="detail-list">{(selected.details || []).map((d, index) => <MatchRow key={`${d.id || d.source_id}-${index}`} detail={d} onChange={value => setSelected({ ...selected, details: selected.details.map((x, i) => i === index ? value : x) })} />)}</div>{selected.status !== 'finalized' && <><div className="form-actions"><button className="primary" disabled={busy} onClick={saveSelected}>Simpan pencocokan</button></div><div className="finalize-box"><div className="panel-heading"><div><h3>Finalisasi & Berita Acara</h3><p>Setelah finalisasi, rekonsiliasi dan snapshot tidak dapat diubah melalui workflow ini.</p></div></div><div className="form-grid"><label>Nomor BA<input value={finalizeForm.number} onChange={e => setFinalizeForm({ ...finalizeForm, number: e.target.value })} required /></label><label>Tanggal BA<input type="date" value={finalizeForm.date} onChange={e => setFinalizeForm({ ...finalizeForm, date: e.target.value })} required /></label><label>Pejabat penandatangan{officials.length ? <select value={finalizeForm.signatory_official_name} onChange={e => { const o = officials.find(x => x.name === e.target.value); setFinalizeForm({ ...finalizeForm, signatory_official_name: o?.name || '', signatory_official_nip: o?.nip || '', signatory_official_position: o?.position || '' }); }} required><option value="">Pilih pejabat</option>{officials.filter(o => o.is_active && Number(o.skpd_id) === Number(selected.skpd_id)).map(o => <option key={o.id} value={o.name}>{o.name} — {o.position}</option>)}</select> : <input value={finalizeForm.signatory_official_name} onChange={e => setFinalizeForm({ ...finalizeForm, signatory_official_name: e.target.value })} required />}</label><label>NIP<input value={finalizeForm.signatory_official_nip} onChange={e => setFinalizeForm({ ...finalizeForm, signatory_official_nip: e.target.value })} /></label><label>Jabatan<input value={finalizeForm.signatory_official_position} onChange={e => setFinalizeForm({ ...finalizeForm, signatory_official_position: e.target.value })} required /></label><label>Path dokumen<input value={finalizeForm.document_path} onChange={e => setFinalizeForm({ ...finalizeForm, document_path: e.target.value })} placeholder="Opsional" /></label></div><button className="primary" disabled={busy} onClick={finalize}>{busy ? 'Memproses…' : 'Finalisasi & buat BA'}</button></div></>}</section>}
  </div>;
}

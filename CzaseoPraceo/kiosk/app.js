/* CzaseoPraceo — kiosk PWA. Offline-first: IndexedDB + sync.
   UX wg ux-guidelines §3/§6. Czas = zegar urządzenia (D-2). */
'use strict';

const API = '../api/index.php';
const DEDUP_MS   = 15 * 60 * 1000;  // FR-4a
const SUCCESS_MS = 4000;            // auto-powrót po sukcesie (ux §3)
const RECOG_MS   = 12000;           // auto-anulowanie rozpoznania
const SYNC_EVERY = 30000;           // okresowa synchronizacja

let token = null;
let place = { id: null, name: '—' };
let employeesById = new Map();   // id -> {id, first_name, last_name, initials, state, last_time}
let cardToEmp = new Map();       // card_number -> employee_id
let recogCtx = null;
let recogTimer = null, successTimer = null;

/* ---------- IndexedDB (jeden, spójny helper) ---------- */
const DB_NAME = 'czaseo-kiosk', DB_VER = 1;
function openDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VER);
    req.onupgradeneeded = () => {
      const db = req.result;
      if (!db.objectStoreNames.contains('meta'))      db.createObjectStore('meta');
      if (!db.objectStoreNames.contains('employees')) db.createObjectStore('employees', { keyPath: 'id' });
      if (!db.objectStoreNames.contains('cards'))     db.createObjectStore('cards'); // key=card_number -> emp_id
      if (!db.objectStoreNames.contains('pending'))   db.createObjectStore('pending', { keyPath: 'local_id', autoIncrement: true });
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}
const dbp = openDB();
/** Wykonaj operację i zwróć wynik żądania po zatwierdzeniu transakcji. */
function idb(store, mode, fn) {
  return dbp.then(db => new Promise((resolve, reject) => {
    const t = db.transaction(store, mode);
    const stores = Array.isArray(store) ? store.map(s => t.objectStore(s)) : t.objectStore(store);
    const req = fn(stores);
    t.oncomplete = () => resolve(req && 'result' in req ? req.result : undefined);
    t.onerror = () => reject(t.error);
    t.onabort = () => reject(t.error);
  }));
}
const metaGet = k => idb('meta', 'readonly', s => s.get(k));
const metaSet = (k, v) => idb('meta', 'readwrite', s => s.put(v, k));

/* ---------- Ekrany ---------- */
function show(id) {
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.getElementById(id).classList.add('active');
}

/* ---------- Zegar ---------- */
const DNI = ['niedziela','poniedziałek','wtorek','środa','czwartek','piątek','sobota'];
const MIE = ['stycznia','lutego','marca','kwietnia','maja','czerwca','lipca','sierpnia','września','października','listopada','grudnia'];
function pad2(n){ return String(n).padStart(2,'0'); }
function tick() {
  const d = new Date();
  const c = document.getElementById('clock'); if (c) c.textContent = `${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
  const dt = document.getElementById('date'); if (dt) dt.textContent = `${DNI[d.getDay()]}, ${d.getDate()} ${MIE[d.getMonth()]} ${d.getFullYear()}`;
  const nd = document.getElementById('nameday');
  if (nd) {
    const key = pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    nd.textContent = (typeof IMIENINY !== 'undefined' && IMIENINY[key]) ? 'Imieniny: ' + IMIENINY[key] : '';
  }
}
function deviceTime(d){ return `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())} ${pad2(d.getHours())}:${pad2(d.getMinutes())}:${pad2(d.getSeconds())}`; }
function hhmm(d){ return `${pad2(d.getHours())}:${pad2(d.getMinutes())}`; }

/* ---------- Sieć / status ---------- */
function setNet() {
  const on = navigator.onLine;
  document.getElementById('net').classList.toggle('offline', !on);
  document.getElementById('net-label').textContent = on ? 'online' : 'offline — zapisuję lokalnie';
}
async function refreshToSync() {
  const c = await idb('pending', 'readonly', s => s.count());
  document.getElementById('tosync').textContent = c > 0 ? `do synchronizacji: ${c}` : '';
}

/* ---------- Tablica obecności (obecni / nieobecni) ---------- */
function attName(e) {
  const ln = (e.last_name || '').trim();
  return (e.first_name || '') + (ln ? ' ' + ln.charAt(0).toUpperCase() + '.' : '');
}
function fillList(id, list, emptyText) {
  const box = document.getElementById(id);
  box.innerHTML = '';
  if (list.length === 0) { const s = document.createElement('span'); s.className = 'none'; s.textContent = emptyText; box.appendChild(s); return; }
  for (const e of list) {
    const span = document.createElement('span');
    span.className = 'att-item';
    span.textContent = attName(e);
    box.appendChild(span);
  }
}
function renderAttendance() {
  const all = [...employeesById.values()];
  const byLast = (a, b) => (a.last_name || '').localeCompare(b.last_name || '', 'pl');
  const present = all.filter(e => e.state === 'in').sort(byLast);
  const absent  = all.filter(e => e.state !== 'in').sort(byLast);
  document.getElementById('present-count').textContent = String(present.length);
  document.getElementById('absent-count').textContent  = String(absent.length);
  fillList('present-list', present, 'Nikogo w pracy');
  fillList('absent-list', absent, 'Wszyscy obecni');
}

/* ---------- Pogoda (Open-Meteo, bez klucza) ---------- */
const WX = {0:'☀️',1:'🌤️',2:'⛅',3:'☁️',45:'🌫️',48:'🌫️',51:'🌦️',53:'🌦️',55:'🌦️',
  56:'🌧️',57:'🌧️',61:'🌧️',63:'🌧️',65:'🌧️',66:'🌧️',67:'🌧️',71:'🌨️',73:'🌨️',75:'❄️',
  77:'❄️',80:'🌦️',81:'🌧️',82:'⛈️',85:'🌨️',86:'🌨️',95:'⛈️',96:'⛈️',99:'⛈️'};
function wxIcon(code) { return WX[code] || '🌡️'; }
const WDAY = ['niedz.','pon.','wt.','śr.','czw.','pt.','sob.'];

async function loadWeather() {
  const box = document.getElementById('weather');
  const city = await metaGet('city');
  if (!city) { box.classList.add('hidden'); box.innerHTML = ''; return; }
  if (!navigator.onLine) { if (!box.innerHTML) box.classList.add('hidden'); return; }
  try {
    let coords = await metaGet('coords');
    if (!coords || coords.city !== city) {
      const g = await fetch(`https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(city)}&count=1&language=pl&format=json`);
      const gd = await g.json();
      if (!gd.results || !gd.results.length) { box.classList.add('hidden'); return; }
      coords = { city, lat: gd.results[0].latitude, lon: gd.results[0].longitude };
      await metaSet('coords', coords);
    }
    const u = `https://api.open-meteo.com/v1/forecast?latitude=${coords.lat}&longitude=${coords.lon}`
      + `&current=temperature_2m,weather_code&daily=weather_code,temperature_2m_max,temperature_2m_min&timezone=auto&forecast_days=5`;
    const d = await (await fetch(u)).json();
    if (!d || !d.current || !d.daily) return;
    let html = `<div class="wx-current"><div class="ic">${wxIcon(d.current.weather_code)}</div>`
      + `<div class="t">${Math.round(d.current.temperature_2m)}°</div><div class="d">${city}</div></div>`;
    for (let i = 0; i < d.daily.time.length; i++) {
      const dt = new Date(d.daily.time[i] + 'T00:00');
      html += `<div class="wx-day"><div class="d">${WDAY[dt.getDay()]}</div><div class="ic">${wxIcon(d.daily.weather_code[i])}</div>`
        + `<div class="mm"><span class="mx">${Math.round(d.daily.temperature_2m_max[i])}°</span> `
        + `<span class="mn">${Math.round(d.daily.temperature_2m_min[i])}°</span></div></div>`;
    }
    box.innerHTML = html;
    box.classList.remove('hidden');
  } catch (_) { if (!box.innerHTML) box.classList.add('hidden'); }
}

/* ---------- Ładowanie danych pracowników ---------- */
async function loadFromCache() {
  const arr  = await idb('employees', 'readonly', s => s.getAll());
  employeesById = new Map((arr || []).map(e => [e.id, e]));
  const keys = await idb('cards', 'readonly', s => s.getAllKeys());
  const vals = await idb('cards', 'readonly', s => s.getAll());
  cardToEmp = new Map((keys || []).map((k, i) => [String(k), vals[i]]));
  const ln = await metaGet('loc_name'); if (ln) place.name = ln;
  renderAttendance();
}
async function fetchEmployees() {
  const res = await fetch(`${API}/employees`, { headers: { 'X-Kiosk-Token': token } });
  if (!res.ok) throw new Error('http ' + res.status);
  const data = await res.json();
  place = { id: data.location_id, name: data.location_name || place.name };
  await idb(['employees','cards'], 'readwrite', ([es, cs]) => {
    es.clear(); cs.clear();
    for (const e of data.employees) {
      es.put({ id: e.id, first_name: e.first_name, last_name: e.last_name, initials: e.initials,
               state: e.state || 'out', last_time: e.last_time || null });
      for (const card of (e.cards || [])) cs.put(e.id, String(card.number));
    }
  });
  await metaSet('loc_name', place.name);
  await loadFromCache();
  document.getElementById('loc-name').textContent = place.name;
}

/* ---------- Odczyt karty (HID „klawiatura") ---------- */
let buf = '', bufTimer = null;
function onKey(e) {
  const setupActive = document.getElementById('screen-setup').classList.contains('active');
  const tag = (document.activeElement && document.activeElement.tagName) || '';
  if (setupActive || tag === 'INPUT' || tag === 'TEXTAREA') return;
  if (e.key === 'Enter') { if (buf.length) { const n = buf; buf = ''; processCard(n); } return; }
  if (e.key >= '0' && e.key <= '9') {
    buf += e.key;
    clearTimeout(bufTimer);
    bufTimer = setTimeout(() => { buf = ''; }, 500);
  }
}

function processCard(number) {
  number = String(number).replace(/\D/g, '');
  if (!number) return;
  const empId = cardToEmp.get(number);
  if (empId === undefined) return showError('Karta nieznana', 'Zgłoś się do kierownika.');
  const emp = employeesById.get(empId);
  if (!emp) return showError('Karta nieznana', 'Zgłoś się do kierownika.');

  if (emp.last_time) {
    const last = new Date(String(emp.last_time).replace(' ', 'T')).getTime();
    const diff = Date.now() - last;
    if (!isNaN(last) && diff >= 0 && diff < DEDUP_MS) {
      return showWarn('Już odbito', `Ostatnie odbicie o ${hhmm(new Date(last))}.`);
    }
  }
  const action = emp.state === 'in' ? 'out' : 'in';
  recogCtx = { emp, action };
  document.getElementById('rec-name').textContent = `${emp.first_name} ${emp.last_name}`;
  document.getElementById('rec-action').textContent = action === 'in' ? 'Wejście' : 'Wyjście';
  document.getElementById('confirm-label').textContent = action === 'in' ? 'Potwierdź wejście' : 'Potwierdź wyjście';
  show('screen-recognized');
  clearTimeout(recogTimer);
  recogTimer = setTimeout(() => { recogCtx = null; show('screen-idle'); }, RECOG_MS);
}

function cardNumberForEmp(empId) { for (const [num, id] of cardToEmp) if (id === empId) return num; return ''; }

async function commitPunch() {
  if (!recogCtx) return;
  clearTimeout(recogTimer);
  const { emp, action } = recogCtx;
  recogCtx = null;
  const now = new Date();
  const punch = {
    device_punch_id: uuid(),
    card_number: cardNumberForEmp(emp.id),
    employee_id: emp.id,
    punch_type: action,
    device_time: deviceTime(now)
  };
  await idb('pending', 'readwrite', s => s.add(punch));
  emp.state = action; emp.last_time = punch.device_time;
  await idb('employees', 'readwrite', s => s.put(emp));
  renderAttendance();
  await refreshToSync();

  feedbackOk();
  document.getElementById('success-title').textContent = action === 'in' ? 'Zarejestrowano wejście' : 'Zarejestrowano wyjście';
  document.getElementById('success-sub').textContent = `${emp.first_name} ${emp.last_name} · ${hhmm(now)}`;
  show('screen-success');
  clearTimeout(successTimer);
  successTimer = setTimeout(() => show('screen-idle'), SUCCESS_MS);
  syncNow();
}

/* ---------- Ekrany wyniku ---------- */
function showWarn(title, sub) {
  document.getElementById('warn-title').textContent = title;
  document.getElementById('warn-sub').textContent = sub || '';
  feedbackWarn(); show('screen-warn'); setTimeout(() => show('screen-idle'), SUCCESS_MS);
}
function showError(title, sub) {
  document.getElementById('error-title').textContent = title;
  document.getElementById('error-sub').textContent = sub || '';
  feedbackWarn(); show('screen-error'); setTimeout(() => show('screen-idle'), SUCCESS_MS);
}

/* ---------- Feedback: dźwięk + wibracja + (kolor/ikona/tekst na ekranie) ---------- */
let audioCtx = null;
function beep(freq, ms) {
  try {
    audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
    const o = audioCtx.createOscillator(), g = audioCtx.createGain();
    o.frequency.value = freq; o.connect(g); g.connect(audioCtx.destination);
    g.gain.value = 0.15; o.start(); setTimeout(() => o.stop(), ms);
  } catch (_) {}
}
function feedbackOk()   { beep(880, 120); if (navigator.vibrate) navigator.vibrate(120); }
function feedbackWarn() { beep(330, 200); if (navigator.vibrate) navigator.vibrate([80,60,80]); }

/* ---------- Sync ---------- */
let syncing = false;
async function syncNow() {
  if (syncing || !navigator.onLine || !token) return;
  syncing = true;
  try {
    const arr = await idb('pending', 'readonly', s => s.getAll());
    if (arr && arr.length) {
      const payload = { punches: arr.map(p => ({
        device_punch_id: p.device_punch_id, card_number: p.card_number,
        punch_type: p.punch_type, device_time: p.device_time
      })) };
      const res = await fetch(`${API}/punches`, {
        method: 'POST',
        headers: { 'X-Kiosk-Token': token, 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      if (res.ok) {
        const data = await res.json();
        const done = [];
        for (const p of arr) {
          const r = data.results[p.device_punch_id];
          if (r === 'accepted' || r === 'duplicate' || r === 'deduped' || (typeof r === 'string' && r.startsWith('rejected'))) done.push(p.local_id);
        }
        if (done.length) await idb('pending', 'readwrite', s => { done.forEach(id => s.delete(id)); });
      }
    }
    await fetchEmployees().catch(() => {});
    await metaSet('last_sync', Date.now());
  } catch (_) { /* offline — później */ }
  finally { syncing = false; await refreshToSync(); }
}

/* ---------- Narzędzia ---------- */
function uuid() {
  if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
  return 'p-' + Date.now() + '-' + Math.floor(Math.random() * 1e9);
}

/* ---------- Konfiguracja (pierwsze uruchomienie) ---------- */
async function saveToken() {
  const val  = document.getElementById('token').value.trim();
  const city = document.getElementById('city').value.trim();
  const err  = document.getElementById('setup-error');
  err.classList.add('hidden');
  if (!/^[0-9a-f]{16,}$/i.test(val)) { err.textContent = 'Nieprawidłowy token.'; err.classList.remove('hidden'); return; }
  token = val;
  try {
    await fetchEmployees();
    await metaSet('token', token);
    await metaSet('city', city);
    renderAttendance();
    loadWeather();
    show('screen-idle');
  } catch (_) {
    token = null;
    err.textContent = 'Nie udało się połączyć (token lub sieć). Spróbuj ponownie.';
    err.classList.remove('hidden');
  }
}

/* ---------- Start ---------- */
async function init() {
  tick(); setInterval(tick, 1000);
  setNet();
  window.addEventListener('online',  () => { setNet(); syncNow(); });
  window.addEventListener('offline', setNet);
  document.addEventListener('keydown', onKey);
  document.getElementById('confirm-btn').addEventListener('click', commitPunch);
  document.getElementById('cancel-btn').addEventListener('click', () => { recogCtx = null; clearTimeout(recogTimer); show('screen-idle'); });
  document.getElementById('setup-save').addEventListener('click', saveToken);
  document.getElementById('gear').addEventListener('click', async () => {
    document.getElementById('token').value = token || '';
    document.getElementById('city').value = (await metaGet('city')) || '';
    document.getElementById('setup-error').classList.add('hidden');
    show('screen-setup');
  });

  const stored = await metaGet('token');
  if (stored) {
    token = stored;
    await loadFromCache();
    document.getElementById('loc-name').textContent = place.name;
    renderAttendance();
    loadWeather();
    show('screen-idle');
    syncNow();
  } else {
    show('screen-setup');
  }
  await refreshToSync();
  setInterval(syncNow, SYNC_EVERY);
  setInterval(loadWeather, 30 * 60 * 1000); // odśwież pogodę co 30 min

  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});
}
init();

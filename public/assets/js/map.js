// 地図ページ: API から施設を取り、選んだ地図アダプタでマーカーを出す。
// 「API の件数」と「画面のマーカー数」を並べて表示する（一致しないと警告）。
(function () {
  'use strict';
  const app = document.getElementById('app');
  if (!app) return;
  const $ = (id) => document.getElementById(id);
  const state = { hotel: app.dataset.hotel, mapType: app.dataset.map, map: null, meta: null, selected: null };

  async function fetchFacilities(hotel, cats) {
    const p = new URLSearchParams({ hotel });
    if (cats) (cats.length ? cats : ['']).forEach((c) => p.append('category[]', c)); // 空の選択 = 何も出さない
    const r = await fetch('/api/facilities.php?' + p.toString(), { headers: { Accept: 'application/json' } });
    if (!r.ok) throw new Error('施設データを取得できませんでした（HTTP ' + r.status + '）');
    return r.json();
  }

  // 情報ウィンドウの中身。textContent で組み立てる（施設名などを HTML として解釈しない）
  function popup(f) {
    const d = document.createElement('div');
    d.className = 'popup';
    const t = document.createElement('strong'); t.textContent = f.name; d.appendChild(t);
    const c = document.createElement('div'); c.className = 'muted'; c.textContent = f.category_label; d.appendChild(c);
    if (f.address) { const a = document.createElement('div'); a.textContent = f.address; d.appendChild(a); }
    if (f.description) { const p = document.createElement('div'); p.textContent = f.description; d.appendChild(p); }
    return d;
  }

  function showError(msg) {
    const el = $('map-error');
    el.textContent = msg || '';
    el.hidden = !msg;
  }

  function updateCounts(apiCount) {
    const markers = state.map ? state.map.markerCount() : 0;
    const el = $('counts');
    el.dataset.apiCount = String(apiCount);
    el.dataset.markerCount = String(markers);
    el.textContent = 'DB（API）の件数: ' + apiCount + ' ／ 画面のマーカー数: ' + markers + (apiCount === markers ? '（一致）' : '（★不一致）');
    el.classList.toggle('bad', apiCount !== markers);
  }

  function buildCategories(categories) {
    const box = $('category-boxes');
    box.replaceChildren();
    categories.forEach((c) => {
      const label = document.createElement('label');
      label.className = 'check';
      const cb = document.createElement('input');
      cb.type = 'checkbox'; cb.value = c.key; cb.checked = true;
      cb.addEventListener('change', refresh);
      label.append(cb, ' ', c.label);
      box.appendChild(label);
    });
  }

  function selectedCategories() {
    return Array.from(document.querySelectorAll('#category-boxes input:checked')).map((i) => i.value);
  }

  // 素早くチェックを切り替えると、古い応答が後から届いて表示を上書きしてしまうため、最新の要求の結果だけを使う
  let latest = 0;
  async function refresh() {
    const mine = ++latest;
    try {
      const data = await fetchFacilities(state.hotel, selectedCategories());
      if (mine !== latest) return; // 古い応答は捨てる
      state.map.setMarkers(data.facilities, popup);
      updateCounts(data.count);
    } catch (e) {
      if (mine === latest) showError(e.message);
    }
  }

  async function start() {
    showError('');
    try {
      const data = await fetchFacilities(state.hotel, null);
      state.meta = data.hotel;
      buildCategories(data.hotel.categories);
      const adapter = window.MapAdapters[state.mapType] || window.MapAdapters.leaflet;
      state.map = await adapter.create($('map'), { center: data.hotel.center, zoom: data.hotel.zoom, onError: showError });
      state.map.setMarkers(data.facilities, popup);
      updateCounts(data.count);
    } catch (e) {
      showError(e.message);
      $('counts').textContent = '地図を表示できませんでした';
    }
  }

  $('hotel-select').addEventListener('change', (e) => {
    location.search = '?hotel=' + encodeURIComponent(e.target.value) + '&map=' + encodeURIComponent(state.mapType);
  });
  $('map-select').addEventListener('change', (e) => {
    location.search = '?hotel=' + encodeURIComponent(state.hotel) + '&map=' + encodeURIComponent(e.target.value);
  });
  start();
})();

// 静的版の地図ページ: data.js（施設データの固定の写し）を Leaflet + OpenStreetMap で表示する。読み取り専用・外部通信は地図タイルのみ。
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const hotels = (window.DEMO_DATA && window.DEMO_DATA.hotels) || [];
  let map = null;
  let layer = null;
  let current = null;

  function showError(msg) {
    const el = $('map-error');
    el.textContent = msg || '';
    el.hidden = !msg;
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

  function selectedCategories() {
    return Array.from(document.querySelectorAll('#category-boxes input:checked')).map((i) => i.value);
  }

  function render() {
    const cats = selectedCategories();
    const items = current.facilities.filter((f) => cats.includes(f.category));
    layer.clearLayers();
    items.forEach((f) => {
      const m = window.L.marker([f.lat, f.lng], { title: f.name });
      m.bindPopup(popup(f));
      layer.addLayer(m);
    });
    const markers = layer.getLayers().length;
    const el = $('counts');
    el.dataset.dataCount = String(items.length);
    el.dataset.markerCount = String(markers);
    el.textContent = '埋め込みデータの件数: ' + items.length + ' ／ 画面のマーカー数: ' + markers + (items.length === markers ? '（一致）' : '（★不一致）');
    el.classList.toggle('bad', items.length !== markers);
  }

  function buildCategories() {
    const box = $('category-boxes');
    box.replaceChildren();
    current.categories.forEach((c) => {
      const label = document.createElement('label');
      label.className = 'check';
      const cb = document.createElement('input');
      cb.type = 'checkbox'; cb.value = c.key; cb.checked = true;
      cb.addEventListener('change', render);
      label.append(cb, ' ', c.label);
      box.appendChild(label);
    });
  }

  function selectHotel(slug) {
    current = hotels.find((h) => h.slug === slug) || hotels[0];
    $('hotel-select').value = current.slug;
    if (location.hash !== '#' + current.slug) history.replaceState(null, '', '#' + current.slug);
    buildCategories();
    map.setView([current.center.lat, current.center.lng], current.zoom);
    render();
  }

  function start() {
    if (!window.L || hotels.length === 0) {
      showError('地図またはデータを読み込めませんでした');
      $('counts').textContent = '地図を表示できませんでした';
      return;
    }
    const sel = $('hotel-select');
    hotels.forEach((h) => {
      const o = document.createElement('option');
      o.value = h.slug; o.textContent = h.name;
      sel.appendChild(o);
    });
    sel.addEventListener('change', (e) => selectHotel(e.target.value));
    window.L.Icon.Default.imagePath = 'vendor/leaflet/images/';
    map = window.L.map($('map'));
    window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);
    layer = window.L.layerGroup().addTo(map);
    window.addEventListener('hashchange', () => selectHotel(location.hash.slice(1))); // 同じタブでの URL 変更・戻る操作にも追従
    selectHotel(location.hash.slice(1));
  }
  start();
})();

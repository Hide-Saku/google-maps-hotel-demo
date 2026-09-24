// 地図アダプタ: Google Maps JavaScript API
// ★未検証: API キーで実際に動かして確認するまでは、動いたとは書かない（README の「Google 版」を参照）。
// キーは <meta name="gmaps-key"> から読む（サーバーが環境変数 GOOGLE_MAPS_API_KEY を渡したときだけ存在する）。
// キーにはリファラ制限（http://localhost:8080/*）と API 制限（Maps JavaScript API のみ）を付ける前提。
(function () {
  'use strict';
  window.MapAdapters = window.MapAdapters || {};

  function loadGoogle(key) {
    return new Promise((resolve, reject) => {
      if (window.google && window.google.maps && window.google.maps.importLibrary) { resolve(); return; }
      const cb = '__gmapsReady' + Date.now();
      const timer = setTimeout(() => reject(new Error('Google Maps の読み込みがタイムアウトしました（15秒）')), 15000);
      window[cb] = () => { clearTimeout(timer); resolve(); };
      const s = document.createElement('script');
      s.async = true;
      s.src = 'https://maps.googleapis.com/maps/api/js?' + new URLSearchParams({ key, v: 'weekly', loading: 'async', callback: cb }).toString();
      s.onerror = () => { clearTimeout(timer); reject(new Error('Google Maps JavaScript API を読み込めませんでした（ネットワークを確認）')); };
      document.head.appendChild(s);
    });
  }

  window.MapAdapters.google = {
    label: 'Google Maps',
    async create(el, opt) {
      const meta = document.querySelector('meta[name="gmaps-key"]');
      const key = meta ? meta.content : '';
      if (!key) throw new Error('Google Maps の API キーが未設定です（.env の GOOGLE_MAPS_API_KEY）');
      // 認証に失敗すると Google が呼ぶ関数（キーの制限・請求先・API の有効化のどれかが原因）
      window.gm_authFailure = () => {
        if (opt.onError) opt.onError('Google Maps の認証に失敗しました。キーのリファラ制限（http://localhost:8080/*）・API 制限・請求先・Maps JavaScript API の有効化を確認してください。');
      };
      await loadGoogle(key);
      const { Map, InfoWindow } = await window.google.maps.importLibrary('maps');
      const { AdvancedMarkerElement } = await window.google.maps.importLibrary('marker');
      const map = new Map(el, { center: opt.center, zoom: opt.zoom, mapId: 'DEMO_MAP_ID' }); // AdvancedMarker には Map ID が必要
      const info = new InfoWindow();
      let markers = [];
      return {
        setMarkers(items, render) {
          markers.forEach((m) => { m.map = null; });
          markers = items.map((f) => {
            const m = new AdvancedMarkerElement({ map, position: { lat: f.lat, lng: f.lng }, title: f.name });
            m.addListener('click', () => { info.setContent(render(f)); info.open({ anchor: m, map }); });
            return m;
          });
        },
        markerCount() { return markers.length; },
        destroy() { markers.forEach((m) => { m.map = null; }); markers = []; el.replaceChildren(); },
      };
    },
  };
})();

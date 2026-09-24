// 地図アダプタ: Leaflet + OpenStreetMap（API キー不要）
// アダプタの約束: create(要素, {center, zoom}) → { setMarkers(施設の配列, 情報ウィンドウの作り方), markerCount(), destroy() }
(function () {
  'use strict';
  window.MapAdapters = window.MapAdapters || {};
  window.MapAdapters.leaflet = {
    label: 'Leaflet + OpenStreetMap',
    async create(el, opt) {
      if (!window.L) throw new Error('Leaflet を読み込めませんでした');
      window.L.Icon.Default.imagePath = '/assets/vendor/leaflet/images/';
      const map = window.L.map(el).setView([opt.center.lat, opt.center.lng], opt.zoom);
      window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      }).addTo(map);
      const layer = window.L.layerGroup().addTo(map);
      return {
        setMarkers(items, render) {
          layer.clearLayers();
          items.forEach((f) => {
            const m = window.L.marker([f.lat, f.lng], { title: f.name });
            m.bindPopup(render(f)); // DOM 要素を渡す（HTML 文字列にしない）
            layer.addLayer(m);
          });
        },
        markerCount() { return layer.getLayers().length; },
        destroy() { map.remove(); },
      };
    },
  };
})();

// 静的版（static/）の施設データを、動いているデモの API から作る。
// 使い方: docker compose up -d のあと  node scripts/build_static.mjs
// 出力: static/data.js（ホテルの設定と、表示対象の施設だけ。秘密・内部の id・更新日時は含めない）
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const BASE = process.env.DEMO_BASE || 'http://localhost:8080';
const SLUGS = ['kyoto', 'sapporo', 'naha'];

const hotels = [];
for (const slug of SLUGS) {
  const r = await fetch(`${BASE}/api/facilities.php?hotel=${slug}`);
  if (!r.ok) throw new Error(`${slug}: HTTP ${r.status}`);
  const j = await r.json();
  hotels.push({
    slug: j.hotel.slug,
    name: j.hotel.name,
    center: j.hotel.center,
    zoom: j.hotel.zoom,
    categories: j.hotel.categories,
    facilities: j.facilities.map((f) => ({
      name: f.name, category: f.category, category_label: f.category_label,
      lat: f.lat, lng: f.lng, address: f.address, description: f.description,
    })),
  });
}
const out = '// 自動生成: node scripts/build_static.mjs（動いているデモの API の写し。手で編集しない）\n'
  + 'window.DEMO_DATA = ' + JSON.stringify({ hotels }, null, 1) + ';\n';
fs.writeFileSync(path.join(ROOT, 'static', 'data.js'), out);
console.log(hotels.map((h) => `${h.slug}: ${h.facilities.length}件`).join(' / '));

// 静的版（static/）の検査。Node のみ（ブラウザ不要）。
// 使い方: docker compose up -d のあと  node tests/static_check.mjs
// ①埋め込みデータが動いているデモの API と一致 ②外部通信は地図タイルのみ・キーや秘密が無い ③「自主検証・架空データ」の明記 ④読み取り専用
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const S = path.join(ROOT, 'static');
const BASE = process.env.DEMO_BASE || 'http://localhost:8080';
let pass = 0, fail = 0;
const check = (name, cond, detail = '') => { if (cond) { pass++; console.log('  ok   ' + name); } else { fail++; console.log('  FAIL ' + name + (detail ? ' — ' + detail : '')); } };

const ctx = { window: {} };
vm.runInNewContext(fs.readFileSync(path.join(S, 'data.js'), 'utf8'), ctx);
const hotels = ctx.window.DEMO_DATA.hotels;

// ① API との一致（全カテゴリ・カテゴリごと）
for (const h of hotels) {
  const all = await (await fetch(`${BASE}/api/facilities.php?hotel=${h.slug}`)).json();
  check(`${h.slug}: 埋め込み件数 = API の件数 (${all.count})`, h.facilities.length === all.count, `${h.facilities.length} vs ${all.count}`);
  check(`${h.slug}: 施設の内容（名前・座標・住所・説明）が API と一致`,
    JSON.stringify(h.facilities.map((f) => [f.name, f.category, f.lat, f.lng, f.address, f.description]).sort())
    === JSON.stringify(all.facilities.map((f) => [f.name, f.category, f.lat, f.lng, f.address, f.description]).sort()));
  for (const c of h.categories) {
    const api = await (await fetch(`${BASE}/api/facilities.php?hotel=${h.slug}&category[]=${c.key}`)).json();
    check(`${h.slug}/${c.key}: カテゴリ別の件数が API と一致 (${api.count})`, h.facilities.filter((f) => f.category === c.key).length === api.count);
  }
  check(`${h.slug}: 表示しないカテゴリの施設が混ざっていない`, h.facilities.every((f) => h.categories.some((c) => c.key === f.category)));
}
check('ホテルは3件（京都・札幌・那覇）', hotels.map((h) => h.slug).join() === 'kyoto,sapporo,naha');

// ②③④ 静的ファイルの中身
const files = ['index.html', 'app.js', 'data.js', 'style.css'].map((n) => [n, fs.readFileSync(path.join(S, n), 'utf8')]);
const all = files.map(([, t]) => t).join('\n');
check('API キー・秘密らしい文字列が無い（AIza / key= / password / secret）', !/AIza|[?&]key=|password|secret/i.test(all));
check('Google Maps の読み込みが無い', !/maps\.googleapis|gmaps|google\.maps/i.test(all));
const html = files[0][1];
const urls = [...(html + files[1][1]).matchAll(/https?:\/\/[^\s"'<>)]+/g)].map((m) => m[0]);
const allowed = (u) => u.startsWith('https://tile.openstreetmap.org') || u.startsWith('https://www.openstreetmap.org/copyright') || u.startsWith('https://github.com/Hide-Saku/google-maps-hotel-demo');
check('外部の URL は 地図タイル・OSM の著作権表示・リポジトリへのリンクのみ', urls.every(allowed), urls.filter((u) => !allowed(u)).join(' '));
check('CSP: connect-src が none（fetch・XHR は出せない）', /connect-src 'none'/.test(html));
check('「自主検証・架空データ」と「実績ではない」の明記', html.includes('自主検証・架空データ') && html.includes('実績ではありません'));
check('読み取り専用: form・fetch・XMLHttpRequest・innerHTML を使っていない', !/<form|fetch\(|XMLHttpRequest|innerHTML|document\.write/.test(html + files[1][1]));
check('施設データに実在の店名らしい未注記の名前が無い（全件「（架空）」付き）', hotels.every((h) => h.facilities.every((f) => f.name.includes('（架空）'))));

console.log(`\n結果: ${pass} 件合格 / ${fail} 件失敗`);
process.exit(fail === 0 ? 0 : 1);

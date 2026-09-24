// 静的版（static/）のブラウザでの実操作テスト（Playwright・Edge）。自前の小さな HTTP サーバーで static/ を配信する。
// 使い方: playwright-core が解決できる場所で  node tests/static_e2e.mjs     （--shots を付けると docs/screens/ に画像も撮る）
import { chromium } from 'playwright-core';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const S = path.join(ROOT, 'static');
const OUT = path.join(ROOT, 'docs', 'screens');
const EDGE = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const shots = process.argv.includes('--shots');
const MIME = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.css': 'text/css; charset=utf-8', '.png': 'image/png' };

const ctx = { window: {} };
vm.runInNewContext(fs.readFileSync(path.join(S, 'data.js'), 'utf8'), ctx);
const hotels = ctx.window.DEMO_DATA.hotels;

const server = http.createServer((req, res) => {
  const p = path.join(S, decodeURIComponent(new URL(req.url, 'http://x').pathname).replace(/^\/$/, '/index.html'));
  if (!p.startsWith(S) || !fs.existsSync(p) || fs.statSync(p).isDirectory()) { res.writeHead(404); res.end(); return; }
  res.writeHead(200, { 'Content-Type': MIME[path.extname(p)] || 'application/octet-stream' });
  res.end(fs.readFileSync(p));
});
await new Promise((r) => server.listen(0, '127.0.0.1', r));
const BASE = `http://127.0.0.1:${server.address().port}`;

let pass = 0, fail = 0;
const check = (name, cond, detail = '') => { if (cond) { pass++; console.log('  ok   ' + name); } else { fail++; console.log('  FAIL ' + name + (detail ? ' — ' + detail : '')); } };

const b = await chromium.launch({ executablePath: EDGE, headless: true });
const page = await (await b.newContext({ viewport: { width: 1280, height: 900 }, locale: 'ja-JP' })).newPage();
const errors = [];
const hosts = new Set();
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('request', (r) => { const u = new URL(r.url()); if (!r.url().startsWith('data:')) hosts.add(u.host); });

const domMarkers = () => page.locator('.leaflet-marker-icon').count();
async function settle() { await page.waitForTimeout(400); }

for (const h of hotels) {
  await page.goto(`${BASE}/#${h.slug}`, { waitUntil: 'load' });
  await settle();
  check(`${h.slug}: 開くとホテル名が選ばれ、マーカー数(DOM) = 埋め込み件数 ${h.facilities.length}`,
    (await page.locator('#hotel-select').inputValue()) === h.slug && (await domMarkers()) === h.facilities.length);
  // 各カテゴリを1つずつ外す・戻す
  for (const c of h.categories) {
    const cb = page.locator(`#category-boxes input[value="${c.key}"]`);
    await cb.uncheck(); await settle();
    const expect = h.facilities.filter((f) => f.category !== c.key).length;
    check(`${h.slug}: 「${c.label}」を外すと ${expect} 件（DOM のマーカー数）`, (await domMarkers()) === expect);
    await cb.check(); await settle();
  }
  // すべて外すと0件・表示は「一致」
  for (const c of h.categories) await page.locator(`#category-boxes input[value="${c.key}"]`).uncheck();
  await settle();
  check(`${h.slug}: すべて外すと 0 件`, (await domMarkers()) === 0 && (await page.textContent('#counts')).includes('0（一致）'));
  for (const c of h.categories) await page.locator(`#category-boxes input[value="${c.key}"]`).check();
  await settle();
  // 連続操作を10回
  const first = h.categories[0].key;
  for (let i = 0; i < 10; i++) { await page.locator(`#category-boxes input[value="${first}"]`).click(); }
  await settle();
  const exp10 = h.facilities.length; // 10回（偶数）で元に戻る
  check(`${h.slug}: 連続10回の切り替え後、元の ${exp10} 件に戻る（DOM）`, (await domMarkers()) === exp10);
  // 情報ウィンドウ
  await page.locator('.leaflet-marker-icon').first().click();
  await settle();
  const pop = await page.locator('.leaflet-popup-content').innerText();
  check(`${h.slug}: マーカーを押すと情報ウィンドウ（施設名と「架空」を含む）`, pop.includes('（架空）') && h.facilities.some((f) => pop.includes(f.name)), pop.slice(0, 60));
}

// ホテル切り替え（選択操作）
await page.goto(`${BASE}/`, { waitUntil: 'load' }); await settle();
for (const slug of ['naha', 'sapporo', 'kyoto']) {
  await page.selectOption('#hotel-select', slug); await settle();
  const h = hotels.find((x) => x.slug === slug);
  check(`選択で ${slug} に切り替え: カテゴリ欄 ${h.categories.length} 個・マーカー ${h.facilities.length} 件`,
    (await page.locator('#category-boxes input').count()) === h.categories.length && (await domMarkers()) === h.facilities.length);
}
check('画面に「自主検証・架空データ」の明記がある', (await page.textContent('.note')).includes('自主検証・架空データ'));
check('コンソールエラー 0 件', errors.length === 0, errors.join(' | '));
check('通信先は 自分のサーバーと OSM のタイル(tile.openstreetmap.org)のみ',
  [...hosts].every((x) => x.startsWith('127.0.0.1') || x === 'tile.openstreetmap.org'), [...hosts].join(','));

if (shots) {
  await page.goto(`${BASE}/#kyoto`, { waitUntil: 'load' }); await page.waitForTimeout(2500);
  await page.screenshot({ path: path.join(OUT, '11_static_kyoto.png') });
  await page.selectOption('#hotel-select', 'naha'); await page.waitForTimeout(1500);
  await page.locator('.leaflet-marker-icon').first().click(); await page.waitForTimeout(2500);
  await page.screenshot({ path: path.join(OUT, '12_static_naha_popup.png') });
}
await b.close();
server.close();
console.log(`\n結果: ${pass} 件合格 / ${fail} 件失敗`);
process.exit(fail === 0 ? 0 : 1);

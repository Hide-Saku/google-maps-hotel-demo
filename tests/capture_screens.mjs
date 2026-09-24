// 公開向けスクリーンショットを docs/screens/ に撮る（Playwright・ページ内容のみ。開発者ツールは写らない）。
// 使い方: docker compose up -d のあと  node tests/capture_screens.mjs   （playwright-core が解決できる場所で）
// 管理画面のパスワードは .env から読む（画面・出力には出さない。入力欄は黒丸）。
// Google 版は GOOGLE_MAPS_API_KEY が入っているときだけ撮る。測定値は docs/screens/measurements.json。
import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const OUT = path.join(ROOT, 'docs', 'screens');
const BASE = 'http://localhost:8080';
const EDGE = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const envText = fs.readFileSync(path.join(ROOT, '.env'), 'utf8');
const env = (n) => (envText.match(new RegExp(`^${n}=(.*)$`, 'm')) || [, ''])[1].trim();
const redact = (s) => String(s).replace(/AIza[0-9A-Za-z_-]+/g, '[KEY]').replace(/key=[^&\s"']+/g, 'key=[KEY]');

fs.mkdirSync(OUT, { recursive: true });
const hasKey = env('GOOGLE_MAPS_API_KEY') !== '';
const meas = { google_key_present: hasKey, maps: [] };
const b = await chromium.launch({ executablePath: EDGE, headless: true });
const ctx = await b.newContext({ viewport: { width: 1280, height: 900 }, locale: 'ja-JP' });
const page = await ctx.newPage();
let errors = [];
page.on('console', (m) => { if (m.type() === 'error') errors.push(redact(m.text())); });

async function shootMap(hotel, provider, fname) {
  errors = [];
  await page.goto(`${BASE}/?hotel=${hotel}&map=${provider}`, { waitUntil: 'networkidle' });
  await page.waitForFunction(() => { const c = document.querySelector('#counts'); return c && !c.textContent.includes('読み込み中'); }, null, { timeout: 20000 });
  await page.waitForTimeout(3000);
  const api = await page.evaluate((h) => fetch(`/api/facilities.php?hotel=${h}`).then((r) => r.json()).then((j) => (j.facilities || []).length), hotel);
  const counts = await page.innerText('#counts');
  const errEl = await page.$('#map-error');
  const err = errEl && (await errEl.isVisible()) ? await errEl.innerText() : '';
  await page.screenshot({ path: path.join(OUT, fname) });
  meas.maps.push({ file: fname, hotel, provider, api_count: api, counts_text: redact(counts), error_text: redact(err), console_errors: errors.slice(0, 5) });
}

await shootMap('kyoto', 'leaflet', '01_map_kyoto_leaflet.png');
await shootMap('naha', 'leaflet', '02_map_naha_leaflet.png');
if (hasKey) {
  await shootMap('kyoto', 'google', '03_map_kyoto_google.png');
  await shootMap('naha', 'google', '04_map_naha_google.png');
  await page.goto(`${BASE}/?hotel=kyoto&map=google`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(3500);
  await page.locator('gmp-advanced-marker').first().click();
  await page.waitForTimeout(1200);
  await page.screenshot({ path: path.join(OUT, '10_map_kyoto_google_infowindow.png') });
}

await page.goto(`${BASE}/admin/login.php`, { waitUntil: 'networkidle' });
await page.screenshot({ path: path.join(OUT, '05_admin_login.png') }); // 入力前（黒丸の数でパスワード長が分かるため）
await page.fill('input[name=username]', env('ADMIN_USERNAME'));
await page.fill('input[name=password]', env('ADMIN_PASSWORD'));
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');
if (page.url().includes('login')) { console.error('login failed'); process.exit(1); }
await page.screenshot({ path: path.join(OUT, '06_admin_index_import_history.png'), fullPage: true });
await page.click("a:has-text('設定・施設を編集')");
await page.waitForLoadState('networkidle');
await page.screenshot({ path: path.join(OUT, '07_admin_hotel_facilities.png'), fullPage: true });
await page.click("a:has-text('編集')");
await page.waitForLoadState('networkidle');
await page.screenshot({ path: path.join(OUT, '08_admin_facility_edit.png'), fullPage: true });
// テスト合格: docs/evidence/test_output.txt（docker compose ... exec web_test php tests/run_all.php の実出力）を端末風に描画
const esc = (t) => t.replace(/&/g, '&amp;').replace(/</g, '&lt;');
const testText = fs.readFileSync(path.join(ROOT, 'docs', 'evidence', 'test_output.txt'), 'utf8');
await page.setViewportSize({ width: 1100, height: 400 });
await page.setContent(`<body style="margin:0;background:#1e2430"><pre style="margin:0;padding:20px;color:#e6edf3;font:14px/1.6 Consolas,'Yu Gothic UI',monospace;white-space:pre-wrap">${esc(testText)}</pre></body>`);
await page.screenshot({ path: path.join(OUT, '09_tests_passed.png'), fullPage: true });
await b.close();
fs.writeFileSync(path.join(OUT, 'measurements.json'), JSON.stringify(meas, null, 2));
console.log(JSON.stringify(meas, null, 2));

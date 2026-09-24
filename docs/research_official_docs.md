# 公式ドキュメント調査（Google Maps Platform / OSM）

取得日 2026-09-24。方法: WebFetch（ページ本文を要約モデル経由で取得）。要約経由のため引用は要約結果の文言。原文と厳密一致は未確認、法務判断には原ページを直接確認すること。

## 参照 URL
- https://developers.google.com/maps/api-security-best-practices
- https://developers.google.com/maps/documentation/javascript/get-api-key
- https://docs.cloud.google.com/docs/authentication/api-keys
- https://developers.google.com/maps/billing-and-pricing/pricing
- https://developers.google.com/maps/billing-and-pricing/manage-costs
- https://developers.google.com/maps/billing-and-pricing/faq
- https://developers.google.com/maps/documentation/geocoding/policies
- https://cloud.google.com/maps-platform/terms/maps-service-terms （本文が長く切れて取得不能）
- https://cloud.google.com/maps-platform/terms （同上）
- https://developers.google.com/maps/documentation/javascript/load-maps-js-api
- https://developers.google.com/maps/documentation/javascript/advanced-markers/migration
- https://developers.google.com/maps/documentation/javascript/markers
- https://developers.google.com/maps/documentation/javascript/error-messages
- https://developers.google.com/maps/documentation/javascript/events
- https://operations.osmfoundation.org/policies/tiles/

## 1. API キーの制限
| 項目 | 内容 |
|---|---|
| アプリ制限 | 種類: Android/iOS アプリ、ウェブサイト（クライアント側）、IP/CIDR（サーバー側）。 URL: api-security-best-practices |
| HTTP リファラの書式 | ワイルドカード `*` はサブドメインまたはパスの置換のみ可、URL 途中は不可。例 `*.example.com` `example.com/*`。ドメイン全体は `example.com/*` と `*.example.com/*` の2件。 URL: docs.cloud.google.com/docs/authentication/api-keys |
| ポート | 「ポート番号を含めると、そのポートのリクエストだけ一致。指定しないと任意のポートに一致」。よって `http://localhost:8080/*` 形式はポート含め可（同ページの記述から。localhost の具体例そのものは未確認）。 |
| API 制限 | 「キーを使える API/SDK/サービスを制限する」。アプリ制限と API 制限の併用が推奨。 |
| 無制限キー | 「不正利用で生じた料金はあなたが負担する」（api-security-best-practices）。 |
| 補足 | 公式は「制限は強く推奨」（get-api-key）。Maps Demo Key は「テスト・試作専用、本番非対応」。 |
取得日 2026-09-24

## 2. 費用の上限・アラート・料金
| 項目 | 内容 |
|---|---|
| 予算・アラート | 「予算の設定は利用や支出を自動では上限化しない」= 自動停止しない。 URL: manage-costs |
| クォータ | 1日あたり等のクォータ上限を設定して使用量を制限できる。「リクエスト数を制限し予期せぬ課金を防ぐ」。 URL: manage-costs |
| 料金モデル | 従量課金。各コアサービスは SKU ごとの課金イベント単価、月次集計。2025-03-01（グローバル）から $200 月額クレジットを廃止し、コアサービス SKU ごとの月間無料枠へ変更（faq）。無料枠: Essentials 10,000／Pro 5,000／Enterprise 1,000 イベント。 |
| Dynamic Maps | SKU 名「Dynamic Maps」(FAF4-3B2D-51B2)、無料 10,000 イベント/月。以降 $7.00/1000（10,001-100,000）、$5.60（100,001-500,000）等。 URL: pricing |
| Geocoding | SKU 名「Geocoding」(BAC8-4E68-E261)、無料 10,000 イベント/月。単価は今回未取得（faq の例では $5/1000）。 |
| 注意 | Maps JavaScript API の地図読み込みが Dynamic Maps SKU に対応するかの明記は今回未確認（pricing 表の名称からの推定）。 |
取得日 2026-09-24

## 3. ジオコーディング結果・座標の保存
| 項目 | 内容 |
|---|---|
| Place ID | 「キャッシュ制限の対象外。無期限に保存してよい」。 URL: geocoding/policies |
| 緯度経度の保存期間 | **未確認**（Service Specific Terms の本文が取得できず。geocoding/policies にも記載なし）。 |
| 一般のキャッシュ制限 | 「ほとんどのジオコーディング結果は長期の保存・キャッシュ不可」（geocoding/policies の要約）。具体期間は未確認。 |
| Google 以外の地図への表示 | geocoding/policies: Google マップ上に表示する場合は追加の帰属表示不要、Google マップ以外に表示する場合は帰属表示が必要と読める。Leaflet/OSM 併用の可否を明示した禁止条項は今回の取得範囲では**未確認**（Terms 本文が取得不能）。利用規約の該当条項を直接確認すること。 |
取得日 2026-09-24

## 4. Maps JavaScript API の現行の読み込み
| 項目 | 内容 |
|---|---|
| 動的ロード | ブートストラップローダー（インラインスクリプト）で `google.maps.importLibrary()` を定義。`key` は必須。`async` は「可能な限り推奨」。実行時に `await google.maps.importLibrary('maps')`。任意パラメータ v, language, region, authReferrerPolicy, mapIds。公式スニペット全文は今回未取得（load-maps-js-api を直接参照）。`loading=async` の記述自体は今回の取得結果では確認できず**未確認**。 |
| 従来 Marker | `google.maps.Marker` は 2024-02-21（v3.56）から非推奨。「廃止予定はなく既存実装は動作継続」。 URL: markers |
| AdvancedMarkerElement | `google.maps.importLibrary("marker")` か `libraries=marker`、クラスは `google.maps.marker.AdvancedMarkerElement`、初期化時に **Map ID が必要**（例 `mapId: 'DEMO_MAP_ID'`）。最小 API バージョン 3.53.2。 URL: advanced-markers/migration, markers |
| 認証エラー | グローバル関数 `gm_authFailure` を定義しておくと認証失敗時に呼ばれる（events）。`RefererNotAllowedMapError`: 「Maps JavaScript API を読み込んでいる URL が許可リファラ一覧に追加されていない」（error-messages）。 |
取得日 2026-09-24

## 5. OSM 標準タイルの利用ポリシー
URL: https://operations.osmfoundation.org/policies/tiles/
- 帰属: 地図上に明示、通常は右下。「© OpenStreetMap contributors」。
- 識別: 独自の User-Agent（アプリ名＋任意の連絡先）が必要。okhttp や python-requests 等の既定値はブロック。
- Referer: ウェブサイトからは有効な Referer を維持。
- キャッシュ: `Cache-Control` 等を尊重、最低7日程度のキャッシュ。
- 禁止: 大量ダウンロード、スクレイピング、プリフェッチ、オフライン用途。
- 技術: HTTPS の `https://tile.openstreetmap.org/{z}/{x}/{y}.png` のみ。可能なら HTTP/2 か 3。
- 保証なし: best-effort、違反時は予告なくブロックあり。大量利用はほかの提供元やセルフホストを検討。
取得日 2026-09-24

# 設計提案メモ（自主検証・2026-09-24）

「Google Maps × 施設データ × 管理画面」の小さなデモを作りながら、**こちらから気付いて提案できる点**を3つにまとめる。
根拠は公式ドキュメント（取得日 2026-09-24）。取得は要約経由のため、引用は要約結果の文言で、**原文との厳密な一致は未確認**（詳細と URL は `docs/research_official_docs.md`）。
確認できなかった点は「未確認」と書き、断定しない。既存の特定のシステムは見ていないので、以下は「一般に、こう決める必要がある」という提案で、特定のシステムの現状の評価ではない。

## 1. API キーの制限と、費用の上限

**提案**: キーを環境（開発／本番）と用途（ブラウザ／サーバー）で分け、**HTTP リファラ制限と API 制限を必ず付ける**。費用は「アラート」だけでは止まらないので、**クォータ（1日の上限）で物理的に止める**。

- **制限の書式**: リファラのワイルドカード `*` は、サブドメインまたはパスの置換だけに使える（例 `*.example.com`、`example.com/*`）。ポート番号を含めて書くと、そのポートのリクエストだけに一致し、書かなければ任意のポートに一致する。このデモでは `http://localhost:8080/*` と `http://127.0.0.1:8080/*` の2件に絞る想定（`localhost` の具体例そのものは公式の記述からの推測で、**実際にキーを作って動かすまで未検証**）。
  出典: https://docs.cloud.google.com/docs/authentication/api-keys
- **API 制限**: 「そのキーで呼べる API」を絞る。アプリ制限（リファラ）と API 制限の併用が推奨されている。制限のないキーが不正利用された場合の料金は**利用者の負担**と公式に書かれている。
  出典: https://developers.google.com/maps/api-security-best-practices
- **費用**: 予算とアラートは、**利用や支出を自動では上限化しない**（公式の記述）。1日あたりなどの**クォータ上限**を設定して使用量を制限できる。
  出典: https://developers.google.com/maps/billing-and-pricing/manage-costs
- **料金モデル**: 2025-03-01 から、$200 の月間クレジットではなく、**SKU ごとの月間無料枠**になった（公式 FAQ）。料金ページ上、Dynamic Maps と Geocoding の無料枠はいずれも月10,000イベント。**Maps JavaScript API の地図読み込みが Dynamic Maps の SKU に当たるかは、公式ページで明記を確認できていない（未確認）**。Geocoding の単価も今回は未取得。
  出典: https://developers.google.com/maps/billing-and-pricing/pricing ／ https://developers.google.com/maps/billing-and-pricing/faq
- **運用の提案**: ①ブラウザ用キー（リファラ制限）とサーバー用キー（IP 制限）を分ける ②キーはコードに書かず環境変数で渡す（このデモは `.env`・`.gitignore` 済み）③クォータを「想定利用量の数倍」に設定し、予算アラートは早期警告として併用 ④キーの漏えい時にすぐ差し替えられるよう、環境ごとに別のキーにしておく。

## 2. 緯度経度（ジオコーディング結果）の保存方針

**提案**: Google のジオコーディング結果を DB に長期保存する前提で設計しない。**保存してよいと確認できたものだけを保存し、それ以外は都度取得（または短期キャッシュ）にする**。施設の座標は、まず**ホテル側・外部データ側が持つ座標**を正とする。

- **確認できたこと**: **Place ID は、キャッシュ制限の対象外で、無期限に保存してよい**（Geocoding のポリシーページ）。一方、「ほとんどのジオコーディング結果は長期の保存・キャッシュができない」という趣旨の記述がある。
  出典: https://developers.google.com/maps/documentation/geocoding/policies
- **確認できなかったこと（未確認）**: **緯度経度そのものを何日まで保存できるか**。利用規約（Service Specific Terms）の本文を、今回のツールでは取得できなかった。**「期間はこれ」とは書かない。実運用に入る前に、規約の該当条項を直接読んで確認する必要がある。**
  あわせて、**Google 由来のデータを Google 以外の地図（Leaflet/OSM）に表示してよいか**も、規約の本文を確認できていない（ポリシーページには、Google の地図以外に表示する場合は帰属表示が必要という趣旨の記述）。
- **このデモの設計への反映**: 地図アダプタは差し替え可能にしたが、**表示しているのは自前のデータだけ**（Google から取得したデータは一切使っていない）。だから OSM 側の表示で規約に触れる問題は起きない。将来、Google のジオコーディングを使うなら、次のテーブルで**取得元・取得日時・期限を必ず持たせる**:
  `facility_geocodes(facility_id, provider, place_id, lat, lng, fetched_at, expires_at)`。`expires_at` を過ぎた行は、バッチで削除または再取得する。`place_id` だけは期限なしで保持できる（上記の公式記述による）。
- **OSM を使う場合の注意**（公式ポリシー）: 地図上に「© OpenStreetMap contributors」を出す／大量取得・先読みは禁止／ブロックされることがある（best-effort）。本番で利用が多くなるなら、タイルの提供元を変えるか、自前配信を検討する。
  出典: https://operations.osmfoundation.org/policies/tiles/

## 3. 施設データのスキーマと、AI 検索を差し込める場所

**提案**: 「取り込みに強い（冪等）」ことを最初にスキーマへ入れ、AI 検索は**本体テーブルを変えずに、派生データとして横に足す**。

- **スキーマの要点**（`db/schema.sql`。このデモで実装・テスト済み）:
  - `hotels`（ホテルごとの地図の中心・ズーム）＋ `hotel_categories`（**ホテルごとに表示するカテゴリ**）＝ホテル別の設定
  - `facilities` に **`source`（取得元）＋ `external_id`（外部ID）の複合ユニーク**＋ `updated_at` ＝ 外部 API から何度取り込んでも件数が増えない（テストで確認: 同じデータを2回流して、追加0・更新0）
  - `import_runs` / `import_skipped` ＝ 取り込みの履歴と、スキップした行の理由（原因調査のため）
- **AI 検索の差し込み口**（このデモでは実装しない）: ①`facilities.description` などの文章から検索用の文書を作る `search_documents(facility_id, text, embedding, embedded_at)` を**別テーブル**で持つ ②`updated_at > embedded_at` の行だけを再計算する（取り込みが冪等なので、差分更新にできる）③アプリ側は `SearchProvider`（キーワード検索／ベクトル検索）の**インターフェース**の裏に置き、既存の `/api/facilities.php` は変えない。AI コンシェルジュの回答が「DB にある施設」だけを根拠にできるよう、回答には `facility_id` を必ず含める。
- **抜けやすい点**: 外部側で施設が**削除された**とき、今の取り込みは追加・更新しかしない。実運用では `deleted_at`（論理削除）と、「今回の取り込みに含まれなかった行」の扱いを決める必要がある。

## 補足: 公式ドキュメントで確認できた、実装上の注意

- 従来の `google.maps.Marker` は **2024-02-21（v3.56）から非推奨**（廃止予定は無く、既存の実装は動く）。新規実装は `AdvancedMarkerElement`（`marker` ライブラリの読み込みと **Map ID が必要**）。このデモの Google 版アダプタは `AdvancedMarkerElement` で書いてある（**未検証**）。
  出典: https://developers.google.com/maps/documentation/javascript/markers
- 認証に失敗したときは、グローバル関数 `gm_authFailure` が呼ばれる（出典: https://developers.google.com/maps/documentation/javascript/events ）。コンソールには `RefererNotAllowedMapError`（読み込んだ URL が許可リファラに無い）などが出る（出典: https://developers.google.com/maps/documentation/javascript/error-messages ）。このデモは、`gm_authFailure` で画面に原因の候補（リファラ制限・API 制限・請求先・API の有効化）を出す。

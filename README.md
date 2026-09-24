# 施設マップ × 管理画面 の小さなデモ（case15・自主検証）

「ホテルごとに施設情報を持ち、地図に表示し、管理画面で編集でき、外部データを取り込める」の最小構成。
**自作の自主検証で、実務・受託の実績ではない。** ホテル・施設・人物はすべて架空（地図の中心だけ実在の地域の座標）。先方のシステムは見ておらず、再現もしていない。

- 着手: 2026-09-24 23:49（最初のコミット 23:52:10）。完成の時刻は下の「経過」を参照（`git log` の時刻であり、作業時間そのものではない）

## 動かし方（Docker のみ。ローカルに PHP は不要）
```bash
cp .env.example .env        # 値を自分のものに直す（.env はコミットしない）
docker compose up -d --build
```
- 地図: http://localhost:8080/ ／ 管理画面: http://localhost:8080/admin/ （ユーザー名・パスワードは `.env` の `ADMIN_USERNAME` / `ADMIN_PASSWORD`）
- **ポートは 8080 固定**（Google のキーのリファラ制限が `http://localhost:8080/*` のため）
- 初回起動で、テーブル作成 → 架空のホテル3件と施設 → 管理者 → モックの取り込み、まで自動で行う
- 取り込みだけ再実行: `docker compose exec web php scripts/import.php`
- テスト: `docker compose --profile test up -d --build web_test` → `docker compose --profile test exec -T web_test php tests/run_all.php`
- わざと壊す確認: `python tests/mutation_check.py`（コードのコピーを壊して、テストが赤になることを確かめる）

## 作ったもの
| 機能 | 内容 |
|---|---|
| 地図ページ | DB の施設をマーカーで表示。ホテルの切り替え・カテゴリの絞り込み・マーカーを押すと情報ウィンドウ。**API の件数と画面のマーカー数を並べて表示**（不一致なら警告） |
| 管理画面 | ログイン（パスワードは `password_hash`）／ホテルごとの設定（地図の中心・ズーム・表示するカテゴリ）／施設の追加・編集・削除 |
| セキュリティ | 未ログインは弾く／POST は CSRF トークン必須／SQL は全てプレースホルダ（PDO）／出力は HTML エスケープ／ログイン時にセッションIDを作り直す／クッキーは HttpOnly・SameSite=Lax |
| 取り込み | ローカルのモック JSON（`data/mock_facilities.json`・架空）から施設を取り込む。取得元＋外部ID で**冪等**。不正な行はスキップし、DB（`import_skipped`）と `logs/import_skipped.log` に理由を残す |
| 地図アダプタ | `public/assets/js/adapters/`。**Leaflet + OpenStreetMap**（必須・キー不要）と **Google Maps**（キーがあるときだけ有効） |
| 設計提案メモ | `docs/DESIGN_PROPOSALS.md`（キーの制限と費用／座標の保存方針／スキーマと AI 検索の差し込み口。出典つき・未確認は明記） |

## 実測した数字（2026-09-25・Docker 上で実行）
- **マーカー数 = API の件数 = DB の表示対象の件数**（Leaflet 版・ブラウザで確認）: **京都 8・札幌 4 はブラウザで、画面のマーカー数まで確認**（札幌は DB に5件あるが、「交通」を表示しない設定なので1件は出ない）。**那覇 5 は API の件数だけを curl で確認（画面のマーカー数は未確認）**。カテゴリを素早く切り替える操作を10回繰り返しても、京都の画面・API・DB が一致
- **取り込み**: 1回目 追加10・スキップ7 → 2回目（同じデータ）**追加0・更新0・変更なし10**・スキップ7（施設は計18件のまま）: `docs/evidence/import_output.txt`（DB の `import_runs` の実出力）
- **テスト 27件合格**（規則の単体 12＋HTTP 15。実際にリクエストを送って、未ログイン・CSRF・SQL の特殊文字・XSS・API の異常系を確認）: `docs/evidence/test_output.txt`
- **わざと壊した7か所すべてで、テストが赤**（認可／CSRF／冪等／SQL のプレースホルダ／HTML エスケープ／セッションID／表示カテゴリ）: `docs/evidence/mutation_check_output.txt`
- 見つけて直した不具合: カテゴリを素早く切り替えると、古い応答が後から届いて表示を上書きした（ブラウザで再現→修正→10回連続で一致を確認）

## Google 版の状態: **未検証**
- Google Maps のアダプタは書いたが、**API キーで実際に動かしていない。動いたとは書かない。**
- キーが無い間は、地図の選択肢に「Google Maps（APIキー未設定・未検証）」が出て、選べない。
- **キーを入れるとき（オーナーの作業。キーはチャットに貼らない）**: `.env` の `GOOGLE_MAPS_API_KEY` に自分で入れて `docker compose up -d`。キーには **HTTP リファラ制限（`http://localhost:8080/*` と `http://127.0.0.1:8080/*`）と API 制限（Maps JavaScript API のみ）**を付ける。予算アラートとクォータの設定は `docs/DESIGN_PROPOSALS.md` §1。
- 動かしたら、マーカー数・情報ウィンドウ・エラー時の挙動（キー制限・請求先・API 未有効化の切り分け）を実測して、ここに「検証済み」と書く。

## 作っていないもの・未検証の点
- 先方の β 版の再現／AI 検索・AI コンシェルジュの実装（設計メモで差し込み口を示しただけ）／実在のホテル・実在サービスの API の利用
- フレームワークは使っていない（素の PHP + PDO）。Composer・PHPUnit も未使用（自作の小さなテスト実行スクリプト）
- ブルートフォース対策（ログインの試行回数の制限）・パスワード変更・複数の管理者の権限分け・監査ログ・CSP ヘッダー・HTTPS（ローカルの http のみ）は**未実装**
- 外部で削除された施設の扱い（論理削除）は未実装（設計メモに記載）
- 取り込みは、管理画面で手編集した「取り込み由来の行」を、次の取り込みで上書きする（手編集を保護する仕様は無い）
- ログインのセッションに有効期限の設定は無い（ブラウザを閉じるまで）。`docker-compose.yml` は 8080・8081 を全インターフェースに公開するので、`.env` の管理者パスワードは必ず自分の値にする
- ブラウザでの画面の確認は、内蔵ブラウザでの Leaflet 版のみ。他のブラウザ・スマホ実機は未確認。管理画面の見た目の目視は未実施
- Google の利用規約（緯度経度の保存期間・Google 以外の地図への表示）は、規約本文を取得できず**未確認**

## 構成
`src/`（Db・Repo・Validator・Auth・Csrf・Importer・View）／`public/`（地図ページ・API・管理画面・アダプタ）／`db/`（スキーマ・初期データ）／`scripts/`（起動・取り込み・テスト用DBの初期化）／`tests/`／`docs/`。Leaflet 1.9.4（BSD-2-Clause）を `public/assets/vendor/leaflet/` に同梱している。

## 経過（`git log`）
`git log --format='%h %ad %s' --date=iso` を参照。

# アフィリエイトサイト × 2(WordPress)

| サイト | 子テーマ | ローカルURL |
|---|---|---|
| 占いサイト | `sites/uranai/themes/uranai-child` | http://localhost:8081 |
| アダルトサイト | `sites/adult/themes/adult-child` | http://localhost:8082 |

## 構成
```
shared/
  mu-plugins/affiliate-toolkit.php   2サイト共通: PR表記・/go/リンク・ボタン・ランキング・比較表
  themes/affiliate-base/             共通の親テーマ(軽量・レスポンシブ)
sites/
  uranai/themes/uranai-child/        占いサイト用の子テーマ(紫×金)
  adult/themes/adult-child/          アダルトサイト用の子テーマ(ダーク・年齢確認ゲート)
  uranai/plugins/uranai-tools/       占い機能(12星座ランキング・九星気学診断)
  adult/plugins/fanza-connector/     FANZA API連携(イチオシ女優・作品一覧・検索)
docs/                                各サイトのコンテンツ計画
docker-compose.yml                   ローカル確認用
```

## ローカルで確認する
```sh
docker compose up -d
```
1. http://localhost:8081 と http://localhost:8082 で WordPress の初期設定をする
2. 管理画面 → 外観 → テーマ で、それぞれ `Uranai Child` / `Adult Child` を有効化
3. 設定 → パーマリンク を「投稿名」にする

## 本番(レンタルサーバー)への設置
各サイトの WordPress に以下をアップロードする。
- `shared/mu-plugins/affiliate-toolkit.php` → `wp-content/mu-plugins/`
- `shared/themes/affiliate-base/` → `wp-content/themes/`
- 対応する子テーマ → `wp-content/themes/`

## 占いサイト: 12星座ランキング・九星気学
プラグイン `Uranai Tools` を有効化すると、トップに「今日の12星座ランキング」→「九星気学の診断」→「電話占いランキング」の順で並ぶ。

- **12星座ランキング** `[zodiac_ranking]`: 毎日0時に自動更新。日付から決まる計算なので、同じ日は誰が見ても同じ順位。
  運勢文・開運アドバイス・ラッキーカラー/アイテムは `data.php` の文例から組み合わせる(文例を増やすほどバリエーションが増える)。
- **九星気学** `[kyusei_form]`: 生年月日 → 本命星・月命星・性格・今年/今月の運勢(年盤・月盤での位置)・相性の良い星・今日の星座順位。
  立春・節入りで年/月を区切る。結果URL(`?birth=`)は検索エンジンにインデックスさせない。
- 診断結果の下にアップセル枠(現在は電話占いへの送客)。手相・定期購読ができたら `ut_kyusei_upsell_html` フィルタで差し替える。
- WordPress の 設定 → 一般 → タイムゾーン を「東京」にしておくこと(ランキングの日付切り替えに使用)。

## アダルトサイト: FANZA連携
1. DMMアフィリエイトに登録し、API ID と API用アフィリエイトID(末尾 -990〜-999)を取得
2. プラグイン `FANZA Connector` を有効化 → 設定 → FANZA連携 に API ID / アフィリエイトID を入力
3. 同じ画面の「イチオシ女優」に女優IDを最大8人分(推奨5人)入力 → トップ最上部に宣材写真・コメント・代表作が並ぶ
   - 女優IDは同じ画面の「女優IDを調べる」で名前から検索できる
   - 写真・表示名・おすすめ作品は任意で上書き可。行を空にすれば非表示。いつでも差し替え可能
4. トップには続けて「人気の作品」「新着作品」が自動表示される。ヘッダー検索は FANZA の作品も検索する

記事中でも使えるショートコード:
```
[fanza_featured]
[fanza_items title="新着作品" sort="date" hits="8"]
[fanza_items actress_id="12345" sort="rank" hits="4"]
[fanza_items keyword="キーワード" hits="8"]
```
API の結果は6時間キャッシュされ(設定で変更可)、API障害時は前回の結果を表示する。

### 今日の値下げ・割引率ランキング・終了間近
- 毎朝5時(サイトのタイムゾーン)に WP-Cron で FANZA の人気作品(1ページ100件×設定ページ数、既定5)と新作1ページを取得し、価格を日付付きで記録する(テーブル `{prefix}fz_prices` / `{prefix}fz_items`、60日分保持)
- 「今日の値下げ」= 各作品の前回記録より安くなったもの(最初の取得の翌日から表示)/「割引率ランキング」= 定価に対する割引率順 /「終了間近」= セール終了日時が近い順
- 設定 → FANZA連携 の下部に、最終取得日時・記録件数・次回の自動取得と「価格を今すぐ取得」ボタン
- アクセスの少ないサイトでは WP-Cron が遅れることがあるため、本番ではサーバーの cron から `wp-cron.php` を定期実行するのが確実
```
[fanza_price_drops hits="8"]
[fanza_discount_ranking hits="8"]
[fanza_ending_soon hits="8"]
```
トップの並び: イチオシ女優 → 今日の値下げ → 終了間近 → 人気の作品 → 新着作品

## アフィリエイトリンクの管理
リンクは子テーマの `affiliate-links.json` で一元管理する。記事中ではショートコードを使う。

```
[aff_ranking slugs="uranai-a,uranai-b,uranai-c"]   ランキング
[aff_table slugs="uranai-a,uranai-b"]              比較表
[aff_button slug="uranai-a"]                       CTAボタン
[aff_button slug="uranai-a" label="無料で試す"]     ラベル変更
```

- リンクは `/go/{slug}/` 経由でリダイレクトされる。ASP が決まったら JSON の `url` を差し替えるだけで全記事に反映される。
- `example.com` の URL は仮置き扱いで、ログイン中のみ「URL未設定」の警告と、管理バーに件数が表示される。
- 投稿にはPR表記が自動で入る(ステマ規制対応)。不要な投稿はカスタムフィールド `atk_no_pr` に `1` を設定する。

## 次にやること
- [ ] ASP に登録して `affiliate-links.json` の URL・料金・特典を埋める(アダルトは専用ASPが必要。`docs/content-plan-adult.md` 参照)
- [ ] ドメイン・サーバーを決める(アダルト可のサーバーか確認)
- [ ] 固定ページを作成: 運営者情報 / プライバシーポリシー / お問い合わせ(原稿は [`docs/legal/`](docs/legal/README.md))
- [ ] `docs/` のコンテンツ計画に沿って最初の記事を書く

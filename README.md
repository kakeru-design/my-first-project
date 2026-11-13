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
- [ ] 固定ページを作成: 運営者情報 / プライバシーポリシー / お問い合わせ
- [ ] `docs/` のコンテンツ計画に沿って最初の記事を書く

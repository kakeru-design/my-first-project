# インフラ選定レポート(レンタルサーバー・ドメイン)

- 作成: Aチーム / 調査日: 2026-11-13
- 対象: 占いサイト(WordPress + Stripe定期購読予定)/アダルトサイト(FANZA APIによるセール情報、WP-Cronで毎日価格記録予定)
- 調査方法: Web検索で各社の公式FAQ・利用規約・料金ページの記載を確認した。**今回の調査環境では各社サイト本文を直接開けず(ネットワーク制限)、検索エンジン経由で得た公式ページの抜粋に頼っている**。条文番号や金額は、契約前に必ず各社の公式ページで原文を確認すること。
- 料金はキャンペーンや改定で頻繁に変わる。本書の金額は「目安」として扱うこと。

---

## 1. 結論

### おすすめのサーバー構成: 「アダルトだけ別サーバー」案

| サイト | サーバー | 理由 |
|---|---|---|
| 占いサイト | **エックスサーバー スタンダード** | 安定性・サポート・cron・PHP8・自動バックアップ(14日分)・独自ドメイン永久無料特典。アダルトは規約で不可なので、占い専用にする |
| アダルトサイト | **シン・レンタルサーバー ベーシック**(代替案: mixhost スタンダード) | 公式FAQで「アダルトサイトの運営は可能(無修正・児童ポルノ等は禁止)」と明言。cron設定・自動バックアップ(14日分)・マルチドメイン無制限 |

理由の要点:
- アダルトサイトを置けるサーバーは限られる(シン・レンタルサーバー、mixhost、カラフルボックスのアダルト対応プラン)。主要どころのエックスサーバー、ConoHa WING、ロリポップは**規約上不可**。
- 占いサイトは将来Stripeの定期購読(会員ページ)を載せる。決済・会員情報を持つサイトを、アダルトサイトと同じ契約に置くと、アダルト側が規約違反扱い・通報・負荷超過などで止められたときに**占いサイトと会員の決済導線まで巻き添えで止まる**。分けておけば影響は片方だけで済む。
- 費用差は月1,000〜1,500円程度(後述)。収益化前でも許容できる範囲と判断した。
- シン・レンタルサーバーはエックスサーバー社グループのサービス。運営会社まで分けたい場合は、アダルトを **mixhost**(公式ヘルプで「日本国内で合法なアダルトサイトは運営可」)にする。

### おすすめのレジストラ
- 占いサイト: **エックスサーバーの「独自ドメイン永久無料」特典**で取得(サーバー契約中は更新料0円)。特典の対象外なら Xserverドメイン。
- アダルトサイト: **Cloudflare Registrar**(原価販売・WHOIS情報の秘匿が標準)。代替は **バリュードメイン**。
  - お名前.com・ムームードメイン・Xserverドメインは、アダルト用途について規約上の懸念がある(第3章)。アダルトのドメインはサーバーと別会社で管理するのが無難。

### ドメイン名候補(6つ、すべて空き状況未確認)

| サイト | 候補 | 理由(1行) |
|---|---|---|
| 占い | `seizarank.com` | 「星座ランキング」が名前だけで伝わり、短い |
| 占い | `unsei-biyori.com` | 「運勢日和」。毎日見に来る運勢サイトの印象が出る |
| 占い | `kyusei-note.jp` | 「九星ノート」。九星気学の診断が伝わり、.jp で国内向けの信頼感 |
| アダルト | `nesage-av.com` | 「値下げ」+作品ジャンルが直接伝わる |
| アダルト | `r18-sale.net` | 短く、成人向けセール情報だと一目で分かる |
| アダルト | `yasune-watch.com` | 「安値ウォッチ」。毎日の価格記録・値下げ監視の機能と合う |

---

## 2. レンタルサーバー比較

### 2-1. アダルトサイト設置の可否(最重要)

| サーバー | 判定 | 根拠(公式ページと要旨) |
|---|---|---|
| エックスサーバー | **禁止** | FAQ「コンテンツに関して制限はありますか?」 https://www.xserver.ne.jp/support/faq/dont_contents_stint.php : ポルノサイトやそれに類するものは禁止、発覚時はサービス停止あり。風俗店・アダルトグッズ・同人系の運用自体は禁止にあたらないが、**性行為やそれを連想させる画像・動画の掲載は禁止**。利用規約 https://www.xserver.ne.jp/rule/rule.php : 猥褻な表現を含む情報の掲載を禁止。→ 成人向け作品のパッケージ画像を一覧表示するサイトは該当する可能性が高く、不可と判断 |
| ConoHa WING | **禁止** | ConoHa会員規約 https://www.conoha.jp/agreement/ 第21条1項16号(わいせつ・児童ポルノ等の画像等の送信・表示)、**同項24号「性的な好奇心を煽る情報を掲載する行為」**を禁止。サポートFAQ https://support.conoha.jp/common/faq/conoha-q/ : アダルト・出会い系での利用について、確認次第サービスを制限 |
| シン・レンタルサーバー | **許可(条件付き)** | FAQ「アダルトサイトの運営は可能ですか?」 https://www.shin-server.jp/support/faq/dont_adult.php : 「はい、可能です。ただし、無修正・児童ポルノなど、法令又は公序良俗に反する内容は禁止」 |
| ロリポップ | **禁止** | FAQ「アダルトサイトは設置できますか」 https://support.lolipop.jp/hc/ja/articles/360048391734 : アダルトサイトは設置不可、**アダルトサイトへのリンクも不可**。禁止事項 https://lolipop.jp/terms/prohibition/ |
| さくらのレンタルサーバ | **未確認** | ご利用上の注意 https://rs.sakura.ad.jp/terms/ : 公序良俗に反する写真(わいせつ・児童ポルノなど)の掲載は禁止。ただし「成人向けサイト全般」を許可・禁止する明文は今回確認できなかった。設置する場合は事前に公式へ確認が必要 |
| mixhost | **許可(条件付き)** | ヘルプ「アダルトサイトの運営について」 https://help.mixhost.jp/articles/115003736031 : 日本国内で合法なアダルトサイトは運営可、無修正など違法性の高いサイトは不可。利用規約 https://mixhost.jp/tos/ 第16条(法令違反・公序良俗違反の禁止)。アダルトサーバー利用時は独自ドメインのネームサーバーをmixhost指定に変更する必要あり |
| カラフルボックス | **許可(条件付き)** | FAQ「アダルトサイトは設置できますか?」 https://help.colorfulbox.jp/faq/adult-permission/ : **アダルト対応サーバーのプランに限り**設置可。無修正・児童ポルノは禁止。アダルト用プランは別サーバー・別IP、通常プランとの相互変更は不可 https://www.colorfulbox.jp/adult/ |
| (参考)お名前.com レンタルサーバー | **禁止** | ヘルプ https://help.onamae.com/answer/8806 : アダルトサイトの運営は禁止 |

> 注意: 「許可」のサーバーでも、わいせつ物(無修正等)・児童ポルノは当然不可。FANZAの公式素材であっても、表示方法(年齢確認ゲート、サンプル画像の扱い)はDMMアフィリエイトの規約にも従うこと(本調査の範囲外)。

### 2-2. 機能・料金比較

金額は税込・月額換算の目安。キャンペーン価格は終了済みのものが多いため、通常価格・更新価格を重視して記載。

| サーバー(代表プラン) | 月額の目安 | 初期費用 | cron | PHP | 自動バックアップ | マルチドメイン |
|---|---|---|---|---|---|---|
| エックスサーバー スタンダード | 990円(36ヶ月)〜1,320円(3ヶ月)※2026/10〜11のキャンペーン表示時の値。現行は要確認 | 0円 | 可 | PHP8/7/5 | 1日1回、Web・DBとも14日分、無料 | 可(数は要確認) |
| ConoHa WING ベーシック(WINGパック) | 通常1,452円。キャンペーン時は36ヶ月で659円の表示あり(2026/11/5終了) | 0円 | 可(ジョブスケジューラー) | 要確認 | 1日1回、14日分、無料 | 可(数は要確認) |
| シン・レンタルサーバー ベーシック | 1,078円(36ヶ月)/1,232円(12ヶ月)/1,386円(3・6ヶ月)※2025/9改定後 | 0円 | 可(サーバーパネル「Cron設定」) | PHP8系(詳細は要確認) | 1日1回、Web・DBとも14日分 | 無制限 |
| ロリポップ ハイスピード | 660円(36ヶ月)/1,045円(12ヶ月)/1,430円(1ヶ月)※2026/1改定後 | 要確認 | 可(10件、1分間隔) | 要確認 | 無料(7世代は有料オプション月440円) | 可(数は要確認) |
| さくらのレンタルサーバ スタンダード | 500円(36ヶ月一括18,000円)〜660円(毎月払い) | 0円 | 可 | 要確認 | バックアップ&ステージング(最大8世代、無料) | 200個 |
| mixhost スタンダード | 初回968円(1年)→更新1,518円。別ページで初回913円・更新1,408円(2年)の表示もあり食い違い | 無料(古い告知の記載) | 可(cPanel Cronジョブ) | PHP 8.3まで選択可(ドメイン単位) | 1日1回、外部サーバーにファイル・DB、14日分 | 可(数は要確認) |
| カラフルボックス BOX2(アダルト対応) | 初回528円(36ヶ月・キャンペーン)→更新1,320円 | 要確認 | 可(cPanel Cronジョブ) | PHP 8.0〜8.3(ドメイン単位) | 通常プランは14日分無料。アダルトプランの仕様は未確認 | 可(数は要確認) |

共通事項:
- **WordPress簡単インストール・無料SSL**: 上記各社とも一般に提供している(エックスサーバー「WordPressクイックスタート」、ConoHa WING「かんたんセットアップ」、cPanel系はインストーラー等)。ただし本調査では各社ページで個別に確認できていないため、申込前に確認すること。
- **表示速度**: 各社とも「国内最速」等をうたうが、自社調べ・計測条件が異なり比較の根拠にならない。今回の調査では中立なベンチマークを確認できなかった(未確認)。WP-Cronの日次処理やFANZA APIからの取得は軽い処理なので、上記どのプランでも性能面で問題になる可能性は低い。
- **サーバーcron**: WP-Cronはアクセスがないと動かないため、アダルトサイトの毎日の価格記録は、サーバーcronで `wp-cron.php` を叩く(または WP-CLI の `wp cron event run --due-now`)構成が確実。上記の「許可」3社はいずれもcronを設定できる。

### 2-3. 「同じサーバーにまとめる」案と「アダルトだけ別」案の比較

| 観点 | まとめる案(例: シン・レンタルサーバー1契約に2サイト) | 分ける案(占い: エックスサーバー、アダルト: シン or mixhost) |
|---|---|---|
| 月額 | 約1,100〜1,400円 | 約2,100〜2,900円 |
| 管理の手間 | 管理画面1つ、バックアップ・PHP更新も一括 | 管理画面2つ |
| 規約違反時の影響 | アダルト側の問題(通報・誤判定・負荷)で**アカウントごと停止**すると占いサイトも止まる | 影響はアダルト側だけ |
| 決済・会員サイトへの影響 | Stripe審査・決済代行の審査で、同一IP・同一運営のアダルトサイトが見られるリスク | 占い側を独立させられる |
| ブランド・信頼性 | 同一IP上にアダルトサイトがあることを気にする利用者・提携先がありうる(実害の程度は未確認) | 気にしなくてよい |
| 負荷の影響 | アダルト側の日次取得・アクセス集中が占い側の速度に影響しうる | 独立 |

**アダルト可サーバーで占いも運用するメリット**: 安い、管理が1か所。
**デメリット**: 巻き添え停止のリスク、Stripe定期購読を載せた後の決済・会員への影響。占いサイトは会員課金を予定しているため、**分ける案を推奨**。費用を最優先する立ち上げ初期だけ1契約にまとめ、Stripe導入前に占いを移す、という段階的な案も可能(ただし移転の手間が後で発生する)。

---

## 3. ドメイン・レジストラ比較

| レジストラ | .com 取得/更新の目安 | WHOIS情報公開代行 | アダルトサイトでの利用 | サーバーとのセット特典 |
|---|---|---|---|---|
| お名前.com | 更新1,287円〜(比較記事、2026/10時点。保有数等で変動) | あり(無料かは要確認) | **懸念あり**。同社レンタルサーバーはアダルト禁止 https://help.onamae.com/answer/8806 。ドメイン登録規約 https://www.onamae.com/agreement/a_ag/registration.html には同社判断で登録取消・使用停止できる定めがあるが、アダルト用途の明文は未確認 | お名前.comサーバーとのセットあり(アダルト不可) |
| ムームードメイン | 取得750円/更新1,728円(公式価格表)+サービス維持調整費 | あり(無料かは未確認) | **懸念あり**。Webサイトビルダー利用特約で性風俗・わいせつ系コンテンツとそのリンクを禁止 https://muumuu-domain.com/?mode=website_builder_contract 。ドメイン単体の利用規約 https://muumuu-domain.com/?mode=kiyaku でのアダルト扱いは未確認。.xxx は商標保護目的のみでサイト運用不可 | ムームーサーバー同時契約で取得・更新0円の特典あり |
| Xserverドメイン | 更新1,721円→2026/11/9から1,750円の予定(比較記事) | あり(無料かは要確認) | **懸念あり**。XServerアカウント利用規約 https://www.xdomain.ne.jp/rule/rule.php 第22条で「猥褻な表現を含む情報」「未成年者にとって有害と認められる情報の掲載」を禁止(検索結果の抜粋より) | エックスサーバー/シン・レンタルサーバーの「独自ドメイン永久無料」特典(サーバー契約中は無料) |
| Cloudflare Registrar | .com 11.17ドル(登録・更新・移管同額、原価販売) https://pricing.registrar.cloudflare.com/ | **標準でWHOIS秘匿**(無料) https://developers.cloudflare.com/registrar/ | **公式の明文は未確認**。公式ブログ https://blog.cloudflare.com/cloudflares-abuse-policies-and-approach/ では違法コンテンツ(児童搾取等)を対象とする方針。コミュニティでは「合法な成人向けコンテンツは制限されない」との回答が多いが非公式 | なし。**ネームサーバーはCloudflare必須**(mixhostのアダルトサーバーのように指定NS必須のサーバーとは相性が悪い)。.jp は扱いなし |
| バリュードメイン | 要確認 | あり(要確認) | **明確な禁止条項は見当たらない**。会員規約 https://www.value-domain.com/terms/ 第17条で青少年有害情報について18歳以上のみ閲覧できる措置等を求める(=成人向けを前提とした運用ルールがある) | 同社サーバー(コアサーバー等)とのセットあり(詳細未確認) |

### TLDの選び方

| TLD | SEO | 信頼性 | 更新料 | アダルト用途 |
|---|---|---|---|---|
| .com | 不利なし | 最も一般的で高い | 約1,300〜1,750円/年(各社) | 問題なし。第一候補 |
| .net | 不利なし | .comに次ぐ | .comと同程度 | 問題なし |
| .jp | 国別ドメインとして日本向けの強い地域シグナル(Google公式) | 国内で信頼感が高い。取得には日本国内の住所が必要 | 3,000円前後〜と高め(各社要確認) | 規則上の明文は未確認。登録者の住所要件があり、個人のアダルト用途には勧めにくい |
| .info / 新gTLD(.site, .online, .xyz 等) | Googleは新gTLDを.com等と同等に扱い、TLDのキーワードに有利不利はないと説明(2015年の公式ブログ) | スパムが多いTLDとして警戒されやすく、メール到達率やユーザーの信頼で不利になりがち | **初年度が安く更新が高い**ものが多い | 安いTLDはスパム扱いされやすく非推奨 |
| .xxx / .adult / .porn | 不利はないが利点もない | 用途は明確 | 高額 | ムームードメインでは.xxxは運用不可。フィルタで一括ブロックされやすい |

出典: Google「Google's handling of new top level domains」(2015年、古い情報) https://developers.google.com/search/blog/2015/07/googles-handling-of-new-top-level 、「Managing Multi-Regional and Multilingual Sites」 https://developers.google.com/search/docs/specialty/international/managing-multi-regional-sites

**結論**: 両サイトとも `.com` を基本とする。占いサイトは国内向けの信頼感を重視するなら `.jp` も候補。アダルトは `.com` / `.net`。

---

## 4. ドメイン名候補

> 空き状況について: 今回の調査環境ではWHOIS/RDAPや登録サービスの検索ページに接続できなかった(ネットワーク制限)。**6つとも「空き状況未確認」**。Web検索ではこれらの文字列を使った既存サイトは見つからなかったが、未使用でも登録済みのことはよくある。取得前に各レジストラの検索画面で確認すること。

### 占いサイト(参考サイト koyomi-hoshi.com の「koyomi」「hoshi」は使わない)

| 候補 | TLD | 理由 | 空き状況 |
|---|---|---|---|
| `seizarank.com` | .com | 「星座ランキング」が名前で伝わる。9文字で短い | 空き状況未確認 |
| `unsei-biyori.com` | .com | 「運勢日和」。毎日の運勢を見に来るサイトの印象で覚えやすい | 空き状況未確認 |
| `kyusei-note.jp` | .jp | 「九星ノート」。九星気学の診断機能が伝わり、.jp で国内向けの信頼感 | 空き状況未確認 |

### アダルトサイト(参考サイト videofanz.com の「video」「fanz」、および「FANZA」「DMM」は商標の観点から使わない)

| 候補 | TLD | 理由 | 空き状況 |
|---|---|---|---|
| `nesage-av.com` | .com | 「値下げ」+ジャンル名で内容が直接伝わる | 空き状況未確認 |
| `r18-sale.net` | .net | 短く、成人向けのセール情報サイトだと一目で分かる | 空き状況未確認 |
| `yasune-watch.com` | .com | 「安値ウォッチ」。毎日の価格記録・値下げ監視機能と合う | 空き状況未確認 |

補足: ハイフン入りは口頭で伝えにくい一方、ローマ字の区切りが読みやすくなる。ハイフン無し版(例: `unseibiyori.com`)が空いていれば、そちらと比べて選ぶとよい。

---

## 5. 次のアクション(依頼者の手順)

1. **規約の原文確認**: 第2章の「許可」サーバー(シン・レンタルサーバー/mixhost)の最新の利用規約・FAQを公式サイトで読み、成人向けの作品パッケージ画像を一覧表示する用途で問題ないか確認する。不安があれば契約前にサポートへ問い合わせる。
2. **シン・レンタルサーバーのアカウント規約の確認**: シン・レンタルサーバーがエックスサーバー社の共通アカウント(XServerアカウント)の規約に従う場合、同規約の「猥褻な表現」「未成年者に有害な情報」の禁止条項との関係を確認する(今回未確認)。矛盾が解消できなければアダルトは mixhost にする。
3. **ドメインの空き確認**: 第4章の候補を、Cloudflare Registrar(アダルト)とエックスサーバーのドメイン検索(占い)で確認し、各サイト1つに決める。
4. **占いサイトのサーバー契約**: エックスサーバー スタンダード(12ヶ月以上が割安)を契約し、独自ドメイン永久無料特典で占いサイトのドメインを取得する。WordPressクイックスタートでインストール、無料SSLを有効化、PHPを8.x に設定。
5. **アダルトサイトのドメイン取得**: Cloudflare Registrar で取得(WHOIS秘匿は標準)。DNSはCloudflareのまま、サーバーのIPをAレコードで指定する。mixhost を選んだ場合は、ネームサーバーを mixhost 指定にする必要があるため、Cloudflare Registrar ではなくバリュードメイン等で取得する。
6. **アダルトサイトのサーバー契約**: シン・レンタルサーバー ベーシック(または mixhost スタンダード)を契約。WordPressをインストールし、SSL・PHP 8.x を設定。
7. **cron設定**: 両サイトで `wp-config.php` に `define('DISABLE_WP_CRON', true);` を入れ、サーバーcronで5〜15分ごとに `wp-cron.php` を実行する。アダルトサイトの毎日の価格取得はこの仕組みで確実に動かす。
8. **バックアップ確認**: サーバーの自動バックアップ(14日分)に加え、価格履歴DBは月1回程度ダウンロード保存する(14日を超える履歴の保険)。
9. **Stripe導入前の確認**: Stripeの禁止・制限業種リストで占い(サイキック/占術サービス)の扱いを確認する(本調査では未確認)。アダルト側にはStripeを使わない。

---

## 6. 出典一覧

※いずれも2026-11-13時点の検索で参照。サイト本文を直接取得できず、検索結果の抜粋に基づく箇所がある。

### レンタルサーバー
- エックスサーバー FAQ「コンテンツに関して制限はありますか?」 https://www.xserver.ne.jp/support/faq/dont_contents_stint.php
- エックスサーバー 利用規約 https://www.xserver.ne.jp/rule/rule.php
- エックスサーバー 料金 https://www.xserver.ne.jp/price/
- エックスサーバー 自動バックアップ https://www.xserver.ne.jp/functions/service_backup.php
- ConoHa会員規約 https://www.conoha.jp/agreement/
- ConoHaサポート「ConoHaについて」 https://support.conoha.jp/common/faq/conoha-q/
- ConoHa WING 料金 https://www.conoha.jp/pricing/
- ConoHa WING 自動バックアップ https://www.conoha.jp/wing/function/autobackup/
- シン・レンタルサーバー FAQ「アダルトサイトの運営は可能ですか?」 https://www.shin-server.jp/support/faq/dont_adult.php
- シン・レンタルサーバー 料金 https://www.shin-server.jp/service/price.php
- シン・レンタルサーバー 料金改定のお知らせ(2025/9/1実施) https://www.shin-server.jp/news/detail.php?view_id=1574
- シン・レンタルサーバー 機能一覧 https://www.shin-server.jp/service/functions.php
- シン・レンタルサーバー Cron設定 https://www.shin-server.jp/support/manual/man_program_cron.php
- シン・レンタルサーバー 独自ドメイン永久無料特典 https://www.shin-server.jp/support/manual/man_order_present_domain.php
- ロリポップ FAQ「アダルトサイトは設置できますか」 https://support.lolipop.jp/hc/ja/articles/360048391734
- ロリポップ 禁止事項 https://lolipop.jp/terms/prohibition/
- ロリポップ 料金 https://lolipop.jp/pricing/
- さくらのレンタルサーバ ご利用上の注意 https://rs.sakura.ad.jp/terms/
- さくらのレンタルサーバ スタンダード https://rs.sakura.ad.jp/plan/standard/
- さくらのレンタルサーバ バックアップ&ステージング https://rs.sakura.ad.jp/function/backup-staging/
- mixhost ヘルプ「アダルトサイトの運営について」 https://help.mixhost.jp/articles/115003736031
- mixhost 利用規約 https://mixhost.jp/tos/
- mixhost レンタルサーバー(料金) https://mixhost.jp/hosting/web/
- mixhost cPanelからcronを設定する方法 https://help.mixhost.jp/hc/ja/articles/115003740612
- mixhost 「PHP 8.3」導入開始のお知らせ https://mixhost.jp/news/1632
- カラフルボックス FAQ「アダルトサイトは設置できますか?」 https://help.colorfulbox.jp/faq/adult-permission/
- カラフルボックス アダルトレンタルサーバー https://www.colorfulbox.jp/adult/
- カラフルボックス 料金 https://www.colorfulbox.jp/price/
- カラフルボックス cronの設定方法 https://help.colorfulbox.jp/article/cron-conf/
- カラフルボックス 仕様 https://www.colorfulbox.jp/spec/
- お名前.com ヘルプ「アダルト・性風俗系サイトは運営できますか?」 https://help.onamae.com/answer/8806

### ドメイン
- お名前.com ドメイン登録規約 https://www.onamae.com/agreement/a_ag/registration.html
- お名前.com 更新料金 https://www.onamae.com/service/d-renew/price.html
- ムームードメイン 利用規約 https://muumuu-domain.com/?mode=kiyaku
- ムームードメイン Webサイトビルダー利用特約 https://muumuu-domain.com/?mode=website_builder_contract
- ムームードメイン .com https://muumuu-domain.com/domain/tld/com
- ムームードメイン 「.xxx」ドメインとは https://support.muumuu-domain.com/hc/ja/articles/360045803474
- Xserverドメイン 利用規約 https://www.xdomain.ne.jp/rule/rule.php
- Cloudflare Registrar 価格 https://pricing.registrar.cloudflare.com/
- Cloudflare Registrar ドキュメント https://developers.cloudflare.com/registrar/
- Cloudflare 公式ブログ(abuse方針) https://blog.cloudflare.com/cloudflares-abuse-policies-and-approach/
- バリュードメイン 利用規約 https://www.value-domain.com/terms/
- .com更新料金の比較記事(第三者、2026/10時点) https://monohikaku.com/?p=37685
- Google Search Central Blog「Google's handling of new top level domains」(2015) https://developers.google.com/search/blog/2015/07/googles-handling-of-new-top-level
- Google「Managing Multi-Regional and Multilingual Sites」 https://developers.google.com/search/docs/specialty/international/managing-multi-regional-sites

---

## 付記: 未確認事項のまとめ

- さくらのレンタルサーバのアダルトサイト可否(わいせつ禁止以外の明文なし)
- シン・レンタルサーバーが従う共通アカウント規約とアダルトFAQの関係
- Cloudflare Registrar の成人向けサイト利用に関する公式明文
- ムームードメイン・お名前.com・Xserverドメインのドメイン単体でのアダルト利用可否
- 各社の現行料金(キャンペーン終了後の金額)、WHOIS公開代行の無料/有料、ConoHa・ロリポップ・さくらのPHPバージョン詳細、マルチドメイン数の一部
- 表示速度の中立的な比較
- ドメイン候補6つの空き状況(WHOIS検索に接続できず)
- Stripeでの占いサービスの扱い

<?php
/**
 * SNS(X・Threads・Instagram)投稿文の作成(管理者専用)
 * ダッシュボードと「ツール → SNS投稿文」に、今日投稿する文章を表示する。
 * コピーボタンと、X・Threadsの投稿画面を文章入りで開くボタン付き。
 * 曜日ごとの「紹介投稿」(サイトのページ紹介)は X / Threads / Instagram の3種類を出す。
 */

defined( 'ABSPATH' ) || exit;

/** Xの文字数(日本語・絵文字は2、URLは23として数える。上限280) */
function ut_x_weight( $text ) {
	$n    = 0;
	$urls = preg_match_all( '#https?://\S+#u', $text );
	$rest = preg_replace( '#https?://\S+#u', '', $text );
	foreach ( preg_split( '//u', $rest, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
		$o  = mb_ord( $ch, 'UTF-8' );
		$n += ( $o <= 0x10FF || ( $o >= 0x2000 && $o <= 0x200D ) || ( $o >= 0x2010 && $o <= 0x201F ) || ( $o >= 0x2032 && $o <= 0x2037 ) ) ? 1 : 2;
	}
	return $n + 23 * $urls;
}

/**
 * 行の配列から投稿文を作る。280を超える場合は「省略可」の行(null以外のキー 'opt')から順に削る
 * @param array<int, string|array{opt: string}> $lines
 */
function ut_x_compose( array $lines ) {
	$build = fn( $ls ) => trim( preg_replace( "/\n{3,}/", "\n\n", implode( "\n", array_map( fn( $l ) => is_array( $l ) ? $l['opt'] : $l, $ls ) ) ) );
	$text  = $build( $lines );
	while ( ut_x_weight( $text ) > 280 ) {
		$idx = null;
		foreach ( $lines as $i => $l ) {
			if ( is_array( $l ) ) {
				$idx = $i;
			}
		}
		if ( null === $idx ) {
			break;
		}
		unset( $lines[ $idx ] );
		$text = $build( $lines );
	}
	return $text;
}

/** 文字数が多い文を、句点までで短くする */
function ut_x_short( $text, $max = 30 ) {
	$first = preg_split( '/(?<=。)/u', $text, 2 )[0];
	return mb_strlen( $first ) <= $max ? $first : mb_substr( $first, 0, $max - 1 ) . '…';
}

/** 今日投稿する文章の一覧 */
function ut_x_posts( ?DateTimeImmutable $today = null ) {
	$today = $today ?: ut_today();
	$day   = $today->format( 'n/j' ) . '(' . ut_weekday( $today ) . ')';
	$posts = array();

	// 1. 毎朝のランキング
	$r     = ut_zodiac_ranking( $today );
	$last  = $r[11];
	$posts[] = array(
		'label' => '毎朝のランキング(7時ごろ)',
		'text'  => ut_x_compose(
			array(
				'🌙' . $day . 'の12星座ランキング',
				'',
				'👑1位 ' . $r[0]['name'],
				array( 'opt' => '「' . ut_x_short( $r[0]['text'], 44 ) . '」' ),
				'🥈2位 ' . $r[1]['name'],
				'🥉3位 ' . $r[2]['name'],
				'',
				array( 'opt' => '12位の' . $last['name'] . 'さんは「' . ut_x_short( $last['advice'], 44 ) . '」' ),
				'あなたの星座は何位?👇',
				home_url( '/' ),
				'#今日の運勢 #星座占い',
			)
		),
	);

	// 2. 誕生日の人へ
	$b       = ut_birthday( (int) $today->format( 'n' ), (int) $today->format( 'j' ) );
	$posts[] = array(
		'label' => '今日が誕生日の人へ(昼ごろ)',
		'text'  => ut_x_compose(
			array(
				'🎂今日' . $today->format( 'n月j日' ) . 'がお誕生日の方、おめでとうございます',
				'',
				$today->format( 'n月j日' ) . '生まれは' . ut_zodiac_signs()[ $b['key'] ]['name'] . '。',
				array( 'opt' => ut_x_short( ut_birth_numbers()[ $b['num'] ]['trait'], 40 ) ),
				'',
				'性格・恋愛傾向・ラッキーカラーはこちら👇',
				ut_birthday_url( $b['m'], $b['d'] ),
				'#誕生日占い #星座占い',
			)
		),
	);

	// 3. 月曜は週間ランキング
	if ( '1' === $today->format( 'N' ) ) {
		$w       = ut_weekly_ranking( $today );
		$posts[] = array(
			'label' => '週間ランキング(月曜)',
			'text'  => ut_x_compose(
				array(
					'🗓今週(' . $w['monday']->format( 'n/j' ) . '〜)の12星座ランキング',
					'',
					'👑1位 ' . $w['list'][0]['name'] . '「' . $w['list'][0]['keyword'] . '」',
					'🥈2位 ' . $w['list'][1]['name'],
					'🥉3位 ' . $w['list'][2]['name'],
					'',
					'今週のキーワードとラッキーデーは👇',
					home_url( '/shukan/' ),
					'#今週の運勢 #星座占い',
				)
			),
		);
	}

	// 4. 新月・満月の前日と当日
	foreach ( ut_moon_calendar( $today, 1 ) as $m ) {
		$diff = (int) $today->setTime( 0, 0 )->diff( $m['dt']->setTime( 0, 0 ) )->format( '%r%a' );
		if ( $diff < 0 || $diff > 1 ) {
			continue;
		}
		$kind    = $m['full'] ? '満月' : '新月';
		$sign    = ut_zodiac_signs()[ $m['sign'] ]['name'];
		$theme   = ut_moon_themes()[ $m['sign'] ][ $m['full'] ? 1 : 0 ];
		$posts[] = array(
			'label' => $kind . '(' . ( $diff ? '前日' : '当日' ) . ')',
			'text'  => ut_x_compose(
				array(
					( $m['full'] ? '🌕' : '🌑' ) . ( $diff ? '明日' : '今日' ) . $m['dt']->format( 'n/j G:i' ) . 'ごろ、' . $sign . 'の' . $kind,
					'',
					'テーマは「' . $theme . '」',
					$m['full'] ? '手放したいことを書き出してみて🕯' : '叶えたい願い事を書き出してみて✍️',
					'',
					'新月・満月カレンダー👇',
					home_url( '/tsuki/' ),
					'#' . $kind . ' #星座占い',
				)
			),
		);
	}
	return $posts;
}

/** Threadsの投稿画面を文章入りで開くURL */
function ut_threads_intent( $text ) {
	return 'https://www.threads.net/intent/post?text=' . rawurlencode( $text );
}

/** 投稿文1つ分の表示(テキスト欄+コピー+投稿ボタン) */
function ut_post_box_html( $id, $label, $text, $limit_label, $buttons ) {
	$out  = '<div style="margin:0 0 14px">';
	$out .= '<p style="margin:0 0 4px"><strong>' . esc_html( $label ) . '</strong> <span style="color:#646970">' . esc_html( $limit_label ) . '</span></p>';
	$out .= '<textarea id="' . esc_attr( $id ) . '" rows="8" style="width:100%;font-size:13px" readonly>' . esc_textarea( $text ) . '</textarea>';
	$out .= '<p style="margin:6px 0 0;display:flex;gap:8px;flex-wrap:wrap">';
	$out .= '<button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById(\'' . esc_attr( $id ) . '\').value);this.textContent=\'コピーしました\'">コピー</button>';
	foreach ( $buttons as $b ) {
		$out .= '<a class="button' . ( 'X' === $b[0] ? ' button-primary' : '' ) . '" target="_blank" rel="noopener" href="' . esc_url( $b[1] ) . '">' . esc_html( $b[0] ) . 'で投稿する</a>';
	}
	return $out . '</p></div>';
}

function ut_x_posts_html() {
	$out = '<p style="margin-top:0">' . esc_html( ut_today()->format( 'Y年n月j日' ) ) . 'の投稿文です。XとThreadsは同じ本文で投稿できます。</p>';
	foreach ( ut_x_posts() as $i => $p ) {
		$w    = ut_x_weight( $p['text'] );
		$out .= ut_post_box_html(
			'ut-x-' . $i,
			$p['label'],
			$p['text'],
			'X ' . $w . '/280',
			array( array( 'X', 'https://x.com/intent/post?text=' . rawurlencode( $p['text'] ) ), array( 'Threads', ut_threads_intent( $p['text'] ) ) )
		);
	}
	$intro = ut_intro_post();
	if ( $intro ) {
		$out .= '<hr><h3 style="margin:12px 0 4px">紹介投稿(' . esc_html( $intro['label'] ) . ')</h3>';
		$out .= '<p style="margin:0 0 10px;color:#646970">曜日ごとに、サイトのページを1つ紹介します。' . ( $intro['pr'] ? '広告(Amazon)への誘導を含むため「#PR」付きです。' : '' ) . '</p>';
		$out .= ut_post_box_html( 'ut-intro-x', 'X用', $intro['x'], 'X ' . ut_x_weight( $intro['x'] ) . '/280', array( array( 'X', 'https://x.com/intent/post?text=' . rawurlencode( $intro['x'] ) ) ) );
		$out .= ut_post_box_html( 'ut-intro-th', 'Threads用', $intro['threads'], mb_strlen( $intro['threads'] ) . '/500文字', array( array( 'Threads', ut_threads_intent( $intro['threads'] ) ) ) );
		$out .= ut_post_box_html( 'ut-intro-ig', 'Instagram用キャプション', $intro['insta'], mb_strlen( $intro['insta'] ) . '文字(リンクはプロフィールから)', array() );
	}
	return $out;
}

add_action(
	'wp_dashboard_setup',
	function () {
		if ( current_user_can( 'edit_posts' ) ) {
			wp_add_dashboard_widget( 'ut_x_posts', '今日のSNS投稿文(月詠灯)', fn() => print( ut_x_posts_html() ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
);

add_action(
	'admin_menu',
	function () {
		add_management_page(
			'SNS投稿文',
			'SNS投稿文',
			'edit_posts',
			'ut-x-posts',
			fn() => print( '<div class="wrap"><h1>今日のSNS投稿文</h1>' . ut_x_posts_html() . '</div>' ) // phpcs:ignore WordPress.Security.EscapeOutput
		);
	}
);

/* ==================================================================
 * 紹介投稿(曜日ごとに、サイトのページを1つ紹介する)
 *   火=相性 / 水=九星気学の無料診断 / 木=開運グッズ(#PR) / 金=誕生日別の性格
 *   土=月の満ち欠け / 日=今日1位の星座ページ / 月=なし(週間ランキングがあるため)
 * X は ut_x_compose で280以内、Threads は500文字以内に収める。
 * ================================================================== */

/** Threads用:500文字を超えたら「省略可」の行から削る */
function ut_threads_compose( array $lines, $max = 500 ) {
	$build = fn( $ls ) => trim( preg_replace( "/\n{3,}/", "\n\n", implode( "\n", array_map( fn( $l ) => is_array( $l ) ? $l['opt'] : $l, $ls ) ) ) );
	$text  = $build( $lines );
	while ( mb_strlen( $text ) > $max ) {
		$idx = null;
		foreach ( $lines as $i => $l ) {
			if ( is_array( $l ) ) {
				$idx = $i;
			}
		}
		if ( null === $idx ) {
			break;
		}
		unset( $lines[ $idx ] );
		$text = $build( $lines );
	}
	return $text;
}

/** 指定日の正午(日本時間)の月齢(日) */
function ut_intro_moon_age( DateTimeImmutable $day ) {
	$jd = ( $day->setTime( 12, 0 )->getTimestamp() / 86400 ) + 2440587.5;
	$k  = floor( ( $jd - 2451550.09766 ) / 29.530588861 );
	while ( ut_moon_phase_jde( $k, false ) > $jd ) {
		--$k;
	}
	while ( ut_moon_phase_jde( $k + 1, false ) <= $jd ) {
		++$k;
	}
	return $jd - ut_moon_phase_jde( $k, false );
}

/** 月齢から、月の呼び名とひとこと */
function ut_intro_moon_phase( $age ) {
	if ( $age < 1.5 || $age >= 28.5 ) {
		return array( '新月', '新しく始めたいことを、そっと書き出してみるのに向いた時期です。' );
	}
	if ( $age < 5.5 ) {
		return array( '三日月', '小さな一歩を踏み出すのにぴったりの、はじまりの時期です。' );
	}
	if ( $age < 9 ) {
		return array( '上弦の月', '育てていきたいことに、少しずつ手をかけてみる時期です。' );
	}
	if ( $age < 13.8 ) {
		return array( '満ちていく月', '積み重ねてきたことが、ふくらんでいく時期です。' );
	}
	if ( $age < 16.2 ) {
		return array( '満月', 'がんばってきた自分を、そっとねぎらってあげたい時期です。' );
	}
	if ( $age < 21 ) {
		return array( '欠けていく月', '手放したいことや、片付けを進めるのに向いた時期です。' );
	}
	if ( $age < 24.5 ) {
		return array( '下弦の月', '見直しや整理をして、次の新月に備える時期です。' );
	}
	return array( '有明の月', '心と体を休めて、ひと息つきたい時期です。' );
}

/**
 * 今日の紹介投稿(月曜は null)
 * @return array{label: string, page: string, pr: bool, x: string, threads: string, insta: string}|null
 */
function ut_intro_post( ?DateTimeImmutable $today = null ) {
	$today = $today ?: ut_today();
	$signs = ut_zodiac_signs();
	$r     = ut_zodiac_ranking( $today );
	$top   = $r[0];
	$md    = $today->format( 'n月j日' );

	switch ( (int) $today->format( 'N' ) ) {
		case 2: // 火: 相性
			$d    = ut_sign_detail( $top, $today );
			$b    = $signs[ $d['partner'] ];
			$c    = ut_compat( $top['key'], $d['partner'] );
			$url  = ut_compat_url( $top['key'], $d['partner'] );
			$pair = $signs[ $c['a'] ]['name'] . 'と' . $signs[ $c['b'] ]['name'];
			return array(
				'label'   => '火曜・12星座の相性',
				'page'    => $url,
				'pr'      => false,
				'x'       => ut_x_compose(
					array(
						'🌙今日1位の' . $top['name'] . 'と、相性の良い' . $b['name'] . '。',
						'二人は「' . $c['label'] . '」の関係で、相性度は' . $c['score'] . '%です。',
						'',
						array( 'opt' => ut_x_short( $c['love'], 46 ) ),
						'恋愛・友情・仕事の相性はこちらから。',
						$url,
						'#星座占い #相性占い',
					)
				),
				'threads' => ut_threads_compose(
					array(
						'今日の12星座ランキングで1位になった' . $top['name'] . '。そんな' . $top['name'] . 'と相性の良い星座は、' . $b['name'] . 'です🌙',
						'',
						$pair . 'は「' . $c['label'] . '」の関係。相性度は' . $c['score'] . '%です。',
						array( 'opt' => $c['total'] ),
						'',
						'恋愛では、' . $c['love'],
						array( 'opt' => '二人がうまくいくコツ:' . $c['tip'] ),
						'',
						'サイトでは78通りの相性をすべて載せています。気になるあの人の星座も、そっとのぞいてみてくださいね。',
						$url,
					)
				),
				'insta'   => implode(
					"\n",
					array(
						$pair . 'の相性は' . $c['score'] . '%🌙',
						'「' . $c['label'] . '」の二人です。',
						'',
						$c['love'],
						'',
						'うまくいくコツ:' . $c['tip'],
						'',
						'78通りの相性は、プロフィールのリンクから見られます。',
						'あなたと大切な人の星座でも、ぜひ確かめてみてくださいね。',
						'',
						'#星座占い #相性占い #12星座 #' . $signs[ $c['a'] ]['name'] . ' #' . $signs[ $c['b'] ]['name'] . ' #恋愛占い #占い #運勢日和',
					)
				),
			);

		case 3: // 水: 九星気学の無料診断
			$center = ut_kyusei_stars()[ ut_kyusei_getsumei( $today ) ]['name'];
			$url    = home_url( '/' ) . '#kyusei';
			$month  = $today->format( 'n' ) . '月';
			return array(
				'label'   => '水曜・九星気学の無料診断',
				'page'    => $url,
				'pr'      => false,
				'x'       => ut_x_compose(
					array(
						'🌙生年月日を入れるだけで、あなたの本命星がわかります。',
						'',
						'九星気学の無料診断では、今日の運勢と' . $month . 'の吉方位もいっしょに。',
						array( 'opt' => $month . 'は' . $center . 'が中央に入る月です。' ),
						'ひと息つく時間に、試してみてくださいね。',
						$url,
						'#九星気学 #吉方位',
					)
				),
				'threads' => ut_threads_compose(
					array(
						'生年月日を入れるだけで、あなたの本命星がわかる「九星気学の無料診断」を置いています🌙',
						'',
						'わかるのは、次のようなことです。',
						'・本命星と月命星、あなたの性格',
						'・今年と' . $month . 'の運勢',
						'・今日の運勢と、やってはいけないこと',
						'・' . $month . 'の吉方位と凶方位',
						'・相性の良い星',
						'',
						array( 'opt' => $month . 'は' . $center . 'が中央に入る月。旅行やお出かけの方角に迷ったら、参考にしてみてくださいね。' ),
						'入力した生年月日は保存しません。',
						$url,
					)
				),
				'insta'   => implode(
					"\n",
					array(
						'生年月日で、あなたの本命星がわかります🌙',
						'',
						'九星気学の無料診断では',
						'・本命星と性格',
						'・今日の運勢',
						'・' . $month . 'の吉方位と凶方位',
						'がいっしょにわかります。',
						'',
						$month . 'は' . $center . 'が中央に入る月。',
						'お出かけの方角に迷ったときの、小さな道しるべにどうぞ。',
						'',
						'診断はプロフィールのリンクから。',
						'',
						'#九星気学 #本命星 #吉方位 #開運 #占い #無料占い #運勢日和',
					)
				),
			);

		case 4: // 木: 開運グッズ(Amazonへの誘導を含むため #PR)
			$url = home_url( '/kaiun/' );
			return array(
				'label'   => '木曜・開運グッズ(#PR)',
				'page'    => $url,
				'pr'      => true,
				'x'       => ut_x_compose(
					array(
						'#PR',
						'🕯今日1位の' . $top['name'] . 'のラッキーアイテムは「' . $top['item'] . '」。',
						'',
						'星座や本命星に合わせた開運グッズを、ジャンル別にまとめました。',
						array( 'opt' => '身近なものから、あなたの足元を照らす灯りを見つけてみてくださいね。' ),
						$url,
						'#開運 #星座占い',
					)
				),
				'threads' => ut_threads_compose(
					array(
						'#PR',
						'今日の12星座ランキングで1位の' . $top['name'] . '。ラッキーアイテムは「' . $top['item'] . '」、ラッキーカラーは' . $top['color'][0] . 'です🕯',
						'',
						'サイトでは、星座や九星気学の本命星に合わせた開運グッズをまとめています。',
						'天然石やお守りのような定番から、キャンドルや手帳のように毎日の習慣に取り入れやすいものまで。',
						'',
						array( 'opt' => '持つだけで何かが変わる、というものではありませんが、お気に入りのものがそばにあると、気持ちがふっと前を向くことがあります。' ),
						'あなたの暮らしに合う灯りを、ひとつ見つけてみてくださいね。',
						$url,
					)
				),
				'insta'   => implode(
					"\n",
					array(
						'#PR',
						'今日1位の' . $top['name'] . 'のラッキーアイテムは「' . $top['item'] . '」🕯',
						'',
						'星座や本命星に合わせた開運グッズを、ジャンル別にまとめました。',
						'天然石、お守り、キャンドル、手帳など、毎日の習慣に取り入れやすいものを中心に選んでいます。',
						'',
						'一覧はプロフィールのリンクから。',
						'※商品リンクにはAmazonアソシエイトのリンクを含みます。',
						'',
						'#開運 #開運グッズ #開運アイテム #開運習慣 #星座占い #ラッキーアイテム #運勢日和',
					)
				),
			);

		case 5: // 金: 誕生日別の性格
			$b   = ut_birthday( (int) $today->format( 'n' ), (int) $today->format( 'j' ) );
			$n   = ut_birth_numbers()[ $b['num'] ];
			$url = home_url( '/tanjobi/' );
			return array(
				'label'   => '金曜・誕生日別の性格',
				'page'    => $url,
				'pr'      => false,
				'x'       => ut_x_compose(
					array(
						'🎂誕生日別の性格を、366日ぶんまとめました。',
						'',
						'今日' . $md . '生まれは' . $signs[ $b['key'] ]['name'] . '。',
						array( 'opt' => ut_x_short( $n['trait'], 40 ) ),
						'あなたや大切な人の誕生日も、そっと調べてみてくださいね。',
						$url,
						'#誕生日占い #星座占い',
					)
				),
				'threads' => ut_threads_compose(
					array(
						'1月1日から12月31日まで、366日それぞれの「誕生日別の性格」をまとめています🌙',
						'',
						'たとえば今日' . $md . '生まれの方は' . $signs[ $b['key'] ]['name'] . '。' . $n['trait'],
						array( 'opt' => '恋愛では、' . $n['love'] ),
						'',
						'ページでは、恋愛傾向や向いている仕事、誕生石、ラッキーカラーもわかります。',
						'家族や友達の誕生日を調べてみるのも、楽しいですよ。',
						$url,
					)
				),
				'insta'   => implode(
					"\n",
					array(
						'誕生日別の性格、366日ぶん🎂',
						'',
						$md . '生まれは' . $signs[ $b['key'] ]['name'] . '。',
						$n['trait'],
						'',
						'恋愛傾向・向いている仕事・誕生石・ラッキーカラーは、プロフィールのリンクから。',
						'大切な人の誕生日も、そっと調べてみてくださいね。',
						'',
						'#誕生日占い #誕生日 #性格診断 #星座占い #12星座 #誕生石 #運勢日和',
					)
				),
			);

		case 6: // 土: 月の満ち欠け
			$age   = ut_intro_moon_age( $today );
			[ $phase, $msg ] = ut_intro_moon_phase( $age );
			$moons = ut_next_moons( $today );
			$next  = $moons['new'] < $moons['full'] ? array( '新月', $moons['new'] ) : array( '満月', $moons['full'] );
			$nd    = $next[1]->format( 'n月j日' ) . '(' . ut_weekday( $next[1] ) . ')';
			$url   = home_url( '/tsuki/' );
			$tag   = '#' . $next[0];
			return array(
				'label'   => '土曜・月の満ち欠け',
				'page'    => $url,
				'pr'      => false,
				'x'       => ut_x_compose(
					array(
						'🌙今日の月は月齢' . number_format( $age, 1 ) . '、' . $phase . 'です。',
						$msg,
						'',
						'次の' . $next[0] . 'は' . $nd . '。',
						'月の満ち欠けカレンダーはこちら。',
						$url,
						'#月の満ち欠け ' . $tag,
					)
				),
				'threads' => ut_threads_compose(
					array(
						'今日' . $md . 'の月は、月齢' . number_format( $age, 1 ) . 'の' . $phase . 'です🌙',
						$msg,
						'',
						'次の' . $next[0] . 'は' . $nd . $next[1]->format( 'G:i' ) . 'ごろ。',
						array( 'opt' => '新月の日には叶えたい願い事を、満月の日には手放したいことを書き出してみるのがおすすめです。' ),
						'',
						'月の満ち欠けと、新月・満月の日時・星座ごとのテーマをカレンダーにまとめています。夜空を見上げるひと息の時間に、のぞいてみてくださいね。',
						$url,
					)
				),
				'insta'   => implode(
					"\n",
					array(
						'今日の月は月齢' . number_format( $age, 1 ) . '、' . $phase . '🌙',
						$msg,
						'',
						'次の' . $next[0] . 'は' . $nd . '。',
						'新月には願い事を、満月には手放したいことを書き出してみてくださいね。',
						'',
						'月の満ち欠けカレンダーは、プロフィールのリンクから。',
						'',
						'#月の満ち欠け #新月 #満月 #月齢 #月のリズム #星座占い #運勢日和',
					)
				),
			);

		case 7: // 日: 今日1位の星座ページ
			$d   = ut_sign_detail( $top, $today );
			$url = ut_sign_url( $top['key'] );
			$time = $d['time'][0];
			return array(
				'label'   => '日曜・今日1位の星座ページ',
				'page'    => $url,
				'pr'      => false,
				'x'       => ut_x_compose(
					array(
						'🌙今日1位の' . $top['name'] . 'さんへ。',
						array( 'opt' => ut_x_short( $top['text'], 44 ) ),
						'',
						'恋愛・仕事・金運や、おすすめの時間帯は星座ページで。',
						'ほかの星座の方も、今日の灯りを見つけてみてくださいね。',
						$url,
						'#今日の運勢 #' . $top['name'],
					)
				),
				'threads' => ut_threads_compose(
					array(
						'今日1位の' . $top['name'] . 'さんへ🌙',
						$top['text'],
						'',
						'恋愛運は' . $d['cats']['love']['stars'] . '、仕事運は' . $d['cats']['work']['stars'] . '、金運は' . $d['cats']['money']['stars'] . '(5段階)。',
						'おすすめの時間帯は「' . $time . '」です。',
						array( 'opt' => 'ひとことアクションは「' . $d['action'] . '」。' ),
						'',
						'星座ページでは、12星座それぞれの今日の運勢を毎日更新しています。あなたの星座も、そっとのぞいてみてくださいね。',
						$url,
					)
				),
				'insta'   => implode(
					"\n",
					array(
						'今日1位は' . $top['name'] . '🌙',
						$top['text'],
						'',
						'おすすめの時間帯:' . $time,
						'ラッキーカラー:' . $top['color'][0],
						'',
						'12星座それぞれの今日の運勢は、プロフィールのリンクから。',
						'',
						'#今日の運勢 #星座占い #12星座占い #' . $top['name'] . ' #占い #運勢日和',
					)
				),
			);
	}
	return null;
}

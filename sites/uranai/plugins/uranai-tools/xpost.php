<?php
/**
 * X(旧Twitter)投稿文の作成(管理者専用)
 * ダッシュボードと「ツール → X投稿文」に、今日投稿する文章を表示する。
 * コピーボタンと、Xの投稿画面を文章入りで開くボタン付き。
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

function ut_x_posts_html() {
	$out = '<p style="margin-top:0">' . esc_html( ut_today()->format( 'Y年n月j日' ) ) . 'の投稿文です。広告リンクを貼る投稿には「#PR」を付けてください。</p>';
	foreach ( ut_x_posts() as $i => $p ) {
		$w    = ut_x_weight( $p['text'] );
		$id   = 'ut-x-' . $i;
		$out .= '<div style="margin:0 0 18px">';
		$out .= '<p style="margin:0 0 4px"><strong>' . esc_html( $p['label'] ) . '</strong> <span style="color:' . ( $w > 280 ? '#b32d2e' : '#646970' ) . '">' . (int) $w . '/280</span></p>';
		$out .= '<textarea id="' . esc_attr( $id ) . '" rows="9" style="width:100%;font-size:13px" readonly>' . esc_textarea( $p['text'] ) . '</textarea>';
		$out .= '<p style="margin:6px 0 0;display:flex;gap:8px;flex-wrap:wrap">';
		$out .= '<button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById(\'' . esc_attr( $id ) . '\').value);this.textContent=\'コピーしました\'">コピー</button>';
		$out .= '<a class="button button-primary" target="_blank" rel="noopener" href="' . esc_url( 'https://x.com/intent/post?text=' . rawurlencode( $p['text'] ) ) . '">Xで投稿する</a>';
		$out .= '</p></div>';
	}
	return $out;
}

add_action(
	'wp_dashboard_setup',
	function () {
		if ( current_user_can( 'edit_posts' ) ) {
			wp_add_dashboard_widget( 'ut_x_posts', '今日のX投稿文(月詠灯)', fn() => print( ut_x_posts_html() ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
);

add_action(
	'admin_menu',
	function () {
		add_management_page(
			'X投稿文',
			'X投稿文',
			'edit_posts',
			'ut-x-posts',
			fn() => print( '<div class="wrap"><h1>今日のX投稿文</h1>' . ut_x_posts_html() . '</div>' ) // phpcs:ignore WordPress.Security.EscapeOutput
		);
	}
);

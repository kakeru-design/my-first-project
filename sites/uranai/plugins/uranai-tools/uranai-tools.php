<?php
/**
 * Plugin Name: Uranai Tools
 * Description: 今日の12星座ランキング(毎日0時に自動更新)と、生年月日から診断する九星気学。
 * Version: 0.1.0
 * Requires PHP: 8.0
 *
 * ショートコード:
 *   [zodiac_ranking]   今日の12星座ランキング
 *   [kyusei_form]      九星気学の入力フォーム+診断結果(?birth=YYYY-MM-DD)
 *
 * ランキングは日付をシードにした決定的な計算で生成するため、同じ日なら誰が見ても同じ結果になる。
 * 文面は data.php の文例を組み合わせて作る。文例を増やせばバリエーションが増える。
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/kyusei.php';

/* ==================================================================
 * 共通
 * ================================================================== */

/** サイトのタイムゾーンでの今日 */
function ut_today() {
	return new DateTimeImmutable( 'now', wp_timezone() );
}

/** シードとキーから 0〜1 の決定的な疑似乱数 */
function ut_rand( $seed, $key ) {
	return hexdec( substr( md5( $seed . '|' . $key ), 0, 8 ) ) / 0xffffffff;
}

/** シードで並べ替え(同じシードなら常に同じ順) */
function ut_shuffle( array $list, $seed ) {
	$keys = array_keys( $list );
	usort( $keys, fn( $a, $b ) => ut_rand( $seed, $a ) <=> ut_rand( $seed, $b ) );
	return array_map( fn( $k ) => $list[ $k ], $keys );
}

function ut_stars( $n ) {
	$n = max( 1, min( 5, (int) $n ) );
	return '<span class="ut-stars" aria-label="5段階中' . $n . '">' . str_repeat( '★', $n ) . '<span class="ut-stars-off">' . str_repeat( '★', 5 - $n ) . '</span></span>';
}

/** 生年月日から星座のキー */
function ut_zodiac_of( DateTimeInterface $d ) {
	$md = (int) $d->format( 'md' );
	foreach ( ut_zodiac_signs() as $key => $s ) {
		[ $from, $to ] = $s['range'];
		if ( $from <= $to ? ( $md >= $from && $md <= $to ) : ( $md >= $from || $md <= $to ) ) {
			return $key;
		}
	}
	return 'capricorn';
}

/* ==================================================================
 * 12星座ランキング
 * ================================================================== */

/**
 * @return array<int, array> 1位から順に並んだ配列
 */
function ut_zodiac_ranking( ?DateTimeInterface $date = null ) {
	$date  = $date ?: ut_today();
	$seed  = 'zodiac-' . $date->format( 'Y-m-d' ) . '-' . apply_filters( 'ut_ranking_salt', '' );
	$order = ut_shuffle( array_keys( ut_zodiac_signs() ), $seed );
	$texts = ut_zodiac_texts();

	$pools = array();
	foreach ( $texts['tiers'] as $tier => $list ) {
		$pools[ $tier ] = ut_shuffle( $list, $seed . '-' . $tier );
	}
	$advice = ut_shuffle( $texts['advice'], $seed . '-advice' );
	$colors = ut_shuffle( $texts['colors'], $seed . '-color' );
	$items  = ut_shuffle( $texts['items'], $seed . '-item' );

	$out = array();
	foreach ( $order as $i => $key ) {
		$rank = $i + 1;
		$tier = $rank <= 3 ? 'top' : ( $rank <= 6 ? 'good' : ( $rank <= 9 ? 'mid' : 'low' ) );
		$base = array( 'top' => 5, 'good' => 4, 'mid' => 3, 'low' => 2 )[ $tier ];
		$sub  = array();
		foreach ( array( 'love' => '恋愛運', 'work' => '仕事運', 'money' => '金運' ) as $k => $label ) {
			$jitter    = (int) floor( ut_rand( $seed, $key . $k ) * 3 ) - 1; // -1, 0, +1
			$sub[ $k ] = array( 'label' => $label, 'stars' => max( 1, min( 5, $base + $jitter ) ) );
		}
		$notes = $texts['signs'][ $key ] ?? array();
		$out[] = array_merge(
			ut_zodiac_signs()[ $key ],
			array(
				'key'    => $key,
				'rank'   => $rank,
				'tier'   => $tier,
				'text'   => $pools[ $tier ][ ( $rank - 1 ) % count( $pools[ $tier ] ) ],
				'advice' => $advice[ $i % count( $advice ) ],
				'color'  => $colors[ $i % count( $colors ) ],
				'item'   => $items[ $i % count( $items ) ],
				'sub'    => $sub,
				'note'   => $notes ? $notes[ (int) floor( ut_rand( $seed, $key . '-note' ) * count( $notes ) ) % count( $notes ) ] : '',
			)
		);
	}
	return $out;
}

function ut_zodiac_ranking_html( $atts = array() ) {
	$atts    = shortcode_atts( array( 'title' => '今日の12星座ランキング' ), $atts );
	$today   = ut_today();
	$ranking = ut_zodiac_ranking( $today );
	$mine    = ut_birth_from_request();
	$my_key  = $mine ? ut_zodiac_of( $mine ) : '';
	$w       = array( '日', '月', '火', '水', '木', '金', '土' )[ (int) $today->format( 'w' ) ];

	$out  = '<section class="ut-ranking" id="zodiac-ranking">';
	$out .= '<div class="ut-ranking-head"><h2 class="ut-title">' . esc_html( $atts['title'] ) . '</h2>';
	$out .= '<p class="ut-date">' . esc_html( $today->format( 'Y年n月j日' ) . '(' . $w . ')' ) . ' 0:00更新</p></div>';

	// 星座から探す(ジャンプナビ)
	$out .= '<nav class="ut-sign-nav" aria-label="星座を選ぶ">';
	foreach ( ut_zodiac_signs() as $key => $s ) {
		$out .= '<a href="#sign-' . esc_attr( $key ) . '"><span class="ut-symbol" aria-hidden="true">' . esc_html( $s['symbol'] ) . '</span>' . esc_html( $s['name'] ) . '</a>';
	}
	$out .= '</nav><ol class="ut-rank-list">';

	foreach ( $ranking as $r ) {
		$cls  = 'ut-rank ut-rank-' . $r['rank'] . ' ut-tier-' . $r['tier'] . ( $r['key'] === $my_key ? ' is-mine' : '' );
		$out .= '<li class="' . esc_attr( $cls ) . '" id="sign-' . esc_attr( $r['key'] ) . '">';
		$out .= '<div class="ut-rank-badge"><span class="ut-rank-num">' . (int) $r['rank'] . '</span><span class="ut-rank-unit">位</span></div>';
		$out .= '<div class="ut-rank-main">';
		$out .= '<h3 class="ut-rank-sign"><span class="ut-symbol" aria-hidden="true">' . esc_html( $r['symbol'] ) . '</span>' . esc_html( $r['name'] )
			. '<small>' . esc_html( $r['period'] ) . '</small>' . ( $r['key'] === $my_key ? '<em class="ut-mine-label">あなたの星座</em>' : '' ) . '</h3>';
		$out .= '<p class="ut-rank-text">' . esc_html( $r['text'] ) . '</p>';
		if ( '' !== $r['note'] ) {
			$out .= '<p class="ut-rank-note">' . esc_html( $r['note'] ) . '</p>';
		}
		$out .= '<dl class="ut-sub">';
		foreach ( $r['sub'] as $s ) {
			$out .= '<div><dt>' . esc_html( $s['label'] ) . '</dt><dd>' . ut_stars( $s['stars'] ) . '</dd></div>';
		}
		$out .= '</dl>';
		$out .= '<p class="ut-lucky"><span><b>ラッキーカラー</b><i class="ut-swatch" style="background:' . esc_attr( $r['color'][1] ) . '"></i>' . esc_html( $r['color'][0] ) . '</span>'
			. '<span><b>ラッキーアイテム</b>' . esc_html( $r['item'] ) . '</span></p>';
		if ( 'low' === $r['tier'] ) {
			$out .= '<p class="ut-advice"><b>開運アドバイス</b>' . esc_html( $r['advice'] ) . '</p>';
		}
		$out .= '</div></li>';
	}
	$out .= '</ol>';
	$out .= '<p class="ut-note">※ランキングは毎日0時に更新されます。結果は娯楽としてお楽しみください。</p>';
	return $out . '</section>';
}
add_shortcode( 'zodiac_ranking', 'ut_zodiac_ranking_html' );

/* ==================================================================
 * 九星気学
 * ================================================================== */

/** ?birth=YYYY-MM-DD を検証して返す */
function ut_birth_from_request() {
	$raw = isset( $_GET['birth'] ) ? sanitize_text_field( wp_unslash( $_GET['birth'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
		return null;
	}
	$d = new DateTimeImmutable( $raw, wp_timezone() );
	return ( (int) $m[1] >= 1901 && $d <= ut_today() ) ? $d : null;
}

function ut_kyusei_form_html( $atts = array() ) {
	$atts  = shortcode_atts( array( 'title' => '九星気学であなたの運勢を診断' ), $atts );
	$birth = ut_birth_from_request();
	$has   = isset( $_GET['birth'] ); // phpcs:ignore WordPress.Security.NonceVerification

	$out  = '<section class="ut-kyusei" id="kyusei">';
	$out .= '<h2 class="ut-title">' . esc_html( $atts['title'] ) . '</h2>';
	$out .= '<p class="ut-lead">生年月日を入れるだけで、あなたの本命星・月命星と、今年・今月の運気がわかります。</p>';
	$out .= '<form class="ut-form" method="get" action="#kyusei">';
	$out .= '<label for="ut-birth">生年月日</label>';
	$out .= '<input id="ut-birth" type="date" name="birth" required min="1901-01-01" max="' . esc_attr( ut_today()->format( 'Y-m-d' ) ) . '" value="' . esc_attr( $birth ? $birth->format( 'Y-m-d' ) : '' ) . '">';
	$out .= '<button type="submit">無料で診断する</button></form>';

	if ( $has && ! $birth ) {
		$out .= '<p class="ut-error">生年月日を正しく入力してください(1901年以降)。</p>';
	}
	if ( $birth ) {
		$out .= ut_kyusei_result_html( $birth );
	}
	return $out . '</section>';
}
add_shortcode( 'kyusei_form', 'ut_kyusei_form_html' );

function ut_kyusei_result_html( DateTimeImmutable $birth ) {
	$today   = ut_today();
	$honmei  = ut_kyusei_honmei( $birth );
	$getsu   = ut_kyusei_getsumei( $birth );
	$stars   = ut_kyusei_stars();
	$palaces = ut_kyusei_palaces();

	$year_pos  = ut_kyusei_palace( $honmei, ut_kyusei_honmei( $today ) );
	$month_pos = ut_kyusei_palace( $honmei, ut_kyusei_getsumei( $today ) );
	$zodiac    = ut_zodiac_signs()[ ut_zodiac_of( $birth ) ];
	$rank      = 0;
	foreach ( ut_zodiac_ranking( $today ) as $r ) {
		if ( $r['name'] === $zodiac['name'] ) {
			$rank = $r['rank'];
		}
	}

	$s    = $stars[ $honmei ];
	$out  = '<div class="ut-result" aria-live="polite">';
	$out .= '<div class="ut-honmei" style="--star:' . esc_attr( $s['color'] ) . '">';
	$out .= '<p class="ut-honmei-label">あなたの本命星</p>';
	$out .= '<p class="ut-honmei-name">' . esc_html( $s['name'] ) . '</p>';
	$out .= '<p class="ut-honmei-sub">月命星:' . esc_html( $stars[ $getsu ]['name'] ) . ' / 五行:' . esc_html( $s['element'] ) . '</p>';
	$out .= '</div>';

	$out .= '<h3>あなたの性格</h3><p>' . esc_html( $s['nature'] ) . '</p>';
	$out .= '<p class="ut-getsu"><b>内面(月命星 ' . esc_html( $stars[ $getsu ]['name'] ) . ')</b>' . esc_html( $stars[ $getsu ]['inner'] ) . '</p>';

	foreach ( array(
		array( $today->format( 'Y' ) . '年の運勢', $year_pos ),
		array( $today->format( 'n' ) . '月の運勢', $month_pos ),
	) as [ $label, $pos ] ) {
		$p    = $palaces[ $pos ];
		$out .= '<div class="ut-fortune"><h3>' . esc_html( $label ) . '<span class="ut-fortune-theme">' . esc_html( $p['theme'] ) . '</span></h3>';
		$out .= '<p class="ut-fortune-stars">' . ut_stars( $p['score'] ) . '</p>';
		$out .= '<p>' . esc_html( $p['text'] ) . '</p></div>';
	}

	$out .= '<h3>相性の良い星</h3><ul class="ut-compat">';
	foreach ( ut_kyusei_good_partners( $honmei ) as $n ) {
		$out .= '<li>' . esc_html( $stars[ $n ]['name'] ) . '</li>';
	}
	$out .= '</ul>';

	if ( $rank ) {
		$out .= '<p class="ut-today-link">あなたの星座は<b>' . esc_html( $zodiac['name'] ) . '</b>。今日の12星座ランキングでは <b>' . (int) $rank . '位</b> です。'
			. ' <a href="?birth=' . esc_attr( $birth->format( 'Y-m-d' ) ) . '#sign-' . esc_attr( ut_zodiac_of( $birth ) ) . '">ランキングを見る</a></p>';
	}

	if ( ut_kyusei_near_boundary( $birth ) ) {
		$out .= '<p class="ut-note">※節入り(季節の変わり目)の前後に生まれた方は、生まれた時刻によって星が変わる場合があります。</p>';
	}

	/* アップセル: 今は電話占いへの送客。手相・定期購読ができたらこのフックで差し替える */
	$out .= apply_filters(
		'ut_kyusei_upsell_html',
		'<div class="ut-upsell"><p class="ut-upsell-title">もっと詳しく知りたい方へ</p>'
		. '<p>恋愛・仕事・人間関係など、あなたの具体的な悩みはプロの占い師に直接相談できます。</p>'
		. ( function_exists( 'atk_button_html' ) ? atk_button_html( apply_filters( 'ut_upsell_slug', 'uranai-a' ), 'プロの占い師に相談してみる' ) : '' )
		. '</div>',
		$honmei,
		$birth
	);

	return $out . '</div>';
}

/* 診断結果のURL(?birth=)は検索エンジンにインデックスさせない */
add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( isset( $_GET['birth'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'uranai-tools', plugins_url( 'assets/uranai-tools.css', __FILE__ ), array(), '0.1.0' );
	}
);

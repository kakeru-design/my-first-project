<?php
/**
 * 今日の運勢の詳細・月の満ち欠け・トップの各ブロック
 */

defined( 'ABSPATH' ) || exit;

/* ==================================================================
 * 星座ごとの詳しい運勢
 * ================================================================== */

/** 同じエレメント、または相性の良いエレメント(火×風、地×水)の星座 */
function ut_compatible_signs( $key ) {
	$profiles = ut_sign_profiles();
	$pair     = array( '火' => '風', '風' => '火', '地' => '水', '水' => '地' );
	$el       = $profiles[ $key ]['element'];
	$out      = array();
	foreach ( $profiles as $k => $p ) {
		if ( $k !== $key && ( $p['element'] === $el || $p['element'] === $pair[ $el ] ) ) {
			$out[] = $k;
		}
	}
	return $out;
}

/**
 * ランキングの1件に、詳しい運勢を足して返す
 */
function ut_sign_detail( array $r, DateTimeInterface $date ) {
	$seed  = 'detail-' . $date->format( 'Y-m-d' ) . '-' . $r['key'];
	$pick  = fn( array $list, $k ) => $list[ (int) floor( ut_rand( $seed, $k ) * count( $list ) ) % count( $list ) ];
	$texts = ut_category_texts();
	$cats  = array();
	foreach ( $r['sub'] as $k => $s ) {
		$cats[ $k ] = $s + array( 'text' => $pick( $texts[ $k ][ $s['stars'] ], $k ) );
	}
	return $r + array(
		'cats'    => $cats,
		'time'    => $pick( ut_time_slots(), 'time' ),
		'action'  => $pick( ut_kyusei_daily_texts()['dos'], 'action' ),
		'partner' => $pick( ut_compatible_signs( $r['key'] ), 'partner' ),
	);
}

/** 今日のランキングから1星座を探す */
function ut_ranking_entry( $key, ?DateTimeInterface $date = null ) {
	foreach ( ut_zodiac_ranking( $date ) as $r ) {
		if ( $r['key'] === $key ) {
			return $r;
		}
	}
	return null;
}

function ut_sign_url( $key ) {
	return home_url( '/seiza/' . $key . '/' );
}

/** 詳しい運勢のカード(星座ページとトップの「今日の1位」で共用) */
function ut_sign_detail_html( array $d, $heading = '' ) {
	$signs = ut_zodiac_signs();
	$out   = '<div class="ut-detail">';
	if ( $heading ) {
		$out .= '<h3 class="ut-detail-title">' . esc_html( $heading ) . '</h3>';
	}
	$out .= '<p class="ut-detail-flow"><b>今日の流れ</b>' . esc_html( $d['text'] ) . '</p>';
	if ( ! empty( $d['note'] ) ) {
		$out .= '<p class="ut-rank-note">' . esc_html( $d['note'] ) . '</p>';
	}
	$out .= '<div class="ut-cats">';
	foreach ( $d['cats'] as $k => $c ) {
		$out .= '<div class="ut-cat ut-cat-' . esc_attr( $k ) . '"><p class="ut-cat-head"><b>' . esc_html( $c['label'] ) . '</b>' . ut_stars( $c['stars'] ) . '</p><p>' . esc_html( $c['text'] ) . '</p></div>';
	}
	$out .= '</div><dl class="ut-today-list">';
	$out .= '<div><dt>おすすめの時間帯</dt><dd>' . esc_html( $d['time'][0] . '(' . $d['time'][1] . ')' ) . '</dd></div>';
	$out .= '<div class="is-do"><dt>ひとことアクション</dt><dd>' . esc_html( $d['action'] ) . '</dd></div>';
	$p    = $signs[ $d['partner'] ];
	$out .= '<div><dt>相性の良い星座</dt><dd><a href="' . esc_url( ut_sign_url( $d['partner'] ) ) . '">' . esc_html( $p['symbol'] . ' ' . $p['name'] ) . '</a></dd></div>';
	$out .= '<div><dt>ラッキーカラー</dt><dd><i class="ut-swatch" style="background:' . esc_attr( $d['color'][1] ) . '"></i>' . esc_html( $d['color'][0] ) . '</dd></div>';
	$out .= '<div><dt>ラッキーアイテム</dt><dd>' . ut_item_html( $d['item'] ) . '</dd></div>';
	if ( 'low' === $d['tier'] ) {
		$out .= '<div class="is-do"><dt>開運アドバイス</dt><dd>' . esc_html( $d['advice'] ) . '</dd></div>';
	}
	return $out . '</dl></div>';
}

/* ==================================================================
 * 新月・満月(Meeus『Astronomical Algorithms』49章の主要項による計算。誤差は数分程度)
 * ================================================================== */

function ut_moon_phase_jde( $k, $full ) {
	$t   = $k / 1236.85;
	$jde = 2451550.09766 + 29.530588861 * $k + 0.00015437 * $t ** 2 - 0.000000150 * $t ** 3 + 0.00000000073 * $t ** 4;
	$e   = 1 - 0.002516 * $t - 0.0000074 * $t ** 2;
	$r   = fn( $deg ) => deg2rad( fmod( $deg, 360 ) );
	$m   = $r( 2.5534 + 29.10535670 * $k - 0.0000014 * $t ** 2 - 0.00000011 * $t ** 3 );
	$mp  = $r( 201.5643 + 385.81693528 * $k + 0.0107582 * $t ** 2 + 0.00001238 * $t ** 3 - 0.000000058 * $t ** 4 );
	$f   = $r( 160.7108 + 390.67050284 * $k - 0.0016118 * $t ** 2 - 0.00000227 * $t ** 3 + 0.000000011 * $t ** 4 );
	$om  = $r( 124.7746 - 1.56375588 * $k + 0.0020672 * $t ** 2 + 0.00000215 * $t ** 3 );
	$c   = $full
		? array( -0.40614, 0.17302, 0.01614, 0.01043, 0.00734, -0.00515, 0.00209 )
		: array( -0.40720, 0.17241, 0.01608, 0.01039, 0.00739, -0.00514, 0.00208 );
	$jde += $c[0] * sin( $mp ) + $c[1] * $e * sin( $m ) + $c[2] * sin( 2 * $mp ) + $c[3] * sin( 2 * $f )
		+ $c[4] * $e * sin( $mp - $m ) + $c[5] * $e * sin( $mp + $m ) + $c[6] * $e * $e * sin( 2 * $m )
		- 0.00111 * sin( $mp - 2 * $f ) - 0.00057 * sin( $mp + 2 * $f ) + 0.00056 * $e * sin( 2 * $mp + $m )
		- 0.00042 * sin( 3 * $mp ) + 0.00042 * $e * sin( $m + 2 * $f ) + 0.00038 * $e * sin( $m - 2 * $f )
		- 0.00024 * $e * sin( 2 * $mp - $m ) - 0.00017 * sin( $om );
	return $jde;
}

/**
 * $from 以降の次の新月・満月
 * @return array{new: DateTimeImmutable, full: DateTimeImmutable}
 */
function ut_next_moons( DateTimeImmutable $from ) {
	$tz     = $from->getTimezone();
	$year   = (int) $from->format( 'Y' ) + ( (int) $from->format( 'z' ) ) / 365.25;
	$k0     = floor( ( $year - 2000 ) * 12.3685 ) - 1;
	$result = array();
	foreach ( array( 'new' => 0.0, 'full' => 0.5 ) as $name => $off ) {
		for ( $k = $k0 + $off; ; $k += 1 ) {
			$unix = ( ut_moon_phase_jde( $k, $off > 0 ) - 2440587.5 ) * 86400;
			$dt   = ( new DateTimeImmutable( '@' . (int) round( $unix ) ) )->setTimezone( $tz );
			if ( $dt >= $from->setTime( 0, 0 ) ) {
				$result[ $name ] = $dt;
				break;
			}
		}
	}
	return $result;
}

/* ==================================================================
 * トップの各ブロック
 * ================================================================== */

function ut_weekday( DateTimeInterface $d ) {
	return array( '日', '月', '火', '水', '木', '金', '土' )[ (int) $d->format( 'w' ) ];
}

/** 今日のまとめ(1位・月詠灯のひとこと・次の新月満月) */
function ut_today_hero_html() {
	$today = ut_today();
	$top   = ut_zodiac_ranking( $today )[0];
	$g     = ut_greetings();
	$msg   = str_replace( '{top}', $top['name'], $g[ (int) floor( ut_rand( 'greet-' . $today->format( 'Y-m-d' ), 'g' ) * count( $g ) ) % count( $g ) ] );
	$moons = ut_next_moons( $today );
	$md    = fn( DateTimeImmutable $d ) => $d->format( 'n月j日' ) . '(' . ut_weekday( $d ) . ')';

	$out  = '<section class="ut-hero">';
	$out .= '<p class="ut-hero-date">' . esc_html( $today->format( 'Y年n月j日' ) . '(' . ut_weekday( $today ) . ')' ) . 'の運勢</p>';
	$out .= '<p class="ut-hero-top">今日の1位は <a href="' . esc_url( ut_sign_url( $top['key'] ) ) . '"><span class="ut-symbol" aria-hidden="true">' . esc_html( $top['symbol'] ) . '</span>' . esc_html( $top['name'] ) . '</a></p>';
	$out .= function_exists( 'ut_kichijitsu_badge_html' ) ? ut_kichijitsu_badge_html() : '';
	$out .= '<div class="ut-letter"><p class="ut-letter-from">月詠灯からのひとこと</p><p>' . esc_html( $msg ) . '</p></div>';
	$out .= function_exists( 'ut_moon_today_html' ) ? ut_moon_today_html( $today, true ) : '';
	$out .= '<p class="ut-moon"><span>🌑 次の新月 <b>' . esc_html( $md( $moons['new'] ) ) . '</b></span><span>🌕 次の満月 <b>' . esc_html( $md( $moons['full'] ) ) . '</b></span></p>';
	return $out . '</section>';
}
add_shortcode( 'uranai_today_hero', 'ut_today_hero_html' );

/** 今日の1位の詳しい運勢 */
function ut_top_detail_html() {
	$today = ut_today();
	$top   = ut_zodiac_ranking( $today )[0];
	$out   = '<section class="ut-top-detail">';
	$out  .= '<h2 class="ut-title">今日1位 ' . esc_html( $top['name'] ) . 'のくわしい運勢</h2>';
	$out  .= ut_sign_detail_html( ut_sign_detail( $top, $today ) );
	$out  .= '<p class="ut-more"><a href="' . esc_url( ut_sign_url( $top['key'] ) ) . '">' . esc_html( $top['name'] ) . 'のページを見る ›</a></p>';
	return $out . '</section>';
}
add_shortcode( 'uranai_top_detail', 'ut_top_detail_html' );

/** 誕生日から星座をさがす(ブラウザ内で判定して星座ページへ移動。入力は送信しない) */
function ut_birthday_picker_html() {
	$ranges = array();
	foreach ( ut_zodiac_signs() as $key => $s ) {
		$ranges[] = array( $key, $s['range'][0], $s['range'][1] );
	}
	$base = home_url( '/seiza/' );
	$out  = '<section class="ut-picker"><h2 class="ut-title">誕生日から星座をさがす</h2>';
	$out .= '<p class="ut-lead">生まれた月と日を選ぶと、あなたの星座の今日の運勢が開きます。入力した内容はどこにも送信されません。</p>';
	$out .= '<form class="ut-form ut-picker-form" onsubmit="return utPick(this)"><select name="m" aria-label="月">';
	for ( $m = 1; $m <= 12; $m++ ) {
		$out .= '<option value="' . $m . '">' . $m . '月</option>';
	}
	$out .= '</select><select name="d" aria-label="日">';
	for ( $d = 1; $d <= 31; $d++ ) {
		$out .= '<option value="' . $d . '">' . $d . '日</option>';
	}
	$out .= '</select><button type="submit">運勢を見る</button></form>';
	$out .= '<script>function utPick(f){var md=f.m.value*100+ +f.d.value,r=' . wp_json_encode( $ranges ) . ';for(var i=0;i<r.length;i++){var a=r[i][1],b=r[i][2];if(a<=b?(md>=a&&md<=b):(md>=a||md<=b)){location.href=' . wp_json_encode( $base ) . '+r[i][0]+"/";return false;}}return false;}</script>';
	return $out . '</section>';
}
add_shortcode( 'uranai_birthday_picker', 'ut_birthday_picker_html' );

/** よくある質問(構造化データ付き) */
function ut_faq_html() {
	$faq  = ut_faq();
	$out  = '<section class="ut-faq"><h2 class="ut-title">よくある質問</h2>';
	$ld   = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array() );
	foreach ( $faq as [ $q, $a ] ) {
		$out .= '<details class="ut-faq-item"><summary>' . esc_html( $q ) . '</summary><p>' . esc_html( $a ) . '</p></details>';
		$ld['mainEntity'][] = array( '@type' => 'Question', 'name' => $q, 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $a ) );
	}
	$out .= '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_UNICODE ) . '</script>';
	return $out . '</section>';
}
add_shortcode( 'uranai_faq', 'ut_faq_html' );

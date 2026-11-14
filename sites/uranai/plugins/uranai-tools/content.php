<?php
/**
 * 読みものページ: 12星座の相性 / 誕生日別の性格 / 週間ランキング / 新月・満月カレンダー
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/content-data.php';

function ut_sign_keys() {
	return array_keys( ut_zodiac_signs() );
}

/* ==================================================================
 * 12星座の相性(78通り)
 * ================================================================== */

/** 星座の並び順で正規化したペア(URLは /aisho/{a}-{b}/) */
function ut_pair( $a, $b ) {
	$keys = ut_sign_keys();
	return array_search( $a, $keys, true ) <= array_search( $b, $keys, true ) ? array( $a, $b ) : array( $b, $a );
}

function ut_compat_url( $a, $b ) {
	[ $a, $b ] = ut_pair( $a, $b );
	return home_url( '/aisho/' . $a . '-' . $b . '/' );
}

function ut_compat( $a, $b ) {
	$keys    = ut_sign_keys();
	$dist    = abs( array_search( $a, $keys, true ) - array_search( $b, $keys, true ) );
	$dist    = min( $dist, 12 - $dist );
	$aspect  = ut_aspects()[ $dist ];
	[ $a, $b ] = ut_pair( $a, $b );
	$score   = min( 99, $aspect['score'] + (int) floor( ut_rand( 'compat', $a . $b ) * 5 ) );
	return array( 'a' => $a, 'b' => $b, 'dist' => $dist, 'score' => $score ) + $aspect;
}

function ut_compat_page_html( $a, $b ) {
	$s   = ut_zodiac_signs();
	$p   = ut_sign_profiles();
	$c   = ut_compat( $a, $b );
	[ $a, $b ] = array( $c['a'], $c['b'] );
	$na  = $s[ $a ]['name'];
	$nb  = $s[ $b ]['name'];
	$same = $a === $b;

	$out  = '<article class="ut-article">';
	$out .= '<header class="ut-sign-hero"><p class="ut-sign-symbol" aria-hidden="true">' . esc_html( $s[ $a ]['symbol'] . ( $same ? '' : ' × ' . $s[ $b ]['symbol'] ) ) . '</p>';
	$out .= '<h1 class="ut-sign-name">' . esc_html( $same ? $na . 'どうしの相性' : $na . 'と' . $nb . 'の相性' ) . '</h1>';
	$out .= '<p class="ut-sign-period">' . esc_html( $c['name'] . '/「' . $c['label'] . '」の関係' ) . '</p>';
	$out .= '<p class="ut-sign-rank"><span>相性度</span><b>' . (int) $c['score'] . '</b><span>%</span></p></header>';

	$out .= '<div class="ut-detail">';
	$intro = $same
		? $na . 'の「' . $p[ $a ]['keywords'][0] . '」と「' . $p[ $a ]['keywords'][1] . '」を、二人そろって持っている組み合わせです。'
		: $p[ $a ]['element'] . 'のエレメントを持つ' . $na . 'の「' . $p[ $a ]['keywords'][0] . '」と、' . $p[ $b ]['element'] . 'のエレメントを持つ' . $nb . 'の「' . $p[ $b ]['keywords'][0] . '」が出会う組み合わせです。';
	$out .= '<p class="ut-detail-flow"><b>総合</b>' . esc_html( $intro . $c['total'] ) . '</p>';
	$out .= '<div class="ut-cats">';
	foreach ( array( 'love' => '恋愛', 'friend' => '友情', 'work' => '仕事' ) as $k => $label ) {
		$out .= '<div class="ut-cat ut-cat-' . esc_attr( 'friend' === $k ? 'money' : $k ) . '"><p class="ut-cat-head"><b>' . esc_html( $label ) . '</b></p><p>' . esc_html( $c[ $k ] ) . '</p></div>';
	}
	$out .= '</div><dl class="ut-today-list"><div class="is-do"><dt>うまくいくコツ</dt><dd>' . esc_html( $c['tip'] ) . '</dd></div></dl></div>';

	foreach ( $same ? array( $a ) : array( $a, $b ) as $k ) {
		$out .= '<section class="ut-profile"><h2 class="ut-title">' . esc_html( $s[ $k ]['name'] ) . 'の性格</h2><p>' . esc_html( $p[ $k ]['nature'] ) . '</p>';
		$out .= '<p class="ut-more"><a href="' . esc_url( ut_sign_url( $k ) ) . '">' . esc_html( $s[ $k ]['name'] ) . 'の今日の運勢 ›</a></p></section>';
	}

	$out .= '<section class="ut-other-signs"><h2 class="ut-title">' . esc_html( $na ) . 'とほかの星座の相性</h2>' . ut_compat_links_html( $a ) . '</section>';
	$out .= '<p class="ut-more"><a href="' . esc_url( home_url( '/aisho/' ) ) . '">12星座の相性一覧 ›</a></p>';
	$out .= '<p class="ut-note">※相性は星座どうしの位置関係から見た傾向です。お二人の関係を決めつけるものではありません。</p>';
	return $out . '</article>';
}

/** ある星座から12星座への相性リンク */
function ut_compat_links_html( $key ) {
	$s   = ut_zodiac_signs();
	$out = '<ul class="ut-compat-links">';
	foreach ( ut_sign_keys() as $k ) {
		$c    = ut_compat( $key, $k );
		$out .= '<li><a href="' . esc_url( ut_compat_url( $key, $k ) ) . '"><span class="ut-symbol" aria-hidden="true">' . esc_html( $s[ $k ]['symbol'] ) . '</span>' . esc_html( $s[ $k ]['name'] ) . '<b>' . (int) $c['score'] . '%</b></a></li>';
	}
	return $out . '</ul>';
}

function ut_compat_index_html() {
	$s    = ut_zodiac_signs();
	$out  = '<article class="ut-article"><h1 class="ut-page-title">12星座の相性一覧(78通り)</h1>';
	$out .= '<p>2つの星座を選ぶと、恋愛・友情・仕事の相性がわかります。相性は、12星座の輪の中での位置関係(エレメントや向かい合わせなど)から見た傾向です。</p>';
	$out .= '<form class="ut-form ut-picker-form" onsubmit="location.href=' . esc_attr( wp_json_encode( home_url( '/aisho/' ) ) ) . '+[this.a.value,this.b.value].sort(function(x,y){return utOrd.indexOf(x)-utOrd.indexOf(y)}).join(\'-\')+\'/\';return false;">';
	foreach ( array( 'a' => 'あなたの星座', 'b' => '相手の星座' ) as $n => $label ) {
		$out .= '<select name="' . $n . '" aria-label="' . esc_attr( $label ) . '">';
		foreach ( $s as $k => $v ) {
			$out .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $v['name'] ) . '</option>';
		}
		$out .= '</select>';
	}
	$out .= '<button type="submit">相性を見る</button></form><script>var utOrd=' . wp_json_encode( ut_sign_keys() ) . ';</script>';
	foreach ( $s as $k => $v ) {
		$out .= '<section class="ut-other-signs"><h2 class="ut-title">' . esc_html( $v['symbol'] . ' ' . $v['name'] ) . 'との相性</h2>' . ut_compat_links_html( $k ) . '</section>';
	}
	return $out . '</article>';
}

/* ==================================================================
 * 誕生日別の性格(366日)
 * ================================================================== */

function ut_birthday_valid( $m, $d ) {
	return checkdate( (int) $m, (int) $d, 2000 );
}

function ut_birthday_url( $m, $d ) {
	return home_url( '/tanjobi/' . (int) $m . '-' . (int) $d . '/' );
}

function ut_birthday( $m, $d ) {
	$date  = new DateTimeImmutable( sprintf( '2000-%02d-%02d', $m, $d ) );
	$key   = ut_zodiac_of( $date );
	$keys  = ut_sign_keys();
	$sign  = ut_zodiac_signs()[ $key ];
	// 星座の期間の中で、何番目の10日間(デカン)にあたるか
	[ $from, $to ] = $sign['range'];
	$start = new DateTimeImmutable( sprintf( '%d-%02d-%02d', $from > $to && $m <= 6 ? 1999 : 2000, intdiv( $from, 100 ), $from % 100 ) );
	$end   = new DateTimeImmutable( sprintf( '%d-%02d-%02d', $from > $to && $m > 6 ? 2001 : 2000, intdiv( $to, 100 ), $to % 100 ) );
	$pos   = $start->diff( $date )->days / max( 1, $start->diff( $end )->days + 1 );
	$decan = min( 2, (int) floor( $pos * 3 ) );
	$sub   = $keys[ ( array_search( $key, $keys, true ) + 4 * $decan ) % 12 ];
	$num   = ( $m + $d ) % 9 ?: 9;
	$edge  = $start->diff( $date )->days <= 1 || $date->diff( $end )->days <= 1;
	return array( 'm' => $m, 'd' => $d, 'key' => $key, 'decan' => $decan, 'sub' => $sub, 'num' => $num, 'edge' => $edge );
}

function ut_birthday_page_html( $m, $d ) {
	$b    = ut_birthday( $m, $d );
	$s    = ut_zodiac_signs();
	$p    = ut_sign_profiles();
	$n    = ut_birth_numbers()[ $b['num'] ];
	$sign = $s[ $b['key'] ];
	$sub  = $s[ $b['sub'] ];
	$name = $m . '月' . $d . '日';

	$out  = '<article class="ut-article">';
	$out .= '<header class="ut-sign-hero"><p class="ut-sign-symbol" aria-hidden="true">' . esc_html( $sign['symbol'] ) . '</p>';
	$out .= '<h1 class="ut-sign-name">' . esc_html( $name ) . '生まれの性格と運勢</h1>';
	$out .= '<p class="ut-sign-period">' . esc_html( $sign['name'] . '(' . $sign['period'] . ')/ 誕生数 ' . $b['num'] ) . '</p></header>';

	$decan_text = 0 === $b['decan']
		? $sign['name'] . 'の性質をもっともストレートに持つ時期の生まれです。'
		: '同じ' . $p[ $b['key'] ]['element'] . 'のエレメントを持つ' . $sub['name'] . 'の影響も受ける時期の生まれで、「' . implode( '・', $p[ $b['sub'] ]['keywords'] ) . '」といった一面もあわせ持っています。';
	$out .= '<div class="ut-detail">';
	$out .= '<p class="ut-detail-flow"><b>性格</b>' . esc_html( $name . '生まれの人は' . $sign['name'] . '。' . $p[ $b['key'] ]['nature'] . $decan_text . $n['trait'] ) . '</p>';
	$out .= '<div class="ut-cats"><div class="ut-cat ut-cat-love"><p class="ut-cat-head"><b>恋愛傾向</b></p><p>' . esc_html( $n['love'] ) . '</p></div>';
	$out .= '<div class="ut-cat ut-cat-work"><p class="ut-cat-head"><b>向いている仕事</b></p><p>' . esc_html( $n['work'] ) . '</p></div></div>';
	$out .= '<dl class="ut-today-list">';
	$out .= '<div><dt>星座</dt><dd><a href="' . esc_url( ut_sign_url( $b['key'] ) ) . '">' . esc_html( $sign['symbol'] . ' ' . $sign['name'] ) . '</a>(エレメント:' . esc_html( $p[ $b['key'] ]['element'] ) . '/守護星:' . esc_html( $p[ $b['key'] ]['ruler'] ) . ')</dd></div>';
	$out .= '<div><dt>誕生石</dt><dd>' . esc_html( ut_birthstones()[ $m ] ) . '</dd></div>';
	$out .= '<div><dt>ラッキーカラー</dt><dd><i class="ut-swatch" style="background:' . esc_attr( $n['color'][1] ) . '"></i>' . esc_html( $n['color'][0] ) . '</dd></div>';
	$out .= '<div><dt>ラッキーナンバー</dt><dd>' . (int) $b['num'] . '</dd></div>';
	$out .= '</dl></div>';
	if ( $b['edge'] ) {
		$out .= '<p class="ut-note">※星座の境目の日です。生まれた年や時刻によっては、となりの星座になる場合があります。</p>';
	}

	$out .= '<section class="ut-other-signs"><h2 class="ut-title">' . esc_html( $sign['name'] ) . 'との相性</h2>' . ut_compat_links_html( $b['key'] ) . '</section>';

	$prev = ( new DateTimeImmutable( sprintf( '2000-%02d-%02d', $m, $d ) ) )->modify( '-1 day' );
	$next = ( new DateTimeImmutable( sprintf( '2000-%02d-%02d', $m, $d ) ) )->modify( '+1 day' );
	$out .= '<nav class="ut-pager"><a href="' . esc_url( ut_birthday_url( $prev->format( 'n' ), $prev->format( 'j' ) ) ) . '">‹ ' . esc_html( $prev->format( 'n月j日' ) ) . '</a>';
	$out .= '<a href="' . esc_url( home_url( '/tanjobi/' ) ) . '">誕生日一覧</a>';
	$out .= '<a href="' . esc_url( ut_birthday_url( $next->format( 'n' ), $next->format( 'j' ) ) ) . '">' . esc_html( $next->format( 'n月j日' ) ) . ' ›</a></nav>';
	$out .= '<p class="ut-note">※誕生日から見た傾向です。結果は娯楽としてお楽しみください。</p>';
	return $out . '</article>';
}

function ut_birthday_index_html() {
	$out = '<article class="ut-article"><h1 class="ut-page-title">誕生日別の性格(366日)</h1><p>生まれた日を選ぶと、星座と誕生数から見た性格・恋愛傾向・向いている仕事・ラッキーカラーがわかります。</p>';
	for ( $m = 1; $m <= 12; $m++ ) {
		$out .= '<section class="ut-bday-month"><h2 class="ut-title">' . $m . '月生まれ</h2><ul class="ut-bday-grid">';
		for ( $d = 1; $d <= 31; $d++ ) {
			if ( ut_birthday_valid( $m, $d ) ) {
				$out .= '<li><a href="' . esc_url( ut_birthday_url( $m, $d ) ) . '">' . $d . '日</a></li>';
			}
		}
		$out .= '</ul></section>';
	}
	return $out . '</article>';
}

/* ==================================================================
 * 週間ランキング(毎週月曜に更新)
 * ================================================================== */

function ut_weekly_ranking( ?DateTimeImmutable $date = null ) {
	$date  = $date ?: ut_today();
	$mon   = $date->modify( 'monday this week' );
	$seed  = 'week-' . $mon->format( 'Y-m-d' );
	$t     = ut_weekly_texts();
	$order = ut_shuffle( ut_sign_keys(), $seed );
	$pools = array();
	foreach ( array( 'top', 'good', 'mid', 'low' ) as $tier ) {
		$pools[ $tier ] = ut_shuffle( $t[ $tier ], $seed . $tier );
	}
	$kw   = ut_shuffle( $t['keywords'], $seed . 'kw' );
	$days = array( '月', '火', '水', '木', '金', '土', '日' );
	$out  = array();
	foreach ( $order as $i => $key ) {
		$rank  = $i + 1;
		$tier  = $rank <= 3 ? 'top' : ( $rank <= 6 ? 'good' : ( $rank <= 9 ? 'mid' : 'low' ) );
		$out[] = ut_zodiac_signs()[ $key ] + array(
			'key'     => $key,
			'rank'    => $rank,
			'tier'    => $tier,
			'text'    => $pools[ $tier ][ $i % 3 ],
			'keyword' => $kw[ $i ],
			'best'    => $days[ (int) floor( ut_rand( $seed, $key . 'day' ) * 7 ) ],
		);
	}
	return array( 'monday' => $mon, 'list' => $out );
}

function ut_weekly_html() {
	$w    = ut_weekly_ranking();
	$sun  = $w['monday']->modify( '+6 days' );
	$out  = '<article class="ut-article"><h1 class="ut-page-title">今週の12星座ランキング</h1>';
	$out .= '<p class="ut-date">' . esc_html( $w['monday']->format( 'n月j日' ) . '(月)〜' . $sun->format( 'n月j日' ) . '(日)/ 毎週月曜0時更新' ) . '</p><ol class="ut-rank-list">';
	foreach ( $w['list'] as $r ) {
		$out .= '<li class="ut-rank ut-rank-' . (int) $r['rank'] . '"><div class="ut-rank-badge"><span class="ut-rank-num">' . (int) $r['rank'] . '</span><span class="ut-rank-unit">位</span></div><div class="ut-rank-main">';
		$out .= '<h2 class="ut-rank-sign"><span class="ut-symbol" aria-hidden="true">' . esc_html( $r['symbol'] ) . '</span>' . esc_html( $r['name'] ) . '<small>' . esc_html( $r['period'] ) . '</small></h2>';
		$out .= '<p class="ut-rank-text">' . esc_html( $r['text'] ) . '</p>';
		$out .= '<p class="ut-lucky"><span><b>今週のキーワード</b>' . esc_html( $r['keyword'] ) . '</span><span><b>ラッキーデー</b>' . esc_html( $r['best'] ) . '曜日</span></p>';
		$out .= '<p class="ut-rank-more"><a href="' . esc_url( ut_sign_url( $r['key'] ) ) . '">' . esc_html( $r['name'] ) . 'の今日の運勢 ›</a></p></div></li>';
	}
	return $out . '</ol><p class="ut-note">※ランキングは毎週月曜0時に更新されます。結果は娯楽としてお楽しみください。</p></article>';
}

/* ==================================================================
 * 新月・満月カレンダー
 * ================================================================== */

/** 太陽の黄経(度)。簡易式で誤差0.01度程度 */
function ut_sun_longitude( $jd ) {
	$t  = ( $jd - 2451545.0 ) / 36525;
	$l0 = 280.46646 + 36000.76983 * $t;
	$m  = deg2rad( 357.52911 + 35999.05029 * $t );
	$c  = ( 1.914602 - 0.004817 * $t ) * sin( $m ) + 0.019993 * sin( 2 * $m ) + 0.000289 * sin( 3 * $m );
	return fmod( fmod( $l0 + $c, 360 ) + 360, 360 );
}

/** 指定日から約13か月分の新月・満月 */
function ut_moon_calendar( DateTimeImmutable $from, $months = 13 ) {
	$tz   = $from->getTimezone();
	$keys = ut_sign_keys();
	$k0   = floor( ( (int) $from->format( 'Y' ) + (int) $from->format( 'z' ) / 365.25 - 2000 ) * 12.3685 ) - 1;
	$list = array();
	for ( $k = $k0; $k < $k0 + $months + 2; $k += 0.5 ) {
		$full = fmod( $k, 1 ) !== 0.0;
		$jde  = ut_moon_phase_jde( $k, $full );
		$dt   = ( new DateTimeImmutable( '@' . (int) round( ( $jde - 2440587.5 ) * 86400 ) ) )->setTimezone( $tz );
		if ( $dt < $from->setTime( 0, 0 ) ) {
			continue;
		}
		$lon    = ut_sun_longitude( $jde ) + ( $full ? 180 : 0 );
		$list[] = array( 'dt' => $dt, 'full' => $full, 'sign' => $keys[ (int) floor( fmod( $lon, 360 ) / 30 ) ] );
		if ( count( $list ) >= $months * 2 ) {
			break;
		}
	}
	return $list;
}

function ut_moon_calendar_html() {
	$today = ut_today();
	$s     = ut_zodiac_signs();
	$th    = ut_moon_themes();
	$list  = ut_moon_calendar( $today );
	$out   = '<article class="ut-article"><h1 class="ut-page-title">新月・満月カレンダー</h1>';
	$out  .= '<p>新月は「始まり」、満月は「満ちる・手放す」のタイミング。新月の日には願い事を、満月の日には手放したいことを書き出してみるのがおすすめです。月が入る星座ごとのテーマも参考にしてください。</p>';
	$next  = $list[0];
	$out  .= '<div class="ut-moon-next"><p>' . ( $next['full'] ? '🌕 次の満月' : '🌑 次の新月' ) . '</p><p class="ut-moon-date">' . esc_html( $next['dt']->format( 'Y年n月j日' ) . '(' . ut_weekday( $next['dt'] ) . ')' . $next['dt']->format( 'G:i' ) ) . '</p>';
	$out  .= '<p>' . esc_html( $s[ $next['sign'] ]['name'] . 'の' . ( $next['full'] ? '満月' : '新月' ) . ':' . $th[ $next['sign'] ][ $next['full'] ? 1 : 0 ] ) . '</p></div>';
	$out  .= '<ul class="ut-moon-list">';
	foreach ( $list as $e ) {
		$out .= '<li class="' . ( $e['full'] ? 'is-full' : 'is-new' ) . '"><p class="ut-moon-when"><span class="ut-moon-type">' . ( $e['full'] ? '🌕 満月' : '🌑 新月' ) . '</span>' . esc_html( $e['dt']->format( 'Y年n月j日' ) . '(' . ut_weekday( $e['dt'] ) . ')' . $e['dt']->format( 'G:i' ) ) . '</p>';
		$out .= '<p class="ut-moon-theme"><b>' . esc_html( $s[ $e['sign'] ]['name'] ) . '</b>' . esc_html( $th[ $e['sign'] ][ $e['full'] ? 1 : 0 ] ) . '</p></li>';
	}
	$out .= '</ul><p class="ut-note">※日時は日本時間です。天文計算による値で、数分程度の誤差があります。</p></article>';
	return $out;
}

/* ==================================================================
 * 読みものへのリンク(トップ用)
 * ================================================================== */
function ut_reading_links_html() {
	$items = array(
		array( home_url( '/shukan/' ), '今週の12星座ランキング', '毎週月曜更新の週間運勢' ),
		array( home_url( '/aisho/' ), '12星座の相性', '恋愛・友情・仕事の相性78通り' ),
		array( home_url( '/tanjobi/' ), '誕生日別の性格', '366日それぞれの性格と恋愛傾向' ),
		array( home_url( '/tsuki/' ), '新月・満月カレンダー', '願い事と手放しのタイミング' ),
	);
	$out = '<section class="ut-reading"><h2 class="ut-title">読みもの</h2><ul class="ut-reading-list">';
	foreach ( $items as [ $url, $title, $sub ] ) {
		$out .= '<li><a href="' . esc_url( $url ) . '"><b>' . esc_html( $title ) . '</b><span>' . esc_html( $sub ) . '</span></a></li>';
	}
	return $out . '</ul></section>';
}
add_shortcode( 'uranai_reading_links', 'ut_reading_links_html' );

<?php
/**
 * 月の満ち欠け(今日の月・月齢・月相・今月のカレンダー・上弦/下弦)
 *
 * 月齢の求め方:
 *   日本時間のその日の正午(12:00 JST)時点で、直前の新月(Meeus 49章で計算した朔の時刻)から
 *   経過した日数。国立天文台などが使う「正午月齢」と同じ考え方。
 * 輝面比:
 *   月齢から位相角を近似し (1 - cos θ) / 2 で求める(θ = 2π × 月齢 / 朔望月)。誤差は数%程度。
 * 上弦・下弦:
 *   Meeus『Astronomical Algorithms』49章の上弦・下弦用の補正項(主要項と W 項)で計算。誤差は数分程度。
 */

defined( 'ABSPATH' ) || exit;

const UT_SYNODIC = 29.530588861;

/** 上弦($last=false)・下弦($last=true)の時刻(JDE)。k は整数 + 0.25 / 0.75 */
function ut_moon_quarter_jde( $k, $last ) {
	$t   = $k / 1236.85;
	$jde = 2451550.09766 + UT_SYNODIC * $k + 0.00015437 * $t ** 2 - 0.000000150 * $t ** 3 + 0.00000000073 * $t ** 4;
	$e   = 1 - 0.002516 * $t - 0.0000074 * $t ** 2;
	$r   = fn( $deg ) => deg2rad( fmod( $deg, 360 ) );
	$m   = $r( 2.5534 + 29.10535670 * $k - 0.0000014 * $t ** 2 - 0.00000011 * $t ** 3 );
	$mp  = $r( 201.5643 + 385.81693528 * $k + 0.0107582 * $t ** 2 + 0.00001238 * $t ** 3 - 0.000000058 * $t ** 4 );
	$f   = $r( 160.7108 + 390.67050284 * $k - 0.0016118 * $t ** 2 - 0.00000227 * $t ** 3 + 0.000000011 * $t ** 4 );
	$om  = $r( 124.7746 - 1.56375588 * $k + 0.0020672 * $t ** 2 + 0.00000215 * $t ** 3 );
	$jde += -0.62801 * sin( $mp ) + 0.17172 * $e * sin( $m ) - 0.01183 * $e * sin( $mp + $m ) + 0.00862 * sin( 2 * $mp )
		+ 0.00804 * sin( 2 * $f ) + 0.00454 * $e * sin( $mp - $m ) + 0.00204 * $e * $e * sin( 2 * $m )
		- 0.00180 * sin( $mp - 2 * $f ) - 0.00070 * sin( $mp + 2 * $f ) - 0.00040 * sin( 3 * $mp )
		- 0.00034 * $e * sin( 2 * $mp - $m ) + 0.00032 * $e * sin( $m + 2 * $f ) + 0.00032 * $e * sin( $m - 2 * $f )
		- 0.00028 * $e * $e * sin( $mp + 2 * $m ) + 0.00027 * $e * sin( 2 * $mp + $m ) - 0.00017 * sin( $om );
	$w    = 0.00306 - 0.00038 * $e * cos( $m ) + 0.00026 * cos( $mp ) - 0.00002 * cos( $mp - $m ) + 0.00002 * cos( $mp + $m ) + 0.00002 * cos( 2 * $f );
	return $jde + ( $last ? -$w : $w );
}

function ut_jde_to_datetime( $jde, DateTimeZone $tz ) {
	return ( new DateTimeImmutable( '@' . (int) round( ( $jde - 2440587.5 ) * 86400 ) ) )->setTimezone( $tz );
}

/** k(整数 = 新月)の目安 */
function ut_moon_k0( DateTimeInterface $d ) {
	return floor( ( (int) $d->format( 'Y' ) + (int) $d->format( 'z' ) / 365.25 - 2000 ) * 12.3685 );
}

/**
 * $from から $to まで(両端含む日付)の主な月相の一覧
 * @return array<int, array{type: string, dt: DateTimeImmutable}> type = new | first | full | last
 */
function ut_moon_events( DateTimeImmutable $from, DateTimeImmutable $to ) {
	$tz   = $from->getTimezone();
	$k0   = ut_moon_k0( $from ) - 2;
	$list = array();
	for ( $k = $k0; $k < $k0 + 20; $k++ ) {
		foreach ( array( 'new' => 0.0, 'first' => 0.25, 'full' => 0.5, 'last' => 0.75 ) as $type => $off ) {
			$kk  = $k + $off;
			$jde = in_array( $type, array( 'new', 'full' ), true ) ? ut_moon_phase_jde( $kk, 'full' === $type ) : ut_moon_quarter_jde( $kk, 'last' === $type );
			$dt  = ut_jde_to_datetime( $jde, $tz );
			if ( $dt >= $from->setTime( 0, 0 ) && $dt <= $to->setTime( 23, 59, 59 ) ) {
				$list[] = array( 'type' => $type, 'dt' => $dt );
			}
		}
		if ( ut_jde_to_datetime( ut_moon_phase_jde( $k, false ), $tz ) > $to->modify( '+1 day' ) ) {
			break;
		}
	}
	usort( $list, fn( $a, $b ) => $a['dt'] <=> $b['dt'] );
	return $list;
}

/** その日の正午(JST)時点の月齢 */
function ut_moon_age( DateTimeImmutable $date ) {
	$noon = $date->setTime( 12, 0 );
	$k    = ut_moon_k0( $noon ) + 1;
	for ( $i = 0; $i < 4; $i++, $k-- ) {
		$nm = ut_jde_to_datetime( ut_moon_phase_jde( $k, false ), $noon->getTimezone() );
		if ( $nm <= $noon ) {
			return ( $noon->getTimestamp() - $nm->getTimestamp() ) / 86400;
		}
	}
	return 0.0;
}

/** 輝面比(0〜1) */
function ut_moon_illumination( $age ) {
	return ( 1 - cos( 2 * M_PI * $age / UT_SYNODIC ) ) / 2;
}

/** 月相名 */
function ut_moon_phase_name( $age ) {
	$names = array(
		array( 1.0, '新月' ),
		array( 2.0, '二日月' ),
		array( 4.0, '三日月' ),
		array( 6.5, '若月' ),
		array( 8.0, '上弦の月' ),
		array( 11.0, '十日夜の月' ),
		array( 13.0, '十三夜' ),
		array( 14.0, '小望月' ),
		array( 15.5, '満月' ),
		array( 16.5, '十六夜' ),
		array( 17.5, '立待月' ),
		array( 18.5, '居待月' ),
		array( 19.5, '寝待月' ),
		array( 21.0, '更待月' ),
		array( 23.0, '下弦の月' ),
		array( 28.5, '有明月' ),
		array( 99, '晦日月' ),
	);
	foreach ( $names as [ $max, $name ] ) {
		if ( $age < $max ) {
			return $name;
		}
	}
	return '新月';
}

/** 月相ごとの月詠灯のひとこと(4つの時期に分ける) */
function ut_moon_word( $age ) {
	$words = array(
		'new'   => '新しい流れが始まるころ。叶えたいことを、ひとつだけ手帳に書いてみてはいかがでしょう。',
		'wax'   => '月が少しずつ満ちていく時期です。始めたことを、焦らず育てていくのに向いているかもしれません。',
		'full'  => '月がいちばん明るく照らす時期。がんばってきた自分を、そっとねぎらってあげてくださいね。',
		'wane'  => '月が少しずつ欠けていく時期です。いらないものを手放したり、部屋を整えたりするのに向いていそうです。',
	);
	if ( $age < 2.0 || $age >= 28.0 ) {
		return $words['new'];
	}
	if ( $age < 13.0 ) {
		return $words['wax'];
	}
	if ( $age < 17.0 ) {
		return $words['full'];
	}
	return $words['wane'];
}

/**
 * 月の形のSVG(明るい部分を金色で描く。満ちていく月は右側、欠けていく月は左側が光る)
 */
function ut_moon_svg( $age, $size = 40 ) {
	$k    = ut_moon_illumination( $age );
	$r    = 20;
	$rx   = round( $r * abs( 1 - 2 * $k ), 2 );
	$wane = $age > UT_SYNODIC / 2;
	// 外側の半円(右)+ 明暗の境目(楕円弧)。三日月型は境目が右へ、半月より太い月は左へふくらむ
	$sweep = $k > 0.5 ? 1 : 0;
	$path  = 'M0,-20 A20,20 0 0 1 0,20 A' . $rx . ',20 0 0 ' . $sweep . ' 0,-20 Z';
	$flip  = $wane ? ' transform="scale(-1,1)"' : '';
	$lit   = $k < 0.02 ? '' : '<path d="' . $path . '" fill="#f3d36b"' . $flip . '/>';
	if ( $k > 0.98 ) {
		$lit = '<circle r="20" fill="#f3d36b"/>';
	}
	return '<svg class="ut-moon-svg" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="-22 -22 44 44" aria-hidden="true" focusable="false">'
		. '<circle r="20" fill="#3a3354" stroke="#6b5f8a" stroke-width="1"/>' . $lit . '</svg>';
}

/** 今日の月のかたまり($compact = トップ用の短い表示) */
function ut_moon_today_html( DateTimeImmutable $date, $compact = false ) {
	$age   = ut_moon_age( $date );
	$name  = ut_moon_phase_name( $age );
	// 新月・上弦・満月・下弦の瞬間がその日にある場合は、その名前を優先する
	$names = array( 'new' => '新月', 'first' => '上弦の月', 'full' => '満月', 'last' => '下弦の月' );
	foreach ( ut_moon_events( $date, $date ) as $e ) {
		$name = $names[ $e['type'] ];
	}
	$illum = (int) round( ut_moon_illumination( $age ) * 100 );
	if ( $compact ) {
		return '<p class="ut-moon-today is-compact">' . ut_moon_svg( $age, 30 )
			. '<span><b>今日の月 ' . esc_html( $name ) . '</b>月齢' . esc_html( number_format( $age, 1 ) ) . '・輝面比' . $illum . '%</span></p>';
	}
	$out  = '<div class="ut-moon-today">' . ut_moon_svg( $age, 84 );
	$out .= '<div><p class="ut-moon-today-label">' . esc_html( $date->format( 'n月j日' ) . '(' . ut_weekday( $date ) . ')' ) . 'の月</p>';
	$out .= '<p class="ut-moon-today-name">' . esc_html( $name ) . '</p>';
	$out .= '<p class="ut-moon-today-data">月齢 ' . esc_html( number_format( $age, 1 ) ) . ' / 輝面比 ' . $illum . '%</p></div>';
	$out .= '<p class="ut-moon-today-word"><b>月詠灯より</b>' . esc_html( ut_moon_word( $age ) ) . '</p></div>';
	return $out;
}

/** 今月の月の満ち欠けカレンダー(7列) */
function ut_moon_month_html( DateTimeImmutable $date ) {
	$first  = $date->modify( 'first day of this month' )->setTime( 0, 0 );
	$last   = $date->modify( 'last day of this month' )->setTime( 0, 0 );
	$events = array();
	$labels = array( 'new' => '新月', 'first' => '上弦', 'full' => '満月', 'last' => '下弦' );
	foreach ( ut_moon_events( $first, $last ) as $e ) {
		$events[ $e['dt']->format( 'j' ) ] = $e;
	}
	$today = ut_today()->format( 'Y-m-d' );

	$out  = '<section class="ut-moon-month"><h2 class="ut-title">' . esc_html( $date->format( 'Y年n月' ) ) . 'の月の満ち欠けカレンダー</h2>';
	$out .= '<div class="ut-moon-grid" role="table" aria-label="' . esc_attr( $date->format( 'n月' ) ) . 'の月の満ち欠け">';
	foreach ( array( '日', '月', '火', '水', '木', '金', '土' ) as $w ) {
		$out .= '<div class="ut-moon-grid-head" role="columnheader">' . $w . '</div>';
	}
	for ( $i = 0; $i < (int) $first->format( 'w' ); $i++ ) {
		$out .= '<div class="ut-moon-cell is-blank" aria-hidden="true"></div>';
	}
	for ( $d = $first; $d <= $last; $d = $d->modify( '+1 day' ) ) {
		$age  = ut_moon_age( $d );
		$ev   = $events[ $d->format( 'j' ) ] ?? null;
		$cls  = 'ut-moon-cell' . ( $ev ? ' is-' . $ev['type'] : '' ) . ( $d->format( 'Y-m-d' ) === $today ? ' is-today' : '' );
		$out .= '<div class="' . esc_attr( $cls ) . '" role="cell"><span class="ut-moon-day">' . (int) $d->format( 'j' ) . '</span>' . ut_moon_svg( $age, 26 );
		$out .= $ev
			? '<span class="ut-moon-ev">' . esc_html( $labels[ $ev['type'] ] ) . '<small>' . esc_html( $ev['dt']->format( 'G:i' ) ) . '</small></span>'
			: '<span class="ut-moon-age">' . esc_html( number_format( $age, 1 ) ) . '</span>';
		$out .= '</div>';
	}
	$out .= '</div>';

	// 今月の主な月の日(上弦・下弦を含む)
	$out .= '<ul class="ut-moon-keydays">';
	foreach ( $events as $e ) {
		$out .= '<li class="is-' . esc_attr( $e['type'] ) . '"><b>' . esc_html( $labels[ $e['type'] ] ) . '</b>' . esc_html( $e['dt']->format( 'n月j日' ) . '(' . ut_weekday( $e['dt'] ) . ')' . $e['dt']->format( 'G:i' ) ) . '</li>';
	}
	$out .= '</ul><p class="ut-note">※月齢は日本時間の正午の値です。月の形は月齢から描いた目安です。</p></section>';
	return $out;
}

<?php
/**
 * 九星気学の計算
 *
 * - 年の区切りは立春、月の区切りは各月の節入り(小寒・立春・啓蟄…大雪)。
 * - 節入り日は寿星万年暦の近似式(1901〜2100年)で算出。誤差は年により±1日程度。
 */

defined( 'ABSPATH' ) || exit;

/** 各月の節入り日(その月の何日か) */
function ut_setsu_day( $year, $month ) {
	// [21世紀の定数, 20世紀の定数]。月 => 節気: 1小寒 2立春 3啓蟄 4清明 5立夏 6芒種 7小暑 8立秋 9白露 10寒露 11立冬 12大雪
	static $c = array(
		1  => array( 5.4055, 6.11 ),
		2  => array( 3.87, 4.6295 ),
		3  => array( 5.63, 6.3826 ),
		4  => array( 4.81, 5.59 ),
		5  => array( 5.52, 6.318 ),
		6  => array( 5.678, 6.5 ),
		7  => array( 7.108, 7.928 ),
		8  => array( 7.5, 8.35 ),
		9  => array( 7.646, 8.44 ),
		10 => array( 8.318, 9.098 ),
		11 => array( 7.438, 8.218 ),
		12 => array( 7.18, 7.9 ),
	);
	if ( $year < 1901 || $year > 2100 ) {
		return array( 1 => 6, 4, 6, 5, 6, 6, 7, 8, 8, 8, 7, 7 )[ $month ];
	}
	$is21 = $year > 2000;
	$y    = $is21 ? $year - 2000 : $year - 1900;
	$cc   = $c[ $month ][ $is21 ? 0 : 1 ];
	$leap = $month <= 2 ? intdiv( $y - 1, 4 ) : intdiv( $y, 4 );
	return (int) floor( $y * 0.2422 + $cc ) - $leap;
}

/** 九星気学上の年(立春前なら前年) */
function ut_kyusei_year( DateTimeInterface $d ) {
	$y  = (int) $d->format( 'Y' );
	$md = (int) $d->format( 'n' ) * 100 + (int) $d->format( 'j' );
	return $md < 200 + ut_setsu_day( $y, 2 ) ? $y - 1 : $y;
}

/** 九星気学上の月(寅月=2月始まり)の通し番号 1〜12 */
function ut_kyusei_month_index( DateTimeInterface $d ) {
	$m = (int) $d->format( 'n' );
	if ( (int) $d->format( 'j' ) < ut_setsu_day( (int) $d->format( 'Y' ), $m ) ) {
		$m = 1 === $m ? 12 : $m - 1;
	}
	return ( ( $m - 2 + 12 ) % 12 ) + 1;
}

function ut_wrap9( $n ) {
	return ( ( ( $n - 1 ) % 9 ) + 9 ) % 9 + 1;
}

/** 本命星(1〜9) */
function ut_kyusei_honmei( DateTimeInterface $d ) {
	return ut_wrap9( 11 - ( ut_kyusei_year( $d ) % 9 ) );
}

/** 月命星(1〜9) */
function ut_kyusei_getsumei( DateTimeInterface $d ) {
	$year_star = ut_kyusei_honmei( $d );
	$start     = in_array( $year_star, array( 1, 4, 7 ), true ) ? 8 : ( in_array( $year_star, array( 3, 6, 9 ), true ) ? 5 : 2 );
	return ut_wrap9( $start - ( ut_kyusei_month_index( $d ) - 1 ) );
}

/**
 * 盤の中央が $center のとき、星 $star が入る宮(後天定位の番号 1〜9。5=中宮)
 */
function ut_kyusei_palace( $star, $center ) {
	return ut_wrap9( $star - $center + 5 );
}

/** 相性の良い星(相生・比和) */
function ut_kyusei_good_partners( $star ) {
	$el    = array( 1 => '水', 2 => '土', 3 => '木', 4 => '木', 5 => '土', 6 => '金', 7 => '金', 8 => '土', 9 => '火' );
	$gen   = array( '木' => '火', '火' => '土', '土' => '金', '金' => '水', '水' => '木' );
	$mine  = $el[ $star ];
	$out   = array();
	foreach ( $el as $n => $e ) {
		if ( $n !== $star && ( $e === $mine || $gen[ $e ] === $mine || $gen[ $mine ] === $e ) ) {
			$out[] = $n;
		}
	}
	return $out;
}

/** 節入り日の前後1日に生まれたか(時刻によって星が変わる可能性) */
function ut_kyusei_near_boundary( DateTimeInterface $d ) {
	$day = (int) $d->format( 'j' );
	return abs( $day - ut_setsu_day( (int) $d->format( 'Y' ), (int) $d->format( 'n' ) ) ) <= 1;
}

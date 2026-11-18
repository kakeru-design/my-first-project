<?php
/**
 * 開運日・吉日(一粒万倍日・天赦日・寅の日・巳の日・己巳の日・甲子の日)
 *
 * - 日の干支: ユリウス日から計算(1949-10-01 = 甲子 で検算済み)
 * - 一粒万倍日: 節月(立春・啓蟄…の節入りで区切る月)と日の十二支の組み合わせ
 * - 天赦日: 季節(立春〜/立夏〜/立秋〜/立冬〜)と日の干支の組み合わせ
 * ※旧暦にもとづく六曜・不成就日は計算していない
 *
 * ページ: /kichijitsu/  ショートコード: [uranai_kichijitsu_badge]
 */

defined( 'ABSPATH' ) || exit;

const UT_STEMS    = array( '甲', '乙', '丙', '丁', '戊', '己', '庚', '辛', '壬', '癸' );
const UT_BRANCHES = array( '子', '丑', '寅', '卯', '辰', '巳', '午', '未', '申', '酉', '戌', '亥' );

/** 日の干支の番号(0 = 甲子 … 59 = 癸亥) */
function ut_day_ganzhi( DateTimeInterface $d ) {
	$y   = (int) $d->format( 'Y' );
	$m   = (int) $d->format( 'n' );
	$day = (int) $d->format( 'j' );
	$a   = intdiv( 14 - $m, 12 );
	$y2  = $y + 4800 - $a;
	$m2  = $m + 12 * $a - 3;
	$jdn = $day + intdiv( 153 * $m2 + 2, 5 ) + 365 * $y2 + intdiv( $y2, 4 ) - intdiv( $y2, 100 ) + intdiv( $y2, 400 ) - 32045;
	return ( $jdn + 49 ) % 60;
}

function ut_ganzhi_name( $n ) {
	return UT_STEMS[ $n % 10 ] . UT_BRANCHES[ $n % 12 ];
}

/** 吉日の種類と解説 */
function ut_kichijitsu_types() {
	return array(
		'tensha'  => array(
			'name'  => '天赦日',
			'read'  => 'てんしゃび',
			'short' => '暦の上で最上の吉日',
			'text'  => '天がすべてを赦(ゆる)すといわれる、暦の上で最上とされる吉日です。年に5〜6回ほどしかありません。新しいことを始める、入籍、開業などに選ばれることが多い日です。',
			'do'    => '新しいことを始める・入籍・開業・お財布の新調',
		),
		'ichiryu' => array(
			'name'  => '一粒万倍日',
			'read'  => 'いちりゅうまんばいび',
			'short' => '一粒の籾が万倍に実る日',
			'text'  => '一粒の籾(もみ)が、万倍にも実って稲穂になるといわれる日です。この日に始めたことは大きく育つとされ、始めごとや、お金にまつわることに向いているといわれます。月に4〜6回ほどあります。',
			'do'    => '始めごと・貯金や口座開設・お財布の使い始め・目標を書き出す',
			'dont'  => '借りること(借金も万倍になる、という言い伝えから)',
		),
		'tora'    => array(
			'name'  => '寅の日',
			'read'  => 'とらのひ',
			'short' => '金運招来の日',
			'text'  => '「虎は千里行って千里還る」という言葉から、出したお金が戻ってくるといわれる、金運にゆかりのある日です。12日に1回めぐってきます。',
			'do'    => '旅行の出発・お財布の新調・宝くじを買う',
		),
		'mi'      => array(
			'name'  => '巳の日',
			'read'  => 'みのひ',
			'short' => '弁財天にゆかりのある日',
			'text'  => '金運や芸事の神様・弁財天の使いである白蛇にゆかりのある日です。12日に1回めぐってきます。',
			'do'    => '金運のお参り・習い事を始める・お財布の使い始め',
		),
		'tsuchinotomi' => array(
			'name'  => '己巳の日',
			'read'  => 'つちのとみのひ',
			'short' => '巳の日の中でも特別な日',
			'text'  => '60日に1回めぐってくる、巳の日の中でも特に縁起が良いとされる日です。弁財天へのお参りに選ばれることが多い日です。',
			'do'    => '弁財天へのお参り・金運や芸事にまつわる始めごと',
		),
		'kinoene' => array(
			'name'  => '甲子の日',
			'read'  => 'きのえねのひ',
			'short' => '60日に1回の始まりの日',
			'text'  => '干支の組み合わせの1番目にあたる、60日に1回の「始まりの日」です。新しいことを始めるのに良い日といわれます。',
			'do'    => '新しい習慣を始める・計画を立てる',
		),
	);
}

/**
 * その日の吉日(表示順: 天赦日 → 一粒万倍日 → 己巳 → 巳 → 寅 → 甲子)
 * @return string[] 吉日の種類のキー
 */
function ut_kichijitsu( DateTimeInterface $d ) {
	$n      = ut_day_ganzhi( $d );
	$branch = $n % 12;
	$month  = ut_kyusei_month_index( $d ); // 寅月 = 1 … 丑月 = 12
	// 一粒万倍日: 節月ごとの十二支(寅月から順に)
	$ichiryu = array( 1 => array( 1, 6 ), array( 9, 2 ), array( 0, 3 ), array( 3, 4 ), array( 5, 6 ), array( 9, 6 ), array( 0, 7 ), array( 3, 8 ), array( 9, 6 ), array( 9, 10 ), array( 11, 0 ), array( 3, 0 ) );
	// 天赦日: 春(寅卯辰)戊寅 / 夏(巳午未)甲午 / 秋(申酉戌)戊申 / 冬(亥子丑)甲子
	$tensha = array( 14, 30, 44, 0 )[ intdiv( $month - 1, 3 ) ];

	$hits = array();
	if ( $n === $tensha ) {
		$hits[] = 'tensha';
	}
	if ( in_array( $branch, $ichiryu[ $month ], true ) ) {
		$hits[] = 'ichiryu';
	}
	if ( 5 === $n ) {
		$hits[] = 'tsuchinotomi';
	} elseif ( 5 === $branch ) {
		$hits[] = 'mi';
	}
	if ( 2 === $branch ) {
		$hits[] = 'tora';
	}
	if ( 0 === $n ) {
		$hits[] = 'kinoene';
	}
	return $hits;
}

/** $from から $days 日の吉日 */
function ut_kichijitsu_range( DateTimeImmutable $from, $days ) {
	$out = array();
	for ( $i = 0; $i < $days; $i++ ) {
		$d    = $from->setTime( 12, 0 )->modify( "+{$i} day" );
		$hits = ut_kichijitsu( $d );
		if ( $hits ) {
			$out[] = array( 'date' => $d, 'hits' => $hits );
		}
	}
	return $out;
}

/** 次の「その種類」の吉日 */
function ut_next_kichijitsu( $type, DateTimeImmutable $from, $within = 400 ) {
	for ( $i = 0; $i < $within; $i++ ) {
		$d = $from->setTime( 12, 0 )->modify( "+{$i} day" );
		if ( in_array( $type, ut_kichijitsu( $d ), true ) ) {
			return $d;
		}
	}
	return null;
}

/* ==================================================================
 * イラスト(SVG)
 * ================================================================== */
function ut_kichijitsu_svg( $type, $size = 72 ) {
	$s = (int) $size;
	switch ( $type ) {
		case 'ichiryu': // 稲穂
			$grains = '';
			for ( $i = 0; $i < 7; $i++ ) {
				$y       = 14 + $i * 6;
				$grains .= '<ellipse cx="' . ( 34 - $i * 0.6 ) . '" cy="' . $y . '" rx="4" ry="6.5" transform="rotate(-25 ' . ( 34 - $i * 0.6 ) . ' ' . $y . ')"/>'
					. '<ellipse cx="' . ( 44 - $i * 0.4 ) . '" cy="' . ( $y + 3 ) . '" rx="4" ry="6.5" transform="rotate(25 ' . ( 44 - $i * 0.4 ) . ' ' . ( $y + 3 ) . ')"/>';
			}
			return '<svg viewBox="0 0 80 80" width="' . $s . '" height="' . $s . '" aria-hidden="true"><circle cx="40" cy="40" r="38" fill="#2b1a4a"/><path d="M39 12 Q42 45 37 72" stroke="#c9a227" stroke-width="2.5" fill="none"/><g fill="#e9c46a">' . $grains . '</g><path d="M37 60 Q22 52 18 40" stroke="#7fb069" stroke-width="2.5" fill="none"/></svg>';
		case 'tensha': // 光の差す天
			return '<svg viewBox="0 0 80 80" width="' . $s . '" height="' . $s . '" aria-hidden="true"><circle cx="40" cy="40" r="38" fill="#2b1a4a"/><g stroke="#f3d36b" stroke-width="3" stroke-linecap="round"><line x1="40" y1="12" x2="40" y2="22"/><line x1="20" y1="20" x2="26" y2="27"/><line x1="60" y1="20" x2="54" y2="27"/><line x1="12" y1="40" x2="22" y2="40"/><line x1="68" y1="40" x2="58" y2="40"/></g><circle cx="40" cy="42" r="13" fill="#f3d36b"/><path d="M16 60 Q28 52 40 60 T64 60" stroke="#b39ddb" stroke-width="3" fill="none"/></svg>';
		case 'tora': // 虎の縞と小判
			return '<svg viewBox="0 0 80 80" width="' . $s . '" height="' . $s . '" aria-hidden="true"><circle cx="40" cy="40" r="38" fill="#2b1a4a"/><ellipse cx="40" cy="42" rx="20" ry="26" fill="#e9b04a"/><g stroke="#2b1a4a" stroke-width="3" stroke-linecap="round"><path d="M24 30 L32 34"/><path d="M24 42 L33 43"/><path d="M24 54 L32 51"/><path d="M56 30 L48 34"/><path d="M56 42 L47 43"/><path d="M56 54 L48 51"/></g><text x="40" y="48" text-anchor="middle" font-size="16" font-weight="700" fill="#2b1a4a">寅</text></svg>';
		case 'mi':
		case 'tsuchinotomi': // 白蛇
			return '<svg viewBox="0 0 80 80" width="' . $s . '" height="' . $s . '" aria-hidden="true"><circle cx="40" cy="40" r="38" fill="#2b1a4a"/><path d="M22 58 C22 40 58 48 58 32 C58 22 46 18 40 24" stroke="#f5f0ff" stroke-width="8" fill="none" stroke-linecap="round"/><circle cx="42" cy="23" r="5" fill="#f5f0ff"/><circle cx="43" cy="22" r="1.3" fill="#d6336c"/>' . ( 'tsuchinotomi' === $type ? '<circle cx="60" cy="58" r="6" fill="#c9a227"/>' : '' ) . '</svg>';
		default: // 甲子: 始まりの星
			return '<svg viewBox="0 0 80 80" width="' . $s . '" height="' . $s . '" aria-hidden="true"><circle cx="40" cy="40" r="38" fill="#2b1a4a"/><path d="M40 16 L46 34 L64 34 L50 45 L55 63 L40 52 L25 63 L30 45 L16 34 L34 34 Z" fill="#f3d36b"/></svg>';
	}
}

/* ==================================================================
 * 表示
 * ================================================================== */

function ut_md_w( DateTimeInterface $d ) {
	return $d->format( 'n月j日' ) . '(' . ut_weekday( $d ) . ')';
}

/** トップ用のひとこと(今日の吉日、なければ次の一粒万倍日) */
function ut_kichijitsu_badge_html() {
	$today = ut_today();
	$types = ut_kichijitsu_types();
	$hits  = ut_kichijitsu( $today );
	$url   = home_url( '/kichijitsu/' );
	if ( $hits ) {
		$names = implode( '・', array_map( fn( $k ) => $types[ $k ]['name'], $hits ) );
		return '<p class="ut-kichi-badge is-today"><a href="' . esc_url( $url ) . '">✦ 今日は<b>' . esc_html( $names ) . '</b></a></p>';
	}
	$next = ut_next_kichijitsu( 'ichiryu', $today );
	return $next ? '<p class="ut-kichi-badge"><a href="' . esc_url( $url ) . '">次の一粒万倍日は <b>' . esc_html( ut_md_w( $next ) ) . '</b></a></p>' : '';
}
add_shortcode( 'uranai_kichijitsu_badge', 'ut_kichijitsu_badge_html' );

function ut_kichijitsu_page_html() {
	$today = ut_today();
	$types = ut_kichijitsu_types();
	$hits  = ut_kichijitsu( $today );

	$out  = '<article class="ut-article ut-kichi">';
	$out .= '<h1 class="ut-page-title">開運日・吉日カレンダー</h1>';
	$out .= '<p>一粒万倍日・天赦日・寅の日・巳の日など、暦の上で縁起が良いとされる日を、毎日自動で計算してお知らせします。始めごとやお財布の新調、目標を書き出す日を選ぶ参考にしてくださいね。</p>';

	// 今日
	$out .= '<section class="ut-kichi-today"><p class="ut-kichi-date">' . esc_html( $today->format( 'Y年n月j日' ) . '(' . ut_weekday( $today ) . ')・' . ut_ganzhi_name( ut_day_ganzhi( $today ) ) . 'の日' ) . '</p>';
	if ( $hits ) {
		$out .= '<p class="ut-kichi-head">今日は <b>' . esc_html( implode( '・', array_map( fn( $k ) => $types[ $k ]['name'], $hits ) ) ) . '</b></p>';
		$out .= '<div class="ut-kichi-icons">';
		foreach ( $hits as $k ) {
			$out .= ut_kichijitsu_svg( $k, 64 );
		}
		$out .= '</div>';
	} else {
		$next = ut_next_kichijitsu( 'ichiryu', $today );
		$out .= '<p class="ut-kichi-head">今日は特別な吉日ではありません</p>';
		if ( $next ) {
			$out .= '<p>次の一粒万倍日は <b>' . esc_html( ut_md_w( $next ) ) . '</b> です。</p>';
		}
	}
	$out .= '</section>';

	// これからの吉日(60日)
	$out .= '<section><h2 class="ut-title">これからの吉日(60日分)</h2><ul class="ut-kichi-list">';
	foreach ( ut_kichijitsu_range( $today, 60 ) as $e ) {
		$is_today = $e['date']->format( 'Ymd' ) === $today->format( 'Ymd' );
		$big      = in_array( 'tensha', $e['hits'], true ) || count( $e['hits'] ) >= 2;
		$out     .= '<li class="' . ( $is_today ? 'is-today ' : '' ) . ( $big ? 'is-big' : '' ) . '"><span class="ut-kichi-day">' . esc_html( ut_md_w( $e['date'] ) ) . '</span><span class="ut-kichi-tags">';
		foreach ( $e['hits'] as $k ) {
			$out .= '<span class="ut-kichi-tag tag-' . esc_attr( $k ) . '">' . esc_html( $types[ $k ]['name'] ) . '</span>';
		}
		$out .= '</span></li>';
	}
	$out .= '</ul><p class="ut-note">※複数の吉日が重なる日や天赦日は、特に縁起が良いとされます。</p></section>';

	// 解説
	$out .= '<section><h2 class="ut-title">吉日の意味</h2>';
	foreach ( $types as $k => $t ) {
		$next = ut_next_kichijitsu( $k, $today->modify( '+1 day' ) );
		$out .= '<div class="ut-kichi-card" id="' . esc_attr( $k ) . '">' . ut_kichijitsu_svg( $k, 72 ) . '<div>';
		$out .= '<h3>' . esc_html( $t['name'] ) . '<small>' . esc_html( $t['read'] ) . '</small></h3>';
		$out .= '<p class="ut-kichi-short">' . esc_html( $t['short'] ) . '</p>';
		$out .= '<p>' . esc_html( $t['text'] ) . '</p>';
		$out .= '<p class="ut-kichi-do"><b>この日にいいこと</b>' . esc_html( $t['do'] ) . '</p>';
		if ( ! empty( $t['dont'] ) ) {
			$out .= '<p class="ut-kichi-dont"><b>控えたいこと</b>' . esc_html( $t['dont'] ) . '</p>';
		}
		if ( $next ) {
			$out .= '<p class="ut-kichi-next">次の' . esc_html( $t['name'] ) . ':<b>' . esc_html( ut_md_w( $next ) ) . '</b></p>';
		}
		$out .= '</div></div>';
	}
	$out .= '</section>';
	$out .= '<p class="ut-note">※暦の吉日は、古くからの言い伝えにもとづく目安です。結果を保証するものではありません。六曜や不成就日など、旧暦にもとづく暦注はこのページでは扱っていません。</p>';
	return $out . '</article>';
}

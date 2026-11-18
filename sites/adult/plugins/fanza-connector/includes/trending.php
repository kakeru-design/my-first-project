<?php
/**
 * 今週の注目女優(自動)
 * FANZAの人気作品(sort=rank)上位100本の出演者を数え、出演作の多い順に選ぶ。
 * 「わたしのベスト5」(イチオシ女優)に入っている人は除く。
 * 集計結果は期間ごとに固定(週: 月曜0時に入れ替え / 日: 毎日0時)。
 *
 * ショートコード: [fanza_trending title="今週の注目女優" count="5" period="week"]
 *   period: week(既定)| day
 */

defined( 'ABSPATH' ) || exit;

/** 期間のキー(サイトのタイムゾーン基準) */
function fz_trend_period_key( $period ) {
	$now = new DateTimeImmutable( 'now', wp_timezone() );
	return 'day' === $period ? $now->format( 'Y-m-d' ) : $now->modify( 'monday this week' )->format( 'Y-m-d' );
}

/**
 * @return array<int, array{id: string, name: string, count: int, item: array}>
 */
function fz_trending_actresses( $count = 5, $period = 'week' ) {
	$key    = 'fz_trend_' . $period . '_' . fz_trend_period_key( $period );
	$cached = get_transient( $key );
	if ( false === $cached ) {
		$items = fz_items( array( 'sort' => 'rank', 'hits' => 100 ) );
		if ( is_wp_error( $items ) || ! $items ) {
			return array();
		}
		$tally = array();
		foreach ( $items as $rank => $item ) {
			foreach ( (array) ( $item['iteminfo']['actress'] ?? array() ) as $a ) {
				$id = (string) ( $a['id'] ?? '' );
				if ( '' === $id || ! ctype_digit( $id ) ) {
					continue;
				}
				if ( ! isset( $tally[ $id ] ) ) {
					$tally[ $id ] = array( 'id' => $id, 'name' => $a['name'] ?? '', 'count' => 0, 'best' => $rank, 'item' => $item );
				}
				++$tally[ $id ]['count'];
			}
		}
		// 出演作の多い順、同数なら人気順位の高い作品に出ている順
		usort( $tally, fn( $x, $y ) => array( $y['count'], $x['best'] ) <=> array( $x['count'], $y['best'] ) );
		$cached = array_slice( $tally, 0, 30 );
		set_transient( $key, $cached, 'day' === $period ? DAY_IN_SECONDS : WEEK_IN_SECONDS );
	}
	$exclude = array_filter( array_map( fn( $r ) => (string) ( $r['actress_id'] ?? '' ), fz_featured_rows() ) );
	$list    = array_values( array_filter( $cached, fn( $t ) => ! in_array( $t['id'], $exclude, true ) ) );
	return array_slice( $list, 0, max( 1, (int) $count ) );
}

function fz_trending_html( $atts = array() ) {
	$a    = shortcode_atts(
		array(
			'title'  => '',
			'sub'    => '',
			'count'  => 5,
			'period' => 'week',
		),
		$atts
	);
	$period = 'day' === $a['period'] ? 'day' : 'week';
	$title  = $a['title'] ? $a['title'] : ( 'day' === $period ? '今日の注目女優' : '今週の注目女優' );
	$sub    = $a['sub'] ? $a['sub'] : ( 'day' === $period ? 'いま人気の作品に多く出演している女優(毎日更新)' : '人気作品に多く出演している女優(毎週月曜更新)' );
	$list   = fz_trending_actresses( (int) $a['count'], $period );
	if ( ! $list ) {
		return current_user_can( 'manage_options' ) ? fz_admin_notice_html( '注目女優を集計できませんでした(API設定を確認してください)。' ) : '';
	}

	$out = '<section class="fz-featured fz-trending">' . fz_section_head_html( $title, $sub ) . '<div class="fz-featured-list">';
	foreach ( $list as $i => $t ) {
		$actress = fz_actress( $t['id'] );
		$name    = $actress['name'] ?? $t['name'];
		$photo   = $actress['imageURL']['large'] ?? ( $actress['imageURL']['small'] ?? '' );
		$more    = $actress['listURL']['digital'] ?? ( $t['item']['affiliateURL'] ?? '' );
		$item    = $t['item'];

		$out .= '<article class="fz-card">';
		$out .= '<div class="fz-card-photo"><span class="fz-card-badge fz-card-badge-trend">' . ( 'day' === $period ? '今日' : '今週' ) . 'の注目 ' . ( $i + 1 ) . '</span>';
		if ( $photo ) {
			$out .= '<img src="' . esc_url( $photo ) . '" alt="' . esc_attr( $name ) . '" loading="lazy">';
		}
		$out .= '</div><div class="fz-card-body">';
		$out .= '<h3 class="fz-card-name">' . esc_html( $name ) . '</h3>';
		if ( ! empty( $actress['ruby'] ) ) {
			$out .= '<p class="fz-card-ruby">' . esc_html( $actress['ruby'] ) . '</p>';
		}
		$out .= '<p class="fz-card-comment">人気作品の上位100本のうち、<b>' . (int) $t['count'] . '本</b>に出演しています。</p>';
		if ( $item ) {
			$out .= '<a class="fz-card-item" href="' . esc_url( $item['affiliateURL'] ?? '' ) . '" ' . fz_link_attrs() . '>'
				. '<img src="' . esc_url( $item['imageURL']['list'] ?? ( $item['imageURL']['small'] ?? '' ) ) . '" alt="" loading="lazy">'
				. '<span><span class="fz-card-item-label">いま人気の出演作</span>' . esc_html( wp_trim_words( $item['title'] ?? '', 40, '…' ) ) . '</span></a>';
		}
		if ( $more ) {
			$out .= '<a class="atk-button fz-card-cta" href="' . esc_url( $more ) . '" ' . fz_link_attrs() . '>出演作品をもっと見る</a>';
		}
		$out .= '</div></article>';
	}
	return $out . '</div></section>';
}
add_shortcode( 'fanza_trending', 'fz_trending_html' );

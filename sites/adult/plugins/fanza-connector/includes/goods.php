<?php
/**
 * FANZA通販のアダルトグッズ(DMM Webサービス ItemList: site=FANZA, service=mono, floor=goods)
 *
 * ショートコード:
 *   [fanza_goods title="人気のアダルトグッズ" sort="rank" hits="8"]
 *   [fanza_goods title="新着グッズ" sort="date"]
 *   [fanza_goods keyword="ローション" hits="12"]
 *   [fanza_goods genre_id="12345"]        ジャンルIDで絞り込み
 * API未設定・0件のときは、一般の閲覧者には何も表示しない(管理者には案内)。
 */

defined( 'ABSPATH' ) || exit;

/** グッズのカード */
function fz_goods_card_html( $item ) {
	$url   = $item['affiliateURL'] ?? '';
	$pr    = fz_item_prices( $item );
	$img   = $item['imageURL']['large'] ?? ( $item['imageURL']['list'] ?? ( $item['imageURL']['small'] ?? '' ) );
	$maker = $item['iteminfo']['maker'][0]['name'] ?? ( $item['iteminfo']['label'][0]['name'] ?? '' );

	$out  = '<article class="fz-item fz-goods">';
	$out .= '<a class="fz-item-link" href="' . esc_url( $url ) . '" ' . fz_link_attrs() . '>';
	$out .= '<div class="fz-item-thumb fz-goods-thumb">' . ( $img ? '<img src="' . esc_url( $img ) . '" alt="" loading="lazy">' : '' ) . '</div>';
	$out .= '<div class="fz-item-body">';
	if ( $pr['off'] ) {
		$out .= '<span class="fz-off">' . (int) $pr['off'] . '%OFF</span>';
	}
	$out .= '<h3 class="fz-item-title">' . esc_html( $item['title'] ?? '' ) . '</h3>';
	if ( $maker ) {
		$out .= '<p class="fz-code">' . esc_html( $maker ) . '</p>';
	}
	if ( $pr['price'] ) {
		$out .= '<p class="fz-price">' . ( $pr['off'] ? '<del>' . number_format( $pr['list'] ) . '円</del> ' : '' )
			. '<strong>' . number_format( $pr['price'] ) . '円</strong>' . ( false !== strpos( (string) ( $item['prices']['price'] ?? '' ), '~' ) ? '〜' : '' ) . '</p>';
	}
	$out .= fz_stars_html( $item['review']['average'] ?? 0, $item['review']['count'] ?? 0 );
	$out .= '</div></a>';
	$out .= '<a class="fz-item-cta" href="' . esc_url( $url ) . '" ' . fz_link_attrs() . '>FANZA通販で見る<small>PR</small></a>';
	return $out . '</article>';
}

function fz_goods_html( $atts = array() ) {
	$a    = shortcode_atts(
		array(
			'title'    => '人気のアダルトグッズ',
			'sub'      => 'FANZA通販で売れているグッズ',
			'more'     => '',
			'keyword'  => '',
			'genre_id' => '',
			'sort'     => 'rank',
			'hits'     => 8,
			'floor'    => 'goods',
		),
		$atts
	);
	$args = array(
		'service' => 'mono',
		'floor'   => preg_replace( '/[^a-z_]/', '', $a['floor'] ),
		'keyword' => $a['keyword'],
		'sort'    => in_array( $a['sort'], array( 'rank', 'date', 'review', 'price', '-price', 'match' ), true ) ? $a['sort'] : 'rank',
		'hits'    => max( 1, min( 100, (int) $a['hits'] ) ),
	);
	if ( $a['genre_id'] ) {
		$args['article']    = 'genre';
		$args['article_id'] = preg_replace( '/\D/', '', $a['genre_id'] );
	}
	$items = fz_items( $args );
	if ( is_wp_error( $items ) || ! $items ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		return '<section class="fz-section">' . fz_section_head_html( $a['title'], $a['sub'] )
			. fz_admin_notice_html( is_wp_error( $items ) ? $items->get_error_message() : 'グッズが見つかりませんでした(条件を確認してください)。' ) . '</section>';
	}
	$out = '<section class="fz-section fz-goods-section">' . fz_section_head_html( $a['title'], $a['sub'], $a['more'] ) . '<div class="fz-items fz-goods-list">';
	foreach ( $items as $item ) {
		$out .= fz_goods_card_html( $item );
	}
	return $out . '</div></section>';
}
add_shortcode( 'fanza_goods', 'fz_goods_html' );

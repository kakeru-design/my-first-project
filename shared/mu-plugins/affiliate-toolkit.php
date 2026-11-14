<?php
/**
 * Plugin Name: Affiliate Toolkit
 * Description: 2サイト共通のアフィリエイト機能(PR表記・リンク管理・ボタン・ランキング)。mu-plugins に設置して使う。
 * Version: 0.2.0
 *
 * 案件データはテーマ側で `atk_links` フィルタから渡す(子テーマの affiliate-links.json)。
 *   [aff_button slug="xxx"]            … CTAボタン
 *   [aff_ranking slugs="a,b,c"]        … ランキングボックス
 *   [aff_table slugs="a,b,c"]          … 比較表
 *   [pr_notice]                        … PR表記(自動挿入もされる)
 * リンクは /go/{slug} 経由で302リダイレクトされるので、ASPが決まったら JSON の url を差し替えるだけで全記事に反映される。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 案件データを取得する。 */
function atk_links() {
	static $links = null;
	if ( null === $links ) {
		$links = apply_filters( 'atk_links', array() );
	}
	return $links;
}

function atk_link( $slug ) {
	$links = atk_links();
	return isset( $links[ $slug ] ) ? $links[ $slug ] : null;
}

/** URLが未設定(仮置き)かどうか。 */
function atk_is_placeholder( $link ) {
	return empty( $link['url'] ) || false !== strpos( $link['url'], 'example.com' );
}

function atk_go_url( $slug ) {
	return home_url( '/go/' . rawurlencode( $slug ) . '/' );
}

/* ------------------------------------------------------------------
 * /go/{slug} リダイレクト
 * ------------------------------------------------------------------ */
add_action(
	'template_redirect',
	function () {
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( '' !== $home && 0 === strpos( $path, $home ) ) {
			$path = trim( substr( $path, strlen( $home ) ), '/' );
		}
		if ( ! preg_match( '#^go/([a-z0-9_-]+)$#i', $path, $m ) ) {
			return;
		}
		$link = atk_link( $m[1] );
		if ( ! $link || atk_is_placeholder( $link ) ) {
			status_header( 404 );
			nocache_headers();
			wp_die( esc_html__( 'このリンクはまだ準備中です。', 'atk' ), '', array( 'response' => 404 ) );
		}
		header( 'X-Robots-Tag: noindex, nofollow' );
		do_action( 'atk_before_redirect', $m[1], $link );
		wp_redirect( $link['url'], 302, 'Affiliate Toolkit' ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	},
	0
);

/* /go/ をクローラーに辿らせない */
add_filter(
	'robots_txt',
	function ( $output ) {
		return $output . "Disallow: /go/\n";
	}
);

/* ------------------------------------------------------------------
 * PR表記(景品表示法のステマ規制対応)
 * ------------------------------------------------------------------ */
function atk_pr_notice_html() {
	$text = apply_filters(
		'atk_pr_notice_text',
		'本記事にはアフィリエイト広告(PR)が含まれています。リンク経由でサービスを利用・購入されると、当サイトに報酬が支払われることがあります。'
	);
	return '<p class="atk-pr-notice"><span class="atk-pr-label">PR</span>' . esc_html( $text ) . '</p>';
}

add_shortcode( 'pr_notice', 'atk_pr_notice_html' );

add_filter(
	'the_content',
	function ( $content ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( has_shortcode( $content, 'pr_notice' ) || get_post_meta( get_the_ID(), 'atk_no_pr', true ) ) {
			return $content;
		}
		return atk_pr_notice_html() . $content;
	},
	5
);

/* ------------------------------------------------------------------
 * ショートコード
 * ------------------------------------------------------------------ */
function atk_button_html( $slug, $label = '' ) {
	$link = atk_link( $slug );
	if ( ! $link ) {
		return current_user_can( 'edit_posts' )
			? '<p class="atk-warning">[aff] 未登録のslug: ' . esc_html( $slug ) . '</p>'
			: '';
	}
	$label = $label ? $label : ( $link['cta'] ?? ( $link['name'] . 'の公式サイトを見る' ) );
	$html  = sprintf(
		'<a class="atk-button" href="%s" rel="sponsored nofollow noopener" target="_blank">%s</a>',
		esc_url( atk_go_url( $slug ) ),
		esc_html( $label )
	);
	if ( atk_is_placeholder( $link ) && current_user_can( 'edit_posts' ) ) {
		$html .= '<span class="atk-warning">※URL未設定(仮置き)</span>';
	}
	return '<div class="atk-button-wrap">' . $html . '</div>';
}

add_shortcode(
	'aff_button',
	function ( $atts ) {
		$atts = shortcode_atts( array( 'slug' => '', 'label' => '' ), $atts );
		return atk_button_html( $atts['slug'], $atts['label'] );
	}
);

function atk_stars( $rating ) {
	$rating = max( 0, min( 5, (float) $rating ) );
	$full   = (int) round( $rating );
	return '<span class="atk-stars" aria-label="評価 ' . esc_attr( $rating ) . ' / 5">'
		. str_repeat( '★', $full ) . str_repeat( '☆', 5 - $full )
		. ' <span class="atk-stars-num">' . esc_html( number_format( $rating, 1 ) ) . '</span></span>';
}

function atk_slugs( $csv ) {
	return array_values( array_filter( array_map( 'trim', explode( ',', (string) $csv ) ) ) );
}

add_shortcode(
	'aff_ranking',
	function ( $atts ) {
		$atts = shortcode_atts( array( 'slugs' => '' ), $atts );
		$out  = '<ol class="atk-ranking">';
		$rank = 0;
		foreach ( atk_slugs( $atts['slugs'] ) as $slug ) {
			$link = atk_link( $slug );
			if ( ! $link ) {
				continue;
			}
			++$rank;
			$out .= '<li class="atk-rank-item atk-rank-' . $rank . '">';
			$out .= '<div class="atk-rank-head"><span class="atk-rank-num">' . $rank . '</span>';
			$out .= '<span class="atk-rank-name">' . esc_html( $link['name'] ) . '</span>';
			if ( isset( $link['rating'] ) ) {
				$out .= atk_stars( $link['rating'] );
			}
			$out .= '</div>';
			if ( ! empty( $link['catch'] ) ) {
				$out .= '<p class="atk-rank-catch">' . esc_html( $link['catch'] ) . '</p>';
			}
			if ( ! empty( $link['features'] ) ) {
				$out .= '<ul class="atk-rank-features">';
				foreach ( (array) $link['features'] as $f ) {
					$out .= '<li>' . esc_html( $f ) . '</li>';
				}
				$out .= '</ul>';
			}
			$out .= atk_button_html( $slug );
			$out .= '</li>';
		}
		return $out . '</ol>';
	}
);

add_shortcode(
	'aff_table',
	function ( $atts ) {
		$atts  = shortcode_atts( array( 'slugs' => '' ), $atts );
		$slugs = atk_slugs( $atts['slugs'] );
		$rows  = apply_filters( 'atk_table_columns', array( 'price' => '料金', 'bonus' => '特典' ) );
		$out   = '<div class="atk-table-wrap"><table class="atk-table"><thead><tr><th></th>';
		foreach ( $slugs as $slug ) {
			$link = atk_link( $slug );
			$out .= '<th>' . esc_html( $link ? $link['name'] : $slug ) . '</th>';
		}
		$out .= '</tr></thead><tbody><tr><th>評価</th>';
		foreach ( $slugs as $slug ) {
			$link = atk_link( $slug );
			$out .= '<td>' . ( isset( $link['rating'] ) ? atk_stars( $link['rating'] ) : '-' ) . '</td>';
		}
		$out .= '</tr>';
		foreach ( $rows as $key => $label ) {
			$out .= '<tr><th>' . esc_html( $label ) . '</th>';
			foreach ( $slugs as $slug ) {
				$link = atk_link( $slug );
				$out .= '<td>' . esc_html( $link[ $key ] ?? '-' ) . '</td>';
			}
			$out .= '</tr>';
		}
		$out .= '<tr><th>公式</th>';
		foreach ( $slugs as $slug ) {
			$out .= '<td>' . atk_button_html( $slug, '詳細' ) . '</td>';
		}
		return $out . '</tr></tbody></table></div>';
	}
);

/* 管理バーに仮置きリンクの件数を表示 */
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$pending = count( array_filter( atk_links(), 'atk_is_placeholder' ) );
		if ( $pending ) {
			$bar->add_node(
				array(
					'id'    => 'atk-pending',
					'title' => '仮置きリンク: ' . $pending . '件',
				)
			);
		}
	},
	100
);

/* ------------------------------------------------------------------
 * Google アナリティクス(GA4)
 * 設定 → 一般 の「GA4 測定ID」に G-XXXXXXX を入れると計測タグを出力する。
 * - ログイン中の編集者・管理者は計測しない(自分のアクセスを除外)
 * - URL の birth パラメータ(占いの生年月日)は送信前に取り除く
 * - アフィリエイトリンクのクリックを affiliate_click イベントとして記録
 * ------------------------------------------------------------------ */
add_action(
	'admin_init',
	function () {
		register_setting(
			'general',
			'atk_ga_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $v ) {
					$v = strtoupper( trim( (string) $v ) );
					return preg_match( '/^G-[A-Z0-9]{4,20}$/', $v ) ? $v : '';
				},
				'default'           => '',
			)
		);
		add_settings_field(
			'atk_ga_id',
			'GA4 測定ID',
			function () {
				printf(
					'<input name="atk_ga_id" id="atk_ga_id" type="text" class="regular-text" placeholder="G-XXXXXXXXXX" value="%s"><p class="description">Google アナリティクスの測定IDを入れると計測を開始します。ログイン中の編集者・管理者のアクセスは計測しません。</p>',
					esc_attr( get_option( 'atk_ga_id', '' ) )
				);
			},
			'general'
		);
	}
);

add_action(
	'wp_head',
	function () {
		$id = get_option( 'atk_ga_id', '' );
		if ( ! $id || current_user_can( 'edit_posts' ) ) {
			return;
		}
		?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $id ); ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
(function () {
	var u = new URL(location.href);
	u.searchParams.delete('birth');
	gtag('config', <?php echo wp_json_encode( $id ); ?>, { page_location: u.toString() });
})();
document.addEventListener('click', function (e) {
	var a = e.target.closest && e.target.closest('a[href]');
	if (!a) return;
	var href = a.getAttribute('href') || '';
	if (!/\/go\/|dmm\.co\.jp|dmm\.com/.test(href)) return;
	var sec = a.closest('section');
	var head = sec && sec.querySelector('h2');
	var card = a.closest('.fz-item, .fz-card, .atk-rank-item');
	var name = card && card.querySelector('.fz-item-title, .fz-card-name, .atk-rank-name');
	gtag('event', 'affiliate_click', {
		placement: head ? head.textContent.trim().slice(0, 50) : 'その他',
		item_name: name ? name.textContent.trim().slice(0, 80) : (a.textContent || '').trim().slice(0, 80),
		link_domain: (href.match(/^https?:\/\/([^\/]+)/) || [, location.hostname])[1]
	});
});
</script>
		<?php
	},
	2
);

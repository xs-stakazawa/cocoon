<?php //ダッシュボード上部に表示するメッセージ
/**
 * Cocoon WordPress Theme
 * @author: yhira
 * @link: https://wp-cocoon.com/
 * @license: http://www.gnu.org/licenses/gpl-2.0.html GPL v2 or later
 * @reference: https://github.com/rocket-martue/Hello-Musou
 */
if ( !defined( 'ABSPATH' ) ) exit;

if ( !function_exists( 'dashboard_message_get_text' ) ):
function dashboard_message_get_text() {
	//メッセージファイルを取得
	$filename = get_cocoon_template_directory() . '/lib/dashboard-message.txt';
	$messages = wp_filesystem_get_contents( $filename );

	//改行で分ける
	$messages = explode( "\n", $messages );

	//一行だけを表示する
	return wptexturize( $messages[ rand( 0, count( $messages ) - 1 ) ] );
}
endif;


//メッセージHTMLの出力
add_action( 'admin_notices', 'generate_dashboard_message' );
if ( !function_exists( 'generate_dashboard_message' ) ):
function generate_dashboard_message() {
	$chosen = dashboard_message_get_text();
	$lang   = '';
	if ( 'en_' !== substr( get_user_locale(), 0, 3 ) ) {
		$lang = ' lang="en"';
	}

	printf(
		'<p id="dashboard-message"><span class="screen-reader-text">%s </span><span dir="ltr"%s>%s</span></p>',
		__( 'Guidance on COVID-19:', THEME_NAME),
		$lang,
		$chosen
	);
}
endif;

//メッセージエリアのCSS出力
add_action( 'admin_head', 'dashboard_message_css' );
if ( !function_exists( 'dashboard_message_css' ) ):
function dashboard_message_css() {
	echo "
	<style type='text/css'>
	#dashboard-message {
		display: block;
		width: fit-content;
		margin-inline-start: auto;
		padding: 5px 10px;
		margin-block: 0;
		font-size: 12px;
		line-height: 1.6666;
	}
	.block-editor-page #dashboard-message {
		display: none;
	}
	@media screen and (max-width: 782px) {
		#dashboard-message {
			width: auto;
			margin-inline-start: 0;
			padding-inline: 0;
		}
	}
	</style>
	";
}
endif;


// ============================================================
// Xwrite プロモーション通知バー（記事数連動方式）
//
// 表示ロジック:
//   - サイトの累計公開記事数に応じて表示するメッセージを切り替える
//     -  1〜15記事: 案1（初心者〜中級者向け）
//     - 16〜49記事: 案2（運営が軌道に乗ってきた中級者向け）
//     -  50記事以上: 案3（他テーマ検討中の層向け）
//   - ユーザーが「通知を表示しない」をクリックすると、
//     そのメッセージの非表示フラグを user_meta に保存し、永久に非表示になる
//   - 記事数が増えて別の案に切り替わった場合、新しい案は再び表示される
//     （各案ごとに独立して非表示フラグを管理しているため）
// ============================================================

if ( ! defined( 'XWRITE_PROMO_MSG1_META' ) ) {
	define( 'XWRITE_PROMO_MSG1_META', 'cocoon_xwrite_msg1_dismissed' ); //  1〜15記事
}
if ( ! defined( 'XWRITE_PROMO_MSG2_META' ) ) {
	define( 'XWRITE_PROMO_MSG2_META', 'cocoon_xwrite_msg2_dismissed' ); // 16〜49記事
}
if ( ! defined( 'XWRITE_PROMO_MSG3_META' ) ) {
	define( 'XWRITE_PROMO_MSG3_META', 'cocoon_xwrite_msg3_dismissed' ); // 50記事以上
}

// 表示すべきメッセージ ID を返す（静的キャッシュ付き）: 'msg1' | 'msg2' | 'msg3' | null
if ( ! function_exists( 'xwrite_promo_get_message_id' ) ):
function xwrite_promo_get_message_id() {
	static $cache = 'unset';
	if ( $cache !== 'unset' ) return $cache;

	$post_count = (int) wp_count_posts()->publish;
	$user_id    = get_current_user_id();

	if ( $post_count >= 50 ) {
		$msg_id   = 'msg3';
		$meta_key = XWRITE_PROMO_MSG3_META;
	} elseif ( $post_count >= 16 ) {
		$msg_id   = 'msg2';
		$meta_key = XWRITE_PROMO_MSG2_META;
	} elseif ( $post_count >= 1 ) {
		$msg_id   = 'msg1';
		$meta_key = XWRITE_PROMO_MSG1_META;
	} else {
		// 記事0件 → 表示なし
		$cache = null;
		return $cache;
	}

	// ユーザーがそのメッセージを既に非表示にしていれば null を返す
	$cache = get_user_meta( $user_id, $meta_key, true ) ? null : $msg_id;

	return $cache;
}
endif;

// 管理画面の通知バーを出力
add_action( 'admin_notices', 'xwrite_promo_render_notice' );
if ( ! function_exists( 'xwrite_promo_render_notice' ) ):
function xwrite_promo_render_notice() {
	$msg_id = xwrite_promo_get_message_id();
	if ( ! $msg_id ) return;

	// @todo 公式サイト・移行手順の実際の URL に変更してください
	$official_url  = 'https://xwrite.jp/';
	$migration_url = 'https://xwrite.jp/migration/';

	$messages = [
		'msg1' => 'プロが作ったデモサイトを丸ごと再現！コピー&ペーストの簡単操作で、あなたのブログが本格サイトに。Cocoonの記事も安全に引き継げるテーマ『Xwrite』を試してみませんか？',
		'msg2' => '記事の執筆に慣れてきたらブログの見た目も次のステージへ。Cocoonの次の選択肢としてぴったりな、公式移行プラグイン完備のテーマ『Xwrite』でデザインを一新しませんか？',
		'msg3' => 'Cocoonからの乗り換えを検討中なら、公式移行プラグイン完備のテーマ『Xwrite』が最短ルート。記事の崩れを最小限に抑え、理想のデザインを今すぐ手に入れませんか？',
	];

	$nonce = wp_create_nonce( 'xwrite_promo_dismiss' );
	?>
	<div id="xwrite-promo-notice" class="notice notice-warning is-dismissible"
		data-msg-id="<?php echo esc_attr( $msg_id ); ?>"
		data-nonce="<?php echo esc_attr( $nonce ); ?>">
		<p>
			<?php echo esc_html( $messages[ $msg_id ] ); ?><br>
			<span class="xwrite-promo-links">
			[<a href="<?php echo esc_url( $official_url ); ?>" target="_blank" rel="noopener noreferrer">公式サイトへ</a>]
			[<a href="<?php echo esc_url( $migration_url ); ?>" target="_blank" rel="noopener noreferrer">移行の手順を確認</a>]
			[<a href="#" class="xwrite-promo-dismiss">通知を表示しない</a>]
			</span>
		</p>
	</div>
	<?php
}
endif;

// AJAX ハンドラー: 非表示フラグを user_meta に保存
add_action( 'wp_ajax_xwrite_promo_dismiss', 'xwrite_promo_ajax_dismiss' );
if ( ! function_exists( 'xwrite_promo_ajax_dismiss' ) ):
function xwrite_promo_ajax_dismiss() {
	check_ajax_referer( 'xwrite_promo_dismiss', 'nonce' );

	$msg_id  = isset( $_POST['msg_id'] ) ? sanitize_key( $_POST['msg_id'] ) : '';
	$user_id = get_current_user_id();

	$meta_map = [
		'msg1' => XWRITE_PROMO_MSG1_META,
		'msg2' => XWRITE_PROMO_MSG2_META,
		'msg3' => XWRITE_PROMO_MSG3_META,
	];

	if ( isset( $meta_map[ $msg_id ] ) ) {
		update_user_meta( $user_id, $meta_map[ $msg_id ], '1' );
		wp_send_json_success();
	}

	wp_send_json_error( 'invalid_msg_id' );
}
endif;

// インライン JS: DOM 移動（h1 の前に移動）＋「通知を表示しない」の挙動
add_action( 'admin_footer', 'xwrite_promo_inline_js' );
if ( ! function_exists( 'xwrite_promo_inline_js' ) ):
function xwrite_promo_inline_js() {
	if ( ! xwrite_promo_get_message_id() ) return;
	?>
	<script>
	(function () {
		'use strict';
		var notice = document.getElementById('xwrite-promo-notice');
		if ( ! notice ) return;

		// ページタイトル（h1）の直前に移動する
		var h1 = document.querySelector('#wpbody-content .wrap > h1, #wpbody-content .wrap > h2');
		if ( h1 && h1.parentNode ) {
			h1.parentNode.insertBefore( notice, h1 );
		}

		// 非表示フラグをサーバーに記録する共通関数
		function sendDismiss() {
			var msgId = notice.getAttribute( 'data-msg-id' );
			var nonce = notice.getAttribute( 'data-nonce' );
			var fd = new FormData();
			fd.append( 'action', 'xwrite_promo_dismiss' );
			fd.append( 'nonce',  nonce );
			fd.append( 'msg_id', msgId );
			fetch( ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' } );
		}

		// WP 標準の × ボタン（is-dismissible が動的追加するため委譲方式）
		notice.addEventListener( 'click', function ( e ) {
			if ( e.target.closest && e.target.closest( '.notice-dismiss' ) ) {
				sendDismiss();
			}
		} );

		// 「通知を表示しない」テキストリンク
		var dismissLink = notice.querySelector( '.xwrite-promo-dismiss' );
		if ( dismissLink ) {
			dismissLink.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				notice.style.display = 'none';
				sendDismiss();
			} );
		}
	})();
	</script>
	<?php
}
endif;

// Xwrite 通知バーの CSS
add_action( 'admin_head', 'xwrite_promo_css' );
if ( ! function_exists( 'xwrite_promo_css' ) ):
function xwrite_promo_css() {
	if ( ! xwrite_promo_get_message_id() ) return;
	?>
	<style>
	#xwrite-promo-notice {
		border-left-color: #f5a623;
	}
	#xwrite-promo-notice .xwrite-promo-links {
		display: block;
		margin-top: 4px;
	}
	.block-editor-page #xwrite-promo-notice {
		display: none;
	}
	</style>
	<?php
}
endif;

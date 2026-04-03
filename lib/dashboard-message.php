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
// Xwrite プロモーション通知バー
//
// 表示ロジック:
//   - XWRITE_PROMO_CURRENT がバージョン ID として機能する
//   - ユーザーが × を押すと、そのバージョン ID を user_meta に保存する
//   - 次回以降のページ読み込み時に保存値と XWRITE_PROMO_CURRENT を比較し、
//     一致していれば非表示、異なれば表示する
//
// メッセージを差し替えるには:
//   1. XWRITE_PROMO_MESSAGE のテキストを書き換えるだけでよい
//      → テキストが変わると md5 ハッシュが変わり、バージョン ID が自動更新される
//      → × で非表示済みのユーザーにも新しいメッセージが再表示される
// ============================================================

if ( ! defined( 'XWRITE_PROMO_DISMISSED_META' ) ) {
	// ユーザーが最後に閉じたバージョン ID を保存する user_meta キー
	define( 'XWRITE_PROMO_DISMISSED_META', 'cocoon_xwrite_dismissed_version' );
}
if ( ! defined( 'XWRITE_PROMO_MESSAGE' ) ) {
	// ← メッセージを差し替えるときはここだけ変更する（バージョン ID は自動更新される）
	define( 'XWRITE_PROMO_MESSAGE', 'プロが作ったデモサイトを丸ごと再現！コピー&ペーストの簡単操作で、あなたのブログが本格サイトに。Cocoonの記事も安全に引き継げるテーマ『Xwrite』を試してみませんか？' );
}
if ( ! defined( 'XWRITE_PROMO_CURRENT' ) ) {
	// メッセージテキストの md5 ハッシュをバージョン ID として使用（手動変更不要）
	define( 'XWRITE_PROMO_CURRENT', md5( XWRITE_PROMO_MESSAGE ) );
}

// 表示すべきメッセージ ID を返す（静的キャッシュ付き）: 'msg1' | 'msg2' | ... | null
if ( ! function_exists( 'xwrite_promo_get_message_id' ) ):
function xwrite_promo_get_message_id() {
	static $cache = 'unset';
	if ( $cache !== 'unset' ) return $cache;

	$user_id   = get_current_user_id();
	$dismissed = get_user_meta( $user_id, XWRITE_PROMO_DISMISSED_META, true );

	// ユーザーが現在の案を既に閉じていれば非表示、それ以外は表示
	$cache = ( $dismissed === XWRITE_PROMO_CURRENT ) ? null : XWRITE_PROMO_CURRENT;

	return $cache;
}
endif;

// 管理画面の通知バーを出力
add_action( 'admin_notices', 'xwrite_promo_render_notice' );
if ( ! function_exists( 'xwrite_promo_render_notice' ) ):
function xwrite_promo_render_notice() {
	$msg_id = xwrite_promo_get_message_id();
	if ( ! $msg_id ) return;

	$official_url  = 'https://xwrite.jp/';
	$migration_url = 'https://xwrite.jp/migration/';

	$nonce = wp_create_nonce( 'xwrite_promo_dismiss' );
	?>
	<div id="xwrite-promo-notice" class="notice is-dismissible"
		data-msg-id="<?php echo esc_attr( $msg_id ); ?>"
		data-nonce="<?php echo esc_attr( $nonce ); ?>">
		<p>
			<?php echo esc_html( XWRITE_PROMO_MESSAGE ); ?>
			[<a href="<?php echo esc_url( $official_url ); ?>" target="_blank" rel="noopener noreferrer">公式サイトへ</a>]
			[<a href="<?php echo esc_url( $migration_url ); ?>" target="_blank" rel="noopener noreferrer">移行の手順を確認</a>]
		</p>
	</div>
	<?php
}
endif;

// AJAX ハンドラー: 閉じた案の ID を user_meta に保存
add_action( 'wp_ajax_xwrite_promo_dismiss', 'xwrite_promo_ajax_dismiss' );
if ( ! function_exists( 'xwrite_promo_ajax_dismiss' ) ):
function xwrite_promo_ajax_dismiss() {
	check_ajax_referer( 'xwrite_promo_dismiss', 'nonce' );

	$msg_id  = isset( $_POST['msg_id'] ) ? sanitize_key( $_POST['msg_id'] ) : '';
	$user_id = get_current_user_id();

	if ( $msg_id ) {
		update_user_meta( $user_id, XWRITE_PROMO_DISMISSED_META, $msg_id );
		wp_send_json_success();
	}

	wp_send_json_error( 'invalid_msg_id' );
}
endif;

// インライン JS: DOM 移動（h1 の前に移動）＋ × ボタンの挙動
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

		// × ボタンのクリックをイベント委譲で検知する
		// （WP の is-dismissible がボタンを動的追加するため、委譲方式が必要）
		notice.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest ? e.target.closest( '.notice-dismiss' ) : null;
			if ( ! btn ) return;

			var msgId = notice.getAttribute( 'data-msg-id' );
			var nonce = notice.getAttribute( 'data-nonce' );

			// 閉鎖日時をサーバーに記録（視覚的な非表示は WP の is-dismissible に任せる）
			var fd = new FormData();
			fd.append( 'action', 'xwrite_promo_dismiss' );
			fd.append( 'nonce',  nonce );
			fd.append( 'msg_id', msgId );
			fetch( ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' } );
		} );
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
	/* WP 標準 .notice のスタイルをベースにし、色と×ボタン位置のみ上書き */
	#xwrite-promo-notice {
		border-left-color: #f5a623;
		background-color: #fff8e1;
	}
	#xwrite-promo-notice .notice-dismiss {
		top: 50%;
		transform: translateY(-50%);
	}
	.block-editor-page #xwrite-promo-notice {
		display: none;
	}
	</style>
	<?php
}
endif;

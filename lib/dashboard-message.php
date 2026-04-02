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
// Xwrite プロモーション通知バー（段階的表示）
//
// 表示ロジック:
//   1. 案1 を最初に表示する（初心者〜中級者）
//   2. 案1 の × を押すと閉鎖日時を user_meta に保存
//   3. 案1 閉鎖から XWRITE_PROMO_INTERVAL 秒（デフォルト=30日）経過後に 案2 を表示
//   4. 案2 の × を押すと閉鎖日時を保存
//   5. 案2 閉鎖から XWRITE_PROMO_INTERVAL 秒経過後に 案3 を表示
//   6. 案3 の × を押したら以降は何も表示しない
// ============================================================

if ( ! defined( 'XWRITE_PROMO_MSG1_META' ) ) {
	define( 'XWRITE_PROMO_MSG1_META', 'cocoon_xwrite_msg1_dismissed' );
}
if ( ! defined( 'XWRITE_PROMO_MSG2_META' ) ) {
	define( 'XWRITE_PROMO_MSG2_META', 'cocoon_xwrite_msg2_dismissed' );
}
if ( ! defined( 'XWRITE_PROMO_MSG3_META' ) ) {
	define( 'XWRITE_PROMO_MSG3_META', 'cocoon_xwrite_msg3_dismissed' );
}
if ( ! defined( 'XWRITE_PROMO_INTERVAL' ) ) {
	define( 'XWRITE_PROMO_INTERVAL', 10 ); // テスト用: 10秒（本番は 30 * DAY_IN_SECONDS）
}

// 表示すべきメッセージ ID を返す（静的キャッシュ付き）: 'msg1' | 'msg2' | 'msg3' | null
if ( ! function_exists( 'xwrite_promo_get_message_id' ) ):
function xwrite_promo_get_message_id() {
	static $cache = 'unset';
	if ( $cache !== 'unset' ) return $cache;

	$user_id   = get_current_user_id();
	$time_msg1 = (int) get_user_meta( $user_id, XWRITE_PROMO_MSG1_META, true );
	$time_msg2 = (int) get_user_meta( $user_id, XWRITE_PROMO_MSG2_META, true );
	$time_msg3 = (int) get_user_meta( $user_id, XWRITE_PROMO_MSG3_META, true );

	if ( $time_msg3 ) {
		// 案3 も閉じ済み → 表示なし
		$cache = null;
	} elseif ( $time_msg2 && ( time() - $time_msg2 ) >= XWRITE_PROMO_INTERVAL ) {
		// 案2 閉鎖から期間経過 → 案3 を表示
		$cache = 'msg3';
	} elseif ( $time_msg2 ) {
		// 案2 は閉じたが期間未到達 → 表示なし
		$cache = null;
	} elseif ( $time_msg1 && ( time() - $time_msg1 ) >= XWRITE_PROMO_INTERVAL ) {
		// 案1 閉鎖から期間経過 → 案2 を表示
		$cache = 'msg2';
	} elseif ( ! $time_msg1 ) {
		// 案1 を未閉鎖 → 案1 を表示
		$cache = 'msg1';
	} else {
		// 案1 は閉じたが期間未到達 → 表示なし
		$cache = null;
	}

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
	<div id="xwrite-promo-notice" class="notice is-dismissible"
		data-msg-id="<?php echo esc_attr( $msg_id ); ?>"
		data-nonce="<?php echo esc_attr( $nonce ); ?>">
		<p>
			<?php echo esc_html( $messages[ $msg_id ] ); ?>
			[<a href="<?php echo esc_url( $official_url ); ?>" target="_blank" rel="noopener noreferrer">公式サイトへ</a>]
			[<a href="<?php echo esc_url( $migration_url ); ?>" target="_blank" rel="noopener noreferrer">移行の手順を確認</a>]
		</p>
	</div>
	<?php
}
endif;

// AJAX ハンドラー: 閉鎖日時を user_meta に保存
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
		update_user_meta( $user_id, $meta_map[ $msg_id ], time() );
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

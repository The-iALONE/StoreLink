<?php
/**
 * Admin settings template.
 *
 * @package StoreLink
 */

use StoreLink\Admin\SettingsPage;
use StoreLink\Admin\SettingsStore;
use StoreLink\Core\Plugin;

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( Plugin::capability() ) ) {
	return;
}

$settings  = SettingsStore::get();
$result    = isset( $_GET['storelink_webhook'] ) ? sanitize_text_field( wp_unslash( $_GET['storelink_webhook'] ) ) : '';
$test      = isset( $_GET['storelink_test'] ) ? sanitize_text_field( wp_unslash( $_GET['storelink_test'] ) ) : '';
$saved     = isset( $_GET['storelink_saved'] );
$queued    = isset( $_GET['storelink_channel_queued'] ) ? absint( wp_unslash( $_GET['storelink_channel_queued'] ) ) : null;
$notice_pl = isset( $_GET['platform'] ) ? sanitize_key( wp_unslash( $_GET['platform'] ) ) : '';
$tab       = SettingsPage::sanitize_tab( isset( $_GET['tab'] ) ? wp_unslash( $_GET['tab'] ) : 'general' );
$labels    = array(
	'telegram' => __( 'Telegram', 'storelink' ),
	'bale'     => __( 'Bale', 'storelink' ),
);
$error     = ( $notice_pl && ! empty( $settings[ $notice_pl . '_webhook_error' ] ) )
	? (string) $settings[ $notice_pl . '_webhook_error' ]
	: '';
$tabs      = array(
	'general'  => __( 'General', 'storelink' ),
	'telegram' => $labels['telegram'],
	'bale'     => $labels['bale'],
);
?>
<div class="wrap storelink-settings">
	<h1><?php echo esc_html__( 'StoreLink', 'storelink' ); ?></h1>
	<p><?php echo esc_html__( 'Connect WooCommerce to Telegram and Bale. Other messengers can be added through the gateway interface.', 'storelink' ); ?></p>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html__( 'Settings saved.', 'storelink' ); ?></p></div>
	<?php endif; ?>

	<?php if ( null !== $queued ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( sprintf( __( 'Queued %d product(s) for the channel. WooCommerce Action Scheduler will post or edit them.', 'storelink' ), $queued ) ); ?></p></div>
	<?php endif; ?>

	<?php if ( '1' === $result ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html__( 'Webhook registered.', 'storelink' ); ?></p></div>
	<?php elseif ( '0' === $result ) : ?>
		<div class="notice notice-error">
			<p><?php echo esc_html__( 'Could not register the webhook.', 'storelink' ); ?></p>
			<?php if ( $error ) : ?>
				<p><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( '1' === $test ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( (string) $settings['telegram_probe_text'] ); ?></p></div>
	<?php elseif ( '0' === $test ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( (string) $settings['telegram_probe_text'] ); ?></p></div>
	<?php endif; ?>

	<form id="storelink-settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'storelink_save_settings' ); ?>
		<input type="hidden" name="action" value="storelink_save_settings" />
		<input type="hidden" name="storelink_tab" value="<?php echo esc_attr( $tab ); ?>" />

		<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $id => $title ) : ?>
				<a
					href="<?php echo esc_url( add_query_arg( array( 'page' => 'storelink', 'tab' => $id ), admin_url( 'admin.php' ) ) ); ?>"
					class="nav-tab<?php echo $tab === $id ? ' nav-tab-active' : ''; ?>"
					data-tab="<?php echo esc_attr( $id ); ?>"
				><?php echo esc_html( $title ); ?></a>
			<?php endforeach; ?>
		</nav>

		<div class="storelink-tab-panel<?php echo 'general' === $tab ? ' is-active' : ''; ?>" data-tab="general">
			<?php include STORELINK_PLUGIN_DIR . 'templates/settings/general.php'; ?>
		</div>

		<?php foreach ( SettingsStore::platforms() as $platform ) : ?>
			<div class="storelink-tab-panel<?php echo $tab === $platform ? ' is-active' : ''; ?>" data-tab="<?php echo esc_attr( $platform ); ?>">
				<?php include STORELINK_PLUGIN_DIR . 'templates/settings/messenger.php'; ?>
			</div>
		<?php endforeach; ?>

		<?php submit_button( __( 'Save settings', 'storelink' ) ); ?>
	</form>
</div>

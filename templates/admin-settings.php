<?php
/**
 * Admin settings template.
 *
 * @package StoreLink
 */

use StoreLink\Admin\SettingsStore;
use StoreLink\Core\Plugin;
use StoreLink\Core\TokenVault;

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( Plugin::capability() ) ) {
	return;
}

$settings  = SettingsStore::get();
$result    = isset( $_GET['storelink_webhook'] ) ? sanitize_text_field( wp_unslash( $_GET['storelink_webhook'] ) ) : '';
$saved     = isset( $_GET['storelink_saved'] );
$notice_pl = isset( $_GET['platform'] ) ? sanitize_key( wp_unslash( $_GET['platform'] ) ) : '';
$labels    = array(
	'telegram' => __( 'Telegram', 'storelink' ),
	'bale'     => __( 'Bale', 'storelink' ),
);
$error     = ( $notice_pl && ! empty( $settings[ $notice_pl . '_webhook_error' ] ) )
	? (string) $settings[ $notice_pl . '_webhook_error' ]
	: '';
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'StoreLink', 'storelink' ); ?></h1>
	<p><?php echo esc_html__( 'Connect WooCommerce to Telegram and Bale. Other messengers can be added through the gateway interface.', 'storelink' ); ?></p>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html__( 'Settings saved.', 'storelink' ); ?></p></div>
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

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'storelink_save_settings' ); ?>
		<input type="hidden" name="action" value="storelink_save_settings" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="storelink-webhook-public-base"><?php echo esc_html__( 'Public webhook base URL', 'storelink' ); ?></label></th>
				<td>
					<input
						id="storelink-webhook-public-base"
						class="regular-text"
						type="url"
						name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[webhook_public_base]"
						value="<?php echo esc_attr( (string) $settings['webhook_public_base'] ); ?>"
						placeholder="https://xxxx.ngrok-free.app"
					/>
					<p class="description"><?php echo esc_html__( 'Required on localhost. Paste your public HTTPS origin (ngrok, Cloudflare Tunnel). Leave empty if this WordPress site is already public HTTPS.', 'storelink' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="storelink-telegram-proxy"><?php echo esc_html__( 'Telegram outbound proxy', 'storelink' ); ?></label></th>
				<td>
					<input
						id="storelink-telegram-proxy"
						class="regular-text"
						type="text"
						name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[telegram_proxy]"
						value="<?php echo esc_attr( (string) $settings['telegram_proxy'] ); ?>"
						placeholder="socks5://127.0.0.1:10808"
					/>
					<p class="description"><?php echo esc_html__( 'Optional. PHP calls to api.telegram.org (setWebhook, replies). Example: socks5://127.0.0.1:10808 for V2Ray. Incoming webhooks still need public HTTPS.', 'storelink' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="storelink-telegram-admin-ids"><?php echo esc_html__( 'Telegram admin user IDs', 'storelink' ); ?></label></th>
				<td>
					<input
						id="storelink-telegram-admin-ids"
						class="regular-text"
						type="text"
						name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[telegram_admin_ids]"
						value="<?php echo esc_attr( (string) $settings['telegram_admin_ids'] ); ?>"
						placeholder="123456789"
					/>
					<p class="description"><?php echo esc_html__( 'Numeric Telegram user IDs (comma-separated). Those users see Orders in the bot. They must send /start once so order alerts can be delivered.', 'storelink' ); ?></p>
				</td>
			</tr>
		</table>

		<?php foreach ( SettingsStore::platforms() as $platform ) : ?>
			<?php
			$token   = TokenVault::decrypt( (string) $settings[ $platform . '_token' ] );
			$webhook = SettingsStore::webhook_url( $platform );
			$status  = ! empty( $settings[ $platform . '_webhook_ok' ] )
				? __( 'Webhook connected.', 'storelink' )
				: __( 'Webhook is not connected.', 'storelink' );
			$label   = $labels[ $platform ] ?? $platform;
			?>
			<h2><?php echo esc_html( $label ); ?></h2>
			<?php if ( 'bale' === $platform ) : ?>
				<p class="description"><?php echo esc_html__( 'Bale stays on the Telegram-compatible adapter. Finish the Telegram shop bot before adding more Bale-specific features.', 'storelink' ); ?></p>
			<?php endif; ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php echo esc_html( sprintf( __( 'Enable %s', 'storelink' ), $label ) ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[<?php echo esc_attr( $platform ); ?>_enabled]" value="1" <?php checked( ! empty( $settings[ $platform . '_enabled' ] ) ); ?> />
							<?php echo esc_html( sprintf( __( 'Accept %s bot updates', 'storelink' ), $label ) ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="storelink-<?php echo esc_attr( $platform ); ?>-token"><?php echo esc_html__( 'Bot token', 'storelink' ); ?></label></th>
					<td>
						<input
							id="storelink-<?php echo esc_attr( $platform ); ?>-token"
							class="regular-text"
							type="password"
							autocomplete="off"
							name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[<?php echo esc_attr( $platform ); ?>_token]"
							value="<?php echo esc_attr( TokenVault::mask( $token ) ); ?>"
						/>
						<p class="description"><?php echo esc_html__( 'Token is stored encrypted. Leave the masked value unchanged to keep the current token.', 'storelink' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Bot username', 'storelink' ); ?></th>
					<td><?php echo esc_html( $settings[ $platform . '_username' ] ? '@' . $settings[ $platform . '_username' ] : '—' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Webhook URL', 'storelink' ); ?></th>
					<td><code><?php echo esc_html( $webhook ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Status', 'storelink' ); ?></th>
					<td><?php echo esc_html( $status ); ?></td>
				</tr>
			</table>
			<p>
				<button type="submit" class="button button-secondary" name="storelink_connect" value="<?php echo esc_attr( $platform ); ?>">
					<?php echo esc_html( sprintf( __( 'Connect %s webhook', 'storelink' ), $label ) ); ?>
				</button>
			</p>
		<?php endforeach; ?>

		<?php submit_button( __( 'Save settings', 'storelink' ) ); ?>
	</form>
</div>

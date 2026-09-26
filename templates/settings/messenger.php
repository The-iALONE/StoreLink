<?php
/**
 * Per-messenger connection settings.
 *
 * @package StoreLink
 *
 * @var string $platform Platform key.
 * @var array<string, mixed> $settings Settings.
 * @var array<string, string> $labels Platform labels.
 * @var string $test getMe result flag.
 */

use StoreLink\Admin\SettingsStore;
use StoreLink\Core\TokenVault;

defined( 'ABSPATH' ) || exit;

$platform = sanitize_key( (string) $platform );
$token    = TokenVault::decrypt( (string) $settings[ $platform . '_token' ] );
$webhook  = SettingsStore::webhook_url( $platform );
if ( 'rubika' === $platform ) {
	$secret  = SettingsStore::secret( 'rubika' );
	$webhook = untrailingslashit( $webhook ) . '/' . ( '' !== $secret ? $secret : '{secret}' );
}
$status = ! empty( $settings[ $platform . '_webhook_ok' ] )
	? __( 'Webhook connected.', 'storelink' )
	: __( 'Webhook is not connected.', 'storelink' );
$label = $labels[ $platform ] ?? $platform;
?>
<p class="description"><?php echo esc_html( sprintf( __( '%s uses the same shop as Telegram (catalog, cart, my orders, admin).', 'storelink' ), $label ) ); ?></p>
<p class="description"><?php echo esc_html__( 'Enable the bot, save the token, connect the webhook, then /start.', 'storelink' ); ?></p>
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
			<p class="description"><?php echo esc_html__( 'Token is stored encrypted.', 'storelink' ); ?></p>
			<p class="description"><?php echo esc_html__( 'Leave the masked value unchanged to keep the current token.', 'storelink' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php echo esc_html__( 'Bot username', 'storelink' ); ?></th>
		<td><span class="storelink-ltr"><?php echo esc_html( $settings[ $platform . '_username' ] ? '@' . $settings[ $platform . '_username' ] : '—' ); ?></span></td>
	</tr>
	<?php if ( 'telegram' === $platform ) : ?>
		<tr>
			<th scope="row"><label for="storelink-telegram-proxy"><?php echo esc_html__( 'Telegram outbound proxy', 'storelink' ); ?></label></th>
			<td>
				<input
					id="storelink-telegram-proxy"
					class="regular-text storelink-ltr"
					type="text"
					name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[telegram_proxy]"
					value="<?php echo esc_attr( (string) $settings['telegram_proxy'] ); ?>"
					placeholder="socks5://127.0.0.1:10808"
				/>
				<p class="description"><?php echo esc_html__( 'Optional. PHP calls to api.telegram.org (setWebhook, replies).', 'storelink' ); ?></p>
				<p class="description"><?php echo esc_html__( 'Example: socks5://127.0.0.1:10808 for V2Ray.', 'storelink' ); ?></p>
				<p class="description"><?php echo esc_html__( 'Incoming webhooks still need public HTTPS.', 'storelink' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="storelink-telegram-relay"><?php echo esc_html__( 'Telegram API relay URL', 'storelink' ); ?></label></th>
			<td>
				<input
					id="storelink-telegram-relay"
					class="regular-text storelink-ltr"
					type="text"
					name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[telegram_relay]"
					value="<?php echo esc_attr( (string) $settings['telegram_relay'] ); ?>"
					placeholder="https://your-worker.workers.dev"
				/>
				<p class="description"><?php echo esc_html__( 'For Iranian shared hosting: PHP cannot reach api.telegram.org and cannot use a local SOCKS proxy.', 'storelink' ); ?></p>
				<p class="description"><?php echo esc_html__( 'Deploy the sample Cloudflare Worker in tools/cloudflare-telegram-relay.js and paste its HTTPS origin here.', 'storelink' ); ?></p>
				<p class="description"><?php echo esc_html__( 'This replaces api.telegram.org for outbound calls only.', 'storelink' ); ?></p>
			</td>
		</tr>
	<?php endif; ?>
	<tr>
		<th scope="row"><?php echo esc_html__( 'Test API', 'storelink' ); ?></th>
		<td>
			<button type="submit" class="button" name="storelink_test_connection" value="<?php echo esc_attr( $platform ); ?>">
				<?php echo esc_html__( 'Test getMe', 'storelink' ); ?>
			</button>
			<?php if ( ! empty( $settings[ $platform . '_probe_text' ] ) && '' === $test ) : ?>
				<p class="description"><?php echo esc_html( (string) $settings[ $platform . '_probe_text' ] ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="storelink-<?php echo esc_attr( $platform ); ?>-admin-ids"><?php echo esc_html( sprintf( __( '%s admin user IDs', 'storelink' ), $label ) ); ?></label></th>
		<td>
			<input
				id="storelink-<?php echo esc_attr( $platform ); ?>-admin-ids"
				class="regular-text"
				type="text"
				name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[<?php echo esc_attr( $platform ); ?>_admin_ids]"
				value="<?php echo esc_attr( (string) $settings[ $platform . '_admin_ids' ] ); ?>"
				placeholder="<?php echo 'rubika' === $platform ? 'u0QFtn0…' : '123456789'; ?>"
			/>
			<?php if ( 'rubika' === $platform ) : ?>
				<p class="description"><?php echo esc_html__( 'Rubika user IDs from /start (they look like u…).', 'storelink' ); ?></p>
			<?php else : ?>
				<p class="description"><?php echo esc_html( sprintf( __( 'Numeric %s user IDs (comma-separated).', 'storelink' ), $label ) ); ?></p>
			<?php endif; ?>
			<p class="description"><?php echo esc_html__( 'Those users see Orders in the bot.', 'storelink' ); ?></p>
			<p class="description"><?php echo esc_html__( 'They must send /start once so order alerts can be delivered.', 'storelink' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="storelink-<?php echo esc_attr( $platform ); ?>-channel-id"><?php echo esc_html( sprintf( __( '%s channel', 'storelink' ), $label ) ); ?></label></th>
		<td>
			<input
				id="storelink-<?php echo esc_attr( $platform ); ?>-channel-id"
				class="regular-text"
				type="text"
				name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[<?php echo esc_attr( $platform ); ?>_channel_id]"
				value="<?php echo esc_attr( (string) $settings[ $platform . '_channel_id' ] ); ?>"
				placeholder="<?php echo 'telegram' === $platform ? '@mychannel or -100…' : ''; ?>"
			/>
			<p class="description"><?php echo esc_html__( 'Add the bot as admin.', 'storelink' ); ?></p>
			<p class="description"><?php echo esc_html__( 'Price/stock changes post or edit using the caption fields on the General tab.', 'storelink' ); ?></p>
			<p class="description"><?php echo esc_html__( 'No AI.', 'storelink' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php echo esc_html__( 'Webhook URL', 'storelink' ); ?></th>
		<td><code class="storelink-ltr"><?php echo esc_html( $webhook ); ?></code></td>
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

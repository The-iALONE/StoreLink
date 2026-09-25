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
$status   = ! empty( $settings[ $platform . '_webhook_ok' ] )
	? __( 'Webhook connected.', 'storelink' )
	: __( 'Webhook is not connected.', 'storelink' );
$label    = $labels[ $platform ] ?? $platform;
?>
<?php if ( 'bale' === $platform ) : ?>
	<p class="description"><?php echo esc_html__( 'Bale uses the same shop as Telegram (catalog, cart, my orders, admin).', 'storelink' ); ?></p>
	<p class="description"><?php echo esc_html__( 'Enable the bot, save the token, connect the webhook, then /start.', 'storelink' ); ?></p>
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
					class="regular-text"
					type="url"
					name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[telegram_relay]"
					value="<?php echo esc_attr( (string) $settings['telegram_relay'] ); ?>"
					placeholder="https://your-worker.workers.dev"
				/>
				<p class="description"><?php echo esc_html__( 'For Iranian shared hosting: PHP cannot reach api.telegram.org and cannot use a local SOCKS proxy.', 'storelink' ); ?></p>
				<p class="description"><?php echo esc_html__( 'Deploy the sample Cloudflare Worker in tools/cloudflare-telegram-relay.js and paste its HTTPS origin here.', 'storelink' ); ?></p>
				<p class="description"><?php echo esc_html__( 'This replaces api.telegram.org for outbound calls only.', 'storelink' ); ?></p>
				<p class="description"><?php echo esc_html__( 'Incoming webhooks still need a public HTTPS site URL.', 'storelink' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php echo esc_html__( 'Telegram API test', 'storelink' ); ?></th>
			<td>
				<button type="submit" class="button" name="storelink_test_telegram" value="1">
					<?php echo esc_html__( 'Test Telegram getMe', 'storelink' ); ?>
				</button>
				<?php if ( ! empty( $settings['telegram_probe_text'] ) && '' === $test ) : ?>
					<p class="description"><?php echo esc_html( (string) $settings['telegram_probe_text'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
	<?php endif; ?>
	<tr>
		<th scope="row"><label for="storelink-<?php echo esc_attr( $platform ); ?>-admin-ids"><?php echo esc_html( 'telegram' === $platform ? __( 'Telegram admin user IDs', 'storelink' ) : __( 'Bale admin user IDs', 'storelink' ) ); ?></label></th>
		<td>
			<input
				id="storelink-<?php echo esc_attr( $platform ); ?>-admin-ids"
				class="regular-text"
				type="text"
				name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[<?php echo esc_attr( $platform ); ?>_admin_ids]"
				value="<?php echo esc_attr( (string) $settings[ $platform . '_admin_ids' ] ); ?>"
				placeholder="123456789"
			/>
			<?php if ( 'telegram' === $platform ) : ?>
				<p class="description"><?php echo esc_html__( 'Numeric Telegram user IDs (comma-separated).', 'storelink' ); ?></p>
				<p class="description"><?php echo esc_html__( 'Those users see Orders in the bot.', 'storelink' ); ?></p>
				<p class="description"><?php echo esc_html__( 'They must send /start once so order alerts can be delivered.', 'storelink' ); ?></p>
			<?php else : ?>
				<p class="description"><?php echo esc_html__( 'Numeric Bale user IDs.', 'storelink' ); ?></p>
				<p class="description"><?php echo esc_html__( 'Same shop and admin order contract as Telegram.', 'storelink' ); ?></p>
				<p class="description"><?php echo esc_html__( 'Send /start in the Bale bot once for alerts.', 'storelink' ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="storelink-<?php echo esc_attr( $platform ); ?>-channel-id"><?php echo esc_html( 'telegram' === $platform ? __( 'Telegram channel', 'storelink' ) : __( 'Bale channel', 'storelink' ) ); ?></label></th>
		<td>
			<input
				id="storelink-<?php echo esc_attr( $platform ); ?>-channel-id"
				class="regular-text"
				type="text"
				name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[<?php echo esc_attr( $platform ); ?>_channel_id]"
				value="<?php echo esc_attr( (string) $settings[ $platform . '_channel_id' ] ); ?>"
				placeholder="<?php echo 'telegram' === $platform ? '@mychannel or -100…' : ''; ?>"
			/>
			<?php if ( 'telegram' === $platform ) : ?>
				<p class="description"><?php echo esc_html__( 'Add the bot as admin.', 'storelink' ); ?></p>
				<p class="description"><?php echo esc_html__( 'Price/stock changes post or edit using the caption fields on the General tab.', 'storelink' ); ?></p>
				<p class="description"><?php echo esc_html__( 'No AI.', 'storelink' ); ?></p>
			<?php else : ?>
				<p class="description"><?php echo esc_html__( 'Optional. Same caption as Telegram if the Bale bot can post there.', 'storelink' ); ?></p>
			<?php endif; ?>
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

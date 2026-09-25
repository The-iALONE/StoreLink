<?php
/**
 * Shared StoreLink settings.
 *
 * @package StoreLink
 */

use StoreLink\Admin\SettingsStore;

defined( 'ABSPATH' ) || exit;
?>
<h2><?php echo esc_html__( 'Connection', 'storelink' ); ?></h2>
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
			<p class="description"><?php echo esc_html__( 'Required on localhost.', 'storelink' ); ?></p>
			<p class="description"><?php echo esc_html__( 'Paste your public HTTPS origin (ngrok or Cloudflare Tunnel).', 'storelink' ); ?></p>
			<p class="description"><?php echo esc_html__( 'Leave empty if this WordPress site is already public HTTPS.', 'storelink' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php echo esc_html__( 'Shipment tracking', 'storelink' ); ?></th>
		<td>
			<p class="description"><?php echo esc_html__( 'Only orders with a physical item.', 'storelink' ); ?></p>
			<p class="description"><?php echo esc_html__( 'Set carrier and number on the WooCommerce order or from the Telegram admin bot.', 'storelink' ); ?></p>
			<p class="description"><?php echo esc_html__( 'Iran Post and Tipax open their official pages. Local courier has no tracking website.', 'storelink' ); ?></p>
			<p class="description"><?php echo esc_html__( 'Carrier sites are not scraped.', 'storelink' ); ?></p>
			<p class="description"><?php echo esc_html__( 'A separate Telegram message when tracking is saved needs the customer tracking checkbox under Bot notifications.', 'storelink' ); ?></p>
			<p class="description"><?php echo esc_html__( 'My orders and status messages can still show the number.', 'storelink' ); ?></p>
		</td>
	</tr>
</table>

<h2><?php echo esc_html__( 'Bot shop', 'storelink' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php echo esc_html__( 'Bot categories', 'storelink' ); ?></th>
		<td>
			<label class="storelink-check" for="storelink-show-uncategorized">
				<input
					id="storelink-show-uncategorized"
					type="checkbox"
					name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[bot_show_uncategorized]"
					value="1"
					<?php checked( ! empty( $settings['bot_show_uncategorized'] ) ); ?>
				/>
				<span><?php echo esc_html__( 'Show the WooCommerce default category (Uncategorized) in the bot category menu.', 'storelink' ); ?></span>
			</label>
			<p class="description"><?php echo esc_html__( 'Products stay in the full catalog and search either way.', 'storelink' ); ?></p>
			<p class="description"><?php echo esc_html__( 'This does not change the store website.', 'storelink' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php echo esc_html__( 'Free downloadable products', 'storelink' ); ?></th>
		<td>
			<label class="storelink-check" for="storelink-free-download-skip">
				<input
					id="storelink-free-download-skip"
					type="checkbox"
					name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[free_download_skip_checkout]"
					value="1"
					<?php checked( ! empty( $settings['free_download_skip_checkout'] ) ); ?>
				/>
				<span><?php echo esc_html__( 'If the cart is only downloadable products with price 0, skip name, address, shipping and payment.', 'storelink' ); ?></span>
			</label>
			<p class="description"><?php echo esc_html__( 'Complete the WooCommerce order and send download links in the bot.', 'storelink' ); ?></p>
		</td>
	</tr>
</table>

<h2><?php echo esc_html__( 'Notifications', 'storelink' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php echo esc_html__( 'Bot notifications', 'storelink' ); ?></th>
		<td>
			<p>
				<label class="storelink-check">
					<input type="checkbox" name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[notify_admin_new_order]" value="1" <?php checked( ! empty( $settings['notify_admin_new_order'] ) ); ?> />
					<span><?php echo esc_html__( 'Store: new order (default on)', 'storelink' ); ?></span>
				</label>
			</p>
			<p>
				<label class="storelink-check">
					<input type="checkbox" name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[notify_admin_status]" value="1" <?php checked( ! empty( $settings['notify_admin_status'] ) ); ?> />
					<span><?php echo esc_html__( 'Store: order status changed (default off)', 'storelink' ); ?></span>
				</label>
			</p>
			<p>
				<label class="storelink-check">
					<input type="checkbox" name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[notify_customer_status]" value="1" <?php checked( ! empty( $settings['notify_customer_status'] ) ); ?> />
					<span><?php echo esc_html__( 'Customer: order status changed (default on)', 'storelink' ); ?></span>
				</label>
			</p>
			<p>
				<label class="storelink-check">
					<input type="checkbox" name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[notify_customer_refunded]" value="1" <?php checked( ! empty( $settings['notify_customer_refunded'] ) ); ?> />
					<span><?php echo esc_html__( 'Customer: separate message when refunded (default off)', 'storelink' ); ?></span>
				</label>
			</p>
			<p>
				<label class="storelink-check">
					<input type="checkbox" name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[notify_customer_shipped]" value="1" <?php checked( ! empty( $settings['notify_customer_shipped'] ) ); ?> />
					<span><?php echo esc_html__( 'Customer: separate message when a physical order is completed (default off)', 'storelink' ); ?></span>
				</label>
			</p>
			<p>
				<label class="storelink-check">
					<input type="checkbox" name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[notify_customer_tracking]" value="1" <?php checked( ! empty( $settings['notify_customer_tracking'] ) ); ?> />
					<span><?php echo esc_html__( 'Customer: message when tracking is saved (default off)', 'storelink' ); ?></span>
				</label>
			</p>
		</td>
	</tr>
</table>

<h2><?php echo esc_html__( 'Channel', 'storelink' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php echo esc_html__( 'Channel caption', 'storelink' ); ?></th>
		<td>
			<p>
				<label class="storelink-check">
					<input type="checkbox" name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[channel_caption_name]" value="1" <?php checked( ! empty( $settings['channel_caption_name'] ) ); ?> />
					<span><?php echo esc_html__( 'Name (default on)', 'storelink' ); ?></span>
				</label>
			</p>
			<p>
				<label class="storelink-check">
					<input type="checkbox" name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[channel_caption_price]" value="1" <?php checked( ! empty( $settings['channel_caption_price'] ) ); ?> />
					<span><?php echo esc_html__( 'Price (default on)', 'storelink' ); ?></span>
				</label>
			</p>
			<p>
				<label class="storelink-check">
					<input type="checkbox" name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[channel_caption_stock]" value="1" <?php checked( ! empty( $settings['channel_caption_stock'] ) ); ?> />
					<span><?php echo esc_html__( 'Stock (default on)', 'storelink' ); ?></span>
				</label>
			</p>
			<p>
				<label class="storelink-check">
					<input type="checkbox" name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[channel_caption_link]" value="1" <?php checked( ! empty( $settings['channel_caption_link'] ) ); ?> />
					<span><?php echo esc_html__( 'Product link (default on)', 'storelink' ); ?></span>
				</label>
			</p>
			<p>
				<label class="storelink-check">
					<input type="checkbox" name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[channel_caption_short_desc]" value="1" <?php checked( ! empty( $settings['channel_caption_short_desc'] ) ); ?> />
					<span><?php echo esc_html__( 'Short description (default off)', 'storelink' ); ?></span>
				</label>
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php echo esc_html__( 'Publish to channel', 'storelink' ); ?></th>
		<td>
			<?php
			$cats = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
				)
			);
			?>
			<p>
				<label for="storelink-channel-publish-category"><?php echo esc_html__( 'One category', 'storelink' ); ?></label><br />
				<select id="storelink-channel-publish-category" name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[channel_publish_category]">
					<option value="0"><?php echo esc_html__( '— None —', 'storelink' ); ?></option>
					<?php if ( is_array( $cats ) ) : ?>
						<?php foreach ( $cats as $cat ) : ?>
							<?php if ( $cat instanceof WP_Term ) : ?>
								<option value="<?php echo esc_attr( (string) $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</p>
			<p>
				<label for="storelink-channel-publish-ids"><?php echo esc_html__( 'Product IDs', 'storelink' ); ?></label><br />
				<input
					id="storelink-channel-publish-ids"
					class="regular-text storelink-ltr"
					type="text"
					name="<?php echo esc_attr( SettingsStore::OPTION ); ?>[channel_publish_ids]"
					value=""
				/>
			</p>
			<p class="description"><?php echo esc_html__( 'Published simple or variable products.', 'storelink' ); ?></p>
			<p class="description"><?php echo esc_html__( 'Separate IDs with a comma or a space.', 'storelink' ); ?></p>
			<p>
				<button type="submit" class="button" name="storelink_channel_publish" value="1">
					<?php echo esc_html__( 'Queue publish', 'storelink' ); ?>
				</button>
			</p>
			<p class="description"><?php echo esc_html__( 'Saves settings, then queues one job per product.', 'storelink' ); ?></p>
			<p class="description"><?php echo esc_html__( 'Existing channel posts are edited.', 'storelink' ); ?></p>
			<p class="description"><?php echo esc_html__( 'A new post is only sent when none is stored or the message is gone.', 'storelink' ); ?></p>
		</td>
	</tr>
</table>

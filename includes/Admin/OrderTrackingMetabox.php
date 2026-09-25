<?php
/**
 * Tracking fields on the WooCommerce order screen.
 *
 * @package StoreLink
 */

namespace StoreLink\Admin;

use StoreLink\Core\Plugin;
use StoreLink\Tracking\ProviderRegistry;
use StoreLink\Tracking\TrackingService;

defined( 'ABSPATH' ) || exit;

/**
 * Order metabox for carrier and tracking number.
 */
class OrderTrackingMetabox {

	public function register(): void {
		add_meta_box(
			'storelink_tracking',
			__( 'StoreLink tracking', 'storelink' ),
			array( $this, 'render' ),
			array( 'shop_order', 'woocommerce_page_wc-orders' ),
			'side',
			'default'
		);
	}

	/**
	 * @param \WP_Post|\WC_Order $post_or_order Order.
	 */
	public function render( $post_or_order ): void {
		if ( ! current_user_can( Plugin::capability() ) ) {
			return;
		}

		$order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID ?? 0 );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$service = new TrackingService();
		if ( ! $service->order_needs_shipment( $order ) ) {
			echo '<p>' . esc_html__( 'This order has no physical shipment.', 'storelink' ) . '</p>';
			return;
		}

		$data = $service->get( $order );
		wp_nonce_field( 'storelink_save_tracking', 'storelink_tracking_nonce' );
		?>
		<p>
			<label for="storelink-tracking-carrier"><?php echo esc_html__( 'Carrier', 'storelink' ); ?></label>
			<select id="storelink-tracking-carrier" name="storelink_tracking_carrier" class="widefat">
				<?php foreach ( ProviderRegistry::instance()->all() as $provider ) : ?>
					<option value="<?php echo esc_attr( $provider->id() ); ?>" <?php selected( $data['carrier'], $provider->id() ); ?>>
						<?php echo esc_html( $provider->label() ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="storelink-tracking-number"><?php echo esc_html__( 'Tracking number', 'storelink' ); ?></label>
			<input
				id="storelink-tracking-number"
				class="widefat"
				type="text"
				name="storelink_tracking_number"
				value="<?php echo esc_attr( $data['number'] ); ?>"
			/>
		</p>
		<?php if ( $data['has'] ) : ?>
			<p><?php echo esc_html( sprintf( __( 'Shipment: %s', 'storelink' ), $data['status'] ) ); ?></p>
			<?php if ( '' !== $data['url'] ) : ?>
				<p><a href="<?php echo esc_url( $data['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open tracking page', 'storelink' ); ?></a></p>
			<?php endif; ?>
			<?php if ( ! empty( $data['history'] ) ) : ?>
				<p><strong><?php echo esc_html__( 'Tracking history', 'storelink' ); ?></strong></p>
				<ul>
					<?php foreach ( array_reverse( $data['history'] ) as $row ) : ?>
						<?php if ( ! is_array( $row ) ) : ?>
							<?php continue; ?>
						<?php endif; ?>
						<li>
							<?php
							echo esc_html(
								trim(
									(string) ( $row['at'] ?? '' ) . ' — ' . (string) ( $row['status'] ?? '' ) . ' ' . (string) ( $row['note'] ?? '' )
								)
							);
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * @param int $order_id Order id.
	 */
	public function save( $order_id ): void {
		if ( ! current_user_can( Plugin::capability() ) ) {
			return;
		}

		$nonce = isset( $_POST['storelink_tracking_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['storelink_tracking_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'storelink_save_tracking' ) ) {
			return;
		}

		$number = isset( $_POST['storelink_tracking_number'] ) ? sanitize_text_field( wp_unslash( $_POST['storelink_tracking_number'] ) ) : '';
		if ( '' === $number ) {
			return;
		}

		$carrier = isset( $_POST['storelink_tracking_carrier'] ) ? sanitize_key( wp_unslash( $_POST['storelink_tracking_carrier'] ) ) : 'manual';
		( new TrackingService() )->save( (int) $order_id, $carrier, $number );
	}
}

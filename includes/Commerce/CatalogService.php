<?php
/**
 * WooCommerce catalog reader.
 *
 * @package StoreLink
 */

namespace StoreLink\Commerce;

defined( 'ABSPATH' ) || exit;

/**
 * Paginated product catalog from WooCommerce APIs.
 */
class CatalogService {

	public const PER_PAGE = 5;

	/**
	 * @return array{items: array<int, array<string, mixed>>, page: int, pages: int, total: int}
	 */
	public function list_page( int $page = 1, string $search = '' ): array {
		$page = max( 1, $page );

		$args = array(
			'status'   => 'publish',
			'type'     => array( 'simple', 'variation' ),
			'limit'    => self::PER_PAGE,
			'page'     => $page,
			'paginate' => true,
			'return'   => 'objects',
			'orderby'  => 'date',
			'order'    => 'DESC',
		);

		$search = sanitize_text_field( $search );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$result = wc_get_products( $args );

		$items = array();
		foreach ( $result->products as $product ) {
			if ( $product instanceof \WC_Product ) {
				$items[] = $this->to_array( $product );
			}
		}

		$total = (int) $result->total;
		$pages = max( 1, (int) ceil( $total / self::PER_PAGE ) );

		return array(
			'items' => $items,
			'page'  => $page,
			'pages' => $pages,
			'total' => $total,
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get( int $product_id ): ?array {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product ) {
			return null;
		}

		return $this->to_array( $product );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array( \WC_Product $product ): array {
		$image = '';
		$id    = $product->get_image_id();
		if ( $id ) {
			$src = wp_get_attachment_image_url( (int) $id, 'medium' );
			if ( $src ) {
				$image = $src;
			}
		}

		$purchasable = $product->is_purchasable() && $product->is_in_stock()
			&& in_array( $product->get_type(), array( 'simple', 'variation' ), true );

		return array(
			'id'           => $product->get_id(),
			'name'         => $product->get_name(),
			'sku'          => $product->get_sku(),
			'type'         => $product->get_type(),
			'price'        => $product->get_price(),
			'price_html'   => wp_strip_all_tags( html_entity_decode( (string) $product->get_price_html() ) ),
			'stock'        => $product->get_stock_quantity(),
			'in_stock'     => $product->is_in_stock(),
			'purchasable'  => $purchasable,
			'image'        => $image,
			'permalink'    => $product->get_permalink(),
		);
	}
}

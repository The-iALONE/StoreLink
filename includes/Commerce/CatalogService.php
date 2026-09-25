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
	public function list_page( int $page = 1, string $search = '', int $category_id = 0 ): array {
		$page = max( 1, $page );

		$args = array(
			'status'   => 'publish',
			'type'     => array( 'simple', 'variable' ),
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

		$category_id = absint( $category_id );
		if ( $category_id > 0 && '' === $search ) {
			$term = get_term( $category_id, 'product_cat' );
			if ( $term instanceof \WP_Term ) {
				$args['category'] = array( $term->slug );
			}
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
	 * @return array<int, array{id:int, name:string, slug:string}>
	 */
	public function list_categories( int $parent = 0 ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'parent'     => max( 0, $parent ),
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$hide_default = ! \StoreLink\Admin\SettingsStore::show_uncategorized_in_bot();
		$default_id   = $hide_default ? (int) get_option( 'default_product_cat' ) : 0;

		$list = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			if ( $default_id > 0 && (int) $term->term_id === $default_id ) {
				continue;
			}
			$list[] = array(
				'id'   => (int) $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			);
		}

		return $list;
	}

	public function category_name( int $category_id ): string {
		$term = get_term( absint( $category_id ), 'product_cat' );
		return $term instanceof \WP_Term ? $term->name : '';
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
	 * @param array<string, string> $selected Attribute slug => option slug.
	 * @return array{key:string, label:string, options: array<int, array{slug:string, name:string}>}|null
	 */
	public function next_attribute( int $product_id, array $selected ): ?array {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product_Variable ) {
			return null;
		}

		foreach ( $product->get_variation_attributes() as $attribute => $options ) {
			$key = sanitize_title( $attribute );
			if ( isset( $selected[ $key ] ) && '' !== $selected[ $key ] ) {
				continue;
			}

			$allowed = $this->available_options( $product, $key, $selected, is_array( $options ) ? $options : array() );
			if ( empty( $allowed ) ) {
				continue;
			}

			$label = wc_attribute_label( $attribute, $product );
			$list  = array();
			foreach ( $allowed as $slug ) {
				$list[] = array(
					'slug' => (string) $slug,
					'name' => $this->option_label( $attribute, (string) $slug ),
				);
			}

			return array(
				'key'     => $key,
				'label'   => $label,
				'options' => $list,
			);
		}

		return null;
	}

	/**
	 * @param array<string, string> $selected Selected attributes.
	 */
	public function match_variation( int $product_id, array $selected ): ?\WC_Product_Variation {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product_Variable ) {
			return null;
		}

		$data = array();
		foreach ( $selected as $key => $value ) {
			$data[ 'attribute_' . $key ] = $value;
		}

		$store = \WC_Data_Store::load( 'product' );
		$vid   = (int) $store->find_matching_product_variation( $product, $data );
		if ( $vid < 1 ) {
			return null;
		}

		$variation = wc_get_product( $vid );
		return $variation instanceof \WC_Product_Variation ? $variation : null;
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

		$type        = $product->get_type();
		$purchasable = $product->is_purchasable() && 'outofstock' !== $product->get_stock_status()
			&& in_array( $type, array( 'simple', 'variation', 'variable' ), true );

		return array(
			'id'          => $product->get_id(),
			'name'        => $product->get_name(),
			'sku'         => $product->get_sku(),
			'type'        => $type,
			'price'       => $product->get_price(),
			'price_html'  => PriceFormat::from_html( (string) $product->get_price_html() ),
			'price_now'   => PriceFormat::amount( (float) $product->get_price() ),
			'stock'       => $product->get_stock_quantity(),
			'in_stock'    => $product->is_in_stock(),
			'purchasable'  => $purchasable,
			'image'        => $image,
			'permalink'    => $product->get_permalink(),
			'short_desc'   => wp_strip_all_tags( (string) $product->get_short_description() ),
			'virtual'      => $product->is_virtual(),
			'downloadable' => $product->is_downloadable(),
		);
	}

	/**
	 * @param array<string, string> $selected Selected attributes.
	 * @param array<int, string>    $options  Attribute options.
	 * @return array<int, string>
	 */
	private function available_options( \WC_Product_Variable $product, string $key, array $selected, array $options ): array {
		$allowed = array();
		foreach ( $product->get_available_variations( 'objects' ) as $variation ) {
			if ( ! $variation instanceof \WC_Product_Variation || ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
				continue;
			}
			$attrs = $variation->get_variation_attributes();
			$ok    = true;
			foreach ( $selected as $sel_key => $sel_val ) {
				$var_val = (string) ( $attrs[ 'attribute_' . $sel_key ] ?? $attrs[ $sel_key ] ?? '' );
				if ( '' !== $var_val && $var_val !== $sel_val ) {
					$ok = false;
					break;
				}
			}
			if ( ! $ok ) {
				continue;
			}
			$current = (string) ( $attrs[ 'attribute_' . $key ] ?? $attrs[ $key ] ?? '' );
			if ( '' !== $current ) {
				$allowed[] = $current;
			} else {
				foreach ( $options as $option ) {
					$allowed[] = (string) $option;
				}
			}
		}

		return array_values( array_unique( $allowed ) );
	}

	private function option_label( string $attribute, string $slug ): string {
		if ( taxonomy_exists( $attribute ) ) {
			$term = get_term_by( 'slug', $slug, $attribute );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term->name;
			}
		}

		return $slug;
	}

	public function set_stock_status( int $product_id, string $status ): \WP_Error|\WC_Product {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product || $product->is_type( 'variable' ) ) {
			return new \WP_Error( 'not_found', __( 'Product not found.', 'storelink' ) );
		}

		$status = 'outofstock' === $status ? 'outofstock' : 'instock';
		$product->set_stock_status( $status );
		$product->save();

		return $product;
	}

	public function set_stock_quantity( int $product_id, int $qty ): \WP_Error|\WC_Product {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product || $product->is_type( 'variable' ) ) {
			return new \WP_Error( 'not_found', __( 'Product not found.', 'storelink' ) );
		}

		$qty = max( 0, $qty );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $qty );
		if ( $qty < 1 ) {
			$product->set_stock_status( 'outofstock' );
		} else {
			$product->set_stock_status( 'instock' );
		}
		$product->save();

		return $product;
	}

	public function adjust_stock( int $product_id, int $delta ): \WP_Error|\WC_Product {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product || $product->is_type( 'variable' ) ) {
			return new \WP_Error( 'not_found', __( 'Product not found.', 'storelink' ) );
		}

		return $this->set_stock_quantity( $product_id, (int) $product->get_stock_quantity() + $delta );
	}
}

<?php
/**
 * Filter registry.
 *
 * @package PricePilot
 */

namespace PricePilot\Products\Filters;

use PricePilot\Products\Filters\Filters\CategoryFilter;
use PricePilot\Products\Filters\Filters\OnSaleFilter;
use PricePilot\Products\Filters\Filters\PostStatusFilter;
use PricePilot\Products\Filters\Filters\PriceMaxFilter;
use PricePilot\Products\Filters\Filters\PriceMinFilter;
use PricePilot\Products\Filters\Filters\ProductTypeFilter;
use PricePilot\Products\Filters\Filters\SearchFilter;
use PricePilot\Products\Filters\Filters\SkuFilter;
use PricePilot\Products\Filters\Filters\StockMaxFilter;
use PricePilot\Products\Filters\Filters\StockMinFilter;
use PricePilot\Products\Filters\Filters\StockStatusFilter;
use PricePilot\Products\Filters\Filters\TagFilter;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and applies product filters.
 */
class FilterRegistry {

	/**
	 * Registered filters keyed by filter key.
	 *
	 * @var array<string, FilterInterface>
	 */
	private array $filters = array();

	/**
	 * Constructor — register default filters.
	 */
	public function __construct() {
		$defaults = array(
			new SearchFilter(),
			new SkuFilter(),
			new CategoryFilter(),
			new TagFilter(),
			new PriceMinFilter(),
			new PriceMaxFilter(),
			new StockMinFilter(),
			new StockMaxFilter(),
			new StockStatusFilter(),
			new ProductTypeFilter(),
			new PostStatusFilter(),
			new OnSaleFilter(),
		);

		foreach ( $defaults as $filter ) {
			$this->register( $filter );
		}
	}

	/**
	 * Register a filter.
	 *
	 * @param FilterInterface $filter Filter instance.
	 * @return void
	 */
	public function register( FilterInterface $filter ): void {
		$this->filters[ $filter->key() ] = $filter;
	}

	/**
	 * Get all registered filter keys and metadata.
	 *
	 * @return array<int, array{key:string}>
	 */
	public function get_definitions(): array {
		$definitions = array();

		foreach ( $this->filters as $key => $filter ) {
			$definitions[] = array( 'key' => $key );
		}

		return $definitions;
	}

	/**
	 * Apply filters from request params to query args.
	 *
	 * @param array<string, mixed> $query_args Base query args.
	 * @param array<string, mixed> $params     Filter params.
	 * @return array<string, mixed>
	 */
	public function apply_all( array $query_args, array $params ): array {
		foreach ( $params as $key => $value ) {
			if ( null === $value || '' === $value ) {
				continue;
			}

			if ( ! isset( $this->filters[ $key ] ) ) {
				continue;
			}

			$filter    = $this->filters[ $key ];
			$sanitized = $filter->sanitize( $value );
			$query_args = $filter->apply( $query_args, $sanitized );
		}

		return $query_args;
	}
}

<?php
/**
 * Product filter interface.
 *
 * @package PricePilot
 */

namespace PricePilot\Products\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Contract for product query filters.
 */
interface FilterInterface {

	/**
	 * Filter key used in API/query params.
	 *
	 * @return string
	 */
	public function key(): string;

	/**
	 * Apply filter to wc_get_products args.
	 *
	 * @param array<string, mixed> $query_args Query arguments.
	 * @param mixed                $value      Filter value.
	 * @return array<string, mixed>
	 */
	public function apply( array $query_args, mixed $value ): array;

	/**
	 * Sanitize incoming filter value.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	public function sanitize( mixed $value ): mixed;
}

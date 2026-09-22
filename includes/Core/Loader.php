<?php
/**
 * Hook loader.
 *
 * @package StoreLink
 */

namespace StoreLink\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registers WordPress hooks from arrays.
 */
class Loader {

	/**
	 * @var array<int, array{hook:string, component:object, callback:string, priority:int, accepted_args:int}>
	 */
	protected array $actions = array();

	/**
	 * @var array<int, array{hook:string, component:object, callback:string, priority:int, accepted_args:int}>
	 */
	protected array $filters = array();

	public function add_action( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	public function add_filter( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	public function run(): void {
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}

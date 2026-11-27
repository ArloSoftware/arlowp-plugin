<?php
/**
 * Internal metadata for one Arlo shortcode render.
 */

namespace ArloTraining\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carries handler metadata without changing the WordPress content filter contract.
 *
 * @internal
 */
final class ShortcodeRenderContext {

	private static ?self $current = null;

	private ?bool $uses_class_layout = null;

	/**
	 * Run the content filters for this render, restoring any enclosing context afterward.
	 *
	 * @param self     $context Context for this render.
	 * @param callable $render  Content filter pipeline.
	 * @return mixed Filtered shortcode output.
	 */
	public static function with_current( self $context, callable $render ) {
		$previous      = self::$current;
		self::$current = $context;
		try {
			return $render();
		} finally {
			self::$current = $previous;
		}
	}

	public static function current(): ?self {
		return self::$current;
	}

	public function set_uses_class_layout( bool $uses_class_layout ): void {
		$this->uses_class_layout = $uses_class_layout;
	}

	/**
	 * @return bool|null Whether the resolved layout is class, or null if the handler did not report it.
	 */
	public function uses_class_layout(): ?bool {
		return $this->uses_class_layout;
	}
}

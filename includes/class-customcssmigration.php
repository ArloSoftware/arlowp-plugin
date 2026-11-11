<?php

namespace ArloTraining;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Moves legacy Custom CSS into WordPress's Additional CSS, verifies the write, and can take it back out again.
 *
 * The CSS is written inside a fenced block so it can be found again after the administrator edits Additional CSS.
 * Classic themes use the Customizer's `custom_css` post; block themes use the Site Editor's global-styles CSS.
 * Every write is read back from the database and rolled back when it does not match.
 */
class CustomCssMigration {

	const STORE_CUSTOM_CSS    = 'custom_css';
	const STORE_GLOBAL_STYLES = 'global_styles';

	const FENCE_VERSION = 1;
	const FENCE_BEGIN   = 'ARLO-CUSTOM-CSS:BEGIN';
	const FENCE_END     = 'ARLO-CUSTOM-CSS:END';

	const INTEGRITY_INTACT   = 'intact';
	const INTEGRITY_EDITED   = 'edited';
	const INTEGRITY_DAMAGED  = 'damaged';
	const INTEGRITY_ORPHANED = 'orphaned';

	/**
	 * Size above which the check reports how much CSS is printed on every page.
	 */
	const SIZE_NOTICE_BYTES = 51200;

	/**
	 * Which core store holds Additional CSS for the active theme.
	 *
	 * @return string One of the STORE_* constants.
	 */
	public static function current_store(): string {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ? self::STORE_GLOBAL_STYLES : self::STORE_CUSTOM_CSS;
	}

	/**
	 * Where an administrator edits Additional CSS for a store.
	 *
	 * @param string $store One of the STORE_* constants.
	 * @return string Admin URL.
	 */
	public static function destination_url( string $store ): string {
		if ( self::STORE_GLOBAL_STYLES === $store ) {
			return add_query_arg(
				[
					'p'       => '/styles',
					'section' => '/css',
				],
				admin_url( 'site-editor.php' )
			);
		}
		return add_query_arg( 'autofocus[section]', 'custom_css', admin_url( 'customize.php' ) );
	}

	/**
	 * Where an administrator finds Additional CSS in the admin menus.
	 *
	 * @param string $store One of the STORE_* constants.
	 * @return string Translated menu path.
	 */
	public static function destination_label( string $store ): string {
		return self::STORE_GLOBAL_STYLES === $store
			? __( 'Appearance > Editor > Styles > Additional CSS', 'arlo-training-and-event-management-system' )
			: __( 'Appearance > Customize > Additional CSS', 'arlo-training-and-event-management-system' );
	}

	/**
	 * Builds the fenced block holding the migrated CSS.
	 *
	 * The markers use plain ASCII with no angle brackets or ampersands, so WordPress's HTML filters leave them alone.
	 *
	 * @param string $css Migrated CSS.
	 * @param string $id  Fence identifier.
	 * @return string
	 */
	public static function build_block( string $css, string $id ): string {
		return '/* ' . self::FENCE_BEGIN . ' id=' . $id . ' v=' . self::FENCE_VERSION . " */\n"
			. CustomCss::normalize_line_endings( $css )
			. "\n/* " . self::FENCE_END . ' id=' . $id . ' */';
	}

	/**
	 * Generates a fence identifier.
	 *
	 * @return string Eight lowercase hexadecimal characters.
	 */
	public static function new_fence_id(): string {
		return bin2hex( random_bytes( 4 ) );
	}

	/**
	 * Finds the one Arlo block in stored Additional CSS.
	 *
	 * A missing, duplicated, mismatched or reversed marker means the block cannot be trusted, so nothing is returned.
	 *
	 * @param string $content Stored Additional CSS.
	 * @return array{id: string, start: int, end: int, inner: string}|null
	 */
	public static function find_block( string $content ): ?array {
		$begin_pattern = '~/\* ' . preg_quote( self::FENCE_BEGIN, '~' ) . ' id=([0-9a-f]{8}) v=[0-9]+ \*/\r?\n~';
		$end_pattern   = '~\r?\n/\* ' . preg_quote( self::FENCE_END, '~' ) . ' id=([0-9a-f]{8}) \*/~';
		if ( 1 !== preg_match_all( $begin_pattern, $content, $begin, PREG_OFFSET_CAPTURE ) || 1 !== preg_match_all( $end_pattern, $content, $end, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}
		if ( $begin[1][0][0] !== $end[1][0][0] ) {
			return null;
		}
		$start       = $begin[0][0][1];
		$inner_start = $start + strlen( $begin[0][0][0] );
		$inner_end   = $end[0][0][1];
		if ( $inner_end < $inner_start ) {
			return null;
		}
		return [
			'id'    => $begin[1][0][0],
			'start' => $start,
			'end'   => $end[0][0][1] + strlen( $end[0][0][0] ),
			'inner' => CustomCss::normalize_line_endings( substr( $content, $inner_start, $inner_end - $inner_start ) ),
		];
	}

	/**
	 * Returns the offset just past any leading comments, `@charset` and `@import` statements.
	 *
	 * A block placed here keeps existing imports valid, because imports are only honoured before the first rule.
	 *
	 * @param string $css Existing Additional CSS.
	 * @return int Byte offset; zero when there is no prelude.
	 */
	public static function prelude_end( string $css ): int {
		$length = strlen( $css );
		$offset = 0;
		$end    = 0;
		while ( $offset < $length ) {
			$next = $offset;
			while ( $next < $length && ctype_space( $css[ $next ] ) ) {
				++$next;
			}
			if ( '/*' === substr( $css, $next, 2 ) ) {
				$close = strpos( $css, '*/', $next + 2 );
				if ( false === $close ) {
					return $end;
				}
				$offset = $close + 2;
				continue;
			}
			if ( 1 !== preg_match( '/\G@(?:charset|import)\b/i', $css, $unused, 0, $next ) ) {
				return $end;
			}
			$quote = '';
			$depth = 0;
			$scan  = $next;
			while ( $scan < $length ) {
				$char = $css[ $scan ];
				if ( '' !== $quote ) {
					if ( '\\' === $char ) {
						++$scan;
					} elseif ( $char === $quote ) {
						$quote = '';
					}
				} elseif ( '"' === $char || "'" === $char ) {
					$quote = $char;
				} elseif ( '(' === $char ) {
					++$depth;
				} elseif ( ')' === $char ) {
					--$depth;
				} elseif ( ';' === $char && 0 === $depth ) {
					break;
				}
				++$scan;
			}
			if ( $scan >= $length ) {
				return $end;
			}
			$offset = $scan + 1;
			$end    = $offset;
		}
		return $end;
	}

	/**
	 * Places the block before the existing Additional CSS, after any leading imports.
	 *
	 * Plugin CSS has always printed before the site's own Additional CSS, so inserting first keeps today's order.
	 * remove_block() undoes this exactly: the text around the block is left as it was.
	 *
	 * @param string $existing Existing Additional CSS.
	 * @param string $block    Block from build_block().
	 * @return string
	 */
	public static function insert_block( string $existing, string $block ): string {
		$at = self::prelude_end( $existing );
		if ( 0 === $at ) {
			return $block . "\n" . $existing;
		}
		return substr( $existing, 0, $at ) . "\n" . $block . substr( $existing, $at );
	}

	/**
	 * Removes the block and the one line break insert_block() added beside it.
	 *
	 * @param string                                                  $content Stored Additional CSS.
	 * @param array{id: string, start: int, end: int, inner: string} $block   Block from find_block().
	 * @return string
	 */
	public static function remove_block( string $content, array $block ): string {
		$start = $block['start'];
		$end   = $block['end'];
		if ( 0 === $start ) {
			if ( "\r\n" === substr( $content, $end, 2 ) ) {
				$end += 2;
			} elseif ( "\n" === substr( $content, $end, 1 ) ) {
				++$end;
			}
		} elseif ( "\r\n" === substr( $content, $start - 2, 2 ) ) {
			$start -= 2;
		} elseif ( "\n" === substr( $content, $start - 1, 1 ) ) {
			--$start;
		}
		return substr( $content, 0, $start ) . substr( $content, $end );
	}

	/**
	 * Reads the raw Additional CSS for the active theme.
	 *
	 * @param string $store One of the STORE_* constants.
	 * @param bool   $fresh Whether to read the database row again instead of an object-cache copy.
	 * @return string|null The stored text, or null when it cannot be read.
	 */
	public static function read_store( string $store, bool $fresh = false ): ?string {
		if ( self::STORE_GLOBAL_STYLES === $store ) {
			$row = \WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( wp_get_theme(), false );
			if ( empty( $row['ID'] ) ) {
				return '';
			}
			$post = self::load_post( (int) $row['ID'], $fresh );
			if ( null === $post ) {
				return null;
			}
			$data = json_decode( $post->post_content, true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
				return null;
			}
			$css = $data['styles']['css'] ?? '';
			return is_string( $css ) ? $css : null;
		}

		$post = wp_get_custom_css_post( get_stylesheet() );
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}
		$post = self::load_post( $post->ID, $fresh );
		return null === $post ? null : (string) $post->post_content;
	}

	/**
	 * Loads a post, optionally bypassing the object cache.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $fresh   Whether to clear the cached copy first.
	 * @return \WP_Post|null
	 */
	private static function load_post( int $post_id, bool $fresh ): ?\WP_Post {
		if ( $fresh ) {
			clean_post_cache( $post_id );
		}
		$post = get_post( $post_id );
		return $post instanceof \WP_Post ? $post : null;
	}

	/**
	 * Writes the whole Additional CSS text for the active theme.
	 *
	 * The caller must have checked edit_css: for a user without it core either rewrites the text or accepts the request and drops it.
	 *
	 * @param string $store   One of the STORE_* constants.
	 * @param string $content New Additional CSS.
	 * @return true|\WP_Error
	 */
	public static function write_store( string $store, string $content ) {
		if ( self::STORE_GLOBAL_STYLES === $store ) {
			$post_id = \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
			if ( ! $post_id ) {
				return new \WP_Error( 'write_failed' );
			}
			$post = self::load_post( (int) $post_id, true );
			$data = null === $post ? null : json_decode( $post->post_content, true );
			if ( null === $post || JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
				return new \WP_Error( 'unreadable' );
			}
			// The REST route replaces the whole styles object, so every other style is sent back unchanged.
			$styles = isset( $data['styles'] ) && is_array( $data['styles'] ) ? $data['styles'] : [];
			if ( '' === $content ) {
				unset( $styles['css'] );
			} else {
				$styles['css'] = $content;
			}
			$request = new \WP_REST_Request( 'POST', '/wp/v2/global-styles/' . (int) $post_id );
			$request->set_body_params( [ 'styles' => $styles ] );
			$response = rest_do_request( $request );
			return $response->is_error() ? new \WP_Error( 'write_failed' ) : true;
		}

		$result = wp_update_custom_css_post( $content, [ 'stylesheet' => get_stylesheet() ] );
		return is_wp_error( $result ) ? new \WP_Error( 'write_failed' ) : true;
	}

	/**
	 * Whether two stored texts are the same once line endings are normalized.
	 *
	 * @param string $a First text.
	 * @param string $b Second text.
	 * @return bool
	 */
	private static function same_text( string $a, string $b ): bool {
		return CustomCss::normalize_line_endings( $a ) === CustomCss::normalize_line_endings( $b );
	}

	/**
	 * Classifies any Arlo markers already in stored Additional CSS, before a migration writes anything.
	 *
	 * Markers can be left by an earlier migration whose record was lost, for example when 5.1.x saved the settings after a
	 * rollback. A second block next to one of them would stop either from being found, so a migration never adds one.
	 * Any marker text, in any letter case, counts, because a filter may have rewritten it.
	 *
	 * @param string $content Stored Additional CSS.
	 * @param string $css     CSS about to be migrated.
	 * @return array{state: string, block: array|null} 'none' when there are no markers; 'identical' when the only block holds exactly this CSS and can be adopted; otherwise 'conflict'.
	 */
	public static function inspect_existing_block( string $content, string $css ): array {
		if ( 1 !== preg_match( '~ARLO-CUSTOM-CSS:(?:BEGIN|END)~i', $content ) ) {
			return [
				'state' => 'none',
				'block' => null,
			];
		}
		$block = self::find_block( $content );
		if ( null !== $block && 2 === preg_match_all( '~ARLO-CUSTOM-CSS:(?:BEGIN|END)~i', $content ) && self::same_text( $block['inner'], $css ) ) {
			return [
				'state' => 'identical',
				'block' => $block,
			];
		}
		return [
			'state' => 'conflict',
			'block' => null,
		];
	}

	/**
	 * Whether a migrated site's CSS was written for a different theme than the active one.
	 *
	 * WordPress keeps Additional CSS separately for each theme, so a theme or child-theme switch leaves it behind.
	 *
	 * @param array $state State from CustomCss::resolve_state().
	 * @return bool
	 */
	public static function is_orphaned( array $state ): bool {
		return CustomCss::MODE_MIGRATED === $state['mode'] && is_array( $state['migration'] )
			&& ( $state['migration']['stylesheet'] ?? null ) !== get_stylesheet();
	}

	/**
	 * Whether the Custom CSS tab needs its editor: there is legacy CSS to review, an orphaned migration to copy again, or a migrated block that went missing.
	 *
	 * @param array $settings Saved settings.
	 * @return bool
	 */
	public static function needs_editor( array $settings ): bool {
		$state = CustomCss::resolve_state( $settings );
		if ( CustomCss::MODE_LEGACY === $state['mode'] || self::is_orphaned( $state ) ) {
			return true;
		}
		// The block was removed after migrating, for example by restoring an earlier version of the site's styles: the CSS is
		// no longer applied anywhere, so the saved copy can be reviewed and migrated again.
		return CustomCss::MODE_MIGRATED === $state['mode'] && self::INTEGRITY_DAMAGED === self::status( $settings )['integrity'];
	}

	/**
	 * Describes how a migrated site's block is doing.
	 *
	 * @param array $settings Saved settings.
	 * @return array{mode: string, integrity: string|null, store: string|null, migrated_at: int|null, destination_url: string, destination_label: string, back_to_before: bool}
	 */
	public static function status( array $settings ): array {
		$state     = CustomCss::resolve_state( $settings );
		$migration = $state['migration'];
		$store     = is_array( $migration ) && isset( $migration['store'] ) ? (string) $migration['store'] : self::current_store();
		$result    = [
			'mode'              => $state['mode'],
			'integrity'         => null,
			'store'             => is_array( $migration ) ? $store : null,
			'migrated_at'       => is_array( $migration ) && isset( $migration['migrated_at'] ) && is_int( $migration['migrated_at'] ) ? $migration['migrated_at'] : null,
			'destination_url'   => self::destination_url( $store ),
			'destination_label' => self::destination_label( $store ),
			'back_to_before'    => false,
		];
		if ( CustomCss::MODE_MIGRATED !== $state['mode'] || ! is_array( $migration ) ) {
			return $result;
		}
		if ( self::is_orphaned( $state ) ) {
			$result['integrity'] = self::INTEGRITY_ORPHANED;
			return $result;
		}
		$content = self::read_store( $store );
		$block   = null === $content ? null : self::find_block( $content );
		if ( null === $block || ( $migration['fence_id'] ?? null ) !== $block['id'] ) {
			$result['integrity'] = self::INTEGRITY_DAMAGED;
			// Additional CSS that hashes to what it was before the migration was put back exactly, for example by restoring an earlier revision.
			$result['back_to_before'] = null !== $content && hash( 'sha256', $content ) === ( $migration['previous_hash'] ?? null );
			return $result;
		}
		$result['integrity'] = hash( 'sha256', $block['inner'] ) === ( $migration['content_hash'] ?? null ) ? self::INTEGRITY_INTACT : self::INTEGRITY_EDITED;
		return $result;
	}

	/**
	 * Reports whether CSS can be migrated, combining the CSS scan with checks on this site.
	 *
	 * @param string      $css   CSS about to be migrated.
	 * @param string|null $store Destination store; defaults to the active theme's.
	 * @return array Result from CustomCssCompatibility::analyze() with site findings added.
	 */
	public static function preflight( string $css, ?string $store = null ): array {
		$store    = $store ?? self::current_store();
		$settings = get_option( 'arlo_settings', [] );
		$state    = CustomCss::resolve_state( is_array( $settings ) ? $settings : [] );
		$analysis = CustomCssCompatibility::analyze(
			$css,
			[
				'block_theme' => self::STORE_GLOBAL_STYLES === $store,
				'from_file'   => CustomCss::links_legacy_file( $state ),
			]
		);
		$extra    = [];

		// Migrating nothing would strip the CSS the site uses today and overwrite the saved copy with an empty one.
		// It is the only thing to report: nothing else matters until there is CSS to migrate.
		if ( CustomCss::is_blank_css( $css ) ) {
			$analysis['findings'] = [ self::finding( 'empty-css', 'blocking', __( 'There is no CSS in the editor to migrate. Put your CSS back in the editor to migrate it, or use Discard this CSS below if you no longer need it.', 'arlo-training-and-event-management-system' ) ) ];
			$analysis['blocked']  = true;
			$analysis['status']   = 'warning';
			return $analysis;
		}

		// Pasted marker comments would put a second marker pair inside the block, so the block could no longer be found and the write would be undone.
		if ( 1 === preg_match( '~ARLO-CUSTOM-CSS:(?:BEGIN|END)~i', $css ) ) {
			$extra[] = self::finding( 'arlo-markers-in-css', 'blocking', __( 'This CSS contains the ARLO-CUSTOM-CSS comment lines Arlo uses to find your CSS in Additional CSS. Remove those comment lines, keeping the CSS between them, and check again.', 'arlo-training-and-event-management-system' ) );
		}

		// Some themes and plugins remove WordPress's output of Additional CSS, so CSS moved there would silently stop applying.
		if ( self::STORE_CUSTOM_CSS === $store && false === has_action( 'wp_head', 'wp_custom_css_cb' ) ) {
			$extra[] = self::finding( 'custom-css-not-printed', 'blocking', __( 'Your theme or a plugin stops WordPress from printing Additional CSS on your site, so migrated CSS would not appear. Ask your developer to restore it, or leave your CSS where it is.', 'arlo-training-and-event-management-system' ) );
		}

		foreach ( CustomCss::find_violations( $css ) as $violation ) {
			if ( CustomCss::VIOLATION_STYLE_CLOSE === $violation ) {
				$extra[] = self::finding( 'style-close', 'blocking', __( 'This CSS contains a closing style tag (or ends in the start of one), which would end the page style element. Remove it before migrating.', 'arlo-training-and-event-management-system' ) );
			} else {
				$extra[] = self::finding( 'invalid-encoding', 'blocking', __( 'This CSS is not valid UTF-8 text, which WordPress would silently damage. Re-save it as UTF-8 before migrating.', 'arlo-training-and-event-management-system' ) );
			}
		}

		if ( ! CustomCss::current_user_can_edit() ) {
			$message = CustomCss::is_editing_disabled_by_configuration()
				? __( 'This site disables unfiltered HTML (DISALLOW_UNFILTERED_HTML), so Additional CSS cannot be edited by anyone and the CSS cannot be migrated until that is changed.', 'arlo-training-and-event-management-system' )
				: __( 'You need WordPress\'s edit_css permission (on a multisite network, a super administrator) to migrate this CSS.', 'arlo-training-and-event-management-system' );
			$extra[] = self::finding( 'capability', 'blocking', $message );
		}

		$existing   = self::read_store( $store );
		$inspection = null === $existing ? [ 'state' => 'none' ] : self::inspect_existing_block( $existing, $css );
		if ( 'conflict' === $inspection['state'] ) {
			$extra[] = self::finding( 'existing-arlo-block', 'blocking', __( 'Additional CSS already contains an Arlo block from an earlier migration (between the ARLO-CUSTOM-CSS comment markers) that does not match the CSS in the editor. It is not overwritten, because it may hold edits you made there. Review it, then delete that block from Additional CSS or copy what you need from it into the editor above (leaving out the ARLO-CUSTOM-CSS comment lines), and check again.', 'arlo-training-and-event-management-system' ), self::additional_css_link( $store ) );
		} elseif ( 'identical' === $inspection['state'] ) {
			$extra[] = self::finding( 'existing-arlo-block-identical', 'info', __( 'Additional CSS already contains exactly this CSS from an earlier migration. Migrating links the plugin to that block and does not change Additional CSS.', 'arlo-training-and-event-management-system' ), self::additional_css_link( $store ) );
		} elseif ( null !== $existing && ! CustomCss::is_blank_css( $existing ) ) {
			$view    = __( 'view', 'arlo-training-and-event-management-system' );
			$extra[] = self::finding(
				'existing-additional-css',
				'info',
				sprintf(
					/* translators: %s: the word "view", which becomes a link that opens Additional CSS in a new tab. */
					__( 'This site already has existing content in Additional CSS (%s). Your custom CSS will be added above it.', 'arlo-training-and-event-management-system' ),
					$view
				),
				self::additional_css_link( $store, $view )
			);
		}

		if ( strlen( $css ) > self::SIZE_NOTICE_BYTES ) {
			$extra[] = self::finding(
				'size',
				'info',
				sprintf(
					/* translators: %s: size of the CSS, for example "120 KB". */
					__( 'This CSS is %s. WordPress prints Additional CSS inline on every page, so it is not cached separately by browsers.', 'arlo-training-and-event-management-system' ),
					size_format( strlen( $css ) )
				)
			);
		}

		foreach ( $extra as $finding ) {
			$analysis['findings'][] = $finding;
			if ( 'blocking' === $finding['level'] ) {
				$analysis['blocked'] = true;
				if ( 'clear' === $analysis['status'] ) {
					$analysis['status'] = 'warning';
				}
			}
		}
		return $analysis;
	}

	/**
	 * Builds a finding that is not tied to a position in the CSS.
	 *
	 * @param string $code    Finding code.
	 * @param string $level   'blocking' or 'info'.
	 * @param string $message Translated message.
	 * @param array|null $link Optional words in the message to link, and where to; see additional_css_link().
	 * @return array
	 */
	private static function finding( string $code, string $level, string $message, ?array $link = null ): array {
		$finding = [
			'code'    => $code,
			'line'    => 0,
			'column'  => 0,
			'level'   => $level,
			'message' => $message,
		];
		if ( null !== $link ) {
			$finding['link'] = $link;
		}
		return $finding;
	}

	/**
	 * Describes the link that turns the words "Additional CSS" in a finding into a way to open it.
	 *
	 * @param string $store One of the STORE_* constants.
	 * @return array{text: string, url: string} The words to link, which must appear in the message, and where they lead.
	 */
	private static function additional_css_link( string $store, ?string $text = null ): array {
		return [
			'text' => $text ?? __( 'Additional CSS', 'arlo-training-and-event-management-system' ),
			'url'  => self::destination_url( $store ),
		];
	}

	/**
	 * Copies legacy Custom CSS into Additional CSS, after which the plugin holds and prints nothing.
	 *
	 * Nothing is left changed on failure: the previous Additional CSS is restored and the new state is not saved.
	 *
	 * @param string $css      CSS from the editor.
	 * @param array  $settings Saved settings.
	 * @param int    $user_id  Current user; a write with no user is refused.
	 * @return array|\WP_Error Updated settings on success, otherwise an error whose code names the reason.
	 */
	public static function migrate( string $css, array $settings, int $user_id ) {
		if ( $user_id <= 0 || ! CustomCss::current_user_can_edit() ) {
			return new \WP_Error( 'capability' );
		}
		$state = CustomCss::resolve_state( $settings );
		$store = self::current_store();
		// Legacy CSS, a migration left behind by a theme switch, or a migrated block that has gone missing can all be migrated.
		if ( ! self::needs_editor( $settings ) ) {
			return new \WP_Error( 'wrong_state' );
		}
		$analysis = self::preflight( $css, $store );
		if ( ! empty( $analysis['blocked'] ) ) {
			return new \WP_Error( 'blocked', '', $analysis );
		}

		$before = self::read_store( $store, true );
		if ( null === $before ) {
			return new \WP_Error( 'unreadable' );
		}
		// Read again fresh, because Additional CSS may have changed since the check above.
		$inspection = self::inspect_existing_block( $before, $css );
		if ( 'conflict' === $inspection['state'] ) {
			return new \WP_Error( 'existing_block' );
		}
		$adopting = 'identical' === $inspection['state'];
		$id       = $adopting ? $inspection['block']['id'] : self::new_fence_id();
		$payload  = $adopting ? $before : self::insert_block( $before, self::build_block( $css, $id ) );

		$written = $adopting ? true : self::write_store( $store, $payload );
		$after   = is_wp_error( $written ) ? null : self::read_store( $store, true );
		$block   = null === $after ? null : self::find_block( $after );
		$valid   = null !== $block && $block['id'] === $id && self::same_text( $after, $payload )
			&& hash( 'sha256', $block['inner'] ) === hash( 'sha256', CustomCss::normalize_line_endings( $css ) );
		if ( ! $valid ) {
			if ( ! $adopting ) {
				self::write_store( $store, $before );
			}
			return new \WP_Error( is_wp_error( $written ) ? $written->get_error_code() : 'verify_failed' );
		}

		$next                         = $settings;
		$next['customcss']            = $css;
		$state['mode']                = CustomCss::MODE_MIGRATED;
		$state['reverted_at']         = null;
		$state['migration']           = [
			'store'         => $store,
			'stylesheet'    => get_stylesheet(),
			'fence_id'      => $id,
			'content_hash'  => hash( 'sha256', CustomCss::normalize_line_endings( $css ) ),
			'migrated_at'   => time(),
			'migrated_by'   => $user_id,
			'previous_hash' => hash( 'sha256', $before ),
		];
		$next[ CustomCss::STATE_KEY ] = $state;
		if ( ! CustomCss::persist_state( $next ) ) {
			if ( ! $adopting ) {
				self::write_store( $store, $before );
			}
			return new \WP_Error( 'state_failed' );
		}
		return $next;
	}

	/**
	 * Takes the migrated CSS back out of Additional CSS and returns it to the plugin, which prints it again.
	 *
	 * Only possible while the fenced block can still be found exactly once; otherwise nothing is changed.
	 *
	 * @param array $settings Saved settings.
	 * @param int   $user_id  Current user; a write with no user is refused.
	 * @return array|\WP_Error Updated settings on success, otherwise an error whose code names the reason.
	 */
	public static function revert( array $settings, int $user_id ) {
		if ( $user_id <= 0 || ! CustomCss::current_user_can_edit() ) {
			return new \WP_Error( 'capability' );
		}
		$state     = CustomCss::resolve_state( $settings );
		$migration = $state['migration'];
		if ( CustomCss::MODE_MIGRATED !== $state['mode'] || ! is_array( $migration ) ) {
			return new \WP_Error( 'wrong_state' );
		}
		$status = self::status( $settings );
		if ( self::INTEGRITY_ORPHANED === $status['integrity'] || self::INTEGRITY_DAMAGED === $status['integrity'] ) {
			return new \WP_Error( 'no_fence' );
		}

		$store   = (string) $migration['store'];
		$content = self::read_store( $store, true );
		$block   = null === $content ? null : self::find_block( $content );
		if ( null === $block || $block['id'] !== ( $migration['fence_id'] ?? null ) ) {
			return new \WP_Error( 'no_fence' );
		}
		$remaining = self::remove_block( $content, $block );

		$next                         = $settings;
		$next['customcss']            = $block['inner'];
		$next[ CustomCss::STATE_KEY ] = [
			'version'       => CustomCss::STATE_VERSION,
			'mode'          => CustomCss::MODE_LEGACY,
			// Legacy mode only: a reverted site prints its saved CSS and never links the old plugin-folder file.
			'legacy_source' => CustomCss::LEGACY_SOURCE_PLUGIN_SETTING,
			'migration'     => null,
			'reverted_at'   => time(),
		];
		// The plugin prints the CSS again first, so a failure after this point can only print it twice, never lose it.
		if ( ! CustomCss::persist_state( $next ) ) {
			return new \WP_Error( 'state_failed' );
		}

		$written = self::write_store( $store, $remaining );
		$after   = is_wp_error( $written ) ? null : self::read_store( $store, true );
		if ( null === $after || ! self::same_text( $after, $remaining ) || null !== self::find_block( $after ) ) {
			self::write_store( $store, $content );
			CustomCss::persist_state( $settings );
			return new \WP_Error( 'verify_failed' );
		}
		return $next;
	}

	/**
	 * Gives up the CSS the plugin still holds, for a site that does not want to migrate it.
	 *
	 * Nothing is written to Additional CSS. The saved copy is deleted and the plugin stops printing it, so this needs
	 * only the ability to manage the plugin: a user who may not edit Additional CSS can still stop unwanted CSS applying.
	 *
	 * @param array $settings Saved settings.
	 * @param int   $user_id  Current user; a change with no user is refused.
	 * @return array|\WP_Error Updated settings on success, otherwise an error whose code names the reason.
	 */
	public static function discard( array $settings, int $user_id ) {
		if ( $user_id <= 0 || ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden' );
		}
		// The same states that show the editor: legacy CSS, an orphaned migration, or a migrated block that went missing.
		if ( ! self::needs_editor( $settings ) ) {
			return new \WP_Error( 'wrong_state' );
		}

		$next                         = $settings;
		$next['customcss']            = '';
		$next[ CustomCss::STATE_KEY ] = [
			'version'       => CustomCss::STATE_VERSION,
			'mode'          => CustomCss::MODE_DISABLED,
			'legacy_source' => CustomCss::LEGACY_SOURCE_PLUGIN_SETTING,
			'migration'     => null,
			'reverted_at'   => null,
		];
		if ( ! CustomCss::persist_state( $next ) ) {
			return new \WP_Error( 'state_failed' );
		}
		return $next;
	}
}

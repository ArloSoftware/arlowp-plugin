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
	 * @return array{mode: string, integrity: string|null, store: string|null, migrated_at: int|null, adjusted: int, destination_url: string, destination_label: string, back_to_before: bool}
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
			'adjusted'          => is_array( $migration ) && isset( $migration['adjusted'] ) && is_int( $migration['adjusted'] ) && $migration['adjusted'] > 0 ? $migration['adjusted'] : 0,
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
	 * @param string      $css     CSS about to be migrated.
	 * @param string|null $store   Destination store; defaults to the active theme's.
	 * @param array|null  $rewrite Result of CustomCssMarkupRewriter::rewrite() for this CSS, when the caller already has it; otherwise it is worked out here.
	 * @return array Result from CustomCssCompatibility::analyze() with site findings added.
	 */
	public static function preflight( string $css, ?string $store = null, ?array $rewrite = null ): array {
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

		// Core's block editor refuses Additional CSS holding a "<" followed by a letter, so the migration rewrites each one it safely can and
		// blocks on any it cannot. The block is compared with the rewritten text, because that is what a migration writes.
		$rewrite = $rewrite ?? CustomCssMarkupRewriter::rewrite( $css );

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
		$inspection = null === $existing ? [ 'state' => 'none' ] : self::inspect_existing_block( $existing, $rewrite['css'] );
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

		foreach ( self::markup_findings( $rewrite ) as $finding ) {
			$extra[] = $finding;
		}

		// The rewriter could not read all of the CSS, or it has too many brackets to adjust, so any it holds would be written as they are and leave the editor error.
		// The scan already blocks CSS it cannot read, so this only adds the position of the first bracket when nothing else has blocked.
		if ( ! $rewrite['reliable'] && null !== $rewrite['unreadable'] && empty( $analysis['blocked'] ) ) {
			$extra[] = [
				'code'    => 'markup-unreadable',
				'line'    => $rewrite['unreadable']['line'],
				'column'  => $rewrite['unreadable']['column'],
				'level'   => 'blocking',
				'message' => sprintf(
					/* translators: %s: a short piece of the CSS around the problem. */
					__( 'WordPress\'s editor rejects a "<" followed by a letter. Arlo could not adjust this one, because it could not read all of this CSS (look for an unclosed quote or bracket, or a malformed url()) or because the CSS has too many of them. Near: %s', 'arlo-training-and-event-management-system' ),
					$rewrite['unreadable']['snippet']
				),
			];
		}

		// Adjusting makes CSS longer. CSS that is within the check's bound but would not be once adjusted would migrate, then be refused when it is checked again after a revert.
		if ( $rewrite['reliable'] && strlen( $rewrite['css'] ) > CustomCssCompatibility::MAX_BYTES && strlen( $css ) <= CustomCssCompatibility::MAX_BYTES ) {
			$extra[] = self::finding(
				'markup-too-large',
				'blocking',
				sprintf(
					/* translators: %s: the largest size that can be checked, for example "2 MB". */
					__( 'Once Arlo has adjusted its angle brackets this CSS would be larger than %s, the most that can be checked and migrated. Shorten the CSS, then check again.', 'arlo-training-and-event-management-system' ),
					size_format( CustomCssCompatibility::MAX_BYTES )
				)
			);
		}

		foreach ( $extra as $finding ) {
			$analysis['findings'][] = $finding;
			if ( 'blocking' === $finding['level'] ) {
				$analysis['blocked'] = true;
			}
			// Adjustments are listed, so the CSS is not reported as having no issues, but they never block.
			if ( 'clear' === $analysis['status'] && ( 'blocking' === $finding['level'] || ! empty( $finding['fixable'] ) ) ) {
				$analysis['status'] = 'warning';
			}
		}
		return $analysis;
	}

	/**
	 * Describes the angle brackets a migration will rewrite, one finding per kind, and any it cannot.
	 *
	 * @param array $rewrite Result of CustomCssMarkupRewriter::rewrite().
	 * @return array Findings: the rewrites are 'warning' findings flagged 'fixable' with a 'count'; each bracket that cannot be rewritten is 'blocking'.
	 */
	private static function markup_findings( array $rewrite ): array {
		$kinds  = [
			'url'     => 'markup-url',
			'string'  => 'markup-escape',
			'escape'  => 'markup-escape',
			'range'   => 'markup-range',
			'comment' => 'markup-comment',
		];
		$groups = [];
		foreach ( $rewrite['changes'] as $change ) {
			$code = $kinds[ $change['kind'] ];
			if ( ! isset( $groups[ $code ] ) ) {
				$groups[ $code ] = [
					'count' => 0,
					'first' => $change,
				];
			}
			++$groups[ $code ]['count'];
		}

		$findings = [];
		foreach ( [ 'markup-url', 'markup-escape', 'markup-range', 'markup-comment' ] as $code ) {
			if ( ! isset( $groups[ $code ] ) ) {
				continue;
			}
			$count      = $groups[ $code ]['count'];
			$texts      = self::markup_texts( $code, $count );
			$findings[] = [
				'code'    => $code,
				'line'    => $groups[ $code ]['first']['line'],
				'column'  => $groups[ $code ]['first']['column'],
				'level'   => 'warning',
				'message' => $texts[0],
				'fix'     => $texts[1],
				'fixable' => true,
				'count'   => $count,
			];
		}

		foreach ( $rewrite['residue'] as $hit ) {
			$findings[] = [
				'code'    => 'markup-unfixable',
				'line'    => $hit['line'],
				'column'  => $hit['column'],
				'level'   => 'blocking',
				'message' => sprintf(
					/* translators: %s: a short piece of the CSS around the problem. */
					__( 'WordPress\'s editor rejects a "<" followed by a letter, and this one cannot be rewritten without changing what the CSS means. Remove it, or write the CSS another way. Near: %s', 'arlo-training-and-event-management-system' ),
					$hit['snippet']
				),
			];
		}
		$more = $rewrite['residue_count'] - count( $rewrite['residue'] );
		if ( $more > 0 ) {
			// The ones that are not described are still located, by line, so none is lost.
			$others  = array_values( array_diff( $rewrite['residue_lines'], array_column( $rewrite['residue'], 'line' ) ) );
			$message = sprintf(
				/* translators: %d: number of further places that are not listed. */
				_n( '%d more place like this is not listed.', '%d more places like this are not listed.', $more, 'arlo-training-and-event-management-system' ),
				$more
			);
			if ( [] !== $others ) {
				$shown    = array_slice( $others, 0, 20 );
				$message .= ' ' . sprintf(
					/* translators: %s: line numbers, separated by commas. */
					__( 'They are on lines %s.', 'arlo-training-and-event-management-system' ),
					implode( ', ', $shown ) . ( count( $others ) > count( $shown ) ? ', …' : '' )
				);
			}
			$findings[] = self::finding(
				'markup-unfixable-more',
				'blocking',
				$message . ' ' . __( 'Fix the ones above, then check again.', 'arlo-training-and-event-management-system' )
			);
		}
		return $findings;
	}

	/**
	 * What is wrong, and what the migration will do about it, for a group of brackets that will be rewritten.
	 *
	 * They are separate so the settings screen can show the fix after an arrow.
	 *
	 * @param string $code  Finding code.
	 * @param int    $count How many places.
	 * @return array{0: string, 1: string} The problem, and the fix.
	 */
	private static function markup_texts( string $code, int $count ): array {
		switch ( $code ) {
			case 'markup-url':
				return [
					sprintf(
						/* translators: %d: number of addresses. */
						_n( '%d address in a url() contains angle brackets, which WordPress\'s editor rejects', '%d addresses in url() contain angle brackets, which WordPress\'s editor rejects', $count, 'arlo-training-and-event-management-system' ),
						$count
					),
					sprintf(
						/* translators: 1: the text %3C, 2: the text %3E. */
						__( 'Arlo will save them as %1$s and %2$s, which browsers read as the same address.', 'arlo-training-and-event-management-system' ),
						'%3C',
						'%3E'
					),
				];
			case 'markup-escape':
				return [
					sprintf(
						/* translators: %d: number of places. */
						_n( '%d quoted string or selector contains a "<" that WordPress\'s editor rejects', '%d quoted strings or selectors contain a "<" that WordPress\'s editor rejects', $count, 'arlo-training-and-event-management-system' ),
						$count
					),
					sprintf(
						/* translators: %s: the CSS escape for a less-than sign. */
						_n( 'Arlo will save it as the CSS escape %s, which means the same character.', 'Arlo will save each as the CSS escape %s, which means the same character.', $count, 'arlo-training-and-event-management-system' ),
						'\\00003C'
					),
				];
			case 'markup-range':
				return [
					sprintf(
						/* translators: %d: number of places. */
						_n( '%d media or container query compares sizes with "<" and no space after it, which WordPress\'s editor rejects', '%d media or container queries compare sizes with "<" and no space after it, which WordPress\'s editor rejects', $count, 'arlo-training-and-event-management-system' ),
						$count
					),
					_n( 'Arlo will add a space after the "<", for example (width< 600px), which means the same.', 'Arlo will add a space after each "<", for example (width< 600px), which means the same.', $count, 'arlo-training-and-event-management-system' ),
				];
			default:
				return [
					sprintf(
						/* translators: %d: number of comments. */
						_n( '%d comment contains angle brackets, such as an email address or a tag name, which WordPress\'s editor rejects', '%d comments contain angle brackets, such as an email address or a tag name, which WordPress\'s editor rejects', $count, 'arlo-training-and-event-management-system' ),
						$count
					),
					_n( 'Arlo will save them as square brackets, and encode any commented-out address, so the comment still reads the same.', 'Arlo will save them as square brackets, and encode any commented-out address, so each comment still reads the same.', $count, 'arlo-training-and-event-management-system' ),
				];
		}
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
		// Worked out once and handed to the check, so what the check reports is exactly what is written below.
		$rewrite  = CustomCssMarkupRewriter::rewrite( $css );
		$analysis = self::preflight( $css, $store, $rewrite );
		if ( ! empty( $analysis['blocked'] ) ) {
			return new \WP_Error( 'blocked', '', $analysis );
		}

		// Everything from here on, including the saved copy and the integrity hash, is the CSS as it is written: with each angle bracket
		// the editor would reject rewritten. The check has already blocked any that cannot be, and this is the last guard whatever the reason
		// one is left: nothing the editor would reject is ever written.
		if ( CustomCssMarkupRewriter::contains_markup( $rewrite['css'] ) ) {
			return new \WP_Error( 'blocked', '', $analysis );
		}
		$css = $rewrite['css'];

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
			'adjusted'      => count( $rewrite['changes'] ),
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

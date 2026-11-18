<?php
/**
 * The group booking page: its address, its WordPress page and its block.
 *
 * @package Livia
 */

defined( 'ABSPATH' ) || exit;

/**
 * The WordPress page that carries the Livia group booking page.
 */
final class Livia_Group_Page {

	/**
	 * The pages the plugin unpublished when it was deactivated, kept while it is inactive.
	 */
	const REPUBLISH = 'livia_republish';

	/**
	 * When the plugin last added a page to that list.
	 */
	const REPUBLISH_AT = 'livia_republish_at';

	const BLOCK = 'livia/group-page';

	/**
	 * Hooks the block and its assets.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_booking_tools' ), PHP_INT_MAX );
		// Last, after the theme builders of page builders, which choose their own templates.
		add_filter( 'template_include', array( __CLASS__, 'template' ), PHP_INT_MAX - 1 );
		add_action( 'admin_init', array( __CLASS__, 'resume' ) );
	}

	/**
	 * Republishes the pages a deactivation left as drafts while the plugin still runs here: a network deactivation
	 * stopped before its end (PHP's time limit) leaves the plugin active. Each site does it at its own admin load, once
	 * the list has been left alone for ten minutes, so that it never races a deactivation still running. Only for who
	 * may open the plugin's settings, the site's administrator included on a network: an anonymous admin-ajax.php
	 * request also runs admin_init.
	 */
	public static function resume() {
		if ( current_user_can( Livia_Admin::CAPABILITY ) && get_option( self::REPUBLISH ) && time() - (int) get_option( self::REPUBLISH_AT, 0 ) >= 10 * MINUTE_IN_SECONDS ) {
			self::republish();
		}
	}

	/**
	 * Registers the block the page carries, rendered on the server.
	 */
	public static function register_block() {
		register_block_type( LIVIA_DIR . 'build/group-page', array( 'render_callback' => array( __CLASS__, 'render' ) ) );
		wp_set_script_translations( 'livia-group-page-editor-script', 'livia', LIVIA_DIR . 'languages' );
	}

	/**
	 * A restaurant's group page, if it exists.
	 *
	 * @param string $id Restaurant id.
	 * @return WP_Post|null
	 */
	public static function page( $id ) {
		$restaurant = Livia_Service::restaurant( $id );
		$page       = $restaurant && $restaurant['page_id'] ? get_post( (int) $restaurant['page_id'] ) : null;
		return $page instanceof WP_Post && 'page' === $page->post_type ? $page : null;
	}

	/**
	 * A restaurant's group page while it is published.
	 *
	 * @param string $id Restaurant id.
	 * @return WP_Post|null
	 */
	public static function published( $id ) {
		$page = self::page( $id );
		return $page && 'publish' === $page->post_status ? $page : null;
	}

	/**
	 * The group pages of the connected restaurants, by restaurant id.
	 *
	 * @return int[]
	 */
	public static function page_ids() {
		$ids = array();
		foreach ( array_keys( Livia_Service::restaurants() ) as $id ) {
			$page = self::page( $id );
			if ( $page ) {
				$ids[ $id ] = $page->ID;
			}
		}
		return $ids;
	}

	/**
	 * The restaurant a page is the group page of.
	 *
	 * @param int $post_id Page id.
	 * @return string|null Restaurant id.
	 */
	public static function restaurant_of( $post_id ) {
		$id = array_search( (int) $post_id, self::page_ids(), true );
		return false === $id ? null : (string) $id;
	}

	/**
	 * The restaurant whose group page is shown.
	 *
	 * @return string|null Restaurant id.
	 */
	public static function current() {
		foreach ( self::page_ids() as $id => $page_id ) {
			if ( is_page( $page_id ) ) {
				return (string) $id;
			}
		}
		return null;
	}

	/**
	 * The restaurant whose group page is shown in landing mode.
	 *
	 * @return string|null Restaurant id.
	 */
	public static function landing() {
		$id = self::current();
		return null !== $id && 'landing' === Livia_Service::restaurant( $id )['page_layout'] ? $id : null;
	}

	/**
	 * Serves a group page in landing mode with the plugin's own template: the site's head and footer hooks, without the
	 * theme's header and footer. Its embed address keeps WordPress's embed template.
	 *
	 * @param string $template Template WordPress chose.
	 * @return string
	 */
	public static function template( $template ) {
		return ! is_embed() && null !== self::landing() ? LIVIA_DIR . 'includes/views/landing.php' : $template;
	}

	/**
	 * Takes the scripts of the booking tools WordPress was asked to load off a group page in landing mode, their inline
	 * scripts with them.
	 */
	public static function dequeue_booking_tools() {
		if ( null === self::landing() ) {
			return;
		}
		$scripts = wp_scripts();
		foreach ( $scripts->queue as $handle ) {
			$src = isset( $scripts->registered[ $handle ] ) ? (string) $scripts->registered[ $handle ]->src : '';
			if ( Livia_Rules::is_booking_script( 'id="' . $handle . '-js" src="' . $src . '"' ) ) {
				wp_dequeue_script( $handle );
			}
		}
	}

	/**
	 * Prints what a hook of the landing template prints, the scripts of the booking tools left out.
	 *
	 * @param string $hook `wp_head`, `wp_body_open` or `wp_footer`.
	 */
	public static function print_without_booking_tools( $hook ) {
		// shortcut: a plugin that opens an output buffer in one hook and closes it in another loses its processing, as
		// the buffers close out of order; upgrade to one buffer around the whole template if a restaurant's site needs it.
		ob_start();
		do_action( $hook ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- WordPress's own hooks.
		echo Livia_Rules::without_booking_tools( ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The site's own output, scripts removed.
	}

	/**
	 * Loads the page's styles in the head and its scripts, on a group page only.
	 */
	public static function enqueue() {
		$id   = self::current();
		$page = null !== $id ? Livia_Service::group_page( $id ) : null;
		if ( ! $page ) {
			return;
		}
		foreach ( $page['styles'] as $url ) {
			wp_enqueue_style( 'livia-' . md5( $url ), $url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Livia versions its addresses.
		}
		foreach ( $page['scripts'] as $url ) {
			Livia_Widgets::enqueue_script( $url );
		}
	}

	/**
	 * Renders the block: the page Livia rendered for the restaurant of this page, else a short notice.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block content, none.
	 * @param WP_Block $block      Block, which carries the page it is on.
	 * @return string
	 */
	public static function render( $attributes, $content = '', $block = null ) {
		unset( $attributes, $content );
		$post_id = $block instanceof WP_Block && isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
		$id      = self::restaurant_of( $post_id );
		$page    = null !== $id ? Livia_Service::group_page( $id ) : null;
		if ( ! $page ) {
			// A landing page has no theme header to lead the visitor back to the site.
			$back = null !== $id && 'landing' === Livia_Service::restaurant( $id )['page_layout']
				? sprintf( '<p><a href="%s">%s</a></p>', esc_url( home_url( '/' ) ), esc_html__( 'Back to the site', 'livia' ) )
				: '';
			return sprintf(
				'<div %s><p>%s</p>%s</div>',
				get_block_wrapper_attributes(),
				esc_html__( 'This page is temporarily unavailable. Please try again a little later.', 'livia' ),
				$back
			);
		}
		// The fragment comes from the Livia service over the plugin's own connection; kses would strip its widget. Its
		// text is the restaurant's, written in Livia: `the_content` must run none of its brackets as a shortcode.
		// The script hands `?couverts=N` to the conversation before its bundle, loaded later in the footer, starts it;
		// it reads the address in the browser, so the page stays the same for every address and its cache holds. It
		// also looks in the open shadow roots of the fragment, where an isolated page puts its conversation.
		// wptexturize on the_content reads a `<` as a tag start and escapes the `&` after it: the script has no `<`; and
		// do_shortcode runs any `[name]` a theme or plugin declares as a shortcode: it has no bracket either.
		// `liviaGuests` and the attributes keep it out of the cache and optimisation tools, see Livia_Cache.
		return sprintf(
			'<div %s>%s%s</div>',
			get_block_wrapper_attributes(),
			Livia_Rules::without_shortcodes( $page['html'] ),
			wp_get_inline_script_tag( '(function(){var liviaGuests=Number(new URLSearchParams(location.search).get("couverts"));var root=document.currentScript.parentNode;if(liviaGuests>=1&&999>=liviaGuests&&liviaGuests%1===0){Array.prototype.concat.call(root,Array.prototype.map.call(root.querySelectorAll("*"),function(el){return el.shadowRoot;}).filter(Boolean)).forEach(function(part){part.querySelectorAll("livia-chat").forEach(function(chat){chat.setAttribute("guests",String(liviaGuests));});});}}());', Livia_Cache::ATTRIBUTES )
		);
	}

	/**
	 * Why an address cannot take a restaurant's group page, if it cannot.
	 *
	 * @param string $slug Address, one segment, already sanitized.
	 * @param string $id   Restaurant id.
	 * @return array|null `message`, and `post` when a content the webmaster may move to the trash holds the address;
	 *                    null when the address is free.
	 */
	public static function check_address( $slug, $id ) {
		global $wpdb, $wp_rewrite;

		if ( '' === get_option( 'permalink_structure' ) ) {
			return array(
				'message'    => __( 'Your permalinks are set to “Plain”: WordPress cannot give the page a readable address. Choose another setting in Settings > Permalinks.', 'livia' ),
				'permalinks' => true,
			);
		}
		if ( '' === $slug ) {
			return array( 'message' => __( 'Enter an address.', 'livia' ) );
		}

		$own      = self::page( $id );
		$own      = $own ? $own->ID : 0;
		$replaced = array_filter( array_map( 'intval', wp_list_pluck( Livia_Service::restaurants(), 'replaced_id' ) ) );
		$skip     = array_merge( array( $own ), $replaced );
		$types    = array_values( get_post_types( array( 'public' => true ) ) );
		$in       = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- A live check of every status, which no cached API offers; $in holds placeholders only.
		$post_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type IN ($in) AND ID <> %d AND ( post_type <> 'page' OR post_parent = 0 ) ORDER BY post_status = 'publish' DESC, ID LIMIT 1",
				array_merge( array( $slug ), $types, array( $own ) )
			)
		);
		if ( ! $post_id ) {
			// A trashed content keeps its address under `_wp_desired_post_slug` and takes it back when restored.
			$post_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_desired_post_slug' WHERE m.meta_value = %s AND p.post_status = 'trash' AND p.post_type IN ($in) AND p.ID NOT IN (" . implode( ', ', array_fill( 0, count( $skip ), '%d' ) ) . ') LIMIT 1',
					array_merge( array( $slug ), $types, $skip )
				)
			);
		}
		// phpcs:enable
		$other = $post_id ? self::restaurant_of( $post_id ) : null;
		if ( null !== $other ) {
			return array(
				/* translators: %s: restaurant name (in French, already preceded by "de" and contracted). */
				'message' => sprintf( __( 'This address is already used by the group booking page of %s.', 'livia' ), Livia_Rules::of_name( Livia_Service::name( $other ), determine_locale() ) ),
			);
		}
		if ( $post_id ) {
			$post = get_post( $post_id );
			return array(
				'message' => self::taken_by_post_message( $post ),
				'post'    => $post,
			);
		}

		foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term ) {
				return array(
					'message' => sprintf(
						/* translators: %s: category or tag name. */
						'category' === $taxonomy ? __( 'This address is already used by the category “%s”.', 'livia' ) : __( 'This address is already used by the tag “%s”.', 'livia' ),
						$term->name
					),
				);
			}
		}
		// The site's root differs from WordPress's when WordPress lives in a subfolder.
		$roots = function_exists( 'get_home_path' ) ? array( ABSPATH, get_home_path() ) : array( ABSPATH );
		if ( array_filter( $roots, static fn( $root ) => file_exists( trailingslashit( $root ) . $slug ) ) ) {
			return array( 'message' => __( 'This address matches a file or a folder of your server.', 'livia' ) );
		}
		$bases = array( rest_get_url_prefix(), get_option( 'category_base' ), get_option( 'tag_base' ) );
		if ( Livia_Rules::is_reserved_slug( $slug, $bases ) || wp_unique_post_slug( $slug, $own, 'publish', 'page', 0 ) !== $slug ) {
			return array( 'message' => __( 'This address is reserved by WordPress.', 'livia' ) );
		}
		if ( Livia_Rules::rewrite_conflict( $slug, $wp_rewrite->wp_rewrite_rules() ) ) {
			return array( 'message' => __( 'This address is already used by WordPress or by another plugin.', 'livia' ) );
		}
		return null;
	}

	/**
	 * The message for an address held by a content.
	 *
	 * @param WP_Post $post Content.
	 * @return string
	 */
	private static function taken_by_post_message( $post ) {
		$title = '' !== $post->post_title ? $post->post_title : __( '(no title)', 'livia' );
		if ( 'attachment' === $post->post_type ) {
			/* translators: %s: media file title. */
			return sprintf( __( 'This address is already used by the media file “%s”.', 'livia' ), $title );
		}
		if ( 'page' === $post->post_type ) {
			$statuses = array(
				'publish' => _x( 'published', 'page status', 'livia' ),
				'future'  => _x( 'scheduled', 'page status', 'livia' ),
				'private' => _x( 'private', 'page status', 'livia' ),
				'trash'   => _x( 'in the trash', 'page status', 'livia' ),
			);
			/* translators: 1: page title, 2: page status. */
			$message = __( 'This address is already used by your page “%1$s” (%2$s).', 'livia' );
		} else {
			$statuses = array(
				'publish' => _x( 'published', 'post status', 'livia' ),
				'future'  => _x( 'scheduled', 'post status', 'livia' ),
				'private' => _x( 'private', 'post status', 'livia' ),
				'trash'   => _x( 'in the trash', 'post status', 'livia' ),
			);
			/* translators: 1: post title, 2: post status. */
			$message = 'post' === $post->post_type ? __( 'This address is already used by your post “%1$s” (%2$s).', 'livia' ) : __( 'This address is already used by the content “%1$s” (%2$s).', 'livia' );
		}
		$status = isset( $statuses[ $post->post_status ] ) ? $statuses[ $post->post_status ] : _x( 'draft', 'content status', 'livia' );
		return sprintf( $message, $title, $status );
	}

	/**
	 * Publishes a restaurant's group page at an address, creating it when needed.
	 *
	 * @param string $id    Restaurant id.
	 * @param string $slug  Address, checked free.
	 * @param string $title Page title.
	 * @return array{page: WP_Post|null, created: bool} The page, null when WordPress refused it.
	 */
	public static function publish( $id, $slug, $title ) {
		$page    = self::page( $id );
		$content = '<!-- wp:' . self::BLOCK . ' {"align":"full"} /-->';
		if ( $page && 'trash' === $page->post_status ) {
			wp_untrash_post( $page->ID );
		}
		$postarr = array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => $page && has_block( self::BLOCK, $page ) ? $page->post_content : $content,
		);
		if ( $page ) {
			$postarr['ID'] = $page->ID;
			$post_id       = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$post_id = wp_insert_post( wp_slash( $postarr ), true );
		}
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return array(
				'page'    => null,
				'created' => false,
			);
		}
		Livia_Service::save_restaurant( $id, array( 'page_id' => (int) $post_id ) );
		return array(
			'page'    => get_post( $post_id ),
			'created' => ! $page,
		);
	}

	/**
	 * Takes a restaurant's group page off the site: a draft keeps its address for when it comes back.
	 *
	 * @param string $id Restaurant id.
	 * @return bool Whether the page was published.
	 */
	public static function unpublish( $id ) {
		$page = self::published( $id );
		if ( ! $page ) {
			return false;
		}
		wp_update_post(
			array(
				'ID'          => $page->ID,
				'post_status' => 'draft',
			)
		);
		return true;
	}

	/**
	 * Runs a callback on the current site, or on every site of the network for a network-wide (de)activation, a hundred
	 * site ids at a time so that a large network is never loaded at once.
	 *
	 * @param callable $callback     What to do on a site.
	 * @param bool     $network_wide Whether the plugin is (de)activated for the whole network.
	 */
	private static function on_each_site( $callback, $network_wide ) {
		if ( ! $network_wide || ! is_multisite() ) {
			$callback();
			return;
		}
		$offset = 0;
		do {
			// The sites of this network only: another network keeps its own activation.
			$sites = get_sites(
				array(
					'fields'     => 'ids',
					'network_id' => get_current_network_id(),
					'number'     => 100,
					'offset'     => $offset,
				)
			);
			foreach ( $sites as $site ) {
				switch_to_blog( $site );
				$callback();
				restore_current_blog();
			}
			$offset += 100;
			$more    = 100 === count( $sites );
		} while ( $more );
	}

	/**
	 * Republishes the pages the plugin unpublished when it was deactivated, on every site of the network when it is
	 * activated for the whole network.
	 *
	 * @param bool $network_wide Whether the plugin is activated for the whole network.
	 */
	public static function on_activation( $network_wide = false ) {
		self::on_each_site( array( __CLASS__, 'republish' ), $network_wide );
	}

	/**
	 * Unpublishes the pages, which show nothing without the plugin, and removes the menu links it added, on every site
	 * of the network when it is deactivated for the whole network.
	 *
	 * @param bool $network_wide Whether the plugin is deactivated for the whole network.
	 */
	public static function on_deactivation( $network_wide = false ) {
		self::on_each_site(
			static function () use ( $network_wide ) {
				// A site that also activated the plugin on its own keeps it running, and its pages and links with it.
				if ( ! $network_wide || ! in_array( LIVIA_BASENAME, (array) get_option( 'active_plugins', array() ), true ) ) {
					self::unpublish_all();
					Livia_Experience::remove_from_menus();
				}
			},
			$network_wide
		);
	}

	/**
	 * Republishes the site's pages the plugin unpublished when it was deactivated.
	 */
	public static function republish() {
		$republish = (array) get_option( self::REPUBLISH, array() );
		foreach ( self::page_ids() as $page_id ) {
			if ( in_array( $page_id, array_map( 'intval', $republish ), true ) && 'draft' === get_post_status( $page_id ) ) {
				wp_update_post(
					array(
						'ID'          => $page_id,
						'post_status' => 'publish',
					)
				);
			}
		}
		delete_option( self::REPUBLISH );
		delete_option( self::REPUBLISH_AT );
	}

	/**
	 * Unpublishes the site's pages and remembers them for the next activation.
	 */
	public static function unpublish_all() {
		// The list is saved page by page and keeps the pages of an interrupted run, which are drafts already.
		$unpublished = array_map( 'intval', (array) get_option( self::REPUBLISH, array() ) );
		// Every run over a site with a list dates it, pages already drafts included: resume() then waits for this run.
		if ( $unpublished ) {
			update_option( self::REPUBLISH_AT, time(), false );
		}
		foreach ( self::page_ids() as $id => $page_id ) {
			if ( self::unpublish( $id ) ) {
				$unpublished[] = $page_id;
				update_option( self::REPUBLISH_AT, time(), false );
				update_option( self::REPUBLISH, array_values( array_unique( $unpublished ) ), false );
			}
		}
	}
}

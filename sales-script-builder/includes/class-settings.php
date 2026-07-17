<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal settings page so the script-view page slug lives in exactly one
 * place instead of being hardcoded across class-access-control.php,
 * templates/favorites-dashboard.php, and wherever the shortcode is placed.
 *
 * Nested under the Products/Services menu for discoverability.
 */
class SSB_Settings {

	const OPTION_KEY = 'ssb_script_view_slug';
	const DEFAULT_SLUG = 'sales-scripts';

	const ENFORCE_OPTION_KEY = 'ssb_enforce_membership';

	const MANAGE_OPTION_KEY = 'ssb_manage_page_slug';
	const MANAGE_DEFAULT_SLUG = 'manage-scripts';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings_page(): void {
		add_submenu_page(
			'edit.php?post_type=ssb_product',
			__( 'Sales Script Builder Settings', 'sales-script-builder' ),
			__( 'Settings', 'sales-script-builder' ),
			'manage_options',
			'ssb-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'ssb_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_slug' ),
				'default'           => self::DEFAULT_SLUG,
			)
		);

		register_setting(
			'ssb_settings_group',
			self::ENFORCE_OPTION_KEY,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			'ssb_settings_group',
			self::MANAGE_OPTION_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_manage_slug' ),
				'default'           => self::MANAGE_DEFAULT_SLUG,
			)
		);

		add_settings_section(
			'ssb_settings_main',
			__( 'Page Configuration', 'sales-script-builder' ),
			'__return_false',
			'ssb-settings'
		);

		add_settings_field(
			self::OPTION_KEY,
			__( 'Script View Page Slug', 'sales-script-builder' ),
			array( $this, 'render_slug_field' ),
			'ssb-settings',
			'ssb_settings_main'
		);

		add_settings_field(
			self::MANAGE_OPTION_KEY,
			__( 'Manage Products Page Slug', 'sales-script-builder' ),
			array( $this, 'render_manage_slug_field' ),
			'ssb-settings',
			'ssb_settings_main'
		);

		add_settings_field(
			self::ENFORCE_OPTION_KEY,
			__( 'Membership Enforcement', 'sales-script-builder' ),
			array( $this, 'render_enforce_field' ),
			'ssb-settings',
			'ssb_settings_main'
		);
	}

	/**
	 * Strips leading/trailing slashes and sanitizes to a valid slug, so it's
	 * safe to drop straight into home_url( '/' . $slug . '/' ) elsewhere.
	 */
	public function sanitize_slug( $value ): string {
		// Deliberately untyped: register_setting() can hand this callback a null
		// (e.g. when the option row is missing), which a `string` type hint would
		// turn into a fatal TypeError under PHP 8.
		$value = trim( (string) $value, "/ \t\n\r\0\x0B" );
		$value = sanitize_title( $value );
		return $value ? $value : self::DEFAULT_SLUG;
	}

	public function render_slug_field(): void {
		$value = self::get_slug();
		?>
		<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		<p class="description">
			<?php
			printf(
				/* translators: %s: example page slug */
				esc_html__( 'The slug of the page where you\'ve placed the [ssb_script_builder] shortcode (e.g. %s, no leading/trailing slashes). This same value gates membership access and is used to build links from the "My Scripts" favorites dashboard.', 'sales-script-builder' ),
				'<code>sales-scripts</code>'
			);
			?>
		</p>
		<?php
		$page = get_page_by_path( $value );
		if ( $page && 'publish' === $page->post_status ) {
			printf(
				'<p class="description" style="color:#2271b1;">%s <a href="%s" target="_blank">%s</a></p>',
				esc_html__( 'Matched to page:', 'sales-script-builder' ),
				esc_url( get_permalink( $page ) ),
				esc_html( $page->post_title )
			);
		} else {
			printf(
				'<p class="description" style="color:#b32d2e;">%s</p>',
				esc_html__( 'No published page currently matches this slug -- create it or update the slug above.', 'sales-script-builder' )
			);
		}
	}

	public function sanitize_manage_slug( $value ): string {
		$value = trim( (string) $value, "/ \t\n\r\0\x0B" );
		$value = sanitize_title( $value );
		return $value ? $value : self::MANAGE_DEFAULT_SLUG;
	}

	public function render_manage_slug_field(): void {
		$value = self::get_manage_slug();
		?>
		<input type="text" name="<?php echo esc_attr( self::MANAGE_OPTION_KEY ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		<p class="description">
			<?php
			printf(
				/* translators: %s: example page slug */
				esc_html__( 'The slug of the page where you\'ve placed the [ssb_manage_products] shortcode (e.g. %s). This is where members create and edit their own products.', 'sales-script-builder' ),
				'<code>manage-scripts</code>'
			);
			?>
		</p>
		<?php
		$page = get_page_by_path( $value );
		if ( $page && 'publish' === $page->post_status ) {
			printf(
				'<p class="description" style="color:#2271b1;">%s <a href="%s" target="_blank">%s</a></p>',
				esc_html__( 'Matched to page:', 'sales-script-builder' ),
				esc_url( get_permalink( $page ) ),
				esc_html( $page->post_title )
			);
		} else {
			printf(
				'<p class="description" style="color:#b32d2e;">%s</p>',
				esc_html__( 'No published page currently matches this slug -- create it or update the slug above.', 'sales-script-builder' )
			);
		}
	}

	public function render_enforce_field(): void {
		$enforced = self::is_enforced();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::ENFORCE_OPTION_KEY ); ?>" value="1" <?php checked( $enforced ); ?> />
			<?php esc_html_e( 'Require an active MemberPress subscription to view scripts', 'sales-script-builder' ); ?>
		</label>
		<p class="description">
			<?php if ( $enforced ) : ?>
				<span style="color:#008a20;font-weight:600;"><?php esc_html_e( 'Enforcement is ON.', 'sales-script-builder' ); ?></span>
				<?php esc_html_e( 'Only users with an active MemberPress membership (or site admins) can view scripts.', 'sales-script-builder' ); ?>
			<?php else : ?>
				<span style="color:#b32d2e;font-weight:600;"><?php esc_html_e( 'Enforcement is OFF.', 'sales-script-builder' ); ?></span>
				<?php esc_html_e( 'Any logged-in user currently has access, regardless of membership status -- intended for testing before MemberPress goes live. Turn this on before opening the site to real members.', 'sales-script-builder' ); ?>
			<?php endif; ?>
		</p>
		<?php
	}

	public function render_settings_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sales Script Builder Settings', 'sales-script-builder' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'ssb_settings_group' );
				do_settings_sections( 'ssb-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Public read helper -- used by class-access-control.php and
	 * templates/favorites-dashboard.php instead of a hardcoded slug.
	 */
	public static function get_slug(): string {
		$slug = get_option( self::OPTION_KEY, self::DEFAULT_SLUG );
		return $slug ? $slug : self::DEFAULT_SLUG;
	}

	/**
	 * Public read helper for the front-end product manager page slug.
	 */
	public static function get_manage_slug(): string {
		$slug = get_option( self::MANAGE_OPTION_KEY, self::MANAGE_DEFAULT_SLUG );
		return $slug ? $slug : self::MANAGE_DEFAULT_SLUG;
	}

	/**
	 * Whether MemberPress membership should actually be checked. Defaults to
	 * false (off) so the plugin is testable before MemberPress is live on
	 * the site. Flip on from Products/Services > Settings once ready --
	 * this is a deliberate, visible switch rather than something inferred
	 * automatically from whether MemberPress happens to be active.
	 */
	public static function is_enforced(): bool {
		return (bool) get_option( self::ENFORCE_OPTION_KEY, false );
	}
}

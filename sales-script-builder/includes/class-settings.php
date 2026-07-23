<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page so every plugin page's slug lives in exactly one place
 * instead of being hardcoded across class-access-control.php, templates,
 * and wherever a shortcode is placed. Nested under the Products/Services
 * menu for discoverability.
 */
class SSB_Settings {

	const ENFORCE_OPTION_KEY = 'ssb_enforce_membership';

	/**
	 * Every front-end page this plugin has a shortcode for. Adding a new
	 * page later (front-end editor for another content type, etc.) means
	 * adding one row here -- registration, sanitization, and the settings
	 * field all follow from this single source of truth rather than being
	 * hand-written per page.
	 *
	 * key => [option key constant name, default slug, label, shortcode name]
	 */
	private static function page_configs(): array {
		return array(
			'app' => array(
				'option'    => 'ssb_app_page_slug',
				'default'   => 'sales-app',
				'label'     => __( 'App Page Slug (recommended entry point)', 'sales-script-builder' ),
				'shortcode' => '[ssb_app]',
			),
			'script_view' => array(
				'option'    => 'ssb_script_view_slug',
				'default'   => 'sales-scripts',
				'label'     => __( 'Script View Page Slug (standalone, legacy)', 'sales-script-builder' ),
				'shortcode' => '[ssb_script_builder]',
			),
			'hub' => array(
				'option'    => 'ssb_hub_page_slug',
				'default'   => 'sales-hub',
				'label'     => __( 'Hub / Landing Page Slug (standalone, legacy)', 'sales-script-builder' ),
				'shortcode' => '[ssb_hub]',
			),
			'manage_products' => array(
				'option'    => 'ssb_manage_page_slug',
				'default'   => 'manage-scripts',
				'label'     => __( 'Manage Products Page Slug (standalone, legacy)', 'sales-script-builder' ),
				'shortcode' => '[ssb_manage_products]',
			),
			'manage_competitors' => array(
				'option'    => 'ssb_manage_competitors_slug',
				'default'   => 'manage-competitors',
				'label'     => __( 'Manage Competitors Page Slug (standalone, legacy)', 'sales-script-builder' ),
				'shortcode' => '[ssb_manage_competitors]',
			),
			'manage_specials' => array(
				'option'    => 'ssb_manage_specials_slug',
				'default'   => 'manage-specials',
				'label'     => __( 'Manage Specials Page Slug (standalone, legacy)', 'sales-script-builder' ),
				'shortcode' => '[ssb_manage_specials]',
			),
		);
	}

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
		add_settings_section(
			'ssb_settings_pages',
			__( 'Page Configuration', 'sales-script-builder' ),
			'__return_false',
			'ssb-settings'
		);

		foreach ( self::page_configs() as $key => $config ) {
			register_setting(
				'ssb_settings_group',
				$config['option'],
				array(
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'sanitize_slug' ),
					'default'           => $config['default'],
				)
			);

			add_settings_field(
				$config['option'],
				$config['label'],
				array( $this, 'render_slug_field' ),
				'ssb-settings',
				'ssb_settings_pages',
				array( 'config' => $config )
			);
		}

		add_settings_section(
			'ssb_settings_access',
			__( 'Access', 'sales-script-builder' ),
			'__return_false',
			'ssb-settings'
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

		add_settings_field(
			self::ENFORCE_OPTION_KEY,
			__( 'Membership Enforcement', 'sales-script-builder' ),
			array( $this, 'render_enforce_field' ),
			'ssb-settings',
			'ssb_settings_access'
		);
	}

	/**
	 * Strips leading/trailing slashes and sanitizes to a valid slug, so it's
	 * safe to drop straight into home_url( '/' . $slug . '/' ) elsewhere.
	 * Deliberately untyped input: register_setting() can hand this a null
	 * (e.g. when the option row is missing), which a `string` type hint would
	 * turn into a fatal TypeError under PHP 8.
	 */
	public function sanitize_slug( $value ): string {
		$value = trim( (string) $value, "/ \t\n\r\0\x0B" );
		return sanitize_title( $value ); // Empty is fine here; get_*_slug() below falls back to the default.
	}

	public function render_slug_field( array $args ): void {
		$config = $args['config'];
		$value  = get_option( $config['option'], $config['default'] );
		$value  = $value ? $value : $config['default'];
		?>
		<input type="text" name="<?php echo esc_attr( $config['option'] ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		<p class="description">
			<?php
			printf(
				/* translators: 1: shortcode name, 2: example page slug */
				esc_html__( 'The slug of the page where you\'ve placed the %1$s shortcode (e.g. %2$s, no leading/trailing slashes).', 'sales-script-builder' ),
				'<code>' . esc_html( $config['shortcode'] ) . '</code>',
				'<code>' . esc_html( $config['default'] ) . '</code>'
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
			<?php esc_html_e( 'Require an active MemberPress subscription to access any Sales Script Builder page', 'sales-script-builder' ); ?>
		</label>
		<p class="description">
			<?php if ( $enforced ) : ?>
				<span style="color:#008a20;font-weight:600;"><?php esc_html_e( 'Enforcement is ON.', 'sales-script-builder' ); ?></span>
				<?php esc_html_e( 'Only users with an active MemberPress membership (or site admins) can access the hub, script view, or any manage page.', 'sales-script-builder' ); ?>
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

	/* ---------------------------------------------------------------
	 * PUBLIC READ HELPERS
	 * ------------------------------------------------------------- */

	private static function get_page_slug( string $key ): string {
		$configs = self::page_configs();
		$config  = $configs[ $key ];
		$slug    = get_option( $config['option'], $config['default'] );
		return $slug ? $slug : $config['default'];
	}

	public static function get_app_slug(): string {
		return self::get_page_slug( 'app' );
	}

	public static function get_slug(): string {
		return self::get_page_slug( 'script_view' );
	}

	public static function get_hub_slug(): string {
		return self::get_page_slug( 'hub' );
	}

	public static function get_manage_slug(): string {
		return self::get_page_slug( 'manage_products' );
	}

	public static function get_manage_competitors_slug(): string {
		return self::get_page_slug( 'manage_competitors' );
	}

	public static function get_manage_specials_slug(): string {
		return self::get_page_slug( 'manage_specials' );
	}

	/**
	 * Every page slug the plugin protects behind membership enforcement --
	 * used by SSB_Access_Control so a new page added here is automatically
	 * gated without having to also update the access-control file.
	 */
	public static function get_all_protected_slugs(): array {
		$slugs = array();
		foreach ( self::page_configs() as $key => $config ) {
			$slugs[] = self::get_page_slug( $key );
		}
		return $slugs;
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

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
	}

	/**
	 * Strips leading/trailing slashes and sanitizes to a valid slug, so it's
	 * safe to drop straight into home_url( '/' . $slug . '/' ) elsewhere.
	 */
	public function sanitize_slug( string $value ): string {
		$value = trim( $value, "/ \t\n\r\0\x0B" );
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
		if ( $page ) {
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
}

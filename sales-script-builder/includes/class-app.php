<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single-page-app shell. [ssb_app] replaces the five separate shortcodes
 * ([ssb_hub], [ssb_script_builder], [ssb_manage_products],
 * [ssb_manage_competitors], [ssb_manage_specials]) with one tabbed
 * interface on one page. This is the intended front door going forward --
 * see the README's migration note. The five old shortcode classes still
 * exist and still render, but their Edit/Add New/Delete buttons no longer
 * respond (the shared list/form templates were converted to
 * data-attribute buttons wired up by assets/js/app.js, which only
 * initializes inside this container). Recommend unpublishing/redirecting
 * the five old pages once [ssb_app] is live.
 *
 * All five panels are rendered server-side on initial load and simply
 * shown/hidden client-side (assets/js/app.js) rather than routed -- this is
 * what makes "remember where I was" free for the manage tabs (nothing is
 * ever unmounted) and "always reset" achievable for Call Script (it's just
 * a reset-on-activate rule in JS, see app.js).
 */
class SSB_App {

	public function __construct() {
		add_shortcode( 'ssb_app', array( $this, 'render_app' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	public function register_assets(): void {
		wp_register_style( 'ssb-app', SSB_PLUGIN_URL . 'assets/css/app.css', array(), SSB_VERSION );
		wp_register_script( 'ssb-app', SSB_PLUGIN_URL . 'assets/js/app.js', array(), SSB_VERSION, true );
		wp_register_script( 'ssb-glance', SSB_PLUGIN_URL . 'assets/js/glance.js', array(), SSB_VERSION, true );
	}

	public function render_app(): string {
		if ( ! SSB_Access_Control::user_has_access() ) {
			return '<p>' . esc_html__( 'An active membership is required to access this page.', 'sales-script-builder' ) . '</p>';
		}

		// Everything the manage tabs and Call Script need -- same handles the
		// standalone pages already used, so nothing is duplicated.
		wp_enqueue_style( 'ssb-admin' );
		wp_enqueue_style( 'ssb-manage' );
		wp_enqueue_style( 'ssb-frontend' );
		wp_enqueue_style( 'ssb-app' );
		wp_enqueue_script( 'ssb-admin-repeater' );
		wp_enqueue_script( 'ssb-copy-protect' );
		wp_enqueue_script( 'ssb-ga4-events' );
		wp_enqueue_script( 'ssb-favorites' );
		wp_enqueue_script( 'ssb-objection-buttons' );
		wp_enqueue_script( 'ssb-discovery' );
		wp_enqueue_script( 'ssb-glance' );
		wp_enqueue_script( 'ssb-app' );

		wp_localize_script(
			'ssb-app',
			'ssbApp',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ssb_app_nonce' ),
			)
		);

		ob_start();
		?>
		<div class="ssb-app" id="ssb-app">

			<nav class="ssb-app-tabs" role="tablist">
				<button type="button" class="ssb-app-tab is-active" data-tab="call-script" role="tab"><?php esc_html_e( 'Call Script', 'sales-script-builder' ); ?></button>
				<button type="button" class="ssb-app-tab" data-tab="add-products" role="tab"><?php esc_html_e( 'Add Products', 'sales-script-builder' ); ?></button>
				<button type="button" class="ssb-app-tab" data-tab="add-competitors" role="tab"><?php esc_html_e( 'Add Competitors', 'sales-script-builder' ); ?></button>
				<button type="button" class="ssb-app-tab" data-tab="specials" role="tab"><?php esc_html_e( 'Specials', 'sales-script-builder' ); ?></button>
				<button type="button" class="ssb-app-tab" data-tab="glance" role="tab"><?php esc_html_e( 'Competitors At A Glance', 'sales-script-builder' ); ?></button>
			</nav>

			<div class="ssb-app-panel is-active" data-panel="call-script">
				<?php include SSB_PLUGIN_DIR . 'templates/app-call-script.php'; ?>
			</div>

			<div class="ssb-app-panel" data-panel="add-products">
				<?php echo SSB_Frontend_Editor::render_list_fragment(); ?>
			</div>

			<div class="ssb-app-panel" data-panel="add-competitors">
				<?php echo SSB_Frontend_Competitors::render_list_fragment(); ?>
			</div>

			<div class="ssb-app-panel" data-panel="specials">
				<?php echo SSB_Frontend_Specials::render_list_fragment(); ?>
			</div>

			<div class="ssb-app-panel" data-panel="glance">
				<?php include SSB_PLUGIN_DIR . 'templates/app-glance.php'; ?>
			</div>

		</div>
		<?php
		return ob_get_clean();
	}
}

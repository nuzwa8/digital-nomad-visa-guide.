<?php
/**
 * Plugin Name: SSM Digital Nomad Guide
 * Description: Provides comprehensive, up-to-date information and step-by-step guides for Digital Nomad Visas across 40+ countries.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: ssm-dng
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access not allowed
}

// --------------------------------------------------------------------------
/** Part 1 — Plugin Setup, Assets, Activation & Constants */
// --------------------------------------------------------------------------

define( 'SSM_DNG_VERSION', '1.0.0' );
define( 'SSM_DNG_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSM_DNG_URL', plugin_dir_url( __FILE__ ) );

/**
 * Activation function (No DB tables needed yet, but good practice).
 *
 * @return void
 */
function ssm_dng_activate() {
    // Check if roles/capabilities need to be added in the future.
    // For now, we rely on 'manage_options'.
    if ( ! get_option( 'ssm_dng_version' ) ) {
        update_option( 'ssm_dng_version', SSM_DNG_VERSION );
    }
}
register_activation_hook( __FILE__, 'ssm_dng_activate' );

/**
 * Enqueue scripts and styles for the admin area.
 * We use wp_localize_script to pass necessary data to (JavaScript).
 *
 * @param string $hook The current admin page hook.
 * @return void
 */
function ssm_dng_admin_enqueue_scripts( $hook ) {
    // Only load assets on our specific admin page (TBD: screen hook name)
    // We will check against the top-level menu slug 'ssm-dng-dashboard'
    $screen = get_current_screen();
    if ( $screen && strpos( $screen->base, 'ssm-dng-dashboard' ) === false ) {
        return;
    }

    // (CSS) File
    wp_enqueue_style( 
        'ssm-dng-style', 
        SSM_DNG_URL . 'ssm-digital-nomad-guide.css', 
        array(), 
        SSM_DNG_VERSION 
    );

    // (JavaScript) File
    wp_enqueue_script( 
        'ssm-dng-script', 
        SSM_DNG_URL . 'ssm-digital-nomad-guide.js', 
        array( 'jquery' ), 
        SSM_DNG_VERSION, 
        true 
    );

    // Localize Script to pass essential data to (JavaScript)
    wp_localize_script( 'ssm-dng-script', 'ssmData', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'ssm-dng-nonce' ),
        'caps'     => array(
            'can_manage' => current_user_can( 'manage_options' ),
        ),
        'strings' => array(
            'error_title'    => esc_html__( 'Sorry, Something Went Wrong', 'ssm-dng' ),
            'loading'        => esc_html__( 'Loading Data...', 'ssm-dng' ),
            'success_saved'  => esc_html__( 'Information successfully saved!', 'ssm-dng' ),
            'no_countries'   => esc_html__( 'No country found matching the criteria.', 'ssm-dng' ),
        ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'ssm_dng_admin_enqueue_scripts' );

// --------------------------------------------------------------------------
/** Part 2 — Admin Menu and Screen Container */
// --------------------------------------------------------------------------

/**
 * Adds the main admin menu page.
 *
 * @return void
 */
function ssm_dng_add_admin_menu() {
    add_menu_page(
        esc_html__( 'Digital Nomad Guide', 'ssm-dng' ), // Page Title
        esc_html__( 'Nomad Visa Guide', 'ssm-dng' ),    // Menu Title
        'manage_options',                               // Capability
        'ssm-dng-dashboard',                            // Menu Slug
        'ssm_dng_render_admin_page',                    // Function to render content
        'dashicons-admin-site-alt3',                    // Icon
        6                                               // Position
    );
}
add_action( 'admin_menu', 'ssm_dng_add_admin_menu' );

/**
 * Renders the main admin page container and template.
 *
 * @return void
 */
function ssm_dng_render_admin_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Digital Nomad Visa Guide', 'ssm-dng' ); ?></h1>
        <p class="description"><?php esc_html_e( 'A complete list of visa requirements, links, and guides for remote workers and freelancers.', 'ssm-dng' ); ?></p>
        
        <div id="ssm-dng-root" class="ssm-root" data-screen="dashboard">
            <?php esc_html_e( 'Loading...', 'ssm-dng' ); ?>
        </div>
    </div>
    <?php
}

// --------------------------------------------------------------------------
/** Part 3 — Countries Data (Hardcoded for simplicity) and AJAX Endpoints */
// --------------------------------------------------------------------------

/**
 * Centralized list of Digital Nomad Visa countries and their data.
 * This can be fetched from an external source or database in the future for updates.
 * (Note: Data is simplified and needs real-time verification by the user).
 * * @return array
 */
function ssm_dng_get_country_data() {
    // We will start with a small, organized list for the (PHP) template/structure.
    // The complete list of 40+ countries will be loaded via (AJAX) for better performance/structure.
    return array(
        'portugal' => array(
            'name' => esc_html__( 'Portugal', 'ssm-dng' ),
            'flag' => '🇵🇹', // Emoji flag for visual interest
            'income' => esc_html__( 'Approx. €3,280 monthly', 'ssm-dng' ), // Approx €3,280/month (D7 or Digital Nomad Visa)
            'cost_of_living' => esc_html__( 'Medium to High', 'ssm-dng' ),
            'family' => esc_html__( 'Allowed', 'ssm-dng' ),
            'tax' => esc_html__( 'Possible tax exemption under (NHR) for the first 10 years', 'ssm-dng' ),
            'link' => 'https://vistos.mne.gov.pt/en/', // Official link (Example)
            'guide' => esc_html__( 'Apply at the Portuguese (Consulate) or (Embassy), or contact local authorities for the "D7/Digital Nomad Visa".', 'ssm-dng' ),
        ),
        'spain' => array(
            'name' => esc_html__( 'Spain', 'ssm-dng' ),
            'flag' => '🇪🇸',
            'income' => esc_html__( 'Approx. €2,100 monthly', 'ssm-dng' ),
            'cost_of_living' => esc_html__( 'Medium', 'ssm-dng' ),
            'family' => esc_html__( 'Allowed', 'ssm-dng' ),
            'tax' => esc_html__( 'Flat rate of 24% possible for the first 6 years under the (Beckham Law) tax break', 'ssm-dng' ),
            'link' => 'https://www.exteriores.gob.es/en/EmbajadasConsulados/Paginas/index.aspx',
            'guide' => esc_html__( 'Contact Spain\'s Visa Center or Consulate. You will receive a one-year (Residence Permit).', 'ssm-dng' ),
        ),
        // Add more countries here later, or load dynamically via AJAX/JS
    );
}

/**
 * (AJAX) Endpoint to fetch the full list of countries.
 * Used by (JavaScript) to render the main dashboard list.
 *
 * @return void
 */
function ssm_dng_ajax_get_country_list() {
    check_ajax_referer( 'ssm-dng-nonce', 'nonce' );

    // Check capability for security, though this data is non-sensitive
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'ssm-dng' ) ) );
    }

    $countries = ssm_dng_get_country_data();
    
    // In a real-world scenario, we would fetch all 40+ countries here.
    // For now, we return the structured, small list.

    wp_send_json_success( array(
        'countries' => $countries,
    ) );
}
add_action( 'wp_ajax_ssm_dng_get_country_list', 'ssm_dng_ajax_get_country_list' );

// --------------------------------------------------------------------------
/** Part 4 — HTML Templates and Shortcode */
// --------------------------------------------------------------------------

/**
 * Defines the main (HTML) templates for the (JavaScript) application.
 *
 * @return void
 */
function ssm_dng_add_templates() {
    // Only display templates in the admin area
    if ( ! is_admin() && ! is_singular() ) { // is_singular() added to allow shortcode templates on front-end
        return;
    }
    ?>
    <template id="ssm-dng-dashboard-template">
        <div id="ssm-dng-filter-bar" class="ssm-dng-filter-bar">
            <input type="search" id="ssm-dng-search" placeholder="<?php esc_attr_e( 'Search by Country Name or Income Requirement...', 'ssm-dng' ); ?>" />
            <select id="ssm-dng-filter-income">
                <option value=""><?php esc_html_e( 'Minimum Monthly Income', 'ssm-dng' ); ?></option>
                <option value="low"><?php esc_html_e( 'Less than €2,000', 'ssm-dng' ); ?></option>
                <option value="medium"><?php esc_html_e( '€2,000 to €4,000', 'ssm-dng' ); ?></option>
                <option value="high"><?php esc_html_e( 'More than €4,000', 'ssm-dng' ); ?></option>
            </select>
            <button id="ssm-dng-reset-filters" class="button"><?php esc_html_e( 'Reset Filters', 'ssm-dng' ); ?></button>
        </div>

        <div id="ssm-dng-country-list" class="ssm-dng-list-grid">
            <div id="ssm-dng-loader" class="ssm-dng-loading-state">
                <p><?php esc_html_e( 'Compiling the list of best Digital Nomad Visas for you...', 'ssm-dng' ); ?></p>
            </div>
        </div>

        <h2 class="ssm-dng-section-heading"><?php esc_html_e( 'Bonus Information and Benefits', 'ssm-dng' ); ?></h2>
        <div class="ssm-dng-bonus-section">
            <div class="ssm-dng-bonus-card">
                <h3><?php esc_html_e( '💰 List of Digital Banks', 'ssm-dng' ); ?></h3>
                <p><?php esc_html_e( 'Best for international transactions: (Wise), (Revolut), (N26). They help you open an account while sitting in your home country.', 'ssm-dng' ); ?></p>
            </div>
            <div class="ssm-dng-bonus-card">
                <h3><?php esc_html_e( '👨‍👩‍👧 Traveling with Family', 'ssm-dng' ); ?></h3>
                <p><?php esc_html_e( 'Many countries like Portugal, Spain, and Iceland offer a **Family Visa** option. Check the relevant country section for details.', 'ssm-dng' ); ?></p>
            </div>
            <div class="ssm-dng-bonus-card">
                <h3><?php esc_html_e( '💼 International (CV) and (Cover Letter)', 'ssm-dng' ); ?></h3>
                <p><?php esc_html_e( 'Use an international standard (CV) without a photo or date of birth when applying for jobs (especially in European countries).', 'ssm-dng' ); ?></p>
            </div>
        </div>
    </template>

    <template id="ssm-dng-country-card-template">
        <div class="ssm-dng-card" data-country-slug="[SLUG]">
            <h2 class="ssm-dng-country-title">[FLAG] [NAME]</h2>
            <div class="ssm-dng-details-table">
                <div><span><?php esc_html_e( 'Income Requirement', 'ssm-dng' ); ?>:</span> <strong data-ssm-field="income">[INCOME]</strong></div>
                <div><span><?php esc_html_e( 'Tax Information', 'ssm-dng' ); ?>:</span> <span data-ssm-field="tax">[TAX]</span></div>
                <div><span><?php esc_html_e( 'Cost of Living', 'ssm-dng' ); ?>:</span> <span data-ssm-field="cost_of_living">[COST]</span></div>
                <div><span><?php esc_html_e( 'Family Allowed', 'ssm-dng' ); ?>:</span> <span data-ssm-field="family">[FAMILY]</span></div>
            </div>
            
            <h3 class="ssm-dng-guide-heading"><?php esc_html_e( 'Step-by-Step Guide', 'ssm-dng' ); ?></h3>
            <p data-ssm-field="guide">[GUIDE]</p>

            <a href="[LINK]" target="_blank" class="ssm-dng-apply-link button button-primary">
                <?php esc_html_e( 'Go to Official Application Link 🔗', 'ssm-dng' ); ?>
            </a>
            <p class="ssm-dng-disclaimer"><?php esc_html_e( 'Important: Always verify fees, requirements, and procedures on the official link.', 'ssm-dng' ); ?></p>
        </div>
    </template>
    <?php
}
add_action( 'admin_footer', 'ssm_dng_add_templates' );

/**
 * Shortcode to display the country list on the front-end.
 * This will use the same (JavaScript) logic and templates.
 *
 * @param array $atts Shortcode attributes.
 * @return string The (HTML) output.
 */
function ssm_dng_digital_nomad_shortcode( $atts ) {
    // Enqueue the scripts/styles if used on the front-end
    wp_enqueue_style( 
        'ssm-dng-style', 
        SSM_DNG_URL . 'ssm-digital-nomad-guide.css', 
        array(), 
        SSM_DNG_VERSION 
    );
    wp_enqueue_script( 
        'ssm-dng-script', 
        SSM_DNG_URL . 'ssm-digital-nomad-guide.js', 
        array( 'jquery' ), 
        SSM_DNG_VERSION, 
        true 
    );

    // Output the container and the templates
    ob_start();
    ssm_dng_add_templates(); // Add templates to the footer
    $templates = ob_get_clean();

    return $templates . '
        <div id="ssm-dng-root" class="ssm-root" data-screen="dashboard">
            <p>' . esc_html__( 'Loading data. Please wait.', 'ssm-dng' ) . '</p>
        </div>';
}
add_shortcode( 'digital_nomad_visa_list', 'ssm_dng_digital_nomad_shortcode' );

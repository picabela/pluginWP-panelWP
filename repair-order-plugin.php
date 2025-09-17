<?php
/**
 * Plugin Name: Repair Order Form
 * Plugin URI: https://example.com/repair-order-plugin
 * Description: Formularz zamówień napraw z integracją InPost i panelem administracyjnym
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: repair-order
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('REPAIR_ORDER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('REPAIR_ORDER_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('REPAIR_ORDER_VERSION', '1.0.0');

// Main plugin class
class RepairOrderPlugin {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_custom_routes'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('repair-order', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize admin
        if (is_admin()) {
            require_once REPAIR_ORDER_PLUGIN_PATH . 'includes/admin.php';
            new RepairOrderAdmin();
        }
        
        // Initialize frontend
        require_once REPAIR_ORDER_PLUGIN_PATH . 'includes/frontend.php';
        new RepairOrderFrontend();
        
        // Initialize database
        require_once REPAIR_ORDER_PLUGIN_PATH . 'includes/database.php';
        new RepairOrderDatabase();
        
        // Initialize API handlers
        require_once REPAIR_ORDER_PLUGIN_PATH . 'includes/api-handler.php';
        new RepairOrderAPIHandler();
    }
    
    public function add_rewrite_rules() {
        // Rule for /zamowienie (main order form)
        add_rewrite_rule(
            '^zamowienie/?$',
            'index.php?repair_order_page=form',
            'top'
        );
        
        // Rule for /zamowienie?IDzamowienia (dynamic order links)
        add_rewrite_rule(
            '^zamowienie/([^/]+)/?$',
            'index.php?repair_order_page=order&order_id=$matches[1]',
            'top'
        );
        
        // Rule for payment processing
        add_rewrite_rule(
            '^zamowienie/platnosc/([^/]+)/?$',
            'index.php?repair_order_page=payment&order_id=$matches[1]',
            'top'
        );
        
        // Rule for payment confirmation
        add_rewrite_rule(
            '^zamowienie/potwierdzenie/([^/]+)/?$',
            'index.php?repair_order_page=confirmation&order_id=$matches[1]',
            'top'
        );
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'repair_order_page';
        $vars[] = 'order_id';
        $vars[] = 'estimate_id';
        return $vars;
    }
    
    public function handle_custom_routes() {
        $repair_order_page = get_query_var('repair_order_page');
        
        if (!$repair_order_page) {
            return;
        }
        
        // Prevent WordPress from trying to find a template
        global $wp_query;
        $wp_query->is_404 = false;
        status_header(200);
        
        switch ($repair_order_page) {
            case 'form':
                $this->display_order_form();
                break;
            case 'order':
                $this->display_dynamic_order();
                break;
            case 'payment':
                $this->process_payment();
                break;
            case 'confirmation':
                $this->display_confirmation();
                break;
            default:
                wp_redirect(home_url());
                exit;
        }
        
        exit;
    }
    
    private function display_order_form() {
        $estimate_id = isset($_GET['IDzamowienia']) ? sanitize_text_field($_GET['IDzamowienia']) : null;
        
        get_header();
        echo '<div class="repair-order-container">';
        
        if ($estimate_id) {
            // Display form with pre-filled data from estimate
            echo do_shortcode('[repair_order_form estimate_id="' . $estimate_id . '"]');
        } else {
            // Display regular form
            echo do_shortcode('[repair_order_form]');
        }
        
        echo '</div>';
        get_footer();
    }
    
    private function display_dynamic_order() {
        $order_id = get_query_var('order_id');
        
        if (!$order_id) {
            wp_redirect(home_url());
            exit;
        }
        
        get_header();
        echo '<div class="repair-order-container">';
        echo do_shortcode('[repair_order_form order_id="' . $order_id . '"]');
        echo '</div>';
        get_footer();
    }
    
    private function process_payment() {
        $order_id = get_query_var('order_id');
        
        if (!$order_id) {
            wp_redirect(home_url());
            exit;
        }
        
        // Check if sandbox mode
        $sandbox_mode = get_option('repair_order_sandbox_mode', 1);
        
        if ($sandbox_mode) {
            // Simulate payment in sandbox mode
            $this->simulate_payment($order_id);
        } else {
            // Redirect to real payment gateway (PayU, etc.)
            $this->redirect_to_payment_gateway($order_id);
        }
    }
    
    private function simulate_payment($order_id) {
        global $wpdb;
        
        // Update order status to paid
        $wpdb->update(
            $wpdb->prefix . 'repair_orders',
            array(
                'payment_status' => 'paid',
                'payment_date' => current_time('mysql')
            ),
            array('order_id' => $order_id),
            array('%s', '%s'),
            array('%s')
        );
        
        // Generate InPost label
        require_once REPAIR_ORDER_PLUGIN_PATH . 'includes/api-handler.php';
        $api_handler = new RepairOrderAPIHandler();
        $label_result = $api_handler->create_inpost_shipment($order_id);
        
        // Redirect to confirmation
        wp_redirect(home_url('/zamowienie/potwierdzenie/' . $order_id));
        exit;
    }
    
    private function redirect_to_payment_gateway($order_id) {
        // TODO: Implement PayU or other payment gateway integration
        // For now, redirect to simulation
        $this->simulate_payment($order_id);
    }
    
    private function display_confirmation() {
        $order_id = get_query_var('order_id');
        
        if (!$order_id) {
            wp_redirect(home_url());
            exit;
        }
        
        get_header();
        echo '<div class="repair-order-container">';
        echo do_shortcode('[repair_order_confirmation order_id="' . $order_id . '"]');
        echo '</div>';
        get_footer();
    }
    
    public function activate() {
        // Create database tables
        require_once REPAIR_ORDER_PLUGIN_PATH . 'includes/database.php';
        RepairOrderDatabase::create_tables();
        
        $this->add_rewrite_rules();
        flush_rewrite_rules();
        
        // Set default options
        $default_options = array(
            'sandbox_mode' => 1,
            'inpost_api_token' => '',
            'inpost_organization_id' => '',
            'recipient_name' => '',
            'recipient_email' => '',
            'recipient_phone' => '',
            'service_description' => 'Naprawa obuwia',
            'service_price' => '50.00'
        );
        
        foreach ($default_options as $key => $value) {
            add_option('repair_order_' . $key, $value);
        }
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize the plugin
new RepairOrderPlugin();
?>

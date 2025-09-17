<?php
if (!defined('ABSPATH')) {
    exit;
}

class RepairOrderLinkGenerator {
    
    public function __construct() {
        add_action('wp_ajax_generate_dynamic_link', array($this, 'generate_dynamic_link'));
        add_action('wp_ajax_get_link_analytics', array($this, 'get_link_analytics'));
        add_action('wp_ajax_delete_generated_link', array($this, 'delete_generated_link'));
        add_action('init', array($this, 'handle_dynamic_links'));
        add_action('admin_init', array($this, 'create_links_table'));
    }
    
    public function create_links_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'repair_order_links';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            link_id varchar(50) NOT NULL,
            service_description text NOT NULL,
            service_price decimal(10,2) NOT NULL,
            custom_fields text DEFAULT '',
            expiry_date datetime DEFAULT NULL,
            usage_limit int DEFAULT 0,
            usage_count int DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_by varchar(100) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_used datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY link_id (link_id),
            KEY is_active (is_active),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Create link usage tracking table
        $usage_table = $wpdb->prefix . 'repair_order_link_usage';
        $usage_sql = "CREATE TABLE $usage_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            link_id varchar(50) NOT NULL,
            order_id varchar(50) DEFAULT '',
            user_ip varchar(45) DEFAULT '',
            user_agent text DEFAULT '',
            referrer text DEFAULT '',
            used_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY link_id (link_id),
            KEY used_at (used_at)
        ) $charset_collate;";
        
        dbDelta($usage_sql);
    }
    
    public function handle_dynamic_links() {
        // Handle dynamic link access
        $request_uri = $_SERVER['REQUEST_URI'];
        
        // Check for dynamic link pattern: /zamowienie-link/LINK_ID
        if (preg_match('/\/zamowienie-link\/([a-zA-Z0-9-]+)\/?/', $request_uri, $matches)) {
            $link_id = $matches[1];
            $this->process_dynamic_link($link_id);
        }
    }
    
    private function process_dynamic_link($link_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'repair_order_links';
        
        // Get link data
        $link_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE link_id = %s AND is_active = 1",
            $link_id
        ));
        
        if (!$link_data) {
            wp_die('Link nie istnieje lub wygasł.', 'Błąd linku', array('response' => 404));
        }
        
        // Check expiry
        if ($link_data->expiry_date && strtotime($link_data->expiry_date) < time()) {
            wp_die('Link wygasł.', 'Link wygasł', array('response' => 410));
        }
        
        // Check usage limit
        if ($link_data->usage_limit > 0 && $link_data->usage_count >= $link_data->usage_limit) {
            wp_die('Link osiągnął limit użyć.', 'Limit użyć', array('response' => 410));
        }
        
        // Track usage
        $this->track_link_usage($link_id);
        
        // Update usage count
        $wpdb->update(
            $table_name,
            array(
                'usage_count' => $link_data->usage_count + 1,
                'last_used' => current_time('mysql')
            ),
            array('link_id' => $link_id)
        );
        
        // Generate order and redirect
        $order_id = $this->create_order_from_link($link_data);
        
        if ($order_id) {
            wp_redirect(home_url('/zamowienie/' . $order_id));
            exit;
        } else {
            wp_die('Błąd podczas tworzenia zamówienia.', 'Błąd systemu');
        }
    }
    
    private function track_link_usage($link_id) {
        global $wpdb;
        
        $usage_table = $wpdb->prefix . 'repair_order_link_usage';
        
        $wpdb->insert($usage_table, array(
            'link_id' => $link_id,
            'user_ip' => $this->get_user_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'referrer' => substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500)
        ));
    }
    
    private function get_user_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    private function create_order_from_link($link_data) {
        $order_id = 'RO-' . date('Ymd') . '-' . wp_generate_password(8, false);
        
        $custom_fields = json_decode($link_data->custom_fields, true) ?: array();
        
        $order_data = array(
            'order_id' => $order_id,
            'service_description' => $link_data->service_description,
            'service_price' => $link_data->service_price,
            'payment_status' => 'pending',
            'repair_status' => 'pending',
            'customer_name' => '',
            'customer_email' => '',
            'customer_phone' => '',
            'sender_name' => '',
            'sender_email' => '',
            'sender_phone' => '',
            'sender_address' => '',
            'return_locker' => $custom_fields['default_locker'] ?? ''
        );
        
        if (RepairOrderDatabase::insert_order($order_data)) {
            // Add note about link source
            RepairOrderDatabase::add_order_note(
                $order_id, 
                'Zamówienie utworzone z linku dynamicznego: ' . $link_data->link_id,
                'system',
                'Link Generator'
            );
            
            return $order_id;
        }
        
        return false;
    }
    
    public function generate_dynamic_link() {
        check_ajax_referer('repair_order_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $service_description = sanitize_text_field($_POST['service_description']);
        $service_price = floatval($_POST['service_price']);
        $expiry_days = intval($_POST['expiry_days'] ?? 0);
        $usage_limit = intval($_POST['usage_limit'] ?? 0);
        $custom_name = sanitize_text_field($_POST['custom_name'] ?? '');
        $default_locker = sanitize_text_field($_POST['default_locker'] ?? '');
        
        // Generate unique link ID
        $link_id = $custom_name ? sanitize_title($custom_name) : 'link-' . wp_generate_password(12, false);
        
        // Ensure uniqueness
        global $wpdb;
        $table_name = $wpdb->prefix . 'repair_order_links';
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE link_id = %s",
            $link_id
        ));
        
        if ($existing > 0) {
            $link_id .= '-' . wp_generate_password(6, false);
        }
        
        // Prepare expiry date
        $expiry_date = null;
        if ($expiry_days > 0) {
            $expiry_date = date('Y-m-d H:i:s', strtotime('+' . $expiry_days . ' days'));
        }
        
        // Prepare custom fields
        $custom_fields = json_encode(array(
            'default_locker' => $default_locker
        ));
        
        $current_user = wp_get_current_user();
        
        // Insert link data
        $result = $wpdb->insert($table_name, array(
            'link_id' => $link_id,
            'service_description' => $service_description,
            'service_price' => $service_price,
            'custom_fields' => $custom_fields,
            'expiry_date' => $expiry_date,
            'usage_limit' => $usage_limit,
            'created_by' => $current_user->display_name
        ));
        
        if ($result) {
            $link_url = home_url('/zamowienie-link/' . $link_id);
            
            wp_send_json_success(array(
                'link_id' => $link_id,
                'link_url' => $link_url,
                'message' => 'Link został wygenerowany pomyślnie'
            ));
        } else {
            wp_send_json_error('Błąd podczas generowania linku');
        }
    }
    
    public function get_link_analytics() {
        check_ajax_referer('repair_order_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $link_id = sanitize_text_field($_POST['link_id']);
        
        global $wpdb;
        
        // Get link data
        $table_name = $wpdb->prefix . 'repair_order_links';
        $link_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE link_id = %s",
            $link_id
        ));
        
        if (!$link_data) {
            wp_send_json_error('Link nie został znaleziony');
        }
        
        // Get usage analytics
        $usage_table = $wpdb->prefix . 'repair_order_link_usage';
        
        $daily_usage = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(used_at) as date, COUNT(*) as count 
             FROM $usage_table 
             WHERE link_id = %s 
             GROUP BY DATE(used_at) 
             ORDER BY date DESC 
             LIMIT 30",
            $link_id
        ));
        
        $referrer_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT referrer, COUNT(*) as count 
             FROM $usage_table 
             WHERE link_id = %s AND referrer != '' 
             GROUP BY referrer 
             ORDER BY count DESC 
             LIMIT 10",
            $link_id
        ));
        
        // Get orders created from this link
        $orders_table = $wpdb->prefix . 'repair_orders';
        $notes_table = $wpdb->prefix . 'repair_order_notes';
        
        $orders_from_link = $wpdb->get_results($wpdb->prepare(
            "SELECT o.* FROM $orders_table o 
             INNER JOIN $notes_table n ON o.order_id = n.order_id 
             WHERE n.note_content LIKE %s",
            '%' . $wpdb->esc_like($link_id) . '%'
        ));
        
        wp_send_json_success(array(
            'link_data' => $link_data,
            'daily_usage' => $daily_usage,
            'referrer_stats' => $referrer_stats,
            'orders_from_link' => $orders_from_link,
            'conversion_rate' => count($orders_from_link) > 0 ? (count($orders_from_link) / $link_data->usage_count) * 100 : 0
        ));
    }
    
    public function delete_generated_link() {
        check_ajax_referer('repair_order_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $link_id = sanitize_text_field($_POST['link_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'repair_order_links';
        
        $result = $wpdb->delete($table_name, array('link_id' => $link_id));
        
        if ($result) {
            wp_send_json_success(array('message' => 'Link został usunięty'));
        } else {
            wp_send_json_error('Błąd podczas usuwania linku');
        }
    }
    
    public static function get_all_links($limit = 50, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'repair_order_links';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit, $offset
        ));
    }
    
    public static function get_link_by_id($link_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'repair_order_links';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE link_id = %s",
            $link_id
        ));
    }
}

// Initialize the link generator
new RepairOrderLinkGenerator();
?>

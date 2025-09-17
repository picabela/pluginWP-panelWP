<?php
if (!defined('ABSPATH')) {
    exit;
}

class RepairOrderDatabase {
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Orders table
        $orders_table = $wpdb->prefix . 'repair_orders';
        $orders_sql = "CREATE TABLE $orders_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id varchar(50) NOT NULL,
            customer_name varchar(100) NOT NULL,
            customer_email varchar(100) NOT NULL,
            customer_phone varchar(20) NOT NULL,
            sender_name varchar(100) NOT NULL,
            sender_email varchar(100) NOT NULL,
            sender_phone varchar(20) NOT NULL,
            sender_address text NOT NULL,
            return_locker varchar(50) NOT NULL,
            service_description text NOT NULL,
            service_price decimal(10,2) NOT NULL,
            payment_status varchar(20) DEFAULT 'pending',
            repair_status varchar(20) DEFAULT 'received',
            inpost_shipment_id varchar(50) DEFAULT '',
            tracking_number varchar(50) DEFAULT '',
            label_url text DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_id (order_id),
            KEY payment_status (payment_status),
            KEY repair_status (repair_status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        $cost_estimates_table = $wpdb->prefix . 'repair_cost_estimates';
        $cost_estimates_sql = "CREATE TABLE $cost_estimates_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            estimate_id varchar(50) NOT NULL,
            service_description text NOT NULL,
            service_price decimal(10,2) NOT NULL,
            default_locker varchar(50) DEFAULT '',
            expiry_date datetime DEFAULT NULL,
            usage_limit int(11) DEFAULT 0,
            usage_count int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY estimate_id (estimate_id),
            KEY is_active (is_active),
            KEY expiry_date (expiry_date),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        $notes_table = $wpdb->prefix . 'repair_order_notes';
        $notes_sql = "CREATE TABLE $notes_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id varchar(50) NOT NULL,
            note_type varchar(20) DEFAULT 'admin',
            note_content text NOT NULL,
            created_by varchar(100) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY note_type (note_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        $status_history_table = $wpdb->prefix . 'repair_order_status_history';
        $status_history_sql = "CREATE TABLE $status_history_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id varchar(50) NOT NULL,
            status_type varchar(20) NOT NULL,
            old_status varchar(20) DEFAULT '',
            new_status varchar(20) NOT NULL,
            changed_by varchar(100) DEFAULT 'system',
            changed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY status_type (status_type),
            KEY changed_at (changed_at)
        ) $charset_collate;";
        
        // Settings table for dynamic configurations
        $settings_table = $wpdb->prefix . 'repair_settings';
        $settings_sql = "CREATE TABLE $settings_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($orders_sql);
        dbDelta($cost_estimates_sql);
        dbDelta($notes_sql);
        dbDelta($status_history_sql);
        dbDelta($settings_sql);
        
        update_option('repair_order_db_version', '1.1');
    }
    
    public static function check_database_version() {
        $current_version = get_option('repair_order_db_version', '0');
        $plugin_version = '1.1';
        
        if (version_compare($current_version, $plugin_version, '<')) {
            self::create_tables();
        }
    }
    
    public static function insert_cost_estimate($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'repair_cost_estimates';
        
        return $wpdb->insert($table, $data);
    }
    
    public static function get_cost_estimate_by_id($estimate_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'repair_cost_estimates';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE estimate_id = %s AND is_active = 1",
            $estimate_id
        ));
    }
    
    public static function get_cost_estimates($limit = 20, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'repair_cost_estimates';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }
    
    public static function update_cost_estimate_usage($estimate_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'repair_cost_estimates';
        
        return $wpdb->query($wpdb->prepare(
            "UPDATE $table SET usage_count = usage_count + 1 WHERE estimate_id = %s",
            $estimate_id
        ));
    }
    
    public static function delete_cost_estimate($estimate_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'repair_cost_estimates';
        
        return $wpdb->delete($table, array('estimate_id' => $estimate_id));
    }
    
    public static function is_cost_estimate_valid($estimate_id) {
        $estimate = self::get_cost_estimate_by_id($estimate_id);
        
        if (!$estimate || !$estimate->is_active) {
            return false;
        }
        
        // Check expiry date
        if ($estimate->expiry_date && strtotime($estimate->expiry_date) < time()) {
            return false;
        }
        
        // Check usage limit
        if ($estimate->usage_limit > 0 && $estimate->usage_count >= $estimate->usage_limit) {
            return false;
        }
        
        return true;
    }
    
    public static function cost_estimate_exists($estimate_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'repair_cost_estimates';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE estimate_id = %s",
            $estimate_id
        ));
        
        return $count > 0;
    }
    
    public static function get_orders($limit = 20, $offset = 0, $filters = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'repair_orders';
        
        $where_clauses = array();
        $where_values = array();
        
        if (!empty($filters['payment_status'])) {
            $where_clauses[] = "payment_status = %s";
            $where_values[] = $filters['payment_status'];
        }
        
        if (!empty($filters['repair_status'])) {
            $where_clauses[] = "repair_status = %s";
            $where_values[] = $filters['repair_status'];
        }
        
        if (!empty($filters['search'])) {
            $where_clauses[] = "(order_id LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "created_at >= %s";
            $where_values[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "created_at <= %s";
            $where_values[] = $filters['date_to'] . ' 23:59:59';
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $sql = "SELECT * FROM $table $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare($sql, $where_values));
    }
    
    public static function get_orders_count($filters = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'repair_orders';
        
        $where_clauses = array();
        $where_values = array();
        
        if (!empty($filters['payment_status'])) {
            $where_clauses[] = "payment_status = %s";
            $where_values[] = $filters['payment_status'];
        }
        
        if (!empty($filters['repair_status'])) {
            $where_clauses[] = "repair_status = %s";
            $where_values[] = $filters['repair_status'];
        }
        
        if (!empty($filters['search'])) {
            $where_clauses[] = "(order_id LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "created_at >= %s";
            $where_values[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "created_at <= %s";
            $where_values[] = $filters['date_to'] . ' 23:59:59';
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $sql = "SELECT COUNT(*) FROM $table $where_sql";
        
        if (empty($where_values)) {
            return $wpdb->get_var($sql);
        } else {
            return $wpdb->get_var($wpdb->prepare($sql, $where_values));
        }
    }
    
    public static function get_order_by_id($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'repair_orders';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE order_id = %s",
            $order_id
        ));
    }
    
    public static function insert_order($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'repair_orders';
        
        $result = $wpdb->insert($table, $data);
        
        if ($result && isset($data['order_id'])) {
            self::log_status_change($data['order_id'], 'order', '', 'created', 'system');
        }
        
        return $result;
    }
    
    public static function update_order($order_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'repair_orders';
        
        $current_order = self::get_order_by_id($order_id);
        
        $result = $wpdb->update(
            $table,
            $data,
            array('order_id' => $order_id)
        );
        
        if ($result && $current_order) {
            if (isset($data['payment_status']) && $data['payment_status'] !== $current_order->payment_status) {
                self::log_status_change($order_id, 'payment', $current_order->payment_status, $data['payment_status']);
            }
            
            if (isset($data['repair_status']) && $data['repair_status'] !== $current_order->repair_status) {
                self::log_status_change($order_id, 'repair', $current_order->repair_status, $data['repair_status']);
            }
        }
        
        return $result;
    }
    
    public static function log_status_change($order_id, $status_type, $old_status, $new_status, $changed_by = 'admin') {
        global $wpdb;
        $table = $wpdb->prefix . 'repair_order_status_history';
        
        return $wpdb->insert($table, array(
            'order_id' => $order_id,
            'status_type' => $status_type,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'changed_by' => $changed_by
        ));
    }
    
    public static function add_order_note($order_id, $note_content, $note_type = 'admin', $created_by = 'admin') {
        global $wpdb;
        $table = $wpdb->prefix . 'repair_order_notes';
        
        return $wpdb->insert($table, array(
            'order_id' => $order_id,
            'note_type' => $note_type,
            'note_content' => $note_content,
            'created_by' => $created_by
        ));
    }
    
    public static function get_order_notes($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'repair_order_notes';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE order_id = %s ORDER BY created_at DESC",
            $order_id
        ));
    }
    
    public static function get_order_status_history($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'repair_order_status_history';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE order_id = %s ORDER BY changed_at DESC",
            $order_id
        ));
    }
    
    public static function get_order_statistics() {
        global $wpdb;
        $table = $wpdb->prefix . 'repair_orders';
        
        $stats = array();
        
        // Total orders
        $stats['total_orders'] = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        
        // Orders by payment status
        $payment_stats = $wpdb->get_results("SELECT payment_status, COUNT(*) as count FROM $table GROUP BY payment_status");
        foreach ($payment_stats as $stat) {
            $stats['payment_' . $stat->payment_status] = $stat->count;
        }
        
        // Orders by repair status
        $repair_stats = $wpdb->get_results("SELECT repair_status, COUNT(*) as count FROM $table GROUP BY repair_status");
        foreach ($repair_stats as $stat) {
            $stats['repair_' . $stat->repair_status] = $stat->count;
        }
        
        // Revenue statistics
        $stats['total_revenue'] = $wpdb->get_var("SELECT SUM(service_price) FROM $table WHERE payment_status = 'paid'");
        $stats['pending_revenue'] = $wpdb->get_var("SELECT SUM(service_price) FROM $table WHERE payment_status = 'pending'");
        
        // Recent orders (last 30 days)
        $stats['recent_orders'] = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        return $stats;
    }
}
?>

<?php
if (!defined('ABSPATH')) {
    exit;
}

class RepairOrderAdmin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_generate_dynamic_link', array($this, 'generate_cost_estimate_link'));
        add_action('wp_ajax_delete_cost_estimate', array($this, 'delete_cost_estimate'));
        add_action('wp_ajax_update_order_status', array($this, 'update_order_status'));
        add_action('wp_ajax_add_order_note', array($this, 'add_order_note'));
        add_action('wp_ajax_delete_order', array($this, 'delete_order'));
        add_action('wp_ajax_export_orders', array($this, 'export_orders'));
        add_action('wp_ajax_bulk_update_orders', array($this, 'bulk_update_orders'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Repair Orders',
            'Zamówienia Napraw',
            'manage_options',
            'repair-orders',
            array($this, 'admin_page'),
            'dashicons-tools',
            30
        );
        
        add_submenu_page(
            'repair-orders',
            'Wszystkie zamówienia',
            'Wszystkie zamówienia',
            'manage_options',
            'repair-orders',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'repair-orders',
            'Statystyki',
            'Statystyki',
            'manage_options',
            'repair-orders-stats',
            array($this, 'stats_page')
        );
    }
    
    public function register_settings() {
        // API Settings
        register_setting('repair_order_api', 'repair_order_sandbox_mode');
        register_setting('repair_order_api', 'repair_order_inpost_api_token');
        register_setting('repair_order_api', 'repair_order_inpost_organization_id');
        
        // Recipient Settings
        register_setting('repair_order_recipient', 'repair_order_recipient_name');
        register_setting('repair_order_recipient', 'repair_order_recipient_email');
        register_setting('repair_order_recipient', 'repair_order_recipient_phone');
        register_setting('repair_order_recipient', 'repair_order_recipient_locker');
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'repair-orders') !== false) {
            wp_enqueue_script('repair-order-admin', REPAIR_ORDER_PLUGIN_URL . 'assets/admin.js', array('jquery'), REPAIR_ORDER_VERSION, true);
            wp_enqueue_style('repair-order-admin', REPAIR_ORDER_PLUGIN_URL . 'assets/admin.css', array(), REPAIR_ORDER_VERSION);
            
            wp_localize_script('repair-order-admin', 'repairOrderAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('repair_order_nonce')
            ));
        }
    }
    
    public function admin_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'orders';
        ?>
        <div class="wrap">
            <h1>Zarządzanie Zamówieniami Napraw</h1>
            
            <!-- Updated navigation tabs - combined service and links into cost estimates -->
            <nav class="nav-tab-wrapper">
                <a href="?page=repair-orders&tab=orders" class="nav-tab <?php echo $active_tab == 'orders' ? 'nav-tab-active' : ''; ?>">Zamówienia</a>
                <a href="?page=repair-orders&tab=api" class="nav-tab <?php echo $active_tab == 'api' ? 'nav-tab-active' : ''; ?>">Ustawienia API</a>
                <a href="?page=repair-orders&tab=recipient" class="nav-tab <?php echo $active_tab == 'recipient' ? 'nav-tab-active' : ''; ?>">Odbiorca</a>
                <a href="?page=repair-orders&tab=cost_estimates" class="nav-tab <?php echo $active_tab == 'cost_estimates' ? 'nav-tab-active' : ''; ?>">Generowanie Kosztorysu</a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'orders':
                        $this->render_orders_tab();
                        break;
                    case 'api':
                        $this->render_api_tab();
                        break;
                    case 'recipient':
                        $this->render_recipient_tab();
                        break;
                    case 'cost_estimates':
                        $this->render_cost_estimates_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private function render_orders_tab() {
        // Handle bulk actions
        if (isset($_POST['bulk_action']) && isset($_POST['order_ids'])) {
            $this->handle_bulk_actions();
        }
        
        // Get filter parameters
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($current_page - 1) * $per_page;
        
        $filters = array();
        if (isset($_GET['payment_status']) && !empty($_GET['payment_status'])) {
            $filters['payment_status'] = sanitize_text_field($_GET['payment_status']);
        }
        if (isset($_GET['repair_status']) && !empty($_GET['repair_status'])) {
            $filters['repair_status'] = sanitize_text_field($_GET['repair_status']);
        }
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $filters['search'] = sanitize_text_field($_GET['search']);
        }
        if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
            $filters['date_from'] = sanitize_text_field($_GET['date_from']);
        }
        if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
            $filters['date_to'] = sanitize_text_field($_GET['date_to']);
        }
        
        $orders = RepairOrderDatabase::get_orders($per_page, $offset, $filters);
        $total_orders = RepairOrderDatabase::get_orders_count($filters);
        $total_pages = ceil($total_orders / $per_page);
        
        ?>
        <div class="repair-orders-list">
            <div class="orders-header">
                <h2>Lista Zamówień (<?php echo $total_orders; ?>)</h2>
                <div class="orders-actions">
                    <button type="button" id="export-orders" class="button">Eksportuj CSV</button>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="orders-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="repair-orders">
                    <input type="hidden" name="tab" value="orders">
                    
                    <div class="filter-row">
                        <input type="text" name="search" placeholder="Szukaj po ID, nazwisku, email..." value="<?php echo esc_attr(isset($_GET['search']) ? $_GET['search'] : ''); ?>">
                        
                        <select name="payment_status">
                            <option value="">Wszystkie płatności</option>
                            <option value="pending" <?php selected(isset($_GET['payment_status']) ? $_GET['payment_status'] : '', 'pending'); ?>>Oczekuje</option>
                            <option value="paid" <?php selected(isset($_GET['payment_status']) ? $_GET['payment_status'] : '', 'paid'); ?>>Opłacone</option>
                            <option value="failed" <?php selected(isset($_GET['payment_status']) ? $_GET['payment_status'] : '', 'failed'); ?>>Błąd</option>
                        </select>
                        
                        <select name="repair_status">
                            <option value="">Wszystkie naprawy</option>
                            <option value="received" <?php selected(isset($_GET['repair_status']) ? $_GET['repair_status'] : '', 'received'); ?>>Odebrane</option>
                            <option value="in_progress" <?php selected(isset($_GET['repair_status']) ? $_GET['repair_status'] : '', 'in_progress'); ?>>W trakcie</option>
                            <option value="completed" <?php selected(isset($_GET['repair_status']) ? $_GET['repair_status'] : '', 'completed'); ?>>Ukończone</option>
                            <option value="shipped" <?php selected(isset($_GET['repair_status']) ? $_GET['repair_status'] : '', 'shipped'); ?>>Wysłane</option>
                        </select>
                        
                        <input type="date" name="date_from" value="<?php echo esc_attr(isset($_GET['date_from']) ? $_GET['date_from'] : ''); ?>" placeholder="Od">
                        <input type="date" name="date_to" value="<?php echo esc_attr(isset($_GET['date_to']) ? $_GET['date_to'] : ''); ?>" placeholder="Do">
                        
                        <button type="submit" class="button">Filtruj</button>
                        <a href="?page=repair-orders&tab=orders" class="button">Wyczyść</a>
                    </div>
                </form>
            </div>
            
            <!-- Bulk Actions -->
            <form method="post" id="bulk-actions-form">
                <div class="bulk-actions">
                    <select name="bulk_action">
                        <option value="">Akcje grupowe</option>
                        <option value="mark_paid">Oznacz jako opłacone</option>
                        <option value="mark_in_progress">Oznacz jako w trakcie</option>
                        <option value="mark_completed">Oznacz jako ukończone</option>
                        <option value="mark_shipped">Oznacz jako wysłane</option>
                        <option value="delete">Usuń</option>
                    </select>
                    <button type="submit" class="button" onclick="return confirm('Czy na pewno chcesz wykonać tę akcję?')">Zastosuj</button>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="select-all-orders">
                            </td>
                            <th>ID Zamówienia</th>
                            <th>Klient</th>
                            <th>Email</th>
                            <th>Telefon</th>
                            <th>Usługa</th>
                            <th>Cena</th>
                            <th>Status Płatności</th>
                            <th>Status Naprawy</th>
                            <th>Paczkomat</th>
                            <th>Data</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="12" class="no-orders">Brak zamówień spełniających kryteria</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                        <tr class="order-row" data-order-id="<?php echo esc_attr($order->order_id); ?>">
                            <th class="check-column">
                                <input type="checkbox" name="order_ids[]" value="<?php echo esc_attr($order->order_id); ?>">
                            </th>
                            <td>
                                <strong><?php echo esc_html($order->order_id); ?></strong>
                                <?php if (!empty($order->tracking_number)): ?>
                                <br><small>Tracking: <?php echo esc_html($order->tracking_number); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($order->customer_name); ?></td>
                            <td>
                                <a href="mailto:<?php echo esc_attr($order->customer_email); ?>"><?php echo esc_html($order->customer_email); ?></a>
                            </td>
                            <td>
                                <a href="tel:<?php echo esc_attr($order->customer_phone); ?>"><?php echo esc_html($order->customer_phone); ?></a>
                            </td>
                            <td><?php echo esc_html($order->service_description); ?></td>
                            <td><?php echo esc_html(number_format($order->service_price, 2)); ?> zł</td>
                            <td>
                                <select class="order-status" data-order="<?php echo esc_attr($order->order_id); ?>" data-type="payment">
                                    <option value="pending" <?php selected($order->payment_status, 'pending'); ?>>Oczekuje</option>
                                    <option value="paid" <?php selected($order->payment_status, 'paid'); ?>>Opłacone</option>
                                    <option value="failed" <?php selected($order->payment_status, 'failed'); ?>>Błąd</option>
                                </select>
                            </td>
                            <td>
                                <select class="order-status" data-order="<?php echo esc_attr($order->order_id); ?>" data-type="repair">
                                    <option value="received" <?php selected($order->repair_status, 'received'); ?>>Odebrane</option>
                                    <option value="in_progress" <?php selected($order->repair_status, 'in_progress'); ?>>W trakcie</option>
                                    <option value="completed" <?php selected($order->repair_status, 'completed'); ?>>Ukończone</option>
                                    <option value="shipped" <?php selected($order->repair_status, 'shipped'); ?>>Wysłane</option>
                                </select>
                            </td>
                            <td><?php echo esc_html($order->return_locker); ?></td>
                            <td><?php echo esc_html(date('d.m.Y H:i', strtotime($order->created_at))); ?></td>
                            <td>
                                <button class="button button-small view-order" data-order="<?php echo esc_attr($order->order_id); ?>">Zobacz</button>
                                <button class="button button-small delete-order" data-order="<?php echo esc_attr($order->order_id); ?>">Usuń</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php wp_nonce_field('repair_order_bulk_action', 'bulk_nonce'); ?>
            </form>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper">
                <?php
                $pagination_args = array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo; Poprzednia',
                    'next_text' => 'Następna &raquo;',
                    'total' => $total_pages,
                    'current' => $current_page
                );
                echo paginate_links($pagination_args);
                ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Order Details Modal -->
        <div id="order-details-modal" class="order-modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Szczegóły zamówienia</h3>
                    <span class="close-modal">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="order-details-content"></div>
                    
                    <div class="order-notes-section">
                        <h4>Notatki</h4>
                        <div id="order-notes-list"></div>
                        <div class="add-note">
                            <textarea id="new-note-content" placeholder="Dodaj notatkę..." rows="3"></textarea>
                            <button type="button" id="add-note-btn" class="button">Dodaj notatkę</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function render_api_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('repair_order_api'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Tryb Testowy (Sandbox)</th>
                    <td>
                        <input type="checkbox" name="repair_order_sandbox_mode" value="1" <?php checked(get_option('repair_order_sandbox_mode'), 1); ?> />
                        <p class="description">Zaznacz aby używać trybu testowego InPost API</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">InPost API Token</th>
                    <td>
                        <input type="text" name="repair_order_inpost_api_token" value="<?php echo esc_attr(get_option('repair_order_inpost_api_token')); ?>" class="regular-text" />
                        <p class="description">Token API do InPost ShipX</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">InPost Organization ID</th>
                    <td>
                        <input type="text" name="repair_order_inpost_organization_id" value="<?php echo esc_attr(get_option('repair_order_inpost_organization_id')); ?>" class="regular-text" />
                        <p class="description">ID organizacji w systemie InPost</p>
                    </td>
                </tr>
            </table>
            
            <h3>API Płatności</h3>
            <p><em>Funkcjonalność płatności będzie dostępna w kolejnej wersji wtyczki.</em></p>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }
    
    private function render_recipient_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('repair_order_recipient'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Nazwa odbiorcy</th>
                    <td>
                        <input type="text" name="repair_order_recipient_name" value="<?php echo esc_attr(get_option('repair_order_recipient_name')); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Email odbiorcy</th>
                    <td>
                        <input type="email" name="repair_order_recipient_email" value="<?php echo esc_attr(get_option('repair_order_recipient_email')); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Telefon odbiorcy</th>
                    <td>
                        <input type="text" name="repair_order_recipient_phone" value="<?php echo esc_attr(get_option('repair_order_recipient_phone')); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Paczkomat odbiorcy</th>
                    <td>
                        <input type="text" name="repair_order_recipient_locker" value="<?php echo esc_attr(get_option('repair_order_recipient_locker')); ?>" class="regular-text" />
                        <p class="description">Kod paczkomatu gdzie będą odbierane przesyłki do naprawy</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }
    
    private function render_cost_estimates_tab() {
        $generated_estimates = RepairOrderDatabase::get_cost_estimates(20, 0);
        ?>
        <div class="cost-estimates-container">
            <div class="estimates-header">
                <h2>Generowanie Kosztorysu</h2>
                <p>Utwórz kosztorys z ceną i opisem usługi, a następnie wygeneruj unikalny link do wysłania klientowi.</p>
            </div>
            
            <div class="estimate-generator-form">
                <h3>Nowy kosztorys</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Opis usługi</th>
                        <td>
                             Fixed ID to match JavaScript 
                            <textarea id="link_service_description" rows="3" class="large-text" placeholder="np. Naprawa obuwia - wymiana podeszwy"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Cena usługi (brutto)</th>
                        <td>
                             Fixed ID to match JavaScript 
                            <input type="number" id="link_service_price" step="0.01" min="0" placeholder="0.00" /> zł
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Nazwa kosztorysu (opcjonalna)</th>
                        <td>
                             Fixed ID to match JavaScript 
                            <input type="text" id="link_custom_name" placeholder="np. naprawa-butow-promocja" class="regular-text" />
                            <p class="description">Zostanie użyta w URL. Jeśli puste, zostanie wygenerowana automatycznie.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Domyślny paczkomat</th>
                        <td>
                             Fixed ID to match JavaScript 
                            <input type="text" id="link_default_locker" placeholder="np. KRA01M" class="regular-text" />
                            <p class="description">Paczkomat będzie wstępnie wypełniony w formularzu</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Wygaśnięcie linku</th>
                        <td>
                             Fixed ID to match JavaScript 
                            <select id="link_expiry_days">
                                <option value="0">Bez wygaśnięcia</option>
                                <option value="1">1 dzień</option>
                                <option value="7">7 dni</option>
                                <option value="30">30 dni</option>
                                <option value="90">90 dni</option>
                                <option value="365">1 rok</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Limit użyć</th>
                        <td>
                             Fixed ID to match JavaScript 
                            <input type="number" id="link_usage_limit" value="0" min="0" />
                            <p class="description">0 = bez limitu</p>
                        </td>
                    </tr>
                </table>
                
                <p>
                     Fixed ID to match JavaScript 
                    <button type="button" id="generate_dynamic_link" class="button button-primary">Wygeneruj Kosztorys i Link</button>
                </p>
                
                 Fixed ID to match JavaScript 
                <div id="generated_link_container" style="display: none;">
                    <h4>Wygenerowany Link:</h4>
                    <div class="generated-link-box">
                        <input type="text" id="generated_link" readonly class="large-text" />
                        <button type="button" id="copy_link" class="button">Skopiuj</button>
                        <button type="button" id="test_link" class="button">Testuj</button>
                    </div>
                </div>
            </div>
            
            <div class="existing-estimates">
                <h3>Wygenerowane kosztorysy</h3>
                
                <?php if (empty($generated_estimates)): ?>
                <p>Brak wygenerowanych kosztorysów.</p>
                <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID Kosztorysu</th>
                            <th>Usługa</th>
                            <th>Cena</th>
                            <th>Użycia</th>
                            <th>Limit</th>
                            <th>Wygaśnięcie</th>
                            <th>Status</th>
                            <th>Utworzony</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($generated_estimates as $estimate): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($estimate->estimate_id); ?></strong>
                                <div class="estimate-url">
                                    <small><a href="<?php echo esc_url(home_url('/zamowienie?IDzamowienia=' . $estimate->estimate_id)); ?>" target="_blank">
                                        <?php echo esc_html(home_url('/zamowienie?IDzamowienia=' . $estimate->estimate_id)); ?>
                                    </a></small>
                                </div>
                            </td>
                            <td><?php echo esc_html(wp_trim_words($estimate->service_description, 8)); ?></td>
                            <td><?php echo esc_html(number_format($estimate->service_price, 2)); ?> zł</td>
                            <td><?php echo intval($estimate->usage_count); ?></td>
                            <td><?php echo $estimate->usage_limit > 0 ? intval($estimate->usage_limit) : '∞'; ?></td>
                            <td>
                                <?php if ($estimate->expiry_date): ?>
                                    <?php echo esc_html(date('d.m.Y H:i', strtotime($estimate->expiry_date))); ?>
                                <?php else: ?>
                                    Brak
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $is_expired = $estimate->expiry_date && strtotime($estimate->expiry_date) < time();
                                $is_limit_reached = $estimate->usage_limit > 0 && $estimate->usage_count >= $estimate->usage_limit;
                                
                                if (!$estimate->is_active) {
                                    echo '<span class="status-badge status-inactive">Nieaktywny</span>';
                                } elseif ($is_expired) {
                                    echo '<span class="status-badge status-expired">Wygasł</span>';
                                } elseif ($is_limit_reached) {
                                    echo '<span class="status-badge status-limit">Limit</span>';
                                } else {
                                    echo '<span class="status-badge status-active">Aktywny</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html(date('d.m.Y H:i', strtotime($estimate->created_at))); ?></td>
                            <td>
                                <button class="button button-small copy-estimate-url" data-url="<?php echo esc_attr(home_url('/zamowienie?IDzamowienia=' . $estimate->estimate_id)); ?>">Kopiuj</button>
                                <button class="button button-small delete-estimate" data-estimate="<?php echo esc_attr($estimate->estimate_id); ?>">Usuń</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function generate_cost_estimate_link() {
        try {
            check_ajax_referer('repair_order_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Brak uprawnień do wykonania tej operacji', 403);
                return;
            }
            
            // Validate required fields
            if (empty($_POST['service_description'])) {
                wp_send_json_error('Opis usługi jest wymagany');
                return;
            }
            
            if (empty($_POST['service_price']) || floatval($_POST['service_price']) <= 0) {
                wp_send_json_error('Cena usługi musi być większa od 0');
                return;
            }
            
            $service_description = sanitize_textarea_field($_POST['service_description']);
            $service_price = floatval($_POST['service_price']);
            $custom_name = sanitize_text_field($_POST['custom_name'] ?? '');
            $default_locker = sanitize_text_field($_POST['default_locker'] ?? '');
            $expiry_days = intval($_POST['expiry_days'] ?? 0);
            $usage_limit = intval($_POST['usage_limit'] ?? 0);
            
            // Validate custom name if provided
            if (!empty($custom_name)) {
                if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $custom_name)) {
                    wp_send_json_error('Nazwa kosztorysu może zawierać tylko litery, cyfry, myślniki i podkreślenia');
                    return;
                }
                
                // Check if custom name already exists
                if (RepairOrderDatabase::cost_estimate_exists($custom_name)) {
                    wp_send_json_error('Kosztorys o tej nazwie już istnieje');
                    return;
                }
            }
            
            // Generate unique estimate ID
            $estimate_id = !empty($custom_name) ? $custom_name : 'EST-' . date('Ymd') . '-' . wp_generate_password(6, false);
            
            // Ensure uniqueness
            $counter = 1;
            $original_id = $estimate_id;
            while (RepairOrderDatabase::cost_estimate_exists($estimate_id)) {
                $estimate_id = $original_id . '-' . $counter;
                $counter++;
                if ($counter > 100) {
                    wp_send_json_error('Nie można wygenerować unikalnego ID kosztorysu');
                    return;
                }
            }
            
            // Calculate expiry date
            $expiry_date = null;
            if ($expiry_days > 0) {
                $expiry_date = date('Y-m-d H:i:s', strtotime('+' . $expiry_days . ' days'));
            }
            
            // Store cost estimate
            $estimate_data = array(
                'estimate_id' => $estimate_id,
                'service_description' => $service_description,
                'service_price' => $service_price,
                'default_locker' => $default_locker,
                'expiry_date' => $expiry_date,
                'usage_limit' => $usage_limit,
                'usage_count' => 0,
                'is_active' => 1
            );
            
            $result = RepairOrderDatabase::insert_cost_estimate($estimate_data);
            
            if ($result) {
                $link_url = home_url('/zamowienie?IDzamowienia=' . $estimate_id);
                wp_send_json_success(array(
                    'link_url' => $link_url, 
                    'estimate_id' => $estimate_id,
                    'message' => 'Kosztorys został utworzony pomyślnie'
                ));
            } else {
                wp_send_json_error('Błąd podczas zapisywania kosztorysu w bazie danych');
            }
            
        } catch (Exception $e) {
            error_log('Repair Order Plugin - Generate Cost Estimate Error: ' . $e->getMessage());
            wp_send_json_error('Wystąpił nieoczekiwany błąd. Spróbuj ponownie.');
        }
    }
    
    public function delete_cost_estimate() {
        try {
            check_ajax_referer('repair_order_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Brak uprawnień do wykonania tej operacji', 403);
                return;
            }
            
            if (empty($_POST['estimate_id'])) {
                wp_send_json_error('ID kosztorysu jest wymagane');
                return;
            }
            
            $estimate_id = sanitize_text_field($_POST['estimate_id']);
            
            // Check if estimate exists
            if (!RepairOrderDatabase::cost_estimate_exists($estimate_id)) {
                wp_send_json_error('Kosztorys nie został znaleziony');
                return;
            }
            
            $result = RepairOrderDatabase::delete_cost_estimate($estimate_id);
            
            if ($result) {
                wp_send_json_success(array('message' => 'Kosztorys został usunięty pomyślnie'));
            } else {
                wp_send_json_error('Błąd podczas usuwania kosztorysu z bazy danych');
            }
            
        } catch (Exception $e) {
            error_log('Repair Order Plugin - Delete Cost Estimate Error: ' . $e->getMessage());
            wp_send_json_error('Wystąpił nieoczekiwany błąd podczas usuwania kosztorysu');
        }
    }
    
    public function update_order_status() {
        try {
            check_ajax_referer('repair_order_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Brak uprawnień do wykonania tej operacji', 403);
                return;
            }
            
            if (empty($_POST['order_id']) || empty($_POST['status_type']) || empty($_POST['status_value'])) {
                wp_send_json_error('Wszystkie pola są wymagane');
                return;
            }
            
            $order_id = sanitize_text_field($_POST['order_id']);
            $status_type = sanitize_text_field($_POST['status_type']);
            $status_value = sanitize_text_field($_POST['status_value']);
            
            // Validate status type
            if (!in_array($status_type, ['payment', 'repair'])) {
                wp_send_json_error('Nieprawidłowy typ statusu');
                return;
            }
            
            // Validate status values
            $valid_payment_statuses = ['pending', 'paid', 'failed'];
            $valid_repair_statuses = ['received', 'in_progress', 'completed', 'shipped'];
            
            if ($status_type === 'payment' && !in_array($status_value, $valid_payment_statuses)) {
                wp_send_json_error('Nieprawidłowy status płatności');
                return;
            }
            
            if ($status_type === 'repair' && !in_array($status_value, $valid_repair_statuses)) {
                wp_send_json_error('Nieprawidłowy status naprawy');
                return;
            }
            
            // Check if order exists
            $order = RepairOrderDatabase::get_order_by_id($order_id);
            if (!$order) {
                wp_send_json_error('Zamówienie nie zostało znalezione');
                return;
            }
            
            $update_data = array();
            if ($status_type === 'payment') {
                $update_data['payment_status'] = $status_value;
                if ($status_value === 'paid') {
                    $update_data['payment_date'] = current_time('mysql');
                }
            } elseif ($status_type === 'repair') {
                $update_data['repair_status'] = $status_value;
            }
            
            $result = RepairOrderDatabase::update_order($order_id, $update_data);
            
            if ($result !== false) {
                wp_send_json_success(array('message' => 'Status został zaktualizowany pomyślnie'));
            } else {
                wp_send_json_error('Błąd podczas aktualizacji statusu w bazie danych');
            }
            
        } catch (Exception $e) {
            error_log('Repair Order Plugin - Update Order Status Error: ' . $e->getMessage());
            wp_send_json_error('Wystąpił nieoczekiwany błąd podczas aktualizacji statusu');
        }
    }

    public function add_order_note() {
        try {
            check_ajax_referer('repair_order_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Brak uprawnień do wykonania tej operacji', 403);
                return;
            }
            
            if (empty($_POST['order_id']) || empty($_POST['note_content'])) {
                wp_send_json_error('ID zamówienia i treść notatki są wymagane');
                return;
            }
            
            $order_id = sanitize_text_field($_POST['order_id']);
            $note_content = sanitize_textarea_field($_POST['note_content']);
            
            // Check if order exists
            $order = RepairOrderDatabase::get_order_by_id($order_id);
            if (!$order) {
                wp_send_json_error('Zamówienie nie zostało znalezione');
                return;
            }
            
            $current_user = wp_get_current_user();
            
            $result = RepairOrderDatabase::add_order_note($order_id, $note_content, 'admin', $current_user->display_name);
            
            if ($result) {
                wp_send_json_success(array('message' => 'Notatka została dodana pomyślnie'));
            } else {
                wp_send_json_error('Błąd podczas dodawania notatki do bazy danych');
            }
            
        } catch (Exception $e) {
            error_log('Repair Order Plugin - Add Order Note Error: ' . $e->getMessage());
            wp_send_json_error('Wystąpił nieoczekiwany błąd podczas dodawania notatki');
        }
    }
    
    public function delete_order() {
        try {
            check_ajax_referer('repair_order_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Brak uprawnień do wykonania tej operacji', 403);
                return;
            }
            
            if (empty($_POST['order_id'])) {
                wp_send_json_error('ID zamówienia jest wymagane');
                return;
            }
            
            $order_id = sanitize_text_field($_POST['order_id']);
            
            // Check if order exists
            $order = RepairOrderDatabase::get_order_by_id($order_id);
            if (!$order) {
                wp_send_json_error('Zamówienie nie zostało znalezione');
                return;
            }
            
            global $wpdb;
            $result = $wpdb->delete($wpdb->prefix . 'repair_orders', array('order_id' => $order_id));
            
            if ($result !== false) {
                wp_send_json_success(array('message' => 'Zamówienie zostało usunięte pomyślnie'));
            } else {
                wp_send_json_error('Błąd podczas usuwania zamówienia z bazy danych');
            }
            
        } catch (Exception $e) {
            error_log('Repair Order Plugin - Delete Order Error: ' . $e->getMessage());
            wp_send_json_error('Wystąpił nieoczekiwany błąd podczas usuwania zamówienia');
        }
    }
    
    public function export_orders() {
        try {
            check_ajax_referer('repair_order_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_die('Brak uprawnień do eksportu danych');
            }
            
            $orders = RepairOrderDatabase::get_orders(1000, 0);
            
            if (empty($orders)) {
                wp_die('Brak zamówień do eksportu');
            }
            
            $filename = 'zamowienia_napraw_' . date('Y-m-d') . '.csv';
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);
            
            $output = fopen('php://output', 'w');
            
            if (!$output) {
                wp_die('Błąd podczas tworzenia pliku CSV');
            }
            
            // Add BOM for proper UTF-8 encoding in Excel
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($output, array(
                'ID Zamówienia', 'Klient', 'Email', 'Telefon', 'Paczkomat', 'Usługa', 'Cena', 
                'Status płatności', 'Status naprawy', 'Numer przesyłki', 'Data utworzenia'
            ));
            
            foreach ($orders as $order) {
                fputcsv($output, array(
                    $order->order_id,
                    $order->customer_name,
                    $order->customer_email,
                    $order->customer_phone,
                    $order->return_locker,
                    $order->service_description,
                    $order->service_price,
                    $order->payment_status,
                    $order->repair_status,
                    $order->tracking_number ?? '',
                    $order->created_at
                ));
            }
            
            fclose($output);
            exit;
            
        } catch (Exception $e) {
            error_log('Repair Order Plugin - Export Orders Error: ' . $e->getMessage());
            wp_die('Wystąpił błąd podczas eksportu zamówień');
        }
    }
    
    public function bulk_update_orders() {
        // Implement bulk update logic here
    }
}
?>

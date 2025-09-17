<?php
if (!defined('ABSPATH')) {
    exit;
}

class RepairOrderFrontend {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_shortcode('repair_order_form', array($this, 'render_shortcode'));
        add_shortcode('repair_form', array($this, 'render_shortcode'));
        add_shortcode('zamowienie_naprawy', array($this, 'render_shortcode'));
        add_shortcode('repair_order_button', array($this, 'render_button_shortcode'));
        add_shortcode('repair_order_status', array($this, 'render_status_shortcode'));
        add_shortcode('repair_order_confirmation', array($this, 'render_confirmation_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_submit_repair_order', array($this, 'handle_order_submission'));
        add_action('wp_ajax_nopriv_submit_repair_order', array($this, 'handle_order_submission'));
        add_action('wp_ajax_simulate_payment', array($this, 'simulate_payment'));
        add_action('wp_ajax_nopriv_simulate_payment', array($this, 'simulate_payment'));
        add_action('wp_ajax_generate_shipment', array($this, 'generate_shipment'));
        add_action('wp_ajax_nopriv_generate_shipment', array($this, 'generate_shipment'));
    }
    
    public function init() {
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('repair-order-frontend', REPAIR_ORDER_PLUGIN_URL . 'assets/frontend.js', array('jquery'), REPAIR_ORDER_VERSION, true);
        wp_enqueue_style('repair-order-frontend', REPAIR_ORDER_PLUGIN_URL . 'assets/frontend.css', array(), REPAIR_ORDER_VERSION);
        
        wp_localize_script('repair-order-frontend', 'repairOrderAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('repair_order_frontend_nonce'),
            'frontend_nonce' => wp_create_nonce('repair_order_frontend_nonce'), // Added for consistency
            'messages' => array(
                'processing' => 'Przetwarzanie...',
                'error' => 'Wystąpił błąd',
                'success' => 'Operacja zakończona pomyślnie'
            )
        ));
    }
    
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'service' => get_option('repair_order_service_description', 'Naprawa obuwia'),
            'price' => get_option('repair_order_service_price', '50.00'),
            'title' => 'Zamówienie Naprawy',
            'show_title' => 'true',
            'button_text' => 'Złóż zamówienie i przejdź do płatności',
            'theme' => 'default',
            'required_fields' => 'name,email,phone,locker',
            'default_locker' => '',
            'custom_css_class' => '',
            'estimate_id' => '', // Added estimate_id parameter for dynamic links
            'order_id' => '' // Added order_id parameter for existing orders
        ), $atts);
        
        if (empty($atts['estimate_id']) && isset($_GET['IDzamowienia'])) {
            $atts['estimate_id'] = sanitize_text_field($_GET['IDzamowienia']);
        }
        
        if (!empty($atts['estimate_id'])) {
            $estimate = RepairOrderDatabase::get_cost_estimate_by_id($atts['estimate_id']);
            if ($estimate && RepairOrderDatabase::is_cost_estimate_valid($atts['estimate_id'])) {
                $atts['service'] = $estimate->service_description;
                $atts['price'] = $estimate->service_price;
                $atts['default_locker'] = $estimate->default_locker;
                // Update usage count
                RepairOrderDatabase::update_cost_estimate_usage($atts['estimate_id']);
            }
        }
        
        // Validate price
        $price = floatval($atts['price']);
        if ($price <= 0) {
            return '<div class="repair-order-error">Błąd: Nieprawidłowa cena usługi</div>';
        }
        
        // Parse required fields
        $required_fields = array_map('trim', explode(',', $atts['required_fields']));
        
        ob_start();
        ?>
        <div class="repair-order-form-container <?php echo esc_attr($atts['theme']); ?> <?php echo esc_attr($atts['custom_css_class']); ?>">
            <?php if ($atts['show_title'] === 'true'): ?>
            <div class="repair-service-info">
                <h3><?php echo esc_html($atts['title']); ?></h3>
                <div class="service-details">
                    <p><strong>Usługa:</strong> <?php echo esc_html($atts['service']); ?></p>
                    <p><strong>Cena:</strong> <?php echo esc_html(number_format($price, 2)); ?> zł</p>
                </div>
            </div>
            <?php endif; ?>
            
            <form id="repair-order-form" class="repair-form" data-theme="<?php echo esc_attr($atts['theme']); ?>">
                <div class="form-section">
                    <h4>Dane klienta</h4>
                    <?php if (in_array('name', $required_fields)): ?>
                    <div class="form-row">
                        <label for="customer_name">Imię i nazwisko *</label>
                        <input type="text" id="customer_name" name="customer_name" required>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (in_array('email', $required_fields)): ?>
                    <div class="form-row">
                        <label for="customer_email">Email *</label>
                        <input type="email" id="customer_email" name="customer_email" required>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (in_array('phone', $required_fields)): ?>
                    <div class="form-row">
                        <label for="customer_phone">Telefon *</label>
                        <input type="tel" id="customer_phone" name="customer_phone" required>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (in_array('locker', $required_fields)): ?>
                    <div class="form-row">
                        <label for="return_locker">Kod paczkomatu do odesłania *</label>
                        <input type="text" id="return_locker" name="return_locker" value="<?php echo esc_attr($atts['default_locker']); ?>" required placeholder="np. KRA01M">
                        <small>Znajdź paczkomat na <a href="https://inpost.pl/znajdz-paczkomat" target="_blank">inpost.pl</a></small>
                    </div>
                    <?php endif; ?>
                </div>
                
                <input type="hidden" name="service_description" value="<?php echo esc_attr($atts['service']); ?>">
                <input type="hidden" name="service_price" value="<?php echo esc_attr($price); ?>">
                <input type="hidden" name="estimate_id" value="<?php echo esc_attr($atts['estimate_id']); ?>">
                <input type="hidden" name="order_id" value="<?php echo esc_attr($atts['order_id']); ?>">
                
                <div class="form-actions">
                    <button type="submit" class="submit-btn">
                        <span class="btn-text"><?php echo esc_html($atts['button_text']); ?></span>
                        <span class="btn-loading" style="display:none;">Przetwarzanie...</span>
                    </button>
                </div>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#repair-order-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $btn = $form.find('.submit-btn');
                var $btnText = $btn.find('.btn-text');
                var $btnLoading = $btn.find('.btn-loading');
                
                // Show loading state
                $btn.prop('disabled', true);
                $btnText.hide();
                $btnLoading.show();
                
                var formData = $form.serialize();
                formData += '&action=submit_repair_order&nonce=' + repairOrderAjax.frontend_nonce;
                
                $.ajax({
                    url: repairOrderAjax.ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            // Redirect to payment page
                            window.location.href = response.data.redirect_url;
                        } else {
                            alert('Błąd: ' + response.data);
                            // Reset button state
                            $btn.prop('disabled', false);
                            $btnText.show();
                            $btnLoading.hide();
                        }
                    },
                    error: function() {
                        alert('Wystąpił błąd podczas przetwarzania zamówienia');
                        // Reset button state
                        $btn.prop('disabled', false);
                        $btnText.show();
                        $btnLoading.hide();
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function render_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'text' => 'Zamów naprawę',
            'service' => get_option('repair_order_service_description', 'Naprawa obuwia'),
            'price' => get_option('repair_order_service_price', '50.00'),
            'css_class' => 'repair-order-button',
            'target' => '_self' // _self, _blank, modal
        ), $atts);
        
        $price = floatval($atts['price']);
        if ($price <= 0) {
            return '<span class="repair-order-error">Błąd: Nieprawidłowa cena</span>';
        }
        
        if ($atts['target'] === 'modal') {
            // Return button that opens modal with form
            return sprintf(
                '<button class="repair-order-modal-trigger %s" data-service="%s" data-price="%s">%s</button>',
                esc_attr($atts['css_class']),
                esc_attr($atts['service']),
                esc_attr($price),
                esc_html($atts['text'])
            );
        } else {
            // Return link to order page
            $link_url = add_query_arg(array(
                'repair_service' => urlencode($atts['service']),
                'repair_price' => $price
            ), home_url('/zamowienie-naprawy/'));
            
            $target_attr = $atts['target'] === '_blank' ? 'target="_blank"' : '';
            
            return sprintf(
                '<a href="%s" class="%s" %s>%s</a>',
                esc_url($link_url),
                esc_attr($atts['css_class']),
                $target_attr,
                esc_html($atts['text'])
            );
        }
    }
    
    public function render_status_shortcode($atts) {
        $atts = shortcode_atts(array(
            'order_id' => '',
            'show_details' => 'false',
            'show_tracking' => 'true'
        ), $atts);
        
        if (empty($atts['order_id'])) {
            return '<div class="repair-order-error">Błąd: Nie podano numeru zamówienia</div>';
        }
        
        $order = RepairOrderDatabase::get_order_by_id($atts['order_id']);
        
        if (!$order) {
            return '<div class="repair-order-error">Zamówienie nie zostało znalezione</div>';
        }
        
        ob_start();
        ?>
        <div class="repair-order-status">
            <h4>Status zamówienia #<?php echo esc_html($order->order_id); ?></h4>
            
            <div class="status-info">
                <p><strong>Status płatności:</strong> 
                    <span class="status-badge status-<?php echo esc_attr($order->payment_status); ?>">
                        <?php echo $this->get_status_label($order->payment_status, 'payment'); ?>
                    </span>
                </p>
                
                <p><strong>Status naprawy:</strong> 
                    <span class="status-badge status-<?php echo esc_attr($order->repair_status); ?>">
                        <?php echo $this->get_status_label($order->repair_status, 'repair'); ?>
                    </span>
                </p>
                
                <?php if ($atts['show_tracking'] === 'true' && !empty($order->tracking_number)): ?>
                <p><strong>Numer przesyłki:</strong> <?php echo esc_html($order->tracking_number); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if ($atts['show_details'] === 'true'): ?>
            <div class="order-details">
                <p><strong>Usługa:</strong> <?php echo esc_html($order->service_description); ?></p>
                <p><strong>Cena:</strong> <?php echo esc_html($order->service_price); ?> zł</p>
                <p><strong>Data zamówienia:</strong> <?php echo esc_html(date('d.m.Y H:i', strtotime($order->created_at))); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function render_confirmation_shortcode($atts) {
        $atts = shortcode_atts(array(
            'order_id' => ''
        ), $atts);
        
        if (empty($atts['order_id'])) {
            return '<div class="repair-order-error">Błąd: Nie podano numeru zamówienia</div>';
        }
        
        $order = RepairOrderDatabase::get_order_by_id($atts['order_id']);
        
        if (!$order) {
            return '<div class="repair-order-error">Zamówienie nie zostało znalezione</div>';
        }
        
        ob_start();
        ?>
        <div class="repair-order-confirmation">
            <div class="confirmation-header">
                <h2>✓ Zamówienie zostało złożone pomyślnie!</h2>
                <p class="order-number">Numer zamówienia: <strong>#<?php echo esc_html($order->order_id); ?></strong></p>
            </div>
            
            <div class="order-summary">
                <h3>Podsumowanie zamówienia</h3>
                <div class="summary-details">
                    <p><strong>Usługa:</strong> <?php echo esc_html($order->service_description); ?></p>
                    <p><strong>Cena:</strong> <?php echo esc_html(number_format($order->service_price, 2)); ?> zł</p>
                    <p><strong>Paczkomat zwrotny:</strong> <?php echo esc_html($order->return_locker); ?></p>
                </div>
            </div>
            
            <div class="payment-section">
                <h3>Płatność</h3>
                <?php if ($order->payment_status === 'pending'): ?>
                <div class="payment-form">
                    <p>Kwota do zapłaty: <strong><?php echo esc_html(number_format($order->service_price, 2)); ?> zł</strong></p>
                    <button id="simulate-payment" class="payment-btn" data-order="<?php echo esc_attr($order->order_id); ?>">
                        <span class="btn-text">Zapłać teraz (Symulacja)</span>
                        <span class="btn-loading" style="display:none;">Przetwarzanie płatności...</span>
                    </button>
                </div>
                <?php elseif ($order->payment_status === 'paid'): ?>
                <div class="payment-success">
                    <p class="success-message">✓ Płatność została zrealizowana</p>
                    
                    <div class="shipping-section">
                        <h3>Wysyłka</h3>
                        <?php if ($order->tracking_number): ?>
                        <div class="shipping-info">
                            <p><strong>Numer przesyłki:</strong> <?php echo esc_html($order->tracking_number); ?></p>
                            <?php if ($order->label_url): ?>
                            <p><a href="<?php echo esc_url($order->label_url); ?>" target="_blank" class="download-label-btn">📄 Pobierz etykietę wysyłkową</a></p>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <button id="generate-shipment" class="shipment-btn" data-order="<?php echo esc_attr($order->order_id); ?>">
                            <span class="btn-text">Wygeneruj etykietę wysyłkową</span>
                            <span class="btn-loading" style="display:none;">Generowanie...</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="next-steps">
                <h3>Dalsze kroki</h3>
                <ol>
                    <li>Dokonaj płatności klikając przycisk powyżej</li>
                    <li>Po płatności zostanie wygenerowana etykieta wysyłkowa</li>
                    <li>Wydrukuj etykietę i naklej na paczkę</li>
                    <li>Nadaj paczkę w wybranym punkcie InPost</li>
                </ol>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle payment simulation
            $('#simulate-payment').on('click', function() {
                var $btn = $(this);
                var $btnText = $btn.find('.btn-text');
                var $btnLoading = $btn.find('.btn-loading');
                var orderId = $btn.data('order');
                
                $btn.prop('disabled', true);
                $btnText.hide();
                $btnLoading.show();
                
                $.ajax({
                    url: repairOrderAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'simulate_payment',
                        order_id: orderId,
                        nonce: repairOrderAjax.frontend_nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload(); // Reload to show updated status
                        } else {
                            alert('Błąd płatności: ' + response.data);
                            $btn.prop('disabled', false);
                            $btnText.show();
                            $btnLoading.hide();
                        }
                    },
                    error: function() {
                        alert('Wystąpił błąd podczas przetwarzania płatności');
                        $btn.prop('disabled', false);
                        $btnText.show();
                        $btnLoading.hide();
                    }
                });
            });
            
            // Handle shipment generation
            $('#generate-shipment').on('click', function() {
                var $btn = $(this);
                var $btnText = $btn.find('.btn-text');
                var $btnLoading = $btn.find('.btn-loading');
                var orderId = $btn.data('order');
                
                $btn.prop('disabled', true);
                $btnText.hide();
                $btnLoading.show();
                
                $.ajax({
                    url: repairOrderAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'generate_shipment',
                        order_id: orderId,
                        nonce: repairOrderAjax.frontend_nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload(); // Reload to show tracking info
                        } else {
                            alert('Błąd generowania etykiety: ' + response.data);
                            $btn.prop('disabled', false);
                            $btnText.show();
                            $btnLoading.hide();
                        }
                    },
                    error: function() {
                        alert('Wystąpił błąd podczas generowania etykiety');
                        $btn.prop('disabled', false);
                        $btnText.show();
                        $btnLoading.hide();
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    private function get_status_label($status, $type) {
        $labels = array(
            'payment' => array(
                'pending' => 'Oczekuje',
                'paid' => 'Opłacone',
                'failed' => 'Błąd płatności'
            ),
            'repair' => array(
                'received' => 'Odebrane',
                'in_progress' => 'W trakcie naprawy',
                'completed' => 'Ukończone',
                'shipped' => 'Wysłane'
            )
        );
        
        return isset($labels[$type][$status]) ? $labels[$type][$status] : $status;
    }
    
    public function handle_order_submission() {
        check_ajax_referer('repair_order_frontend_nonce', 'nonce');
        
        $required_fields = array('customer_name', 'customer_email', 'customer_phone', 'return_locker', 'service_description', 'service_price');
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error('Pole ' . $field . ' jest wymagane');
                return;
            }
        }
        
        $data = array(
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_email' => sanitize_email($_POST['customer_email']),
            'customer_phone' => sanitize_text_field($_POST['customer_phone']),
            'return_locker' => sanitize_text_field($_POST['return_locker']),
            'service_description' => sanitize_text_field($_POST['service_description']),
            'service_price' => floatval($_POST['service_price']),
            'payment_status' => 'pending',
            'repair_status' => 'received'
        );
        
        $existing_order_id = isset($_POST['order_id']) ? sanitize_text_field($_POST['order_id']) : '';
        
        if (!empty($existing_order_id)) {
            // Update existing order
            $existing_order = RepairOrderDatabase::get_order_by_id($existing_order_id);
            if ($existing_order) {
                RepairOrderDatabase::update_order($existing_order_id, $data);
                $order_id = $existing_order_id;
            } else {
                wp_send_json_error('Zamówienie nie zostało znalezione');
                return;
            }
        } else {
            // Create new order
            $order_id = 'RO-' . date('Ymd') . '-' . wp_generate_password(8, false);
            $data['order_id'] = $order_id;
            
            if (isset($_POST['estimate_id']) && !empty($_POST['estimate_id'])) {
                $data['estimate_id'] = sanitize_text_field($_POST['estimate_id']);
            }
            
            $result = RepairOrderDatabase::insert_order($data);
            if (!$result) {
                wp_send_json_error('Błąd podczas tworzenia zamówienia');
                return;
            }
        }
        
        $sandbox_mode = get_option('repair_order_sandbox_mode', 1);
        
        if ($sandbox_mode) {
            // In sandbox mode, redirect to confirmation page with payment simulation
            $redirect_url = home_url('/zamowienie/potwierdzenie/' . $order_id);
        } else {
            // In production mode, redirect to real payment gateway
            $redirect_url = home_url('/zamowienie/platnosc/' . $order_id);
        }
        
        wp_send_json_success(array(
            'order_id' => $order_id,
            'redirect_url' => $redirect_url,
            'message' => 'Zamówienie zostało złożone pomyślnie'
        ));
    }
    
    public function simulate_payment() {
        check_ajax_referer('repair_order_frontend_nonce', 'nonce');

        $order_id = sanitize_text_field($_POST['order_id']);

        $order = RepairOrderDatabase::get_order_by_id($order_id);
        if (!$order) {
            wp_send_json_error('Zamówienie nie zostało znalezione');
        }

        $already_paid = ($order->payment_status === 'paid');

        // Simulate payment processing delay
        sleep(2);

        if (!$already_paid) {
            RepairOrderDatabase::update_order($order_id, array(
                'payment_status' => 'paid'
            ));
            $order = RepairOrderDatabase::get_order_by_id($order_id);
        }

        $shipment_data = array('tracking_number' => '', 'label_url' => '');

        if ($this->should_use_sandbox_mode()) {
            $shipment_data = RepairOrderSandboxHelper::generate_simulated_shipment($order);
            $order = RepairOrderDatabase::get_order_by_id($order_id);
        }

        if (!$already_paid) {
            $this->send_payment_confirmation_email($order);
        }

        if (!empty($shipment_data['tracking_number'])) {
            $this->send_shipping_notification_email($order, $shipment_data['tracking_number'], $shipment_data['label_url']);
        }

        wp_send_json_success(array(
            'message' => 'Płatność została zrealizowana pomyślnie',
            'redirect_url' => home_url('/zamowienie/potwierdzenie/' . $order_id),
            'tracking_number' => $shipment_data['tracking_number'] ?? '',
            'label_url' => $shipment_data['label_url'] ?? ''
        ));
    }

    public function generate_shipment() {
        check_ajax_referer('repair_order_frontend_nonce', 'nonce');

        $order_id = sanitize_text_field($_POST['order_id']);
        $order = RepairOrderDatabase::get_order_by_id($order_id);

        if (!$order) {
            wp_send_json_error('Zamówienie nie zostało znalezione');
        }

        if ($order->payment_status !== 'paid') {
            wp_send_json_error('Zamówienie nie zostało opłacone');
        }

        $shipment_data = array('tracking_number' => '', 'label_url' => '');

        if ($this->should_use_sandbox_mode()) {
            $shipment_data = RepairOrderSandboxHelper::generate_simulated_shipment($order);
        } else {
            $api_handler = new RepairOrderAPIHandler();
            $shipment_data = $api_handler->create_inpost_shipment($order);

            if (is_wp_error($shipment_data)) {
                wp_send_json_error($shipment_data->get_error_message());
            }
        }

        $order = RepairOrderDatabase::get_order_by_id($order_id);

        if (!empty($shipment_data['tracking_number'])) {
            $this->send_shipping_notification_email($order, $shipment_data['tracking_number'], $shipment_data['label_url'] ?? '');

            wp_send_json_success(array(
                'tracking_number' => $shipment_data['tracking_number'],
                'label_url' => $shipment_data['label_url'] ?? '',
                'message' => 'Etykieta została wygenerowana'
            ));
        }

        wp_send_json_error('Nie udało się wygenerować etykiety');
    }

    private function should_use_sandbox_mode() {
        $sandbox_mode = intval(get_option('repair_order_sandbox_mode', 1));
        $api_token = trim((string) get_option('repair_order_inpost_api_token'));
        $organization_id = trim((string) get_option('repair_order_inpost_organization_id'));

        return $sandbox_mode === 1 || empty($api_token) || empty($organization_id);
    }
    
    private function send_payment_confirmation_email($order) {
        $subject = 'Potwierdzenie płatności - Zamówienie #' . $order->order_id;
        $message = "Dziękujemy za dokonanie płatności.\n\n";
        $message .= "Numer zamówienia: " . $order->order_id . "\n";
        $message .= "Usługa: " . $order->service_description . "\n";
        $message .= "Kwota: " . $order->service_price . " zł\n\n";
        $message .= "Status zamówienia możesz sprawdzić pod adresem:\n";
        $message .= home_url('/zamowienie/' . $order->order_id);
        
        wp_mail($order->customer_email, $subject, $message);
    }
    
    private function send_shipping_notification_email($order, $tracking_number, $label_url) {
        $subject = 'Etykieta wysyłkowa - Zamówienie #' . $order->order_id;
        $message = "Twoje zamówienie zostało przygotowane do wysyłki.\n\n";
        $message .= "Numer przesyłki: " . $tracking_number . "\n";
        $message .= "Etykieta wysyłkowa: " . $label_url . "\n\n";
        $message .= "Paczkomat docelowy: " . $order->return_locker;
        
        wp_mail($order->customer_email, $subject, $message);
    }
}
?>

<?php
if (!defined('ABSPATH')) {
    exit;
}

class RepairOrderAPIHandler {
    
    private $api_base_url;
    private $api_token;
    private $organization_id;
    
    public function __construct() {
        $sandbox_mode = get_option('repair_order_sandbox_mode', 1);
        $this->api_base_url = $sandbox_mode ? 'https://sandbox-api-shipx-pl.easypack24.net/v1/' : 'https://api-shipx-pl.easypack24.net/v1/';
        $this->api_token = get_option('repair_order_inpost_api_token');
        $this->organization_id = get_option('repair_order_inpost_organization_id');
    }

    public function create_inpost_shipment($order) {
        if (is_string($order) || is_numeric($order)) {
            $order = RepairOrderDatabase::get_order_by_id($order);
        }

        if (!$order) {
            return new WP_Error('repair_order_not_found', 'Zamówienie nie zostało znalezione');
        }

        if ($order->payment_status !== 'paid') {
            return new WP_Error('repair_order_not_paid', 'Zamówienie nie zostało opłacone');
        }

        if (empty($this->api_token) || empty($this->organization_id)) {
            return new WP_Error('repair_order_missing_credentials', 'Brak konfiguracji API InPost');
        }

        $shipment_data = array(
            'receiver' => array(
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone
            ),
            'sender' => array(
                'name' => get_option('repair_order_recipient_name'),
                'email' => get_option('repair_order_recipient_email'),
                'phone' => get_option('repair_order_recipient_phone')
            ),
            'parcels' => array(
                array(
                    'template' => 'small'
                )
            ),
            'service' => 'inpost_locker_standard',
            'delivery_point' => array(
                'name' => $order->return_locker
            ),
            'reference' => $order->order_id,
            'comments' => 'Zwrot po naprawie: ' . $order->service_description
        );

        $response = $this->make_api_request('organizations/' . $this->organization_id . '/shipments', 'POST', $shipment_data);

        if (!$response || empty($response['id'])) {
            return new WP_Error('repair_order_shipment_failed', 'Błąd podczas tworzenia przesyłki');
        }

        $update_data = array(
            'inpost_shipment_id' => $response['id'],
            'repair_status' => 'shipped'
        );

        if (!empty($response['tracking_number'])) {
            $update_data['tracking_number'] = $response['tracking_number'];
        }

        RepairOrderDatabase::update_order($order->order_id, $update_data);

        $label_url = '';
        $label_response = $this->make_api_request('shipments/' . $response['id'] . '/label', 'GET');

        if ($label_response && !empty($label_response['url'])) {
            $label_url = $label_response['url'];
            RepairOrderDatabase::update_order($order->order_id, array(
                'label_url' => $label_url
            ));
        }

        return array(
            'tracking_number' => $update_data['tracking_number'] ?? '',
            'label_url' => $label_url
        );
    }
    
    private function make_api_request($endpoint, $method = 'GET', $data = null) {
        $url = $this->api_base_url . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );
        
        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            error_log('InPost API Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code >= 200 && $status_code < 300) {
            return $decoded;
        } else {
            error_log('InPost API Error: HTTP ' . $status_code . ' - ' . $body);
            return false;
        }
    }
}
?>

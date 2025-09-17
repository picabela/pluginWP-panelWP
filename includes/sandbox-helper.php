<?php
if (!defined('ABSPATH')) {
    exit;
}

class RepairOrderSandboxHelper {

    /**
     * Generate a simulated shipment with tracking number and local label file.
     *
     * @param object $order Order object from RepairOrderDatabase::get_order_by_id
     * @return array|false Array with tracking_number and label_url or false on failure
     */
    public static function generate_simulated_shipment($order) {
        if (!$order) {
            return false;
        }

        if (!empty($order->tracking_number) && !empty($order->label_url)) {
            return array(
                'tracking_number' => $order->tracking_number,
                'label_url'       => $order->label_url,
            );
        }

        $tracking_number = 'SIM' . strtoupper(wp_generate_password(10, false, false));
        $label_url = '';

        $upload_dir = wp_upload_dir();

        if (empty($upload_dir['error'])) {
            $labels_dir = trailingslashit($upload_dir['basedir']) . 'repair-order-labels/';

            if (wp_mkdir_p($labels_dir)) {
                $label_filename = 'label-' . sanitize_file_name($order->order_id) . '.html';
                $label_path = $labels_dir . $label_filename;

                $label_content = self::build_label_template($order, $tracking_number);

                if (@file_put_contents($label_path, $label_content) !== false) {
                    $label_url = trailingslashit($upload_dir['baseurl']) . 'repair-order-labels/' . $label_filename;
                }
            }
        }

        $update_data = array(
            'tracking_number' => $tracking_number,
            'repair_status'   => 'shipped',
        );

        if ($label_url) {
            $update_data['label_url'] = $label_url;
        }

        RepairOrderDatabase::update_order($order->order_id, $update_data);

        return array(
            'tracking_number' => $tracking_number,
            'label_url'       => $label_url,
        );
    }

    /**
     * Build simple HTML content for the simulated shipping label.
     *
     * @param object $order
     * @param string $tracking_number
     * @return string
     */
    private static function build_label_template($order, $tracking_number) {
        $recipient_name  = get_option('repair_order_recipient_name', '');
        $recipient_email = get_option('repair_order_recipient_email', '');
        $recipient_phone = get_option('repair_order_recipient_phone', '');

        $generated_at = wp_date('d.m.Y H:i');

        $html  = '<!DOCTYPE html><html lang="pl"><head><meta charset="UTF-8" />';
        $html .= '<title>Etykieta wysyłkowa - ' . esc_html($order->order_id) . '</title>';
        $html .= '<style>body{font-family:Arial,sans-serif;background:#f5f5f5;padding:20px;}';
        $html .= '.label{max-width:600px;margin:0 auto;background:#fff;border:2px solid #333;padding:24px;}';
        $html .= '.label h1{font-size:20px;margin-bottom:16px;}';
        $html .= '.section{margin-top:20px;}';
        $html .= '.section h2{font-size:16px;margin-bottom:8px;}';
        $html .= '.info p{margin:4px 0;font-size:14px;}';
        $html .= '.tracking{font-size:18px;font-weight:bold;margin-top:12px;}';
        $html .= '.meta{margin-top:24px;font-size:12px;color:#555;}';
        $html .= '</style></head><body>';
        $html .= '<div class="label">';
        $html .= '<h1>Etykieta wysyłkowa (symulacja)</h1>';
        $html .= '<p class="tracking">Numer przesyłki: ' . esc_html($tracking_number) . '</p>';
        $html .= '<div class="section info">';
        $html .= '<h2>Nadawca</h2>';
        $html .= '<p>' . esc_html($order->customer_name) . '</p>';
        $html .= '<p>' . esc_html($order->customer_email) . '</p>';
        $html .= '<p>' . esc_html($order->customer_phone) . '</p>';
        $html .= '</div>';
        $html .= '<div class="section info">';
        $html .= '<h2>Odbiorca</h2>';
        $html .= '<p>' . esc_html($recipient_name) . '</p>';
        $html .= '<p>' . esc_html($recipient_email) . '</p>';
        $html .= '<p>' . esc_html($recipient_phone) . '</p>';
        $html .= '</div>';
        $html .= '<div class="section info">';
        $html .= '<h2>Szczegóły przesyłki</h2>';
        $html .= '<p>Numer zamówienia: ' . esc_html($order->order_id) . '</p>';
        $html .= '<p>Usługa: ' . esc_html($order->service_description) . '</p>';
        $html .= '<p>Paczkomat zwrotny: ' . esc_html($order->return_locker) . '</p>';
        $html .= '</div>';
        $html .= '<div class="meta">';
        $html .= '<p>Etykieta wygenerowana automatycznie w trybie symulacji.</p>';
        $html .= '<p>Data wygenerowania: ' . esc_html($generated_at) . '</p>';
        $html .= '</div>';
        $html .= '</div></body></html>';

        return $html;
    }
}

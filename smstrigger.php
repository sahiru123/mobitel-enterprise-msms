<?php

class SMSTrigger
{
    /*
     * Declare setting prefix and other values.
     */

    private $prefix = 'sms_notifier_woo_';
    private $password, $userId, $sendId, $adminRecipients;
    private $yesAdminMsg;
    private $contentDefault, $contentAdmin;

    /*
     * Initialize values.
     */

    public function __construct()
    {
        /*
         * Get SMS configuration settings dynamically from fields.
         */
        $this->password = get_option($this->prefix . 'password');
        $this->userId = get_option($this->prefix . 'user_id');
        $this->sendId = get_option($this->prefix . 'from_id');
        $this->adminRecipients = get_option($this->prefix . 'admin_sms_recipients');
        $this->yesAdminMsg = get_option($this->prefix . 'enable_admin_sms') == 'yes';
        $this->contentDefault = get_option($this->prefix . 'default_sms_template');
        $this->contentAdmin = get_option($this->prefix . 'admin_sms_template');

        add_action('woocommerce_order_status_changed', array($this, 'send_sms_for_events'), 11, 3);
        add_action('woocommerce_new_customer_note', array($this, 'send_order_note_sms'));
    }

    public function send_sms_for_events($order_id, $from_status, $to_status)
    {
        if (get_option($this->prefix . 'send_sms_' . $to_status) !== "yes")
            return;
        $this->send_sms($order_id, $to_status);
    }

    public function send_admin_sms_for_woo_new_order($order_id)
    {
        if ($this->yesAdminMsg)
            $this->send_sms($order_id, 'admin-order');
    }

    public function send_order_note_sms($data)
    {
        if (get_option($this->prefix . 'enable_notes_sms') !== "yes")
            return;

        $this->send_sms($data['order_id'], 'new-note', $data['customer_note']);
    }

    public static function shortCode($message, $order_details)
    {
        $order_id = $order_details->get_order_number();

        $order = wc_get_order($order_id);

        $items = $order->get_items();

        $product_titles = array();

        foreach ($items as $item) {
            $product = $item->get_product();
            $product_titles[] = $product->get_name();
        }

        $order_title = implode(', ', $product_titles);

        $tracking_id = $order->get_meta('citypak_tracking_code', true);

        $tracking_id_string = implode(', ', $tracking_id);

        $replacements_string = array(
            '{{shop_name}}' => get_bloginfo('name'),
            '{{order_id}}' => $order_details->get_order_number(),
            '{{order_amount}}' => $order_details->get_total(),
            '{{order_status}}' => ucfirst($order_details->get_status()),
            '{{order_title}}' => $order_title,
            '{{first_name}}' => ucfirst($order_details->billing_first_name),
            '{{last_name}}' => ucfirst($order_details->billing_last_name),
            '{{billing_city}}' => ucfirst($order_details->billing_city),
            '{{customer_phone}}' => $order_details->billing_phone,
            '{{tracking_id}}' => $tracking_id_string,
        );

        return str_replace(array_keys($replacements_string), $replacements_string, $message);
    }



    public static function reformatPhoneNumbers($value)
    {
        $number = preg_replace("/[^0-9]/", "", $value);
        if (strlen($number) == 9) {
            $number = "94" . $number;
        } elseif (strlen($number) == 10 && substr($number, 0, 1) == '0') {
            $number = "94" . ltrim($number, "0");
        } elseif (strlen($number) == 12 && substr($number, 0, 3) == '940') {
            $number = "94" . ltrim($number, "940");
        }
        return $number;
    }

    private function send_sms($order_id, $status, $message_text = '')
    {
        error_log('Sending SMS initiated for order ID: ' . $order_id . ', Status: ' . $status);

        $order_details = wc_get_order($order_id);
        $message = '';

        if ($status == 'admin-order') {
            $message = $this->contentAdmin;
        } elseif ($status == 'new-note') {
            $message_prefix = get_option($this->prefix  . 'note_sms_template');
            $message = $message_prefix .  $message_text;
        } else {
            $message = get_option($this->prefix . $status . '_sms_template');
            if (empty($message))
                $message = $this->contentDefault;
        }

        error_log('Constructed message: ' . $message);

        $message = self::shortCode($message, $order_details);

        error_log('Processed message: ' . $message);

        $pn = ('admin-order' === $status ? $this->adminRecipients : $order_details->get_billing_phone());

        error_log('Phone number(s) to send SMS: ' . $pn);

        $to_numbers = explode(',', $pn);
        foreach ($to_numbers as $numb) {
            if (empty($numb))
                continue;

            $phone = $this->reformatPhoneNumbers($numb);

            error_log('Reformatted phone number: ' . $phone);

            $api_data = array(
                'message' => $message,
                'to' => $phone,
                'from' => $this->sendId,
                'username' => $this->userId,
                'password' => $this->password,
                'messageType' => (int) get_option($this->prefix . 'message_type')
            );

            error_log('API Data: ' . print_r($api_data, true));

            $ch = curl_init('');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $response = curl_exec($ch);
            curl_close($ch);

            error_log('API Response: ' . $response);
        }
    }
}

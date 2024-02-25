<?php

class SMSTrigger
{
    /*
     * Declare setting prefix and other values.
     */

    private $prefix = 'sms_notifier_woo_';
    private $sms_password;
    private $userId;
    private $sendId;
    private $adminRecipients;
    private $yesAdminMsg;
    private $contentDefault;
    private $contentAdmin;

    /*
     * Initialize values.
     */

    public function __construct()
    {
        /*
         * Get SMS configuration settings dynamically from fields.
         */
        $this->sms_password = get_option($this->prefix . 'sms_password');
        $this->userId = get_option($this->prefix . 'user_id');
        $this->sendId = get_option($this->prefix . 'from_id');
        $this->adminRecipients = get_option($this->prefix . 'admin_sms_recipients');
        $this->yesAdminMsg = get_option($this->prefix . 'enable_admin_sms') == 'yes';
        $this->contentDefault = get_option($this->prefix . 'default_sms_template');
        $this->contentAdmin = get_option($this->prefix . 'admin_sms_template');

        add_action('woocommerce_order_status_changed', array($this, 'send_sms_for_events'), 11, 3);
        add_action('woocommerce_new_customer_note', array($this, 'send_order_note_sms'));
        add_action('woocommerce_new_order', array($this, 'send_admin_sms_for_woo_new_order'), 10, 1);

    }

    public function send_sms_for_events($order_id, $from_status, $to_status)
    {
        if (get_option($this->prefix . 'send_sms_' . $to_status) !== "yes")
            return;
        $this->send_sms($order_id, $to_status);
    }

    public function send_admin_sms_for_woo_new_order($order_id)
    {
        error_log('send_admin_sms_for_woo_new_order method called.');
        if ($this->yesAdminMsg) {
            error_log('Sending admin SMS initiated for new order ID: ' . $order_id);
            $this->send_sms($order_id, 'admin-order', ''); // Provide an empty string as the third argument
        } else {
            error_log('Admin SMS sending is disabled. Skipping.');
        }
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

        $tracking_id_string = is_array($tracking_id) ? implode(', ', $tracking_id) : '';

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
            if (empty($message)) {
                $message = $this->contentDefault;
            }
        }

        error_log('Constructed message: ' . $message);

        $message = self::shortCode($message, $order_details);

        error_log('Processed message: ' . $message);

        $pn = ('admin-order' === $status ? $this->adminRecipients : $order_details->get_billing_phone());

        error_log('Phone number(s) to send SMS: ' . $pn);

        $to_numbers = explode(',', $pn);
        foreach ($to_numbers as $numb) {
            if (empty($numb)) {
                continue;
            }

            $phone = $this->reformatPhoneNumbers($numb);

            error_log('Reformatted phone number: ' . $phone);

            include 'ESMSWS.php';

            $username = $this->userId;
            $sms_password = $this->sms_password;
            $alias = $this->sendId;
            $messageToSend = $message;
            $recipients = array($phone);

            //$username = '';
           // $password = 'de!5%84d7}L$';
            // $alias = 'TEST';
            // $messageToSend = 'Test message';
            // $recipients = array('94764154096');

            error_log('Username: ' . $username);
            error_log('Password: ' . $sms_password);
            error_log('Alias: ' . $alias);
            error_log('Message: ' . $messageToSend);
            error_log('Recipients: ' . implode(',', $recipients));

            // Create or check session before sending the message
            $session = createSession('', $username, $sms_password, '');
            if ($session) {
                if (isSession($session)) {
                    $response = sendMessages($session, $alias, $messageToSend, $recipients, 0);
                    if ($response === 151) {
                        $session = renewSession($session);
                        if ($session) {
                            $response = sendMessages($session, $alias, $messageToSend, $recipients, 0);
                            error_log('API Response after session renewal: ' . $response); // Log response after renewal
                        } else {
                            error_log("Failed to renew session.");
                        }
                    } else {
                        // Output the response for other cases.
                        error_log("API response: " . $response); // Log response for other cases
                    }
                } else {
                    error_log("Session is invalid."); // Log invalid session
                }
            } else {
                error_log("Failed to create session."); // Log session creation failure
            }
        }
    }
}

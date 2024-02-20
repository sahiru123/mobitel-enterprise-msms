<?php

class SMSNotifier
{
    public $prefix = 'sms_notifier_woo_';

    public function __construct($baseFile = null)
    {
        $this->init();
    }

    private function init()
    {
        /*
         * Add SMS Notifier to WooCommerce settings.
         */
        $triggerAPI = new SMSTrigger();
        add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_settings_tab_sms', array($this, 'settings_tab'));
        add_action('woocommerce_update_options_settings_tab_sms', array($this, 'update_settings'));

        /*
         * Send new order admin SMS
         */
        add_action('woocommerce_order_status_processing', array($triggerAPI, 'send_admin_sms_for_woo_new_order'), 10, 1);
    }


    public function add_settings_tab($settings_tabs)
    {
        $settings_tabs['settings_tab_sms'] = __('SMS Notifications', $this->prefix);


        return $settings_tabs;
    }


    public function update_settings()
    {
        woocommerce_update_options($this->getFields());
    }

    public function settings_tab()
    {
?>
        <div class="wrap">
            <div class="notice notice-info is-dismissible">
                <p><strong>Plugin Developed by Microweb Global (PVT) LTD</strong></p>
                <p>Digital Empowerment at its Finest</p>
                <img src="https://media.licdn.com/dms/image/D560BAQFiTEsFjMiy4g/company-logo_200_200/0/1682276360757?e=2147483647&v=beta&t=btygzcEDfXABJnHW8hx7DNbzwDE46BefRsjdIsYFkk8" alt="Developer\'s Logo" style="width: 200px; height: auto;" />
                <p>Website: <a href="https://www.microweb.global" target="_blank">www.microweb.global</a></p>
                <p>Email: <a href="mailto:contact@microweb.global">contact@microweb.global</a></p>
                <button type="button" class="notice-dismiss"></button>
            </div>
            <?php
            woocommerce_admin_fields($this->getFields());
            ?>
        </div>
<?php
    }



    private function getFields()
    {

        $all_statuses = wc_get_order_statuses();

        /*
         * 
         * Customer Notifications
         * 
         */


        $fields[] = array(
            'title' => 'Notifications for Customer',
            'type' => 'title',
            'desc' => 'Send SMS to customer\'s mobile phone. Will be sent to the phone number which customer is providing while checkout process.',
            'id' => $this->prefix . 'customersettings'
        );

        $fields[] = array(
            'title' => 'Default Message',
            'id' => $this->prefix . 'default_sms_template',
            'desc_tip' => __('This message will be sent by default if there are no any text in the following event message fields.', $this->prefix),
            'default' => __('Your order #{{order_id}} "{{order_title}}" is now {{order_status}}. Tracking ID: {{tracking_id}}. Thank you for shopping at {{shop_name}}.', $this->prefix),
            'type' => 'textarea',
            'css' => 'min-width:500px;min-height:80px;'
        );



        foreach ($all_statuses as $key => $val) {
            $key = str_replace("wc-", "", $key);
            $fields[] = array(
                'title' => $val,
                'desc' => 'Enable "' . $val . '" status alert',
                'id' => $this->prefix . 'send_sms_' . $key,
                'default' => 'yes',
                'type' => 'checkbox',
            );
            $fields[] = array(
                'id' => $this->prefix . $key . '_sms_template',
                'type' => 'textarea',
                'placeholder' => 'SMS Content for the ' . $val . ' event',
                'css' => 'min-width:500px;margin-top:-25px;min-height:80px;'
            );
        }

        /*
         * 
         * Admin notifications
         * 
         */

        $fields[] = array('type' => 'sectionend', 'id' => $this->prefix . 'adminsettings');
        $fields[] = array(
            'title' => 'Notification for Admin',
            'type' => 'title',
            'desc' => 'Enable admin notifications for new customer orders.',
            'id' => $this->prefix . 'adminsettings'
        );

        $fields[] = array(
            'title' => 'Receive Admin Notifications',
            'id' => $this->prefix . 'enable_admin_sms',
            'desc' => 'Enable admin notifications for new customer orders.',
            'default' => 'no',
            'type' => 'checkbox'
        );
        $fields[] = array(
            'title' => 'Admin Mobile Number',
            'id' => $this->prefix . 'admin_sms_recipients',
            'desc' => 'Enter admin mobile numbers. You can use multiple numbers by separating with a comma.<br> Example: 0704881414, 0771234568.',
            'desc_tip' => 'Enter admin mobile numbers. You can use multiple numbers by separating with a comma.<br> Example: 0704881414, 0771234578.',
            'default' => '',
            'type' => 'text'
        );
        $fields[] = array(
            'title' => 'Message',
            'id' => $this->prefix . 'admin_sms_template',
            'desc_tip' => 'Customization tags for new order SMS: {{shop_name}}, {{order_id}}, {{order_amount}}.',
            'css' => 'min-width:500px;',
            'default' => 'You have a new customer order for {{shop_name}}. Order #{{order_id}}, Total Value: {{order_amount}}',
            'type' => 'textarea'
        );

        /*
         * 
         * API Credentials
         * 
         */

        $fields[] = array('type' => 'sectionend', 'id' => $this->prefix . 'apisettings');
        $fields[] = array(
            'title' => __('SMS Settings', $this->prefix),
            'type' => 'title',
            'desc' => 'Provide following details for your SMS service.',
            'id' => $this->prefix . 'sms_settings'
        );

        $fields[] = array(
            'title' => __('Username', $this->prefix),
            'id' => $this->prefix . 'user_id',
            'desc_tip' => __('Username for accessing the SMS service.', $this->prefix),
            'type' => 'text',
            'css' => 'min-width:300px;',
        );

        $fields[] = array(
            'title' => __('Alias', $this->prefix),
            'id' => $this->prefix . 'from_id',
            'desc_tip' => __('Alias Name for  sending SMS.', $this->prefix),
            'type' => 'text',
            'css' => 'min-width:300px;',
        );
        $fields[] = array(
            'title' => __('Password', $this->prefix),
            'id' => $this->prefix . 'password',
            'desc_tip' => __('Password for accessing the SMS service.', $this->prefix),
            'type' => 'password',
            'css' => 'min-width:300px;',
        );
        $fields[] = array(
            'title' => __('Message Type', $this->prefix),
            'id' => $this->prefix . 'message_type',
            'desc_tip' => __('Enter 1 for Promotional message and 0 for Non-Promotional message.', $this->prefix),
            'type' => 'text',
            'css' => 'min-width:300px;',
        );
        $fields[] = array('type' => 'sectionend', 'id' => $this->prefix . 'customersettings');


        $avbShortcodes = array(
            '{{first_name}}' => "First name of the customer.",
            '{{last_name}}' => "Last name of the customer.",
            '{{shop_name}}' => 'Your shop name (' . get_bloginfo('name') . ').',
            '{{order_id}}' => 'The order ID.',
            '{{order_amount}}' => "Current order amount.",
            '{{order_status}}' => 'Current order status (Pending, Failed, Processing, etc...).',
            '{{billing_city}}' => 'The city in the customer billing address (If available).',
            '{{customer_phone}}' => 'Customer mobile number (If given).',
            '{{tracking_id}}' => 'Tracking ID of the order.',
        );


        $shortcode_desc = '';
        foreach ($avbShortcodes as $handle => $description) {
            $shortcode_desc .= '<b>' . $handle . '</b> - ' . $description . '<br>';
        }

        $fields[] = array(
            'title' => __(' Placeholders', $this->prefix),
            'type' => 'title',
            'desc' => 'use these placeholders in msges body content above. <br><br>' . $shortcode_desc,
            'id' => $this->prefix . 'notifylk_settings'
        );

        return $fields;
    }
}

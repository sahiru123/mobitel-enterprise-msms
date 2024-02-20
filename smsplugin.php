<?php
/*
Plugin Name: SMS Notification Plugin
Version: 1.0
Author URI:        https://www.microweb.global/
Author: Microweb Global
Description: SMS notifications for WooCommerce orders.
*/

require_once(plugin_dir_path(__FILE__) . 'smsnotifier.php');

require_once(plugin_dir_path(__FILE__) . 'smstrigger.php');

$sms_notifier = new SMSNotifier();

add_action('woocommerce_init', function() {
    $sms_trigger = new SMSTrigger();
});
function remove_all_banners() {
    remove_all_actions('admin_notices');
}

add_action('admin_init', 'remove_all_banners');


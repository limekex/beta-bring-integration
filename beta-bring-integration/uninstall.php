<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove saved options
$opts = [
    'bbi_uid','bbi_api_key','bbi_customer_no','bbi_test_mode','bbi_default_preset_key','bbi_presets_json',
    'bbi_sender_name','bbi_sender_org','bbi_sender_phone','bbi_sender_email','bbi_sender_address1','bbi_sender_address2','bbi_sender_postcode','bbi_sender_city','bbi_sender_country'
];

foreach ( $opts as $o ) {
    delete_option( $o );
}

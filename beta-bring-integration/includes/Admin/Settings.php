<?php
namespace BeTA\Bring\Admin;

use BeTA\Bring\Model\SettingsModel;

class Settings {
    public static function init(): void {
        add_filter( 'woocommerce_get_sections_shipping', [ __CLASS__, 'add_section' ] );
        add_filter( 'woocommerce_get_settings_shipping', [ __CLASS__, 'get_settings' ], 10, 2 );
    }

    public static function add_section( array $sections ): array {
        $sections['bbi'] = __( 'BeTA Bring', 'bbi' );
        return $sections;
    }

    public static function get_settings( array $settings, string $current_section ): array {
        if ( 'bbi' !== $current_section ) {
            return $settings;
        }

        $defaults = self::get_defaults();

        $fields = [
            [ 'title' => __( 'BeTA Bring settings', 'bbi' ), 'type' => 'title', 'id' => 'bbi_options' ],
            [
                'title'    => __( 'Mybring UID', 'bbi' ),
                'id'       => 'bbi_uid',
                'type'     => 'text',
                'desc'     => __( 'Your Mybring API user ID (e-mail address). Found in your Mybring profile under API credentials.', 'bbi' ),
                'desc_tip' => true,
                'default'  => $defaults['bbi_uid'],
                'autoload' => false,
            ],
            [
                'title'    => __( 'API Key', 'bbi' ),
                'id'       => 'bbi_api_key',
                'type'     => 'password',
                'desc'     => __( 'Your Mybring API key. Found in your Mybring profile under API credentials. Keep this secret.', 'bbi' ),
                'desc_tip' => true,
                'default'  => $defaults['bbi_api_key'],
            ],
            [
                'title'    => __( 'Customer number', 'bbi' ),
                'id'       => 'bbi_customer_no',
                'type'     => 'text',
                'desc'     => __( 'Your Bring customer number used for billing shipments. Provided by Bring when you set up a customer agreement.', 'bbi' ),
                'desc_tip' => true,
            ],
            [ 'type' => 'sectionend', 'id' => 'bbi_options' ],

            [ 'title' => __( 'Sender details', 'bbi' ), 'type' => 'title', 'id' => 'bbi_sender' ],
            [
                'title'    => __( 'Name', 'bbi' ),
                'id'       => 'bbi_sender_name',
                'type'     => 'text',
                'desc'     => __( 'Full name or company name shown as the sender on shipping labels.', 'bbi' ),
                'desc_tip' => true,
            ],
            [
                'title'    => __( 'Organization', 'bbi' ),
                'id'       => 'bbi_sender_org',
                'type'     => 'text',
                'desc'     => __( 'Organization or VAT number of the sender. Used for business shipments.', 'bbi' ),
                'desc_tip' => true,
            ],
            [
                'title'    => __( 'Phone', 'bbi' ),
                'id'       => 'bbi_sender_phone',
                'type'     => 'text',
                'desc'     => __( 'Contact phone number for the sender. Bring may use this for delivery notifications or exceptions.', 'bbi' ),
                'desc_tip' => true,
            ],
            [
                'title'    => __( 'Email', 'bbi' ),
                'id'       => 'bbi_sender_email',
                'type'     => 'email',
                'desc'     => __( 'Contact e-mail address for the sender. Used for shipment-related correspondence from Bring.', 'bbi' ),
                'desc_tip' => true,
            ],
            [
                'title'    => __( 'Address 1', 'bbi' ),
                'id'       => 'bbi_sender_address1',
                'type'     => 'text',
                'desc'     => __( "Street address line 1 of the sender's pickup location (e.g. \"Storgata 1\").", 'bbi' ),
                'desc_tip' => true,
            ],
            [
                'title'    => __( 'Address 2', 'bbi' ),
                'id'       => 'bbi_sender_address2',
                'type'     => 'text',
                'desc'     => __( 'Optional second address line (apartment number, building, c/o, etc.).', 'bbi' ),
                'desc_tip' => true,
            ],
            [
                'title'    => __( 'Postcode', 'bbi' ),
                'id'       => 'bbi_sender_postcode',
                'type'     => 'text',
                'desc'     => __( "Postal code of the sender's location. Used as the \"from\" postcode when querying live Bring shipping rates.", 'bbi' ),
                'desc_tip' => true,
            ],
            [
                'title'    => __( 'City', 'bbi' ),
                'id'       => 'bbi_sender_city',
                'type'     => 'text',
                'desc'     => __( "City of the sender's location.", 'bbi' ),
                'desc_tip' => true,
            ],
            [
                'title'    => __( 'Country', 'bbi' ),
                'id'       => 'bbi_sender_country',
                'type'     => 'text',
                'desc'     => __( 'Two-letter ISO 3166-1 country code of the sender (e.g. NO, SE, DK, FI).', 'bbi' ),
                'desc_tip' => true,
                'default'  => 'NO',
            ],
            [ 'type' => 'sectionend', 'id' => 'bbi_sender' ],

            [ 'title' => __( 'Behaviour', 'bbi' ), 'type' => 'title', 'id' => 'bbi_behaviour' ],
            [
                'title'    => __( 'Test mode', 'bbi' ),
                'id'       => 'bbi_test_mode',
                'type'     => 'checkbox',
                'desc'     => __( 'When enabled, shipments are submitted with the Bring test indicator — no real labels or charges are created. Also allows booking without valid API credentials.', 'bbi' ),
                'desc_tip' => true,
                'default'  => 'no',
            ],
            [
                'title'    => __( 'Default preset key', 'bbi' ),
                'id'       => 'bbi_default_preset_key',
                'type'     => 'text',
                'desc'     => __( 'The key of the preset pre-selected on the order booking screen and used for bulk booking. Must match a key defined in the Presets JSON below.', 'bbi' ),
                'desc_tip' => true,
            ],
            [
                'title'            => __( 'Presets (JSON)', 'bbi' ),
                'id'               => 'bbi_presets_json',
                'type'             => 'textarea',
                'css'              => 'min-height:200px;',
                'desc'             => __( 'JSON object defining your shipping presets. Each key is a unique preset identifier. Each value must include: label (display name), serviceId (Bring product code), vas (array of value-added service codes), packageTemplate (length/width/height in cm), and maxWeightKg.', 'bbi' ),
                'desc_tip'         => true,
                'default'          => self::default_presets_json(),
                'sanitize_callback' => [ __CLASS__, 'sanitize_presets_json' ],
            ],
            [ 'type' => 'sectionend', 'id' => 'bbi_behaviour' ],

            [ 'type' => 'sectionend', 'id' => 'bbi_end' ],
        ];

        return $fields;
    }

    public static function get_defaults(): array {
        return [
            'bbi_uid' => '',
            'bbi_api_key' => '',
            'bbi_customer_no' => '',
        ];
    }

    public static function default_presets_json(): string {
        return json_encode( [
            'pakke_i_postkassen' => [
                'label' => 'Pakke i postkassen',
                'serviceId' => 'PAKKE_I_POSTKASSEN',
                'vas' => [],
                'packageTemplate' => [ 'length' => 30, 'width' => 20, 'height' => 10 ],
                'maxWeightKg' => 2.0,
            ],
            'hent_i_butikk' => [
                'label' => 'Hent i butikk',
                'serviceId' => 'SERVICEPAKKE',
                'vas' => [ 'NOTIFY_RECIPIENT_SMS' ],
                'packageTemplate' => [ 'length' => 60, 'width' => 35, 'height' => 35 ],
                'maxWeightKg' => 35.0,
            ],
            'hjemlevering' => [
                'label' => 'Hjemlevering',
                'serviceId' => 'PA_DOREN',
                'vas' => [],
                'packageTemplate' => [ 'length' => 120, 'width' => 40, 'height' => 40 ],
                'maxWeightKg' => 35.0,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }

    public static function sanitize_presets_json( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $decoded = json_decode( $value, true );
        if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
            add_settings_error( 'bbi_presets_json', 'bbi_presets_json_invalid', __( 'Presets JSON is invalid. Please correct.', 'bbi' ), 'error' );
            // Return previous value to avoid overwriting with invalid data
            return get_option( 'bbi_presets_json', '' );
        }

        return wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }
}

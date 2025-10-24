<?php
namespace BeTA\Bring\Model;

class SettingsModel {
    public function get_uid(): ?string {
        return get_option( 'bbi_uid', '' ) ?: null;
    }

    public function get_api_key(): ?string {
        return get_option( 'bbi_api_key', '' ) ?: null;
    }

    public function get_customer_no(): ?string {
        return get_option( 'bbi_customer_no', '' ) ?: null;
    }

    public function is_test_mode(): bool {
        return 'yes' === get_option( 'bbi_test_mode', 'no' );
    }

    public function get_default_preset_key(): ?string {
        return get_option( 'bbi_default_preset_key', '' ) ?: null;
    }

    public function get_presets(): array {
        $raw = get_option( 'bbi_presets_json', '' );
        if ( empty( $raw ) ) {
            return [];
        }

        $decoded = json_decode( $raw, true );
        if ( null === $decoded || ! is_array( $decoded ) ) {
            return [];
        }

        return $decoded;
    }

    public function get_preset( string $key ): ?array {
        $presets = $this->get_presets();
        return $presets[ $key ] ?? null;
    }

    public function get_sender_array(): array {
        return [
            'name' => get_option( 'bbi_sender_name', '' ),
            'orgno' => get_option( 'bbi_sender_org', '' ),
            'phone' => get_option( 'bbi_sender_phone', '' ),
            'email' => get_option( 'bbi_sender_email', '' ),
            'address1' => get_option( 'bbi_sender_address1', '' ),
            'address2' => get_option( 'bbi_sender_address2', '' ),
            'postcode' => get_option( 'bbi_sender_postcode', '' ),
            'city' => get_option( 'bbi_sender_city', '' ),
            'country' => get_option( 'bbi_sender_country', 'NO' ),
        ];
    }
}

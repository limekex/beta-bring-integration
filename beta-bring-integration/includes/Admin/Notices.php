<?php
namespace BeTA\Bring\Admin;

class Notices {
    public static function init(): void {
        add_action( 'admin_notices', [ __CLASS__, 'render_notices' ] );
    }

    public static function render_notices(): void {
        settings_errors();
    }
}

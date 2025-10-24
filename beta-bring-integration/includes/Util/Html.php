<?php
namespace BeTA\Bring\Util;

class Html {
    public static function select_options( array $options, $selected = null ): string {
        $html = '';
        foreach ( $options as $value => $label ) {
            $sel = selected( $selected, $value, false );
            $html .= sprintf( '<option value="%s" %s>%s</option>', esc_attr( $value ), $sel, esc_html( $label ) );
        }
        return $html;
    }
}

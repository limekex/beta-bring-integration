<?php
// CLI script to simulate a booking using BookingService in test mode.
require_once __DIR__ . '/../includes/Autoloader.php';
\BeTA\Bring\Autoloader::register( __DIR__ . '/../includes', 'BeTA\\Bring\\' );

// Minimal WP stubs for CLI
if ( ! function_exists( 'get_option' ) ) {
    $GLOBALS['__bbi_test_options'] = [];
    function get_option( $k, $d = null ) { return $GLOBALS['__bbi_test_options'][$k] ?? $d; }
    function update_option( $k, $v ) { $GLOBALS['__bbi_test_options'][$k] = $v; }
}

if ( ! function_exists( 'wc_get_logger' ) ) {
    function wc_get_logger() { return new class { public function info(){} public function error(){} }; }
}

// home_url shim for CLI/test environment
if ( ! function_exists( 'home_url' ) ) {
    function home_url( $path = '' ) {
        $base = 'http://example.test';
        return rtrim( $base, '/' ) . '/' . ltrim( $path, '/' );
    }
}

// Prepare test mode and presets
update_option( 'bbi_test_mode', 'yes' );
$presets = [
    'cli_preset' => [ 'label' => 'CLI Preset', 'serviceId' => 'PAKKE_I_POSTKASSEN', 'vas' => [], 'packageTemplate' => ['length'=>30,'width'=>20,'height'=>10] ]
];
update_option( 'bbi_presets_json', json_encode( $presets ) );

// Create a fake order object similar to tests
class SimpleProduct { public $weight; public function __construct($w){$this->weight=$w;} public function get_weight(){return $this->weight;} }
class SimpleOrder {
    private $id = 999;
    private $items = [];
    public function __construct(){}
    public function add_item($p,$q=1){ $this->items[] = (object)['product'=>$p,'qty'=>$q]; }
    public function get_items(){ $out=[]; foreach($this->items as $it){ $out[] = new class($it){ private $it; public function __construct($it){$this->it=$it;} public function get_product(){return $this->it->product;} public function get_quantity(){return $this->it->qty;} }; } return $out; }
    public function get_id(){ return $this->id; }
    public function get_order_number(){ return (string)$this->id; }
    public function get_formatted_billing_full_name(){ return 'CLI User'; }
    public function get_billing_first_name(){ return 'CLI'; }
    public function get_billing_last_name(){ return 'User'; }
    public function get_billing_phone(){ return '000'; }
    public function get_billing_email(){ return 'cli@example.test'; }
    public function get_billing_address_1(){ return 'CLI Street'; }
    public function get_billing_address_2(){ return ''; }
    public function get_billing_postcode(){ return '0000'; }
    public function get_billing_city(){ return 'City'; }
    public function get_billing_country(){ return 'NO'; }
}

$order = new SimpleOrder();
$order->add_item( new SimpleProduct(1.2), 2 );

$settings = new \BeTA\Bring\Model\SettingsModel();
$preset = $settings->get_preset( 'cli_preset' );
$service = new \BeTA\Bring\API\BookingService( $settings );
$result = $service->book_order( $order, $preset );

echo "Simulated booking result:\n";
echo json_encode( $result->to_array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";

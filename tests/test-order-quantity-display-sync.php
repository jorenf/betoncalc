<?php
/**
 * Standalone regression checks for calculator quantity display in orders.
 *
 * Run: php tests/test-order-quantity-display-sync.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$pass = 0;
$fail = 0;

function check( string $label, bool $condition, string $detail = '' ): void {
    global $pass, $fail;
    if ( $condition ) {
        echo "[PASS] $label\n";
        $pass++;
    } else {
        echo "[FAIL] $label" . ( $detail ? " - $detail" : '' ) . "\n";
        $fail++;
    }
}

function add_action() {
    return true;
}

function add_filter() {
    return true;
}

function __( $text ) {
    return $text;
}

function esc_html__( $text ) {
    return $text;
}

function esc_html( $text ) {
    return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function get_option( $key, $default = false ) {
    return $default;
}

function wc_format_localized_decimal( $value ) {
    return (string) $value;
}

function wc_price( $value ) {
    return 'EUR ' . number_format( (float) $value, 2, '.', '' );
}

function wp_kses_post( $value ) {
    return $value;
}

class WC_Order_Item_Product {
    private $quantity;
    private $meta = array();

    public function __construct( int $quantity = 1 ) {
        $this->quantity = $quantity;
    }

    public function get_quantity() {
        return $this->quantity;
    }

    public function add_meta_data( $key, $value, $unique = false ) {
        $this->meta[ $key ] = $value;
    }

    public function update_meta_data( $key, $value ) {
        $this->meta[ $key ] = $value;
    }

    public function get_meta( $key ) {
        return $this->meta[ $key ] ?? '';
    }
}

class Bossier_Test_Product {
    public function is_type( $type ) {
        return false;
    }
}

require_once dirname( __DIR__ ) . '/frontend/class-order.php';
require_once dirname( __DIR__ ) . '/frontend/class-cart.php';
require_once dirname( __DIR__ ) . '/includes/woopages/class-woopages-helper.php';

function calculator_data_with_stale_quantity(): array {
    return array(
        'calculator_id'       => 123,
        'selections'          => array(
            'qty_field'   => '14',
            'color_field' => '0',
        ),
        'display_data'        => array(
            'qty_field'   => array(
                'label'     => 'Aantal',
                'value'     => '14',
                'raw_value' => 14,
                'type'      => 'quantity',
            ),
            'color_field' => array(
                'label'     => 'Kleur',
                'value'     => 'Grijs',
                'raw_value' => '0',
                'type'      => 'color',
            ),
        ),
        'calculated_price'    => 21.34,
        'calculated_weight'   => 0,
        'breakdown'           => array(),
        'quantity_multiplier' => 14,
    );
}

$order_handler = new \Bossier\Calculator\Frontend\Order();
$order_item    = new \WC_Order_Item_Product( 22 );

$order_handler->save_order_item_meta(
    $order_item,
    'cart-key',
    array(
        'quantity'           => 22,
        'bossier_calculator' => calculator_data_with_stale_quantity(),
    ),
    null
);

$saved_display    = $order_item->get_meta( '_bossier_display_data' );
$saved_selections = $order_item->get_meta( '_bossier_selections' );

check(
    'Order item display data stores the WooCommerce quantity for quantity fields',
    isset( $saved_display['qty_field']['value'], $saved_display['qty_field']['raw_value'] )
        && '22' === (string) $saved_display['qty_field']['value']
        && 22 === (int) $saved_display['qty_field']['raw_value'],
    'Saved display data still contains stale calculator quantity 14.'
);

check(
    'Order item selections and visible Aantal meta are synced to the WooCommerce quantity',
    isset( $saved_selections['qty_field'] )
        && 22 === (int) $saved_selections['qty_field']
        && '22' === (string) $order_item->get_meta( 'Aantal' ),
    'Saved order meta still exposes stale quantity 14.'
);

check(
    'Non-quantity calculator fields stay untouched when quantity is synced',
    isset( $saved_display['color_field']['value'] )
        && 'Grijs' === $saved_display['color_field']['value'],
    'Quantity sync changed unrelated display data.'
);

$existing_order_item = new \WC_Order_Item_Product( 22 );
$existing_order_item->update_meta_data( '_bossier_calculator_id', 123 );
$existing_order_item->update_meta_data( '_bossier_display_data', calculator_data_with_stale_quantity()['display_data'] );

ob_start();
$order_handler->display_admin_order_item_meta( 1, $existing_order_item, null );
$admin_html = ob_get_clean();

check(
    'Admin order calculator details render the actual order item quantity even for stale saved data',
    false !== strpos( $admin_html, '<strong>Aantal:</strong> 22' )
        && false === strpos( $admin_html, '<strong>Aantal:</strong> 14' ),
    'Admin order details still show stale calculator quantity 14.'
);

$cart_handler = new \Bossier\Calculator\Frontend\Cart();
$cart_display = $cart_handler->display_cart_item_data(
    array(),
    array(
        'quantity'           => 22,
        'bossier_calculator' => calculator_data_with_stale_quantity(),
    )
);

$cart_quantity_value = '';
foreach ( $cart_display as $row ) {
    if ( isset( $row['key'] ) && 'Aantal' === $row['key'] ) {
        $cart_quantity_value = $row['value'];
        break;
    }
}

check(
    'Cart item display data renders the current WooCommerce cart quantity',
    '22' === (string) $cart_quantity_value,
    'Cart display still shows stale calculator quantity 14.'
);

$specs = \Bossier\Calculator\WooPages\WooPages_Helper::get_product_specs(
    array(
        'quantity'           => 22,
        'data'               => new \Bossier_Test_Product(),
        'bossier_calculator' => calculator_data_with_stale_quantity(),
    )
);

check(
    'WooPages cart specs render the current WooCommerce cart quantity',
    in_array( '22', $specs, true ) && ! in_array( '14', $specs, true ),
    'WooPages specs still show stale calculator quantity 14.'
);

echo "\n$pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );

<?php
/**
 * Plugin Name: Woocommerce Request a Quote Payment Method
 * Plugin URI: https://choclomedia.com
 * Version: 1.0
 * Author: Choclomedia
 * Author URI: https://choclomedia.com
 * Description: El plugin Woocommerce Request A Quote Payment Method permite a sus clientes solicitar una estimación de la lista de productos en los que están interesados a través de las ordenes regulares de Woocommerce.
 * Text Domain: wrqpm
 * Domain Path: /languages/
 *
 */

// https://stackoverflow.com/questions/17081483/custom-payment-method-in-woocommerce/37631908#37631908?newreg=b25426416a614c00b38ba8eb87da126f
// https://www.skyverge.com/blog/how-to-create-a-simple-woocommerce-payment-gateway/


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

function register_labels_order_status() {
    register_post_status( 'wc-request-a-quote', array(
        'label'                     => _x('Cotización Solicitada', 'woocommerce'),
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'Cotización Solicitada <span class="count">(%s)</span>', 'Cotización Solicitada <span class="count">(%s)</span>' )
    ) );
}
add_action( 'init', 'register_labels_order_status' );


function add_awaiting_shipment_to_order_statuses( $order_statuses ) {
    $new_order_statuses = array();
    foreach ( $order_statuses as $key => $status ) {
        $new_order_statuses[ $key ] = $status;
        if ( 'wc-processing' === $key ) {
            $new_order_statuses['wc-request-a-quote'] = 'Cotización Solicitada';
        }
    }
    return $new_order_statuses;
}
add_filter( 'wc_order_statuses', 'add_awaiting_shipment_to_order_statuses' );

add_action('pre_get_posts', function($query){
    if (is_admin() && $query->is_main_query() && $_GET['post_type'] == 'shop_order'){
        $post_status = $query->query_vars['post_status'];
        $post_status[] = 'wc-request-a-quote';
        $query->set('post_status', $post_status);
    }
});

add_action('admin_head', 'styling_admin_order_list' );
function styling_admin_order_list() {
    //global $pagenow, $post;
	//if( $pagenow != 'edit.php') return; // Exit
	//if( get_post_type($post->ID) != 'shop_order' ) return; // Exit
    ?>
    <style>
		.order-status-dashboard{
			display: -webkit-inline-box;
			display: inline-flex;
			line-height: 2.5em;
			color: #777;
			background: #e5e5e5;
			border-radius: 4px;
			border-bottom: 1px solid rgba(0,0,0,.05);
			cursor: inherit!important;
			white-space: nowrap;
			max-width: 100%;
			min-width: 120px;
			padding: 0 12px;
			margin: 0 auto;
		}

		.order-status-dashboard.status-label-creating,
		.order-status.status-request-a-quote{
    		color: darkorange;
            border-left: 5px solid darkorange;
			font-weight: bold;
        }
		.lk24-dashboard-table thead,
		.lk24-dashboard-table tfoot{
			font-weight: bold;
			background: lightgray;
		}
		.lk24-dashboard-table tr{
			margin-bottom: 8px;
		}
    </style>
    <?php
}

function wrqpm_add_dashboard_widget() {
	wp_add_dashboard_widget(
		'wrqpm_dashboard_order_request_a_quote',         // Widget slug.
		_x('Cotizaciones Solicitadas', 'wrqpm'),         // Title.
		'wrqpm_dashboard_order_label_statues' // Display function.
	);
}
add_action( 'wp_dashboard_setup', 'wrqpm_add_dashboard_widget' );
function wrqpm_dashboard_order_label_statues() {
	$query = new WC_Order_Query( array(
		'limit' => 10,
		'status' => array('request-a-quote'),
		'orderby' => 'date',
		'order' => 'ASC',
		//'return' => 'ids',
	) );
	$orders = $query->get_orders();
	if ($orders){
		echo '<table class="lk24-dashboard-table" width="100%" border="0" align="center">';
		echo '<thead>';
		echo '<tr>';
		echo '<td><strong>#Cotización</strong></td>';
		echo '<td><strong>Fecha</strong></td>';
		echo '<td><strong>Cliente</strong></td>';
		echo '<td align="center"><strong>Total</strong></td>';
		echo '<td>&nbsp;</td>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';
		foreach($orders as $order){
//			var_dump($order->get_status());
			echo '<tr>';
			echo '<td height="40">#'. $order->get_id() .'</td>';
			echo '<td>'. date('d.m.Y', strtotime($order->get_date_created()) ) .'</td>';
			echo '<td>'.$order->get_billing_first_name().' '.$order->get_billing_last_name().'</td>';
			echo '<td align="right">$ '.$order->get_total().'</td>';
			echo '<td align="center"><a href="'. $order->get_edit_order_url().'"><span class="dashicons dashicons-admin-generic"></span></a></td>';
			echo '</tr>';
		}
		echo '</tbody>';
		echo '<tfoot>';
		echo '<tr>';
		echo '<td><strong>#Cotización</strong></td>';
		echo '<td><strong>Fecha</strong></td>';
		echo '<td><strong>Cliente</strong></td>';
		echo '<td align="center"><strong>Total</strong></td>';
		echo '<td>&nbsp;</td>';
		echo '</tr>';
		echo '</tfoot>';
		echo '</table>';
	}
	// https://businessbloomer.com/woocommerce-easily-get-order-info-total-items-etc-from-order-object/
	// https://github.com/woocommerce/woocommerce/wiki/wc_get_orders-and-WC_Order_Query
	// https://www.webhat.in/article/woocommerce-tutorial/how-to-get-order-details-by-order-id/
	// https://docs.woocommerce.com/wc-apidocs/class-WC_Order.html
	// http://woocommerce.wp-a2z.org/
}


// AGREGAR METODO DE PAGO -> SOLICITUD DE Cotizaciones
add_action('plugins_loaded', 'init_custom_gateway_class');
function init_custom_gateway_class(){

    class WC_Gateway_Request extends WC_Payment_Gateway {

        public $domain;

        /**
         * Constructor for the gateway.
         */
        public function __construct() {

            $this->domain = 'wrqpm';

            $this->id                 = 'request-a-quote-method';
            $this->icon               = apply_filters('woocommerce_custom_gateway_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __( 'Solicitud de Cotizaciones', $this->domain );
            $this->method_description = __( 'Permite que los usuarios soliciten Cotizaciones.', $this->domain );

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );
            $this->order_status = $this->get_option( 'order_status', 'wc-request-a-quote' );

            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou_custom', array( $this, 'thankyou_page' ) );

            // Customer Emails
            add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
        }

        /**
         * Initialise Gateway Settings Form Fields.
         */
        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Habilitar', $this->domain ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Habilitar Solicitud de Cotizaciones', $this->domain ),
                    'default' => 'no'
                ),
                'title' => array(
                    'title'       => __( 'Titulo', $this->domain ),
                    'type'        => 'text',
                    'description' => __( '', $this->domain ),
                    'default'     => __( 'Solicitud de Cotizaciones', $this->domain ),
                    'desc_tip'    => true,
                ),
                'order_status' => array(
                    'title'       => __( 'Estado de la Orden', $this->domain ),
                    'type'        => 'select',
                    'class'       => 'wc-enhanced-select',
                    'description' => __( 'Seleccione el Estatus por defecto que tendran las ordenes hechas con este tipo de método.', $this->domain ),
                    'default'     => 'wc-request-a-quote',
                    'desc_tip'    => true,
                    'options'     => wc_get_order_statuses()
                ),
                'description' => array(
                    'title'       => __( 'Descripción', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'La descripción que verán todos los usuario en la vista de Checkout.', $this->domain ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'instructions' => array(
                    'title'       => __( 'Instrucciones', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'Las instrucciones que se verán en la página de agradecimiento y en el contenido del correo.', $this->domain ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
            );
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page() {
            if ( $this->instructions )
                echo wpautop( wptexturize( $this->instructions ) );
        }

        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */
        public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
            if ( $this->instructions && ! $sent_to_admin && 'custom' === $order->payment_method && $order->has_status( 'wc-request-a-quote' ) ) {
                echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
            }
        }

/*        public function payment_fields(){

            if ( $description = $this->get_description() ) {
                echo wpautop( wptexturize( $description ) );
            }

            ?>
            <div id="custom_input">
                <p class="form-row form-row-wide">
                    <label for="mobile" class=""><?php _e('Mobile Number', $this->domain); ?></label>
                    <input type="text" class="" name="mobile" id="mobile" placeholder="" value="">
                </p>
                <p class="form-row form-row-wide">
                    <label for="transaction" class=""><?php _e('Transaction ID', $this->domain); ?></label>
                    <input type="text" class="" name="transaction" id="transaction" placeholder="" value="">
                </p>
            </div>
            <?php
        }*/

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {

            $order = wc_get_order( $order_id );

            $status = 'wc-' === substr( $this->order_status, 0, 3 ) ? substr( $this->order_status, 3 ) : $this->order_status;

            // Set order status
            $order->update_status( $status, __( 'Checkout con Solicitud de Cotización. ', $this->domain ) );

            // Reduce stock levels
            $order->reduce_order_stock();

            // Remove cart
            WC()->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result'    => 'success',
                'redirect'  => $this->get_return_url( $order )
            );
        }
    }
}

add_filter( 'woocommerce_payment_gateways', 'add_custom_gateway_class' );
function add_custom_gateway_class( $methods ) {
    $methods[] = 'WC_Gateway_Request';
    return $methods;
}
/*
add_action('woocommerce_checkout_process', 'process_custom_payment');
function process_custom_payment(){

    if($_POST['payment_method'] != 'custom')
        return;

    if( !isset($_POST['mobile']) || empty($_POST['mobile']) )
        wc_add_notice( __( 'Please add your mobile number', $this->domain ), 'error' );


    if( !isset($_POST['transaction']) || empty($_POST['transaction']) )
        wc_add_notice( __( 'Please add your transaction ID', $this->domain ), 'error' );

}*/

/**
 * Update the order meta with field value
 */
/*
add_action( 'woocommerce_checkout_update_order_meta', 'custom_payment_update_order_meta' );
function custom_payment_update_order_meta( $order_id ) {

    if($_POST['payment_method'] != 'custom')
        return;

    // echo "<pre>";
    // print_r($_POST);
    // echo "</pre>";
    // exit();

    update_post_meta( $order_id, 'mobile', $_POST['mobile'] );
    update_post_meta( $order_id, 'transaction', $_POST['transaction'] );
}

/**
 * Display field value on the order edit page
 */
/*
add_action( 'woocommerce_admin_order_data_after_billing_address', 'custom_checkout_field_display_admin_order_meta', 10, 1 );
function custom_checkout_field_display_admin_order_meta($order){
    $method = get_post_meta( $order->id, '_payment_method', true );
    if($method != 'custom')
        return;

    $mobile = get_post_meta( $order->id, 'mobile', true );
    $transaction = get_post_meta( $order->id, 'transaction', true );

    echo '<p><strong>'.__( 'Mobile Number' ).':</strong> ' . $mobile . '</p>';
    echo '<p><strong>'.__( 'Transaction ID').':</strong> ' . $transaction . '</p>';
}

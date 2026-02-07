<?php
/**
 * Invoice PDF Template.
 *
 * This template is used to generate invoice PDFs.
 * Variables available: $invoice, $order, $company
 *
 * @package Bossier_Calculator_Builder
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title><?php printf( __( 'Factuur %s', 'bossier-calculator' ), $invoice->get_invoice_number() ); ?></title>
	<style>
		<?php include dirname( __FILE__ ) . '/style.css'; ?>
	</style>
</head>
<body>
	<div class="document-wrapper">
		<!-- Header -->
		<table class="header-table">
			<tr>
				<td class="logo-cell">
					<?php echo $invoice->get_logo_html(); ?>
				</td>
				<td class="company-info-cell">
					<div class="company-name"><?php echo esc_html( $company['name'] ); ?></div>
					<div class="company-address"><?php echo nl2br( esc_html( $company['address'] ) ); ?></div>
					<?php if ( ! empty( $company['vat_number'] ) ) : ?>
						<div class="company-detail">BTW Nr: <?php echo esc_html( $company['vat_number'] ); ?></div>
					<?php endif; ?>
					<?php if ( ! empty( $company['coc_number'] ) ) : ?>
						<div class="company-detail">KVK Nr: <?php echo esc_html( $company['coc_number'] ); ?></div>
					<?php endif; ?>
					<?php if ( ! empty( $company['iban'] ) ) : ?>
						<div class="company-detail">IBAN Nr: <?php echo esc_html( $company['iban'] ); ?></div>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<!-- Document Title -->
		<h1 class="document-title"><?php echo esc_html( get_option( 'boost_pdf_invoice_title', __( 'FACTUUR', 'bossier-calculator' ) ) ); ?></h1>

		<!-- Address & Order Info -->
		<table class="info-table">
			<tr>
				<td class="address-cell">
					<div class="address-block">
						<div class="address-label"><?php esc_html_e( 'Factuuradres', 'bossier-calculator' ); ?></div>
						<?php
						// Build structured NAW display
						$billing_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
						$billing_company = $order->get_billing_company();
						$billing_address = $order->get_billing_address_1();
						$billing_address_2 = $order->get_billing_address_2();
						$billing_postcode = $order->get_billing_postcode();
						$billing_city = $order->get_billing_city();
						$billing_country = WC()->countries->countries[ $order->get_billing_country() ] ?? $order->get_billing_country();
						$customer_vat = $invoice->get_customer_vat_number();
						?>
						<?php if ( ! empty( $billing_company ) ) : ?>
							<strong><?php echo esc_html( $billing_company ); ?></strong><br>
						<?php endif; ?>
						<?php if ( ! empty( $billing_name ) ) : ?>
							<?php echo esc_html( $billing_name ); ?><br>
						<?php endif; ?>
						<?php if ( ! empty( $billing_address ) ) : ?>
							<?php echo esc_html( $billing_address ); ?>
							<?php if ( ! empty( $billing_address_2 ) ) : ?>
								<?php echo esc_html( $billing_address_2 ); ?>
							<?php endif; ?>
							<br>
						<?php endif; ?>
						<?php if ( ! empty( $billing_postcode ) || ! empty( $billing_city ) ) : ?>
							<?php echo esc_html( $billing_postcode ); ?> <?php echo esc_html( $billing_city ); ?><br>
						<?php endif; ?>
						<?php if ( ! empty( $billing_country ) ) : ?>
							<?php echo esc_html( $billing_country ); ?>
						<?php endif; ?>
						<?php if ( ! empty( $customer_vat ) ) : ?>
							<div class="customer-vat"><?php esc_html_e( 'BTW-nummer:', 'bossier-calculator' ); ?> <?php echo esc_html( $customer_vat ); ?></div>
						<?php endif; ?>
					</div>
					<?php if ( $order->get_billing_email() ) : ?>
						<div class="customer-email"><?php echo esc_html( $order->get_billing_email() ); ?></div>
					<?php endif; ?>
				</td>
				<td class="order-info-cell">
					<table class="order-details">
						<tr>
							<th><?php _e( 'Factuurnummer:', 'bossier-calculator' ); ?></th>
							<td><?php echo esc_html( $invoice->get_invoice_number() ); ?></td>
						</tr>
						<tr>
							<th><?php _e( 'Bestelnummer:', 'bossier-calculator' ); ?></th>
							<td><?php echo esc_html( $order->get_order_number() ); ?></td>
						</tr>
						<tr>
							<th><?php _e( 'Besteldatum:', 'bossier-calculator' ); ?></th>
							<td><?php echo esc_html( $invoice->format_date( $order->get_date_created()->getTimestamp() ) ); ?></td>
						</tr>
						<tr>
							<th><?php _e( 'Betaalmethode:', 'bossier-calculator' ); ?></th>
							<td><?php echo esc_html( $order->get_payment_method_title() ); ?></td>
						</tr>
					</table>
				</td>
			</tr>
		</table>

		<!-- Products Table -->
		<table class="products-table invoice">
			<thead>
				<tr>
					<th class="product-col"><?php _e( 'Product', 'bossier-calculator' ); ?></th>
					<th class="qty-col"><?php _e( 'Aantal', 'bossier-calculator' ); ?></th>
					<th class="price-col"><?php _e( 'Prijs', 'bossier-calculator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $invoice->get_order_items() as $item ) : ?>
					<tr>
						<td class="product-col">
							<span class="item-name"><?php echo esc_html( $item['name'] ); ?></span>
							<?php if ( ! empty( $item['sku'] ) ) : ?>
								<br><span class="item-sku">SKU: <?php echo esc_html( $item['sku'] ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $item['length'] ) ) : ?>
								<br><span class="item-detail"><?php esc_html_e( 'Lengte:', 'bossier-calculator' ); ?> <?php echo esc_html( $item['length'] ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $item['color'] ) ) : ?>
								<br><span class="item-detail"><?php esc_html_e( 'Kleur:', 'bossier-calculator' ); ?> <?php echo esc_html( $item['color'] ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $item['weight'] ) ) : ?>
								<br><span class="item-detail"><?php esc_html_e( 'Gewicht:', 'bossier-calculator' ); ?> <?php echo esc_html( $item['weight'] ); ?> <?php echo esc_html( get_option( 'woocommerce_weight_unit' ) ); ?></span>
							<?php endif; ?>
						</td>
						<td class="qty-col"><?php echo esc_html( $item['quantity'] ); ?></td>
						<td class="price-col"><?php echo $invoice->format_price( $item['total'] + $item['total_tax'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				<?php
				// Add shipping as line item
				$shipping_item = $invoice->get_shipping_line_item();
				if ( $shipping_item ) :
				?>
					<tr class="shipping-item">
						<td class="product-col">
							<span class="item-name"><?php echo esc_html( $shipping_item['name'] ); ?></span>
							<?php if ( ! empty( $shipping_item['description'] ) ) : ?>
								<br><span class="shipping-description"><?php echo esc_html( $shipping_item['description'] ); ?></span>
							<?php endif; ?>
						</td>
						<td class="qty-col"><?php echo esc_html( $shipping_item['quantity'] ); ?></td>
						<td class="price-col"><?php echo $invoice->format_price( $shipping_item['total'] + $shipping_item['total_tax'] ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<!-- Totals -->
		<table class="totals-table">
			<?php foreach ( $invoice->get_totals() as $key => $total ) : ?>
				<tr class="<?php echo esc_attr( $key ); ?>">
					<th><?php echo esc_html( $total['label'] ); ?></th>
					<td><?php echo wp_kses_post( $total['value'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>

		<!-- Footer -->
		<?php if ( ! empty( $company['footer'] ) ) : ?>
			<div class="document-footer">
				<?php echo nl2br( esc_html( $company['footer'] ) ); ?>
			</div>
		<?php endif; ?>
	</div>
</body>
</html>

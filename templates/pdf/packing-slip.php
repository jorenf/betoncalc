<?php
/**
 * Packing Slip PDF Template.
 *
 * This template is used to generate packing slip PDFs.
 * Variables available: $packing_slip, $order, $company
 *
 * @package Bossier_Calculator_Builder
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title><?php printf( __( 'Pakbon %s', 'bossier-calculator' ), $order->get_order_number() ); ?></title>
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
					<?php echo $packing_slip->get_logo_html(); ?>
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
		<h1 class="document-title"><?php echo esc_html( get_option( 'boost_pdf_packing_slip_title', __( 'PAKBON', 'bossier-calculator' ) ) ); ?></h1>

		<!-- Address & Order Info -->
		<table class="info-table">
			<tr>
				<td class="address-cell">
					<div class="address-block">
						<?php echo wp_kses_post( $packing_slip->get_shipping_address() ); ?>
					</div>
					<?php if ( $order->get_billing_email() ) : ?>
						<div class="customer-email"><?php echo esc_html( $order->get_billing_email() ); ?></div>
					<?php endif; ?>
				</td>
				<td class="order-info-cell">
					<table class="order-details">
						<tr>
							<th><?php _e( 'Bestelnummer:', 'bossier-calculator' ); ?></th>
							<td><?php echo esc_html( $order->get_order_number() ); ?></td>
						</tr>
						<tr>
							<th><?php _e( 'Besteldatum:', 'bossier-calculator' ); ?></th>
							<td><?php echo esc_html( $packing_slip->get_formatted_order_date() ); ?></td>
						</tr>
						<?php if ( $packing_slip->get_shipping_method() ) : ?>
							<tr>
								<th><?php _e( 'Verzendmethode:', 'bossier-calculator' ); ?></th>
								<td><?php echo esc_html( $packing_slip->get_shipping_method() ); ?></td>
							</tr>
						<?php endif; ?>
					</table>
				</td>
			</tr>
		</table>

		<!-- Products Table -->
		<table class="products-table packing-slip">
			<thead>
				<tr>
					<th class="product-col"><?php _e( 'Product', 'bossier-calculator' ); ?></th>
					<th class="qty-col"><?php _e( 'Hoeveelheid', 'bossier-calculator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $packing_slip->get_order_items() as $item ) : ?>
					<tr>
						<td class="product-col">
							<span class="item-name"><?php echo esc_html( $item['name'] ); ?></span>
							<?php if ( ! empty( $item['sku'] ) ) : ?>
								<br><span class="item-sku">SKU: <?php echo esc_html( $item['sku'] ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $item['weight'] ) ) : ?>
								<br><span class="item-weight"><?php _e( 'Gewicht:', 'bossier-calculator' ); ?> <?php echo esc_html( $item['weight'] ); ?><?php echo esc_html( get_option( 'woocommerce_weight_unit' ) ); ?></span>
							<?php endif; ?>

							<?php if ( $packing_slip->show_calculator_config() && ! empty( $item['calculator_data'] ) ) : ?>
								<div class="calculator-config">
									<?php foreach ( $item['calculator_data'] as $field ) : ?>
										<div class="calc-field">
											<strong><?php echo esc_html( $field['label'] ); ?>:</strong>
											<?php echo esc_html( $field['value'] ); ?>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</td>
						<td class="qty-col"><?php echo esc_html( $item['quantity'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<!-- Customer Notes -->
		<?php if ( $order->get_customer_note() ) : ?>
			<div class="customer-notes">
				<h3><?php _e( 'Opmerkingen klant', 'bossier-calculator' ); ?></h3>
				<p><?php echo nl2br( esc_html( $order->get_customer_note() ) ); ?></p>
			</div>
		<?php endif; ?>

		<!-- Footer -->
		<?php
		$packing_footer = get_option( 'boost_pdf_packing_slip_footer', '' );
		if ( empty( $packing_footer ) ) {
			$packing_footer = $company['footer'];
		}
		if ( ! empty( $packing_footer ) ) :
		?>
			<div class="document-footer">
				<?php echo nl2br( esc_html( $packing_footer ) ); ?>
			</div>
		<?php endif; ?>
	</div>
</body>
</html>

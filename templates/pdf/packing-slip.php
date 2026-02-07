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

		/* Packing Slip Specific Table Styles */
		.packing-table {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 15px;
			font-size: 8pt;
		}

		.packing-table th,
		.packing-table td {
			border: 1px solid #333;
			padding: 8px 6px;
			text-align: left;
			vertical-align: middle;
		}

		.packing-table thead th {
			background: #e5e7eb;
			color: #1f2937;
			font-weight: 600;
			font-size: 8pt;
			text-align: center;
			text-transform: uppercase;
			letter-spacing: 0.3px;
		}

		.packing-table tbody td {
			height: 32px;
		}

		.packing-table tbody tr:nth-child(even) {
			background: #f8fafc;
		}

		.packing-table .col-product {
			width: 22%;
		}

		.packing-table .col-size {
			width: 12%;
			text-align: center;
		}

		.packing-table .col-qty {
			width: 8%;
			text-align: center;
			font-weight: 600;
		}

		.packing-table .col-color {
			width: 10%;
			text-align: center;
		}

		.packing-table .col-angle {
			width: 10%;
			text-align: center;
		}

		.packing-table .col-tracking {
			width: 23%;
		}

		.packing-table .col-check {
			width: 15%;
			text-align: center;
		}

		.packing-table .product-name {
			font-weight: 600;
			color: #1e293b;
		}

		.packing-table .same-as-above {
			color: #94a3b8;
		}

		/* Tracking lines for manual writing */
		.tracking-lines {
			border-bottom: 1px solid #cbd5e1;
			height: 20px;
			margin-bottom: 2px;
		}

		/* Checkbox styling */
		.checkbox-cell {
			text-align: center;
		}

		.checkbox-box {
			display: inline-block;
			width: 16px;
			height: 16px;
			border: 2px solid #374151;
			background: #fff;
			border-radius: 2px;
		}

		/* Customer phone styling */
		.customer-phone {
			font-size: 8pt;
			color: #64748b;
			margin-top: 4px;
		}
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
						<div class="address-label"><?php esc_html_e( 'Afleveradres', 'bossier-calculator' ); ?></div>
						<?php
						// Build structured NAW display
						$shipping_name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
						$shipping_company = $order->get_shipping_company();
						$shipping_address = $order->get_shipping_address_1();
						$shipping_address_2 = $order->get_shipping_address_2();
						$shipping_postcode = $order->get_shipping_postcode();
						$shipping_city = $order->get_shipping_city();
						$shipping_country = WC()->countries->countries[ $order->get_shipping_country() ] ?? $order->get_shipping_country();
						?>
						<?php if ( ! empty( $shipping_company ) ) : ?>
							<strong><?php echo esc_html( $shipping_company ); ?></strong><br>
						<?php endif; ?>
						<?php if ( ! empty( $shipping_name ) ) : ?>
							<?php echo esc_html( $shipping_name ); ?><br>
						<?php endif; ?>
						<?php if ( ! empty( $shipping_address ) ) : ?>
							<?php echo esc_html( $shipping_address ); ?>
							<?php if ( ! empty( $shipping_address_2 ) ) : ?>
								<?php echo esc_html( $shipping_address_2 ); ?>
							<?php endif; ?>
							<br>
						<?php endif; ?>
						<?php if ( ! empty( $shipping_postcode ) || ! empty( $shipping_city ) ) : ?>
							<?php echo esc_html( $shipping_postcode ); ?> <?php echo esc_html( $shipping_city ); ?><br>
						<?php endif; ?>
						<?php if ( ! empty( $shipping_country ) ) : ?>
							<?php echo esc_html( $shipping_country ); ?>
						<?php endif; ?>
					</div>
					<?php if ( $order->get_billing_email() ) : ?>
						<div class="customer-email"><?php echo esc_html( $order->get_billing_email() ); ?></div>
					<?php endif; ?>
					<?php if ( $order->get_billing_phone() ) : ?>
						<div class="customer-phone"><?php echo esc_html( $order->get_billing_phone() ); ?></div>
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
								<th><?php _e( 'Leveringsmethode:', 'bossier-calculator' ); ?></th>
								<td><?php echo esc_html( $packing_slip->get_shipping_method() ); ?></td>
							</tr>
						<?php endif; ?>
					</table>
				</td>
			</tr>
		</table>

		<!-- Products Table - Warehouse Format -->
		<table class="packing-table">
			<thead>
				<tr>
					<th class="col-product"><?php _e( 'Productnaam', 'bossier-calculator' ); ?></th>
					<th class="col-size"><?php _e( 'Afmeting', 'bossier-calculator' ); ?></th>
					<th class="col-qty"><?php _e( 'Aantal', 'bossier-calculator' ); ?></th>
					<th class="col-color"><?php _e( 'Kleur', 'bossier-calculator' ); ?></th>
					<th class="col-angle"><?php _e( 'Verstek', 'bossier-calculator' ); ?></th>
					<th class="col-tracking"><?php _e( 'Mal gereed / aantal', 'bossier-calculator' ); ?></th>
					<th class="col-check"><?php _e( 'Product gereed', 'bossier-calculator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$items = $packing_slip->get_order_items();
				$last_product_name = '';

				foreach ( $items as $item ) :
					// Get calculator data
					$length = '';
					$color = '';
					$verstek = '';

					if ( ! empty( $item['calculator_data'] ) ) {
						foreach ( $item['calculator_data'] as $field ) {
							$label_lower = strtolower( $field['label'] );
							if ( strpos( $label_lower, 'lengte' ) !== false || strpos( $label_lower, 'afmeting' ) !== false ) {
								$length = $field['value'];
							}
							if ( strpos( $label_lower, 'kleur' ) !== false || strpos( $label_lower, 'color' ) !== false ) {
								$color = $field['value'];
							}
							if ( strpos( $label_lower, 'verstek' ) !== false || strpos( $label_lower, 'hoek' ) !== false ) {
								$verstek = $field['value'];
							}
						}
					}

					// Check if this is a continuation of the same product
					$is_same_product = ( $item['name'] === $last_product_name );
					$last_product_name = $item['name'];
				?>
					<tr>
						<td class="col-product">
							<?php if ( ! $is_same_product ) : ?>
								<span class="product-name"><?php echo esc_html( $item['name'] ); ?></span>
							<?php endif; ?>
						</td>
						<td class="col-size"><?php echo esc_html( $length ); ?></td>
						<td class="col-qty"><?php echo esc_html( $item['quantity'] ); ?></td>
						<td class="col-color">
							<?php if ( ! $is_same_product || ! empty( $color ) ) : ?>
								<?php echo esc_html( $color ); ?>
							<?php else : ?>
								<span class="same-as-above">"</span>
							<?php endif; ?>
						</td>
						<td class="col-angle"><?php echo esc_html( $verstek ); ?></td>
						<td class="col-tracking">
							<div class="tracking-lines"></div>
						</td>
						<td class="col-check checkbox-cell">
							<span class="checkbox-box"></span>
						</td>
					</tr>
				<?php endforeach; ?>

				<?php
				// Add empty rows for manual additions
				for ( $i = 0; $i < 3; $i++ ) :
				?>
					<tr>
						<td class="col-product"></td>
						<td class="col-size"></td>
						<td class="col-qty"></td>
						<td class="col-color"></td>
						<td class="col-angle"></td>
						<td class="col-tracking"><div class="tracking-lines"></div></td>
						<td class="col-check checkbox-cell"><span class="checkbox-box"></span></td>
					</tr>
				<?php endfor; ?>
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

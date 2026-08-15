<?php
/**
 * Template Name: GCM Verify Certificate
 *
 * Public certificate verification — enter certificate ID to view the official certificate.
 *
 * @package GigaClassMarket
 */

$code = '';
if ( isset( $_GET['code'] ) ) {
	$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
}
if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['gcm_cert_code'] ) ) {
	if ( isset( $_POST['gcm_verify_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gcm_verify_nonce'] ) ), 'gcm_verify_certificate' ) ) {
		$code = sanitize_text_field( wp_unslash( $_POST['gcm_cert_code'] ) );
	}
}

$certificate = null;
$error       = '';
if ( '' !== $code && class_exists( 'GCM_Certificate_Service' ) ) {
	$certificate = GCM_Certificate_Service::get_by_code( $code );
	if ( ! $certificate ) {
		$error = __( 'No certificate found for that ID. Check the number and try again.', 'giga-class-market' );
	}
}

get_header();
?>
<section class="gcm-page-hero gcm-page-hero--verify">
	<div class="gcm-container">
		<p class="gcm-eyebrow gcm-animate"><?php esc_html_e( 'Official verification', 'giga-class-market' ); ?></p>
		<h1 class="gcm-animate"><?php esc_html_e( 'Verify Certificate', 'giga-class-market' ); ?></h1>
		<p class="gcm-animate gcm-lead">
			<?php
			echo wp_kses(
				__( 'Enter the <strong>certificate ID</strong> from the student’s email to confirm authenticity and view the official award.', 'giga-class-market' ),
				array( 'strong' => array() )
			);
			?>
		</p>
	</div>
</section>

<section class="gcm-section">
	<div class="gcm-container gcm-verify-layout">
		<form class="gcm-verify-form gcm-animate" method="get" action="<?php echo esc_url( home_url( '/verify-certificate/' ) ); ?>">
			<label for="gcm-cert-code">
				<span><?php esc_html_e( 'Certificate number / ID', 'giga-class-market' ); ?></span>
				<input
					id="gcm-cert-code"
					type="text"
					name="code"
					value="<?php echo esc_attr( $code ); ?>"
					placeholder="GCM-XXXX-XXXX-XXXX"
					required
					autocomplete="off"
					spellcheck="false"
				>
			</label>
			<button type="submit" class="gcm-button gcm-button--gold gcm-button--full">
				<?php esc_html_e( 'Verify Certificate', 'giga-class-market' ); ?>
			</button>
		</form>

		<?php if ( $error ) : ?>
			<p class="gcm-form-message gcm-form-message--error" role="alert"><?php echo esc_html( $error ); ?></p>
		<?php endif; ?>

		<?php if ( $certificate ) : ?>
			<div class="gcm-verify-result gcm-animate">
				<p class="gcm-verify-result__badge">
					<?php
					echo wp_kses(
						__( 'This certificate is <strong>valid</strong> and issued by Giga Class Market.', 'giga-class-market' ),
						array( 'strong' => array() )
					);
					?>
				</p>
				<?php echo GCM_Certificate_Service::render_certificate_html( $certificate ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside renderer. ?>
				<div class="gcm-verify-result__actions">
					<button type="button" class="gcm-button gcm-button--outline" onclick="window.print();">
						<?php esc_html_e( 'Print certificate', 'giga-class-market' ); ?>
					</button>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();

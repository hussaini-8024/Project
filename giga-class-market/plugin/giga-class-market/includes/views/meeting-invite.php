<?php
/**
 * Meeting ID, passcode, join link, and invitation share panel.
 *
 * @package GigaClassMarket
 *
 * @var object $class Class row.
 * @var array  $args Panel args.
 * @var array  $payload Meeting payload.
 */

defined( 'ABSPATH' ) || exit;

$meeting_id = $payload['meeting_id_display'] ?? '';
$passcode   = $payload['passcode'] ?? '';
$join_url   = $payload['join_url'] ?? '';
$invite_url = $payload['invite_url'] ?? '';
$invite     = $payload['invite_text'] ?? '';
$provider   = $payload['provider'] ?? '';
$can_edit   = ! empty( $args['can_edit_passcode'] );
$variant    = isset( $args['variant'] ) ? sanitize_key( $args['variant'] ) : 'teacher';
$class_id   = (int) $class->id;
$uid        = 'gcm-meet-' . $class_id . '-' . $variant;
?>
<div class="gcm-meeting-invite gcm-meeting-invite--<?php echo esc_attr( $variant ); ?>" data-class-id="<?php echo esc_attr( (string) $class_id ); ?>">
	<p class="gcm-meeting-invite__label"><?php esc_html_e( 'Meeting details', 'giga-class-market' ); ?></p>
	<dl class="gcm-meeting-invite__grid">
		<div>
			<dt><?php esc_html_e( 'Meeting ID', 'giga-class-market' ); ?></dt>
			<dd>
				<code id="<?php echo esc_attr( $uid ); ?>-id"><?php echo esc_html( $meeting_id ? $meeting_id : '—' ); ?></code>
				<?php if ( $meeting_id ) : ?>
					<button type="button" class="gcm-copy-value" data-copy="<?php echo esc_attr( $meeting_id ); ?>"><?php esc_html_e( 'Copy', 'giga-class-market' ); ?></button>
				<?php endif; ?>
			</dd>
		</div>
		<div>
			<dt><?php esc_html_e( 'Passcode', 'giga-class-market' ); ?></dt>
			<dd>
				<code id="<?php echo esc_attr( $uid ); ?>-pw"><?php echo esc_html( $passcode ? $passcode : '—' ); ?></code>
				<?php if ( $passcode ) : ?>
					<button type="button" class="gcm-copy-value" data-copy="<?php echo esc_attr( $passcode ); ?>"><?php esc_html_e( 'Copy', 'giga-class-market' ); ?></button>
				<?php endif; ?>
			</dd>
		</div>
		<div class="gcm-meeting-invite__link">
			<dt><?php esc_html_e( 'Join by link', 'giga-class-market' ); ?></dt>
			<dd>
				<?php if ( $join_url ) : ?>
					<a href="<?php echo esc_url( $join_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open join link', 'giga-class-market' ); ?></a>
					<button type="button" class="gcm-copy-value" data-copy="<?php echo esc_attr( $join_url ); ?>"><?php esc_html_e( 'Copy link', 'giga-class-market' ); ?></button>
				<?php else : ?>
					—
				<?php endif; ?>
			</dd>
		</div>
	</dl>

	<?php if ( $can_edit ) : ?>
		<form class="gcm-ajax-form gcm-teacher-form gcm-meeting-invite__passcode" data-action="gcm_update_class_passcode">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>" />
			<input type="hidden" name="class_id" value="<?php echo esc_attr( (string) $class_id ); ?>" />
			<label>
				<?php esc_html_e( 'Customize passcode', 'giga-class-market' ); ?>
				<input type="text" name="passcode" maxlength="10" value="<?php echo esc_attr( $passcode ); ?>" placeholder="<?php esc_attr_e( '4–10 characters', 'giga-class-market' ); ?>" autocomplete="off" />
			</label>
			<button type="submit" class="gcm-button gcm-button--small gcm-button--outline"><?php esc_html_e( 'Update passcode', 'giga-class-market' ); ?></button>
			<div class="gcm-form-message" aria-live="polite"></div>
		</form>
		<?php if ( 'jitsi' === $provider ) : ?>
			<p class="gcm-meeting-invite__hint"><?php esc_html_e( 'Share this passcode with students. Public Jitsi rooms cannot lock themselves automatically — the join link still opens the room.', 'giga-class-market' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<div class="gcm-meeting-invite__share">
		<textarea class="gcm-invite-source" readonly hidden><?php echo esc_textarea( $invite ); ?></textarea>
		<button type="button" class="gcm-button gcm-button--small gcm-copy-invite"><?php esc_html_e( 'Copy invitation', 'giga-class-market' ); ?></button>
		<button
			type="button"
			class="gcm-button gcm-button--small gcm-button--outline gcm-share-invite"
			data-title="<?php echo esc_attr( sprintf( __( 'Join: %s', 'giga-class-market' ), $class->title ) ); ?>"
			data-url="<?php echo esc_url( $join_url ? $join_url : $invite_url ); ?>"
		><?php esc_html_e( 'Share invitation', 'giga-class-market' ); ?></button>
		<?php if ( $invite_url ) : ?>
			<button type="button" class="gcm-button gcm-button--small gcm-button--outline gcm-copy-value" data-copy="<?php echo esc_attr( $invite_url ); ?>"><?php esc_html_e( 'Copy invitation link', 'giga-class-market' ); ?></button>
		<?php endif; ?>
	</div>
</div>

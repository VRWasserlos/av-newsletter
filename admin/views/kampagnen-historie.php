<?php
/**
 * Admin-View: Kampagnen-Historie (versendete Kampagnen)
 *
 * @package VRW_Newsletter
 * @author  Kai Naumann <info@vereinsring-wasserlos.de>
 * @version 1.0.7
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$campaigns = AVN_Database::get_campaigns_by_status( [ 'sent', 'sending', 'scheduled' ], 100 );
?>
<div class="wrap av-admin-container">

    <div class="av-page-header">
        <h1 class="av-page-title">
            <span class="dashicons dashicons-chart-bar"></span>
            <?php esc_html_e( 'Kampagnen-Historie', 'av-newsletter' ); ?>
            <span class="av-count-badge"><?php echo esc_html( count( $campaigns ) ); ?></span>
        </h1>
    </div>

    <div class="av-card">
        <div class="av-card-body">
            <?php if ( empty( $campaigns ) ) : ?>
                <div class="av-empty-state-box">
                    <span class="dashicons dashicons-chart-bar av-empty-icon"></span>
                    <p>
                        <?php esc_html_e( 'Noch keine Kampagnen versendet.', 'av-newsletter' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-mail-senden&action=new' ) ); ?>">
                            <?php esc_html_e( 'Erste Mail erstellen', 'av-newsletter' ); ?>
                        </a>
                    </p>
                </div>
            <?php else : ?>
                <table class="av-table av-table-hover">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Template', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Betreff', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Empfänger', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Anzahl', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Datum', 'av-newsletter' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $campaigns as $c ) :
                        switch ( $c->status ) {
                            case 'sent':         $badge_class = 'av-badge-active';   $badge_label = __( 'Versendet',     'av-newsletter' ); break;
                            case 'sent_partial': $badge_class = 'av-badge-warning';  $badge_label = __( 'Teilweise',     'av-newsletter' ); break;
                            case 'failed':       $badge_class = 'av-badge-inactive'; $badge_label = __( 'Fehler',        'av-newsletter' ); break;
                            case 'sending':      $badge_class = 'av-badge-info';     $badge_label = __( 'Wird gesendet', 'av-newsletter' ); break;
                            case 'scheduled':    $badge_class = 'av-badge-pending';  $badge_label = __( 'Geplant',       'av-newsletter' ); break;
                            default:             $badge_class = 'av-badge-draft';    $badge_label = esc_html( $c->status ); break;
                        }
                        $date_col = ( $c->status === 'scheduled' && ! empty( $c->scheduled_at ) )
                            ? date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $c->scheduled_at ) )
                            : date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $c->created_at ) );
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html( $c->template_name ?? '–' ); ?></strong></td>
                            <td><?php echo esc_html( $c->subject ?: '—' ); ?></td>
                            <td>
                                <?php echo $c->group_name
                                    ? esc_html( $c->group_name )
                                    : '<em>' . esc_html__( 'Alle', 'av-newsletter' ) . '</em>'; ?>
                            </td>
                            <td><?php echo esc_html( $c->recipient_count ); ?></td>
                            <td><span class="av-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_label ); ?></span></td>
                            <td><?php echo esc_html( $date_col ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

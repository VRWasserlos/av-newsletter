<?php
/**
 * Admin-View: Newsletter Dashboard
 *
 * @package VRW_Newsletter
 * @author  Kai Naumann <info@vereinsring-wasserlos.de>
 * @version 1.0.7
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$total      = AVN_Database::count_subscribers();
$active     = AVN_Database::count_subscribers( [ 'status' => 'active' ] );
$pending    = AVN_Database::count_subscribers( [ 'status' => 'pending' ] );
$groups     = AVN_Database::get_groups();
$templates  = AVN_Database::get_mail_templates();
$recent_log = AVN_Database::get_mail_log( [ 'limit' => 5 ] );
?>
<div class="wrap av-admin-container">

    <div class="av-page-header">
        <h1 class="av-page-title">
            <span class="dashicons dashicons-email-alt"></span>
            <?php esc_html_e( 'Newsletter Dashboard', 'av-newsletter' ); ?>
        </h1>
    </div>

    <!-- Stats -->
    <div class="av-stats-grid">
        <div class="av-stat-card">
            <div class="av-stat-icon av-stat-icon--primary">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div>
                <div class="av-stat-number"><?php echo esc_html( $total ); ?></div>
                <div class="av-stat-label"><?php esc_html_e( 'Abonnenten gesamt', 'av-newsletter' ); ?></div>
            </div>
        </div>
        <div class="av-stat-card">
            <div class="av-stat-icon av-stat-icon--success">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div>
                <div class="av-stat-number"><?php echo esc_html( $active ); ?></div>
                <div class="av-stat-label"><?php esc_html_e( 'Aktive Abonnenten', 'av-newsletter' ); ?></div>
            </div>
        </div>
        <div class="av-stat-card">
            <div class="av-stat-icon av-stat-icon--warning">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div>
                <div class="av-stat-number"><?php echo esc_html( $pending ); ?></div>
                <div class="av-stat-label"><?php esc_html_e( 'Ausstehend (Opt-In)', 'av-newsletter' ); ?></div>
            </div>
        </div>
        <div class="av-stat-card">
            <div class="av-stat-icon av-stat-icon--info">
                <span class="dashicons dashicons-email-alt2"></span>
            </div>
            <div>
                <div class="av-stat-number"><?php echo esc_html( count( $templates ) ); ?></div>
                <div class="av-stat-label"><?php esc_html_e( 'Mail-Templates', 'av-newsletter' ); ?></div>
            </div>
        </div>
    </div>

    <div class="av-content-grid">
        <!-- Schnellzugriff -->
        <div class="av-card">
            <div class="av-card-header">
                <h3><?php esc_html_e( 'Schnellzugriff', 'av-newsletter' ); ?></h3>
            </div>
            <div class="av-card-body">
                <div class="av-quick-actions">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-abonnenten' ) ); ?>" class="av-btn av-btn-secondary av-btn-full">
                        <span class="dashicons dashicons-groups"></span>
                        <?php esc_html_e( 'Abonnenten', 'av-newsletter' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-gruppen' ) ); ?>" class="av-btn av-btn-secondary av-btn-full">
                        <span class="dashicons dashicons-category"></span>
                        <?php esc_html_e( 'Gruppen', 'av-newsletter' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-mail-builder' ) ); ?>" class="av-btn av-btn-primary av-btn-full">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e( 'Neues Mail-Template', 'av-newsletter' ); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Letzter Mail-Log -->
        <div class="av-card">
            <div class="av-card-header">
                <h3><?php esc_html_e( 'Letzte gesendete Mails', 'av-newsletter' ); ?></h3>
            </div>
            <div class="av-card-body">
                <?php if ( empty( $recent_log ) ) : ?>
                    <p class="av-empty-state"><?php esc_html_e( 'Noch keine Mails gesendet.', 'av-newsletter' ); ?></p>
                <?php else : ?>
                    <table class="av-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Empfänger', 'av-newsletter' ); ?></th>
                                <th><?php esc_html_e( 'Betreff', 'av-newsletter' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'av-newsletter' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $recent_log as $log ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $log->recipient_email ); ?></td>
                                    <td><?php echo esc_html( $log->subject ); ?></td>
                                    <td><span class="av-badge av-badge-<?php echo esc_attr( $log->status ); ?>"><?php echo esc_html( ucfirst( $log->status ) ); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

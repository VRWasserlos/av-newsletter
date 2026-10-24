<?php
/**
 * Admin-View: Mail-Templates
 *
 * @package VRW_Newsletter
 * @author  Kai Naumann <info@vereinsring-wasserlos.de>
 * @version 1.0.7
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$templates = AVN_Database::get_mail_templates();
$groups    = AVN_Database::get_groups();
?>
<div class="wrap av-admin-container">

    <div class="av-page-header">
        <h1 class="av-page-title">
            <span class="dashicons dashicons-email-alt"></span>
            <?php esc_html_e( 'Mail-Templates', 'av-newsletter' ); ?>
            <span class="av-count-badge"><?php echo esc_html( count( $templates ) ); ?></span>
        </h1>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-mail-builder' ) ); ?>" class="av-btn av-btn-primary">
            <span class="dashicons dashicons-plus-alt"></span>
            <?php esc_html_e( 'Neues Template', 'av-newsletter' ); ?>
        </a>
    </div>

    <div class="av-card">
        <div class="av-card-body">
            <?php if ( empty( $templates ) ) : ?>
                <div class="av-empty-state-box">
                    <span class="dashicons dashicons-email-alt av-empty-icon"></span>
                    <p>
                        <?php esc_html_e( 'Noch keine Mail-Templates vorhanden.', 'av-newsletter' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-mail-builder' ) ); ?>">
                            <?php esc_html_e( 'Erstes Template erstellen', 'av-newsletter' ); ?>
                        </a>
                    </p>
                </div>
            <?php else : ?>
                <table class="av-table av-table-hover">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Betreff', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Erstellt', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Aktionen', 'av-newsletter' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $templates as $tpl ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $tpl->name ); ?></strong></td>
                            <td><?php echo esc_html( $tpl->subject ); ?></td>
                            <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $tpl->created_at ) ) ); ?></td>
                            <td class="av-actions">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-mail-builder&template_id=' . $tpl->id ) ); ?>"
                                   class="av-btn av-btn-sm av-btn-secondary">
                                    <span class="dashicons dashicons-edit"></span>
                                    <?php esc_html_e( 'Bearbeiten', 'av-newsletter' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-mail-senden&action=new&tpl=' . $tpl->id ) ); ?>"
                                   class="av-btn av-btn-sm av-btn-ghost">
                                    <span class="dashicons dashicons-email"></span>
                                    <?php esc_html_e( 'Mail erstellen', 'av-newsletter' ); ?>
                                </a>
                                <button class="av-btn av-btn-sm av-btn-danger av-delete-mail-template"
                                        data-id="<?php echo esc_attr( $tpl->id ); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

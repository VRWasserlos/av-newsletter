<?php
/**
 * Admin-View: Gruppen-Verwaltung
 *
 * @package VRW_Newsletter
 * @author  Kai Naumann <info@vereinsring-wasserlos.de>
 * @version 1.0.7
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$groups = AVN_Database::get_groups();
?>
<div class="wrap av-admin-container">

    <div class="av-page-header">
        <h1 class="av-page-title">
            <span class="dashicons dashicons-category"></span>
            <?php esc_html_e( 'Gruppen', 'av-newsletter' ); ?>
            <span class="av-count-badge"><?php echo esc_html( count( $groups ) ); ?></span>
        </h1>
        <button id="av-open-add-group" class="av-btn av-btn-primary">
            <span class="dashicons dashicons-plus-alt"></span>
            <?php esc_html_e( 'Gruppe hinzufügen', 'av-newsletter' ); ?>
        </button>
    </div>

    <div class="av-card">
        <div class="av-card-body">
            <?php if ( empty( $groups ) ) : ?>
                <div class="av-empty-state-box">
                    <span class="dashicons dashicons-category av-empty-icon"></span>
                    <p><?php esc_html_e( 'Noch keine Gruppen vorhanden.', 'av-newsletter' ); ?></p>
                </div>
            <?php else : ?>
                <table class="av-table av-table-hover">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Beschreibung', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Abonnenten', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Erstellt', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Aktionen', 'av-newsletter' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $groups as $group ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $group->name ); ?></strong></td>
                            <td><?php echo $group->description ? esc_html( $group->description ) : '<em>' . esc_html__( 'keine', 'av-newsletter' ) . '</em>'; ?></td>
                            <td>
                                <span class="av-badge av-badge-info">
                                    <?php echo esc_html( AVN_Database::count_group_subscribers( $group->id ) ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $group->created_at ) ) ); ?></td>
                            <td class="av-actions">
                                <button class="av-btn av-btn-sm av-btn-secondary av-edit-group"
                                        data-group='<?php echo esc_attr( wp_json_encode( [
                                            'id'          => $group->id,
                                            'name'        => $group->name,
                                            'description' => $group->description,
                                        ] ) ); ?>'>
                                    <span class="dashicons dashicons-edit"></span>
                                </button>
                                <button class="av-btn av-btn-sm av-btn-danger av-delete-group"
                                        data-id="<?php echo esc_attr( $group->id ); ?>">
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

    <!-- Gruppe hinzufügen/bearbeiten Modal -->
    <div id="av-group-modal" class="av-modal">
        <div class="av-modal-overlay"></div>
        <div class="av-modal-content">
            <div class="av-modal-header">
                <h3 id="av-group-modal-title"><?php esc_html_e( 'Gruppe', 'av-newsletter' ); ?></h3>
                <button class="av-modal-close"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="av-modal-body">
                <input type="hidden" id="av-group-id" value="">
                <div class="av-form-row">
                    <div class="av-col-12">
                        <label><?php esc_html_e( 'Name', 'av-newsletter' ); ?> *</label>
                        <input type="text" id="av-group-name" class="av-input" required>
                    </div>
                </div>
                <div class="av-form-row">
                    <div class="av-col-12">
                        <label><?php esc_html_e( 'Beschreibung', 'av-newsletter' ); ?></label>
                        <textarea id="av-group-description" class="av-textarea" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="av-modal-footer">
                <button id="av-save-group" class="av-btn av-btn-primary">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'Speichern', 'av-newsletter' ); ?>
                </button>
            </div>
        </div>
    </div>

</div>

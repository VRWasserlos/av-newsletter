<?php
/**
 * Admin-View: Abonnenten-Verwaltung
 *
 * @package VRW_Newsletter
 * @author  Kai Naumann <info@vereinsring-wasserlos.de>
 * @version 1.0.7
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$search        = sanitize_text_field( $_GET['search'] ?? '' );
$status_filter = sanitize_key( $_GET['status'] ?? '' );
$group_filter  = (int) ( $_GET['group_id'] ?? 0 );
$subscribers   = AVN_Database::get_subscribers( [
    'search'   => $search,
    'status'   => $status_filter ?: null,
    'group_id' => $group_filter ?: null,
    'limit'    => 200,
] );
$groups = AVN_Database::get_groups();
?>
<div class="wrap av-admin-container">

    <div class="av-page-header">
        <h1 class="av-page-title">
            <span class="dashicons dashicons-groups"></span>
            <?php esc_html_e( 'Abonnenten', 'av-newsletter' ); ?>
            <span class="av-count-badge"><?php echo esc_html( AVN_Database::count_subscribers() ); ?></span>
        </h1>
        <div class="av-header-actions">
            <button id="av-open-import" class="av-btn av-btn-secondary">
                <span class="dashicons dashicons-upload"></span>
                <?php esc_html_e( 'Importieren (XLSX)', 'av-newsletter' ); ?>
            </button>
            <button id="av-open-add-subscriber" class="av-btn av-btn-primary">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php esc_html_e( 'Abonnent hinzufügen', 'av-newsletter' ); ?>
            </button>
        </div>
    </div>

    <!-- Filter -->
    <div class="av-card av-filter-bar">
        <form method="get" action="">
            <input type="hidden" name="page" value="avn-abonnenten">
            <div class="av-form-row av-form-row-inline">
                <div class="av-col-4">
                    <input type="text" name="search" class="av-input" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Name oder E-Mail suchen…', 'av-newsletter' ); ?>">
                </div>
                <div class="av-col-3">
                    <select name="status" class="av-select">
                        <option value=""><?php esc_html_e( 'Alle Status', 'av-newsletter' ); ?></option>
                        <option value="active"       <?php selected( $status_filter, 'active' ); ?>><?php esc_html_e( 'Aktiv', 'av-newsletter' ); ?></option>
                        <option value="pending"      <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Ausstehend', 'av-newsletter' ); ?></option>
                        <option value="unsubscribed" <?php selected( $status_filter, 'unsubscribed' ); ?>><?php esc_html_e( 'Abgemeldet', 'av-newsletter' ); ?></option>
                    </select>
                </div>
                <div class="av-col-3">
                    <select name="group_id" class="av-select">
                        <option value=""><?php esc_html_e( 'Alle Gruppen', 'av-newsletter' ); ?></option>
                        <?php foreach ( $groups as $g ) : ?>
                            <option value="<?php echo esc_attr( $g->id ); ?>" <?php selected( $group_filter, $g->id ); ?>>
                                <?php echo esc_html( $g->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="av-col-auto">
                    <button type="submit" class="av-btn av-btn-secondary">
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e( 'Suchen', 'av-newsletter' ); ?>
                    </button>
                    <?php if ( $search || $status_filter || $group_filter ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-abonnenten' ) ); ?>" class="av-btn av-btn-ghost">
                        <?php esc_html_e( 'Zurücksetzen', 'av-newsletter' ); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabelle -->
    <div class="av-card">
        <div class="av-card-body">
            <?php if ( empty( $subscribers ) ) : ?>
                <div class="av-empty-state-box">
                    <span class="dashicons dashicons-groups av-empty-icon"></span>
                    <p><?php esc_html_e( 'Keine Abonnenten gefunden.', 'av-newsletter' ); ?></p>
                </div>
            <?php else : ?>
                <table class="av-table av-table-hover">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="av-select-all-subscribers"></th>
                            <th><?php esc_html_e( 'E-Mail', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Name', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Gruppen', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Angemeldet', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Aktionen', 'av-newsletter' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $subscribers as $sub ) :
                        $sub_groups  = AVN_Database::get_subscriber_groups( $sub->id );
                        $group_names = [];
                        foreach ( $groups as $g ) {
                            if ( in_array( (string) $g->id, $sub_groups, true ) ) {
                                $group_names[] = esc_html( $g->name );
                            }
                        }
                    ?>
                        <tr>
                            <td><input type="checkbox" class="av-subscriber-check" value="<?php echo esc_attr( $sub->id ); ?>"></td>
                            <td><?php echo esc_html( $sub->email ); ?></td>
                            <td><?php echo esc_html( trim( $sub->first_name . ' ' . $sub->last_name ) ); ?></td>
                            <td><?php echo $group_names ? implode( ', ', $group_names ) : '<em>' . esc_html__( 'keine', 'av-newsletter' ) . '</em>'; ?></td>
                            <td>
                                <span class="av-badge av-badge-<?php echo esc_attr( $sub->status ); ?>">
                                    <?php echo esc_html( AVN_Subscriber::status_label( $sub->status ) ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sub->created_at ) ) ); ?></td>
                            <td class="av-actions">
                                <button class="av-btn av-btn-sm av-btn-secondary av-edit-subscriber"
                                        data-subscriber='<?php echo esc_attr( wp_json_encode( [
                                            'id'         => $sub->id,
                                            'email'      => $sub->email,
                                            'first_name' => $sub->first_name,
                                            'last_name'  => $sub->last_name,
                                            'status'     => $sub->status,
                                            'groups'     => $sub_groups,
                                        ] ) ); ?>'>
                                    <span class="dashicons dashicons-edit"></span>
                                </button>
                                <button class="av-btn av-btn-sm av-btn-danger av-delete-subscriber"
                                        data-id="<?php echo esc_attr( $sub->id ); ?>">
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

    <!-- Import-Modal -->
    <div id="av-import-modal" class="av-modal">
        <div class="av-modal-overlay"></div>
        <div class="av-modal-content">
            <div class="av-modal-header">
                <h3><?php esc_html_e( 'Abonnenten importieren (XLSX)', 'av-newsletter' ); ?></h3>
                <button class="av-modal-close"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="av-modal-body">

                <div class="av-notice av-notice-info" style="margin-bottom:16px;">
                    <strong><?php esc_html_e( 'Unterstütztes Format:', 'av-newsletter' ); ?></strong>
                    <?php esc_html_e( 'MailPoet XLSX-Export. Erwartete Spalten: Email, First name, Last name, Status, List.', 'av-newsletter' ); ?>
                </div>

                <div class="av-form-row">
                    <div class="av-col-12">
                        <label><?php esc_html_e( 'XLSX-Datei auswählen', 'av-newsletter' ); ?> *</label>
                        <input type="file" id="av-import-file" class="av-input" accept=".xlsx" required>
                    </div>
                </div>

                <div class="av-form-row">
                    <div class="av-col-6">
                        <label><?php esc_html_e( 'Duplikate (bereits vorhandene E-Mails)', 'av-newsletter' ); ?></label>
                        <select id="av-import-duplicate" class="av-select">
                            <option value="skip"><?php esc_html_e( 'Überspringen', 'av-newsletter' ); ?></option>
                            <option value="update"><?php esc_html_e( 'Aktualisieren', 'av-newsletter' ); ?></option>
                        </select>
                    </div>
                    <div class="av-col-6">
                        <label><?php esc_html_e( 'Standard-Status (wenn keine Status-Spalte)', 'av-newsletter' ); ?></label>
                        <select id="av-import-default-status" class="av-select">
                            <option value="active"><?php esc_html_e( 'Aktiv', 'av-newsletter' ); ?></option>
                            <option value="pending"><?php esc_html_e( 'Ausstehend (Bestätigung erforderlich)', 'av-newsletter' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="av-form-row">
                    <div class="av-col-12">
                        <label><?php esc_html_e( 'Alle Importierten zusätzlich einer Gruppe zuweisen (optional)', 'av-newsletter' ); ?></label>
                        <select id="av-import-group" class="av-select">
                            <option value=""><?php esc_html_e( '— keine —', 'av-newsletter' ); ?></option>
                            <?php foreach ( $groups as $g ) : ?>
                                <option value="<?php echo esc_attr( $g->id ); ?>"><?php echo esc_html( $g->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="av-import-result" class="av-hidden"></div>

            </div>
            <div class="av-modal-footer">
                <button id="av-start-import" class="av-btn av-btn-primary">
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e( 'Importieren', 'av-newsletter' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Abonnent hinzufügen/bearbeiten Modal -->
    <div id="av-subscriber-modal" class="av-modal">
        <div class="av-modal-overlay"></div>
        <div class="av-modal-content">
            <div class="av-modal-header">
                <h3 id="av-subscriber-modal-title"><?php esc_html_e( 'Abonnent', 'av-newsletter' ); ?></h3>
                <button class="av-modal-close"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="av-modal-body">
                <input type="hidden" id="av-sub-id" value="">
                <div class="av-form-row">
                    <div class="av-col-6">
                        <label><?php esc_html_e( 'E-Mail', 'av-newsletter' ); ?> *</label>
                        <input type="email" id="av-sub-email" class="av-input" required>
                    </div>
                    <div class="av-col-3">
                        <label><?php esc_html_e( 'Vorname', 'av-newsletter' ); ?></label>
                        <input type="text" id="av-sub-firstname" class="av-input">
                    </div>
                    <div class="av-col-3">
                        <label><?php esc_html_e( 'Nachname', 'av-newsletter' ); ?></label>
                        <input type="text" id="av-sub-lastname" class="av-input">
                    </div>
                </div>
                <div class="av-form-row">
                    <div class="av-col-4">
                        <label><?php esc_html_e( 'Status', 'av-newsletter' ); ?></label>
                        <select id="av-sub-status" class="av-select">
                            <option value="active"><?php esc_html_e( 'Aktiv', 'av-newsletter' ); ?></option>
                            <option value="pending"><?php esc_html_e( 'Ausstehend', 'av-newsletter' ); ?></option>
                            <option value="unsubscribed"><?php esc_html_e( 'Abgemeldet', 'av-newsletter' ); ?></option>
                        </select>
                    </div>
                    <div class="av-col-8">
                        <label><?php esc_html_e( 'Gruppen', 'av-newsletter' ); ?></label>
                        <div class="av-checkbox-group" id="av-sub-groups">
                            <?php foreach ( $groups as $g ) : ?>
                                <label>
                                    <input type="checkbox" class="av-group-check" value="<?php echo esc_attr( $g->id ); ?>">
                                    <?php echo esc_html( $g->name ); ?>
                                </label>
                            <?php endforeach; ?>
                            <?php if ( empty( $groups ) ) : ?>
                                <em><?php esc_html_e( 'Noch keine Gruppen vorhanden.', 'av-newsletter' ); ?></em>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="av-modal-footer">
                <button id="av-save-subscriber" class="av-btn av-btn-primary">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'Speichern', 'av-newsletter' ); ?>
                </button>
            </div>
        </div>
    </div>

</div>

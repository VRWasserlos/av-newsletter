<?php
/**
 * Admin-View: Mails – Liste der Entwürfe + Editor
 *
 * Listenansicht:  ?page=avn-mail-senden
 * Editor (neu):   ?page=avn-mail-senden&action=new
 * Editor (Draft): ?page=avn-mail-senden&draft_id=X
 *
 * @package VRW_Newsletter
 * @author  Kai Naumann <info@vereinsring-wasserlos.de>
 * @version 1.0.7
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$action    = isset( $_GET['action'] )   ? sanitize_key( $_GET['action'] ) : '';
$draft_id  = isset( $_GET['draft_id'] ) ? (int) $_GET['draft_id']         : 0;
$is_editor = ( $action === 'new' || $draft_id > 0 );

/* ── Gemeinsame Daten ─────────────────────────────────────────────────── */
$templates    = AVN_Database::get_mail_templates();
$groups       = AVN_Database::get_groups();
$sender_name  = get_option( 'avn_sender_name',  get_bloginfo( 'name' ) );
$sender_email = get_option( 'avn_sender_email', get_option( 'admin_email' ) );
$total_active = AVN_Database::count_subscribers( [ 'status' => 'active' ] );

/* ── LISTENANSICHT ─────────────────────────────────────────────────────── */
if ( ! $is_editor ) :
    $all_campaigns = AVN_Database::get_all_campaigns( 200 );
?>
<div class="wrap av-admin-container">

    <div class="av-page-header">
        <h1 class="av-page-title">
            <span class="dashicons dashicons-email-alt"></span>
            <?php esc_html_e( 'Mails', 'av-newsletter' ); ?>
        </h1>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-mail-senden&action=new' ) ); ?>"
           class="av-btn av-btn-primary">
            <span class="dashicons dashicons-plus-alt"></span>
            <?php esc_html_e( 'Neue Mail', 'av-newsletter' ); ?>
        </a>
    </div><!-- .av-page-header -->

    <div class="av-card">
        <div class="av-card-body">
            <?php if ( empty( $all_campaigns ) ) : ?>
                <div class="av-empty-state-box">
                    <span class="dashicons dashicons-email av-empty-icon"></span>
                    <p>
                        <?php esc_html_e( 'Noch keine Mails vorhanden.', 'av-newsletter' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-mail-senden&action=new' ) ); ?>">
                            <?php esc_html_e( 'Erste Mail erstellen', 'av-newsletter' ); ?>
                        </a>
                    </p>
                </div>
            <?php else : ?>
                <table class="av-table av-table-hover">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Betreff', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Template', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Empfänger', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Datum', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Aktionen', 'av-newsletter' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $all_campaigns as $c ) :
                        switch ( $c->status ) {
                            case 'draft':        $badge_class = 'av-badge-draft';    $badge_label = __( 'Entwurf',          'av-newsletter' ); break;
                            case 'sent':         $badge_class = 'av-badge-active';   $badge_label = __( 'Versendet',        'av-newsletter' ); break;
                            case 'sent_partial': $badge_class = 'av-badge-warning';  $badge_label = __( 'Teilweise',        'av-newsletter' ); break;
                            case 'failed':       $badge_class = 'av-badge-inactive'; $badge_label = __( 'Fehler',           'av-newsletter' ); break;
                            case 'sending':      $badge_class = 'av-badge-info';     $badge_label = __( 'Wird gesendet',    'av-newsletter' ); break;
                            case 'scheduled':    $badge_class = 'av-badge-pending';  $badge_label = __( 'Geplant',          'av-newsletter' ); break;
                            default:             $badge_class = 'av-badge-draft';    $badge_label = esc_html( $c->status ); break;
                        }
                        $date_col = ( $c->status === 'scheduled' && ! empty( $c->scheduled_at ) )
                            ? date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $c->scheduled_at ) )
                            : date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $c->created_at ) );
                    ?>
                        <tr>
                            <td>
                                <?php if ( $c->status === 'draft' ) : ?>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-mail-senden&draft_id=' . $c->id ) ); ?>">
                                        <strong><?php echo esc_html( $c->subject ?: __( '(kein Betreff)', 'av-newsletter' ) ); ?></strong>
                                    </a>
                                <?php else : ?>
                                    <strong><?php echo esc_html( $c->subject ?: '—' ); ?></strong>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $c->template_name ?? '–' ); ?></td>
                            <td>
                                <?php echo $c->group_name
                                    ? esc_html( $c->group_name )
                                    : '<em>' . esc_html__( 'Alle', 'av-newsletter' ) . '</em>'; ?>
                                <?php if ( $c->recipient_count ) : ?>
                                    <span class="av-table-meta">(<?php echo (int) $c->recipient_count; ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="av-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_label ); ?></span></td>
                            <td><?php echo esc_html( $date_col ); ?></td>
                            <td class="av-table-actions">
                                <?php if ( $c->status === 'draft' ) : ?>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-mail-senden&draft_id=' . $c->id ) ); ?>"
                                       class="av-btn av-btn-sm av-btn-secondary">
                                        <span class="dashicons dashicons-edit"></span>
                                        <?php esc_html_e( 'Bearbeiten', 'av-newsletter' ); ?>
                                    </a>
                                <?php endif; ?>
                                <button class="av-btn av-btn-sm av-btn-secondary avn-duplicate-campaign"
                                        data-id="<?php echo esc_attr( $c->id ); ?>"
                                        title="<?php esc_attr_e( 'Als Entwurf kopieren', 'av-newsletter' ); ?>">
                                    <span class="dashicons dashicons-admin-page"></span>
                                </button>
                                <button class="av-btn av-btn-sm av-btn-danger avn-delete-draft"
                                        data-id="<?php echo esc_attr( $c->id ); ?>">
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

</div><!-- .wrap -->

<?php else : // ── EDITOR ──────────────────────────────────────────────────── ?>
<?php
/* ── Editor-Variablen ──────────────────────────────────────────────────── */
$draft = $draft_id ? AVN_Database::get_campaign( $draft_id ) : null;

$draft_template_id  = $draft ? (int) $draft->template_id          : ( isset( $_GET['tpl'] ) ? (int) $_GET['tpl'] : 0 );
// NULL in DB = 'all' (alle Abonnenten); sonst Gruppen-ID
$draft_group_id     = $draft ? ( $draft->group_id === null ? 'all' : (int) $draft->group_id ) : '';
$draft_subject      = $draft ? $draft->subject                     : '';
$draft_from_name    = $draft ? $draft->sender_name                 : $sender_name;
$draft_from_email   = $draft ? $draft->sender_email                : $sender_email;
$draft_content_json = $draft ? ( $draft->content_json ?? '' )      : '';
$draft_scheduled_at = $draft ? ( $draft->scheduled_at ?? '' )      : '';

// Template für den Builder laden
$tpl_obj             = $draft_template_id ? AVN_Database::get_mail_template( $draft_template_id ) : null;
$initial_blocks_json = $tpl_obj ? ( $tpl_obj->body_json ?? '[]' ) : '[]';
$has_template        = ! empty( $tpl_obj );

// Wenn Entwurf gespeicherten Inhalt hat, diesen als initiale Blöcke nutzen
if ( $draft && ! empty( $draft->content_json ) && $draft->content_json !== '[]' ) {
    $initial_blocks_json = $draft->content_json;
}

// Builder-Daten als JSON für JS
$builder_data = json_encode( [
    'id'        => 0,
    'body_json' => json_decode( $initial_blocks_json, true ) ?: [],
] );
?>
<div class="wrap av-admin-container">

    <!-- Seitenheader mit Aktionsbuttons -->
    <div class="av-page-header">
        <h1 class="av-page-title">
            <span class="dashicons dashicons-email-alt"></span>
            <?php echo $draft_id
                ? esc_html__( 'Mail bearbeiten', 'av-newsletter' )
                : esc_html__( 'Neue Mail', 'av-newsletter' ); ?>
        </h1>
        <div class="av-header-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-mail-senden' ) ); ?>"
               class="av-btn av-btn-ghost">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Zur Liste', 'av-newsletter' ); ?>
            </a>
            <button id="av-preview-mail" class="av-btn av-btn-ghost" <?php echo ! $has_template ? 'disabled' : ''; ?>>
                <span class="dashicons dashicons-visibility"></span>
                <?php esc_html_e( 'Vorschau', 'av-newsletter' ); ?>
            </button>
            <button id="avn-save-draft" class="av-btn av-btn-secondary">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e( 'Entwurf speichern', 'av-newsletter' ); ?>
            </button>
            <button id="avn-start-campaign" class="av-btn av-btn-primary" <?php echo ! $has_template ? 'disabled' : ''; ?>>
                <span class="dashicons dashicons-email-alt"></span>
                <?php esc_html_e( 'Senden', 'av-newsletter' ); ?>
            </button>
        </div>
    </div>

    <!-- Verstecktes Feld für Draft-ID -->
    <input type="hidden" id="avn-draft-id" value="<?php echo esc_attr( $draft_id ?: '' ); ?>">

    <!-- Meta-Karte: Template, Empfänger, Betreff, Absender -->
    <div class="av-card" style="margin-bottom:16px;">
        <div class="av-card-body">
            <div class="av-form-row">
                <div class="av-col-6">
                    <label for="avn-campaign-template"><?php esc_html_e( 'Template', 'av-newsletter' ); ?></label>
                    <select id="avn-campaign-template" class="av-input">
                        <option value=""><?php esc_html_e( '— Template wählen —', 'av-newsletter' ); ?></option>
                        <?php foreach ( $templates as $tpl ) : ?>
                            <option value="<?php echo esc_attr( $tpl->id ); ?>"
                                    data-body-json="<?php echo esc_attr( $tpl->body_json ?? '[]' ); ?>"
                                    <?php selected( $draft_template_id, $tpl->id ); ?>>
                                <?php echo esc_html( $tpl->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="av-col-6">
                    <label for="avn-campaign-group"><?php esc_html_e( 'Empfänger', 'av-newsletter' ); ?></label>
                    <select id="avn-campaign-group" class="av-input">
                        <option value="" <?php selected( $draft_group_id, '' ); ?>><?php esc_html_e( '— Empfänger wählen —', 'av-newsletter' ); ?></option>
                        <option value="all" <?php selected( $draft_group_id, 'all' ); ?>><?php printf( esc_html__( 'Alle (%d aktiv)', 'av-newsletter' ), $total_active ); ?></option>
                        <?php foreach ( $groups as $g ) : ?>
                            <option value="<?php echo esc_attr( $g->id ); ?>"
                                    <?php selected( $draft_group_id, $g->id ); ?>>
                                <?php echo esc_html( $g->name ); ?>
                                (<?php echo esc_html( $g->subscriber_count ?? 0 ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="av-form-row">
                <div class="av-col-6">
                    <label for="avn-campaign-subject"><?php esc_html_e( 'Betreff', 'av-newsletter' ); ?></label>
                    <input type="text" id="avn-campaign-subject" class="av-input"
                           value="<?php echo esc_attr( $draft_subject ); ?>"
                           placeholder="<?php esc_attr_e( 'Betreff der Mail', 'av-newsletter' ); ?>">
                </div>
                <div class="av-col-6">
                    <label><?php esc_html_e( 'Absender', 'av-newsletter' ); ?></label>
                    <div class="av-input-group">
                        <input type="text"  id="avn-campaign-from-name"  class="av-input"
                               value="<?php echo esc_attr( $draft_from_name ); ?>"
                               placeholder="<?php esc_attr_e( 'Name', 'av-newsletter' ); ?>">
                        <input type="email" id="avn-campaign-from-email" class="av-input"
                               value="<?php echo esc_attr( $draft_from_email ); ?>"
                               placeholder="<?php esc_attr_e( 'E-Mail', 'av-newsletter' ); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Block-Builder -->
    <div id="av-mail-builder-app"
         data-mode="campaign"
         data-template="<?php echo esc_attr( $builder_data ); ?>"
         data-content-json="<?php echo esc_attr( $draft_content_json ); ?>">

        <!-- Palette (linke Spalte) -->
        <aside class="av-builder-palette">
            <h3><?php esc_html_e( 'Blöcke', 'av-newsletter' ); ?></h3>
            <ul class="av-palette-list">
                <?php
                $palette_items = [
                    [ 'type' => 'heading',  'icon' => 'dashicons-editor-textcolor', 'label' => __( 'Überschrift', 'av-newsletter' ) ],
                    [ 'type' => 'text',     'icon' => 'dashicons-editor-paragraph', 'label' => __( 'Text',        'av-newsletter' ) ],
                    [ 'type' => 'image',    'icon' => 'dashicons-format-image',     'label' => __( 'Bild',        'av-newsletter' ) ],
                    [ 'type' => 'button',   'icon' => 'dashicons-button',           'label' => __( 'Button',      'av-newsletter' ) ],
                    [ 'type' => 'divider',  'icon' => 'dashicons-minus',            'label' => __( 'Trennlinie',  'av-newsletter' ) ],
                    [ 'type' => 'spacer',   'icon' => 'dashicons-sort',             'label' => __( 'Abstand',     'av-newsletter' ) ],
                    [ 'type' => 'columns2', 'icon' => 'dashicons-layout',           'label' => __( '2 Spalten',   'av-newsletter' ) ],
                    [ 'type' => 'footer',   'icon' => 'dashicons-admin-generic',    'label' => __( 'Footer',      'av-newsletter' ) ],
                    [ 'type' => 'dynamic',  'icon' => 'dashicons-shortcode',        'label' => __( 'Dynamisch',   'av-newsletter' ) ],
                ];
                foreach ( $palette_items as $item ) :
                ?>
                    <li class="av-palette-item <?php echo ! $has_template ? 'av-disabled' : ''; ?>"
                        data-block-type="<?php echo esc_attr( $item['type'] ); ?>">
                        <span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>"></span>
                        <?php echo esc_html( $item['label'] ); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <!-- Canvas (Mitte) -->
        <main class="av-builder-canvas" id="av-mail-canvas-inner">
            <div id="av-campaign-builder-placeholder"
                 class="<?php echo $has_template ? 'av-hidden' : ''; ?>">
                <span class="dashicons dashicons-layout"></span>
                <strong><?php esc_html_e( 'Kein Template gewählt', 'av-newsletter' ); ?></strong>
                <p><?php esc_html_e( 'Bitte oben ein Template auswählen, um den Editor zu aktivieren.', 'av-newsletter' ); ?></p>
            </div>
        </main>

        <!-- Inspector: nur Block-Eigenschaften -->
        <aside class="av-builder-inspector" id="av-mail-inspector">
            <div class="av-inspector-block-props">
                <h3><?php esc_html_e( 'Block-Eigenschaften', 'av-newsletter' ); ?></h3>
                <p class="av-inspector-empty"><?php esc_html_e( 'Block zum Bearbeiten auswählen.', 'av-newsletter' ); ?></p>
                <div class="av-inspector-form av-hidden"></div>
            </div>
        </aside><!-- #av-mail-inspector -->

    </div><!-- #av-mail-builder-app -->

    <!-- ── Versand-Optionen (unterhalb des Builders) ───────────────────── -->
    <div class="av-card av-campaign-send-card">
        <div class="av-card-body">
            <div class="av-form-row">
                <div class="av-col-6">
                    <label><?php esc_html_e( 'Versandzeitpunkt', 'av-newsletter' ); ?></label>
                    <div class="av-radio-group av-radio-group-sm" style="flex-direction:row;gap:12px;">
                        <label class="av-radio-label">
                            <input type="radio" name="avn-send-when" value="now" checked>
                            <span class="av-radio-box">
                                <strong><?php esc_html_e( 'Sofort senden', 'av-newsletter' ); ?></strong>
                            </span>
                        </label>
                        <label class="av-radio-label">
                            <input type="radio" name="avn-send-when" value="scheduled">
                            <span class="av-radio-box">
                                <strong><?php esc_html_e( 'Geplant', 'av-newsletter' ); ?></strong>
                            </span>
                        </label>
                    </div>
                    <div id="avn-schedule-row" style="display:none;margin-top:10px;">
                        <label for="avn-scheduled-at"><?php esc_html_e( 'Datum & Uhrzeit', 'av-newsletter' ); ?></label>
                        <input type="datetime-local" id="avn-scheduled-at" class="av-input"
                               value="<?php echo $draft_scheduled_at ? esc_attr( date( 'Y-m-d\TH:i', strtotime( $draft_scheduled_at ) ) ) : ''; ?>">
                    </div>
                </div>
                <div class="av-col-6">
                    <label for="avn-test-email"><?php esc_html_e( 'Test-Mail senden', 'av-newsletter' ); ?></label>
                    <div class="av-input-group">
                        <input type="email" id="avn-test-email" class="av-input"
                               value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"
                               placeholder="test@example.com">
                        <button id="avn-btn-send-test" class="av-btn av-btn-secondary">
                            <span class="dashicons dashicons-email-alt"></span>
                            <?php esc_html_e( 'Test senden', 'av-newsletter' ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Vorschau-Modal -->
    <div id="av-mail-preview-modal" class="av-modal">
        <div class="av-modal-overlay"></div>
        <div class="av-modal-content av-modal-lg">
            <div class="av-modal-header">
                <h3><?php esc_html_e( 'Vorschau', 'av-newsletter' ); ?></h3>
                <button class="av-modal-close"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="av-modal-body" id="av-mail-preview-content"></div>
        </div>
    </div>

</div><!-- .wrap -->
<?php endif; ?>

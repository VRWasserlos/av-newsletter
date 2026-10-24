<?php
/**
 * Admin-View: Mail-Builder (Drag & Drop)
 *
 * @package VRW_Newsletter
 * @author  Kai Naumann <info@vereinsring-wasserlos.de>
 * @version 1.0.7
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$tpl_id   = isset( $_GET['template_id'] ) ? (int) $_GET['template_id'] : 0;
$tpl      = $tpl_id ? AVN_Database::get_mail_template( $tpl_id ) : null;
$tpl_data = $tpl ? [
    'id'        => $tpl->id,
    'name'      => $tpl->name,
    'subject'   => $tpl->subject,
    'body_json' => $tpl->body_json,
] : [
    'id'        => 0,
    'name'      => '',
    'subject'   => '',
    'body_json' => '[]',
];
?>
<div class="wrap av-admin-container">

    <div class="av-page-header">
        <h1 class="av-page-title">
            <span class="dashicons dashicons-email-alt"></span>
            <?php $tpl ? esc_html_e( 'Mail-Template bearbeiten', 'av-newsletter' ) : esc_html_e( 'Neues Mail-Template', 'av-newsletter' ); ?>
        </h1>
        <div class="av-header-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-mail-templates' ) ); ?>" class="av-btn av-btn-ghost">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Zur Übersicht', 'av-newsletter' ); ?>
            </a>
            <button id="av-preview-mail" class="av-btn av-btn-secondary">
                <span class="dashicons dashicons-visibility"></span>
                <?php esc_html_e( 'Vorschau', 'av-newsletter' ); ?>
            </button>
            <button id="av-save-mail-template" class="av-btn av-btn-primary">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e( 'Speichern', 'av-newsletter' ); ?>
            </button>
        </div>
    </div>

    <div id="av-mail-builder-app" data-template='<?php echo esc_attr( wp_json_encode( $tpl_data ) ); ?>'>

        <!-- Linke Palette: Block-Typen -->
        <aside class="av-builder-palette">
            <h3><?php esc_html_e( 'Mail-Blöcke', 'av-newsletter' ); ?></h3>
            <p class="av-palette-hint"><?php esc_html_e( 'Blöcke auf die Mail-Canvas ziehen', 'av-newsletter' ); ?></p>

            <div class="av-field-palette-grid">
                <?php
                $block_types = [
                    'content_area' => [ 'icon' => 'edit',              'label' => 'Inhalts-Bereich' ],
                    'heading'  => [ 'icon' => 'editor-paragraph', 'label' => 'Überschrift' ],
                    'text'     => [ 'icon' => 'editor-alignleft',  'label' => 'Text' ],
                    'image'    => [ 'icon' => 'format-image',      'label' => 'Bild' ],
                    'button'   => [ 'icon' => 'button',            'label' => 'Button' ],
                    'divider'  => [ 'icon' => 'minus',             'label' => 'Trennlinie' ],
                    'spacer'   => [ 'icon' => 'move',              'label' => 'Abstand' ],
                    'columns2' => [ 'icon' => 'columns',           'label' => '2 Spalten' ],
                    'social'   => [ 'icon' => 'share',             'label' => 'Social Links' ],
                    'footer'   => [ 'icon' => 'admin-generic',     'label' => 'Footer/Abmeldung' ],
                    'dynamic'  => [ 'icon' => 'tag',               'label' => 'Dynamischer Inhalt' ],
                ];
                foreach ( $block_types as $type => $meta ) : ?>
                    <div class="av-palette-item" draggable="true" data-block-type="<?php echo esc_attr( $type ); ?>">
                        <span class="dashicons dashicons-<?php echo esc_attr( $meta['icon'] ); ?>"></span>
                        <span><?php echo esc_html( $meta['label'] ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="av-palette-vars">
                <h4><?php esc_html_e( 'Template-Platzhalter', 'av-newsletter' ); ?></h4>
                <ul>
                    <li><code>{{site_name}}</code></li>
                    <li><code>{{unsubscribe_link}}</code></li>
                    <li><code>{{confirm_link}}</code></li>
                </ul>
            </div>
        </aside>

        <!-- Mail-Canvas -->
        <main class="av-builder-canvas">

            <!-- Template-Metadaten -->
            <div class="av-card av-builder-meta-card">
                <div class="av-card-header">
                    <h3><?php esc_html_e( 'Mail-Einstellungen', 'av-newsletter' ); ?></h3>
                </div>
                <div class="av-card-body">
                    <div class="av-form-row">
                        <div class="av-col-6">
                            <label><?php esc_html_e( 'Template-Name', 'av-newsletter' ); ?> *</label>
                            <input type="text" id="av-tpl-title" class="av-input"
                                   value="<?php echo esc_attr( $tpl_data['name'] ); ?>"
                                   placeholder="<?php esc_attr_e( 'z.B. Willkommensmail', 'av-newsletter' ); ?>">
                        </div>
                        <div class="av-col-6">
                            <label><?php esc_html_e( 'Betreff', 'av-newsletter' ); ?> *</label>
                            <input type="text" id="av-tpl-subject" class="av-input"
                                   value="<?php echo esc_attr( $tpl_data['subject'] ); ?>"
                                   placeholder="<?php esc_attr_e( 'Willkommen bei {{site_name}}', 'av-newsletter' ); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mail-Canvas -->
            <div class="av-mail-canvas-wrapper">
                <div class="av-mail-canvas-header">
                    <span><?php esc_html_e( 'Mail-Vorschau', 'av-newsletter' ); ?></span>
                    <div>
                        <button id="av-mail-canvas-desktop" class="av-btn av-btn-sm av-btn-secondary av-active">
                            <span class="dashicons dashicons-desktop"></span>
                        </button>
                        <button id="av-mail-canvas-mobile" class="av-btn av-btn-sm av-btn-secondary">
                            <span class="dashicons dashicons-smartphone"></span>
                        </button>
                    </div>
                </div>

                <div class="av-mail-canvas" id="av-mail-canvas">
                    <div class="av-mail-canvas-inner" id="av-mail-canvas-inner">
                        <div class="av-mail-drop-placeholder">
                            <span class="dashicons dashicons-download"></span>
                            <p><?php esc_html_e( 'Block hierher ziehen', 'av-newsletter' ); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Rechte Seitenleiste: Block-Eigenschaften -->
        <aside class="av-builder-inspector" id="av-mail-inspector">
            <h3><?php esc_html_e( 'Block-Eigenschaften', 'av-newsletter' ); ?></h3>
            <p class="av-inspector-empty"><?php esc_html_e( 'Block zum Bearbeiten auswählen.', 'av-newsletter' ); ?></p>
            <div class="av-inspector-form av-hidden"></div>
        </aside>

    </div><!-- #av-mail-builder-app -->

    <!-- Vorschau-Modal -->
    <div id="av-mail-preview-modal" class="av-modal">
        <div class="av-modal-overlay"></div>
        <div class="av-modal-content av-modal-lg">
            <div class="av-modal-header">
                <h3><?php esc_html_e( 'Mail-Vorschau', 'av-newsletter' ); ?></h3>
                <button class="av-modal-close"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="av-modal-body" id="av-mail-preview-content"></div>
        </div>
    </div>

</div>

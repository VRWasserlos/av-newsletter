<?php
/**
 * Admin-View: Einstellungen
 *
 * @package VRW_Newsletter
 * @author  Kai Naumann <info@vereinsring-wasserlos.de>
 * @version 1.0.7
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$sender_name       = get_option( 'avn_sender_name', get_bloginfo( 'name' ) );
$sender_email      = get_option( 'avn_sender_email', get_option( 'admin_email' ) );
$doi_enabled       = get_option( 'avn_doi_enabled', '1' );
$log_retention     = get_option( 'avn_log_retention', '90' );
$unsubscribe_page  = get_option( 'avn_unsubscribe_page', '' );

// E-Mail-Konfiguration
$mail_method      = get_option( 'avn_mail_method', 'wp_mail' ); // 'wp_mail' | 'smtp'
$smtp_host        = get_option( 'avn_smtp_host',        '' );
$smtp_port        = get_option( 'avn_smtp_port',        '587' );
$smtp_encryption  = get_option( 'avn_smtp_encryption',  'tls' ); // 'tls' | 'ssl' | 'none'
$smtp_auth        = get_option( 'avn_smtp_auth',        '1' );
$smtp_user        = get_option( 'avn_smtp_user',        '' );
// Passwort niemals in die View rendern – Platzhalter anzeigen wenn gesetzt
$smtp_pass_set    = ( get_option( 'avn_smtp_pass', '' ) !== '' );

// SPF-Empfehlung anhand SMTP-Host ermitteln
$spf_include = '';
$_host = strtolower( $smtp_host );
if     ( str_contains( $_host, 'gmx.'    ) )                                          $spf_include = 'include:gmx.net';
elseif ( str_contains( $_host, 'web.de'  ) )                                          $spf_include = 'include:web.de';
elseif ( str_contains( $_host, 'ionos.'  ) || str_contains( $_host, '1und1.'  ) )     $spf_include = 'include:ionos.de';
elseif ( str_contains( $_host, 'strato.' ) )                                          $spf_include = 'include:strato.de';
elseif ( str_contains( $_host, 'hetzner.') )                                          $spf_include = 'mx';
elseif ( str_contains( $_host, 'gmail.'  ) || str_contains( $_host, 'google.' ) )     $spf_include = 'include:_spf.google.com';
elseif ( str_contains( $_host, 'outlook.') || str_contains( $_host, 'office365.') )   $spf_include = 'include:spf.protection.outlook.com';
elseif ( $smtp_host !== '' ) {
    // Unbekannter Host: IP-Platzhalter
    $spf_include = 'ip4:<deine-server-ip>';
}
$spf_record     = $spf_include ? 'v=spf1 ' . $spf_include . ' ~all' : '';
$sender_domain  = $sender_email && str_contains( $sender_email, '@' )
    ? substr( $sender_email, strpos( $sender_email, '@' ) + 1 )
    : '';

$tab = sanitize_key( $_GET['settings_tab'] ?? 'allgemein' );

$pages = get_pages();

// ── Cron-Daten ────────────────────────────────────────────────────────────
$cron_interval      = get_option( 'avn_cron_interval', '5' );  // Minuten
$cron_batch_size    = get_option( 'avn_cron_batch_size', '20' );
$next_cron          = wp_next_scheduled( 'avn_mail_queue_cron' );
$cron_active        = (bool) $next_cron;

global $wpdb;
$queued_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}av_mail_log WHERE status = 'queued'" );
$failed_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}av_mail_log WHERE status = 'failed'" );
$sent_today    = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}av_mail_log WHERE status = 'sent' AND created_at >= %s",
    gmdate( 'Y-m-d 00:00:00' )
) );

// ── Debug-Log-Daten ───────────────────────────────────────────────────────
$log_entries = [];
if ( $tab === 'debug' ) {
    $log_limit   = min( 200, max( 1, (int) ( $_GET['log_limit'] ?? 50 ) ) );
    $log_status  = sanitize_key( $_GET['log_status'] ?? '' );
    $log_where   = $log_status ? $wpdb->prepare( "WHERE status = %s", $log_status ) : '';
    $log_entries = $wpdb->get_results(
        "SELECT id, campaign_id, recipient_email, subject, status, error_message, created_at
         FROM {$wpdb->prefix}av_mail_log
         {$log_where}
         ORDER BY created_at DESC
         LIMIT {$log_limit}"
    );
    $log_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}av_mail_log {$log_where}" );
}
?>
<div class="wrap av-admin-container">

    <div class="av-page-header">
        <h1 class="av-page-title">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e( 'Einstellungen', 'av-newsletter' ); ?>
        </h1>
        <button id="avn-settings-save" class="av-btn av-btn-primary">
            <span class="dashicons dashicons-saved"></span>
            <?php esc_html_e( 'Speichern', 'av-newsletter' ); ?>
        </button>
    </div>

    <!-- Tab-Navigation -->
    <nav class="av-tab-nav" style="margin-bottom:24px;">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-einstellungen&settings_tab=allgemein' ) ); ?>"
           class="av-tab-link <?php echo $tab === 'allgemein' ? 'av-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e( 'Allgemein', 'av-newsletter' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-einstellungen&settings_tab=email' ) ); ?>"
           class="av-tab-link <?php echo $tab === 'email' ? 'av-tab-active' : ''; ?>">
            <span class="dashicons dashicons-email-alt"></span>
            <?php esc_html_e( 'E-Mail-Versand', 'av-newsletter' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-einstellungen&settings_tab=cron' ) ); ?>"
           class="av-tab-link <?php echo $tab === 'cron' ? 'av-tab-active' : ''; ?>">
            <span class="dashicons dashicons-clock"></span>
            <?php esc_html_e( 'Cron / Queue', 'av-newsletter' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=avn-einstellungen&settings_tab=debug' ) ); ?>"
           class="av-tab-link <?php echo $tab === 'debug' ? 'av-tab-active' : ''; ?>">
            <span class="dashicons dashicons-search"></span>
            <?php esc_html_e( 'Debug / Log', 'av-newsletter' ); ?>
        </a>
    </nav>

    <form id="avn-settings-form">
        <?php wp_nonce_field( 'avn_save_settings', 'avn_settings_nonce' ); ?>
        <!-- aktiver Tab als Hidden-Field für den JS-Handler -->
        <input type="hidden" name="avn_settings_tab" value="<?php echo esc_attr( $tab ); ?>">

        <?php if ( $tab === 'allgemein' ) : ?>

        <!-- ── Absender ───────────────────────────────────────────────────── -->
        <div class="av-card">
            <div class="av-card-header av-collapsible-header">
                <h3>
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php esc_html_e( 'Absender', 'av-newsletter' ); ?>
                </h3>
                <span class="dashicons dashicons-arrow-down-alt2 av-collapse-icon"></span>
            </div>
            <div class="av-card-body av-collapsible-body">
                <div class="av-form-row">
                    <div class="av-col-6">
                        <label for="avn_sender_name"><?php esc_html_e( 'Absender-Name', 'av-newsletter' ); ?></label>
                        <input type="text" id="avn_sender_name" name="avn_sender_name" class="av-input"
                               value="<?php echo esc_attr( $sender_name ); ?>"
                               placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                    </div>
                    <div class="av-col-6">
                        <label for="avn_sender_email"><?php esc_html_e( 'Absender-E-Mail', 'av-newsletter' ); ?></label>
                        <input type="email" id="avn_sender_email" name="avn_sender_email" class="av-input"
                               value="<?php echo esc_attr( $sender_email ); ?>"
                               placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Double-Opt-In ─────────────────────────────────────────────── -->
        <div class="av-card">
            <div class="av-card-header av-collapsible-header">
                <h3>
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e( 'Double-Opt-In', 'av-newsletter' ); ?>
                </h3>
                <span class="dashicons dashicons-arrow-down-alt2 av-collapse-icon"></span>
            </div>
            <div class="av-card-body av-collapsible-body">
                <div class="av-form-row">
                    <div class="av-col-12">
                        <label class="av-toggle-label">
                            <input type="checkbox" id="avn_doi_enabled" name="avn_doi_enabled"
                                   value="1" <?php checked( '1', $doi_enabled ); ?>>
                            <span class="av-toggle"></span>
                            <?php esc_html_e( 'Double-Opt-In aktivieren (Bestätigungs-E-Mail bei neuen Abonnenten)', 'av-newsletter' ); ?>
                        </label>
                    </div>
                </div>
                <div class="av-form-row">
                    <div class="av-col-6">
                        <label for="avn_unsubscribe_page"><?php esc_html_e( 'Abmelde-Seite', 'av-newsletter' ); ?></label>
                        <select id="avn_unsubscribe_page" name="avn_unsubscribe_page" class="av-select">
                            <option value=""><?php esc_html_e( '— Keine Seite ausgewählt —', 'av-newsletter' ); ?></option>
                            <?php foreach ( $pages as $page ) : ?>
                                <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $unsubscribe_page, $page->ID ); ?>>
                                    <?php echo esc_html( $page->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="av-hint">
                            <?php esc_html_e( 'Diese Seite erhält den Abmelde-Link in ausgesendeten Mails ({{unsubscribe_link}}).', 'av-newsletter' ); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Protokoll ─────────────────────────────────────────────────── -->
        <div class="av-card">
            <div class="av-card-header av-collapsible-header">
                <h3>
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e( 'Mail-Protokoll', 'av-newsletter' ); ?>
                </h3>
                <span class="dashicons dashicons-arrow-down-alt2 av-collapse-icon"></span>
            </div>
            <div class="av-card-body av-collapsible-body">
                <div class="av-form-row">
                    <div class="av-col-4">
                        <label for="avn_log_retention"><?php esc_html_e( 'Protokoll-Aufbewahrung (Tage)', 'av-newsletter' ); ?></label>
                        <input type="number" id="avn_log_retention" name="avn_log_retention" class="av-input"
                               value="<?php echo esc_attr( $log_retention ); ?>" min="1" max="365">
                        <p class="av-hint">
                            <?php esc_html_e( 'Mail-Protokolleinträge älter als diese Anzahl Tage werden automatisch gelöscht.', 'av-newsletter' ); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ( $tab === 'email' ) : ?>

        <!-- ── E-Mail-Versandmethode ──────────────────────────────────────── -->
        <div class="av-card">
            <div class="av-card-body">
                <div class="av-form-row">
                    <div class="av-col-12">
                        <label><?php esc_html_e( 'Versandmethode', 'av-newsletter' ); ?></label>
                        <div class="av-radio-group">
                            <label class="av-radio-label">
                                <input type="radio" name="avn_mail_method" value="wp_mail"
                                       id="avn-method-wp" <?php checked( $mail_method, 'wp_mail' ); ?>>
                                <span class="av-radio-box">
                                    <strong><?php esc_html_e( 'WordPress (Standard)', 'av-newsletter' ); ?></strong>
                                    <span class="av-hint"><?php esc_html_e( 'Nutzt die eingebaute WordPress-Mailfunktion (wp_mail). Einfach, kein Setup nötig.', 'av-newsletter' ); ?></span>
                                </span>
                            </label>
                            <label class="av-radio-label">
                                <input type="radio" name="avn_mail_method" value="smtp"
                                       id="avn-method-smtp" <?php checked( $mail_method, 'smtp' ); ?>>
                                <span class="av-radio-box">
                                    <strong><?php esc_html_e( 'SMTP (eigener Mailserver)', 'av-newsletter' ); ?></strong>
                                    <span class="av-hint"><?php esc_html_e( 'Direkter Versand über einen SMTP-Server (z.B. Gmail, Ionos, Strato, eigener Server).', 'av-newsletter' ); ?></span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── SMTP-Einstellungen ─────────────────────────────────────────── -->
        <div class="av-card" id="avn-smtp-fields" <?php echo $mail_method !== 'smtp' ? 'style="display:none;"' : ''; ?>>
            <div class="av-card-header">
                <h3>
                    <span class="dashicons dashicons-networking"></span>
                    <?php esc_html_e( 'SMTP-Konfiguration', 'av-newsletter' ); ?>
                </h3>
            </div>
            <div class="av-card-body">

                <div class="av-form-row">
                    <div class="av-col-8">
                        <label for="avn_smtp_host"><?php esc_html_e( 'SMTP-Host', 'av-newsletter' ); ?></label>
                        <input type="text" id="avn_smtp_host" name="avn_smtp_host" class="av-input"
                               value="<?php echo esc_attr( $smtp_host ); ?>"
                               placeholder="z.B. smtp.gmail.com oder mail.ihre-domain.de">
                    </div>
                    <div class="av-col-2">
                        <label for="avn_smtp_port"><?php esc_html_e( 'Port', 'av-newsletter' ); ?></label>
                        <input type="number" id="avn_smtp_port" name="avn_smtp_port" class="av-input"
                               value="<?php echo esc_attr( $smtp_port ); ?>" min="1" max="65535"
                               placeholder="587">
                    </div>
                    <div class="av-col-2">
                        <label for="avn_smtp_encryption"><?php esc_html_e( 'Verschlüsselung', 'av-newsletter' ); ?></label>
                        <select id="avn_smtp_encryption" name="avn_smtp_encryption" class="av-select">
                            <option value="tls"  <?php selected( $smtp_encryption, 'tls' ); ?>>STARTTLS (Port 587)</option>
                            <option value="ssl"  <?php selected( $smtp_encryption, 'ssl' ); ?>>SSL/TLS (Port 465)</option>
                            <option value="none" <?php selected( $smtp_encryption, 'none' ); ?>><?php esc_html_e( 'Keine', 'av-newsletter' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="av-form-row">
                    <div class="av-col-12">
                        <label class="av-toggle-label">
                            <input type="checkbox" id="avn_smtp_auth" name="avn_smtp_auth"
                                   value="1" <?php checked( '1', $smtp_auth ); ?>>
                            <span class="av-toggle"></span>
                            <?php esc_html_e( 'SMTP-Authentifizierung verwenden', 'av-newsletter' ); ?>
                        </label>
                    </div>
                </div>

                <div class="av-form-row" id="avn-smtp-auth-fields" <?php echo $smtp_auth !== '1' ? 'style="display:none;"' : ''; ?>>
                    <div class="av-col-6">
                        <label for="avn_smtp_user"><?php esc_html_e( 'Benutzername / E-Mail', 'av-newsletter' ); ?></label>
                        <input type="text" id="avn_smtp_user" name="avn_smtp_user" class="av-input"
                               value="<?php echo esc_attr( $smtp_user ); ?>"
                               autocomplete="off"
                               placeholder="benutzername@ihre-domain.de">
                    </div>
                    <div class="av-col-6">
                        <label for="avn_smtp_pass">
                            <?php esc_html_e( 'Passwort', 'av-newsletter' ); ?>
                            <?php if ( $smtp_pass_set ) : ?>
                                <span class="av-hint"> — <?php esc_html_e( 'bereits gesetzt. Leer lassen zum Beibehalten.', 'av-newsletter' ); ?></span>
                            <?php endif; ?>
                        </label>
                        <input type="password" id="avn_smtp_pass" name="avn_smtp_pass" class="av-input"
                               value=""
                               autocomplete="new-password"
                               placeholder="<?php echo $smtp_pass_set ? '••••••••' : esc_attr__( 'Passwort eingeben', 'av-newsletter' ); ?>">
                    </div>
                </div>

                <!-- Hinweis: Test-Button wurde in separaten Bereich verschoben -->

                <?php if ( $smtp_host !== '' && $spf_record !== '' ) : ?>
                <!-- SPF-Hinweis -->
                <div class="av-form-row" style="margin-top:16px; padding-top:16px; border-top:1px solid var(--av-border);">
                    <div class="av-col-12">
                        <div class="av-notice av-notice-info" style="margin:0;">
                            <strong>SPF-DNS-Eintrag</strong><br>
                            Damit E-Mails nicht im Spam-Ordner landen, muss folgender SPF-Eintrag im DNS der Absender-Domain
                            <?php if ( $sender_domain ) : ?><strong>(<?php echo esc_html( $sender_domain ); ?>)</strong><?php endif; ?>
                            eingetragen sein:<br><br>
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <code id="avn-spf-record" style="background:var(--av-bg,#f5f5f5);padding:6px 12px;border-radius:4px;font-size:13px;flex:1;min-width:200px;"><?php echo esc_html( $spf_record ); ?></code>
                                <button type="button" class="av-btn av-btn-secondary av-btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('avn-spf-record').textContent).then(function(){var b=this;b.textContent='✓ Kopiert';setTimeout(function(){b.textContent='Kopieren';},2000);}.bind(this));">
                                    Kopieren
                                </button>
                            </div>
                            <p style="margin:8px 0 0; font-size:12px; color:var(--av-text-muted,#666);">
                                DNS-Eintragstyp: <strong>TXT</strong> &nbsp;·&nbsp;
                                Name: <strong>@</strong> (oder leer) &nbsp;·&nbsp;
                                Falls bereits ein SPF-Eintrag existiert, <strong>nicht</strong> einen zweiten anlegen, sondern den bestehenden um <code><?php echo esc_html( $spf_include ); ?></code> erweitern.
                            </p>
                        </div>
                    </div>
                </div>
                <?php elseif ( $mail_method === 'smtp' && $smtp_host === '' ) : ?>
                <div class="av-form-row" style="margin-top:16px; padding-top:16px; border-top:1px solid var(--av-border);">
                    <div class="av-col-12">
                        <div class="av-notice av-notice-info" style="margin:0;">
                            <strong>SPF-DNS-Eintrag</strong><br>
                            Um die passende SPF-Empfehlung für Ihren SMTP-Host zu sehen, speichern Sie bitte zuerst die SMTP-Einstellungen.
                        </div>
                    </div>
                </div>
                <?php elseif ( $mail_method === 'wp_mail' && $sender_domain !== '' ) : ?>
                <div class="av-form-row" style="margin-top:16px; padding-top:16px; border-top:1px solid var(--av-border);">
                    <div class="av-col-12">
                        <div class="av-notice av-notice-info" style="margin:0;">
                            <strong>SPF-DNS-Eintrag</strong><br>
                            Für den Versand über WordPress <code>wp_mail()</code> sollten Sie einen SPF-Eintrag für die Absender-Domain
                            <strong>(<?php echo esc_html( $sender_domain ); ?>)</strong> beim Hosting-Anbieter einrichten.
                            Bitte nutzen Sie dazu die SPF-Angaben Ihres Providers.
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- ── Test-Mail (beide Versandmethoden) ──────────────────────────────── -->
        <div class="av-card">
            <div class="av-card-header">
                <h3>
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php esc_html_e( 'Test-Mail senden', 'av-newsletter' ); ?>
                </h3>
            </div>
            <div class="av-card-body">
                <div class="av-form-row">
                    <div class="av-col-8">
                        <label for="avn-test-email-universal"><?php esc_html_e( 'E-Mail-Adresse', 'av-newsletter' ); ?></label>
                        <div class="av-input-group">
                            <input type="email" id="avn-test-email-universal" class="av-input"
                                   value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"
                                   placeholder="test@example.com">
                            <button type="button" id="avn-btn-test-mail" class="av-btn av-btn-secondary">
                                <span class="dashicons dashicons-email"></span>
                                <?php esc_html_e( 'Test-Mail senden', 'av-newsletter' ); ?>
                            </button>
                        </div>
                        <p class="av-hint">
                            <?php 
                            if ( $mail_method === 'smtp' ) {
                                esc_html_e( 'Sendet eine Test-Mail über SMTP (dein konfigurierter Mailserver).', 'av-newsletter' );
                            } else {
                                esc_html_e( 'Sendet eine Test-Mail über WordPress wp_mail() (Standardversand).', 'av-newsletter' );
                            }
                            ?>
                        </p>
                    </div>
                    <div class="av-col-4" id="avn-test-result" style="padding-top:32px;"></div>
                </div>
            </div>
        </div>

        <!-- Hinweis wp_mail -->
        <div id="avn-wpmail-info" <?php echo $mail_method !== 'wp_mail' ? 'style="display:none;"' : ''; ?>>
            <div class="av-notice av-notice-info">
                <strong><?php esc_html_e( 'WordPress-Mailversand aktiv.', 'av-newsletter' ); ?></strong>
                <?php esc_html_e( 'Mails werden über die WordPress-Funktion wp_mail() versendet. Für professionellen Versand empfehlen wir SMTP mit einem dedizierten Absender-Server.', 'av-newsletter' ); ?>
            </div>
        </div>

        <?php elseif ( $tab === 'debug' ) : ?>

        <!-- ── Debug: Mail-Log ───────────────────────────────────────────── -->
        <div class="av-card">
            <div class="av-card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <h3 style="margin:0;">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e( 'Mail-Log', 'av-newsletter' ); ?>
                    <span class="av-count-badge"><?php echo esc_html( isset( $log_total ) ? $log_total : 0 ); ?></span>
                </h3>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <!-- Filter -->
                    <form method="get" style="display:flex;gap:6px;align-items:center;">
                        <input type="hidden" name="page" value="avn-einstellungen">
                        <input type="hidden" name="settings_tab" value="debug">
                        <select name="log_status" class="av-select" style="height:32px;font-size:12px;" onchange="this.form.submit()">
                            <option value="" <?php selected( $log_status ?? '', '' ); ?>><?php esc_html_e( 'Alle Status', 'av-newsletter' ); ?></option>
                            <option value="queued"  <?php selected( $log_status ?? '', 'queued' ); ?>><?php esc_html_e( 'In Warteschlange', 'av-newsletter' ); ?></option>
                            <option value="sent"    <?php selected( $log_status ?? '', 'sent' ); ?>><?php esc_html_e( 'Versendet', 'av-newsletter' ); ?></option>
                            <option value="failed"  <?php selected( $log_status ?? '', 'failed' ); ?>><?php esc_html_e( 'Fehlgeschlagen', 'av-newsletter' ); ?></option>
                        </select>
                        <select name="log_limit" class="av-select" style="height:32px;font-size:12px;" onchange="this.form.submit()">
                            <option value="50"  <?php selected( $log_limit ?? 50, 50 ); ?>>50</option>
                            <option value="100" <?php selected( $log_limit ?? 50, 100 ); ?>>100</option>
                            <option value="200" <?php selected( $log_limit ?? 50, 200 ); ?>>200</option>
                        </select>
                    </form>
                    <button type="button" id="avn-log-clear-failed" class="av-btn av-btn-ghost av-btn-sm"
                            style="color:#991b1b;border-color:#fca5a5;">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e( 'Fehlgeschlagene löschen', 'av-newsletter' ); ?>
                    </button>
                    <button type="button" id="avn-log-clear-all" class="av-btn av-btn-ghost av-btn-sm"
                            style="color:#6b7280;">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e( 'Gesamtes Log löschen', 'av-newsletter' ); ?>
                    </button>
                </div>
            </div>
            <div class="av-card-body" style="padding:0;overflow-x:auto;">
                <?php if ( empty( $log_entries ) ) : ?>
                    <div class="av-empty-state-box">
                        <span class="dashicons dashicons-search av-empty-icon"></span>
                        <p><?php esc_html_e( 'Keine Log-Einträge gefunden.', 'av-newsletter' ); ?></p>
                    </div>
                <?php else : ?>
                <table class="av-table" style="margin:0;">
                    <thead>
                        <tr>
                            <th style="width:40px;">ID</th>
                            <th><?php esc_html_e( 'Empfänger', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Betreff', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Fehlermeldung', 'av-newsletter' ); ?></th>
                            <th><?php esc_html_e( 'Datum', 'av-newsletter' ); ?></th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $log_entries as $entry ) :
                        switch ( $entry->status ) {
                            case 'sent':   $bc = 'av-badge-active';   $bl = __( 'Versendet',       'av-newsletter' ); break;
                            case 'queued': $bc = 'av-badge-pending';  $bl = __( 'Warteschlange',   'av-newsletter' ); break;
                            case 'failed': $bc = 'av-badge-inactive'; $bl = __( 'Fehlgeschlagen',  'av-newsletter' ); break;
                            default:       $bc = 'av-badge-draft';    $bl = esc_html( $entry->status );
                        }
                    ?>
                        <tr>
                            <td style="color:var(--av-gray-400);font-size:11px;"><?php echo esc_html( $entry->id ); ?></td>
                            <td><code style="font-size:12px;"><?php echo esc_html( $entry->recipient_email ); ?></code></td>
                            <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr( $entry->subject ); ?>">
                                <?php echo esc_html( $entry->subject ?: '—' ); ?>
                            </td>
                            <td><span class="av-badge <?php echo esc_attr( $bc ); ?>"><?php echo esc_html( $bl ); ?></span></td>
                            <td style="color:#991b1b;font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                title="<?php echo esc_attr( $entry->error_message ?? '' ); ?>">
                                <?php echo esc_html( $entry->error_message ?: '' ); ?>
                            </td>
                            <td style="font-size:12px;white-space:nowrap;">
                                <?php echo esc_html( date_i18n( 'd.m.Y H:i:s', strtotime( $entry->created_at ) ) ); ?>
                            </td>
                            <td>
                                <button type="button" class="av-btn av-btn-ghost av-btn-sm avn-log-delete-entry"
                                        data-id="<?php echo esc_attr( $entry->id ); ?>"
                                        title="<?php esc_attr_e( 'Eintrag löschen', 'av-newsletter' ); ?>">
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

        <?php elseif ( $tab === 'cron' ) : ?>

        <!-- ── Cron-Status ───────────────────────────────────────────────── -->
        <div class="av-card">
            <div class="av-card-header">
                <h3>
                    <span class="dashicons dashicons-clock"></span>
                    <?php esc_html_e( 'Cron-Status', 'av-newsletter' ); ?>
                </h3>
            </div>
            <div class="av-card-body">
                <div class="av-form-row" style="gap:16px;">
                    <div class="av-stat-box">
                        <div class="av-stat-value <?php echo $cron_active ? 'av-text-success' : 'av-text-danger'; ?>">
                            <?php echo $cron_active
                                ? '<span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Aktiv', 'av-newsletter' )
                                : '<span class="dashicons dashicons-warning"></span> ' . esc_html__( 'Inaktiv', 'av-newsletter' ); ?>
                        </div>
                        <div class="av-stat-label"><?php esc_html_e( 'WP-Cron', 'av-newsletter' ); ?></div>
                    </div>
                    <div class="av-stat-box">
                        <div class="av-stat-value"><?php echo esc_html( $next_cron ? date_i18n( 'd.m.Y H:i:s', $next_cron ) : '—' ); ?></div>
                        <div class="av-stat-label"><?php esc_html_e( 'Nächster Lauf', 'av-newsletter' ); ?></div>
                    </div>
                    <div class="av-stat-box">
                        <div class="av-stat-value av-text-warning"><?php echo esc_html( $queued_count ); ?></div>
                        <div class="av-stat-label"><?php esc_html_e( 'In Warteschlange', 'av-newsletter' ); ?></div>
                    </div>
                    <div class="av-stat-box">
                        <div class="av-stat-value av-text-success"><?php echo esc_html( $sent_today ); ?></div>
                        <div class="av-stat-label"><?php esc_html_e( 'Heute versendet', 'av-newsletter' ); ?></div>
                    </div>
                    <div class="av-stat-box">
                        <div class="av-stat-value <?php echo $failed_count > 0 ? 'av-text-danger' : ''; ?>"><?php echo esc_html( $failed_count ); ?></div>
                        <div class="av-stat-label"><?php esc_html_e( 'Fehlgeschlagen', 'av-newsletter' ); ?></div>
                    </div>
                </div>

                <?php if ( ! $cron_active ) : ?>
                <div class="av-notice av-notice-error" style="margin-top:16px;">
                    <strong><?php esc_html_e( 'WP-Cron ist nicht registriert!', 'av-newsletter' ); ?></strong>
                    <?php esc_html_e( 'Die Mail-Queue wird nicht automatisch abgearbeitet. Plugin bitte deaktivieren und erneut aktivieren.', 'av-newsletter' ); ?>
                </div>
                <?php endif; ?>

                <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="button" id="avn-cron-run-now" class="av-btn av-btn-secondary">
                        <span class="dashicons dashicons-controls-play"></span>
                        <?php esc_html_e( 'Queue jetzt ausführen', 'av-newsletter' ); ?>
                    </button>
                    <?php if ( $failed_count > 0 ) : ?>
                    <button type="button" id="avn-cron-retry-failed" class="av-btn av-btn-ghost">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e( 'Fehlgeschlagene erneut versuchen', 'av-newsletter' ); ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Cron-Konfiguration ────────────────────────────────────────── -->
        <div class="av-card">
            <div class="av-card-header">
                <h3>
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e( 'Konfiguration', 'av-newsletter' ); ?>
                </h3>
            </div>
            <div class="av-card-body">
                <div class="av-form-row">
                    <div class="av-col-4">
                        <label for="avn_cron_interval"><?php esc_html_e( 'Ausführungsintervall (Minuten)', 'av-newsletter' ); ?></label>
                        <input type="number" id="avn_cron_interval" name="avn_cron_interval" class="av-input"
                               value="<?php echo esc_attr( $cron_interval ); ?>" min="1" max="60">
                        <p class="av-hint"><?php esc_html_e( 'Wie oft der Cron die Queue abarbeitet. Änderung erfordert Plugin-Neustart.', 'av-newsletter' ); ?></p>
                    </div>
                    <div class="av-col-4">
                        <label for="avn_cron_batch_size"><?php esc_html_e( 'Mails pro Lauf (Batch-Größe)', 'av-newsletter' ); ?></label>
                        <input type="number" id="avn_cron_batch_size" name="avn_cron_batch_size" class="av-input"
                               value="<?php echo esc_attr( $cron_batch_size ); ?>" min="1" max="200">
                        <p class="av-hint"><?php esc_html_e( 'Maximale Anzahl Mails die pro Cron-Lauf versendet werden.', 'av-newsletter' ); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; ?>

    </form>
</div>

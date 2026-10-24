<?php
/**
 * Aktivierung: Newsletter-Tabellen erstellen
 *
 * @package VRW_Newsletter
 * @author  Kai Naumann <info@vereinsring-wasserlos.de>
 * @version 1.0.7
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AVN_Activator {

    public static function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( "CREATE TABLE {$wpdb->prefix}av_subscribers (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email       VARCHAR(191)    NOT NULL,
            first_name  VARCHAR(100)    NOT NULL DEFAULT '',
            last_name   VARCHAR(100)    NOT NULL DEFAULT '',
            status      VARCHAR(30)     NOT NULL DEFAULT 'pending',
            token       VARCHAR(64)     NOT NULL DEFAULT '',
            created_at  DATETIME        NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY status (status)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}av_groups (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(191)    NOT NULL,
            description TEXT,
            created_at  DATETIME        NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}av_subscriber_groups (
            subscriber_id BIGINT UNSIGNED NOT NULL,
            group_id      BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (subscriber_id, group_id),
            KEY group_id (group_id)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}av_mail_templates (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name           VARCHAR(191)    NOT NULL DEFAULT '',
            subject        VARCHAR(191)    NOT NULL DEFAULT '',
            body_json      LONGTEXT,
            body_html      LONGTEXT,
            trigger_type   VARCHAR(30)     NOT NULL DEFAULT 'manual',
            recipient      VARCHAR(30)     NOT NULL DEFAULT 'subscriber',
            recipient_meta VARCHAR(191)    DEFAULT NULL,
            status         VARCHAR(20)     NOT NULL DEFAULT 'active',
            created_at     DATETIME        NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}av_mail_log (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id     BIGINT UNSIGNED DEFAULT NULL,
            template_id     BIGINT UNSIGNED DEFAULT NULL,
            submission_id   BIGINT UNSIGNED DEFAULT NULL,
            recipient_email VARCHAR(191)    NOT NULL,
            subject         VARCHAR(191)    NOT NULL DEFAULT '',
            sender_name     VARCHAR(191)    NOT NULL DEFAULT '',
            sender_email    VARCHAR(191)    NOT NULL DEFAULT '',
            status          VARCHAR(20)     NOT NULL DEFAULT 'queued',
            error_message   TEXT,
            created_at      DATETIME        NOT NULL DEFAULT '0000-00-00 00:00:00',
            sent_at         DATETIME        DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY campaign_id (campaign_id),
            KEY template_id (template_id)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}av_campaigns (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            template_id     BIGINT UNSIGNED NOT NULL,
            group_id        BIGINT UNSIGNED DEFAULT NULL,
            subject         VARCHAR(191)    NOT NULL DEFAULT '',
            sender_name     VARCHAR(191)    NOT NULL DEFAULT '',
            sender_email    VARCHAR(191)    NOT NULL DEFAULT '',
            content_html    LONGTEXT        DEFAULT NULL,
            content_json    LONGTEXT        DEFAULT NULL,
            scheduled_at    DATETIME        DEFAULT NULL,
            sent_at         DATETIME        DEFAULT NULL,
            status          VARCHAR(20)     NOT NULL DEFAULT 'queued',
            recipient_count INT UNSIGNED    NOT NULL DEFAULT 0,
            created_at      DATETIME        NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) $charset;" );

        update_option( 'avn_db_version', AVN_VERSION );
    }
}

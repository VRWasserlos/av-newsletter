/**
 * AV Newsletter – Admin-JS
 * Behandelt: Modals, Abonnenten, Gruppen, Kampagnen, Mail-Templates, Einstellungen
 */
/* global avnAdmin, jQuery */
(function ($) {
    'use strict';

    /* ── Hilfsfunktionen ─────────────────────────────────────────────────── */

    function avnAjax(action, data, success, fail) {
        data = $.extend({ action: action, nonce: avnAdmin.nonce }, data);
        $.post(avnAdmin.ajaxurl, data)
            .done(function (res) {
                if (res.success) {
                    success && success(res.data);
                } else {
                    fail && fail(res.data);
                }
            })
            .fail(function () {
                fail && fail(avnAdmin.i18n.error);
            });
    }

    function avnNotice(msg, type) {
        type = type || 'success';
        var $n = $('<div class="av-notice av-notice-' + type + '">' + $('<span>').text(msg).html() + '</div>');
        $('.av-page-header').after($n);
        setTimeout(function () { $n.fadeOut(400, function () { $n.remove(); }); }, 3200);
    }

    /* ── Modal ───────────────────────────────────────────────────────────── */

    $(document).on('click', '.av-modal-close, .av-modal-overlay', function () {
        $(this).closest('.av-modal').removeClass('av-active');
        $('body').removeClass('av-modal-open');
    });

    $(document).on('keyup', function (e) {
        if (e.key === 'Escape') {
            $('.av-modal.av-active').removeClass('av-active');
            $('body').removeClass('av-modal-open');
        }
    });

    function openModal($modal) {
        $modal.addClass('av-active');
        $('body').addClass('av-modal-open');
    }

    /* ── Collapsible Cards ───────────────────────────────────────────────── */

    $(document).on('click', '.av-collapsible-header', function () {
        var $body = $(this).next('.av-collapsible-body');
        var $icon = $(this).find('.av-collapse-icon');
        $body.slideToggle(200);
        $icon.toggleClass('dashicons-arrow-up-alt2 dashicons-arrow-down-alt2');
    });

    /* ── Abonnenten ──────────────────────────────────────────────────────── */

    // Modal öffnen – Hinzufügen
    $(document).on('click', '#av-open-add-subscriber', function () {
        $('#av-subscriber-modal-title').text(avnAdmin.i18n.add_subscriber || 'Abonnent hinzufügen');
        $('#av-sub-id').val('');
        $('#av-sub-email').val('').prop('readonly', false);
        $('#av-sub-firstname').val('');
        $('#av-sub-lastname').val('');
        $('#av-sub-status').val('active');
        $('#av-sub-groups .av-group-check').prop('checked', false);
        openModal($('#av-subscriber-modal'));
        setTimeout(function () { $('#av-sub-email').focus(); }, 100);
    });

    // Modal öffnen – Bearbeiten
    $(document).on('click', '.av-edit-subscriber', function () {
        var d = $(this).data('subscriber');
        if (typeof d === 'string') { try { d = JSON.parse(d); } catch (e) { return; } }

        $('#av-subscriber-modal-title').text(avnAdmin.i18n.edit_subscriber || 'Abonnent bearbeiten');
        $('#av-sub-id').val(d.id);
        $('#av-sub-email').val(d.email).prop('readonly', true);
        $('#av-sub-firstname').val(d.first_name || '');
        $('#av-sub-lastname').val(d.last_name || '');
        $('#av-sub-status').val(d.status || 'active');

        var groups = d.groups || [];
        $('#av-sub-groups .av-group-check').each(function () {
            $(this).prop('checked',
                groups.indexOf($(this).val()) !== -1 ||
                groups.indexOf(parseInt($(this).val())) !== -1
            );
        });

        openModal($('#av-subscriber-modal'));
    });

    // Speichern
    $(document).on('click', '#av-save-subscriber', function () {
        var email = $('#av-sub-email').val().trim();
        if (!email) { avnNotice(avnAdmin.i18n.email_required || 'Bitte E-Mail eingeben.', 'error'); return; }

        var groupIds = [];
        $('#av-sub-groups .av-group-check:checked').each(function () {
            groupIds.push($(this).val());
        });

        var $btn = $(this).prop('disabled', true);
        avnAjax('avn_save_subscriber', {
            id:         $('#av-sub-id').val(),
            email:      email,
            first_name: $('#av-sub-firstname').val(),
            last_name:  $('#av-sub-lastname').val(),
            status:     $('#av-sub-status').val(),
            groups:     groupIds,
        }, function () {
            $('#av-subscriber-modal').removeClass('av-active');
            $('body').removeClass('av-modal-open');
            avnNotice(avnAdmin.i18n.saved);
            setTimeout(function () { location.reload(); }, 800);
        }, function (msg) {
            avnNotice(msg || avnAdmin.i18n.error, 'error');
            $btn.prop('disabled', false);
        });
    });

    // Löschen
    $(document).on('click', '.av-delete-subscriber', function () {
        if (!confirm(avnAdmin.i18n.confirm_delete)) { return; }
        var id   = $(this).data('id');
        var $row = $(this).closest('tr');
        avnAjax('avn_delete_subscriber', { id: id }, function () {
            $row.fadeOut(300, function () { $row.remove(); });
            avnNotice(avnAdmin.i18n.saved);
        }, function () {
            avnNotice(avnAdmin.i18n.error, 'error');
        });
    });

    // Select-All-Checkbox
    $(document).on('change', '#av-select-all-subscribers', function () {
        $('.av-subscriber-check').prop('checked', $(this).prop('checked'));
    });

    /* ── Gruppen ─────────────────────────────────────────────────────────── */

    // Modal öffnen – Hinzufügen
    $(document).on('click', '#av-open-add-group', function () {
        $('#av-group-modal-title').text(avnAdmin.i18n.add_group || 'Gruppe hinzufügen');
        $('#av-group-id').val('');
        $('#av-group-name').val('');
        $('#av-group-description').val('');
        openModal($('#av-group-modal'));
        setTimeout(function () { $('#av-group-name').focus(); }, 100);
    });

    // Modal öffnen – Bearbeiten
    $(document).on('click', '.av-edit-group', function () {
        var d = $(this).data('group');
        if (typeof d === 'string') { try { d = JSON.parse(d); } catch (e) { return; } }

        $('#av-group-modal-title').text(avnAdmin.i18n.edit_group || 'Gruppe bearbeiten');
        $('#av-group-id').val(d.id);
        $('#av-group-name').val(d.name || '');
        $('#av-group-description').val(d.description || '');
        openModal($('#av-group-modal'));
    });

    // Speichern
    $(document).on('click', '#av-save-group', function () {
        var name = $('#av-group-name').val().trim();
        if (!name) { avnNotice(avnAdmin.i18n.name_required || 'Bitte Gruppen-Name eingeben.', 'error'); return; }

        var $btn = $(this).prop('disabled', true);
        avnAjax('avn_save_group', {
            id:          $('#av-group-id').val(),
            name:        name,
            description: $('#av-group-description').val(),
        }, function () {
            $('#av-group-modal').removeClass('av-active');
            $('body').removeClass('av-modal-open');
            avnNotice(avnAdmin.i18n.saved);
            setTimeout(function () { location.reload(); }, 800);
        }, function (msg) {
            avnNotice(msg || avnAdmin.i18n.error, 'error');
            $btn.prop('disabled', false);
        });
    });

    // Löschen
    $(document).on('click', '.av-delete-group', function () {
        if (!confirm(avnAdmin.i18n.confirm_delete)) { return; }
        var id   = $(this).data('id');
        var $row = $(this).closest('tr');
        avnAjax('avn_delete_group', { id: id }, function () {
            $row.fadeOut(300, function () { $row.remove(); });
            avnNotice(avnAdmin.i18n.saved);
        }, function () {
            avnNotice(avnAdmin.i18n.error, 'error');
        });
    });

    /* ── Mail-Templates: Löschen ─────────────────────────────────────────── */

    $(document).on('click', '.av-delete-mail-template', function () {
        if (!confirm(avnAdmin.i18n.confirm_delete)) { return; }
        var id   = $(this).data('id');
        var $row = $(this).closest('tr');
        avnAjax('avn_delete_mail_template', { id: id }, function () {
            $row.fadeOut(300, function () { $row.remove(); });
            avnNotice(avnAdmin.i18n.saved);
        }, function () {
            avnNotice(avnAdmin.i18n.error, 'error');
        });
    });

    /* ── Einstellungen: E-Mail-Tab Interaktivität ────────────────────────── */

    // Versandmethode umschalten
    $(document).on('change', 'input[name="avn_mail_method"]', function () {
        var method = $(this).val();
        if (method === 'smtp') {
            $('#avn-smtp-fields').slideDown(200);
            $('#avn-wpmail-info').slideUp(200);
        } else {
            $('#avn-smtp-fields').slideUp(200);
            $('#avn-wpmail-info').slideDown(200);
        }
    });

    // SMTP-Auth-Felder ein-/ausblenden
    $(document).on('change', '#avn_smtp_auth', function () {
        if ($(this).prop('checked')) {
            $('#avn-smtp-auth-fields').slideDown(200);
        } else {
            $('#avn-smtp-auth-fields').slideUp(200);
        }
    });

    // SMTP Test-Mail senden
    $(document).on('click', '#avn-smtp-send-test', function () {
        var testEmail = $('#avn-smtp-test-email').val().trim()
            || $('#avn-smtp-test-email').attr('placeholder');
        if (!testEmail) { return; }

        var $btn    = $(this).prop('disabled', true);
        var $result = $('#avn-smtp-test-result').html('<em>Sende…</em>');

        avnAjax('avn_send_smtp_test', { test_email: testEmail }, function (data) {
            var html = '<div class="av-notice av-notice-success">Test-Mail erfolgreich gesendet!</div>';
            if ( data && typeof data.debug === 'string' && data.debug.trim() !== '' ) {
                html += '<div class="av-notice av-notice-info" style="margin-top:8px;white-space:pre-wrap;font-family:monospace;">' + $('<span>').text(data.debug).html() + '</div>';
            }
            $result.html(html);
            $btn.prop('disabled', false);
        }, function (msg) {
            var message = avnAdmin.i18n.error;
            var debug   = '';

            if ( msg && typeof msg === 'object' ) {
                if ( typeof msg.message === 'string' && msg.message.trim() !== '' ) {
                    message = msg.message;
                } else if ( typeof msg.data === 'object' && typeof msg.data.message === 'string' ) {
                    message = msg.data.message;
                } else {
                    message = JSON.stringify(msg, null, 2);
                }
                if ( typeof msg.debug === 'string' && msg.debug.trim() !== '' ) {
                    debug = msg.debug;
                } else if ( typeof msg.data === 'object' && typeof msg.data.debug === 'string' ) {
                    debug = msg.data.debug;
                }
            } else if ( typeof msg === 'string' ) {
                message = msg;
            }

            if ( typeof message !== 'string' ) {
                message = JSON.stringify(message, null, 2);
            }
            if ( typeof debug !== 'string' ) {
                debug = JSON.stringify(debug, null, 2);
            }

            var html = '<div class="av-notice av-notice-error"><strong>Test-Mail fehlgeschlagen:</strong><br>' + $('<span>').text(message).html() + '</div>';
            if ( debug ) {
                html += '<div class="av-notice av-notice-error" style="margin-top:8px;white-space:pre-wrap;font-family:monospace;">' + $('<span>').text(debug).html() + '</div>';
            }
            $result.html(html);
            $btn.prop('disabled', false);
        });
    });

    // Cron: Queue manuell ausführen
    $(document).on('click', '#avn-cron-run-now', function () {
        var $btn = $(this).prop('disabled', true).text('Wird ausgeführt…');
        avnAjax('avn_cron_run_now', {}, function (data) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> Queue jetzt ausführen');
            avnNotice('Ausgeführt. In Warteschlange: ' + data.queued + ' | Fehlgeschlagen: ' + data.failed + ' | Heute gesendet: ' + data.sent_today);
            setTimeout(function () { location.reload(); }, 1500);
        }, function () {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> Queue jetzt ausführen');
            avnNotice('Fehler beim Ausführen.', 'error');
        });
    });

    // Cron: Fehlgeschlagene Mails erneut in Queue
    $(document).on('click', '#avn-cron-retry-failed', function () {
        var $btn = $(this).prop('disabled', true);
        avnAjax('avn_cron_retry_failed', {}, function (data) {
            avnNotice(data.requeued + ' Mail(s) erneut in die Warteschlange gestellt.');
            setTimeout(function () { location.reload(); }, 1200);
        }, function () {
            $btn.prop('disabled', false);
            avnNotice('Fehler.', 'error');
        });
    });

    // Log: Einzelnen Eintrag löschen
    $(document).on('click', '.avn-log-delete-entry', function () {
        var id  = $(this).data('id');
        var $tr = $(this).closest('tr');
        avnAjax('avn_log_delete_entry', { id: id }, function () {
            $tr.fadeOut(200, function () { $tr.remove(); });
        }, function () {
            avnNotice('Fehler beim Löschen.', 'error');
        });
    });

    // Log: Fehlgeschlagene löschen
    $(document).on('click', '#avn-log-clear-failed', function () {
        if (!confirm('Alle fehlgeschlagenen Einträge löschen?')) { return; }
        var $btn = $(this).prop('disabled', true);
        avnAjax('avn_log_clear', { scope: 'failed' }, function () {
            avnNotice('Fehlgeschlagene Einträge gelöscht.');
            setTimeout(function () { location.reload(); }, 800);
        }, function () {
            $btn.prop('disabled', false);
            avnNotice('Fehler.', 'error');
        });
    });

    // Log: Gesamtes Log löschen
    $(document).on('click', '#avn-log-clear-all', function () {
        if (!confirm('Wirklich das gesamte Mail-Log löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) { return; }
        var $btn = $(this).prop('disabled', true);
        avnAjax('avn_log_clear', { scope: 'all' }, function () {
            avnNotice('Mail-Log geleert.');
            setTimeout(function () { location.reload(); }, 800);
        }, function () {
            $btn.prop('disabled', false);
            avnNotice('Fehler.', 'error');
        });
    });

    /* ── Einstellungen speichern ─────────────────────────────────────────── */

    $(document).on('click', '#avn-settings-save', function () {
        var $btn  = $(this).prop('disabled', true);
        var $form = $('#avn-settings-form');
        var formData = $form.serializeArray();
        var data = { action: 'avn_save_settings', nonce: avnAdmin.nonce };
        $.each(formData, function (i, f) {
            // Radiobuttons + Text: alle gesetzten Werte erfassen
            data[f.name] = f.value;
        });
        // Unchecked Checkboxen explizit als 0 senden
        $form.find('input[type="checkbox"]').each(function () {
            if (!$(this).prop('checked')) {
                data[$(this).attr('name')] = '0';
            }
        });

        $.post(avnAdmin.ajaxurl, data)
            .done(function (res) {
                if (res.success) {
                    avnNotice(avnAdmin.i18n.saved);
                    // Seite nach 800ms neu laden, damit Änderungen sichtbar werden
                    // (z.B. Versandmethode-Umschaltung, Felder aus-/einblenden)
                    setTimeout(function () { location.reload(); }, 800);
                } else {
                    avnNotice(avnAdmin.i18n.error, 'error');
                }
            })
            .always(function () { $btn.prop('disabled', false); });
    });

    /* ── Platzhalter kopieren ────────────────────────────────────────────── */

    $(document).on('click', '.av-palette-vars code', function () {
        var text = $(this).text();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () {
                avnNotice('"' + text + '" ' + (avnAdmin.i18n.copied || 'kopiert!'));
            });
        }
    });

    /* ── Mail senden (mail-senden.php) ───────────────────────────────────── */

    // Zusammenfassung aktualisieren
    function avnUpdateSummary() {
        if (!$('#avn-campaign-subject').length) { return; }
        var $tplOpt  = $('#avn-campaign-template option:selected');
        var $grpOpt  = $('#avn-campaign-group option:selected');
        var subject  = ($('#avn-campaign-subject').val() || '').trim();
        var sendWhen = $('input[name="avn-send-when"]:checked').val();

        // Template
        if ($tplOpt.val()) {
            $('#avn-summary-template').text($tplOpt.text().trim());
        } else {
            $('#avn-summary-template').html('<em>' + (avnAdmin.i18n.select_template || '—') + '</em>');
        }

        // Empfänger
        var cnt = $grpOpt.data('count');
        if (cnt !== undefined) {
            $('#avn-summary-recipients').text(cnt + ' Abonnenten');
        }

        // Betreff
        if (subject) {
            $('#avn-summary-subject').text(subject);
        } else if ($tplOpt.val()) {
            var tplSubject = $tplOpt.data('subject');
            $('#avn-summary-subject').text(tplSubject || '—');
        } else {
            $('#avn-summary-subject').html('<em>Aus Template</em>');
        }

        // Versandzeitpunkt
        if (sendWhen === 'scheduled') {
            var dt = $('#avn-scheduled-at').val();
            $('#avn-summary-when').text(dt ? new Date(dt).toLocaleString('de-DE') : 'Geplant (Datum fehlt)');
        } else {
            $('#avn-summary-when').text('Sofort');
        }
    }

    // Template-Dropdown → Betreff vorausfüllen + Edit-Link
    $(document).on('change', '#avn-campaign-template', function () {
        var $opt     = $(this).find('option:selected');
        var subject  = $opt.data('subject') || '';
        var editUrl  = $opt.data('edit-url') || '';

        // Betreff-Feld: Placeholder setzen (nicht den Wert überschreiben wenn schon was drin)
        $('#avn-campaign-subject').attr('placeholder',
            subject ? subject : 'Leer lassen, um Betreff aus dem Template zu übernehmen'
        );

        // Edit-Link
        var $wrap = $('#avn-template-link-wrap');
        if (editUrl && $opt.val()) {
            $wrap.html(
                '<a href="' + editUrl + '" target="_blank">' +
                '<span class="dashicons dashicons-edit"></span> Template bearbeiten</a>'
            );
        } else {
            $wrap.empty();
        }

        // Builder laden / entladen
        var tplId = $opt.val();
        if (tplId && window.avnCampaignBuilder) {
            var bodyJson = $opt.data('body-json') || '[]';
            window.avnCampaignBuilder.load(bodyJson);
            $('#av-campaign-builder-placeholder').hide();
            $('#av-mail-builder-app .av-palette-item').removeClass('av-disabled').attr('draggable', 'true');
            $('#av-preview-mail').prop('disabled', false);
        } else if (!tplId && window.avnCampaignBuilder) {
            window.avnCampaignBuilder.load('[]');
            $('#av-campaign-builder-placeholder').show();
            $('#av-mail-builder-app .av-palette-item').addClass('av-disabled').removeAttr('draggable');
            $('#av-preview-mail').prop('disabled', true);
        }

        avnUpdateSummary();
    });

    // Gruppe → Zusammenfassung
    $(document).on('change', '#avn-campaign-group', avnUpdateSummary);

    // Betreff-Eingabe → Zusammenfassung
    $(document).on('input', '#avn-campaign-subject', avnUpdateSummary);

    // Radio Sofort/Geplant
    $(document).on('change', 'input[name="avn-send-when"]', function () {
        if ($(this).val() === 'scheduled') {
            $('#avn-schedule-row').slideDown(200);
        } else {
            $('#avn-schedule-row').slideUp(200);
        }
        avnUpdateSummary();
    });

    // datetime-local → Zusammenfassung
    $(document).on('change', '#avn-scheduled-at', avnUpdateSummary);

    // Draft speichern
    $(document).on('click', '#avn-save-draft', function () {
        var templateId  = $('#avn-campaign-template').val();
        var groupId     = $('#avn-campaign-group').val();
        var subject     = $('#avn-campaign-subject').val() ? $('#avn-campaign-subject').val().trim() : '';
        var senderName  = $('#avn-campaign-from-name').val() ? $('#avn-campaign-from-name').val().trim() : '';
        var senderEmail = $('#avn-campaign-from-email').val() ? $('#avn-campaign-from-email').val().trim() : '';
        var draftId     = $('#avn-draft-id').val();
        var scheduledAt = $('input[name="avn-send-when"]:checked').val() === 'scheduled'
                            ? $('#avn-scheduled-at').val() : '';

        var contentJson = window.avnCampaignBuilder ? window.avnCampaignBuilder.getJson() : '[]';

        var $btn = $(this).prop('disabled', true);
        avnAjax('avn_save_draft', {
            draft_id:     draftId,
            template_id:  templateId,
            group_id:     groupId,
            subject:      subject,
            sender_name:  senderName,
            sender_email: senderEmail,
            content_json: contentJson,
            scheduled_at: scheduledAt,
        }, function (data) {
            $('#avn-draft-id').val(data.id);
            // URL aktualisieren ohne Neuladen
            if (window.history && window.history.replaceState) {
                var url = new URL(window.location.href);
                url.searchParams.set('draft_id', data.id);
                window.history.replaceState({}, '', url.toString());
            }
            avnNotice('Entwurf gespeichert!');
            $btn.prop('disabled', false);
            // Sidebar-Liste nach kurzer Zeit aktualisieren
            setTimeout(function () { location.reload(); }, 1000);
        }, function (msg) {
            avnNotice(msg || avnAdmin.i18n.error, 'error');
            $btn.prop('disabled', false);
        });
    });

    // Draft / Kampagne löschen
    $(document).on('click', '.av-delete-draft, .avn-delete-draft', function () {
        if (!confirm(avnAdmin.i18n.confirm_delete || 'Wirklich löschen?')) { return; }
        var id  = $(this).data('id');
        var $tr = $(this).closest('tr');
        avnAjax('avn_delete_draft', { id: id }, function () {
            $tr.fadeOut(300, function () { $tr.remove(); });
            avnNotice('Gelöscht.');
        }, function () {
            avnNotice(avnAdmin.i18n.error, 'error');
        });
    });

    // Kampagne duplizieren
    $(document).on('click', '.avn-duplicate-campaign', function () {
        var id   = $(this).data('id');
        var $btn = $(this).prop('disabled', true);
        avnAjax('avn_duplicate_campaign', { id: id }, function (data) {
            avnNotice('Kopie erstellt!');
            // Zur neuen Kopie (Entwurf) wechseln
            window.location.href = avnAdmin.adminurl
                ? avnAdmin.adminurl + 'admin.php?page=avn-mail-senden&draft_id=' + data.id
                : 'admin.php?page=avn-mail-senden&draft_id=' + data.id;
        }, function () {
            $btn.prop('disabled', false);
            avnNotice(avnAdmin.i18n.error, 'error');
        });
    });

    // Test-Mail senden
    $(document).on('click', '#avn-btn-send-test', function () {
        var templateId = $('#avn-campaign-template').val();
        var testEmail  = $('#avn-test-email').val().trim();

        if (!templateId) {
            avnNotice(avnAdmin.i18n.select_template || 'Bitte zuerst ein Template wählen.', 'error');
            return;
        }
        if (!testEmail) {
            avnNotice('Bitte eine Test-E-Mail-Adresse eingeben.', 'error');
            return;
        }

        var contentJson = window.avnCampaignBuilder ? window.avnCampaignBuilder.getJson() : '[]';

        var $btn = $(this).prop('disabled', true);
        avnAjax('avn_send_test_mail', {
            template_id:  templateId,
            test_email:   testEmail,
            content_json: contentJson,
        }, function () {
            avnNotice(avnAdmin.i18n.test_sent || 'Test-Mail gesendet!');
            $btn.prop('disabled', false);
        }, function (msg) {
            avnNotice(msg || avnAdmin.i18n.error, 'error');
            $btn.prop('disabled', false);
        });
    });

    // Kampagne starten
    $(document).on('click', '#avn-start-campaign', function () {
        var templateId   = $('#avn-campaign-template').val();
        var groupId      = $('#avn-campaign-group').val();
        var subject      = $('#avn-campaign-subject').val() ? $('#avn-campaign-subject').val().trim() : '';
        var senderName   = $('#avn-campaign-from-name').val() ? $('#avn-campaign-from-name').val().trim() : '';
        var senderEmail  = $('#avn-campaign-from-email').val() ? $('#avn-campaign-from-email').val().trim() : '';
        var sendWhen     = $('input[name="avn-send-when"]:checked').val();
        var scheduledAt  = sendWhen === 'scheduled' ? $('#avn-scheduled-at').val() : '';

        var contentJson = window.avnCampaignBuilder ? window.avnCampaignBuilder.getJson() : '[]';

        if (!templateId) {
            avnNotice(avnAdmin.i18n.select_template || 'Bitte zuerst ein Template wählen.', 'error');
            return;
        }
        if (sendWhen === 'scheduled' && !scheduledAt) {
            avnNotice('Bitte Datum und Uhrzeit für den geplanten Versand angeben.', 'error');
            return;
        }

        var $btn = $(this).prop('disabled', true);
        avnAjax('avn_create_campaign', {
            template_id:  templateId,
            group_id:     groupId,
            subject:      subject,
            sender_name:  senderName,
            sender_email: senderEmail,
            scheduled_at: scheduledAt,
            content_json: contentJson,
            draft_id:     $('#avn-draft-id').val(),
        }, function (data) {
            var msg = (data && data.status === 'scheduled')
                ? (avnAdmin.i18n.campaign_sched || 'Kampagne wurde geplant.')
                : (avnAdmin.i18n.campaign_sent  || 'Kampagne wurde gestartet!');
            avnNotice(msg);
            $btn.prop('disabled', false);
            // Seite nach kurzer Zeit neu laden, um Kampagnenhistorie zu aktualisieren
            setTimeout(function () { location.reload(); }, 1500);
        }, function (msg) {
            avnNotice(msg || avnAdmin.i18n.error, 'error');
            $btn.prop('disabled', false);
        });
    });

    // Init: Schedule-Row verstecken
    $(function () {
        $('#avn-schedule-row').hide();
        avnUpdateSummary();
    });

    /* ── Abonnenten-Import ───────────────────────────────────────────────── */

    // Import-Modal öffnen
    $(document).on('click', '#av-open-import', function () {
        $('#av-import-file').val('');
        $('#av-import-duplicate').val('skip');
        $('#av-import-default-status').val('active');
        $('#av-import-group').val('');
        $('#av-import-result').hide().empty();
        $('#av-start-import').prop('disabled', false)
            .find('.dashicons').attr('class', 'dashicons dashicons-upload');
        openModal($('#av-import-modal'));
    });

    // Import starten
    $(document).on('click', '#av-start-import', function () {
        var fileInput = document.getElementById('av-import-file');
        if (!fileInput || !fileInput.files.length) {
            avnNotice(avnAdmin.i18n.import_no_file || 'Bitte eine XLSX-Datei auswählen.', 'error');
            return;
        }

        var fd = new FormData();
        fd.append('action',             'avn_import_subscribers');
        fd.append('nonce',              avnAdmin.nonce);
        fd.append('xlsx_file',          fileInput.files[0]);
        fd.append('duplicate_handling', $('#av-import-duplicate').val());
        fd.append('default_status',     $('#av-import-default-status').val());
        fd.append('default_group_id',   $('#av-import-group').val());

        var $btn = $(this).prop('disabled', true).text(avnAdmin.i18n.importing || 'Importiere…');
        $('#av-import-result').hide().empty();

        $.ajax({
            url:         avnAdmin.ajaxurl,
            type:        'POST',
            data:        fd,
            processData: false,
            contentType: false,
        }).done(function (res) {
            if (res.success) {
                var d = res.data;
                var rows = [];
                if (d.sheets > 1) {
                    rows.push('<p>' + d.sheets + ' Tabellenblätter (Gruppen) eingelesen</p>');
                }
                rows.push('<p><strong>' + d.imported + '</strong> Abonnenten neu importiert</p>');
                if (d.updated   > 0) { rows.push('<p><strong>' + d.updated        + '</strong> aktualisiert</p>'); }
                if (d.skipped   > 0) { rows.push('<p><strong>' + d.skipped        + '</strong> übersprungen (Duplikate)</p>'); }
                if (d.groups_created > 0) { rows.push('<p><strong>' + d.groups_created + '</strong> neue Gruppe(n) angelegt</p>'); }
                if (d.errors && d.errors.length) {
                    var errHtml = '<details><summary><strong>' + d.errors.length + ' Fehler/Hinweise</strong></summary><ul>';
                    $.each(d.errors, function (i, e) { errHtml += '<li>' + $('<span>').text(e).html() + '</li>'; });
                    errHtml += '</ul></details>';
                    rows.push(errHtml);
                }
                var cls = d.imported > 0 ? 'av-notice-success' : 'av-notice-info';
                $('#av-import-result').html(
                    '<div class="av-notice ' + cls + '">' + rows.join('') + '</div>'
                ).show();

                if (d.imported > 0 || d.updated > 0) {
                    setTimeout(function () { location.reload(); }, 2200);
                }
            } else {
                avnNotice(res.data || 'Import fehlgeschlagen.', 'error');
            }
        }).fail(function () {
            avnNotice('Verbindungsfehler beim Import.', 'error');
        }).always(function () {
            $btn.prop('disabled', false).html(
                '<span class="dashicons dashicons-upload"></span> ' +
                (avnAdmin.i18n.import_btn || 'Importieren')
            );
        });
    });

    // Universeller Test-Mail-Button (Einstellungen-Tab)
    $(document).on('click', '#avn-btn-test-mail', function () {
        var testEmail = $('#avn-test-email-universal').val().trim() || $('#avn-test-email-universal').attr('placeholder');
        if (!testEmail) {
            avnNotice('Bitte eine E-Mail-Adresse eingeben.', 'error');
            return;
        }

        var $btn    = $(this).prop('disabled', true);
        var $result = $('#avn-test-result').html('<em>Sende...</em>');

        avnAjax('avn_test_mail_settings', { test_email: testEmail }, function (data) {
            var msg = (data && data.message) ? data.message : 'Test-Mail erfolgreich versendet!';
            var html = '<div class="av-notice av-notice-success">' + msg + '</div>';
            $result.html(html);
            $btn.prop('disabled', false);
        }, function (msg) {
            var message = 'Test-Mail fehlgeschlagen.';
            if (msg && typeof msg === 'object') {
                if (msg.message) {
                    message = msg.message;
                } else if (msg.data && msg.data.message) {
                    message = msg.data.message;
                }
            } else if (typeof msg === 'string') {
                message = msg;
            }
            var html = '<div class="av-notice av-notice-error"><strong>' + message + '</strong></div>';
            $result.html(html);
            $btn.prop('disabled', false);
        });
    });

}(jQuery));

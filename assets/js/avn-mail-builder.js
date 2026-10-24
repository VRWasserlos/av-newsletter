/**
 * AV Newsletter – Mail Builder JS
 * Drag & Drop Mail-Template-Editor
 */
/* global avnAdmin, jQuery */
(function ($) {
    'use strict';

    /* ── State ───────────────────────────────────────────────────────────── */
    var tplData = {
        id: 0, name: '', subject: '', body_json: [],
    };

    var selectedBlockIdx = null;
    var _blockIdCounter  = 1;
    var isCampaign       = false;
    var quillInstances   = {};

    /* ── Init ───────────────────────────────────────────────────── */

    function init() {
        var $app = $('#av-mail-builder-app');
        if (!$app.length) { return; }

        isCampaign = $app.data('mode') === 'campaign';

        var raw = $app.data('template');
        if (raw) {
            tplData.id      = raw.id      || 0;
            tplData.name    = raw.name    || '';
            tplData.subject = raw.subject || '';
            var rawBj = raw.body_json;
            if (Array.isArray(rawBj)) {
                tplData.body_json = rawBj;
            } else {
                try { tplData.body_json = JSON.parse(rawBj || '[]'); } catch (e) { tplData.body_json = []; }
            }
        }

        // Meta-Felder nur im Template-Modus befüllen
        if (!isCampaign) {
            $('#av-tpl-title').val(tplData.name);
            $('#av-tpl-subject').val(tplData.subject);
        }

        renderCanvas();
        initPaletteDrag();

        $('#av-mail-canvas-desktop').on('click', function () {
            $('#av-mail-canvas-inner').removeClass('av-mobile-view');
            $(this).addClass('av-active');
            $('#av-mail-canvas-mobile').removeClass('av-active');
        });

        $('#av-mail-canvas-mobile').on('click', function () {
            $('#av-mail-canvas-inner').addClass('av-mobile-view');
            $(this).addClass('av-active');
            $('#av-mail-canvas-desktop').removeClass('av-active');
        });

        if (!isCampaign) {
            // Template-Builder: Speichern-Button + Vorschau binden
            $('#av-save-mail-template').on('click', saveTemplate);
            $('#av-preview-mail').on('click', showPreview);
        } else {
            // Campaign-Modus: API für avn-admin.js bereitstellen
            window.avnCampaignBuilder = {
                /**
                 * Blöcke aus Template laden und Canvas neu rendern.
                 * @param {string|Array} blocksJson JSON-String oder Array
                 */
                load: function (blocksJson) {
                    try {
                        tplData.body_json = typeof blocksJson === 'string'
                            ? JSON.parse(blocksJson || '[]')
                            : (blocksJson || []);
                    } catch (e) {
                        tplData.body_json = [];
                    }
                    selectedBlockIdx = null;
                    hideInspector();
                    renderCanvas();
                },
                /** Aktuellen Block-JSON-String zurückgeben */
                getJson: function () {
                    return JSON.stringify(tplData.body_json);
                },
            };
            // Vorschau-Button im Campaign-Editor
            $('#av-preview-mail').on('click', showPreview);

            // Beim Seitenaufruf: Template laden, gespeicherter Draft-Inhalt hat Vorrang
            var $preselected = $('#avn-campaign-template option:selected');
            var savedContent = $app.attr('data-content-json') || '';

            if ($preselected.val()) {
                // Template-Struktur laden (Fallback)
                var preJson = $preselected.data('body-json') || '[]';
                window.avnCampaignBuilder.load(preJson);

                // Gespeicherter Entwurfs-Inhalt überschreibt Template-Blöcke
                if (savedContent && savedContent !== '[]') {
                    window.avnCampaignBuilder.load(savedContent);
                }

                $('#av-campaign-builder-placeholder').hide();
                $('#av-mail-builder-app .av-palette-item').removeClass('av-disabled').attr('draggable', 'true');
                $('#av-preview-mail').prop('disabled', false);
            } else if (tplData.body_json && tplData.body_json.length) {
                $('#av-campaign-builder-placeholder').hide();
                $('#av-mail-builder-app .av-palette-item').removeClass('av-disabled').attr('draggable', 'true');
                $('#av-preview-mail').prop('disabled', false);
            }
        }
    }

    /* ── Palette Drag ────────────────────────────────────────────────────── */

    function initPaletteDrag() {
        $(document).on('dragstart', '.av-palette-item[data-block-type]', function (e) {
            $(this).addClass('av-dragging-source');
            e.originalEvent.dataTransfer.setData('text/plain', JSON.stringify({
                source: 'palette',
                blockType: $(this).data('block-type'),
            }));
        });

        $(document).on('dragend', '.av-palette-item[data-block-type]', function () {
            $(this).removeClass('av-dragging-source');
        });

        var $canvas = $('#av-mail-canvas-inner');

        $canvas.on('dragover', function (e) {
            e.preventDefault();
            $(this).addClass('av-drop-active');
        });

        $canvas.on('dragleave', function (e) {
            if (!$(e.relatedTarget).closest('#av-mail-canvas-inner').length) {
                $(this).removeClass('av-drop-active');
            }
        });

        $canvas.on('drop', function (e) {
            e.preventDefault();
            $(this).removeClass('av-drop-active');
            try {
                var dragData = JSON.parse(e.originalEvent.dataTransfer.getData('text/plain'));
                if (dragData.source === 'palette') {
                    addBlock(dragData.blockType);
                }
            } catch (ex) { /* noop */ }
        });
    }

    /* ── Canvas rendern ──────────────────────────────────────────────────── */

    function renderCanvas() {
        // Quill-Instanzen bereinigen
        quillInstances = {};

        var $inner = $('#av-mail-canvas-inner').empty();

        if (!tplData.body_json.length) {
            $inner.append(
                '<div class="av-mail-drop-placeholder">' +
                '<span class="dashicons dashicons-download"></span>' +
                '<p>Block hierher ziehen</p>' +
                '</div>'
            );
            return;
        }

        $.each(tplData.body_json, function (bi, block) {
            $inner.append(renderBlock(bi, block));
        });

        // Quill NACH DOM-Insertion initialisieren
        $.each(tplData.body_json, function (bi, block) {
            if (block.type === 'content_area' && window.Quill) {
                var $container = $inner.find('.av-mail-block[data-block-idx="' + bi + '"] .av-ca-quill');
                if (!$container.length) { return; }

                var placeholders = [
                    '{{first_name}}', '{{last_name}}', '{{email}}',
                    '{{confirm_link}}', '{{unsubscribe_link}}',
                    '{{site_name}}', '{{site_url}}',
                ];

                var q = new window.Quill($container[0], {
                    theme: 'snow',
                    placeholder: 'Mail-Inhalt hier eingeben…',
                    modules: {
                        toolbar: {
                            container: [
                                [{ header: [1, 2, 3, false] }],
                                ['bold', 'italic', 'underline'],
                                [{ color: [] }],
                                ['link', 'blockquote'],
                                [{ list: 'ordered' }, { list: 'bullet' }],
                                ['clean'],
                            ],
                        },
                    },
                });

                // Platzhalter-Dropdown einfügen
                var $ph = $('<select class="av-ca-ph-select"><option value="">Platzhalter…</option></select>');
                var labels = { '{{first_name}}': 'Vorname', '{{last_name}}': 'Nachname', '{{email}}': 'E-Mail',
                               '{{confirm_link}}': 'Bestätigungslink', '{{unsubscribe_link}}': 'Abmeldelink',
                               '{{site_name}}': 'Seitenname', '{{site_url}}': 'Seiten-URL' };
                $.each(placeholders, function (_, ph) {
                    $ph.append('<option value="' + ph + '">' + (labels[ph] || ph) + '</option>');
                });
                $ph.on('change', function () {
                    var ph = $(this).val();
                    if (!ph) { return; }
                    var range = q.getSelection(true);
                    q.insertText(range ? range.index : q.getLength() - 1, ph, 'user');
                    $(this).val('');
                });
                $container.closest('.av-ca-editor-wrap').find('.ql-toolbar').append($ph);

                // Initialinhalt setzen
                if (block.text) {
                    q.clipboard.dangerouslyPasteHTML(block.text);
                }

                // Auf Änderungen reagieren
                q.on('text-change', function () {
                    block.text = q.root.innerHTML === '<p><br></p>' ? '' : q.root.innerHTML;
                });

                quillInstances[bi] = q;
            }
        });

        if ($.fn.sortable) {
            $inner.sortable({
                placeholder: 'av-drag-placeholder',
                cancel: '.av-ca-editor-wrap, .ql-editor, .ql-toolbar, .av-ca-ph-select',
                update: function () {
                    var newBlocks = [];
                    $inner.children('.av-mail-block').each(function () {
                        var idx = parseInt($(this).data('block-idx'));
                        if (typeof tplData.body_json[idx] !== 'undefined') {
                            newBlocks.push(tplData.body_json[idx]);
                        }
                    });
                    tplData.body_json = newBlocks;
                    renderCanvas();
                },
            });
        }
    }

    function addBlock(type) {
        var block = defaultBlock(type);
        tplData.body_json.push(block);
        renderCanvas();
        var bi = tplData.body_json.length - 1;
        selectedBlockIdx = bi;
        renderInspector(bi);
        $('.av-mail-block[data-block-idx="' + bi + '"]').addClass('av-selected');
    }

    function defaultBlock(type) {
        var base = { _id: '_b' + (_blockIdCounter++), type: type };
        switch (type) {
            case 'content_area': return $.extend(base, {});
            case 'heading':  return $.extend(base, { text: 'Ihre Überschrift', level: 'h2', align: 'left', color: '#1f2937' });
            case 'text':     return $.extend(base, { text: 'Ihr Text hier…', align: 'left', color: '#374151' });
            case 'image':    return $.extend(base, { url: '', alt: '', width: 100 });
            case 'button':   return $.extend(base, { text: 'Klicken Sie hier', url: '#', bg_color: '#3b82f6', text_color: '#ffffff', align: 'center' });
            case 'divider':  return $.extend(base, { color: '#e5e7eb' });
            case 'spacer':   return $.extend(base, { height: 24 });
            case 'columns2': return $.extend(base, { col1: 'Spalte 1', col2: 'Spalte 2' });
            case 'social':   return $.extend(base, { links: [{ label: 'Website', url: '{{site_url}}' }] });
            case 'footer':   return $.extend(base, { text: '{{site_name}} · <a href="{{unsubscribe_link}}">Abmelden</a>' });
            case 'dynamic':  return $.extend(base, { placeholder: '{{first_name}}' });
            default:         return base;
        }
    }

    /* ── Block-Rendering (Canvas) ────────────────────────────────────────── */

    function renderBlock(bi, block) {
        var $wrap = $('<div class="av-mail-block" data-block-idx="' + bi + '">');

        var $toolbar = $(
            '<div class="av-mail-block-toolbar">' +
            '<button class="av-block-move-up" title="Hoch"><span class="dashicons dashicons-arrow-up-alt2"></span></button>' +
            '<button class="av-block-move-dn" title="Runter"><span class="dashicons dashicons-arrow-down-alt2"></span></button>' +
            '<button class="av-block-delete" title="Löschen"><span class="dashicons dashicons-trash"></span></button>' +
            '</div>'
        );
        $wrap.append($toolbar);

        if (block.type === 'content_area') {
            // Quill-Container – wird nach DOM-Insertion in renderCanvas() initialisiert
            var $edWrap = $('<div class="av-ca-editor-wrap">');
            $edWrap.append('<div class="av-ca-quill"></div>');
            $wrap.append($edWrap);

            // Block markieren wenn in der Editor-Umgebung geklickt wird
            $edWrap.on('mousedown', function () {
                $('.av-mail-block').removeClass('av-selected');
                $wrap.addClass('av-selected');
                selectedBlockIdx = bi;
                hideInspector();
            });

        } else {
            $wrap.append(blockHtml(block));
            $wrap.on('click', function (e) {
                if ($(e.target).closest('.av-mail-block-toolbar').length) { return; }
                $('.av-mail-block').removeClass('av-selected');
                $wrap.addClass('av-selected');
                selectedBlockIdx = bi;
                renderInspector(bi);
            });
        }

        $wrap.find('.av-block-delete').on('click', function (e) {
            e.stopPropagation();
            tplData.body_json.splice(bi, 1);
            if (selectedBlockIdx === bi) { selectedBlockIdx = null; hideInspector(); }
            renderCanvas();
        });

        $wrap.find('.av-block-move-up').on('click', function (e) {
            e.stopPropagation();
            if (bi > 0) {
                var tmp = tplData.body_json[bi];
                tplData.body_json[bi]     = tplData.body_json[bi - 1];
                tplData.body_json[bi - 1] = tmp;
                renderCanvas();
            }
        });

        $wrap.find('.av-block-move-dn').on('click', function (e) {
            e.stopPropagation();
            if (bi < tplData.body_json.length - 1) {
                var tmp = tplData.body_json[bi];
                tplData.body_json[bi]     = tplData.body_json[bi + 1];
                tplData.body_json[bi + 1] = tmp;
                renderCanvas();
            }
        });

        return $wrap;
    }

    function blockHtml(block) {
        switch (block.type) {
            case 'content_area': {
                var caText = block.text || '';
                if (caText) {
                    return '<div class="av-mail-content-area av-mail-content-area--filled">' + caText + '</div>';
                }
                return '<div class="av-mail-content-area">'
                    + '<span class="dashicons dashicons-edit"></span>'
                    + '<strong>Inhalts-Bereich</strong>'
                    + '<p>Block anklicken um den Mail-Inhalt zu bearbeiten.</p>'
                    + '</div>';
            }
            case 'heading':
                var tag = escTag(block.level || 'h2');
                return '<' + tag + ' class="av-mail-heading" style="text-align:' + escCss(block.align) + ';color:' + escCss(block.color) + '">' + escHtml(block.text || '') + '</' + tag + '>';
            case 'text':
                return '<p class="av-mail-text" style="text-align:' + escCss(block.align) + ';color:' + escCss(block.color) + '">' + escHtml(block.text || '') + '</p>';
            case 'image':
                if (!block.url) {
                    return '<div class="av-mail-image-placeholder"><span class="dashicons dashicons-format-image"></span> Bild-URL eingeben</div>';
                }
                return '<div class="av-mail-image-wrap"><img src="' + escAttr(block.url) + '" alt="' + escAttr(block.alt || '') + '" style="width:' + (parseInt(block.width) || 100) + '%"></div>';
            case 'button':
                return '<div class="av-mail-button-wrap" style="text-align:' + escCss(block.align) + '">' +
                       '<a class="av-mail-button" style="background:' + escCss(block.bg_color) + ';color:' + escCss(block.text_color) + '">' + escHtml(block.text || 'Button') + '</a></div>';
            case 'divider':
                return '<hr class="av-mail-divider" style="border-color:' + escCss(block.color) + '">';
            case 'spacer':
                return '<div class="av-mail-spacer" style="height:' + (parseInt(block.height) || 24) + 'px"></div>';
            case 'columns2':
                return '<div class="av-mail-cols2"><div>' + escHtml(block.col1 || 'Spalte 1') + '</div><div>' + escHtml(block.col2 || 'Spalte 2') + '</div></div>';
            case 'social':
                var links = (block.links || []).map(function (l) {
                    return '<a href="' + escAttr(l.url || '#') + '">' + escHtml(l.label || '') + '</a>';
                }).join('');
                return '<div class="av-mail-social">' + links + '</div>';
            case 'footer':
                return '<div class="av-mail-footer">' + (block.text || '') + '</div>';
            case 'dynamic':
                return '<div class="av-mail-dynamic-block"><code>' + escHtml(block.placeholder || '') + '</code></div>';
            default:
                return '<div>[' + escHtml(block.type) + ']</div>';
        }
    }

    /* ── Inspector ───────────────────────────────────────────────────────── */

    function hideInspector() {
        var $insp = $('#av-mail-inspector');
        $insp.find('.av-inspector-empty').show();
        $insp.find('.av-inspector-form').hide().empty();
    }

    function renderInspector(bi) {
        var block = tplData.body_json[bi];
        if (!block) { return; }

        var $insp = $('#av-mail-inspector');
        $insp.find('.av-inspector-empty').hide();
        var $form = $insp.find('.av-inspector-form').removeClass('av-hidden').show().empty();

        switch (block.type) {
            case 'heading':
                addInspField($form,    'av-bi-text',  'Text',         'text',   block.text  || '');
                addInspSelect($form,   'av-bi-level', 'Ebene',        [{v:'h1',l:'H1'},{v:'h2',l:'H2'},{v:'h3',l:'H3'}], block.level || 'h2');
                addInspSelect($form,   'av-bi-align', 'Ausrichtung',  [{v:'left',l:'Links'},{v:'center',l:'Mitte'},{v:'right',l:'Rechts'}], block.align || 'left');
                addInspColor($form,    'av-bi-color', 'Farbe',        block.color || '#1f2937');
                break;
            case 'text':
                addInspTextarea($form, 'av-bi-text',  'Text',         block.text || '');
                addInspSelect($form,   'av-bi-align', 'Ausrichtung',  [{v:'left',l:'Links'},{v:'center',l:'Mitte'},{v:'right',l:'Rechts'}], block.align || 'left');
                addInspColor($form,    'av-bi-color', 'Farbe',        block.color || '#374151');
                break;
            case 'image':
                addInspField($form, 'av-bi-url',   'Bild-URL',    'url',    block.url   || '');
                addInspField($form, 'av-bi-alt',   'Alt-Text',    'text',   block.alt   || '');
                addInspField($form, 'av-bi-width', 'Breite (%)',  'number', block.width || 100);
                break;
            case 'button':
                addInspField($form,  'av-bi-text',    'Button-Text',      'text', block.text || '');
                addInspField($form,  'av-bi-url',     'Link-URL',         'text', block.url  || '#');
                addInspSelect($form, 'av-bi-align',   'Ausrichtung',      [{v:'left',l:'Links'},{v:'center',l:'Mitte'},{v:'right',l:'Rechts'}], block.align || 'center');
                addInspColor($form,  'av-bi-bg',      'Hintergrundfarbe', block.bg_color   || '#3b82f6');
                addInspColor($form,  'av-bi-textcol', 'Textfarbe',        block.text_color || '#ffffff');
                break;
            case 'divider':
                addInspColor($form, 'av-bi-color', 'Linienfarbe', block.color || '#e5e7eb');
                break;
            case 'spacer':
                addInspField($form, 'av-bi-height', 'Höhe (px)', 'number', block.height || 24);
                break;
            case 'columns2':
                addInspTextarea($form, 'av-bi-col1', 'Spalte 1', block.col1 || '');
                addInspTextarea($form, 'av-bi-col2', 'Spalte 2', block.col2 || '');
                break;
            case 'footer':
                addInspTextarea($form, 'av-bi-text', 'HTML-Inhalt (Platzhalter erlaubt)', block.text || '');
                break;
            case 'dynamic':
                addInspField($form, 'av-bi-placeholder', 'Platzhalter', 'text', block.placeholder || '');
                break;
        }

        $form.find('.av-input, .av-select').on('input change', function () {
            syncBlockFromInspector(bi);
        });
    }

    function syncBlockFromInspector(bi) {
        var block    = tplData.body_json[bi];
        var textVal  = $('#av-bi-text').val();
        var alignVal = $('#av-bi-align').val();
        var colorVal = $('#av-bi-color').val();
        var urlVal   = $('#av-bi-url').val();

        switch (block.type) {
            case 'heading':
                block.text  = textVal;
                block.level = $('#av-bi-level').val() || 'h2';
                block.align = alignVal || 'left';
                block.color = colorVal || '#1f2937';
                break;
            case 'text':
                block.text  = textVal;
                block.align = alignVal || 'left';
                block.color = colorVal || '#374151';
                break;
            case 'image':
                block.url   = urlVal;
                block.alt   = $('#av-bi-alt').val();
                block.width = parseInt($('#av-bi-width').val()) || 100;
                break;
            case 'button':
                block.text       = textVal;
                block.url        = urlVal || '#';
                block.align      = alignVal || 'center';
                block.bg_color   = $('#av-bi-bg').val()      || '#3b82f6';
                block.text_color = $('#av-bi-textcol').val() || '#ffffff';
                break;
            case 'divider':
                block.color = colorVal || '#e5e7eb';
                break;
            case 'spacer':
                block.height = parseInt($('#av-bi-height').val()) || 24;
                break;
            case 'columns2':
                block.col1 = $('#av-bi-col1').val();
                block.col2 = $('#av-bi-col2').val();
                break;
            case 'footer':
                block.text = textVal;
                break;
            case 'dynamic':
                block.placeholder = $('#av-bi-placeholder').val();
                break;
        }

        // Block im Canvas live neu rendern
        var $block   = $('.av-mail-block[data-block-idx="' + bi + '"]');
        var $toolbar = $block.find('.av-mail-block-toolbar').detach();
        $block.html(blockHtml(block));
        $block.prepend($toolbar);
    }

    /* ── Inspector Hilfsf. ───────────────────────────────────────────────── */

    function addInspRichEditor($target, id, labelText, val) {
        var placeholders = [
            { v: '{{first_name}}',       l: 'Vorname' },
            { v: '{{last_name}}',        l: 'Nachname' },
            { v: '{{email}}',            l: 'E-Mail' },
            { v: '{{confirm_link}}',     l: 'Bestätigungslink' },
            { v: '{{unsubscribe_link}}', l: 'Abmeldelink' },
            { v: '{{site_name}}',        l: 'Seitenname' },
            { v: '{{site_url}}',         l: 'Seiten-URL' },
        ];
        var $w  = $('<div class="av-rich-editor-wrap">');
        $w.append('<label>' + escHtml(labelText) + '</label>');

        var $tb = $('<div class="av-rich-editor-toolbar">');
        $tb.append('<button type="button" data-cmd="bold"      title="Fett"><b>B</b></button>');
        $tb.append('<button type="button" data-cmd="italic"    title="Kursiv"><i>I</i></button>');
        $tb.append('<button type="button" data-cmd="underline" title="Unterstrichen"><u>U</u></button>');
        $tb.append('<button type="button" class="av-re-link"   title="Link einfügen"><span class="dashicons dashicons-admin-links"></span></button>');
        $tb.append('<span class="av-re-sep"></span>');

        var $phSel = $('<select class="av-re-placeholder-sel">');
        $phSel.append('<option value="">Platzhalter…</option>');
        $.each(placeholders, function (_, ph) {
            $phSel.append('<option value="' + ph.v + '">' + escHtml(ph.l) + '</option>');
        });
        $tb.append($phSel);
        $w.append($tb);

        var $ed = $('<div class="av-rich-editor av-input" id="' + id + '" contenteditable="true">').html(val || '');
        $w.append($ed);
        $target.append($w);

        $tb.find('[data-cmd]').on('mousedown', function (e) {
            e.preventDefault();
            document.execCommand($(this).data('cmd'), false, null);
            $ed.trigger('input');
        });

        $tb.find('.av-re-link').on('mousedown', function (e) {
            e.preventDefault();
            var url = window.prompt('URL eingeben:', 'https://');
            if (url) {
                document.execCommand('createLink', false, url);
                $ed.trigger('input');
            }
        });

        $phSel.on('change', function () {
            var ph = $(this).val();
            if (!ph) { return; }
            $ed[0].focus();
            document.execCommand('insertText', false, ph);
            $ed.trigger('input');
            $(this).val('');
        });
    }

    function addInspField($target, id, labelText, type, val) {
        var $w = $('<div>');
        $w.append('<label for="' + id + '">' + labelText + '</label>');
        $w.append('<input type="' + type + '" id="' + id + '" class="av-input" value="' + escAttr(String(val)) + '">');
        $target.append($w);
    }

    function addInspTextarea($target, id, labelText, val) {
        var $w = $('<div>');
        $w.append('<label for="' + id + '">' + labelText + '</label>');
        $w.append('<textarea id="' + id + '" class="av-input" rows="3">' + escHtml(String(val)) + '</textarea>');
        $target.append($w);
    }

    function addInspSelect($target, id, labelText, options, selected) {
        var $w   = $('<div>');
        var $sel = $('<select id="' + id + '" class="av-select">');
        $.each(options, function (i, o) {
            $sel.append('<option value="' + escAttr(o.v) + '"' + (o.v === selected ? ' selected' : '') + '>' + escHtml(o.l) + '</option>');
        });
        $w.append('<label for="' + id + '">' + labelText + '</label>').append($sel);
        $target.append($w);
    }

    function addInspColor($target, id, labelText, val) {
        var $w = $('<div>');
        $w.append('<label for="' + id + '">' + labelText + '</label>');
        $w.append('<input type="color" id="' + id + '" class="av-input av-input-color" value="' + escAttr(val) + '">');
        $target.append($w);
    }

    /* ── HTML generieren (für body_html) ────────────────────────────────── */

    function buildBodyHtml() {
        var parts = [
            '<!DOCTYPE html><html><head><meta charset="utf-8">',
            '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:0;background:#f0f0f0}',
            '.wrapper{max-width:600px;margin:0 auto;background:#fff;padding:32px}</style>',
            '</head><body><div class="wrapper">',
        ];
        $.each(tplData.body_json, function (i, block) {
            if (block.type === 'content_area') {
                parts.push('{{mail_content}}');
            } else {
                parts.push(blockHtml(block));
            }
        });
        parts.push('</div></body></html>');
        return parts.join('');
    }

    /* ── Vorschau-HTML mit aufgelösten Platzhaltern ──────────────────────── */

    var previewDummy = {
        '{{first_name}}':       'Max',
        '{{last_name}}':        'Mustermann',
        '{{email}}':            'max.mustermann@example.de',
        '{{site_name}}':        (typeof avnAdmin !== 'undefined' && avnAdmin.site_name) ? avnAdmin.site_name : document.title,
        '{{site_url}}':         (typeof avnAdmin !== 'undefined' && avnAdmin.site_url)  ? avnAdmin.site_url  : window.location.origin + '/',
        '{{unsubscribe_link}}': '<a href="#" style="color:#6b7280">Abmelden</a>',
        '{{confirm_link}}':     '<a href="#" style="color:#2563eb">Anmeldung bestätigen</a>',
        '{{mail_content}}':     '<p style="color:#6b7280;font-style:italic;">[Hier erscheint der Mail-Inhalt]</p>',
    };

    function resolvePlaceholders(html) {
        $.each(previewDummy, function (placeholder, value) {
            html = html.split(placeholder).join(value);
        });
        return html;
    }

    function buildPreviewHtml() {
        var parts = [
            '<!DOCTYPE html><html><head><meta charset="utf-8">',
            '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:0;background:#f0f0f0}',
            '.wrapper{max-width:600px;margin:0 auto;background:#fff;padding:32px}</style>',
            '</head><body><div class="wrapper">',
        ];
        $.each(tplData.body_json, function (i, block) {
            if (block.type === 'content_area') {
                if (isCampaign && block.text) {
                    parts.push('<div style="line-height:1.7">' + block.text + '</div>');
                } else {
                    parts.push(previewDummy['{{mail_content}}']);
                }
            } else {
                parts.push(blockHtml(block));
            }
        });
        parts.push('</div></body></html>');
        return resolvePlaceholders(parts.join(''));
    }

    /* ── Vorschau ────────────────────────────────────────────────────────── */

    function showPreview() {
        var $modal   = $('#av-mail-preview-modal');
        var $content = $('#av-mail-preview-content');
        $content.empty();
        $('<iframe>')
            .attr({ frameborder: 0, width: '100%', height: '500' })
            .appendTo($content)
            .prop('srcdoc', buildPreviewHtml());
        $modal.addClass('av-active');
        $('body').addClass('av-modal-open');
    }

    /* ── Speichern ───────────────────────────────────────────────────────── */

    function saveTemplate() {
        var name    = $('#av-tpl-title').val().trim();
        var subject = $('#av-tpl-subject').val().trim();

        if (!name || !subject) {
            alert('Bitte Name und Betreff eingeben.');
            return;
        }

        var $btn = $('#av-save-mail-template').prop('disabled', true).text('Speichert…');

        $.post(avnAdmin.ajaxurl, {
            action:         'avn_save_mail_template',
            nonce:          avnAdmin.nonce,
            id:             tplData.id,
            name:           name,
            subject:        subject,
            body_json:      JSON.stringify(tplData.body_json),
            body_html:      buildBodyHtml(),
        }).done(function (res) {
            if (res.success) {
                tplData.id = res.data.id;
                if (window.history && window.history.replaceState) {
                    var url = new URL(window.location.href);
                    url.searchParams.set('template_id', res.data.id);
                    window.history.replaceState({}, '', url.toString());
                }
                $btn.html('<span class="dashicons dashicons-yes-alt"></span> Gespeichert!');
                setTimeout(function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Speichern');
                }, 2000);
            } else {
                alert(avnAdmin.i18n.error);
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Speichern');
            }
        }).fail(function () {
            alert(avnAdmin.i18n.error);
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Speichern');
        });
    }

    /* ── Escape-Helfer ───────────────────────────────────────────────────── */

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function escAttr(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function escCss(str) {
        return String(str).replace(/[^a-zA-Z0-9#%(),.\- ]/g, '');
    }

    function escTag(str) {
        return String(str).replace(/[^a-z0-9]/gi, '');
    }

    /* ── Start ───────────────────────────────────────────────────────────── */

    $(function () { init(); });

}(jQuery));

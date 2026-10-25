(function($){
    "use strict";
    var firstClicked = false;

    $(document).ready(function(){
        repeater_actions()
        localStorage.setItem('test', 'hi');
        let rep = $('.repeater');

        rep.each(function(e){
            let classList = $(this).attr("class").split(" ")
            let key = classList[classList.length - 1]

            if(localStorage.getItem(key) != null){
                localStorage.removeItem(key)
            }

        })
    })

    $(document).on('row_loaded', function(){
        var rcntrlIsPressed;


        $(document).keydown(function(event){
            if(event.which=="17"){
                rcntrlIsPressed = true;
            }
            $('.tm-repeater-select-field option').each(function(indx, e){
                $(e).on('click', function(ev){
                    if(rcntrlIsPressed){
                        $(e).attr('selected', 'selected')
                    }
                })
            });
        });
        
        $(document).keyup(function(){
            rcntrlIsPressed = false;
        });

        $('.tm-repeater-select-field option').each(function(indx, e){
            $(e).on('click', function(ev){
                if($(e).attr('selected') != undefined){
                    $(e).removeAttr('selected')
                }
            })
        });
        
        $(document).on('click', '.tm-rptr-remove', function(){
            const $row  = $(this).closest('.tm-repeater-row');
            const rows  = $(this).closest('.tm-repeater-rows').find('.tm-repeater-row').length;
            if(rows > 1){
                $row.remove();
            }
        })

        $('.tm-repeater-select-field.select2').each(function(indx, el){
            var $sel = $(this);
            // Skip if already a live select2 instance — avoids double-binding
            // when row_loaded fires repeatedly after multiple Add Row clicks.
            if ($sel.hasClass('select2-hidden-accessible')) return;
            var ph   = $sel.data('placeholder') || $sel.attr('data-placeholder') || '';
            var opts = { width: '100%', allowClear: false };
            if (ph) opts.placeholder = ph;
            $sel.select2(opts);
            $sel.on('select2:select', function (e) {
                $(e.target).closest('.repeater-field').find('input[type="hidden"]').val(JSON.stringify($(e.target).val()));
            });
        });

        $('.tm-repeater-select-field.normal').each(function(indx, el){
            $(el).on('change', function (e) {
                $(e.target).closest('.repeater-field').find('input[type="hidden"]').val(JSON.stringify($(e.target).val()));
            });
        });
    })

    // Hoisted so the dragula drop handler can call it too.
    // Never renumbers existing rows — only assigns a label to rows that
    // have data-count="0" (new rows). This preserves user-visible item
    // numbers across sort, add, and delete operations.
    function updateCounter($repeater) {
        var $rows = $repeater.find('.tm-repeater-row:not(.tp-hidden-template)');
        $repeater.find('.tp-row-counter').val($rows.length);

        // Find the highest number already assigned to any visible row.
        var maxNum = 0;
        $rows.each(function() {
            var n = parseInt($(this).find('.tm-rptr-title').attr('data-count') || 0, 10);
            if (n > maxNum) maxNum = n;
        });

        // Only label rows whose data-count is 0 (freshly added / template).
        $rows.each(function() {
            var $label = $(this).find('.tm-rptr-title');
            if (parseInt($label.attr('data-count') || 0, 10) <= 0) {
                maxNum++;
                $label.text('Item ' + maxNum).attr('data-count', maxNum);
                $(this).find('input[name$="_item_num[]"]').val(maxNum);
            }
        });
    }

    const repeater_actions = function(){

        $(document).on('click', '.tm-repeater-add-btn', function() {
            let $repeater = $(this).closest('.tm-repeater');
            let $rows = $repeater.find('.tm-repeater-row');
            let $hiddenTemplate = $rows.filter('.tp-hidden-template');

            let $newRow;
            if ($hiddenTemplate.length) {
                // Reuse hidden template row (first Add Row click on an empty repeater).
                $newRow = $hiddenTemplate.removeClass('tp-hidden-template').show();
            } else {
                // Clone first visible row (all subsequent Add Row clicks).
                $newRow = $rows.first().clone();

                // Reset the item-number label so updateCounter treats this as a
                // freshly added row and assigns the next sequential number.
                $newRow.find('.tm-rptr-title').attr('data-count', 0);
                $newRow.find('input[name$="_item_num[]"]').val(0);
            }

            // Saved rows render with `is-collapsed` so the metabox loads
            // compactly. Freshly added rows should be expanded so the user
            // can fill them in immediately — strip the class here for both
            // the template-reveal and clone paths.
            $newRow.removeClass('is-collapsed');

            // (clone-only) Re-namespace bracketed indices on the clone so
            // PHP auto-increments correctly after explicitly indexed sorted
            // rows. Kept inside its own branch for clarity.
            if (!$hiddenTemplate.length) {

                // If rows were reindexed after sorting (names like field[0]),
                // reset the clone back to field[] so PHP auto-increments it
                // correctly after the explicitly-indexed sorted rows.
                $newRow.find('[name]').each(function() {
                    var n = $(this).attr('name');
                    if (/\[\d+\]$/.test(n)) {
                        $(this).attr('name', n.replace(/\[\d+\]$/, '[]'));
                    }
                });

                $newRow.appendTo($repeater.find('.tm-repeater-rows'));
            }

            // ── Strip & reinitialise widget state ─────────────────────────────────
            // tpmeta-fields.js runs at page load and initialises ALL matching inputs,
            // including those in the hidden template row. cloneNode copies DOM state
            // but NOT event listeners; a reused template row may have stale gradient
            // state. Stripping and reinitialising here covers BOTH paths cleanly.
            //
            // Row index used to build unique IDs (prevents jQuery UI datepicker
            // onclick-by-#id collision across rows).
            var _newRowIdx = $repeater.find('.tm-repeater-row:not(.tp-hidden-template)').length;

            // 1. Replace any wp-picker-container wrappers with fresh plain inputs.
            //    For gradient stop-colour inputs (.tpgrd-stop-color), restore the
            //    colour value from the original picker so the swatch is not blank
            //    when TPMetaGradient.boot re-initialises it below.  Plain colorpickers
            //    (not gradient stops) intentionally start empty for a fresh row.
            $newRow.find('.wp-picker-container').each(function() {
                var $c      = $(this);
                var $inp    = $c.find('input.wp-color-picker');
                var def     = $inp.data('default-color') || '';
                var name    = $inp.attr('name') || '';
                var isStop  = !!$c.closest('.tm-grd-stop').length;
                var cls     = isStop
                    ? 'tm-input tm-colorpicker-input tpgrd-stop-color'
                    : 'tm-input tm-input-sm tm-colorpicker-input';
                // For stops: use the picker's current value (or its default) so the
                // fresh input has a valid colour for TPMetaGradient.boot to read.
                var val     = isStop ? ($inp.val() || def || '#3362FF') : '';
                $c.replaceWith($('<input>', {
                    type: 'text', 'class': cls, value: val, 'data-default-color': def, name: name
                }));
            });

            // 2. Datepicker: strip hasDatepicker, assign a unique ID per row so
            //    jQuery UI's day-cell onclick('#id') targets only this row's input,
            //    then reinitialise with the native-event-dispatching onSelect callback.
            $newRow.find('.tm-datepicker-input').each(function() {
                $(this).removeClass('hasDatepicker');
                if (this.id) {
                    this.id = this.id.replace(/__(?:r|n)\d+.*$/, '') + '__n' + _newRowIdx;
                }
                if ($.fn.datepicker) {
                    $(this).datepicker({
                        dateFormat: 'mm/dd/yy',
                        onSelect: function() {
                            // Use dispatchEvent so native addEventListener listeners
                            // (e.g. any change-based save hooks) also fire.
                            this.dispatchEvent(new Event('change', { bubbles: true }));
                        },
                        beforeShow: function() {
                            setTimeout(function() {
                                $('#ui-datepicker-div').addClass('tm-datepicker');
                            }, 0);
                        }
                    });
                }
            });

            // Strip stale Select2 state from the cloned row.
            // cloneNode copies the .select2-container visual stub (a sibling of
            // the original <select>) and the .select2-hidden-accessible class,
            // but NOT jQuery's internal Select2 instance. The clone looks
            // initialised but has no live event bindings — typing in the search
            // box does nothing, change events don't fire, etc. Removing the
            // stub + the class lets the row_loaded handler build a fresh widget.
            $newRow.find('select.select2-hidden-accessible').each(function() {
                var $sel = $(this);
                $sel.next('.select2-container').remove();
                $sel.removeClass('select2-hidden-accessible');
                // Defensive: if a stale instance somehow attached, destroy it.
                try { if (typeof $sel.data('select2') !== 'undefined') $sel.select2('destroy'); } catch (e) {}
            });

            // Reset visible inputs to blank for a fresh new row.
            // Exclude inputs inside .tm-gradient-wrap — those must keep their
            // values so TPMetaGradient.boot can initialise with the correct
            // colours, angle, and positions from the template defaults.
            // (Resetting them to '' causes blank swatches, NaN stop positions,
            // and an immediately broken gradient state on first syncStops call.)
            $newRow.find('input, select, textarea')
                .not('[type=hidden]')
                .filter(function() { return !$(this).closest('.tm-gradient-wrap').length; })
                .val('');

            // 3. Reinitialise standalone colorpickers (non-gradient-stop inputs).
            //    Gradient stop colorpickers are handled by TPMetaGradient.boot below.
            $newRow.find('.tm-colorpicker-input').not('.wp-color-picker').not('.tpgrd-stop-color').wpColorPicker({
                change: function(event, ui) {
                    var hex = ui.color.toString();
                    $(this).closest('.wp-picker-container')
                           .find('.wp-color-result')
                           .css('background-color', hex);
                }
            });

            // 4. Gradient: assign unique wrap IDs and boot TPMetaGradient.
            //    Template rows are skipped by the PHP inline boot call, so boot has
            //    not run yet for either the reused template or a clone.
            $newRow[0].querySelectorAll('.tm-gradient-wrap').forEach(function(gw) {
                gw.id = (gw.id || 'tpgrd').replace(/__(?:r|n)\d+.*$/, '') + '__n' + _newRowIdx;
                if (typeof TPMetaGradient !== 'undefined' && typeof TPMetaGradient.boot === 'function') {
                    var gradHidden = gw.parentNode && gw.parentNode.querySelector('.tm-gradient-value');
                    if (gradHidden) TPMetaGradient.boot(gw, gradHidden);
                }
            });

            // Update counter + labels
            updateCounter($repeater);

            $(document).trigger('row_loaded');
        });

        $(document).on('click', '.tm-rptr-remove', function() {
            let $repeater = $(this).closest('.tm-repeater');
            let $rows = $repeater.find('.tm-repeater-row:not(.tp-hidden-template)');

            if ($rows.length > 1) {
                // Remove clicked row
                $(this).closest('.tm-repeater-row').remove();
            } else {
                // Last row → hide instead of remove
                $rows.first().addClass('tp-hidden-template').hide();
            }

            // Always update counter after action
            updateCounter($repeater);
        });

        // Collapse / expand individual rows.
        $(document).on('click', '.tm-rptr-toggle', function() {
            $(this).closest('.tm-repeater-row').toggleClass('is-collapsed');
        });

        $(document).on('click change', '.tm-repeater-conditional', function(){
            var closestRow      = $(this).closest('.tm-repeater-row')
            var key             = $(this).data('key')
            var targetElement   = key != ''? closestRow.find(`.tm-field-row.${key}`) : '';
            var operand         = targetElement != ''? targetElement.data('operand') : '';
            var value           = targetElement != ''? targetElement.data('value') : '';

            if(targetElement != '' && operand != '' && value != ''){
                if($(this).is('input')){
                    if($(this).is(':checked')){
                        $(this).val('on')
                        $(this).prev().val('on')
                        elementVisibility($(this).val(), operand, value, targetElement, closestRow)
                    }else{
                        $(this).val('off')
                        $(this).prev().val('off')
                        elementVisibility($(this).val(), operand, value, targetElement, closestRow)
                    }
                }else if($(this).is('select')){
                    elementVisibility($(this).val(), operand, value, targetElement, closestRow)
                }else{
                    console.warn('Input type not matched!')
                }
            }else{
                console.warn('Target element id not found!')
            }
            
        })

        const elementVisibility = function(current_val, operand, compare_val, target_el, closest_el){
            let evaluate = eval(`'${current_val}' ${operand} '${compare_val}'`)
            if(evaluate){
                if(closest_el.length){
                    target_el.show(300)
                }else{
                    console.error('Closest Eelement not found!')
                }
            }else{
                target_el.hide(400)
            }
        }
        
        const repeaters = document.querySelectorAll(".tm-repeater-rows");
        var dragulaInstance = dragula(Array.from(repeaters), {
            moves: function(el, container, handle) {
                // Allow dragging only from the grip handle.
                return !!(handle.closest('.tm-rptr-handle'));
            },
            direction: 'vertical'
        });

        // Track the container width when a drag starts so the CSS variable
        // --gu-mirror-w is set on :root. The ghost (.gu-mirror) is appended to
        // <body> by Dragula and uses this var for its width via CSS.
        dragulaInstance.on('drag', function(el, source) {
            var w = source ? source.getBoundingClientRect().width : 600;
            document.documentElement.style.setProperty('--gu-mirror-w', w + 'px');
        });

        // After a drop, reindex every input name in the affected repeater so
        // PHP receives field values in visual (sorted) order rather than the
        // original render order.
        dragulaInstance.on('drop', function(el, target) {
            if (!target) return;
            $(target).find('.tm-repeater-row:not(.tp-hidden-template)').each(function(i) {
                $(this).find('[name]').each(function() {
                    var n = $(this).attr('name');
                    // Replace trailing [] or [N] with [i] to lock sorted order.
                    $(this).attr('name', n.replace(/\[(\d*)\]$/, '[' + i + ']'));
                });
            });
            // Only sync the hidden row-count input — do NOT renumber the "Item N"
            // labels so the user sees items keep their original numbers after sorting.
            var $repeater = $(target).closest('.tm-repeater');
            $repeater.find('.tp-row-counter').val(
                $repeater.find('.tm-repeater-row:not(.tp-hidden-template)').length
            );
        });

        imageFunctionality();
        galleryFunctionality();
    }

    const imageFunctionality = function(){
        $(document).on('click', '.tm-add-image', function(e){
            e.preventDefault();
            let frame, editFrame;
            let $this = $(this);
            let $imageContainer = $this.closest('.tm-image-field').find('.tm-image-container');

            frame = wp.media({
                title:'Select an image',
                button:{
                    text:'Add Image'
                },
                multiple:false
            })

            frame.on('select', function(){
                let attachment, attchmentURL;
                attachment = frame.state().get('selection').first().toJSON();
                // Use the original uploaded URL so the preview shows the
                // actual file (no WP-generated crop/resize). The wrapper's
                // CSS handles the visual sizing.
                attchmentURL = (attachment.sizes && attachment.sizes.full && attachment.sizes.full.url)
                    ? attachment.sizes.full.url
                    : attachment.url;
                $imageContainer.html(`<div class="tm-image-item">
                    <div class="tm-image-prev">
                        <img src="${attchmentURL}" alt=""/>
                    </div>
                    <div class="tm-image-actions">
                        <a data-attachment-id="${attachment.id}" href="#" class="tm-delete"><span class="dashicons dashicons-no-alt"></span></a>
                        <a data-attachment-id="${attachment.id}" href="#" class="tm-edit"><span class="dashicons dashicons-edit"></span></a>
                    </div>
                </div>`)
                
                $this.closest('.tm-image-field').find('input.tm-image-value').val(attachment.id)
    
                $imageContainer.find('.tm-image-actions > a.tm-delete').on('click', function(e){
                    e.preventDefault();
                    $(e.target).closest('.tm-image-field').find('input.tm-image-value').val('')
                    $(e.target).parent().parent().parent().remove()
                })
                frame.close();
                return false;
            })

            frame.open()
            return false;
        })



        $(document).on('click', '.tm-image-actions > a.tm-delete', function(e){
            e.preventDefault();
            $(e.target).closest('.tm-image-field').find('input.tm-image-value').val('')
            $(e.target).parent().parent().parent().remove();
        })
    }


    const galleryFunctionality = function(){
        $(document).on('click', '.tm-add-gallery', function(e){
            e.preventDefault();
            let $this = $(this);
            let $frame = wp.media({
                title:'Choose images for your gallery',
                library: { type: 'image' },
                button: { text: 'Insert' },
                multiple:true
            });

            $frame.on('select', function(){
                let attachments = $frame.state().get('selection').toJSON();
                let ids = $this.closest('.tm-gallery-field').find('input.tm-gallery-value').val() != '' ? $this.closest('.tm-gallery-field').find('input.tm-gallery-value').val().split(',') : [];
                var attachmentURL;
               
                attachments.map(function(el, i){
                    ids = [...ids, el.id]
                    attachmentURL = el.sizes.thumbnail? el.sizes.thumbnail.url : el.sizes.full.url;
                    $this.closest('.tm-rptr-field').find('.tm-gallery-container').append(`
                    <div class="tm-gallery-item">
                        <div class="tm-gallery-img">
                            <img src="${attachmentURL}" alt=""/>
                        </div>
                        <div class="tm-gallery-actions">
                            <a data-attachment-id="${el.id}" href="#" class="tm-delete repeater"><span class="dashicons dashicons-no-alt"></span></a>
                        </div>
                    </div>
                    `)
                })
                $this.closest('.tm-gallery-field').find('input.tm-gallery-value').val(ids.join(','))


                $(document).on('click', '.tm-gallery-actions > a.tm-delete.repeater', function(e){
                    e.preventDefault();
                    const $this = $(this);
                    const selected = $( e.currentTarget ).data( 'attachment-id' );
                    ids = ids.filter( id => id != selected )
                    $(e.currentTarget).closest('.tm-gallery-field').find('.tm-gallery-value').val(ids.join(','));
                    $(e.currentTarget).closest('.tm-gallery-item').remove();
                });

                $frame.close();
                return false;
            })
            
            $frame.open();
            return false;
        })


        $(document).on('click', '.tm-gallery-actions > a.tm-delete.repeater', function(e){
            e.preventDefault();
            const $this = $(this);
            const selected    = $(e.currentTarget).data( 'attachment-id' );
            let ids         =  $(e.currentTarget).closest('.tm-gallery-field').find('.tm-gallery-value').val();
            ids = ids.split(',');
            ids = ids.filter( id => id != selected );
            $(e.currentTarget).closest('.tm-gallery-field').find('.tm-gallery-value').val(ids.join(','));
            $(e.currentTarget).closest('.tm-gallery-item').remove();
        })

        $('.tm-repeater-select-field.select2').each(function(indx, el){
            var $sel = $(this);
            if ($sel.hasClass('select2-hidden-accessible')) return;
            var ph   = $sel.data('placeholder') || $sel.attr('data-placeholder') || '';
            var opts = { width: '100%', allowClear: false };
            if (ph) opts.placeholder = ph;
            $sel.select2(opts);
            $sel.on('select2:select', function (e) {
                $(e.target).closest('.tm-rptr-field').find('input[type="hidden"]').val(JSON.stringify($(e.target).val()));
            }).on('select2:unselect', function (e) {
                $(e.target).closest('.tm-rptr-field').find('input[type="hidden"]').val(JSON.stringify($(e.target).val()));
            });
        });

        $('.tm-repeater-select-field.normal').each(function(indx, el){
            $(el).on('change', function (e) {
                $(e.target).closest('.tm-rptr-field').find('input[type="hidden"]').val(JSON.stringify($(e.target).val()));
            });
        });

        var rcntrlIsPressed;


        $(document).keydown(function(event){
            if(event.which=="17"){
                rcntrlIsPressed = true;
            }
            $('.tm-repeater-select-field option').each(function(indx, e){
                $(e).on('click', function(ev){
                    if(rcntrlIsPressed){
                        $(e).attr('selected', 'selected')
                    }
                })
            });
        });
        
        $(document).keyup(function(){
            rcntrlIsPressed = false;
        });

        $('.tm-repeater-select-field option').each(function(indx, e){
            $(e).on('click', function(ev){
                if($(e).attr('selected') != undefined){
                    $(e).removeAttr('selected')
                }
            })
        });
    }
})(jQuery)

;(function( $ ){
    "use scrict";

    // Idempotent select2 init helper — applies width: '100%' and reads
    // data-placeholder if present so post-select renders nicely. Calling on an
    // already-initialised select rebuilds it; safe to invoke multiple times.
    function tpInitSelect2($el) {
        var ph   = $el.data('placeholder') || $el.attr('data-placeholder') || '';
        var opts = { width: '100%', allowClear: false };
        if (ph) opts.placeholder = ph;
        return $el.select2(opts);
    }

    $(document).ready(function(){
        // In the Customizer, repeater select sub-fields are initialised by
        // tpmeta-customizer-fields.js with a body-level dropdownParent (so the
        // menu escapes the section's overflow and the z-index sits above the
        // pane). This generic init has NO dropdownParent, so letting it run in
        // the Customizer re-inits those selects and clips the dropdown — the
        // arrow flips to "open" but no menu shows. Skip it there; the options
        // panel (no .wp-customizer body class) is unaffected.
        if ( ! $('body').hasClass('wp-customizer') ) {
            $('.tm-select-field.select2').not('.tpmeta-field-radio_image *').each(function(){
                tpInitSelect2($(this));
            });
        }
        var cntrlIsPressed;

        $(document).keydown(function(event){
            if(event.which=="17"){
                cntrlIsPressed = true;
            }
            $('.tm-select-field option').each(function(indx, e){
                $(e).on('click', function(ev){
                    if($(e).attr('selected') != undefined){
                        $(e).removeAttr('selected')
                    }else{
                        console.log(cntrlIsPressed)
                        if(cntrlIsPressed){
                            $(e).attr('selected', 'selected')
                        }
                    }
                })
            });
        });
        
        $(document).keyup(function(){
            cntrlIsPressed = false;
        });

        $('.tm-select-field option').each(function(indx, e){
            $(e).on('click', function(ev){
                if($(e).attr('selected') != undefined){
                    $(e).removeAttr('selected')
                }
            })
        });

    });

    $('.tm-switch input').on('change', function(){
        var isBoolean     = $(this).data('switch-type') === 'boolean';
        var isHiddenField = $(this).parent().children('input[type="hidden"]');
        if($(this).is(':checked')){
            if (!isBoolean) $(this).val('on');
            if(isHiddenField.length){
                isHiddenField.val(isBoolean ? 'true' : 'on');
            }
        }else{
            if (!isBoolean) $(this).val('off');
            if(isHiddenField.length){
                isHiddenField.val(isBoolean ? 'false' : 'off');
            }
        }
    })

    // Canonical date format used everywhere on the front-end. Legacy values
    // saved in long/ISO formats (e.g. "May 14, 2026", "2026-05-14") are
    // normalised to this on init so the popup datepicker pre-selects the
    // correct day when the user clicks the input.
    var TPMETA_DATE_FORMAT    = 'mm/dd/yy';
    var TPMETA_ACCEPT_FORMATS = [ 'mm/dd/yy', 'MM d, yy', 'M d, yy', 'yy-mm-dd', 'dd/mm/yy', 'D, M d, yy' ];

    function tpmetaParseDateLenient( str ) {
        if ( ! str ) return null;
        str = String( str ).trim();
        if ( ! str ) return null;
        if ( ! $.datepicker ) return null;
        for ( var i = 0; i < TPMETA_ACCEPT_FORMATS.length; i++ ) {
            try {
                var d = $.datepicker.parseDate( TPMETA_ACCEPT_FORMATS[ i ], str );
                if ( d ) return d;
            } catch ( e ) {}
        }
        var t = Date.parse( str );
        return isNaN( t ) ? null : new Date( t );
    }

    $('.tm-datepicker-input').each(function () {
        if ( ! this.value ) return;
        var d = tpmetaParseDateLenient( this.value );
        if ( d ) {
            this.value = $.datepicker.formatDate( TPMETA_DATE_FORMAT, d );
        }
    });

    $('.tm-datepicker-input').datepicker({
        dateFormat: TPMETA_DATE_FORMAT,
        onSelect: function () {
            // Dispatch a NATIVE bubbling change event, not a jQuery synthetic
            // trigger. jQuery's .trigger('change') only fires jQuery-bound
            // (.on/.bind) handlers — it is invisible to native addEventListener
            // listeners (e.g. the repeater's bindRow change handler). A real
            // dispatchEvent fires both, so syncAll() is always called.
            this.dispatchEvent( new Event( 'change', { bubbles: true } ) );
        },
        beforeShow: function(input, inst) {
            setTimeout(function() {
                $('#ui-datepicker-div').addClass('tm-datepicker');
            }, 0);
        }
    });


    // Shared colorpicker init — always passes the change callback so the
    // preview button background updates in real time.
    function tpInitColorpicker($scope) {
        var $inputs = $scope
            ? $scope.find('.tm-colorpicker-input').not('.wp-color-picker')
            : $('.tm-colorpicker-input').not('.wp-color-picker');

        // Gradient stop-colour inputs (.tpgrd-stop-color) MUST be initialised
        // exclusively by TPMetaGradient.boot so they get the syncStops callback.
        // Wrapping them here with a generic handler breaks the live preview and
        // hidden-JSON update — the gradient state and visible inputs diverge.
        // This filter applies regardless of which script loads first.
        $inputs = $inputs.filter(function() {
            return !$(this).hasClass('tpgrd-stop-color');
        });

        $inputs.wpColorPicker({
            change: function(event, ui) {
                var hex = ui.color.toString();
                $(this).closest('.wp-picker-container')
                       .find('.wp-color-result')
                       .css('background-color', hex);
            }
        });
    }

    tpInitColorpicker();

    // Close any open color picker when clicking outside its container.
    // WP's own body-level handler can miss clicks in some WP admin contexts.
    $(document).on('mousedown.tpcolorpicker', function(e) {
        if (!$(e.target).closest('.wp-picker-container').length) {
            $('.wp-picker-container.wp-picker-active').each(function() {
                $(this).removeClass('wp-picker-active');
                $(this).find('.wp-picker-holder').hide();
                // Must remove wp-picker-open too — WP's toggle checks for it
                // and calls close() instead of open() if it's still present.
                $(this).find('.wp-color-result')
                       .attr('aria-expanded', 'false')
                       .removeClass('wp-picker-open');
            });
        }
    });

    // ── Multicolor repeater: carrier rebuild ─────────────────────────────────

    // Shared rebuild helper: reads every checked swatch in the carrier's sibling
    // group and writes the resulting JSON map into the carrier hidden input.
    function tpMcRebuildCarrier( $carrier ) {
        var map = {};
        $carrier.siblings( '.tm-multicolor-group' )
                .find( '.tm-mc-rpt-picker' )
                .each( function() {
                    if ( $( this ).prop('checked') ) {
                        map[ $( this ).data('mc-key') ] = $( this ).data('mc-color');
                    }
                } );
        $carrier.val( JSON.stringify( map ) );
    }

    // CAPTURE-phase native listener — fires BEFORE any bubble-phase handler.
    //
    // The options-panel repeater attaches a bubble-phase native 'change' on each
    // row (bindRow) that immediately calls syncAll() → collectRow(). collectRow()
    // reads the carrier hidden input as the authoritative value for multicolor
    // sub-fields. If we update the carrier in jQuery's bubble-phase delegated
    // handler (document-level, fires AFTER the row-level bubble handler), the
    // carrier is still stale when collectRow() reads it, so the old value is
    // saved instead of the user's selection.
    //
    // Using the CAPTURE phase means this fires top-down, before any bubble-phase
    // handler at any element — carrier is always correct by the time syncAll()
    // reads it.
    document.addEventListener( 'change', function ( e ) {
        var t = e.target;
        if ( ! t || ! t.classList || ! t.classList.contains( 'tm-mc-rpt-picker' ) ) return;

        var cid   = t.getAttribute( 'data-mc-carrier' );
        if ( ! cid ) return;

        var group = t.closest( '.tm-multicolor-group' );
        if ( ! group ) return;

        // Carrier is a sibling of .tm-multicolor-group.
        var carrier = group.parentElement
            ? group.parentElement.querySelector( 'input.tm-multicolor-carrier-' + cid )
            : null;
        // Fallback: wider row wrapper search.
        if ( ! carrier ) {
            var row = t.closest( '.tp-metabox-repeater-item-wrapper, .tm-rptr-body, .tm-repeater-row' );
            carrier = row ? row.querySelector( 'input.tm-multicolor-carrier-' + cid ) : null;
        }
        if ( ! carrier ) return;

        var map = {};
        group.querySelectorAll( '.tm-mc-rpt-picker' ).forEach( function ( chk ) {
            if ( chk.checked ) map[ chk.dataset.mcKey ] = chk.dataset.mcColor;
        } );
        carrier.value = JSON.stringify( map );
    }, true /* capture */ );

    // On metabox form submit: final safety sync for any carriers whose change
    // event was never fired (e.g. cloned rows the user never touched).
    $( document ).on( 'submit', '#post, #post-new, .edit-post-sidebar form', function() {
        $( 'input.tm-multicolor-carrier', this ).each( function() {
            tpMcRebuildCarrier( $( this ) );
        } );
    } );

    $(document).on('click', '.tp-metabox-repeater-collapse', function(x){
        x.preventDefault();
        var $wrapper = $(this).parent().find('.tp-metabox-repeater-item-wrapper');
        var expanding = !$wrapper.is(':visible');
        $wrapper.slideToggle(300, function() {
            // Init colorpickers in freshly added (not yet initialized) rows.
            if (expanding) {
                tpInitColorpicker($(this));
            }
        });
    });

    $(document).ready(function(){
        $('.tm-meta-wrapper').closest('.inside').addClass('pure-metafields');
        $('.tp-metabox-repeater-row .tp-metabox-repeater-item-wrapper').slideUp('300');
    });

    $(document).on('click', '.tm-image-actions .tm-edit', function(e){
        e.preventDefault();

        const attachmentId = $(this).data('attachment-id');
        const attachment = wp.media.attachment(attachmentId);

        // Fetch ensures the attachment details load
        attachment.fetch().then(() => {
            const frame = wp.media({
                title: 'Edit Image',
                multiple: false,
                library: {
                    type: 'image'
                },
                button: {
                    text: 'Update Image'
                }
            });

             // Add the attachment as selected and show right panel
             frame.on('open', function(){
                const selection = frame.state().get('selection');
                selection.reset([attachment]); // single image

                // Optional: Hide the library panel
                $('.media-frame-router, .media-frame-menu').hide();
                $('.media-frame-content').addClass('single-image-edit');
            });

            frame.open();
        });
    });

    // Gallery item edit — replace a single image inside a gallery field.
    // Opens the media library pre-selecting the current image; on selection
    // the item thumbnail, data-image_id, and the hidden CSV value are updated.
    $(document).on('click', '.tm-gallery-actions .tm-edit', function(e) {
        e.preventDefault();

        var $btn          = $(this);
        var attachmentId  = $btn.data('attachment-id');
        var $item         = $btn.closest('.tm-gallery-item');
        var $galleryField = $btn.closest('.tm-gallery-field');
        var attachment    = wp.media.attachment(attachmentId);

        attachment.fetch().then(function() {
            var frame = wp.media({
                title:    'Replace Image',
                multiple: false,
                library:  { type: 'image' },
                button:   { text: 'Use this image' }
            });

            // Pre-select the current image so the user can easily swap it.
            frame.on('open', function() {
                var selection = frame.state().get('selection');
                selection.reset([attachment]);
            });

            frame.on('select', function() {
                var newAtt   = frame.state().get('selection').first().toJSON();
                var thumbURL = (newAtt.sizes && newAtt.sizes.thumbnail)
                    ? newAtt.sizes.thumbnail.url
                    : (newAtt.url || '');

                // Update thumbnail, ID attributes, and action link data.
                $item.find('img').attr('src', thumbURL);
                $item.attr('data-image_id', newAtt.id);
                $item.find('[data-attachment-id]').attr('data-attachment-id', newAtt.id);

                // Rebuild the hidden CSV from all current gallery items.
                var ids = [];
                $galleryField.find('.tm-gallery-item').each(function() {
                    ids.push($(this).attr('data-image_id'));
                });
                $galleryField.find('.tm-gallery-value, input[type="hidden"]').val(ids.join(','));
            });

            frame.open();
        });
    });

})( jQuery );
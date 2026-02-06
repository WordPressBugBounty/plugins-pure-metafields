;(function( $ ){
    "use scrict";

    $(document).ready(function(){
        $('.tm-select-field.select2').each(function(){
            $(this).select2();
        });
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
        var isHiddenField = $(this).parent().children('input[type="hidden"]');
        if($(this).is(':checked')){
            $(this).val('on');
            if(isHiddenField.length){
                isHiddenField.val('on');
            }
        }else{
            $(this).val('off');
            if(isHiddenField.length){
                isHiddenField.val('off');
            }
        }
    })

    $('.tm-datepicker-input').datepicker({
        beforeShow: function(input, inst) {
            setTimeout(function() {
            // Add your custom class to the datepicker's wrapper
            $('#ui-datepicker-div').addClass('tm-datepicker');
            }, 0);
        }
    });


    $('.tm-colorpicker-input').wpColorPicker({});

    $(document).on('click', '.tp-metabox-repeater-collapse', function(x){
        x.preventDefault();
        $(this).parent().find('.tp-metabox-repeater-item-wrapper').slideToggle();
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
    
})( jQuery );
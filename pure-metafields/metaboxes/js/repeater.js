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
        
        $(document).on('click', '.tp-delete-row', function(){
            
            const rows = $(this).closest('.tp-metabox-repeater').find('.tp-metabox-repeater-row').length
            if(rows > 1){
                $(this).parent().remove()
            }
        })

        $('.tm-repeater-select-field.select2').each(function(indx, el){
            $(this).select2();
            $(el).on('select2:select', function (e) {
                $(e.target).closest('.repeater-field').find('input[type="hidden"]').val(JSON.stringify($(e.target).val()));
            });
        });

        $('.tm-repeater-select-field.normal').each(function(indx, el){
            $(el).on('change', function (e) {
                $(e.target).closest('.repeater-field').find('input[type="hidden"]').val(JSON.stringify($(e.target).val()));
            });
        });
    })

    const repeater_actions = function(){

        function updateCounter($repeater) {
            // Count visible rows (ignore hidden template)
            let count = $repeater.find('.tp-metabox-repeater-row:not(.tp-hidden-template)').length;
            $repeater.find('.tp-row-counter').val(count);

            // Renumber visible rows
            $repeater.find('.tp-metabox-repeater-row:not(.tp-hidden-template)').each(function(index) {
                $(this).find('.tp-metabox-repeater-collapse-text')
                    .text(`Item ${index + 1}`)
                    .attr('data-count', index + 1);
            });
        }

        $(document).on('click', '.tp-add-row', function() {
            let $repeater = $(this).closest('.tp-repeater');
            let $rows = $repeater.find('.tp-metabox-repeater-row');
            let $hiddenTemplate = $rows.filter('.tp-hidden-template');

            let $newRow;
            if ($hiddenTemplate.length) {
                // Reuse hidden row
                $newRow = $hiddenTemplate.removeClass('tp-hidden-template').show();
            } else {
                // Clone first row
                $newRow = $rows.first().clone();
                $newRow.appendTo($repeater.find('.tp-metabox-repeater'));
            }

            // Reset inputs except hidden fields
            $newRow.find('input, select, textarea').not('[type=hidden]').val('');

            // Update counter + labels
            updateCounter($repeater);

            $(document).trigger('row_loaded');
        });

        $(document).on('click', '.tp-delete-row', function() {
            let $repeater = $(this).closest('.tp-repeater');
            let $rows = $repeater.find('.tp-metabox-repeater-row:not(.tp-hidden-template)');

            if ($rows.length > 1) {
                // Remove clicked row
                $(this).closest('.tp-metabox-repeater-row').remove();
            } else {
                // Last row → hide instead of remove
                $rows.first().addClass('tp-hidden-template').hide();
            }

            // Always update counter after action
            updateCounter($repeater);
        });

        $(document).on('click change', '.tm-repeater-conditional', function(){
            var closestRow      = $(this).closest('.tp-metabox-repeater-row')
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
        
        const repeaters = document.querySelectorAll(".tp-metabox-repeater");
        dragula(Array.from(repeaters),{
            moves: function (el, container, handle) {
                return handle.classList.contains('tp-metabox-repeater-collapse');
            },
            direction:'vertical'
        })

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
                attchmentURL = attachment.sizes.thumbnail? attachment.sizes.thumbnail.url : attachment.sizes.full.url;
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
                    $this.closest('.repeater-field').find('.tm-gallery-container').append(`
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
            $(this).select2();
            $(el).on('select2:select', function (e) {
                $(e.target).closest('.repeater-field').find('input[type="hidden"]').val(JSON.stringify($(e.target).val()));
            });
        });
        $('.tm-repeater-select-field.select2').each(function(indx, el){
            $(this).select2();
            $(el).on('select2:unselect', function (e) {
                $(e.target).closest('.repeater-field').find('input[type="hidden"]').val(JSON.stringify($(e.target).val()));
            });
        });

        $('.tm-repeater-select-field.normal').each(function(indx, el){
            $(el).on('change', function (e) {
                $(e.target).closest('.repeater-field').find('input[type="hidden"]').val(JSON.stringify($(e.target).val()));
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

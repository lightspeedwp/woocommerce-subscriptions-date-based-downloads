var WC_PDD = {

    init: function() {

        var download_box_selector = '#general_product_data .options_group.show_if_downloadable';

        //Add in the date text field
        var date_field = '<input type="text" class="file_date input_text" placeholder="' + wc_pdd_edit_params.placeholder + '" name="_wc_file_dates[]" value="">';
        var date_field_end = '<input type="text" class="file_date input_text" placeholder="' + wc_pdd_edit_params.placeholder_end + '" name="_wc_file_dates_end[]" value="">';

        if ( 0 !== jQuery( download_box_selector ).length ) {
            this.addDateField( download_box_selector, date_field, date_field_end );
            this.replaceNewDateField( download_box_selector, date_field, date_field_end );
            this.runDatepicker();
        }

        this.loadDatesForVariations();
    },

    addDateField: function ( selector, date_field, date_field_end ) {
        var counter = 0;

        var file_dates = wc_pdd_edit_params.file_dates.split(',');

        jQuery( selector ).find( '.file_name' ).each( function() {
            jQuery( this ).append( date_field );

            if ( undefined !== file_dates[ counter ] ) {
                jQuery( this ).find( '.file_date' ).val( file_dates[ counter ] );
            }
            counter++;
        });

        counter = 0;
		console.log( wc_pdd_edit_params );
		if ( undefined !== wc_pdd_edit_params.file_dates_end.split ) {
			var file_dates_end = wc_pdd_edit_params.file_dates_end.split(',');
			jQuery( selector ).find( '.file_url' ).each( function() {
				jQuery( this ).append( date_field_end );

				if ( undefined !== file_dates_end[ counter ] ) {
					jQuery( this ).find( '.file_date' ).val( file_dates_end[ counter ] );
				}
				counter++;
			});			
		}
    },

    replaceNewDateField: function ( selector, date_field, date_field_end ) {

        var add_new_html = jQuery( selector ).find( '.button.insert' ).attr( 'data-row' );
        add_new_html = jQuery( add_new_html );
        add_new_html.find( '.file_name' ).append( date_field );
        add_new_html.find( '.file_url' ).append( date_field_end );

        jQuery( selector ).find( '.button.insert' ).attr( 'data-row', '<tr>' + add_new_html.html() + '</tr>' )

        // Trigger the date selector for the new item.
        jQuery( selector + ' .button.insert' ).on( 'click', function() {

            setTimeout( function(){
                jQuery( document.body ).trigger( 'wc-init-datepickers' );
            }  , 500 );

        });
    },

    runDatepicker: function() {
        // Initiate the Date Picker
        jQuery( document.body ).on( 'wc-init-datepickers', function() {
            jQuery( '.file_name .file_date, .file_url .file_date' ).datepicker({
                dateFormat: 'yy-mm-dd',
                numberOfMonths: 1,
                showButtonPanel: true
            });
        }).trigger( 'wc-init-datepickers' );
    },

    loadDatesForVariations: function () {

        var $thisObj = this;

        jQuery( document.body ).on( 'woocommerce_variations_loaded', function() {
            var download_box_selector = '#variable_product_options .show_if_variation_downloadable';

            $thisObj.addDateFieldVariations( download_box_selector );
            $thisObj.runDatepicker();
            $thisObj.fillVariationDateValues( );
            $thisObj.replaceVariationsNewDateField( download_box_selector );
            $thisObj.reloadVariationsInfoOnSave( download_box_selector );

        });

    },
    addDateFieldVariations: function ( selector ) {
        var counter = 0;

        jQuery( selector ).find( '.file_name' ).each( function() {
            var date_field = jQuery( this ).find( '.input_text' ).clone();

            date_field.addClass( 'file_date' );
            date_field.val("");
            date_field.attr('placeholder','File Date');
            date_field.attr('value','');

            //Get the name with the id inside.
            var input_name = date_field.attr( 'name' );
            input_name = input_name.replace( '_wc_variation_file_names', '_wc_variation_file_dates' );
            date_field.attr( 'name', input_name );

            jQuery( this ).append( date_field );

            counter++;
        });
        
    },

    fillVariationDateValues: function ( ) {
        if ( wc_pdd_edit_params.variation_file_dates ) {

            var dates = wc_pdd_edit_params.variation_file_dates;

            for (var key in dates) {
                if (dates.hasOwnProperty(key)) {

                    var counter = 0;
                    var file_dates = dates[key].split(',');

                    jQuery( 'input[name="_wc_variation_file_dates[' + key + '][]"]' ).each( function() {

                        if ( undefined !== file_dates[ counter ] ) {
                            jQuery( this ).val( file_dates[ counter ] );
                        }
                        counter++;
                    });

                }
            }

        }
    },

    replaceVariationsNewDateField: function ( selector ) {

        jQuery( selector ).find( '.button.insert' ).each( function ( ) {

            var add_new_html = jQuery( this ).attr( 'data-row' );

            //Parse this into a node
            add_new_html = jQuery( add_new_html );

            if ( false === jQuery( this ).hasClass( 'date-added' )  ) {

                var variation_date_field = add_new_html.find('.file_name .input_text').clone( true );

                variation_date_field.addClass('file_date');
                variation_date_field.val("");
                variation_date_field.attr('placeholder', 'File Date');
                variation_date_field.attr('value', '');

                //Get the name with the id inside.
                var input_name = variation_date_field.attr('name');
                input_name = input_name.replace('_wc_variation_file_names', '_wc_variation_file_dates');
                variation_date_field.attr('name', input_name);

                add_new_html.find('.file_name').append(variation_date_field);

                jQuery(this).attr('data-row', '<tr>' + add_new_html.html() + '</tr>');

                jQuery(this).addClass( 'date-added' );

                // Trigger the date selector for the new item.
                jQuery(this).on('click', function () {

                    setTimeout(function () {
                        jQuery(document.body).trigger('wc-init-datepickers');
                    }, 500);

                });
            }
        });

    },

    reloadVariationsInfoOnSave: function( selector ) {

        jQuery( document.body ).on( 'woocommerce_variations_save_variations_button', function() {
            var new_variation_dates = [];
            var formatted_dates = {};

            jQuery( selector ).find( '.file_name .file_date' ).each( function( ){
                var current_date = jQuery( this ).val();

                var id = jQuery( this ).attr( 'name' );
                id = id.replace('_wc_variation_file_dates[', '');
                id = id.replace('][]', '');

                if ( undefined === new_variation_dates[id] ) {
                    new_variation_dates[id] = [];
                }
                new_variation_dates[id].push( current_date );
            });

            if ( 0 !== new_variation_dates.length ) {
                for (var key in new_variation_dates) {
                    if (new_variation_dates.hasOwnProperty(key)) {
                        formatted_dates[ key ] =  new_variation_dates[ key ].join(',');
                    }
                }

                wc_pdd_edit_params.variation_file_dates = formatted_dates;
            }

        });

    }
};

jQuery(document).ready(function() {
    WC_PDD.init();
});


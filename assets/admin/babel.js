((function($) {
    
    $(document).ready(function() {
        var Request, Toastr = null;
        if (typeof Grav !== 'undefined' && Grav && Grav.default && Grav.default.Utils) {
            Request = Grav.default.Utils.request;
            Toastr = Grav.default.Utils.toastr;
        }
        var indexer = $('#babel-index, #admin-nav-quick-tray .babel-reindex'),
            current = null, currentTray = null;
        if (!indexer.length) { return; }

        indexer.on('click', function(e) {
            e.preventDefault();
            var target = $(e.target),
                isTray = target.closest('#admin-nav-quick-tray').length,
                status = indexer.siblings('.babel-status'),
                errorDetails = indexer.siblings('.babel-error-details');
            current = status.clone(true);

            if (isTray) {
                target = target.is('i') ? target.parent() : target;
                currentTray = target.find('i').attr('class');
                target.find('i').attr('class', 'fa fa-fw fa-circle-o-notch fa-spin');
            }

            errorDetails
                .hide()
                .empty();

            status
                .removeClass('error success')
                .empty()
                .html('<i class="fa fa-circle-o-notch fa-spin" />');

            $.ajax({
                type: 'POST',
                url: GravAdmin.config.base_url_relative + '.json/task' + GravAdmin.config.param_sep + 'reindexBabel',
                data: { 'admin-nonce': GravAdmin.config.admin_nonce }
            }).done(function(done) {
                if (done.status === 'success') {
                    indexer.removeClass('critical').addClass('reindex');
                    status.removeClass('error').addClass('success');
                    Toastr.success(done.message);
                    setTimeout(function() {
                        document.location = GravAdmin.config.base_url_relative + '/babel';                
                    }, 750);                    
                } else {
                    indexer.removeClass('reindex').addClass('critical');
                    status.removeClass('success').addClass('error');
                    var error = done.message;
                    if (done.details) {
                        error += '<br />' + done.details;
                        errorDetails
                            .text(done.details)
                            .show();

                        status.replaceWith(current);
                    }

                    Toastr.error(error);
                }

                status.html(done.message);
            }).fail(function(error) {
                if (error.responseJSON && error.responseJSON.error) {
                    indexer.removeClass('reindex').addClass('critical');
                    errorDetails
                        .text(error.responseJSON.error.message)
                        .show();

                    status.replaceWith(current);
                }
            }).always(function() {
                target.find('i').attr('class', currentTray);
                current = null;
                currentTray = null;
            });
        })
        $('.admin-block #babeldomains').on('change', function() {
            if ($(this).val() == '*') {
                document.location = GravAdmin.config.base_url_relative + '/babel';                
            } else {
                document.location = GravAdmin.config.base_url_relative + '/babel/domain:' + $(this).val();                
            }
        });
        $('.admin-block .untranslated').on('click', function() {
            var $domain = $('.admin-block #babeldomains').val();
            var $lang = $(this).data('lang');
            var $count = $(this).data('count');
            babelset(0, $lang, $domain, $count);
        });
        $('.admin-block .translated').on('click', function() {
            var $domain = $('.admin-block #babeldomains').val();
            var $lang = $(this).data('lang');
            var $count = $(this).data('count');
            //document.location = GravAdmin.config.base_url_relative + '/babel';                
            //table.destroy();            
            babelset(1, $lang, $domain, $count);
        });
        $('.admin-block .all').on('click', function() {
            var $domain = $('.admin-block #babeldomains').val();
            var $lang = $(this).data('lang');
            var $count = $(this).data('count');
            //document.location = GravAdmin.config.base_url_relative + '/babel';                
            //table.destroy();            
            babelset(-1, $lang, $domain, $count);
        });
        
        $(document).on('click', '.admin-block .babel_copy', function() {            
            var $translation = $(this).closest('.babel_translation').find('.babel-translation').html();
            var $targetval = $(this).closest('tr').find('.babel_translated').val();
            if ($targetval === '') {
                $(this).closest('tr').find('.babel_translated').val($translation);
            }
        });
       
        $(document).on('click', '.admin-block .button.babel_save', function() {            
            var $doc_id = $(this).closest('tr').find('.babel_doc_id').val();
            var $translation = $(this).closest('tr').find('.babel_translated').val();
            var $babelize = $(this).closest('tr').find('.babel_translated');            
            babelsave($doc_id, $translation, $babelize);
        });
       
        $(document).on('change', '.admin-block .babel_translated', function() {            
            if (($(this).val().length  && $(this)[0].defaultValue != $(this).val()) || $(this).data('babelized')) {
                $(this).closest('tr').find('.babel_save').addClass('button');
            } else if ($(this).closest('tr').find('.babel_save').hasClass('button')) {
                $(this).closest('tr').find('.babel_save').removeClass('button');
            }
        });

        $(document).on('keydown', '.admin-block .babel_translated', function(event) {
            if((event.ctrlKey || event.metaKey) && event.which == 83) {
                if (($(this).val().length && $(this)[0].defaultValue != $(this).val()) || $(this).data('babelized')) {                
                    var $doc_id = $(this).closest('tr').find('.babel_doc_id').val();
                    var $translation = $(this).val();
                    var $babelize = $(this);            
                    babelsave($doc_id, $translation, $babelize);
                } else {
                    Toastr.error('Cannot save empty, unchanged or unbabelized definitions.');
                }
                event.preventDefault();
                return false;
            }            
        });
        
        $(document).keydown(function(event) {
            if((event.ctrlKey || event.metaKey) && event.which == 83) {
                
                event.preventDefault();
                return false;
            }
        });       
       
        $('.index-status #babel-reset').on('click', function() {
            babelreset();
        });
       
        $('.admin-block .export').on('click', function() {
            var $domain = $(this).data('domain');
            var $lang = $(this).data('lang');            
            babelexport($domain, $lang);
        });
       
        $('.index-status #babel-merge').on('click', function() {
            babelmerge();
        });
       
        var babelset = function(status, lang, domain, count) {
            $('#babels_table').dataTable( {
                dom: "Bfrtip",
                autoWidth: false,
                ordering: false,
                columns: [                    
                    { data: null, render: function ( data, type, row ) {
                        var $col =  '<div class="babel_definition"><div class="babel_save' + (data.babelized == '1' ? ' button' : '') + '"><i class="fa fa-save"></i></div><input class="babel_doc_id" type="hidden" value="' + data.doc_id + '" />' +                        
                                    data.language +
                                    '<div>' + data.domain + '</div><div><strong>' + data.doc_id.replace(data.language + '.' + data.domain + '.', '') + '</strong></div>';
                        return $col;
                    } },
                    { data: null, render: function ( data, type, row ) {
                        var $value = data.translated;
                        if (data.language + '.' + $value == data.doc_id) {
                            return '<textarea lang="' + data.language + '" dir="' + data.rtl + '" data-babelized="' + data.babelized + '" class="babel_translated"></textarea>';
                        } else {
                            return '<textarea lang="' + data.language + '" dir="' + data.rtl + '" data-babelized="' + data.babelized + '" class="babel_translated">' + data.translated + '</textarea>';
                        }
                    } },                
                    { data: null, render: function( data, type, row ) {
                            if (data.translations.length > 3) {
                                var $translations = JSON.parse(data.translations);                    
                                var $babel_translations = '<div class="babel_translations">';
                                $.each($translations, function(key, value) {
                                   $babel_translations += '<div class="babel_translation"><button class="babel_copy"><strong>' + key + '</strong>:</button> <translation class="babel-translation" lang="' + key + '" dir="' + value[1] + '">' + value[0] + '</translation></div>';
                                });
                                return $babel_translations + '</div>'
                            }
                            return "";
                    } },
                ],          
                destroy: true,
                ajax: {
                    url: GravAdmin.config.base_url_relative + '.json/task' + GravAdmin.config.param_sep + 'getSetBabel',
                    type: "POST",
                    data: { 
                        'admin-nonce': GravAdmin.config.admin_nonce,
                        'status': status,
                        'lang': lang,
                        'domain': domain,
                        'count': count                    
                    },
                    dataSrc: function(json) {
                        return json;
                    },
                }
            });        
        };
        
        var babelsave = function(doc_id, translation, babelize) {
            var that = this;
            that.babelize = babelize;
            $.ajax({
                type: 'POST',
                url: GravAdmin.config.base_url_relative + '.json/task' + GravAdmin.config.param_sep + 'saveBabel',                
                data: { 
                    'admin-nonce': GravAdmin.config.admin_nonce,
                    'doc_id': doc_id,
                    'translation': translation
                }
            }).done(function(done) {
                if (done.status === 'success') {
                    that.babelize.data('babelized', "1");
                    Toastr.success(done.message);
                } else {
                    var error = done.message;
                    Toastr.error(error);
                }
            }).fail(function(error) {
            }).always(function() {
            });            
        };

        var babelreset = function() {
            $.ajax({
                type: 'POST',
                url: GravAdmin.config.base_url_relative + '.json/task' + GravAdmin.config.param_sep + 'resetBabel',                
                data: { 
                    'admin-nonce': GravAdmin.config.admin_nonce
                }
            }).done(function(done) {
                if (done.status === 'success') {
                    Toastr.success(done.message);
                } else {
                    var error = done.message;
                    Toastr.error(error);
                }
            }).fail(function(error) {
            }).always(function() {
            });            
        };        
        
        var babelreset = function() {
            $.ajax({
                type: 'POST',
                url: GravAdmin.config.base_url_relative + '.json/task' + GravAdmin.config.param_sep + 'resetBabel',                
                data: { 
                    'admin-nonce': GravAdmin.config.admin_nonce
                }
            }).done(function(done) {
                if (done.status === 'success') {
                    Toastr.success(done.message);
                    setTimeout(function() {
                        document.location = GravAdmin.config.base_url_relative + '/babel';                
                    }, 750);
                } else {
                    var error = done.message;
                    Toastr.error(error);
                }
            }).fail(function(error) {
            }).always(function() {
            });            
        };        

        var babelexport = function($domain, $lang) {
            $.ajax({
                type: 'POST',
                url: GravAdmin.config.base_url_relative + '.json/task' + GravAdmin.config.param_sep + 'exportBabel',                
                data: { 
                    'admin-nonce': GravAdmin.config.admin_nonce,
                    'domain': $domain,
                    'lang': $lang
                }
            }).done(function(done) {
                if (done.status === 'success') {
                    Toastr.success(done.message);
                } else {
                    var error = done.message;
                    Toastr.error(error);
                }
            }).fail(function(error) {
            }).always(function() {
            });            
        };        

        var babelmerge = function() {
            console.log('wwelrjer');
            $.ajax({
                type: 'POST',
                url: GravAdmin.config.base_url_relative + '.json/task' + GravAdmin.config.param_sep + 'mergeBabel',                
                data: { 
                    'admin-nonce': GravAdmin.config.admin_nonce
                }
            }).done(function(done) {
                if (done.status === 'success') {
                    Toastr.success(done.message);
                } else {
                    var error = done.message;
                    Toastr.error(error);
                }
            }).fail(function(error) {
            }).always(function() {
            });            
        };        
        
    });
})(jQuery));

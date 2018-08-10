((function($) {
    $(document).ready(function() {
        var Request, Toastr = null;
        if (typeof Grav !== 'undefined' && Grav && Grav.default && Grav.default.Utils) {
            Request = Grav.default.Utils.request;
            Toastr = Grav.default.Utils.toastr;
        }
        var babelAdmin = $('#admin-nav-quick-tray .babel-translator-admin');
        if (!babelAdmin.length) { return; }

        babelAdmin.on('click', function(e) {
            document.location = GravAdmin.config.base_url_relative + '/babel';
        });
    });
})(jQuery));

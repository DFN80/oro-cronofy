define(function(require) {
    'use strict';


    var oauthView;
    var BaseView = require('oroui/js/app/views/base/view');
    var __ = require('orotranslation/js/translator');
    var $ = require('jquery');
    var mediator = require('oroui/js/mediator');
    var Modal = require('oroui/js/modal');
    var Messenger = require('oroui/js/messenger');
    var loadingMask = require('oroui/js/app/views/loading-mask-view');

    oauthView = BaseView.extend({
        autoRender: false,
        disconnectUrl: null,
        identifier: null,
        el: $('#oauth-disconnect'),
        events: {
            "click": "confirm"
        },

        initialize: function (options) {
            this.disconnectUrl = options.disconnectUrl;
            this.identifier = options.identifier;
        },

        /**
         * Register listener for postMessage and open Cronofy Authenticate Page in new window.
         */
        confirm: function () {

            var confirmationDialog = new Modal({
                title: 'Are you sure?',
                okText: __('dfn.oro_cronofy.oauth.disconnect_confirm.ok'),
                content: __('dfn.oro_cronofy.oauth.disconnect_confirm.content', {calendar: this.identifier}),
                className: 'modal oro-modal-normal',
                okButtonClass: 'btn-primary btn-large'
            });

            var disconnectUrl = this.disconnectUrl;
            var thisView = this;

            confirmationDialog.on('ok', function() {
                var mask = new loadingMask({
                   container: $('#page')
                });
                mask.show();
                $.ajax({
                    url:  disconnectUrl,
                    success: function(data) {
                        mask.hide();
                        confirmationDialog.close();
                        Messenger.notificationFlashMessage('success', data.identifier+' disconnected.');
                        mediator.execute('refreshPage');
                    },
                    error: function() {
                        Messenger.notificationFlashMessage('error', __('Error disconnecting calendar.'));
                    }
                });
            });

            confirmationDialog.open();

        }
    });

    return oauthView;
});



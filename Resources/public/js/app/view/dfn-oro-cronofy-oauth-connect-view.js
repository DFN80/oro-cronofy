define(function(require) {
    'use strict';


    var oauthView;
    var BaseView = require('oroui/js/app/views/base/view');
    var __ = require('orotranslation/js/translator');
    var mediator = require('oroui/js/mediator');

    oauthView = BaseView.extend({
        autoRender: true,
        container: '.cronofy-oauth-connect-holder',
        containerMethod: 'prepend',
        tagName: "button",
        attributes: {"type": "button", "class": "btn"},
        connectUrl: null,
        popup: null,

        initialize: function (options) {
            this.connectUrl = options.connectUrl;
            this.$el.html(__('dfn.oro_cronofy.oauth.connect'));

            //Bind the views this scope to methods.
            _.bindAll(this, "open", "receiveMessage");
        },
        events: {
            "click": "open"
        },

        /**
         * Register listener for postMessage and open Cronofy Authenticate Page in new window.
         */
        open: function () {
            //Add postMessage listener
            window.addEventListener("message", this.receiveMessage);
            this.popup = window.open(this.connectUrl, 'oauth', 'width=500,height=870,menubar=no');
        },

        receiveMessage: function (event) {
            var action = event.data.action;

            if (action === "oauth") {
                //Get elevated permissions URL and redirect popup to that
                this.popup.location.href = event.data.elevateUrl;
            } else if (action === "complete") {
                if (event.data.response === "success") {
                    mediator.execute(
                        'showFlashMessage',
                        'success',
                        'Synchronizing ' + event.data.identifier
                    );
                    mediator.execute('refreshPage');
                } else {
                    mediator.execute(
                        'showFlashMessage',
                        'error',
                        'There was a problem authenticating your Calendar.'
                    );
                    mediator.execute('refreshPage');
                }
            }
        },

        /**
         * Call un-register listener and then run parent remove method.
         */
        remove: function () {
            window.removeEventListener("message", this.receiveMessage);
            BaseView.prototype.remove.apply(this, arguments);
        }

    });

    return oauthView;
});

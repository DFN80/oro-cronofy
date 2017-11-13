define(function(require) {
    'use strict';


    var oauthView;
    var BaseView = require('oroui/js/app/views/base/view');
    var __ = require('orotranslation/js/translator');
    var $ = require('jquery');
    var mediator = require('oroui/js/mediator');
    var layout = require('oroui/js/layout');
    var routing = require('routing');
    var loadingMask = require('oroui/js/app/views/loading-mask-view');

    oauthView = BaseView.extend({
        autoRender: true,
        container: '.cronofy-oauth-connect-holder',
        containerMethod: 'prepend',
        tagName: "button",
        attributes: {"type": "button", "class": "btn"},
        connectUrl: null,

        initialize: function (options) {
            this.connectUrl = options.connectUrl;
            this.$el.html(__('dfn.oro_cronofy.oauth.connect'));
        },
        events: {
            "click": "open"
        },

        /**
         * Register listener for postMessage and open Cronofy Authenticate Page in new window.
         */
        open: function () {
            //Add postmessage listener
            window.addEventListener("message", this.receiveMessage);
            window.open(this.connectUrl, 'oauth', 'width=500,height=870,menubar=no');
        },

        receiveMessage: function (event) {
            //Do something with the data returned from the postmessage
            console.log(event);
            mediator.execute(
                'showFlashMessage',
                'success',
                'Synchronizing ' + event.data
            );
            mediator.execute('refreshPage');
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

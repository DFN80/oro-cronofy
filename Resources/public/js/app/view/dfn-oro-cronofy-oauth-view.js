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
        container: '.cronofy-oauth-holder',
        containerMethod: 'prepend',
        tagName: "button",
        attributes: {"type": "button", "class": "btn"},
        authUrl: null,

        initialize: function (options) {
            this.authUrl = options.authUrl;
            this.$el.html(__('dfn.oro_cronofy.oauth.connect'));
        },
        events: {
            "click": "open"
        },

        open: function () {
            //Add postmessage listener
            window.addEventListener("message", this.receiveMessage);
            window.open(this.authUrl, 'oauth', 'width=500,height=870,menubar=no');
        },

        receiveMessage: function (event) {
            //Do something with the data returned from the postmessage
            console.log(event);
        }

    });

    return oauthView;
});

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
        scope: "create_calendar read_events create_event delete_event read_free_busy change_participation_status",
        state: null,

        initialize: function (options) {
            this.state = options.state;
            this.authUrl = "//app.cronofy.com/oauth/authorize?response_type=code&client_id="+options.clientId+"&redirect_uri="+options.redirectUrl+"&scope="+this.scope+"&state="+this.state;
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

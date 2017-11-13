define(function(require) {
    'use strict';


    var oauthView;
    var BaseView = require('oroui/js/app/views/base/view');
    var __ = require('orotranslation/js/translator');
    var $ = require('jquery');
    var mediator = require('oroui/js/mediator');
    var DialogWidget = require('oro/dialog-widget');
    var layout = require('oroui/js/layout');
    var routing = require('routing');
    var loadingMask = require('oroui/js/app/views/loading-mask-view');

    oauthView = BaseView.extend({
        autoRender: false,
        disconnectUrl: null,

        initialize: function (options) {
            this.setElement($('#oauth-disconnect'));
            this.disconnectUrl = options.disconnectUrl;
            //this.$el.html(__('dfn.oro_cronofy.oauth.connect'));
        },
        events: {
            "click": "confirm"
        },

        /**
         * Register listener for postMessage and open Cronofy Authenticate Page in new window.
         */
        confirm: function () {
            var widget = new DialogWidget({
                'url': this.href,
                'title': 'test',
                'stateEnabled': false,
                'incrementalPosition': false,
                'dialogOptions': {
                    'width': 650,
                    'autoResize': true,
                    'modal': true,
                    'minHeight': 100
                }
            });
            widget.render();
        }
    });

    return oauthView;
});

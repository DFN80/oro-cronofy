define(function(require) {
    'use strict';

    // var _ = require('underscore');
    // var __ = require('orotranslation/js/translator');
    // var $ = require('jquery');

    var oauthComponent;
    var BaseComponent = require('oroui/js/app/components/base/component');
    var oauthView = require('dfnorocronofy/js/app/view/dfn-oro-cronofy-oauth-disconnect-view');

    oauthComponent = BaseComponent.extend({
        viewType: oauthView,
        /**
         * @constructor
         * @param {Object} options - provides oauth view with configuration
         */
        initialize: function (options) {
            this.view = new this.viewType(options);
        }
    });
    return oauthComponent;
});

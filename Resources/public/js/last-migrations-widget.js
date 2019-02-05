define(
    [
        'jquery',
        'underscore',
        'pim/router',
        'pim/dashboard/abstract-widget',
        'clickandmortar/migrations-manager/template/last-migrations-widget'
    ],
    function ($, _, router, AbstractWidget, template) {
        'use strict';

        return AbstractWidget.extend({
            options: {
                contentLoaded: false
            },

            template: _.template(template)
        });
    }
);

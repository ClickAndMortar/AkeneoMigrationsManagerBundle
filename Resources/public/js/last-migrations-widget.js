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

            template: _.template(template),

            events: {
                'click .show-details-btn': 'showMigrationDetails'
            },

            /**
             * Redirect to the clicked migration page
             *
             * @param {Object} event
             */
            showMigrationDetails: function (event) {
                event.preventDefault();

                router.redirectToRoute(
                    'pim_enrich_job_tracker_show',
                    { id: $(event.currentTarget).data('id') }
                );
            },
        });
    }
);

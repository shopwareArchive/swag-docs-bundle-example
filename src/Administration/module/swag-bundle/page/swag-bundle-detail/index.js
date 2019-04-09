import { Component, Mixin } from 'src/core/shopware';
import template from './swag-bundle-detail.html.twig';

Component.register('swag-bundle-detail', {

    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            repository: null,
            bundle: null,
            isLoading: false
        };
    },

    created() {
        this.repository = this.repositoryFactory.create('swag_bundle');

        this.getBundle();
    },

    methods: {
        getBundle() {
            const id = this.$route.params.id;

            this.repository
                .get(id, this.context)
                .then((entity) => {
                    this.bundle = entity;
                });
        },

        onClickSave() {
            this.isLoading = true;

            // sends the request immediately
            this.repository
                .save(this.bundle, this.context)
                .then(() => {
                    // the entity is stateless, the new data has to be fetched from the server, if required
                    this.getBundle();
                    this.isLoading = false;
                }).catch((exception) => {
                    this.isLoading = false;
                    this.createNotificationError({
                        title: 'The bundle could not be saved.',
                        message: exception
                    });
                });
        }
    }
});

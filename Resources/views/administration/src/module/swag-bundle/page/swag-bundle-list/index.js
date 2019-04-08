import { Component } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './swag-bundle-list.html.twig'

Component.register('swag-bundle-list', {

    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    data() {
        return {
            repository: null,
            bundles: null
        }
    },

    computed: {
        columns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                label: 'Name',
                routerLink: 'swag.bundle.detail',
                inlineEdit: 'string',
                allowResize: true,
                primary: true
            }, {
                property: 'link',
                dataIndex: 'link',
                label: 'Website',
                inlineEdit: 'string',
                allowResize: true
            }, {
                property: 'description',
                dataIndex: 'description',
                label: 'Description',
                inlineEdit: 'string',
                allowResize: true
            }]
        }
    },

    created() {
        this.repository = this.repositoryFactory.create('product_manufacturer');
        this.isLoading = true;

        this.repository
            .search(new Criteria(), this.context)
            .then((result) => {
                this.bundles = result;
                this.isLoading = false;
            });
    }
});

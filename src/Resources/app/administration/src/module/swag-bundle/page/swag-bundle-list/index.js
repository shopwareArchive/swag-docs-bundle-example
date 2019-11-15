import template from './swag-bundle-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('swag-bundle-list', {
    template,

    inject: [
        'repositoryFactory'
    ],

    data() {
        return {
            repository: null,
            bundles: null
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        columns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                label: this.$t('swag-bundle.list.columnName'),
                routerLink: 'swag.bundle.detail',
                inlineEdit: 'string',
                allowResize: true,
                primary: true
            }, {
                property: 'discount',
                dataIndex: 'discount',
                label: this.$t('swag-bundle.list.columnDiscount'),
                inlineEdit: 'number',
                allowResize: true
            }, {
                property: 'discountType',
                dataIndex: 'discountType',
                label: this.$t('swag-bundle.list.columnDiscountType'),
                allowResize: true
            }];
        }
    },

    created() {
        this.repository = this.repositoryFactory.create('swag_bundle');

        this.repository
            .search(new Criteria(), Shopware.Context.api)
            .then((result) => {
                this.bundles = result;
            });
    }
});

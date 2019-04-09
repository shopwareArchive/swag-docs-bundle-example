import { Module } from 'src/core/shopware';
import './page/swag-bundle-list';
import './page/swag-bundle-detail';

Module.register('swag-bundle', {
    type: 'plugin',
    name: 'Bundle',
    description: 'Bundle products',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#ff3822',
    icon: 'default-shopping-paper-bag-product',

    routes: {
        list: {
            components: {
                default: 'swag-bundle-list'
            },
            path: 'list'
        },
        detail: {
            components: {
                default: 'swag-bundle-detail'
            },
            path: 'detail/:id'
        }
    },

    navigation: [{
        id: 'swag-bundle-list',
        label: 'Bundle',
        color: '#ff3822',
        path: 'swag.bundle.list',
        icon: 'default-shopping-paper-bag-product',
        position: 100
    }]
});

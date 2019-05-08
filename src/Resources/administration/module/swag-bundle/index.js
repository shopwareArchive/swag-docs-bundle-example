import { Module } from 'src/core/shopware';
import './page/swag-bundle-list';
import './page/swag-bundle-detail';
import './page/swag-bundle-create';

Module.register('swag-bundle', {
    type: 'plugin',
    name: 'Bundle',
    description: 'Bundle products',
    color: '#ff3d58',
    icon: 'default-shopping-paper-bag-product',

    routes: {
        list: {
            component: 'swag-bundle-list',
            path: 'list'
        },
        detail: {
            component: 'swag-bundle-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'swag.bundle.list'
            }
        },
        create: {
            component: 'swag-bundle-create',
            path: 'create',
            meta: {
                parentPath: 'swag.bundle.list'
            }
        },
    },

    navigation: [{
        label: 'Bundle',
        color: '#ff3d58',
        path: 'swag.bundle.list',
        icon: 'default-shopping-paper-bag-product',
        position: 100
    }]
});

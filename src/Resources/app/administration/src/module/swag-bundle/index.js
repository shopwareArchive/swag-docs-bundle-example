import './page/swag-bundle-list';
import './page/swag-bundle-detail';
import './page/swag-bundle-create';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('swag-bundle', {
    type: 'plugin',
    name: 'Bundle',
    title: 'swag-bundle.general.mainMenuItemGeneral',
    description: 'sw-property.general.descriptionTextModule',
    color: '#ff3d58',
    icon: 'default-shopping-paper-bag-product',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

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
        }
    },

    navigation: [{
        label: 'swag-bundle.general.mainMenuItemGeneral',
        color: '#ff3d58',
        path: 'swag.bundle.list',
        icon: 'default-shopping-paper-bag-product',
        position: 100
    }]
});

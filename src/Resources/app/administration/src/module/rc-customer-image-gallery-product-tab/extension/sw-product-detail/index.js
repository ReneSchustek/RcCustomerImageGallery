import template from './sw-product-detail.html.twig';

Shopware.Component.override('sw-product-detail', {
    template,
});

Shopware.Module.register('rc-customer-image-gallery-product-tab-route', {
    type: 'plugin',
    name: 'rc-customer-image-gallery-product-tab',
    title: 'rcCustomerImageGallery.productTab.title',

    routeMiddleware(next, currentRoute) {
        // Hängt die Galerie-Route nur an `sw.product.detail` an.
        if (currentRoute.name === 'sw.product.detail') {
            currentRoute.children = currentRoute.children || [];
            const exists = currentRoute.children.some((c) => c.name === 'sw.product.detail.rc-customer-image-gallery');
            if (!exists) {
                currentRoute.children.push({
                    name: 'sw.product.detail.rc-customer-image-gallery',
                    path: '/sw/product/detail/:id/rc-customer-image-gallery',
                    component: 'rc-cig-product-tab',
                    meta: {
                        parentPath: 'sw.product.index',
                    },
                });
            }
        }
        next(currentRoute);
    },
});

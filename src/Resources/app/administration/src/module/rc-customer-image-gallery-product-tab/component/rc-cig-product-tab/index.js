import template from './rc-cig-product-tab.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const MEDIA_IDS_FIELD = 'rc_customer_image_gallery_media_ids';

Component.register('rc-cig-product-tab', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            product: null,
            mediaItems: [],
            isLoading: false,
            isSaving: false,
            showMediaModal: false,
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        productId() {
            return this.$route.params.id;
        },

        mediaIds() {
            if (!this.product || !this.product.customFields) {
                return [];
            }
            const ids = this.product.customFields[MEDIA_IDS_FIELD];
            return Array.isArray(ids) ? ids : [];
        },
    },

    created() {
        this.loadProduct();
    },

    methods: {
        loadProduct() {
            this.isLoading = true;
            this.productRepository
                .get(this.productId, Shopware.Context.api)
                .then((product) => {
                    if (!product.customFields) {
                        product.customFields = {};
                    }
                    if (!Array.isArray(product.customFields[MEDIA_IDS_FIELD])) {
                        product.customFields[MEDIA_IDS_FIELD] = [];
                    }
                    this.product = product;
                    return this.loadMedia();
                })
                .catch((error) => {
                    this.createNotificationError({
                        title: this.$tc('global.default.error'),
                        message: error && error.message ? error.message : '',
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        // Loest die gespeicherten IDs in MediaEntities auf und stellt die gepflegte Reihenfolge wieder her.
        loadMedia() {
            const ids = this.mediaIds.filter((id) => typeof id === 'string' && id.length > 0);
            if (ids.length === 0) {
                this.mediaItems = [];
                return Promise.resolve();
            }

            const criteria = new Criteria(1, ids.length);
            criteria.setIds(ids);

            return this.mediaRepository.search(criteria, Shopware.Context.api).then((result) => {
                const byId = {};
                result.forEach((entity) => {
                    byId[entity.id] = entity;
                });
                this.mediaItems = ids.map((id) => byId[id]).filter((entity) => !!entity);
            });
        },

        // Schreibt die aktuelle Reihenfolge der Vorschau zurueck ins CustomField.
        syncIdsFromItems() {
            if (!this.product) {
                return;
            }
            this.product.customFields = this.product.customFields || {};
            this.product.customFields[MEDIA_IDS_FIELD] = this.mediaItems.map((entity) => entity.id);
        },

        onOpenMediaModal() {
            this.showMediaModal = true;
        },

        onCloseMediaModal() {
            this.showMediaModal = false;
        },

        // sw-media-modal-v2 liefert die Auswahl als Array von Media-Items.
        onMediaSelectionChange(selection) {
            const existingIds = this.mediaItems.map((entity) => entity.id);
            (selection || []).forEach((entity) => {
                if (entity && entity.id && existingIds.indexOf(entity.id) === -1) {
                    this.mediaItems.push(entity);
                    existingIds.push(entity.id);
                }
            });
            this.syncIdsFromItems();
            this.showMediaModal = false;
        },

        onRemoveMedia(index) {
            this.mediaItems.splice(index, 1);
            this.syncIdsFromItems();
        },

        onMoveUp(index) {
            if (index <= 0) {
                return;
            }
            const items = this.mediaItems;
            [items[index - 1], items[index]] = [items[index], items[index - 1]];
            this.syncIdsFromItems();
        },

        onMoveDown(index) {
            if (index >= this.mediaItems.length - 1) {
                return;
            }
            const items = this.mediaItems;
            [items[index + 1], items[index]] = [items[index], items[index + 1]];
            this.syncIdsFromItems();
        },

        mediaPreviewUrl(entity) {
            if (!entity) {
                return '';
            }
            return entity.url || '';
        },

        onSave() {
            if (!this.product) {
                return;
            }
            this.syncIdsFromItems();
            this.isSaving = true;
            this.productRepository
                .save(this.product, Shopware.Context.api)
                .then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('global.default.success'),
                        message: this.$tc('rcCustomerImageGallery.productTab.saved'),
                    });
                })
                .catch((error) => {
                    this.createNotificationError({
                        title: this.$tc('global.default.error'),
                        message: error && error.message ? error.message : '',
                    });
                })
                .finally(() => {
                    this.isSaving = false;
                });
        },
    },
});

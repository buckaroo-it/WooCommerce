export function getExpressProductParams(jQuery) {
    const product_id = jQuery('[name="add-to-cart"]').val();
    const variation_field = jQuery('[name="variation_id"]');
    const selected_variation_id = variation_field.length ? variation_field.val() : 0;
    const variation_id = selected_variation_id && selected_variation_id != 0 ? selected_variation_id : product_id;
    const attributes = {};

    jQuery('form.cart [name^="attribute_"]').each(function () {
        const field = jQuery(this);
        const name = field.attr('name');
        const value = field.val();

        if (name && value !== undefined && value !== null && value !== '') {
            attributes[name] = value;
        }
    });

    return {
        product_id,
        variation_id,
        quantity: jQuery('.cart .quantity input').val() || 1,
        attributes,
    };
}

export function getExpressRequestError(response, fallback) {
    return response && response.responseJSON && typeof response.responseJSON.message === 'string'
        ? response.responseJSON.message
        : fallback;
}

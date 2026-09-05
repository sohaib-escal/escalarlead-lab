/**
 * Turn a tree branch into the querystring the idea wizard understands, so a
 * recommendation always lands on a pre-filled creation flow.
 */
export function createUrlFor(path, categoriesBySlug) {
    const params = new URLSearchParams();

    (path ?? []).forEach((step) => {
        if (step.value_id === 'none') return;

        if (step.axis === 'product') params.set('product_id', step.value_id);
        else if (step.axis === 'channel') params.append('channels[]', step.value_id);
        else {
            const categoryId = categoriesBySlug?.[step.axis];
            if (categoryId) params.append(`parameters[${categoryId}][]`, step.value_id);
        }
    });

    return `/creatives/new?${params.toString()}`;
}

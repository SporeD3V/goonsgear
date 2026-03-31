// Handle combo variant selection (size + color buttons)
document.addEventListener('DOMContentLoaded', () => {
    const picker = document.querySelector('[data-product-variant-picker]');
    if (!picker) return;

    const comboMatrixData = picker.dataset.comboMatrix;
    if (!comboMatrixData) return;

    const comboMatrix = JSON.parse(comboMatrixData);
    let selectedSize = null;
    let selectedColor = null;

    // Size button handlers
    const sizeButtons = picker.querySelectorAll('[data-size-select]');
    sizeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            selectedSize = btn.dataset.sizeSelect;
            
            // Update button states
            sizeButtons.forEach(b => b.classList.remove('border-slate-800', 'bg-slate-800', 'text-white'));
            btn.classList.add('border-slate-800', 'bg-slate-800', 'text-white');
            
            updateVariant();
        });
    });

    // Color button handlers
    const colorButtons = picker.querySelectorAll('[data-color-select]');
    colorButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            selectedColor = btn.dataset.colorSelect;
            
            // Update button states
            colorButtons.forEach(b => b.classList.remove('border-slate-800'));
            btn.classList.add('border-slate-800');
            
            updateVariant();
            updateGalleryFilter(selectedColor);
        });
    });

    function updateVariant() {
        if (!selectedSize || !selectedColor) return;

        const key = `${selectedSize}|${selectedColor}`;
        const variant = comboMatrix[key];

        if (!variant) return;

        // Update variant panel
        document.querySelector('[data-variant-price]').textContent = parseFloat(variant.price).toFixed(2);
        document.querySelector('[data-variant-sku]').textContent = variant.sku;
        document.querySelector('[data-variant-qty]').textContent = variant.stock_quantity;
        
        const stockStatus = variant.stock_quantity > 0 ? 'In stock' : 
                          (variant.allow_backorder || variant.is_preorder ? 'Preorder' : 'Out of stock');
        document.querySelector('[data-variant-status]').textContent = stockStatus;

        // Update cart form
        const cartInput = document.querySelector('[data-cart-variant-input]');
        if (cartInput) {
            cartInput.value = variant.id;
        }

        // Update availability date if preorder
        const availabilityLine = document.querySelector('[data-variant-availability-line]');
        const availabilitySpan = document.querySelector('[data-variant-availability]');
        if (stockStatus === 'Preorder' && variant.availability) {
            availabilitySpan.textContent = variant.availability;
            availabilityLine.classList.remove('hidden');
        } else {
            availabilityLine.classList.add('hidden');
        }
    }

    function updateGalleryFilter(color) {
        const filterSelect = document.querySelector('[data-gallery-filter-id="media-variant-filter"]');
        if (!filterSelect) return;

        // Trigger gallery filter by color
        const event = new CustomEvent('variant-color-selected', { detail: { color } });
        document.dispatchEvent(event);
    }
});

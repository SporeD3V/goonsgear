import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
	const variantPickers = document.querySelectorAll('[data-product-variant-picker]');

	variantPickers.forEach((picker) => {
		const variantSelect = picker.querySelector('[data-variant-select]');
		const priceElement = picker.querySelector('[data-variant-price]');
		const skuElement = picker.querySelector('[data-variant-sku]');
		const statusElement = picker.querySelector('[data-variant-status]');
		const qtyElement = picker.querySelector('[data-variant-qty]');
		const galleryFilterId = picker.dataset.galleryFilterId || '';

		if (!variantSelect || !priceElement || !skuElement || !statusElement || !qtyElement) {
			return;
		}

		const syncVariantDetails = () => {
			const selectedOption = variantSelect.options[variantSelect.selectedIndex];

			if (!selectedOption) {
				return;
			}

			priceElement.textContent = selectedOption.dataset.variantPrice || '';
			skuElement.textContent = selectedOption.dataset.variantSku || '';
			statusElement.textContent = selectedOption.dataset.variantStatus || '';
			qtyElement.textContent = selectedOption.dataset.variantQty || '';

			if (galleryFilterId) {
				const galleryFilter = document.getElementById(galleryFilterId);

				if (galleryFilter && galleryFilter.value !== selectedOption.value) {
					galleryFilter.value = selectedOption.value;
					galleryFilter.dispatchEvent(new Event('change'));
				}
			}
		};

		if (galleryFilterId) {
			const galleryFilter = document.getElementById(galleryFilterId);

			if (galleryFilter) {
				galleryFilter.addEventListener('change', () => {
					if (galleryFilter.value === 'all') {
						return;
					}

					const matchingOption = Array.from(variantSelect.options)
						.find((option) => option.value === galleryFilter.value);

					if (matchingOption) {
						variantSelect.value = matchingOption.value;
						syncVariantDetails();
					}
				});
			}
		}

		variantSelect.addEventListener('change', syncVariantDetails);
		syncVariantDetails();
	});

	const galleries = document.querySelectorAll('[data-media-gallery]');

	galleries.forEach((gallery) => {
		const variantFilter = gallery.querySelector('[data-media-variant-filter]');
		const mainImage = gallery.querySelector('[data-media-main-image]');
		const mainVideo = gallery.querySelector('[data-media-main-video]');
		const thumbnails = gallery.querySelectorAll('[data-media-thumb]');

		if (!mainImage || thumbnails.length === 0) {
			return;
		}

		const setActiveThumbnail = (activeThumb) => {
			thumbnails.forEach((thumb) => {
				const isActive = thumb === activeThumb;
				thumb.classList.toggle('ring-2', isActive);
				thumb.classList.toggle('ring-blue-500', isActive);
				thumb.classList.toggle('border-blue-500', isActive);
				thumb.classList.toggle('border-slate-200', !isActive);
				thumb.setAttribute('aria-pressed', isActive ? 'true' : 'false');
			});
		};

		const setMainMedia = (thumb) => {
			const mediaType = thumb.dataset.mediaType;
			const mediaUrl = thumb.dataset.mediaUrl;
			const mediaAlt = thumb.dataset.mediaAlt || '';

			if (!mediaUrl) {
				return;
			}

			if (mediaType === 'video') {
				if (mainVideo) {
					mainVideo.src = mediaUrl;
					mainVideo.classList.remove('hidden');
					mainImage.classList.add('hidden');
				}
			} else {
				mainImage.src = mediaUrl;
				mainImage.alt = mediaAlt;
				mainImage.classList.remove('hidden');
				if (mainVideo) {
					mainVideo.pause();
					mainVideo.classList.add('hidden');
				}
			}

			setActiveThumbnail(thumb);
		};

		const applyVariantFilter = () => {
			const selectedVariantId = variantFilter ? variantFilter.value : 'all';
			let firstVisibleThumb = null;

			thumbnails.forEach((thumb) => {
				const thumbVariantId = thumb.dataset.mediaVariantId || '';
				const showThumb = selectedVariantId === 'all' || thumbVariantId === '' || thumbVariantId === selectedVariantId;

				thumb.classList.toggle('hidden', !showThumb);

				if (showThumb && firstVisibleThumb === null) {
					firstVisibleThumb = thumb;
				}
			});

			if (firstVisibleThumb) {
				setMainMedia(firstVisibleThumb);
			}
		};

		thumbnails.forEach((thumb) => {
			thumb.addEventListener('click', () => {
				if (!thumb.classList.contains('hidden')) {
					setMainMedia(thumb);
				}
			});

			thumb.addEventListener('mouseenter', () => {
				if (!thumb.classList.contains('hidden')) {
					setMainMedia(thumb);
				}
			});

			thumb.addEventListener('focus', () => {
				if (!thumb.classList.contains('hidden')) {
					setMainMedia(thumb);
				}
			});
		});

		if (variantFilter) {
			variantFilter.addEventListener('change', applyVariantFilter);
		}

		applyVariantFilter();
	});
});

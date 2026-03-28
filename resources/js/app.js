import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
	const galleries = document.querySelectorAll('[data-media-gallery]');

	galleries.forEach((gallery) => {
		const variantFilter = gallery.querySelector('[data-media-variant-filter]');
		const mainImage = gallery.querySelector('[data-media-main-image]');
		const mainVideo = gallery.querySelector('[data-media-main-video]');
		const thumbnails = gallery.querySelectorAll('[data-media-thumb]');

		if (!mainImage || !mainVideo || thumbnails.length === 0) {
			return;
		}

		const setMainMedia = (thumb) => {
			const mediaType = thumb.dataset.mediaType;
			const mediaUrl = thumb.dataset.mediaUrl;
			const mediaAlt = thumb.dataset.mediaAlt || '';

			if (!mediaUrl) {
				return;
			}

			if (mediaType === 'video') {
				mainVideo.src = mediaUrl;
				mainVideo.classList.remove('hidden');
				mainImage.classList.add('hidden');
			} else {
				mainImage.src = mediaUrl;
				mainImage.alt = mediaAlt;
				mainImage.classList.remove('hidden');
				mainVideo.pause();
				mainVideo.classList.add('hidden');
			}
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

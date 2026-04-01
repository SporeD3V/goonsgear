import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
	const variantPickers = document.querySelectorAll('[data-product-variant-picker]');

	variantPickers.forEach((picker) => {
		const variantSelect = picker.querySelector('[data-variant-select]');
		const requiresAttributeSelection = picker.dataset.requiresAttributeSelection === '1';
		const unselectedPrice = picker.dataset.unselectedPrice || '--';
		const attributeOrder = (picker.dataset.variantAttributeOrder || '')
			.split(',')
			.map((value) => value.trim())
			.filter((value) => value !== '');
		const attributeButtons = Array.from(picker.querySelectorAll('[data-variant-attribute]'));
		const priceElement = picker.querySelector('[data-variant-price]');
		const skuElement = picker.querySelector('[data-variant-sku]');
		const statusElement = picker.querySelector('[data-variant-status]');
		const qtyElement = picker.querySelector('[data-variant-qty]');
		const availabilityElement = picker.querySelector('[data-variant-availability]');
		const availabilityLine = picker.querySelector('[data-variant-availability-line]');
		const cartVariantInput = picker.querySelector('[data-cart-variant-input]');
		const quantityInput = picker.querySelector('[data-cart-quantity-input]');
		const stockAlertForm = picker.querySelector('[data-stock-alert-form]');
		const stockAlertVariantInput = picker.querySelector('[data-stock-alert-variant-input]');
		const stockAlertSubscribedLabel = picker.querySelector('[data-stock-alert-subscribed-label]');
		const stockAlertLoginNote = picker.querySelector('[data-stock-alert-login-note]');
		const addToCartButton = picker.querySelector('[data-add-to-cart-button]');

		if (!variantSelect || !priceElement || !skuElement || !statusElement || !qtyElement) {
			return;
		}

		const parseVariantAttributes = (option) => {
			if (!option) {
				return {};
			}

			try {
				return JSON.parse(option.dataset.variantAttributes || '{}');
			} catch (_error) {
				return {};
			}
		};

		const resolveVariantSku = (option) => {
			if (!option) {
				return '--';
			}

			const datasetSku = (option.dataset.variantSku || '').trim();
			if (datasetSku !== '') {
				return datasetSku;
			}

			const attributeSku = (option.getAttribute('data-variant-sku') || '').trim();
			if (attributeSku !== '') {
				return attributeSku;
			}

			const optionText = (option.textContent || '').trim();
			const textMatch = optionText.match(/\(([^)]+)\)\s*$/);

			return textMatch && textMatch[1] ? textMatch[1].trim() : '--';
		};

		const variantOptions = Array.from(variantSelect.options).map((option) => ({
			option,
			attributes: parseVariantAttributes(option),
		})).filter(({ option }) => option.value !== '');

		let selectedAttributes = requiresAttributeSelection
			? {}
			: parseVariantAttributes(variantSelect.options[variantSelect.selectedIndex]);

		const dispatchColorSelection = () => {
			document.dispatchEvent(new CustomEvent('variant-color-selected', {
				detail: {
					color: selectedAttributes.color || '',
				},
			}));
		};

		const optionMatchesAttributes = (optionAttributes, attributes) => Object.entries(attributes)
			.every(([attributeKey, attributeValue]) => optionAttributes[attributeKey] === attributeValue);

		const resolveMatchingOption = (attributes) => {
			if (variantOptions.length === 0) {
				return null;
			}

			const orderedAttributes = attributeOrder.reduce((carry, key) => {
				if (attributes[key]) {
					carry[key] = attributes[key];
				}

				return carry;
			}, {});

			const exactMatch = variantOptions.find(({ attributes: optionAttributes }) => optionMatchesAttributes(optionAttributes, orderedAttributes));

			if (exactMatch) {
				return exactMatch.option;
			}

			let fallbackMatch = null;
			for (const attributeKey of attributeOrder) {
				if (!orderedAttributes[attributeKey]) {
					continue;
				}

				const relaxedAttributes = { ...orderedAttributes };
				delete relaxedAttributes[attributeKey];

				fallbackMatch = variantOptions.find(({ attributes: optionAttributes }) => optionMatchesAttributes(optionAttributes, relaxedAttributes));
				if (fallbackMatch) {
					selectedAttributes = relaxedAttributes;
					return fallbackMatch.option;
				}
			}

			return variantOptions[0].option;
		};

		const updateAttributeButtons = () => {
			if (attributeButtons.length === 0) {
				return;
			}

			attributeButtons.forEach((button) => {
				const attributeKey = button.dataset.variantAttribute || '';
				const attributeValue = button.dataset.variantAttributeValue || '';
				const candidateSelection = {
					...selectedAttributes,
					[attributeKey]: attributeValue,
				};

				const hasMatchingVariant = variantOptions.some(({ attributes }) => optionMatchesAttributes(attributes, candidateSelection));
				const isSelected = selectedAttributes[attributeKey] === attributeValue;

				button.disabled = !hasMatchingVariant;
				button.classList.toggle('border-slate-900', isSelected);
				button.classList.toggle('bg-slate-900', isSelected);
				button.classList.toggle('text-white', isSelected);
				button.classList.toggle('border-slate-300', !isSelected);
				button.classList.toggle('bg-white', !isSelected);
				button.classList.toggle('text-slate-700', !isSelected);
				button.classList.toggle('opacity-40', !hasMatchingVariant);
			});
		};

		const clearVariantDetails = () => {
			priceElement.textContent = unselectedPrice;
			skuElement.textContent = '--';
			statusElement.textContent = 'Select options';
			qtyElement.textContent = '--';

			if (availabilityElement) {
				availabilityElement.textContent = '';
			}

			if (availabilityLine) {
				availabilityLine.classList.add('hidden');
			}

			if (cartVariantInput) {
				cartVariantInput.value = '';
			}

			if (quantityInput) {
				quantityInput.removeAttribute('max');
				quantityInput.value = '1';
			}

			if (addToCartButton) {
				addToCartButton.disabled = true;
			}

			if (stockAlertForm) {
				stockAlertForm.classList.add('hidden');
			}

			if (stockAlertLoginNote) {
				stockAlertLoginNote.classList.add('hidden');
			}

			dispatchColorSelection();
			updateAttributeButtons();
		};

		const syncVariantDetails = () => {
			const selectedOption = variantSelect.options[variantSelect.selectedIndex];

			if (!selectedOption || selectedOption.value === '') {
				clearVariantDetails();
				return;
			}

			selectedAttributes = parseVariantAttributes(selectedOption);

			priceElement.textContent = selectedOption.dataset.variantPrice || '';
			skuElement.textContent = resolveVariantSku(selectedOption);
			statusElement.textContent = selectedOption.dataset.variantStatus || '';
			qtyElement.textContent = selectedOption.dataset.variantQty || '';

			if (availabilityElement && availabilityLine) {
				const availability = selectedOption.dataset.variantAvailability || '';
				availabilityElement.textContent = availability;
				availabilityLine.classList.toggle('hidden', !(selectedOption.dataset.variantStatus === 'Preorder' && availability));
			}

			if (cartVariantInput) {
				cartVariantInput.value = selectedOption.value;
			}

			const selectedQuantity = Number.parseInt(selectedOption.dataset.variantQty || '0', 10);
			const tracksInventory = selectedOption.dataset.variantTrackInventory === '1';
			const allowsBackorder = selectedOption.dataset.variantAllowBackorder === '1';
			const isPreorder = selectedOption.dataset.variantIsPreorder === '1';

			if (addToCartButton) {
				addToCartButton.disabled = selectedOption.dataset.variantOutOfStock === '1';
			}

			if (quantityInput) {
				const hasFiniteStockLimit = tracksInventory && !allowsBackorder && !isPreorder && selectedQuantity > 0;

				if (hasFiniteStockLimit) {
					quantityInput.max = String(selectedQuantity);

					const currentQuantity = Number.parseInt(quantityInput.value || '1', 10);
					if (Number.isNaN(currentQuantity) || currentQuantity < 1) {
						quantityInput.value = '1';
					} else if (currentQuantity > selectedQuantity) {
						quantityInput.value = String(selectedQuantity);
					}
				} else {
					quantityInput.removeAttribute('max');
					if (!quantityInput.value || Number.parseInt(quantityInput.value, 10) < 1) {
						quantityInput.value = '1';
					}
				}
			}

			const isOutOfStock = selectedOption.dataset.variantOutOfStock === '1';
			const isSubscribed = selectedOption.dataset.variantStockAlertSubscribed === '1';

			if (stockAlertForm) {
				stockAlertForm.classList.toggle('hidden', !isOutOfStock);

				if (stockAlertVariantInput) {
					stockAlertVariantInput.value = selectedOption.value;
				}

				if (stockAlertSubscribedLabel) {
					stockAlertSubscribedLabel.classList.toggle('hidden', !isSubscribed);
				}
			}

			if (stockAlertLoginNote) {
				stockAlertLoginNote.classList.toggle('hidden', !isOutOfStock);
			}

			dispatchColorSelection();
			updateAttributeButtons();
		};

		variantSelect.addEventListener('change', syncVariantDetails);

		attributeButtons.forEach((button) => {
			button.addEventListener('click', () => {
				const attributeKey = button.dataset.variantAttribute || '';
				const attributeValue = button.dataset.variantAttributeValue || '';

				if (attributeKey === '' || attributeValue === '') {
					return;
				}

				selectedAttributes = {
					...selectedAttributes,
					[attributeKey]: attributeValue,
				};

				if (requiresAttributeSelection && attributeOrder.length > 1) {
					const selectedCount = attributeOrder.filter((key) => selectedAttributes[key]).length;
					if (selectedCount < attributeOrder.length) {
						clearVariantDetails();
						return;
					}
				}

				const matchingOption = resolveMatchingOption(selectedAttributes);
				if (matchingOption) {
					variantSelect.value = matchingOption.value;
				}

				syncVariantDetails();
			});
		});

		if (requiresAttributeSelection) {
			variantSelect.selectedIndex = 0;
			clearVariantDetails();
		} else {
			syncVariantDetails();
		}
	});

	const galleries = document.querySelectorAll('[data-media-gallery]');

	galleries.forEach((gallery) => {
		const mainImage = gallery.querySelector('[data-media-main-image]');
		const mainVideo = gallery.querySelector('[data-media-main-video]');
		const thumbnails = gallery.querySelectorAll('[data-media-thumb]');
		const lightboxLaunch = gallery.querySelector('[data-lightbox-launch]');
		const lightbox = gallery.querySelector('[data-lightbox]');
		const lightboxStage = gallery.querySelector('[data-lightbox-stage]');
		const lightboxImage = gallery.querySelector('[data-lightbox-image]');
		const lightboxVideo = gallery.querySelector('[data-lightbox-video]');
		const lightboxCaption = gallery.querySelector('[data-lightbox-caption]');
		const lightboxClose = gallery.querySelector('[data-lightbox-close]');
		const lightboxPrev = gallery.querySelector('[data-lightbox-prev]');
		const lightboxNext = gallery.querySelector('[data-lightbox-next]');
		const lightboxZoomIn = gallery.querySelector('[data-lightbox-zoom-in]');
		const lightboxZoomOut = gallery.querySelector('[data-lightbox-zoom-out]');
		const lightboxZoomReset = gallery.querySelector('[data-lightbox-zoom-reset]');

		if (!mainImage || thumbnails.length === 0) {
			return;
		}

		let activeThumb = null;
		let lastAppliedColor = '';
		let touchStartX = 0;
		let touchStartY = 0;
		let zoomScale = 1;
		let panX = 0;
		let panY = 0;
		let isPanning = false;
		let panStartX = 0;
		let panStartY = 0;

		const getVisibleThumbnails = () => Array.from(thumbnails).filter((thumb) => !thumb.classList.contains('hidden'));

		thumbnails.forEach((thumb, index) => {
			thumb.dataset.mediaOriginalIndex = String(index);
		});

		const isLightboxOpen = () => Boolean(lightbox) && !lightbox.classList.contains('hidden');

		const applyLightboxTransform = () => {
			if (!lightboxImage || lightboxImage.classList.contains('hidden')) {
				return;
			}

			lightboxImage.style.transform = `translate(${panX}px, ${panY}px) scale(${zoomScale})`;
			lightboxImage.style.transformOrigin = 'center center';
			lightboxImage.style.cursor = zoomScale > 1 ? (isPanning ? 'grabbing' : 'grab') : 'zoom-in';

			if (lightboxZoomReset) {
				lightboxZoomReset.textContent = `${zoomScale.toFixed(1)}x`;
			}
		};

		const resetZoom = () => {
			zoomScale = 1;
			panX = 0;
			panY = 0;
			isPanning = false;
			applyLightboxTransform();
		};

		const zoomBy = (delta) => {
			zoomScale = Math.min(4, Math.max(1, zoomScale + delta));

			if (zoomScale === 1) {
				panX = 0;
				panY = 0;
			}

			applyLightboxTransform();
		};

		const setActiveThumbnail = (selectedThumb) => {
			thumbnails.forEach((thumb) => {
				const isActive = thumb === selectedThumb;
				thumb.classList.toggle('ring-2', isActive);
				thumb.classList.toggle('ring-blue-500', isActive);
				thumb.classList.toggle('border-blue-500', isActive);
				thumb.classList.toggle('border-slate-200', !isActive);
				thumb.setAttribute('aria-pressed', isActive ? 'true' : 'false');
			});

			activeThumb = selectedThumb;
		};

		const setLightboxMedia = (thumb) => {
			if (!lightbox || !lightboxImage || !thumb) {
				return;
			}

			const mediaType = thumb.dataset.mediaType;
			const mediaUrl = thumb.dataset.mediaUrl;
			const mediaZoomUrl = thumb.dataset.mediaZoomUrl || mediaUrl;
			const mediaAlt = thumb.dataset.mediaAlt || '';

			if (!mediaUrl) {
				return;
			}

			if (mediaType === 'video') {
				if (lightboxVideo) {
					lightboxVideo.src = mediaUrl;
					lightboxVideo.classList.remove('hidden');
					lightboxVideo.classList.remove('lightbox-media-animate');
					void lightboxVideo.offsetWidth;
					lightboxVideo.classList.add('lightbox-media-animate');
				}

				lightboxImage.classList.add('hidden');
				if (lightboxCaption) {
					lightboxCaption.textContent = `${mediaAlt || 'Video'} (video)`;
				}

				resetZoom();
			} else {
				lightboxImage.src = mediaZoomUrl;
				lightboxImage.alt = mediaAlt;
				lightboxImage.classList.remove('hidden');
				lightboxImage.classList.remove('lightbox-media-animate');
				void lightboxImage.offsetWidth;
				lightboxImage.classList.add('lightbox-media-animate');

				if (lightboxVideo) {
					lightboxVideo.pause();
					lightboxVideo.classList.add('hidden');
				}

				if (lightboxCaption) {
					lightboxCaption.textContent = mediaAlt || 'Image';
				}

				resetZoom();
			}
		};

		const navigateLightbox = (direction) => {
			const visibleThumbnails = getVisibleThumbnails();

			if (visibleThumbnails.length <= 1) {
				return;
			}

			const currentThumb = activeThumb && visibleThumbnails.includes(activeThumb)
				? activeThumb
				: visibleThumbnails[0];
			const currentIndex = visibleThumbnails.indexOf(currentThumb);
			const nextIndex = (currentIndex + direction + visibleThumbnails.length) % visibleThumbnails.length;
			const nextThumb = visibleThumbnails[nextIndex];

			setMainMedia(nextThumb);
			setLightboxMedia(nextThumb);
		};

		const openLightbox = (thumb = activeThumb) => {
			if (!lightbox || !thumb) {
				return;
			}

			setMainMedia(thumb);
			setLightboxMedia(thumb);
			lightbox.classList.remove('hidden');
			document.body.classList.add('overflow-hidden');
			lightboxClose?.focus();
		};

		const closeLightbox = () => {
			if (!lightbox || !isLightboxOpen()) {
				return;
			}

			lightbox.classList.add('hidden');
			document.body.classList.remove('overflow-hidden');

			if (lightboxVideo) {
				lightboxVideo.pause();
				lightboxVideo.removeAttribute('src');
				lightboxVideo.load();
			}

			resetZoom();
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
					mainVideo.removeAttribute('src');
					mainVideo.load();
					mainVideo.classList.add('hidden');
				}
			}

			setActiveThumbnail(thumb);
		};

		const applyColorFilter = (selectedColor) => {
			const normalizedSelectedColor = (selectedColor || '').trim().toLowerCase();
			const matchingColorThumbs = [];
			const sharedThumbs = [];
			const visibleThumbs = [];

			thumbnails.forEach((thumb) => {
				const thumbVariantId = (thumb.dataset.mediaVariantId || '').trim();
				const thumbColor = (thumb.dataset.mediaVariantColor || '').trim().toLowerCase();
				const showThumb = normalizedSelectedColor === ''
					? true
					: (thumbVariantId === '' || thumbColor === '' || thumbColor === normalizedSelectedColor);

				thumb.classList.toggle('hidden', !showThumb);

				if (!showThumb) {
					thumb.style.order = '999';
					return;
				}

				visibleThumbs.push(thumb);

				if (normalizedSelectedColor !== '' && thumbColor === normalizedSelectedColor) {
					matchingColorThumbs.push(thumb);
				} else {
					sharedThumbs.push(thumb);
				}
			});

			const orderedVisibleThumbs = normalizedSelectedColor === ''
				? visibleThumbs.sort((left, right) => Number(left.dataset.mediaOriginalIndex || 0) - Number(right.dataset.mediaOriginalIndex || 0))
				: [
					...matchingColorThumbs.sort((left, right) => Number(left.dataset.mediaOriginalIndex || 0) - Number(right.dataset.mediaOriginalIndex || 0)),
					...sharedThumbs.sort((left, right) => Number(left.dataset.mediaOriginalIndex || 0) - Number(right.dataset.mediaOriginalIndex || 0)),
				];

			orderedVisibleThumbs.forEach((thumb, index) => {
				thumb.style.order = String(index);
			});

			const activeIsVisible = activeThumb && orderedVisibleThumbs.includes(activeThumb);
			const colorChanged = normalizedSelectedColor !== lastAppliedColor;
			let preferredThumb = null;

			if (normalizedSelectedColor !== '' && colorChanged && matchingColorThumbs.length > 0) {
				preferredThumb = matchingColorThumbs[0];
			} else if (activeIsVisible) {
				preferredThumb = activeThumb;
			} else {
				preferredThumb = orderedVisibleThumbs[0] || null;
			}

			if (preferredThumb && preferredThumb !== activeThumb) {
				setMainMedia(preferredThumb);
			}

			lastAppliedColor = normalizedSelectedColor;
		};

		document.addEventListener('variant-color-selected', (event) => {
			applyColorFilter(event.detail?.color || '');
		});

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

			thumb.addEventListener('dblclick', () => {
				if (!thumb.classList.contains('hidden')) {
					openLightbox(thumb);
				}
			});
		});

		if (lightboxLaunch) {
			lightboxLaunch.addEventListener('click', () => {
				openLightbox(activeThumb || getVisibleThumbnails()[0] || null);
			});
		}

		mainImage.addEventListener('click', () => {
			openLightbox(activeThumb || getVisibleThumbnails()[0] || null);
		});

		mainVideo?.addEventListener('click', () => {
			openLightbox(activeThumb || getVisibleThumbnails()[0] || null);
		});

		mainImage.addEventListener('keydown', (event) => {
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				openLightbox(activeThumb || getVisibleThumbnails()[0] || null);
			}
		});

		mainVideo?.addEventListener('keydown', (event) => {
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				openLightbox(activeThumb || getVisibleThumbnails()[0] || null);
			}
		});

		lightboxClose?.addEventListener('click', closeLightbox);
		lightboxPrev?.addEventListener('click', () => navigateLightbox(-1));
		lightboxNext?.addEventListener('click', () => navigateLightbox(1));
		lightboxZoomIn?.addEventListener('click', () => zoomBy(0.25));
		lightboxZoomOut?.addEventListener('click', () => zoomBy(-0.25));
		lightboxZoomReset?.addEventListener('click', () => resetZoom());

		lightboxImage?.addEventListener('dblclick', () => {
			if (zoomScale > 1) {
				resetZoom();
				return;
			}

			zoomScale = 2;
			applyLightboxTransform();
		});

		lightboxImage?.addEventListener('mousedown', (event) => {
			if (zoomScale <= 1) {
				return;
			}

			event.preventDefault();
			isPanning = true;
			panStartX = event.clientX - panX;
			panStartY = event.clientY - panY;
			applyLightboxTransform();
		});

		document.addEventListener('mousemove', (event) => {
			if (!isPanning || zoomScale <= 1 || !isLightboxOpen()) {
				return;
			}

			panX = event.clientX - panStartX;
			panY = event.clientY - panStartY;
			applyLightboxTransform();
		});

		document.addEventListener('mouseup', () => {
			if (!isPanning) {
				return;
			}

			isPanning = false;
			applyLightboxTransform();
		});

		lightboxStage?.addEventListener('wheel', (event) => {
			if (!isLightboxOpen() || !lightboxImage || lightboxImage.classList.contains('hidden')) {
				return;
			}

			event.preventDefault();
			zoomBy(event.deltaY < 0 ? 0.2 : -0.2);
		}, { passive: false });

		lightbox?.addEventListener('click', (event) => {
			if (event.target === lightbox) {
				closeLightbox();
			}
		});

		lightboxStage?.addEventListener('touchstart', (event) => {
			const touch = event.changedTouches[0];
			touchStartX = touch.clientX;
			touchStartY = touch.clientY;
		}, { passive: true });

		lightboxStage?.addEventListener('touchend', (event) => {
			const touch = event.changedTouches[0];
			const deltaX = touch.clientX - touchStartX;
			const deltaY = touch.clientY - touchStartY;

			if (Math.abs(deltaX) < 50 || Math.abs(deltaX) < Math.abs(deltaY)) {
				return;
			}

			if (deltaX > 0) {
				navigateLightbox(-1);
			} else {
				navigateLightbox(1);
			}
		}, { passive: true });

		document.addEventListener('keydown', (event) => {
			if (!isLightboxOpen()) {
				return;
			}

			if (event.key === 'Escape') {
				event.preventDefault();
				closeLightbox();
				return;
			}

			if (event.key === 'ArrowLeft') {
				event.preventDefault();
				navigateLightbox(-1);
				return;
			}

			if (event.key === 'ArrowRight') {
				event.preventDefault();
				navigateLightbox(1);
				return;
			}

			if (event.key === '+' || event.key === '=') {
				event.preventDefault();
				zoomBy(0.25);
				return;
			}

			if (event.key === '-' || event.key === '_') {
				event.preventDefault();
				zoomBy(-0.25);
				return;
			}

			if (event.key === '0') {
				event.preventDefault();
				resetZoom();
			}
		});

		applyColorFilter('');
	});

	// Live search functionality
	const searchInput = document.getElementById('search-input');
	const searchResults = document.getElementById('search-results');

	if (searchInput && searchResults) {
		const searchEndpoint = searchInput.dataset.searchEndpoint;
		let searchTimeout;

		const escapeHtml = (text) => {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;',
			};
			return text.replace(/[&<>"']/g, (m) => map[m]);
		};

		const performSearch = async (query) => {
			if (query.length < 2) {
				searchResults.classList.add('hidden');
				return;
			}

			try {
				const response = await fetch(`${searchEndpoint}?q=${encodeURIComponent(query)}`);
				const data = await response.json();

				if (data.results && data.results.length > 0) {
					searchResults.innerHTML = data.results
						.map(
							(result) => {
								const hasPrice = result.price !== null && result.price !== undefined;
								const parsedPrice = hasPrice ? Number(result.price) : null;
								const priceMarkup = hasPrice && Number.isFinite(parsedPrice)
									? `<div class="text-xs font-medium text-slate-800">$${parsedPrice.toFixed(2)}</div>`
									: '';

								return `
							<a href="${result.url}" class="group flex gap-3 border-b border-slate-100 p-2 text-sm hover:bg-slate-50">
								${result.image ? `
									<div class="relative h-10 w-10 overflow-hidden rounded">
										<img src="${result.image}" alt="${escapeHtml(result.name)}" class="h-10 w-10 object-cover transition-opacity duration-200 ${result.secondary_image ? 'group-hover:opacity-0' : ''}">
										${result.secondary_image ? `<img src="${result.secondary_image}" alt="${escapeHtml(result.name)}" class="pointer-events-none absolute inset-0 h-10 w-10 object-cover opacity-0 transition-opacity duration-200 group-hover:opacity-100">` : ''}
									</div>
								` : '<div class="h-10 w-10 rounded bg-slate-100"></div>'}
							<div class="flex-1">
								<div class="font-medium text-slate-900">${escapeHtml(result.name)}</div>
								<div class="text-xs text-slate-600">${result.category ? escapeHtml(result.category) : 'Uncategorized'}</div>
								${priceMarkup}
							</div>
						</a>
					`;
							}
						)
						.join('');
					searchResults.classList.remove('hidden');
				} else {
					searchResults.innerHTML = '<div class="p-3 text-center text-xs text-slate-600">No products found</div>';
					searchResults.classList.remove('hidden');
				}
			} catch (error) {
				console.error('Search error:', error);
				searchResults.classList.add('hidden');
			}
		};

		searchInput.addEventListener('input', (e) => {
			clearTimeout(searchTimeout);
			const query = e.target.value.trim();

			searchTimeout = setTimeout(() => {
				performSearch(query);
			}, 300);
		});

		// Hide search results when clicking outside
		document.addEventListener('click', (e) => {
			if (!e.target.closest('#search-input') && !e.target.closest('#search-results')) {
				searchResults.classList.add('hidden');
			}
		});

		// Show results when focusing search input if there's a value
		searchInput.addEventListener('focus', () => {
			if (searchInput.value.trim().length >= 2 && !searchResults.classList.contains('hidden')) {
				searchResults.classList.remove('hidden');
			}
		});
	}

	const recaptchaProtectedForms = document.querySelectorAll('form[data-recaptcha-protected="1"]');

	recaptchaProtectedForms.forEach((form) => {
		if (!form || form.action.endsWith('/checkout')) {
			return;
		}

		const siteKey = form.dataset.recaptchaSiteKey || '';
		const action = form.dataset.recaptchaAction || 'form_submit';
		const errorId = form.dataset.recaptchaErrorId || '';
		const tokenInput = form.querySelector('input[name="recaptcha_token"]');
		const errorContainer = errorId ? document.getElementById(errorId) : null;
		let submitTokenPending = false;

		const setError = (message) => {
			if (!errorContainer) {
				return;
			}

			errorContainer.textContent = message;
			errorContainer.classList.remove('hidden');
		};

		const clearError = () => {
			if (!errorContainer) {
				return;
			}

			errorContainer.textContent = '';
			errorContainer.classList.add('hidden');
		};

		form.addEventListener('submit', async (event) => {
			if (submitTokenPending) {
				submitTokenPending = false;
				return;
			}

			event.preventDefault();
			clearError();

			if (!window.grecaptcha || !siteKey) {
				setError('Security verification is currently unavailable. Please try again.');
				return;
			}

			try {
				const token = await window.grecaptcha.execute(siteKey, { action });

				if (!token) {
					setError('Security verification failed. Please try again.');
					return;
				}

				if (tokenInput) {
					tokenInput.value = token;
				}

				submitTokenPending = true;
				form.requestSubmit();
			} catch {
				setError('Security verification failed. Please try again.');
			}
		});
	});

	// Checkout country/state/city selectors
	const countrySelect = document.getElementById('country');
	const stateWrapper = document.getElementById('state-wrapper');
	const cityWrapper = document.getElementById('city-wrapper');
	const locationEndpoints = document.getElementById('location-endpoints');

	if (countrySelect && stateWrapper && cityWrapper && locationEndpoints) {
		const statesUrl = locationEndpoints.dataset.statesUrl;
		const citiesUrl = locationEndpoints.dataset.citiesUrl;
		const initialState = stateWrapper.dataset.initialState || '';
		const initialCity = cityWrapper.dataset.initialCity || '';
		const statesCache = new Map();
		const citiesCache = new Map();

		const renderStateSelect = (states, selectedState) => {
			const select = document.createElement('select');
			select.id = 'state';
			select.name = 'state';
			select.className = 'w-full rounded border border-slate-300 px-3 py-2 text-sm';

			const placeholder = document.createElement('option');
			placeholder.value = '';
			placeholder.textContent = '-- Select state / region --';
			select.appendChild(placeholder);

			states.forEach((state) => {
				const option = document.createElement('option');
				option.value = state.code;
				option.textContent = state.name;
				if (state.code === selectedState) {
					option.selected = true;
				}
				select.appendChild(option);
			});

			stateWrapper.innerHTML = '';
			stateWrapper.appendChild(select);
		};

		const renderStateInput = (value) => {
			const input = document.createElement('input');
			input.id = 'state';
			input.name = 'state';
			input.type = 'text';
			input.value = value || '';
			input.className = 'w-full rounded border border-slate-300 px-3 py-2 text-sm';
			stateWrapper.innerHTML = '';
			stateWrapper.appendChild(input);
		};

		const renderCitySelect = (cities, selectedCity) => {
			const select = document.createElement('select');
			select.id = 'city';
			select.name = 'city';
			select.required = true;
			select.className = 'w-full rounded border border-slate-300 px-3 py-2 text-sm';

			const placeholder = document.createElement('option');
			placeholder.value = '';
			placeholder.textContent = '-- Select city --';
			select.appendChild(placeholder);

			cities.forEach((city) => {
				const option = document.createElement('option');
				option.value = city;
				option.textContent = city;
				if (city === selectedCity) {
					option.selected = true;
				}
				select.appendChild(option);
			});

			cityWrapper.innerHTML = '';
			cityWrapper.appendChild(select);
		};

		const renderCityInput = (value) => {
			const input = document.createElement('input');
			input.id = 'city';
			input.name = 'city';
			input.type = 'text';
			input.required = true;
			input.value = value || '';
			input.className = 'w-full rounded border border-slate-300 px-3 py-2 text-sm';
			cityWrapper.innerHTML = '';
			cityWrapper.appendChild(input);
		};

		const renderCitySelectPlaceholder = (placeholderText = '-- Select city --') => {
			renderCitySelect([], '');
			const citySelect = document.getElementById('city');

			if (citySelect && citySelect.options.length > 0) {
				citySelect.options[0].textContent = placeholderText;
			}
		};

		const loadCities = async (countryCode, stateCode, selectedCity) => {
			if (!countryCode || !citiesUrl) {
				renderCityInput(selectedCity);
				return;
			}

			const cacheKey = `${countryCode}|${stateCode || ''}`;

			if (citiesCache.has(cacheKey)) {
				const cities = citiesCache.get(cacheKey);
				renderCitySelect(cities, selectedCity);
				return;
			}

			cityWrapper.innerHTML = '<p class="py-2 text-xs text-slate-500">Loading cities...</p>';

			try {
				const params = new URLSearchParams({ country: countryCode });

				if (stateCode) {
					params.set('state', stateCode);
				}

				const response = await fetch(`${citiesUrl}?${params.toString()}`);

				if (!response.ok) {
					throw new Error('Failed to load cities');
				}

				const payload = await response.json();
				const cities = Array.isArray(payload.data) ? payload.data : [];

				if (cities.length > 0) {
					citiesCache.set(cacheKey, cities);
					renderCitySelect(cities, selectedCity);
				} else {
					renderCityInput(selectedCity);
				}
			} catch {
				renderCityInput(selectedCity);
			}
		};

		const loadStates = async (countryCode, selectedState, selectedCity) => {
			if (!countryCode || !statesUrl) {
				renderStateInput(selectedState);
				renderCityInput(selectedCity);
				return;
			}

			if (statesCache.has(countryCode)) {
				const states = statesCache.get(countryCode);

				if (states.length > 0) {
					renderStateSelect(states, selectedState);

					const stateSelect = document.getElementById('state');
					const activeState = stateSelect?.value || '';

					if (activeState) {
						await loadCities(countryCode, activeState, selectedCity);
					} else {
						renderCitySelectPlaceholder('-- Select state / region first --');
					}

					stateSelect?.addEventListener('change', () => {
						const currentCity = document.getElementById('city')?.value || '';
						const currentState = stateSelect.value;

						if (!currentState) {
							renderCitySelectPlaceholder('-- Select state / region first --');
							return;
						}

						loadCities(countrySelect.value, currentState, currentCity);
					});

					return;
				}

				renderStateInput(selectedState);
				await loadCities(countryCode, '', selectedCity);
				return;
			}

			stateWrapper.innerHTML = '<p class="py-2 text-xs text-slate-500">Loading states...</p>';

			try {
				const params = new URLSearchParams({ country: countryCode });
				const response = await fetch(`${statesUrl}?${params.toString()}`);

				if (!response.ok) {
					throw new Error('Failed to load states');
				}

				const payload = await response.json();
				const states = Array.isArray(payload.data) ? payload.data : [];
				statesCache.set(countryCode, states);

				if (states.length > 0) {
					renderStateSelect(states, selectedState);

					const stateSelect = document.getElementById('state');
					const activeState = stateSelect?.value || '';

					if (activeState) {
						await loadCities(countryCode, activeState, selectedCity);
					} else {
						renderCitySelectPlaceholder('-- Select state / region first --');
					}

					stateSelect?.addEventListener('change', () => {
						const currentCity = document.getElementById('city')?.value || '';
						const currentState = stateSelect.value;

						if (!currentState) {
							renderCitySelectPlaceholder('-- Select state / region first --');
							return;
						}

						loadCities(countrySelect.value, currentState, currentCity);
					});
				} else {
					renderStateInput(selectedState);
					await loadCities(countryCode, '', selectedCity);
				}
			} catch {
				renderStateInput(selectedState);
				await loadCities(countryCode, '', selectedCity);
			}
		};

		countrySelect.addEventListener('change', () => {
			loadStates(countrySelect.value, '', '');
		});

		if (countrySelect.value) {
			loadStates(countrySelect.value, initialState, initialCity);
		} else {
			renderStateInput(initialState);
			renderCityInput(initialCity);
		}
	}

	const paypalContainer = document.getElementById('paypal-checkout');
	const checkoutForm = document.querySelector('form[action$="/checkout"]');
	const paypalErrors = document.getElementById('paypal-errors');
	const recaptchaErrors = document.getElementById('recaptcha-errors');

	if (checkoutForm) {
		const recaptchaEnabled = checkoutForm.dataset.recaptchaEnabled === '1';
		const recaptchaSiteKey = checkoutForm.dataset.recaptchaSiteKey || '';
		const recaptchaTokenInput = checkoutForm.querySelector('input[name="recaptcha_token"]');
		let submitTokenPending = false;

		const setRecaptchaError = (message) => {
			if (!recaptchaErrors) {
				return;
			}

			recaptchaErrors.textContent = message;
			recaptchaErrors.classList.remove('hidden');
		};

		const clearRecaptchaError = () => {
			if (!recaptchaErrors) {
				return;
			}

			recaptchaErrors.classList.add('hidden');
			recaptchaErrors.textContent = '';
		};

		const resolveRecaptchaToken = async () => {
			if (!recaptchaEnabled) {
				return '';
			}

			if (!window.grecaptcha || !recaptchaSiteKey) {
				throw new Error('Security verification is currently unavailable.');
			}

			const token = await window.grecaptcha.execute(recaptchaSiteKey, { action: 'checkout' });

			if (!token) {
				throw new Error('Security verification failed. Please try again.');
			}

			if (recaptchaTokenInput) {
				recaptchaTokenInput.value = token;
			}

			return token;
		};

		checkoutForm.addEventListener('submit', async (event) => {
			if (!recaptchaEnabled) {
				return;
			}

			if (submitTokenPending) {
				submitTokenPending = false;
				return;
			}

			event.preventDefault();
			clearRecaptchaError();

			try {
				await resolveRecaptchaToken();
				submitTokenPending = true;
				checkoutForm.requestSubmit();
			} catch (error) {
				setRecaptchaError(error.message || 'Security verification failed. Please try again.');
			}
		});

		if (paypalContainer && window.paypal) {
			const createOrderUrl = paypalContainer.dataset.createOrderUrl;
			const captureOrderUrl = paypalContainer.dataset.captureOrderUrl;
			const csrfToken = paypalContainer.dataset.csrfToken;

			const showPayPalError = (message) => {
				if (!paypalErrors) {
					return;
				}

				paypalErrors.textContent = message;
				paypalErrors.classList.remove('hidden');
			};

			const clearPayPalError = () => {
				if (!paypalErrors) {
					return;
				}

				paypalErrors.classList.add('hidden');
				paypalErrors.textContent = '';
			};

			const collectCheckoutPayload = () => {
				const formData = new FormData(checkoutForm);
				const payload = {};

				formData.forEach((value, key) => {
					payload[key] = value;
				});

				return payload;
			};

			window.paypal.Buttons({
				createOrder: async () => {
					clearPayPalError();
					clearRecaptchaError();

					let recaptchaToken = '';

					if (recaptchaEnabled) {
						try {
							recaptchaToken = await resolveRecaptchaToken();
						} catch (error) {
							const message = error.message || 'Security verification failed. Please try again.';
							showPayPalError(message);
							setRecaptchaError(message);
							throw new Error(message);
						}
					}

					const checkoutPayload = collectCheckoutPayload();

					if (recaptchaEnabled) {
						checkoutPayload.recaptcha_token = recaptchaToken;
					}

					const response = await fetch(createOrderUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'Accept': 'application/json',
							'X-CSRF-TOKEN': csrfToken,
						},
						body: JSON.stringify(checkoutPayload),
					});

					const payload = await response.json();

					if (!response.ok || !payload.id) {
						const message = payload.message || 'Unable to initialize PayPal checkout.';
						showPayPalError(message);
						throw new Error(message);
					}

					return payload.id;
				},
				onApprove: async (data) => {
					clearPayPalError();
					const response = await fetch(captureOrderUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'Accept': 'application/json',
							'X-CSRF-TOKEN': csrfToken,
						},
						body: JSON.stringify({ paypal_order_id: data.orderID }),
					});

					const payload = await response.json();

					if (!response.ok || !payload.redirect_url) {
						const message = payload.message || 'Unable to capture PayPal payment.';
						showPayPalError(message);
						return;
					}

					window.location.href = payload.redirect_url;
				},
				onError: () => {
					showPayPalError('A PayPal error occurred. Please try again.');
				},
			}).render('#paypal-checkout');
		}
	}
});

// Cart abandonment: record email + cart when user blurs the checkout email field
document.addEventListener('DOMContentLoaded', () => {
	const emailInput = document.getElementById('email');
	const trackEmailUrl = document.querySelector('meta[name="csrf-token"]') ? '/cart/track-email' : null;

	if (!emailInput || !trackEmailUrl) {
		return;
	}

	emailInput.addEventListener('blur', () => {
		const email = emailInput.value.trim();

		if (!email || !email.includes('@')) {
			return;
		}

		const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

		fetch(trackEmailUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': csrfToken,
			},
			body: JSON.stringify({ email }),
		}).catch(() => {});
	});
});

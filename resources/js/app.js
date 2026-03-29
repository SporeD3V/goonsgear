import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
	const variantPickers = document.querySelectorAll('[data-product-variant-picker]');

	variantPickers.forEach((picker) => {
		const variantSelect = picker.querySelector('[data-variant-select]');
		const priceElement = picker.querySelector('[data-variant-price]');
		const skuElement = picker.querySelector('[data-variant-sku]');
		const statusElement = picker.querySelector('[data-variant-status]');
		const qtyElement = picker.querySelector('[data-variant-qty]');
		const cartVariantInput = picker.querySelector('[data-cart-variant-input]');
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

			if (cartVariantInput) {
				cartVariantInput.value = selectedOption.value;
			}

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
						<a href="${result.url}" class="flex gap-3 border-b border-slate-100 p-2 text-sm hover:bg-slate-50">
							${result.image ? `<img src="${result.image}" alt="${result.name}" class="h-10 w-10 rounded object-cover">` : '<div class="h-10 w-10 rounded bg-slate-100"></div>'}
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

		const loadCities = async (countryCode, stateCode, selectedCity) => {
			if (!countryCode || !citiesUrl) {
				renderCityInput(selectedCity);
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

			stateWrapper.innerHTML = '<p class="py-2 text-xs text-slate-500">Loading states...</p>';

			try {
				const params = new URLSearchParams({ country: countryCode });
				const response = await fetch(`${statesUrl}?${params.toString()}`);

				if (!response.ok) {
					throw new Error('Failed to load states');
				}

				const payload = await response.json();
				const states = Array.isArray(payload.data) ? payload.data : [];

				if (states.length > 0) {
					renderStateSelect(states, selectedState);

					const stateSelect = document.getElementById('state');
					const activeState = stateSelect?.value || '';
					await loadCities(countryCode, activeState, selectedCity);

					stateSelect?.addEventListener('change', () => {
						const currentCity = document.getElementById('city')?.value || '';
						loadCities(countrySelect.value, stateSelect.value, currentCity);
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
			const currentState = document.getElementById('state')?.value || '';
			const currentCity = document.getElementById('city')?.value || '';
			loadStates(countrySelect.value, currentState, currentCity);
		});

		if (countrySelect.value) {
			loadStates(countrySelect.value, initialState, initialCity);
		} else {
			renderStateInput(initialState);
			renderCityInput(initialCity);
		}
	}
});

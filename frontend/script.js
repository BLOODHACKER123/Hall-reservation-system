const searchForm = document.querySelector('#venue-search');
const searchMessage = document.querySelector('#search-message');
const locationInput = document.querySelector('#location-input');
const venueTypeSelect = document.querySelector('#venue-type-select');
const eventDateInput = document.querySelector('#event-date-input');

if (searchForm) {
	searchForm.addEventListener('submit', (event) => {
		event.preventDefault();

		if (!searchForm.checkValidity()) {
			searchForm.reportValidity();
			return;
		}

		searchMessage.textContent = `Searching for ${venueTypeSelect.value.toLowerCase()}s in ${locationInput.value.trim()} on ${eventDateInput.value}.`;
	});
}

document.addEventListener('DOMContentLoaded', function() {
    const locationInput = document.getElementById('location-input');
    const locationContainer = document.getElementById('location-search');
    let timeoutId;

    if (!locationInput || !locationContainer) return;

    // 1. Inject Dropdown Styles Dynamically
    const style = document.createElement('style');
    style.innerHTML = `
        #custom-suggestions { display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ccc; border-radius: 4px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-height: 200px; overflow-y: auto; z-index: 9999; list-style: none; padding: 0; margin: 4px 0 0 0; text-align: left; }
        #custom-suggestions li { padding: 10px 15px; cursor: pointer; border-bottom: 1px solid #eee; color: #333; font-size: 0.95rem; }
        #custom-suggestions li:last-child { border-bottom: none; }
        #custom-suggestions li:hover { background-color: #f5f5f5; color: #000; }
    `;
    document.head.appendChild(style);

    // 2. Create Custom Dropdown Container
    const suggestionBox = document.createElement('ul');
    suggestionBox.id = 'custom-suggestions';
    locationContainer.style.position = 'relative'; // Anchor the dropdown
    locationContainer.appendChild(suggestionBox);

    // Disable native browser autocomplete to prevent overlap
    locationInput.setAttribute('autocomplete', 'off');

    // 3. Auto-Detect Location on Page Load
    if (!locationInput.value && navigator.geolocation) {
        const originalPlaceholder = locationInput.placeholder;
        locationInput.placeholder = "Detecting your location...";

        navigator.geolocation.getCurrentPosition(
            async function(position) {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;
                try {
                    const response = await fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lon}&localityLanguage=en`);
                    const data = await response.json();
                    const city = data.city || data.locality || data.principalSubdivision;
                    
                    if(city) {
                        locationInput.value = city;
                    } else {
                        locationInput.placeholder = originalPlaceholder;
                    }
                } catch (error) {
                    locationInput.placeholder = originalPlaceholder;
                }
            }, 
            function(error) {
                locationInput.placeholder = originalPlaceholder; 
            },
            { timeout: 5000 }
        );
    }

    // 4. Manual Typing Autocomplete (Open-Meteo API)
    locationInput.addEventListener('input', function(e) {
        const query = e.target.value.trim();

        if (query.length < 2) {
            suggestionBox.style.display = 'none'; 
            return;
        }

        clearTimeout(timeoutId);

       timeoutId = setTimeout(async () => {
            try {
                // Added '&featuretype=settlement' to strictly search for cities/towns/villages
                const response = await fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&format=json&addressdetails=1&countrycodes=LK&featuretype=settlement&limit=8`);
                
                if (!response.ok) throw new Error('API fetch failed');
                
                const data = await response.json();
                suggestionBox.innerHTML = '';

                if (data && data.length > 0) {
                    const addedCities = new Set();

                    data.forEach(place => {
                        // Since we are only fetching settlements now, place.name is exactly what we need
                        const cityName = place.name;
                        
                        if (cityName && !addedCities.has(cityName)) {
                            addedCities.add(cityName);
                            
                            const li = document.createElement('li');
                            const boldQuery = new RegExp(`(${query})`, 'gi');
                            li.innerHTML = cityName.replace(boldQuery, "<strong>$1</strong>");
                            
                            li.addEventListener('click', function() {
                                locationInput.value = cityName;
                                suggestionBox.style.display = 'none';
                            });

                            suggestionBox.appendChild(li);
                        }
                    });

                    suggestionBox.style.display = addedCities.size > 0 ? 'block' : 'none';
                } else {
                    suggestionBox.style.display = 'none';
                }
            } catch (error) {
                console.error("Live Search API Error:", error);
            }
        }, 400);

    });
    // 5. Hide dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== locationInput && e.target !== suggestionBox) {
            suggestionBox.style.display = 'none';
        }
    });
});
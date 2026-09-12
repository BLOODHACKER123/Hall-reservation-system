document.addEventListener('DOMContentLoaded', function() {
    const locationInput = document.getElementById('location-input');
    const dataList = document.getElementById('city-suggestions');
    let timeoutId;

    if (locationInput && !locationInput.value && navigator.geolocation) {
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


    if (locationInput && dataList) {
        locationInput.addEventListener('input', function(e) {
            const query = e.target.value.trim();

            // Clear datalist if less than 2 letters
            if (query.length < 2) {
                dataList.innerHTML = ''; 
                return;
            }

            clearTimeout(timeoutId);

            // Wait 400ms after typing stops
            timeoutId = setTimeout(async () => {
                console.log("Searching for:", query); // Debugging

                try {
                    // Open-Meteo API is great for autocomplete!
                    const response = await fetch(`https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(query)}&count=5&language=en&format=json`);
                    
                    if (!response.ok) throw new Error('API fetch failed');
                    
                    const data = await response.json();

                    // Clear old suggestions
                    dataList.innerHTML = '';

                    // Check if results exist
                    if (data.results && data.results.length > 0) {
                        console.log("Found cities:", data.results); // Debugging

                        // Set to prevent duplicates
                        const addedCities = new Set();

                        data.results.forEach(place => {
                            // Optional: You can force it to only show Sri Lankan cities 
                            // by un-commenting the next line:
                            // if (place.country !== "Sri Lanka") return; 

                            const cityName = place.name;
                            
                            if (cityName && !addedCities.has(cityName)) {
                                addedCities.add(cityName);
                                
                                const option = document.createElement('option');
                                option.value = cityName;
                                dataList.appendChild(option);
                            }
                        });
                    } else {
                        console.log("No cities found for this search.");
                    }
                } catch (error) {
                    console.error("Live Search API Error:", error);
                }
            }, 400); 
        });
    }
});
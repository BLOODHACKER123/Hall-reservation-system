<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Browse Venues | VenueVista</title>
  <link rel="icon" type="image/x-icon" href="images/venuevista-logo.png" />
  <link rel="stylesheet" href="common.css">
  <link rel="stylesheet" href="search.css">
	
</head>
<body>
	<section id="navigation-section">
      <div id="container">
        <div id="nav-bar">
          <a id="logo" href="index.php">VenueVista</a>
          <nav id="nav-links">
            <a href="search.php">Browse Venues</a>
            <a href="list.php">List a Venue</a>
            <a href="ownerdashboard.php">Owners Dashboard</a>
            <a href="admin.php">Admin</a>
          </nav>
          <div id="nav-buttons">
            <a id="list-venue-button" href="list.php">List a Venue</a>
            <a id="login-button" href="loginchoice.php">Login</a>
          </div>
        </div>
      </div>
    </section>

    <section id="search-section">
      <div id="search-container">
        <form id="search-form" action="search.php" method="get">
          <label class="search-field search-location">
            <span class="field-icon" aria-hidden="true">⌕</span>
            <input type="text" name="location" placeholder="Location..." aria-label="Location" />
          </label>
          <label class="search-field">
            <select name="event-type" aria-label="Venue type">
               <option value="" selected>All Types</option>
                <option value="Wedding Venue">Wedding Venue</option>
                <option value="Banquet Hall">Banquet Hall</option>
                <option value="Hotel">Hotel</option>
                <option value="Conference Venue">Conference Venue</option>
                <option value="Garden Venue">Garden Venue</option>
                <option value="Seminar Hall">Seminar Hall</option>
                <option value="Auditorium">Auditorium</option>
                <option value="Rooftop venue">Rooftop venue</option>
                <option value="Meeting">Meeting</option>
            </select>
          </label>
          <label class="search-field search-date">
            <input type="date" name="date" aria-label="Date" />
          </label>
          <label class="search-field search-guests">
            <select name="guests" aria-label="Guests">
              <option value="">Guests</option>
              <option value="1-50">1-50</option>
              <option value="51-150">51-150</option>
              <option value="151-300">151-300</option>
              <option value="301+">301+</option>
            </select>
          </label>
          <label class="search-field search-sort">
            <select name="sort" aria-label="Sort venues">
              <option value="newest">Newest First</option>
              <option value="price-low">Price: Low to High</option>
              <option value="price-high">Price: High to Low</option>
            </select>
          </label>
          <button class="filter-button" type="submit">
            <span class="filter-icon" aria-hidden="true">☷</span> Filters
          </button>
        </form>
      </div>
    </section>

	 <section id="featured-venue">
      <div>
        <p >6 Venues Found</p>
      
        <div id="featured-venue-cards">
          <div class="venue-card">
            <div class="venue-card-image">
              <img
                src="https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=1920&auto=format&fit=crop&q=80"
                alt="Venue 1"
              />
              <div class="hall-type-tag">Wedding Venue</div>
              <div class="featured-tag">Featured</div>
              <div class="rating-tag">★4.8</div>
            </div>

            <div class="location-details">
              <p>The Grand Rosewood Ballroom</p>
              <p class="cost">$8,500/day</p>
              <br />
              <p>
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i> New
                York, NY
              </p>
            </div>
            <div class="hall-details">
              <p>
                <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                100-600 guest
              </p>
              <p>
                <i class="fa-solid fa-comment-dots" aria-hidden="true"></i> 128
                reviews
              </p>
              <a href="search.php">view Details → </a>
            </div>
          </div>

          <div class="venue-card">
            <div class="venue-card-image">
              <img
                src="https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=1200&auto=format&fit=crop&q=80"
                alt="Venue 1"
              />
              <div class="hall-type-tag">Rooftop Venue</div>
              <div class="featured-tag">Featured</div>
              <div class="rating-tag">★4.7</div>
            </div>

            <div class="location-details">
              <p>Skyline Garden Terrace</p>
              <p class="cost">$5,500/day</p>
              <br />
              <p>
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                Chicago,IL
              </p>
            </div>
            <div class="hall-details">
              <p>
                <i class="fa-solid fa-user-group" aria-hidden="true"></i> 50-250
                guest
              </p>
              <p>
                <i class="fa-solid fa-comment-dots" aria-hidden="true"></i> 128
                reviews
              </p>
              <a href="search.php">view Details → </a>
            </div>
          </div>

          <div class="venue-card">
            <div class="venue-card-image">
              <img
                src="https://images.unsplash.com/photo-1530103862676-de8c9debad1d?w=1200&auto=format&fit=crop&q=80"
                alt="Venue 1"
              />
              <div class="hall-type-tag">Garden Venue</div>
              <div class="featured-tag">Featured</div>
              <div class="rating-tag">★4.9</div>
            </div>

            <div class="location-details">
              <p>The Verdant Garden Estate</p>
              <p class="cost">$6,500/day</p>
              <br />
              <p>
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                NAPA,CA
              </p>
            </div>
            <div class="hall-details">
              <p>
                <i class="fa-solid fa-user-group" aria-hidden="true"></i> 30-300
                guest
              </p>
              <p>
                <i class="fa-solid fa-comment-dots" aria-hidden="true"></i> 128
                reviews
              </p>
              <a href="search.php">view Details → </a>
            </div>
          </div>

           <div class="venue-card">
            <div class="venue-card-image">
              <img
                src="https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=1920&auto=format&fit=crop&q=80"
                alt="Venue 1"
              />
              <div class="hall-type-tag">Wedding Venue</div>
              <div class="featured-tag">Featured</div>
              <div class="rating-tag">★4.8</div>
            </div>

            <div class="location-details">
              <p>The Grand Rosewood Ballroom</p>
              <p class="cost">$8,500/day</p>
              <br />
              <p>
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i> New
                York, NY
              </p>
            </div>
            <div class="hall-details">
              <p>
                <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                100-600 guest
              </p>
              <p>
                <i class="fa-solid fa-comment-dots" aria-hidden="true"></i> 128
                reviews
              </p>
              <a href="search.php">view Details → </a>
            </div>
          </div>

          <div class="venue-card">
            <div class="venue-card-image">
              <img
                src="https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=1200&auto=format&fit=crop&q=80"
                alt="Venue 1"
              />
              <div class="hall-type-tag">Rooftop Venue</div>
              <div class="featured-tag">Featured</div>
              <div class="rating-tag">★4.7</div>
            </div>

            <div class="location-details">
              <p>Skyline Garden Terrace</p>
              <p class="cost">$5,500/day</p>
              <br />
              <p>
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                Chicago,IL
              </p>
            </div>
            <div class="hall-details">
              <p>
                <i class="fa-solid fa-user-group" aria-hidden="true"></i> 50-250
                guest
              </p>
              <p>
                <i class="fa-solid fa-comment-dots" aria-hidden="true"></i> 128
                reviews
              </p>
              <a href="search.php">view Details → </a>
            </div>
          </div>

          <div class="venue-card">
            <div class="venue-card-image">
              <img
                src="https://images.unsplash.com/photo-1530103862676-de8c9debad1d?w=1200&auto=format&fit=crop&q=80"
                alt="Venue 1"
              />
              <div class="hall-type-tag">Garden Venue</div>
              <div class="featured-tag">Featured</div>
              <div class="rating-tag">★4.9</div>
            </div>

            <div class="location-details">
              <p>The Verdant Garden Estate</p>
              <p class="cost">$6,500/day</p>
              <br />
              <p>
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                NAPA,CA
              </p>
            </div>
            <div class="hall-details">
              <p>
                <i class="fa-solid fa-user-group" aria-hidden="true"></i> 30-300
                guest
              </p>
              <p>
                <i class="fa-solid fa-comment-dots" aria-hidden="true"></i> 128
                reviews
              </p>
              <a href="search.php">view Details → </a>
            </div>
          </div>
          
        </div>
      </div>
    </section>

	 <section id="footer-section">
      <div id="footer-body">
        <div id="footer-top">

          <div id="footer-details-block">
            <h1>VenueVista</h1>
            <p>Discover extraordinary spaces for life's most meaningful moments. Where every venue tells 
              a story.</p>

            <div class="social-links">
              <a href="">INSTAGRAM</a>
              <a href="">PINTEREST</a>
              <a href="">FACEBOOK</a>
            </div>
          </div>
        
          <div class="footer-nav-links">

            <p>DISCOVER</p>
            <a href="search.php">Browse Venues</a>
            <a href="search.php">Wedding Venues</a>
            <a href="search.php">Banquet Halls</a>
            <a href="search.php">Conference Halls</a>
          
          </div>

          <div class="footer-nav-links">
            <p>FOR OWNERS</p> 
            
                <a href="list.php">List Your Venue</a>
                <a href="ownerdashboard.php">Owner Dashboard</a>
                <a href="mybookings.php">My Bookings</a>
              
          </div>
        
        </div>
        <div id="footer-bottom">
            <p>@ 2026 VenueVista. All rights reserved.</p>

            <div class="footer-links">
            <a href="">Privacy Policy</a>
            <a href="">Terms of Service</a>
            <a href="">Contact</a>
            </div>
          </div>
      </div>
    </section> 
</body>
</html>

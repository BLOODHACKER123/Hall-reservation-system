<?php
session_start();

// 1. TURN ON ERRORS FOR DEBUGGING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. SAFELY LOAD DATABASE
$db_path = __DIR__ . '/../backend/config/database.php';
if (!file_exists($db_path)) {
    die("<h2 style='color:red; text-align:center;'>FATAL ERROR: Cannot find database file.</h2>");
}
require_once $db_path;

if (!isset($pdo)) {
    die("<h2 style='color:red; text-align:center;'>FATAL ERROR: Database connected, but \$pdo variable is missing.</h2>");
}

// FORCE PDO TO THROW STRICT EXCEPTIONS TO PREVENT SILENT 500 CRASHES
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 3. SESSION LOGIC (Lock down to Vendors only)
if (!isset($_SESSION['user_id'])) {
    header("Location: loginchoice.php");
    exit;
}
if ($_SESSION['user_type'] !== 'Vendor') {
    header("Location: index.php");
    exit;
}

$success_message = '';$error_message = '';

// 4. INITIALIZE VARIABLES TO RETAIN FORM DATA (Safe Fallbacks)
$name = isset($_POST['venue-name']) ?$_POST['venue-name'] : '';
$email = isset($_POST['owner-email']) ? $_POST['owner-email'] : '';$venue_type = isset($_POST['venue-type']) ?$_POST['venue-type'] : '';
$city = isset($_POST['city']) ? $_POST['city'] : '';$address = isset($_POST['address']) ?$_POST['address'] : '';
$description = isset($_POST['description']) ? $_POST['description'] : '';$min_capacity = isset($_POST['min-capacity']) ?$_POST['min-capacity'] : '';
$max_capacity = isset($_POST['max-capacity']) ? $_POST['max-capacity'] : '';$base_price = isset($_POST['base-price']) ?$_POST['base-price'] : '';
$venue_rules = isset($_POST['venue-rules']) ? $_POST['venue-rules'] : '';$cancellation_policy = isset($_POST['cancellation-policy']) ?$_POST['cancellation-policy'] : '';

$selected_seating = isset($_POST['seating']) && is_array($_POST['seating']) ?$_POST['seating'] : [];
$selected_catering = isset($_POST['catering']) && is_array($_POST['catering']) ?$_POST['catering'] : [];
$selected_amenities = isset($_POST['amenities']) && is_array($_POST['amenities']) ?$_POST['amenities'] : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clean_name = htmlspecialchars(strip_tags(trim($name)));
    $clean_email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    $clean_venue_type = htmlspecialchars(strip_tags(trim($venue_type)));
    $clean_city = htmlspecialchars(strip_tags(trim($city)));
    $clean_address = htmlspecialchars(strip_tags(trim($address)));
    $clean_description = htmlspecialchars(strip_tags(trim($description)));
    $clean_max = intval($max_capacity);
    $clean_price = floatval($base_price);

    $pkg_names = isset($_POST['package_name']) && is_array($_POST['package_name']) ?$_POST['package_name'] : [];
    $pkg_prices = isset($_POST['package_price']) && is_array($_POST['package_price']) ?$_POST['package_price'] : [];
    $pkg_descs = isset($_POST['package_desc']) && is_array($_POST['package_desc']) ?$_POST['package_desc'] : [];
    $pkg_includes = isset($_POST['package_includes']) && is_array($_POST['package_includes']) ?$_POST['package_includes'] : [];

    if (empty($clean_name) || empty($clean_email) || empty($clean_venue_type) || empty($clean_city) || empty($clean_description) ||$clean_max <= 0 || $clean_price <= 0) {$error_message = "Please fill in all required fields correctly.";
    } elseif (!filter_var($clean_email, FILTER_VALIDATE_EMAIL)) {$error_message = "Please provide a valid email address.";
    } else {
        try {
            $pdo->beginTransaction();

            // Check if user exists
            $stmt =$pdo->prepare("SELECT user_id, user_type FROM users WHERE email = ?");
            $stmt->execute([$clean_email]);
            $user =$stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $user_id =$user['user_id'];
                
                $vendor_check =$pdo->prepare("SELECT user_id FROM vendors WHERE user_id = ?");
                $vendor_check->execute([$user_id]);
                if (!$vendor_check->fetch()) {
                    $stmt =$pdo->prepare("INSERT INTO vendors (user_id, business_name, business_address, verification_status) VALUES (?, ?, ?, 'Pending')");
                    $stmt->execute([$user_id, $clean_name,$clean_city]);
                    
                    $update_type =$pdo->prepare("UPDATE users SET user_type = 'Vendor' WHERE user_id = ?");
                    $update_type->execute([$user_id]);
                }
            } else {
                $stmt =$pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password, user_type) VALUES (?, ?, ?, ?, ?, 'Vendor')");
                $stmt->execute(['Venue', 'Owner',$clean_email, '+947' . rand(1000000, 9999999), password_hash('password123', PASSWORD_DEFAULT)]);
                $user_id =$pdo->lastInsertId();

                $stmt =$pdo->prepare("INSERT INTO vendors (user_id, business_name, business_address, verification_status) VALUES (?, ?, ?, 'Pending')");
                $stmt->execute([$user_id, $clean_name,$clean_city]);
            }

            $env_type = 'indoor';
            if (in_array(strtolower($clean_venue_type), ['garden venue', 'rooftop venue'])) {$env_type = 'open_garden';
            }

            $stmt =$pdo->prepare("
                INSERT INTO halls (
                    vendor_id, name, description, district, address, capacity, 
                    environment_type, venue_type, base_price_per_hour, 
                    weekend_price_per_hour, security_deposit, cancellation_policy, 
                    buffer_time_minutes, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'moderate', 60, 0)
            ");
            
            $stmt->execute([$user_id, $clean_name,$clean_description, $clean_city,$clean_address, $clean_max,$env_type, $clean_venue_type,$clean_price, $clean_price * 1.2,$clean_price * 0.5
            ]);
            
            $hall_id =$pdo->lastInsertId();

            if (!empty($pkg_names)) {
                $pkg_stmt =$pdo->prepare("INSERT INTO hall_packages (hall_id, package_name, price, description, includes) VALUES (?, ?, ?, ?, ?)");
                for ($i = 0; $i < count($pkg_names);$i++) {
                    $p_name = htmlspecialchars(strip_tags(trim($pkg_names[$i])));$p_price = floatval($pkg_prices[$i] ?? 0);
                    $p_desc = htmlspecialchars(strip_tags(trim($pkg_descs[$i] ?? '')));$p_inc = htmlspecialchars(strip_tags(trim($pkg_includes[$i] ?? '')));
                    
                    if (!empty($p_name) &&$p_price > 0) {
                        $pkg_stmt->execute([$hall_id, $p_name,$p_price, $p_desc,$p_inc]);
                    }
                }
            }

            $pdo->commit();$success_message = "Venue submitted successfully! It is currently pending admin review.";
            
            $name =$email = $venue_type =$city = $address =$description = $min_capacity =$max_capacity = $base_price =$venue_rules = $cancellation_policy = '';$selected_seating = $selected_catering =$selected_amenities = [];
            
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {$pdo->rollBack();
            }
            $error_message = "SYSTEM CRASH PREVENTED: " . $e->getMessage() . " on line " . $e->getLine();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>List Your Venue | VenueVista</title>
  <link rel="icon" type="image/x-icon" href="images/venuevista-logo.png" />
  <link rel="stylesheet" href="common.css">
  <script src="navigation.js" defer></script>
  <link rel="stylesheet" href="list.css">
 
  
  <style>
      /* Real-time validation styles */
      input.valid-field, textarea.valid-field { border-color: #2e7d32 !important; outline-color: #2e7d32 !important; }
      input.invalid-field, textarea.invalid-field { border-color: #c62828 !important; outline-color: #c62828 !important; }
      
      /* In-page validation banner */
      #js-error-banner {
          display: none;
          background: #fce8e6;
          color: #c5221f;
          padding: 15px;
          border-radius: 8px;
          margin-bottom: 20px;
          text-align: center;
          font-weight: 500;
          border: 1px solid #f5c6cb;
          animation: fadeIn 0.3s ease-in-out;
      }

      @keyframes fadeIn {
          from { opacity: 0; transform: translateY(-6px); }
          to { opacity: 1; transform: translateY(0); }
      }
  </style>
</head>
<body>

    <!-- DYNAMIC NAVIGATION BAR -->
    <section id="navigation-section">
      <div id="container">
        <div id="nav-bar">
          <a id="logo" href="index.php">VenueVista</a>
          <nav id="nav-links">
            <a href="search.php">Browse Venues</a>
            <a href="list.php" class="active">List a Venue</a>
            
            <?php if (isset($_SESSION['user_type'])): ?>
                <?php if ($_SESSION['user_type'] === 'Vendor'): ?>
                    <a href="ownerdashboard.php">Owners Dashboard</a>   
                <?php elseif ($_SESSION['user_type'] === 'Admin'): ?>
                    <a href="admin.php">Admin Dashboard</a>
                <?php elseif ($_SESSION['user_type'] === 'Customer'): ?>
                    <a href="mybookings.php">My Bookings</a>
                <?php endif; ?>
            <?php endif; ?>
          </nav>
          
          <div id="nav-buttons">
            <?php if (!isset($_SESSION['user_type']) ||$_SESSION['user_type'] !== 'Admin'): ?>
                <a id="list-venue-button" href="list.php">List a Venue</a>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['user_id'])): ?>
                <a id="login-button" href="../backend/config/logout.php">Logout</a>
            <?php else: ?>
                <a id="login-button" href="loginchoice.php">Login</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

  <main class="page-content">
    <p class="eyebrow">JOIN VENUEVISTA</p>
    <h1>List Your Venue</h1>
    <p class="intro">Reach thousands of customers looking for the perfect space. It's free to list.</p>

    <!-- In-page client-side dynamic error box -->
    <div id="js-error-banner" role="alert" aria-live="assertive"></div>

    <?php if (!empty($success_message)): ?>
        <div style="background: #e6f4ea; color: #137333; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 500;">
            <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div style="background: #fce8e6; color: #c5221f; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 500;">
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <ol class="steps" aria-label="Listing progress">
      <li class="current"><span>1</span><strong>Basic Info</strong></li>
      <li><span>2</span><strong>Capacity &amp; Pricing</strong></li>
      <li><span>3</span><strong>Photos &amp; Amenities</strong></li>
      <li><span>4</span><strong>Packages</strong></li>
      <li><span>5</span><strong>Rules and Submit</strong></li>
    </ol>
   
    <form class="listing-form" action="list.php" method="post" novalidate>
      <section class="form-step active-step" data-step="0">
      <h2>Basic Information</h2>
      <label for="venue-name">Venue Name <em>*</em></label>
      <input id="venue-name" name="venue-name" type="text" placeholder="The Grand Ballroom" value="<?= htmlspecialchars($name) ?>" required>

      <label for="owner-email">Owner Email <em>*</em></label>
      <input id="owner-email" name="owner-email" type="email" placeholder="owner@venue.com" value="<?= htmlspecialchars($email) ?>" required>

      <fieldset>
        <legend>Venue Type <em>*</em></legend>
        <div class="type-options">
          <label><input type="radio" name="venue-type" value="Wedding Venue" <?= ($venue_type === 'Wedding Venue') ? 'checked' : '' ?> required><span>Wedding Venue</span></label>
          <label><input type="radio" name="venue-type" value="Banquet Hall" <?= ($venue_type === 'Banquet Hall') ? 'checked' : '' ?>><span>Banquet Hall</span></label>
          <label><input type="radio" name="venue-type" value="Hotel" <?= ($venue_type === 'Hotel') ? 'checked' : '' ?>><span>Hotel</span></label>
          <label><input type="radio" name="venue-type" value="Conference Hall" <?= ($venue_type === 'Conference Hall') ? 'checked' : '' ?>><span>Conference Hall</span></label>
          <label><input type="radio" name="venue-type" value="Garden Venue" <?= ($venue_type === 'Garden Venue') ? 'checked' : '' ?>><span>Garden Venue</span></label>
          <label><input type="radio" name="venue-type" value="Seminar Hall" <?= ($venue_type === 'Seminar Hall') ? 'checked' : '' ?>><span>Seminar Hall</span></label>
          <label><input type="radio" name="venue-type" value="Auditorium" <?= ($venue_type === 'Auditorium') ? 'checked' : '' ?>><span>Auditorium</span></label>
          <label><input type="radio" name="venue-type" value="Rooftop venue" <?= ($venue_type === 'Rooftop venue') ? 'checked' : '' ?>><span>Rooftop Venue</span></label>
          <label><input type="radio" name="venue-type" value="Meeting Room" <?= ($venue_type === 'Meeting Room') ? 'checked' : '' ?>><span>Meeting Room</span></label>
        </div>
      </fieldset>

      <label for="city">City / Area <em>*</em></label>
      <input id="city" name="city" type="text" placeholder="Colombo" value="<?= htmlspecialchars($city) ?>" required>
      
      <label for="address">Full Address</label>
      <input id="address" name="address" type="text" placeholder="123 Main Street, Colombo" value="<?= htmlspecialchars($address) ?>">
      
      <label for="description">Description <em>*</em></label>
      <textarea id="description" name="description" placeholder="Describe your venue..." required><?= htmlspecialchars($description) ?></textarea>
      </section>

      <section class="form-step" data-step="1">
        <h2>Capacity &amp; Pricing</h2>
        <div class="two-fields">
            <div><label for="min-capacity">Min Capacity <em>*</em></label><input id="min-capacity" name="min-capacity" type="number" value="<?= htmlspecialchars($min_capacity) ?>" required></div>
            <div><label for="max-capacity">Max Capacity <em>*</em></label><input id="max-capacity" name="max-capacity" type="number" value="<?= htmlspecialchars($max_capacity) ?>" required></div>
        </div>
        <label for="base-price">Base Price per Day ($) <em>*</em></label>
        <input id="base-price" name="base-price" type="number" value="<?= htmlspecialchars($base_price) ?>" required>
        
        <fieldset><legend>Seating Layouts Available</legend><div class="choice-pills">
            <label><input type="checkbox" name="seating[]" value="theatre" <?= in_array('theatre', $selected_seating) ? 'checked' : '' ?>><span>Theatre</span></label>
            <label><input type="checkbox" name="seating[]" value="classroom" <?= in_array('classroom', $selected_seating) ? 'checked' : '' ?>><span>Classroom</span></label>
            <label><input type="checkbox" name="seating[]" value="banquet" <?= in_array('banquet', $selected_seating) ? 'checked' : '' ?>><span>Banquet</span></label>
            <label><input type="checkbox" name="seating[]" value="cocktail" <?= in_array('cocktail', $selected_seating) ? 'checked' : '' ?>><span>Cocktail</span></label>
        </div></fieldset>

        <fieldset><legend>Catering Options</legend><div class="choice-pills">
            <label><input type="checkbox" name="catering[]" value="in-house" <?= in_array('in-house', $selected_catering) ? 'checked' : '' ?>><span>In-house Catering</span></label>
            <label><input type="checkbox" name="catering[]" value="external" <?= in_array('external', $selected_catering) ? 'checked' : '' ?>><span>External Caterers Allowed</span></label>
            <label><input type="checkbox" name="catering[]" value="alcohol" <?= in_array('alcohol', $selected_catering) ? 'checked' : '' ?>><span>Alcohol Allowed</span></label>
        </div></fieldset>
      </section>

      <section class="form-step" data-step="2">
        <h2>Photos &amp; Amenities</h2>
        <label for="photo-url">Photo URLs</label><div class="inline-field"><input id="photo-url" type="url" placeholder="https://your-photo-url.com/image.jpg"><button class="add-button" type="button">Add</button></div>
        
        <fieldset><legend>Amenities Available</legend><div class="choice-pills">
            <label><input type="checkbox" name="amenities[]" value="wifi" <?= in_array('wifi', $selected_amenities) ? 'checked' : '' ?>><span>WiFi</span></label>
            <label><input type="checkbox" name="amenities[]" value="parking" <?= in_array('parking', $selected_amenities) ? 'checked' : '' ?>><span>Parking</span></label>
            <label><input type="checkbox" name="amenities[]" value="stage" <?= in_array('stage', $selected_amenities) ? 'checked' : '' ?>><span>Stage</span></label>
            <label><input type="checkbox" name="amenities[]" value="air-conditioning" <?= in_array('air-conditioning', $selected_amenities) ? 'checked' : '' ?>><span>Air Conditioning</span></label>
        </div></fieldset>
      </section>

      <section class="form-step" data-step="3">
        <h2>Packages</h2>
        <div class="package-box" id="package-container">
          <label>Add a Package</label>
          <div class="package-entry">
            <input type="text" name="package_name[]" placeholder="Package name (e.g. Silver)">
            <div class="two-fields">
              <input type="number" name="package_price[]" placeholder="Price ($)">
              <input type="text" name="package_desc[]" placeholder="Short description">
            </div>
            <input type="text" name="package_includes[]" placeholder="Includes (comma-separated)">
          </div>
          <button class="add-button" type="button" id="add-package-btn">+ &nbsp; Add Package</button>
        </div>
      </section>

      <section class="form-step" data-step="4">
        <h2>Rules &amp; Policies</h2>
        <label for="venue-rules">Venue Rules</label>
        <textarea id="venue-rules" name="venue-rules" placeholder="e.g. No smoking..."><?= htmlspecialchars($venue_rules) ?></textarea>
        
        <label for="cancellation-policy">Cancellation Policy</label>
        <textarea id="cancellation-policy" name="cancellation-policy" placeholder="e.g. Full refund..."><?= htmlspecialchars($cancellation_policy) ?></textarea>
      </section>

      <div class="form-actions">
        <button class="previous" type="button" disabled>← &nbsp; Previous</button>
        <button class="next" type="submit">Submit Listing <span aria-hidden="true">→</span></button>
      </div>
    </form>
  </main>

  <section id="footer-section">
      <div id="footer-body">
        <div id="footer-top">
          <div id="footer-details-block">
            <h1>VenueVista</h1>
            <p>Discover extraordinary spaces for life's most meaningful moments.</p>
          </div>
        </div>
        <div id="footer-bottom">
            <p>@ 2026 VenueVista. All rights reserved.</p>
        </div>
      </div>
  </section> 

<script>
   document.addEventListener('DOMContentLoaded', function() {
    const steps = document.querySelectorAll('.form-step');
    const indicators = document.querySelectorAll('.steps li');
    const nextBtn = document.querySelector('.next');
    const prevBtn = document.querySelector('.previous');
    const errorBanner = document.getElementById('js-error-banner');
    let currentStep = 0;

    function showError(message) {
        if (!errorBanner) return;
        errorBanner.textContent = message;
        errorBanner.style.display = 'block';
        errorBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function clearError() {
        if (!errorBanner) return;
        errorBanner.textContent = '';
        errorBanner.style.display = 'none';
    }

    function updateFormView() {
        // Enforce visible step display
        steps.forEach((step, index) => {
            step.style.display = index === currentStep ? 'block' : 'none';
            step.classList.toggle('active-step', index === currentStep);
        });

        // Sync step indicators with the active step
        indicators.forEach((indicator, index) => {
            indicator.classList.remove('current', 'completed');
            if (index === currentStep) {
                indicator.classList.add('current');
            } else if (index < currentStep) {
                indicator.classList.add('completed');
            }
        });

        prevBtn.disabled = currentStep === 0;

        if (currentStep === steps.length - 1) {
            nextBtn.innerHTML = 'Submit Listing <span aria-hidden="true">→</span>';
            nextBtn.type = 'submit';
        } else {
            nextBtn.innerHTML = 'Next <span aria-hidden="true">→</span>';
            nextBtn.type = 'button';
        }
    }

    function validateCurrentStep() {
        const currentSection = steps[currentStep];
        const currentInputs = currentSection.querySelectorAll('input[required], textarea[required], select[required]');
        let allValid = true;
        let firstInvalidField = null;

        currentInputs.forEach(input => {
            if (input.type === 'radio') {
                const radioGroup = currentSection.querySelectorAll(`input[name="${input.name}"]`);
                const isChecked = Array.from(radioGroup).some(r => r.checked);
                if (!isChecked) {
                    allValid = false;
                    if (!firstInvalidField) firstInvalidField = radioGroup[0];
                }
            } else {
                if (!input.checkValidity() || input.value.trim() === '') {
                    input.classList.add('invalid-field');
                    input.classList.remove('valid-field');
                    allValid = false;
                    if (!firstInvalidField) firstInvalidField = input;
                } else {
                    input.classList.remove('invalid-field');
                    input.classList.add('valid-field');
                }
            }
        });

        if (!allValid) {
            showError("Please fill in all required fields highlighted in red before proceeding.");
            if (firstInvalidField) {
                firstInvalidField.focus();
            }
            return false;
        }

        clearError();
        return true;
    }

    // Intercept click on the Next/Submit control
    nextBtn.addEventListener('click', function(e) {
        if (currentStep < steps.length - 1) {
            e.preventDefault();
            e.stopPropagation();

            if (validateCurrentStep()) {
                currentStep++;
                updateFormView();
            }
        } else {
            // Final submission validation
            if (!validateCurrentStep()) {
                e.preventDefault();
                e.stopPropagation();
            }
        }
    });

    prevBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (currentStep > 0) {
            clearError();
            currentStep--;
            updateFormView();
        }
    });

    // Real-time error clearing when user edits fields
    document.querySelectorAll('.listing-form input, .listing-form textarea, .listing-form select').forEach(input => {
        const resetValidation = function() {
            if (this.checkValidity() && this.value.trim() !== '') {
                this.classList.remove('invalid-field');
                this.classList.add('valid-field');
            }
            clearError();
        };
        input.addEventListener('input', resetValidation);
        input.addEventListener('change', resetValidation);
    });

    // Package cloning handler
    const addBtn = document.getElementById('add-package-btn');
    const packageContainer = document.getElementById('package-container');
    if (addBtn && packageContainer) {
        addBtn.addEventListener('click', function() {
            const newEntry = document.createElement('div');
            newEntry.className = 'package-entry';
            newEntry.style.marginTop = '20px';
            newEntry.style.paddingTop = '20px';
            newEntry.style.borderTop = '1px dashed #d4a5a54d';
            newEntry.innerHTML = `
                <input type="text" name="package_name[]" placeholder="Package name (e.g. Gold)">
                <div class="two-fields">
                    <input type="number" name="package_price[]" placeholder="Price ($)">
                    <input type="text" name="package_desc[]" placeholder="Short description">
                </div>
                <input type="text" name="package_includes[]" placeholder="Includes (comma-separated)">
                <button type="button" class="remove-pkg" style="background: none; border: none; color: #c62828; cursor: pointer; font-size: 0.9rem; margin-top: 10px;">- Remove this package</button>
            `;
            packageContainer.insertBefore(newEntry, addBtn);
            newEntry.querySelector('.remove-pkg').addEventListener('click', function() {
                newEntry.remove();
            });
        });
    }

    // Initialize clean view state
    updateFormView();
});
    </script>
</body>
</html>
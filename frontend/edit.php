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

if (!isset($_GET['hall_id'])) {
    header("Location: ownerdashboard.php");
    exit;
}
$hall_id = intval($_GET['hall_id']);

$success_message = '';$error_message = '';

// FETCH EXISTING HALL DETAILS TO PRE-FILL THE FORM
try {
    $stmt =$pdo->prepare("SELECT * FROM halls WHERE hall_id = ? AND vendor_id = ?");
    $stmt->execute([$hall_id,$_SESSION['user_id']]);
    $venue =$stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venue) {
        die("<h2 style='color:red; text-align:center;'>Venue not found or you do not have permission to edit it. (Hall ID: $hall_id)</h2>");
    }
    
    // Fetch existing packages for this hall
    $pkg_stmt =$pdo->prepare("SELECT * FROM hall_packages WHERE hall_id = ?");
    $pkg_stmt->execute([$hall_id]);
    $existing_packages =$pkg_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch the owner's email
    $owner_stmt =$pdo->prepare("SELECT email FROM users WHERE user_id = ?");
    $owner_stmt->execute([$venue['vendor_id']]);$owner = $owner_stmt->fetch(PDO::FETCH_ASSOC);$owner_email = $owner ? $owner['email'] : '';
    
} catch (Throwable $e) {
    die("Database Error: " . $e->getMessage());
}

// HANDLE THE UPDATE SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['venue-name']) ? $_POST['venue-name'] : '';$venue_type = isset($_POST['venue-type']) ?$_POST['venue-type'] : '';
    $city = isset($_POST['city']) ? $_POST['city'] : '';$address = isset($_POST['address']) ?$_POST['address'] : '';
    $description = isset($_POST['description']) ? $_POST['description'] : '';$max_capacity = isset($_POST['max-capacity']) ?$_POST['max-capacity'] : '';
    $base_price = isset($_POST['base-price']) ? $_POST['base-price'] : '';$cancellation_policy = isset($_POST['cancellation-policy']) ?$_POST['cancellation-policy'] : '';

    $pkg_names = isset($_POST['package_name']) && is_array($_POST['package_name']) ?$_POST['package_name'] : [];
    $pkg_prices = isset($_POST['package_price']) && is_array($_POST['package_price']) ?$_POST['package_price'] : [];
    $pkg_descs = isset($_POST['package_desc']) && is_array($_POST['package_desc']) ?$_POST['package_desc'] : [];
    $pkg_includes = isset($_POST['package_includes']) && is_array($_POST['package_includes']) ?$_POST['package_includes'] : [];

    $clean_name = htmlspecialchars(strip_tags(trim($name)));
    $clean_venue_type = htmlspecialchars(strip_tags(trim($venue_type)));
    $clean_city = htmlspecialchars(strip_tags(trim($city)));
    $clean_address = htmlspecialchars(strip_tags(trim($address)));
    $clean_description = htmlspecialchars(strip_tags(trim($description)));
    $clean_max = intval($max_capacity);
    $clean_price = floatval($base_price);
    $clean_cancellation = htmlspecialchars(strip_tags(trim($cancellation_policy)));

    if (empty($clean_name) || empty($clean_venue_type) || empty($clean_city) || empty($clean_description) ||$clean_max <= 0 || $clean_price <= 0) {$error_message = "Please fill in all required fields correctly.";
    } else {
        try {
            $pdo->beginTransaction();

            $env_type = 'indoor';
            if (in_array(strtolower($clean_venue_type), ['garden venue', 'rooftop venue'])) {$env_type = 'open_garden';
            }

           
            $update_stmt =$pdo->prepare("
                UPDATE halls 
                SET name = ?, description = ?, district = ?, address = ?, capacity = ?, 
                    environment_type = ?, venue_type = ?, base_price_per_hour = ?, 
                    weekend_price_per_hour = ?, security_deposit = ?, cancellation_policy = ?, 
                    is_active = 0 
                WHERE hall_id = ? AND vendor_id = ?
            ");
            
            $update_stmt->execute([$clean_name,$clean_description,$clean_city,$clean_address,$clean_max,$env_type,$clean_venue_type,$clean_price,$clean_price * 1.2,$clean_price * 0.5,$clean_cancellation,$hall_id,$_SESSION['user_id']
            ]);

       
            $pdo->prepare("DELETE FROM hall_packages WHERE hall_id = ?")->execute([$hall_id]);
            
            if (!empty($pkg_names)) {
                $pkg_insert =$pdo->prepare("INSERT INTO hall_packages (hall_id, package_name, price, description, includes) VALUES (?, ?, ?, ?, ?)");
                for ($i = 0; $i < count($pkg_names);$i++) {
                    $p_name = htmlspecialchars(strip_tags(trim($pkg_names[$i])));$p_price = floatval($pkg_prices[$i] ?? 0);
                    $p_desc = htmlspecialchars(strip_tags(trim($pkg_descs[$i] ?? '')));$p_inc = htmlspecialchars(strip_tags(trim($pkg_includes[$i] ?? '')));
                    
                    if (!empty($p_name) &&$p_price > 0) {
                        $pkg_insert->execute([$hall_id, $p_name,$p_price, $p_desc,$p_inc]);
                    }
                }
            }

            $pdo->commit();
            
      
            header("Location: ownerdashboard.php#venues");
            exit;
            
        } catch (Throwable $e) { 
            if (isset($pdo) && $pdo->inTransaction()) {$pdo->rollBack();
            }
            $error_message = "SYSTEM CRASH PREVENTED: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Venue | VenueVista</title>
  <link rel="icon" type="image/x-icon" href="images/venuevista-logo.png" />
  <link rel="stylesheet" href="common.css">
  <script src="navigation.js" defer></script>
  <link rel="stylesheet" href="list.css">
  
  <style>
     
      input.valid-field, textarea.valid-field { border-color: #2e7d32 !important; outline-color: #2e7d32 !important; }
      input.invalid-field, textarea.invalid-field { border-color: #c62828 !important; outline-color: #c62828 !important; }
      
      .warning-banner { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; border: 1px solid #ffeeba; }
  </style>
</head>
<body>

   
    <section id="navigation-section">
      <div id="container">
        <div id="nav-bar">
          <a id="logo" href="index.php">VenueVista</a>
          <nav id="nav-links">
            <a href="search.php">Browse Venues</a>
            
            <?php if (isset($_SESSION['user_type'])): ?>
                <?php if ($_SESSION['user_type'] === 'Vendor'): ?>
                    <a href="list.php">List a Venue</a>
                    <a href="ownerdashboard.php" class="active">Owners Dashboard</a>
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
    <p class="eyebrow">UPDATE VENUE DETAILS</p>
    <h1>Edit: <?= htmlspecialchars($venue['name']) ?></h1>
    <p class="intro">Update your venue information. Note: Changes require Admin approval to go live.</p>

    <div class="warning-banner">
        <strong>Important:</strong> Saving changes will temporarily remove this venue from the public search until an Admin approves it.
    </div>

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
   
    <form class="listing-form" action="edit.php?hall_id=<?= $hall_id ?>" method="post" novalidate>
      
      <section class="form-step active-step" data-step="0">
      <h2>Basic Information</h2>
      <label for="venue-name">Venue Name <em>*</em></label>
      <input id="venue-name" name="venue-name" type="text" value="<?= htmlspecialchars($venue['name']) ?>" required>

      <label for="owner-email">Owner Email (Read-Only)</label>
      <input id="owner-email" name="owner-email" type="email" value="<?= htmlspecialchars($owner_email) ?>" readonly style="background: #f0f0f0; cursor: not-allowed;">

      <fieldset>
        <legend>Venue Type <em>*</em></legend>
        <div class="type-options">
          <label><input type="radio" name="venue-type" value="Wedding Venue" <?= ($venue['venue_type'] === 'Wedding Venue') ? 'checked' : '' ?> required><span>Wedding Venue</span></label>
          <label><input type="radio" name="venue-type" value="Banquet Hall" <?= ($venue['venue_type'] === 'Banquet Hall') ? 'checked' : '' ?>><span>Banquet Hall</span></label>
          <label><input type="radio" name="venue-type" value="Hotel" <?= ($venue['venue_type'] === 'Hotel') ? 'checked' : '' ?>><span>Hotel</span></label>
          <label><input type="radio" name="venue-type" value="Conference Hall" <?= ($venue['venue_type'] === 'Conference Hall') ? 'checked' : '' ?>><span>Conference Hall</span></label>
          <label><input type="radio" name="venue-type" value="Garden Venue" <?= ($venue['venue_type'] === 'Garden Venue') ? 'checked' : '' ?>><span>Garden Venue</span></label>
          <label><input type="radio" name="venue-type" value="Seminar Hall" <?= ($venue['venue_type'] === 'Seminar Hall') ? 'checked' : '' ?>><span>Seminar Hall</span></label>
          <label><input type="radio" name="venue-type" value="Auditorium" <?= ($venue['venue_type'] === 'Auditorium') ? 'checked' : '' ?>><span>Auditorium</span></label>
          <label><input type="radio" name="venue-type" value="Rooftop venue" <?= ($venue['venue_type'] === 'Rooftop venue') ? 'checked' : '' ?>><span>Rooftop Venue</span></label>
          <label><input type="radio" name="venue-type" value="Meeting Room" <?= ($venue['venue_type'] === 'Meeting Room') ? 'checked' : '' ?>><span>Meeting Room</span></label>
        </div>
      </fieldset>

      <label for="city">City / Area <em>*</em></label>
      <input id="city" name="city" type="text" value="<?= htmlspecialchars($venue['district']) ?>" required>
      
      <label for="address">Full Address</label>
      <input id="address" name="address" type="text" value="<?= htmlspecialchars($venue['address']) ?>">
      
      <label for="description">Description <em>*</em></label>
      <textarea id="description" name="description" required><?= htmlspecialchars($venue['description']) ?></textarea>
      </section>

      <section class="form-step" data-step="1">
        <h2>Capacity &amp; Pricing</h2>
        <div class="two-fields">
            <div><label for="min-capacity">Min Capacity</label><input id="min-capacity" name="min-capacity" type="number" value="1"></div>
            <div><label for="max-capacity">Max Capacity <em>*</em></label><input id="max-capacity" name="max-capacity" type="number" value="<?= htmlspecialchars($venue['capacity']) ?>" required></div>
        </div>
        <label for="base-price">Base Price per Day ($) <em>*</em></label>
        <input id="base-price" name="base-price" type="number" value="<?= htmlspecialchars($venue['base_price_per_hour']) ?>" required>
        
        <fieldset><legend>Seating Layouts Available</legend><div class="choice-pills">
            <label><input type="checkbox" name="seating[]" value="theatre"><span>Theatre</span></label>
            <label><input type="checkbox" name="seating[]" value="classroom"><span>Classroom</span></label>
            <label><input type="checkbox" name="seating[]" value="banquet"><span>Banquet</span></label>
            <label><input type="checkbox" name="seating[]" value="cocktail"><span>Cocktail</span></label>
        </div></fieldset>

        <fieldset><legend>Catering Options</legend><div class="choice-pills">
            <label><input type="checkbox" name="catering[]" value="in-house"><span>In-house Catering</span></label>
            <label><input type="checkbox" name="catering[]" value="external"><span>External Caterers Allowed</span></label>
            <label><input type="checkbox" name="catering[]" value="alcohol"><span>Alcohol Allowed</span></label>
        </div></fieldset>
      </section>

      <section class="form-step" data-step="2">
        <h2>Photos &amp; Amenities</h2>
        <label for="photo-url">Photo URLs</label><div class="inline-field"><input id="photo-url" type="url" placeholder="https://your-photo-url.com/image.jpg"><button class="add-button" type="button">Add</button></div>
        
        <fieldset><legend>Amenities Available</legend><div class="choice-pills">
            <label><input type="checkbox" name="amenities[]" value="wifi"><span>WiFi</span></label>
            <label><input type="checkbox" name="amenities[]" value="parking"><span>Parking</span></label>
            <label><input type="checkbox" name="amenities[]" value="stage"><span>Stage</span></label>
            <label><input type="checkbox" name="amenities[]" value="air-conditioning"><span>Air Conditioning</span></label>
        </div></fieldset>
      </section>

      <section class="form-step" data-step="3">
        <h2>Packages</h2>
        <div class="package-box" id="package-container">
          <label>Existing & New Packages</label>
          
          <!-- Loop through any existing packages from the database -->
          <?php foreach ($existing_packages as$pkg): ?>
              <div class="package-entry" style="margin-top: 20px; padding-top: 20px; border-top: 1px dashed rgba(212, 165, 165, 0.3);">
                <input type="text" name="package_name[]" value="<?= htmlspecialchars($pkg['package_name']) ?>">
                <div class="two-fields">
                  <input type="number" name="package_price[]" value="<?= htmlspecialchars($pkg['price']) ?>">
                  <input type="text" name="package_desc[]" value="<?= htmlspecialchars($pkg['description']) ?>">
                </div>
                <input type="text" name="package_includes[]" value="<?= htmlspecialchars($pkg['includes']) ?>">
                <button type="button" class="remove-pkg" style="background: none; border: none; color: #c62828; cursor: pointer; font-size: 0.9rem; margin-top: 10px;">- Remove this package</button>
              </div>
          <?php endforeach; ?>

          <button class="add-button" type="button" id="add-package-btn" style="margin-top: 20px;">+ &nbsp; Add New Package</button>
        </div>
      </section>

      <section class="form-step" data-step="4">
        <h2>Rules &amp; Policies</h2>
        <label for="venue-rules">Venue Rules</label>
        <textarea id="venue-rules" name="venue-rules" placeholder="e.g. No smoking..."></textarea>
        
        <label for="cancellation-policy">Cancellation Policy</label>
        <textarea id="cancellation-policy" name="cancellation-policy" placeholder="e.g. Full refund..."><?= htmlspecialchars($venue['cancellation_policy']) ?></textarea>
      </section>

      <div class="form-actions">
        <a href="ownerdashboard.php#venues" style="padding: 15px 30px; text-decoration: none; color: #333; font-weight: bold;">Cancel Edit</a>
        <div style="flex-grow: 1; text-align: right;">
            <button class="previous" type="button" disabled>← &nbsp; Previous</button>
            <button class="next" type="submit">Update Venue <span aria-hidden="true">→</span></button>
        </div>
      </div>
    </form>
  </main>

<script>
      document.addEventListener('DOMContentLoaded', function() {
          // --- 1. MULTI-STEP FORM NAVIGATION ---
          const steps = document.querySelectorAll('.form-step');
          const indicators = document.querySelectorAll('.steps li');
          const nextBtn = document.querySelector('.next');
          const prevBtn = document.querySelector('.previous');
          let currentStep = 0;

          function updateFormView() {
              steps.forEach((step, index) => {
                  step.style.display = index === currentStep ? 'block' : 'none';
              });
              indicators.forEach((indicator, index) => {
                  indicator.classList.toggle('current', index === currentStep);
              });
              
              prevBtn.disabled = currentStep === 0;
              
              if (currentStep === steps.length - 1) {
                  nextBtn.innerHTML = 'Update Venue <span aria-hidden="true">→</span>';
                  nextBtn.type = 'submit';
              } else {
                  nextBtn.innerHTML = 'Next <span aria-hidden="true">→</span>';
                  nextBtn.type = 'button';
              }
          }

         nextBtn.addEventListener('click', (e) => {
              if (currentStep < steps.length - 1) {
                  e.preventDefault();
                  
                  // Force HTML5 validation on current step before moving
                  const currentInputs = steps[currentStep].querySelectorAll('input[required], textarea[required]');
                  let allValid = true;
                  currentInputs.forEach(input => {
                      if (!input.checkValidity()) {
                          input.classList.add('invalid-field');
                          allValid = false;
                      }
                  });
                  
                  if (allValid) {
                      currentStep++;
                      updateFormView();
                  } else {
                      alert("Please fill in all required fields marked with a red border before continuing.");
                  }
              } else {
                  // FINAL STEP: Show the warning before actually submitting to the database
                  if (!confirm('Are you sure? Your venue will go offline until approved by an Admin.')) {
                      e.preventDefault(); // Stop the submission if they click "Cancel"
                  }
              }
          });

          prevBtn.addEventListener('click', () => {
              if (currentStep > 0) {
                  currentStep--;
                  updateFormView();
              }
          });

          updateFormView(); // Initialize first view

          // --- 2. DYNAMIC PACKAGE CLONING ---
          const addBtn = document.getElementById('add-package-btn');
          const container = document.getElementById('package-container');
          
          // Attach remove event to existing packages loaded from PHP
          document.querySelectorAll('.remove-pkg').forEach(btn => {
              btn.addEventListener('click', function() {
                  this.parentElement.remove();
              });
          });

          if(addBtn && container) {
              addBtn.addEventListener('click', function() {
                  const newEntry = document.createElement('div');
                  newEntry.className = 'package-entry';
                  newEntry.style.marginTop = '20px';
                  newEntry.style.paddingTop = '20px';
                  newEntry.style.borderTop = '1px dashed rgba(212, 165, 165, 0.3)';
                  
                  newEntry.innerHTML = `
                    <input type="text" name="package_name[]" placeholder="Package name (e.g. Gold)">
                    <div class="two-fields">
                      <input type="number" name="package_price[]" placeholder="Price ($)">
                      <input type="text" name="package_desc[]" placeholder="Short description">
                    </div>
                    <input type="text" name="package_includes[]" placeholder="Includes (comma-separated)">
                    <button type="button" class="remove-pkg" style="background: none; border: none; color: #c62828; cursor: pointer; font-size: 0.9rem; margin-top: 10px;">- Remove this package</button>
                  `;
                  
                  container.insertBefore(newEntry, addBtn);
                  
                  newEntry.querySelector('.remove-pkg').addEventListener('click', function() {
                      newEntry.remove();
                  });
              });
          }

          // --- 3. REAL-TIME VALIDATION COLORS ---
          const requiredInputs = document.querySelectorAll('input[required], textarea[required]');
          requiredInputs.forEach(input => {
              input.addEventListener('input', function() {
                  if (this.checkValidity()) {
                      this.classList.remove('invalid-field');
                      this.classList.add('valid-field');
                  } else {
                      this.classList.remove('valid-field');
                      this.classList.add('invalid-field');
                  }
              });
          });
      });
    </script>
</body>
</html>
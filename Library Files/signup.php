<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require 'db_config.php';

// Initialize variables
$error_message = '';
$name = $username = $email = ''; // To retain form values after submission

// Process form data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate input
    $name = htmlspecialchars(trim($_POST['name']));
    $username = htmlspecialchars(trim($_POST['username']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $role = 'user'; // Set default role to 'user'

    // Validate input
    if (empty($name) || empty($username) || empty($email) || empty($password)) {
        $error_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } else {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert data into the database using prepared statements
        $sql = "INSERT INTO users (name, username, email, password, role) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("sssss", $name, $username, $email, $hashed_password, $role);

        try {
            if ($stmt->execute()) {
                // Redirect to index.php after successful signup
                header("Location: index.php");
                exit(); // Ensure no further code is executed after redirection
            }
        } catch (mysqli_sql_exception $e) {
            // Handle duplicate email error
            if ($e->getCode() == 1062) { // 1062 is the MySQL error code for duplicate entry
                $error_message = "The email address is already registered. Please use a different email.";
            } else {
                $error_message = "An error occurred during registration. Please try again later.";
            }
        }

        $stmt->close();
    }
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="assets/bootstrap.css" />
    <title>Sign Up - Honest Preparatory Library</title>
    <style>
      body,
      html {
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: hidden; /* Prevent scrolling */
      }

      .logo-container {
        background-color: #ffffff; /* White background for the logo */
        padding: 10px 20px; /* Add some padding */
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); /* Add a subtle shadow */
        z-index: 2; /* Ensure the logo is above other content */
      }

      .logo {
        width: 150px;
      }

      .image-container {
        position: absolute; /* Position the image absolutely */
        top: 0;
        left: 0;
        width: 100vw; /* Full viewport width */
        height: 100vh; /* Full viewport height */
        z-index: -1; /* Place the image behind other content */
      }

      .image-container img {
        width: 100%; /* Make the image fill the container width */
        height: 100%; /* Make the image fill the container height */
        object-fit: cover; /* Crop the image to fit the container */
      }

      .signup-container {
        position: absolute; /* Position the signup form absolutely */
        top: 50%; /* Center vertically */
        left: 50%; /* Center horizontally */
        transform: translate(-50%, -50%); /* Center the form */
        z-index: 1; /* Place the form above the image */
        background: rgba(255, 255, 255, 0.8); /* Semi-transparent white background */
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); /* Add a subtle shadow */
        text-align: center; /* Center align text */
      }

      .signup-container h2 {
        margin-bottom: 20px; /* Space below the heading */
      }

      .signup-container .form-control {
        margin-bottom: 15px; /* Space between form fields */
      }

      .login-link {
        margin-top: 15px; /* Space above the login link */
        font-size: 14px; /* Smaller font size */
      }

      .login-link a {
        color: #004aad; /* Match the primary color */
        text-decoration: none; /* Remove underline */
        font-weight: bold; /* Make it bold */
      }

      .login-link a:hover {
        text-decoration: underline; /* Add underline on hover */
      }

      footer {
        position: absolute; /* Position the footer absolutely */
        bottom: 0; /* Place it at the bottom */
        width: 100%; /* Full width */
        background-color: #004aad;
        color: white;
        text-align: center;
        padding: 10px 0;
        z-index: 1; /* Place the footer above the image */
      }
    </style>
  </head>
  <body>
    <!-- Logo Section -->
    <div class="logo-container">
      <img
        class="logo"
        src="images/logo.png"
        alt="Logo"
      />
    </div>

    <!-- Background Image -->
    <div class="image-container">
      <img src="images/RGU Library.jpg" alt="RGU Library" />
    </div>

    <!-- Signup Form -->
    <div class="signup-container">
      <h2>Sign Up</h2>
      <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" role="alert">
          <?php echo $error_message; ?>
        </div>
      <?php endif; ?>
      <form action="signup.php" method="POST">
        <div class="mb-3">
          <input
            type="text"
            class="form-control"
            id="name"
            name="name"
            placeholder="Full Name"
            value="<?php echo $name; ?>"
            required
          />
        </div>
        <div class="mb-3">
          <input
            type="email"
            class="form-control"
            id="email"
            name="email"
            placeholder="Email"
            value="<?php echo $email; ?>"
            required
          />
        </div>
        <div class="mb-3">
          <input
            type="text"
            class="form-control"
            id="username"
            name="username"
            placeholder="Username"
            value="<?php echo $username; ?>"
            required
          />
        </div>
        <div class="mb-3">
          <input
            type="password"
            class="form-control"
            id="password"
            name="password"
            placeholder="Password"
            required
          />
        </div>
        <button type="submit" class="btn btn-primary">Sign Up</button>
      </form>
      <div class="login-link">
        Already have an account? <a href="index.php">Login here</a>
      </div>
    </div>

    <!-- Footer -->
    <footer>
    <p>
    Connect with me: 
    <a href="https://www.linkedin.com/in/emmanuel-acquah-5b4577146/" 
       target="_blank" 
       class="btn btn-linkedin" 
       style="background-color: #0077B5; color: white;">
       <i class="fab fa-linkedin-in"></i> LinkedIn
    </a>
</p>
        <p>&copy; <?php echo date('Y'); ?> Honest Preparatory Library</p>
    </footer>
  </body>
</html>
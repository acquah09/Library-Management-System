<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db_config.php';

// redirect function
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit();
    }
}

// redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        redirect('admin.php');
    } else {
        redirect('user.php');
    }
}

// Check for login errors
$error = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid_credentials':
            $error = 'Invalid username or password.';
            break;
        case 'not_found':
            $error = 'User not found.';
            break;
        case 'access_denied':
            $error = 'You do not have permission to access that page.';
            break;
        case 'not_logged_in':
            $error = 'You must login to access this page.';
            break;
        case 'invalid_admin':
            $error = 'You do not have administrator privileges. Please select "User" role.';
            break;
    }
    }


// Check for signup success
$success = '';
if (isset($_GET['signup_success'])) {
    $success = 'Your account has been created successfully! You can now login.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="assets/bootstrap.css" />
    <title>Honest Preparatory Library</title>
    <style>
      body,
      html {
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: hidden; 
      }

      .logo-container {
        background-color: #ffffff; 
        padding: 10px 20px; 
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); 
        z-index: 2; 
      }

      .logo {
        width: 150px;
      }

      .image-container {
        position: absolute; 
        top: 0;
        left: 0;
        width: 100vw; 
        height: 100vh; 
        z-index: -1; 
      }

      .image-container img {
        width: 100%; 
        height: 100%; 
        object-fit: cover; 
      }

      .login-container {
        position: absolute; 
        top: 50%; 
        left: 50%; 
        transform: translate(-50%, -50%); 
        z-index: 1; 
        background: rgba(
          255,
          255,
          255,
          0.8
        ); 
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.2); 
        text-align: center; 
        max-width: 450px;
        width: 90%;
      }

      .signup-link {
        margin-top: 15px; 
        font-size: 14px; 
      }

      .signup-link a {
        color: #004aad; 
        text-decoration: none; 
        font-weight: bold;
      }

      .signup-link a:hover {
        text-decoration: underline; 
      }

      .error-message {
        color: #dc3545;
        margin-bottom: 15px;
      }

      footer {
        position: absolute; 
        bottom: 0; 
        width: 100%; 
        background-color: #004aad;
        color: white;
        text-align: center;
        padding: 10px 0;
        z-index: 1; 
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
      <img src="images/0408_N16_htwebcrp.avif" alt="Library" />
    </div>

    <!-- Login Form -->
    <div class="login-container">
      <h2 class="text-center">Honest Preparatory Library</h2>
      
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
      <?php endif; ?>
      
      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
      <?php endif; ?>
      
      <form action="authenticate.php" method="POST">
        <div class="mb-3">
          <label for="username" class="form-label">Username</label>
          <input
            type="text"
            class="form-control"
            id="username"
            name="username"
            required
          />
        </div>
        <div class="mb-3">
          <label for="password" class="form-label">Password</label>
          <input
            type="password"
            class="form-control"
            id="password"
            name="password"
            required
          />
        </div>
        <div class="mb-3">
          <label for="role" class="form-label">Role</label>
          <select class="form-select" id="role" name="role" required>
            <option value="user">User</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary w-100">Login</button>
      </form>
      <div class="signup-link">
        New user? <a href="signup.php">Sign up here</a>
      </div>
    </div>

  
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
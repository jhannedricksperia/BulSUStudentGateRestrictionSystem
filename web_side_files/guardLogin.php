<?php
session_start();
require_once 'config.php'; // Make sure this defines FIREBASE_DB_URL

function fetchFirebaseData($node) {
    $url = rtrim(FIREBASE_DB_URL, '/') . '/' . $node . '.json';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $guards = fetchFirebaseData('Guard');
    $valid = false;

    if ($guards) {
        foreach ($guards as $id => $guard) {
            if (
                isset($guard['Email'], $guard['Password']) &&
                $guard['Email'] === $email &&
                $guard['Password'] === $password
            ) {
                $_SESSION['guard'] = $guard;
                header("Location: guardHome.php");
                exit;
            }
        }
    }
    $error = "Invalid email or password!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Guard Login | BSU Gate System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    body {
        font-family: 'Poppins', sans-serif;
        background-color: #870000;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
        color: white;
    }

    .login-container {
        background: white;
        color: #333;
        padding: 40px 35px;
        border-radius: 15px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        text-align: center;
        width: 350px;
    }

    .icon {
        font-size: 55px;
        color: #f9a825;
        margin-bottom: 10px;
    }

    h2 {
        color: #870000;
        margin-bottom: 25px;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 1px;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"] {
        width: 100%;
        padding: 12px;
        margin: 10px 0;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 15px;
    }

    button {
        width: 100%;
        background-color: #f9a825;
        border: none;
        color: #870000;
        padding: 12px;
        font-size: 16px;
        font-weight: 700;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    button:hover {
        background-color: #c62828;
        color: white;
    }

    .back {
        display: inline-block;
        margin-top: 15px;
        color: #870000;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        transition: color 0.3s;
    }

    .back:hover {
        color: #f9a825;
    }

    .error {
        color: #c62828;
        font-size: 14px;
        margin-top: 10px;
    }

    footer {
        position: absolute;
        bottom: 15px;
        font-size: 0.85rem;
        opacity: 0.8;
        color: white;
    }
</style>
</head>
<body>

<div class="login-container">
    <i class="fas fa-user-shield icon"></i>
    <h2>Guard Login</h2>

    <form method="POST" action="">
        <input type="email" name="email" placeholder="Enter Email" required>
        <input type="password" name="password" placeholder="Enter Password" required>
        <button type="submit">Login</button>
    </form>

    <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>

    <a href="index.php" class="back"><i class="fas fa-arrow-left"></i> Back to Home</a>
</div>

<footer>
    &copy; <?php echo date("Y"); ?> Bulacan State University - Student Gate Restriction System
</footer>

</body>
</html>

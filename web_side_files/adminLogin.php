<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Default credentials
    if ($username === "admin" && $password === "admin123") {
        $_SESSION['admin'] = "Administrator";
        header("Location: adminHome.php");
        exit;
    } else {
        $error = "Invalid username or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | BSU Gate System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #870000; /* Deep BSU Red */
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-container {
            background: #fff;
            padding: 40px 35px;
            border-radius: 15px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.25);
            text-align: center;
            width: 360px;
        }

        .logo {
            width: 90px;
            height: auto;
            border-radius: 50%;
            margin-bottom: 10px;
        }

        h2 {
            color: #870000;
            margin-bottom: 25px;
            text-transform: uppercase;
            font-weight: 700;
            font-size: 1.5rem;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #bbb;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
            transition: 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #ffcc00;
            box-shadow: 0 0 6px #ffcc00;
        }

        button {
            width: 100%;
            background-color: #ffcc00;
            border: none;
            color: #000;
            padding: 12px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s, color 0.3s;
        }

        button:hover {
            background-color: #870000;
            color: #fff;
        }

        .back {
            display: inline-block;
            margin-top: 18px;
            color: #870000;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .back:hover {
            color: #ffcc00;
        }

        .error {
            color: #870000;
            background-color: #ffe6e6;
            border: 1px solid #ffcccc;
            padding: 8px;
            border-radius: 6px;
            font-size: 14px;
            margin-top: 12px;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <img src="BSUU.webp" alt="BSU Logo" class="logo">
        <h2>Admin Login</h2>

        <form method="POST" action="">
            <input type="text" name="username" placeholder="Enter Username" required>
            <input type="password" name="password" placeholder="Enter Password" required>
            <button type="submit">Login</button>
        </form>

        <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>

        <a href="index.php" class="back"><i class="fas fa-arrow-left"></i> Back to Home</a>
    </div>

</body>
</html>

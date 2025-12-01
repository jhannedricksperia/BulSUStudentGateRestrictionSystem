<?php
session_start();
include('config.php'); // contains FIREBASE_DB_URL

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        // Fetch Staff records from Firebase
        $json = file_get_contents(FIREBASE_DB_URL . 'Staff.json');
        $data = json_decode($json, true);

        $found = false;

        if ($data && is_array($data)) {
            foreach ($data as $id => $staff) {
                // Ensure correct key capitalization
                if (
                    isset($staff['Email'], $staff['Password']) &&
                    $staff['Email'] === $email &&
                    $staff['Password'] === $password
                ) {
                    $found = true;
                    $_SESSION['staff'] = [
                        'id' => $id,
                        'FirstName' => $staff['FirstName'] ?? '',
                        'LastName' => $staff['LastName'] ?? '',
                        'Email' => $staff['Email'] ?? '',
                        'Campus' => $staff['Campus'] ?? 'Unknown'
                    ];
                    header('Location: staffHome.php');
                    exit;
                }
            }
        }

        if (!$found) {
            $message = "Invalid email or password!";
        }
    } else {
        $message = "Please fill in all fields!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login | Gate Management System</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #870000;
            margin: 0;
            padding: 0;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .login-box {
            background: #fff;
            color: #333;
            padding: 40px;
            border-radius: 12px;
            width: 350px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .login-box img {
            width: 80px;
            margin-bottom: 15px;
        }

        h2 {
            color: #870000;
            margin-bottom: 25px;
        }

        input {
            width: 100%;
            padding: 10px;
            margin: 8px 0 16px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }

        button {
            width: 100%;
            background-color: #870000;
            color: white;
            border: none;
            padding: 12px;
            font-size: 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background-color: #b30000;
        }

        .message {
            color: red;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .footer {
            margin-top: 15px;
            font-size: 14px;
        }

        .footer a {
            color: #870000;
            text-decoration: none;
            font-weight: bold;
        }

        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <img src="BSUU.webp" alt="BSU Logo">
        <h2>Staff Login</h2>

        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="email" name="email" placeholder="Enter Email" required>
            <input type="password" name="password" placeholder="Enter Password" required>
            <button type="submit">Login</button>
        </form>

        <div class="footer">
            <p><a href="index.php">← Back to Home</a></p>
        </div>
    </div>
</body>
</html>

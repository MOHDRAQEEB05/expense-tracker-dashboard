<?php

session_start();

require "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if ($email === "" || $password === "") {

        $message = "Please enter your email and password.";

    } else {

        $stmt = $conn->prepare(
            "SELECT id, name, email, password
             FROM users
             WHERE email = ?"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            // Check the hashed password
            if (password_verify($password, $user["password"])) {

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["user_name"] = $user["name"];
                $_SESSION["user_email"] = $user["email"];

                header("Location: index.php");
                exit;

            } else {

                $message = "Invalid email or password.";
            }

        } else {

            $message = "Invalid email or password.";
        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Login - Expense Tracker</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #0f172a;
    min-height: 100vh;

    display: flex;
    justify-content: center;
    align-items: center;
}

.container {
    width: 400px;
    max-width: 90%;
    background: white;

    padding: 35px;

    border-radius: 15px;

    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.logo {
    text-align: center;
    font-size: 45px;
}

h1 {
    text-align: center;
    margin: 10px 0;
}

.subtitle {
    text-align: center;
    color: #64748b;
    margin-bottom: 25px;
}

.message {
    background: #fee2e2;
    color: #b91c1c;

    padding: 10px;

    border-radius: 8px;

    margin-bottom: 15px;

    text-align: center;
}

.success {
    background: #dcfce7;
    color: #15803d;
}

label {
    display: block;

    margin-top: 15px;
    margin-bottom: 6px;

    font-weight: bold;
}

input {
    width: 100%;

    padding: 12px;

    border: 1px solid #cbd5e1;

    border-radius: 8px;

    font-size: 15px;
}

button {
    width: 100%;

    padding: 13px;

    margin-top: 25px;

    border: none;

    border-radius: 8px;

    background: #2563eb;

    color: white;

    font-size: 16px;

    cursor: pointer;
}

button:hover {
    background: #1d4ed8;
}

.register {
    text-align: center;

    margin-top: 20px;
}

.register a {
    color: #2563eb;

    text-decoration: none;

    font-weight: bold;
}

</style>

</head>

<body>

<div class="container">

    <div class="logo">
        💰
    </div>

    <h1>Welcome Back</h1>

    <p class="subtitle">
        Login to your Expense Tracker
    </p>


    <?php if ($message !== ""): ?>

        <div class="message">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>


    <?php if (isset($_GET["registered"])): ?>

        <div class="message success">
            Account created successfully! Please login.
        </div>

    <?php endif; ?>


    <form method="POST">

        <label>Email</label>

        <input
            type="email"
            name="email"
            placeholder="Enter your email"
            required
        >


        <label>Password</label>

        <input
            type="password"
            name="password"
            placeholder="Enter your password"
            required
        >


        <button type="submit">
            Login
        </button>

    </form>


    <div class="register">

        Don't have an account?

        <a href="register.php">
            Create Account
        </a>

    </div>

</div>

</body>

</html>
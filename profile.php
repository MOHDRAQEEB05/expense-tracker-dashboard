<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require "db.php";

$user_id = $_SESSION["user_id"];
$message = "";
$error = "";


/* UPDATE PROFILE */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_profile"])) {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);

    if ($name === "" || $email === "") {

        $error = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        /* Check whether email belongs to another user */

        $check_stmt = $conn->prepare(
            "SELECT id FROM users
             WHERE email = ? AND id != ?"
        );

        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();

        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {

            $error = "This email is already being used.";

        } else {

            $update_stmt = $conn->prepare(
                "UPDATE users
                 SET name = ?, email = ?
                 WHERE id = ?"
            );

            $update_stmt->bind_param(
                "ssi",
                $name,
                $email,
                $user_id
            );

            if ($update_stmt->execute()) {

                $_SESSION["user_name"] = $name;
                $_SESSION["user_email"] = $email;

                $message = "Profile updated successfully.";

            } else {

                $error = "Something went wrong. Please try again.";
            }

            $update_stmt->close();
        }

        $check_stmt->close();
    }
}


/* CHANGE PASSWORD */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["change_password"])) {

    $current_password = $_POST["current_password"];
    $new_password = $_POST["new_password"];
    $confirm_password = $_POST["confirm_password"];


    if (
        $current_password === "" ||
        $new_password === "" ||
        $confirm_password === ""
    ) {

        $error = "Please fill in all password fields.";

    } elseif (strlen($new_password) < 6) {

        $error = "New password must be at least 6 characters.";

    } elseif ($new_password !== $confirm_password) {

        $error = "New passwords do not match.";

    } else {

        /* Get current password */

        $stmt = $conn->prepare(
            "SELECT password
             FROM users
             WHERE id = ?"
        );

        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $result = $stmt->get_result();

        $user = $result->fetch_assoc();

        $stmt->close();


        if ($user && password_verify($current_password, $user["password"])) {

            $hashed_password = password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );

            $update_password = $conn->prepare(
                "UPDATE users
                 SET password = ?
                 WHERE id = ?"
            );

            $update_password->bind_param(
                "si",
                $hashed_password,
                $user_id
            );

            if ($update_password->execute()) {

                $message = "Password changed successfully.";

            } else {

                $error = "Could not change password.";
            }

            $update_password->close();

        } else {

            $error = "Current password is incorrect.";
        }
    }
}


/* GET USER INFORMATION */

$stmt = $conn->prepare(
    "SELECT name, email
     FROM users
     WHERE id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

$stmt->close();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Profile</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
}

.container {
    max-width: 700px;
    margin: 50px auto;
    padding: 20px;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 14px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

h1 {
    margin-bottom: 25px;
}

h2 {
    margin-top: 0;
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
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 15px;
}

button {
    margin-top: 20px;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    background: #2563eb;
    color: white;
    cursor: pointer;
    font-size: 15px;
}

button:hover {
    background: #1d4ed8;
}

.message {
    background: #dcfce7;
    color: #166534;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.back {
    display: inline-block;
    margin-top: 20px;
    text-decoration: none;
    color: #2563eb;
}

</style>

</head>

<body>

<div class="container">

    <h1>👤 My Profile</h1>

    <?php if ($message !== ""): ?>

        <div class="message">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <!-- PROFILE -->

    <div class="card">

        <h2>👤 Account Information</h2>

        <form method="POST">

            <label>Name</label>

            <input
                type="text"
                name="name"
                value="<?php echo htmlspecialchars($user["name"]); ?>"
                required
            >


            <label>Email</label>

            <input
                type="email"
                name="email"
                value="<?php echo htmlspecialchars($user["email"]); ?>"
                required
            >


            <button type="submit" name="update_profile">
                Save Profile
            </button>

        </form>

    </div>


    <!-- PASSWORD -->

    <div class="card">

        <h2>🔐 Change Password</h2>

        <form method="POST">

            <label>Current Password</label>

            <input
                type="password"
                name="current_password"
                required
            >


            <label>New Password</label>

            <input
                type="password"
                name="new_password"
                minlength="6"
                required
            >


            <label>Confirm New Password</label>

            <input
                type="password"
                name="confirm_password"
                minlength="6"
                required
            >


            <button type="submit" name="change_password">
                Change Password
            </button>

        </form>

    </div>


    <a class="back" href="index.php">
        ← Back to Dashboard
    </a>

</div>

</body>

</html>
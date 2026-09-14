<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $user_id = $_SESSION["user_id"];

    $title = trim($_POST["title"]);
    $amount = $_POST["amount"];
    $category = $_POST["category"];
    $expense_date = $_POST["expense_date"];
    $description = trim($_POST["description"]);

    $sql = "INSERT INTO expenses
            (user_id, title, amount, category, expense_date, description)
            VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "isdsss",
        $user_id,
        $title,
        $amount,
        $category,
        $expense_date,
        $description
    );

    if ($stmt->execute()) {

        header("Location: index.php");
        exit;

    } else {

        echo "Error adding expense: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}

?>
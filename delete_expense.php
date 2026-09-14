<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require "db.php";

if (isset($_GET["id"])) {

    $id = intval($_GET["id"]);
    $user_id = $_SESSION["user_id"];

    $stmt = $conn->prepare(
        "DELETE FROM expenses
         WHERE id = ? AND user_id = ?"
    );

    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();

    $stmt->close();
}

$conn->close();

header("Location: index.php");
exit;

?>
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require "db.php";

$user_id = $_SESSION["user_id"];

if (!isset($_GET["id"]) && !isset($_POST["id"])) {
    header("Location: index.php");
    exit;
}

$id = isset($_POST["id"])
    ? intval($_POST["id"])
    : intval($_GET["id"]);

$message = "";


/* UPDATE EXPENSE */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"]);
    $amount = $_POST["amount"];
    $category = $_POST["category"];
    $expense_date = $_POST["expense_date"];
    $description = trim($_POST["description"]);

    $sql = "UPDATE expenses
            SET title = ?,
                amount = ?,
                category = ?,
                expense_date = ?,
                description = ?
            WHERE id = ? AND user_id = ?";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "sdsssii",
        $title,
        $amount,
        $category,
        $expense_date,
        $description,
        $id,
        $user_id
    );

    if ($stmt->execute()) {

        $stmt->close();
        $conn->close();

        header("Location: index.php");
        exit;

    } else {

        $message = "Error updating expense.";
    }

    $stmt->close();
}


/* GET EXPENSE */

$stmt = $conn->prepare(
    "SELECT id, title, amount, category, expense_date, description
     FROM expenses
     WHERE id = ? AND user_id = ?"
);

$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    $stmt->close();
    $conn->close();

    header("Location: index.php");
    exit;
}

$expense = $result->fetch_assoc();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Expense</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f8;
        }

        .container {
            max-width: 650px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        h1 {
            margin-top: 0;
        }

        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 6px;
            font-weight: bold;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #ddd;
            border-radius: 7px;
            font-size: 15px;
        }

        textarea {
            resize: vertical;
        }

        button {
            margin-top: 20px;
            padding: 12px 20px;
            border: none;
            border-radius: 7px;
            background: #2563eb;
            color: white;
            font-size: 15px;
            cursor: pointer;
        }

        .back {
            display: inline-block;
            margin-left: 10px;
            text-decoration: none;
            color: #555;
        }

        .message {
            color: red;
            margin-bottom: 10px;
        }

    </style>

</head>

<body>

<div class="container">

    <h1>✏️ Edit Expense</h1>

    <?php if ($message !== ""): ?>
        <div class="message">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="id"
            value="<?php echo $expense["id"]; ?>"
        >

        <label>Title</label>

        <input
            type="text"
            name="title"
            value="<?php echo htmlspecialchars($expense["title"]); ?>"
            required
        >

        <label>Amount</label>

        <input
            type="number"
            step="0.01"
            name="amount"
            value="<?php echo htmlspecialchars($expense["amount"]); ?>"
            required
        >

        <label>Category</label>

        <select name="category" required>

            <option value="Food"
                <?php if ($expense["category"] === "Food") echo "selected"; ?>>
                Food
            </option>

            <option value="Travel"
                <?php if ($expense["category"] === "Travel") echo "selected"; ?>>
                Travel
            </option>

            <option value="Shopping"
                <?php if ($expense["category"] === "Shopping") echo "selected"; ?>>
                Shopping
            </option>

            <option value="Bills"
                <?php if ($expense["category"] === "Bills") echo "selected"; ?>>
                Bills
            </option>

            <option value="Education"
                <?php if ($expense["category"] === "Education") echo "selected"; ?>>
                Education
            </option>

            <option value="Other"
                <?php if ($expense["category"] === "Other") echo "selected"; ?>>
                Other
            </option>

        </select>

        <label>Date</label>

        <input
            type="date"
            name="expense_date"
            value="<?php echo htmlspecialchars($expense["expense_date"]); ?>"
            required
        >

        <label>Description</label>

        <textarea
            name="description"
            rows="4"
        ><?php echo htmlspecialchars($expense["description"]); ?></textarea>

        <button type="submit">
            Save Changes
        </button>

        <a class="back" href="index.php">
            Cancel
        </a>

    </form>

</div>

</body>

</html>
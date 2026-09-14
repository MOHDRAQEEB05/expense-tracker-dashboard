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

/* Current month */

$current_month = date("Y-m");


/* Save Budget */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $month = $_POST["month"];
    $amount = floatval($_POST["amount"]);

    if ($month === "" || $amount <= 0) {

        $error = "Please enter a valid month and budget amount.";

    } else {

        /*
         * INSERT new budget or update existing budget
         */

        $stmt = $conn->prepare(
            "INSERT INTO budgets (user_id, month, amount)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE amount = VALUES(amount)"
        );

        $stmt->bind_param(
            "isd",
            $user_id,
            $month,
            $amount
        );

        if ($stmt->execute()) {

            $message = "Budget saved successfully.";

        } else {

            $error = "Could not save budget.";
        }

        $stmt->close();
    }
}


/* Get selected month */

$selected_month = $_GET["month"] ?? $current_month;


/* Get Budget */

$budget_stmt = $conn->prepare(
    "SELECT amount
     FROM budgets
     WHERE user_id = ? AND month = ?"
);

$budget_stmt->bind_param(
    "is",
    $user_id,
    $selected_month
);

$budget_stmt->execute();

$budget_result = $budget_stmt->get_result();

$budget_data = $budget_result->fetch_assoc();

$budget = $budget_data["amount"] ?? 0;

$budget_stmt->close();


/* Get Monthly Spending */

$expense_stmt = $conn->prepare(
    "SELECT SUM(amount) AS total
     FROM expenses
     WHERE user_id = ?
     AND DATE_FORMAT(expense_date, '%Y-%m') = ?"
);

$expense_stmt->bind_param(
    "is",
    $user_id,
    $selected_month
);

$expense_stmt->execute();

$expense_result = $expense_stmt->get_result();

$expense_data = $expense_result->fetch_assoc();

$spent = $expense_data["total"] ?? 0;

$expense_stmt->close();


/* Calculate Remaining */

$remaining = $budget - $spent;


/* Calculate Percentage */

if ($budget > 0) {

    $percentage = ($spent / $budget) * 100;

} else {

    $percentage = 0;
}


/* Limit percentage for progress bar */

$progress = min($percentage, 100);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Monthly Budget</title>

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
    max-width: 750px;
    margin: 40px auto;
    padding: 20px;
}

h1 {
    margin-bottom: 25px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 14px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.stat {
    padding: 20px;
    border-radius: 10px;
    background: #f8fafc;
}

.stat h3 {
    margin-top: 0;
    color: #64748b;
}

.stat h2 {
    margin-bottom: 0;
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
    font-size: 15px;
    cursor: pointer;
}

button:hover {
    background: #1d4ed8;
}

.progress-container {
    width: 100%;
    height: 20px;
    background: #e5e7eb;
    border-radius: 20px;
    overflow: hidden;
    margin-top: 15px;
}

.progress {
    height: 100%;
    width: <?php echo $progress; ?>%;
    background: #2563eb;
}

.warning {
    color: #dc2626;
    font-weight: bold;
}

.good {
    color: #16a34a;
    font-weight: bold;
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
    margin-top: 10px;
    text-decoration: none;
    color: #2563eb;
}

@media (max-width: 700px) {

    .stats {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<div class="container">

    <h1>💰 Monthly Budget</h1>


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


    <!-- SET BUDGET -->

    <div class="card">

        <h2>Set Your Budget</h2>

        <form method="POST">

            <label>Month</label>

            <input
                type="month"
                name="month"
                value="<?php echo htmlspecialchars($selected_month); ?>"
                required
            >


            <label>Budget Amount (₹)</label>

            <input
                type="number"
                name="amount"
                step="0.01"
                min="1"
                placeholder="Example: 10000"
                required
            >


            <button type="submit">
                💾 Save Budget
            </button>

        </form>

    </div>


    <!-- BUDGET STATS -->

    <div class="card">

        <h2>
            📊 <?php echo htmlspecialchars($selected_month); ?>
        </h2>

        <div class="stats">

            <div class="stat">

                <h3>💰 Budget</h3>

                <h2>
                    ₹<?php echo number_format($budget, 2); ?>
                </h2>

            </div>


            <div class="stat">

                <h3>💸 Spent</h3>

                <h2>
                    ₹<?php echo number_format($spent, 2); ?>
                </h2>

            </div>


            <div class="stat">

                <h3>💵 Remaining</h3>

                <h2 class="<?php echo $remaining < 0 ? 'warning' : 'good'; ?>">

                    ₹<?php echo number_format($remaining, 2); ?>

                </h2>

            </div>

        </div>


        <?php if ($budget > 0): ?>

            <p>
                <strong>
                    <?php echo number_format($percentage, 1); ?>%
                </strong>
                of your budget has been used.
            </p>

            <div class="progress-container">

                <div class="progress"></div>

            </div>

        <?php else: ?>

            <p>
                Set a budget to see your spending progress.
            </p>

        <?php endif; ?>


    </div>


    <a class="back" href="index.php">
        ← Back to Dashboard
    </a>

</div>

</body>

</html>
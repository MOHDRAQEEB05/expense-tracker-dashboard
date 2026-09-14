 <?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require "db.php";

$user_id = $_SESSION["user_id"];


/* Total Spending */

$total_stmt = $conn->prepare(
    "SELECT SUM(amount) AS total
     FROM expenses
     WHERE user_id = ?"
);

$total_stmt->bind_param("i", $user_id);
$total_stmt->execute();

$total_result = $total_stmt->get_result();
$total = $total_result->fetch_assoc()["total"] ?? 0;

$total_stmt->close();


/* Average Expense */

$average_stmt = $conn->prepare(
    "SELECT AVG(amount) AS average
     FROM expenses
     WHERE user_id = ?"
);

$average_stmt->bind_param("i", $user_id);
$average_stmt->execute();

$average_result = $average_stmt->get_result();
$average = $average_result->fetch_assoc()["average"] ?? 0;

$average_stmt->close();


/* Spending by Category */

$category_stmt = $conn->prepare(
    "SELECT category, SUM(amount) AS total
     FROM expenses
     WHERE user_id = ?
     GROUP BY category
     ORDER BY total DESC"
);

$category_stmt->bind_param("i", $user_id);
$category_stmt->execute();

$category_result = $category_stmt->get_result();

$categories = [];
$category_totals = [];

while ($row = $category_result->fetch_assoc()) {

    $categories[] = $row["category"];
    $category_totals[] = $row["total"];

}

$category_stmt->close();


/* Monthly Spending */

$month_stmt = $conn->prepare(
    "SELECT
        DATE_FORMAT(expense_date, '%Y-%m') AS month,
        SUM(amount) AS total
     FROM expenses
     WHERE user_id = ?
     GROUP BY month
     ORDER BY month"
);

$month_stmt->bind_param("i", $user_id);
$month_stmt->execute();

$month_result = $month_stmt->get_result();

$months = [];
$monthly_totals = [];

while ($row = $month_result->fetch_assoc()) {

    $months[] = $row["month"];
    $monthly_totals[] = $row["total"];

}

$month_stmt->close();

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Expense Reports</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    body.dark-mode {
    background: #0f172a;
    color: #e5e7eb;
}

body.dark-mode .sidebar {
    background: #020617;
}

body.dark-mode .card,
body.dark-mode .chart-container {
    background: #1e293b;
    color: #e5e7eb;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
}

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 240px;
    height: 100vh;
    background: #111827;
    color: white;
    padding: 25px;
}

.sidebar h2 {
    margin-bottom: 35px;
}

.sidebar a {
    display: block;
    color: #d1d5db;
    text-decoration: none;
    padding: 14px 10px;
    margin-bottom: 8px;
    border-radius: 8px;
}

.sidebar a:hover {
    background: #2563eb;
    color: white;
}

.main {
    margin-left: 240px;
    padding: 35px;
}

h1 {
    margin-top: 0;
}

.cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin: 25px 0;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
}

.card h3 {
    color: #64748b;
    margin-top: 0;
}

.card h2 {
    margin-bottom: 0;
}

.chart-container {
    background: white;
    padding: 25px;
    border-radius: 12px;
    margin-top: 25px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
}

.chart-box {
    max-width: 700px;
    margin: auto;
}

@media (max-width: 900px) {

    .sidebar {
        display: none;
    }

    .main {
        margin-left: 0;
        padding: 20px;
    }

    .cards {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<!-- SIDEBAR -->

<div class="sidebar">

    <h2>💰 Expense Tracker</h2>

    <a href="index.php">🏠 Dashboard</a>

    <a href="index.php">💳 Expenses</a>

   <a href="reports.php">📊 Reports</a>
   
   <a href="budget.php">💰 Budget</a>

<a href="profile.php">👤 My Profile</a>

<a href="logout.php">🚪 Logout</a>
</div>


<!-- MAIN -->

<div class="main">
    <div style="display:flex; justify-content:flex-end; margin-bottom:20px;">

    <button
        onclick="toggleDarkMode()"
        style="
            width:auto;
            padding:10px 18px;
            border:none;
            border-radius:8px;
            cursor:pointer;
            background:#2563eb;
            color:white;
        "
    >
        🌙 Dark Mode
    </button>

</div>

    <h1>📊 Expense Reports</h1>

    <p>Analyze your spending and understand where your money goes.</p>


    <!-- CARDS -->

    <div class="cards">

        <div class="card">

            <h3>💰 Total Spending</h3>

            <h2>₹<?php echo number_format($total, 2); ?></h2>

        </div>


        <div class="card">

            <h3>📈 Average Expense</h3>

            <h2>₹<?php echo number_format($average, 2); ?></h2>

        </div>


        <div class="card">

            <h3>🏆 Top Category</h3>

            <h2>
                <?php
                echo !empty($categories)
                    ? htmlspecialchars($categories[0])
                    : "No Data";
                ?>
            </h2>

        </div>

    </div>


    <!-- CATEGORY CHART -->

    <div class="chart-container">

        <h2>💳 Spending by Category</h2>

        <div class="chart-box">

            <canvas id="categoryChart"></canvas>

        </div>

    </div>


    <!-- MONTHLY CHART -->

    <div class="chart-container">

        <h2>📅 Monthly Spending</h2>

        <div class="chart-box">

            <canvas id="monthlyChart"></canvas>

        </div>

    </div>

</div>


<script>

const categories = <?php echo json_encode($categories); ?>;

const categoryTotals = <?php echo json_encode($category_totals); ?>;

const months = <?php echo json_encode($months); ?>;

const monthlyTotals = <?php echo json_encode($monthly_totals); ?>;


/* Category Chart */

new Chart(
    document.getElementById("categoryChart"),
    {
        type: "doughnut",

        data: {
            labels: categories,

            datasets: [{
                data: categoryTotals
            }]
        },

        options: {
            responsive: true
        }
    }
);


/* Monthly Chart */

new Chart(
    document.getElementById("monthlyChart"),
    {
        type: "bar",

        data: {
            labels: months,

            datasets: [{
                label: "Monthly Spending",
                data: monthlyTotals
            }]
        },

        options: {
            responsive: true,

            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    }
);

</script>
<script>

function toggleDarkMode() {

    document.body.classList.toggle("dark-mode");

    if (document.body.classList.contains("dark-mode")) {

        localStorage.setItem("theme", "dark");

    } else {

        localStorage.setItem("theme", "light");

    }

}


if (localStorage.getItem("theme") === "dark") {

    document.body.classList.add("dark-mode");

}

</script>
</body>

</html>
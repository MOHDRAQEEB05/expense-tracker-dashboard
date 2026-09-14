<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require "db.php";

$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["user_name"];


/* =====================================================
   STATISTICS
===================================================== */

$total_stmt = $conn->prepare(
    "SELECT SUM(amount) AS total
     FROM expenses
     WHERE user_id = ?"
);

$total_stmt->bind_param("i", $user_id);
$total_stmt->execute();

$total_result = $total_stmt->get_result();
$total_data = $total_result->fetch_assoc();

$total_spending = $total_data["total"] ?? 0;

$total_stmt->close();


$count_stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM expenses
     WHERE user_id = ?"
);

$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();

$count_result = $count_stmt->get_result();
$count_data = $count_result->fetch_assoc();

$expense_count = $count_data["total"] ?? 0;

$count_stmt->close();


$average_stmt = $conn->prepare(
    "SELECT AVG(amount) AS average
     FROM expenses
     WHERE user_id = ?"
);

$average_stmt->bind_param("i", $user_id);
$average_stmt->execute();

$average_result = $average_stmt->get_result();
$average_data = $average_result->fetch_assoc();

$average_expense = $average_data["average"] ?? 0;

$average_stmt->close();


/* =====================================================
   CHART DATA - CATEGORY
===================================================== */

$chart_category_stmt = $conn->prepare(
    "SELECT category, SUM(amount) AS total
     FROM expenses
     WHERE user_id = ?
     GROUP BY category
     ORDER BY total DESC"
);

$chart_category_stmt->bind_param("i", $user_id);
$chart_category_stmt->execute();

$chart_category_result = $chart_category_stmt->get_result();

$chart_categories = [];
$chart_category_totals = [];

while ($row = $chart_category_result->fetch_assoc()) {

    $chart_categories[] = $row["category"];
    $chart_category_totals[] = (float)$row["total"];

}

$chart_category_stmt->close();


/* =====================================================
   CHART DATA - MONTHLY
===================================================== */

$chart_month_stmt = $conn->prepare(
    "SELECT
        DATE_FORMAT(expense_date, '%Y-%m') AS month,
        SUM(amount) AS total
     FROM expenses
     WHERE user_id = ?
     GROUP BY month
     ORDER BY month"
);

$chart_month_stmt->bind_param("i", $user_id);
$chart_month_stmt->execute();

$chart_month_result = $chart_month_stmt->get_result();

$chart_months = [];
$chart_month_totals = [];

while ($row = $chart_month_result->fetch_assoc()) {

    $chart_months[] = $row["month"];
    $chart_month_totals[] = (float)$row["total"];

}

$chart_month_stmt->close();


/* =====================================================
   SEARCH AND FILTERS
===================================================== */

$search = $_GET["search"] ?? "";
$filter_category = $_GET["category"] ?? "";
$filter_date = $_GET["date"] ?? "";
$filter_month = $_GET["month"] ?? "";

$sql = "SELECT *
        FROM expenses
        WHERE user_id = ?";

$params = [$user_id];
$types = "i";


if ($search !== "") {

    $sql .= " AND title LIKE ?";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $types .= "s";
}


if ($filter_category !== "") {

    $sql .= " AND category = ?";

    $params[] = $filter_category;
    $types .= "s";
}


if ($filter_date !== "") {

    $sql .= " AND expense_date = ?";

    $params[] = $filter_date;
    $types .= "s";
}


if ($filter_month !== "") {

    $sql .= " AND DATE_FORMAT(expense_date, '%Y-%m') = ?";

    $params[] = $filter_month;
    $types .= "s";
}


$sql .= " ORDER BY expense_date DESC, id DESC";

$stmt = $conn->prepare($sql);

$stmt->bind_param($types, ...$params);

$stmt->execute();

$result = $stmt->get_result();


/* =====================================================
   MONTHLY BUDGET
===================================================== */

$current_month = date("Y-m");


$budget_stmt = $conn->prepare(
    "SELECT amount
     FROM budgets
     WHERE user_id = ?
     AND month = ?"
);

$budget_stmt->bind_param(
    "is",
    $user_id,
    $current_month
);

$budget_stmt->execute();

$budget_result = $budget_stmt->get_result();

$budget_data = $budget_result->fetch_assoc();

$budget = $budget_data["amount"] ?? 0;

$budget_stmt->close();


/* =====================================================
   MONTHLY SPENDING
===================================================== */

$spent_stmt = $conn->prepare(
    "SELECT SUM(amount) AS total
     FROM expenses
     WHERE user_id = ?
     AND DATE_FORMAT(expense_date, '%Y-%m') = ?"
);

$spent_stmt->bind_param(
    "is",
    $user_id,
    $current_month
);

$spent_stmt->execute();

$spent_result = $spent_stmt->get_result();

$spent_data = $spent_result->fetch_assoc();

$monthly_spent = $spent_data["total"] ?? 0;

$spent_stmt->close();


/* =====================================================
   BUDGET CALCULATIONS
===================================================== */

$remaining_budget = $budget - $monthly_spent;


if ($budget > 0) {

    $budget_percentage = ($monthly_spent / $budget) * 100;

} else {

    $budget_percentage = 0;

}


$budget_progress = min($budget_percentage, 100);

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="google-site-verification" content="G9919kl9FEFFre6AHxIQko-TQ3K1dDxZRnVnl9oGuy4" />

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Expense Tracker Dashboard</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<style>

/* =====================================================
   GENERAL
===================================================== */

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family: Arial, Helvetica, sans-serif;

    background: #f5f7fb;

    color: #111827;

    transition: 0.3s;

}


/* =====================================================
   SIDEBAR
===================================================== */

.sidebar {

    position: fixed;

    left: 0;

    top: 0;

    width: 240px;

    height: 100vh;

    background: #111827;

    color: white;

    padding: 25px 20px;

}


.sidebar h2 {

    margin-top: 0;

    margin-bottom: 30px;

    text-align: center;

}


.menu {

    list-style: none;

    padding: 0;

    margin: 0;

}


.menu li {

    margin-bottom: 12px;

}


.menu a {

    display: block;

    padding: 12px 15px;

    text-decoration: none;

    color: #d1d5db;

    border-radius: 8px;

    transition: 0.2s;

}


.menu a:hover {

    background: #2563eb;

    color: white;

}


/* =====================================================
   MAIN
===================================================== */

.main {

    margin-left: 240px;

    padding: 30px;

}


/* =====================================================
   TOP BAR
===================================================== */

.topbar {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;

}


.topbar h1 {

    margin: 0;

}


.welcome {

    color: #6b7280;

}


/* =====================================================
   BUTTONS
===================================================== */

button,
.btn {

    border: none;

    padding: 10px 15px;

    border-radius: 8px;

    cursor: pointer;

    text-decoration: none;

    display: inline-block;

}


.primary {

    background: #2563eb;

    color: white;

}


.primary:hover {

    background: #1d4ed8;

}


.edit {

    background: #f59e0b;

    color: white;

}


.delete {

    background: #dc2626;

    color: white;

}


/* =====================================================
   CARDS
===================================================== */

.cards {

    display: grid;

    grid-template-columns: repeat(3, 1fr);

    gap: 20px;

    margin-bottom: 25px;

}


.card {

    background: white;

    padding: 22px;

    border-radius: 14px;

    box-shadow: 0 4px 15px rgba(0,0,0,0.06);

}


.card-title {

    color: #6b7280;

    font-size: 14px;

    margin-bottom: 10px;

}


.card-value {

    font-size: 26px;

    font-weight: bold;

}


.budget-good {

    color: #16a34a;

}


.budget-warning {

    color: #dc2626;

}


/* =====================================================
   CHARTS
===================================================== */

.chart-container {

    height: 320px;

    position: relative;

}


/* =====================================================
   BUDGET PROGRESS
===================================================== */

.progress-container {

    width: 100%;

    height: 18px;

    background: #e5e7eb;

    border-radius: 20px;

    overflow: hidden;

}


.progress-bar {

    height: 100%;

    background: #2563eb;

    border-radius: 20px;

    transition: width 0.3s;

}


/* =====================================================
   FILTER
===================================================== */

.filter-box {

    background: white;

    padding: 20px;

    border-radius: 14px;

    margin-bottom: 25px;

    box-shadow: 0 4px 15px rgba(0,0,0,0.06);

}


.filter-form {

    display: grid;

    grid-template-columns: 2fr 1fr 1fr 1fr auto auto;

    gap: 10px;

}


input,
select {

    width: 100%;

    padding: 10px;

    border: 1px solid #d1d5db;

    border-radius: 8px;

    font-size: 14px;

}


/* =====================================================
   TABLE
===================================================== */

.table-container {

    background: white;

    border-radius: 14px;

    padding: 20px;

    box-shadow: 0 4px 15px rgba(0,0,0,0.06);

    overflow-x: auto;

}


table {

    width: 100%;

    border-collapse: collapse;

}


th,
td {

    padding: 13px;

    text-align: left;

    border-bottom: 1px solid #e5e7eb;

}


th {

    background: #f9fafb;

    color: #374151;

}


.actions {

    display: flex;

    gap: 7px;

}


/* =====================================================
   ADD EXPENSE
===================================================== */

.add-expense {

    background: white;

    padding: 20px;

    border-radius: 14px;

    margin-bottom: 25px;

    box-shadow: 0 4px 15px rgba(0,0,0,0.06);

}


.expense-form {

    display: grid;

    grid-template-columns: repeat(2, 1fr);

    gap: 15px;

}


.expense-form textarea {

    width: 100%;

    min-height: 80px;

    padding: 10px;

    border: 1px solid #d1d5db;

    border-radius: 8px;

    resize: vertical;

}


.full {

    grid-column: 1 / -1;

}


/* =====================================================
   DARK MODE
===================================================== */

body.dark-mode {

    background: #0f172a;

    color: #f8fafc;

}


body.dark-mode .card,
body.dark-mode .filter-box,
body.dark-mode .table-container,
body.dark-mode .add-expense {

    background: #1e293b;

    color: #f8fafc;

}


body.dark-mode .card-title,
body.dark-mode .welcome {

    color: #94a3b8;

}


body.dark-mode th {

    background: #334155;

    color: white;

}


body.dark-mode td,
body.dark-mode th {

    border-color: #475569;

}


body.dark-mode input,
body.dark-mode select,
body.dark-mode textarea {

    background: #0f172a;

    color: white;

    border-color: #475569;

}


body.dark-mode .progress-container {

    background: #475569;

}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 1000px) {

    .cards {

        grid-template-columns: repeat(2, 1fr);

    }

    .filter-form {

        grid-template-columns: 1fr 1fr;

    }

}


@media (max-width: 700px) {

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;

    }

    .main {

        margin-left: 0;

        padding: 20px;

    }

    .cards {

        grid-template-columns: 1fr;

    }

    .expense-form {

        grid-template-columns: 1fr;

    }

    .filter-form {

        grid-template-columns: 1fr;

    }

    .topbar {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<div class="sidebar">

    <h2>💰 Expense Tracker</h2>

    <ul class="menu">

        <li>
            🏠 <a href="index.php">Dashboard</a>
        </li>

        <li>
            💳 <a href="index.php">Expenses</a>
        </li>

        <li>
            📊 <a href="reports.php">Reports</a>
        </li>

        <li>
            💰 <a href="budget.php">Budget</a>
        </li>

        <li>
            👤 <a href="profile.php">My Profile</a>
        </li>

        <li>
            🚪 <a href="logout.php">Logout</a>
        </li>

    </ul>

</div>


<!-- =====================================================
     MAIN
===================================================== -->

<div class="main">


<!-- TOP BAR -->

<div class="topbar">

    <div>

        <h1>Dashboard</h1>

        <div class="welcome">

            Welcome, <?php echo htmlspecialchars($user_name); ?> 👋

        </div>

    </div>


    <button
        onclick="toggleDarkMode()"
        class="primary"
    >

        🌙 Dark Mode

    </button>

</div>


<!-- =====================================================
     STATISTICS
===================================================== -->

<div class="cards">


    <div class="card">

        <div class="card-title">
            💰 Total Spending
        </div>

        <div class="card-value">

            ₹<?php echo number_format($total_spending, 2); ?>

        </div>

    </div>


    <div class="card">

        <div class="card-title">
            🧾 Total Expenses
        </div>

        <div class="card-value">

            <?php echo $expense_count; ?>

        </div>

    </div>


    <div class="card">

        <div class="card-title">
            📊 Average Expense
        </div>

        <div class="card-value">

            ₹<?php echo number_format($average_expense, 2); ?>

        </div>

    </div>


</div>


<!-- =====================================================
     BUDGET CARDS
===================================================== -->

<div class="cards">


    <div class="card">

        <div class="card-title">
            💰 Monthly Budget
        </div>

        <div class="card-value">

            ₹<?php echo number_format($budget, 2); ?>

        </div>

    </div>


    <div class="card">

        <div class="card-title">
            💸 Monthly Spent
        </div>

        <div class="card-value">

            ₹<?php echo number_format($monthly_spent, 2); ?>

        </div>

    </div>


    <div class="card">

        <div class="card-title">
            💵 Remaining
        </div>

        <div class="card-value <?php echo $remaining_budget < 0 ? 'budget-warning' : 'budget-good'; ?>">

            ₹<?php echo number_format($remaining_budget, 2); ?>

        </div>

    </div>


</div>


<!-- =====================================================
     BUDGET USAGE
===================================================== -->

<div class="card" style="margin-bottom:25px;">

    <div class="card-title">
        📊 Budget Usage
    </div>


    <p>

        <?php echo number_format($budget_percentage, 1); ?>%

        of your monthly budget used

    </p>


    <div class="progress-container">

        <div
            class="progress-bar"
            style="width:<?php echo $budget_progress; ?>%;"
        ></div>

    </div>

</div>


<!-- =====================================================
     CHARTS
===================================================== -->

<div class="cards">


    <!-- CATEGORY CHART -->

    <div class="card">

        <div class="card-title">
            📊 Spending by Category
        </div>

        <div class="chart-container">

            <canvas id="categoryChart"></canvas>

        </div>

    </div>


    <!-- MONTHLY CHART -->

    <div class="card">

        <div class="card-title">
            📈 Monthly Spending
        </div>

        <div class="chart-container">

            <canvas id="monthlyChart"></canvas>

        </div>

    </div>


    <!-- BUDGET CHART -->

    <div class="card">

        <div class="card-title">
            💰 Budget vs Spending
        </div>

        <div class="chart-container">

            <canvas id="budgetChart"></canvas>

        </div>

    </div>


</div>


<!-- =====================================================
     ADD EXPENSE
===================================================== -->

<div class="add-expense">

    <h2>➕ Add New Expense</h2>


    <form
        action="add_expense.php"
        method="POST"
        class="expense-form"
    >


        <div>

            <label>Title</label>

            <input
                type="text"
                name="title"
                placeholder="Example: College Fee"
                required
            >

        </div>


        <div>

            <label>Amount</label>

            <input
                type="number"
                name="amount"
                step="0.01"
                min="0"
                placeholder="Example: 500"
                required
            >

        </div>


        <div>

            <label>Category</label>

            <select name="category" required>

                <option value="">Select Category</option>

                <option value="Food">🍔 Food</option>

                <option value="Travel">🚗 Travel</option>

                <option value="Education">📚 Education</option>

                <option value="Shopping">🛍️ Shopping</option>

                <option value="Entertainment">🎮 Entertainment</option>

                <option value="Bills">💡 Bills</option>

                <option value="Health">🏥 Health</option>

                <option value="Other">📦 Other</option>

            </select>

        </div>


        <div>

            <label>Date</label>

            <input
                type="date"
                name="expense_date"
                required
            >

        </div>


        <div class="full">

            <label>Description</label>

            <textarea
                name="description"
                placeholder="Optional description..."
            ></textarea>

        </div>


        <div class="full">

            <button
                type="submit"
                class="primary"
            >

                ➕ Add Expense

            </button>

        </div>


    </form>

</div>


<!-- =====================================================
     SEARCH & FILTER
===================================================== -->

<div class="filter-box">

    <h2>🔎 Search & Filter</h2>


    <form
        method="GET"
        action="index.php"
        class="filter-form"
    >


        <input
            type="text"
            name="search"
            placeholder="Search expense..."
            value="<?php echo htmlspecialchars($search); ?>"
        >


        <select name="category">

            <option value="">All Categories</option>

            <option value="Food"
                <?php echo $filter_category === "Food" ? "selected" : ""; ?>>
                Food
            </option>

            <option value="Travel"
                <?php echo $filter_category === "Travel" ? "selected" : ""; ?>>
                Travel
            </option>

            <option value="Education"
                <?php echo $filter_category === "Education" ? "selected" : ""; ?>>
                Education
            </option>

            <option value="Shopping"
                <?php echo $filter_category === "Shopping" ? "selected" : ""; ?>>
                Shopping
            </option>

            <option value="Entertainment"
                <?php echo $filter_category === "Entertainment" ? "selected" : ""; ?>>
                Entertainment
            </option>

            <option value="Bills"
                <?php echo $filter_category === "Bills" ? "selected" : ""; ?>>
                Bills
            </option>

            <option value="Health"
                <?php echo $filter_category === "Health" ? "selected" : ""; ?>>
                Health
            </option>

            <option value="Other"
                <?php echo $filter_category === "Other" ? "selected" : ""; ?>>
                Other
            </option>

        </select>


        <input
            type="date"
            name="date"
            value="<?php echo htmlspecialchars($filter_date); ?>"
        >


        <input
            type="month"
            name="month"
            value="<?php echo htmlspecialchars($filter_month); ?>"
        >


        <button
            type="submit"
            class="primary"
        >

            Filter

        </button>


        <a
            href="index.php"
            class="btn"
            style="background:#6b7280;color:white;"
        >

            Reset

        </a>


    </form>

</div>


<!-- =====================================================
     EXPENSE TABLE
===================================================== -->

<div class="table-container">

    <h2>💳 Your Expenses</h2>


    <table>

        <thead>

            <tr>

                <th>ID</th>

                <th>Title</th>

                <th>Amount</th>

                <th>Category</th>

                <th>Date</th>

                <th>Description</th>

                <th>Actions</th>

            </tr>

        </thead>


        <tbody>


        <?php if ($result->num_rows > 0): ?>


            <?php while ($row = $result->fetch_assoc()): ?>

                <tr>

                    <td>
                        <?php echo $row["id"]; ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($row["title"]); ?>
                    </td>

                    <td>
                        ₹<?php echo number_format($row["amount"], 2); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($row["category"]); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($row["expense_date"]); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($row["description"]); ?>
                    </td>

                    <td>

                        <div class="actions">

                            <a
                                href="edit_expense.php?id=<?php echo $row["id"]; ?>"
                                class="btn edit"
                            >
                                ✏️ Edit
                            </a>


                            <a
                                href="delete_expense.php?id=<?php echo $row["id"]; ?>"
                                class="btn delete"
                                onclick="return confirm('Are you sure you want to delete this expense?');"
                            >
                                🗑️ Delete
                            </a>

                        </div>

                    </td>

                </tr>

            <?php endwhile; ?>


        <?php else: ?>


            <tr>

                <td
                    colspan="7"
                    style="text-align:center;padding:30px;"
                >

                    No expenses found.

                </td>

            </tr>


        <?php endif; ?>


        </tbody>

    </table>

</div>


</div>


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>

/* =====================================================
   DARK MODE
===================================================== */

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


/* =====================================================
   CATEGORY CHART
===================================================== */

const categoryLabels =
    <?php echo json_encode($chart_categories); ?>;

const categoryData =
    <?php echo json_encode($chart_category_totals); ?>;


const categoryCanvas =
    document.getElementById("categoryChart");


if (categoryCanvas && categoryData.length > 0) {

    new Chart(categoryCanvas, {

        type: "doughnut",

        data: {

            labels: categoryLabels,

            datasets: [{

                label: "Spending",

                data: categoryData

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            plugins: {

                legend: {

                    position: "bottom"

                }

            }

        }

    });

}


/* =====================================================
   MONTHLY SPENDING CHART
===================================================== */

const monthLabels =
    <?php echo json_encode($chart_months); ?>;

const monthData =
    <?php echo json_encode($chart_month_totals); ?>;


const monthlyCanvas =
    document.getElementById("monthlyChart");


if (monthlyCanvas && monthData.length > 0) {

    new Chart(monthlyCanvas, {

        type: "bar",

        data: {

            labels: monthLabels,

            datasets: [{

                label: "Monthly Spending (₹)",

                data: monthData

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            scales: {

                y: {

                    beginAtZero: true

                }

            },

            plugins: {

                legend: {

                    position: "bottom"

                }

            }

        }

    });

}


/* =====================================================
   BUDGET VS SPENDING
===================================================== */

const budgetAmount =
    <?php echo (float)$budget; ?>;

const spentAmount =
    <?php echo (float)$monthly_spent; ?>;


const budgetCanvas =
    document.getElementById("budgetChart");


if (budgetCanvas) {

    new Chart(budgetCanvas, {

        type: "bar",

        data: {

            labels: ["This Month"],

            datasets: [

                {

                    label: "Budget (₹)",

                    data: [budgetAmount]

                },

                {

                    label: "Spent (₹)",

                    data: [spentAmount]

                }

            ]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            scales: {

                y: {

                    beginAtZero: true

                }

            },

            plugins: {

                legend: {

                    position: "bottom"

                }

            }

        }

    });

}

</script>


</body>

</html>


<?php

$stmt->close();

$conn->close();

?>
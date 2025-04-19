<?php
require_once "dbconfig.php";

// Fetch summary data
$total_income = $conn->query("SELECT SUM(amount) as total FROM incomes")->fetch_assoc()['total'] ?: 0;
$total_expenses = $conn->query("SELECT SUM(amount) as total FROM expenses")->fetch_assoc()['total'] ?: 0;
$total_savings = $conn->query("SELECT SUM(current_amount) as total FROM savings_goals")->fetch_assoc()['total'] ?: 0;
$total_investments = 50000; // Sample data
$total_debts = 267500; // Sample data
$net_worth = ($total_income + $total_investments) - $total_debts;

// Monthly income and expenses for chart
$monthly_data = [];
$res = $conn->query("
    SELECT 
        MONTH(date) as month, 
        YEAR(date) as year,
        'income' as type,
        SUM(amount) as total 
    FROM incomes 
    GROUP BY YEAR(date), MONTH(date)
    UNION ALL
    SELECT 
        MONTH(date) as month, 
        YEAR(date) as year,
        'expense' as type,
        SUM(amount) as total 
    FROM expenses 
    GROUP BY YEAR(date), MONTH(date)
    ORDER BY year, month
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $month_name = date('M', mktime(0, 0, 0, $row['month'], 1));
        $year = $row['year'];
        $key = "$month_name";
        
        if (!isset($monthly_data[$key])) {
            $monthly_data[$key] = ['income' => 0, 'expense' => 0];
        }
        
        $monthly_data[$key][$row['type']] = floatval($row['total']);
    }
}

// Expense categories for pie chart
$expense_categories = [];
$res = $conn->query("SELECT category, SUM(amount) as total FROM expenses GROUP BY category ORDER BY total DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $expense_categories[$row['category']] = floatval($row['total']);
    }
}

// Recent transactions
$recent_transactions = [];
$res = $conn->query("
    (SELECT date, source as category, amount, 'Income' as type, note FROM incomes ORDER BY date DESC LIMIT 5)
    UNION ALL
    (SELECT date, category, amount, 'Expense' as type, note FROM expenses ORDER BY date DESC LIMIT 5)
    ORDER BY date DESC
    LIMIT 10
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recent_transactions[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Dashboard - FinanceTracker</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header class="header">
        <a href="index.php" class="logo">FinanceTracker</a>
        <nav class="nav">
            <a href="index.php" class="active">Dashboard</a>
            <a href="income.php">Income</a>
            <a href="expenses.php">Expenses</a>
            <a href="savings.php">Savings</a>
            <a href="investments.php">Investments</a>
            <a href="debt.php">Debts</a>
            <a href="#">Reports</a>
            <a href="#">News</a>
        </nav>
        <button class="theme-toggle" id="themeToggle">🌙</button>
    </header>

    <div class="container">
        <h1 class="page-title">Financial Dashboard</h1>
        <p class="subtitle">Welcome to your personal finance dashboard. Here's an overview of your financial data.</p>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">
                    <span style="color: #10b981;">↑</span> Income
                </div>
                <div class="stat-value positive">$<?= number_format($total_income) ?></div>
                <div class="stat-description">Total income received</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">
                    <span style="color: #ef4444;">↓</span> Expenses
                </div>
                <div class="stat-value negative">$<?= number_format($total_expenses) ?></div>
                <div class="stat-description">Total expenses</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">
                    <span style="color: #3b82f6;">↗</span> Savings
                </div>
                <div class="stat-value">$<?= number_format($total_savings) ?></div>
                <div class="stat-description">Total savings</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">
                    <span style="color: #8b5cf6;">📊</span> Investments
                </div>
                <div class="stat-value">$<?= number_format($total_investments) ?></div>
                <div class="stat-description">Total investments</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">
                    <span style="color: #f59e0b;">💰</span> Debts
                </div>
                <div class="stat-value">$<?= number_format($total_debts) ?></div>
                <div class="stat-description">Total debts</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">
                    <span style="color: <?= $net_worth >= 0 ? '#10b981' : '#ef4444' ?>;">💵</span> Net Worth
                </div>
                <div class="stat-value <?= $net_worth >= 0 ? 'positive' : 'negative' ?>">$<?= number_format($net_worth) ?></div>
                <div class="stat-description">Total assets minus liabilities</div>
            </div>
        </div>
        
        <div class="content-grid">
            <div class="card">
                <h2 class="card-title">Income vs Expenses</h2>
                <p class="subtitle">Monthly comparison of income and expenses</p>
                <canvas id="incomeExpensesChart" height="300"></canvas>
            </div>
            
            <div class="card">
                <h2 class="card-title">Expense Breakdown</h2>
                <p class="subtitle">Distribution by category</p>
                <canvas id="expenseBreakdownChart" height="300"></canvas>
            </div>
        </div>
        
        <div class="card" style="margin-top: 30px;">
            <h2 class="card-title">Recent Transactions</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_transactions as $transaction): ?>
                    <tr>
                        <td><?= date('n/j/Y', strtotime($transaction['date'])) ?></td>
                        <td><?= htmlspecialchars($transaction['category']) ?></td>
                        <td class="<?= $transaction['type'] == 'Income' ? 'positive' : 'negative' ?>">
                            <?= $transaction['type'] == 'Income' ? '+' : '-' ?>$<?= number_format($transaction['amount']) ?>
                        </td>
                        <td><?= $transaction['type'] ?></td>
                        <td><?= htmlspecialchars($transaction['note']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div>
                <h3 class="footer-title">FinanceTracker</h3>
                <p class="footer-text">Track your personal finances, manage expenses, and stay on top of your financial goals.</p>
            </div>
            
            <div>
                <h3 class="footer-title">Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="income.php">Income</a></li>
                    <li><a href="expenses.php">Expenses</a></li>
                    <li><a href="savings.php">Savings</a></li>
                    <li><a href="#">Financial News</a></li>
                </ul>
            </div>
            
            <div>
                <h3 class="footer-title">Contact</h3>
                <p class="footer-text">Email: support@financetracker.com</p>
                <p class="footer-text">Phone: +1 (123) 456-7890</p>
                <p class="footer-text">Address: 123 Finance St, Money City</p>
            </div>
        </div>
        
        <div class="copyright">
            © 2025 FinanceTracker. All rights reserved.
        </div>
    </footer>

    <script>
    // Income vs Expenses Chart
    const incomeExpensesCtx = document.getElementById('incomeExpensesChart').getContext('2d');
    const monthlyLabels = <?= json_encode(array_keys($monthly_data)) ?>;
    const incomeData = <?= json_encode(array_map(function($item) { return $item['income']; }, $monthly_data)) ?>;
    const expenseData = <?= json_encode(array_map(function($item) { return $item['expense']; }, $monthly_data)) ?>;
    
    new Chart(incomeExpensesCtx, {
        type: 'bar',
        data: {
            labels: monthlyLabels,
            datasets: [
                {
                    label: 'Income',
                    data: incomeData,
                    backgroundColor: '#10b981',
                    borderRadius: 4
                },
                {
                    label: 'Expenses',
                    data: expenseData,
                    backgroundColor: '#ef4444',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    
    // Expense Breakdown Chart
    const expenseBreakdownCtx = document.getElementById('expenseBreakdownChart').getContext('2d');
    const categoryLabels = <?= json_encode(array_keys($expense_categories)) ?>;
    const categoryData = <?= json_encode(array_values($expense_categories)) ?>;
    const backgroundColors = [
        '#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', 
        '#06b6d4', '#ec4899', '#14b8a6', '#f97316', '#6366f1'
    ];
    
    // Calculate percentages for labels
    const totalExpenses = categoryData.reduce((sum, value) => sum + value, 0);
    const formattedLabels = categoryLabels.map((label, index) => {
        const percentage = Math.round((categoryData[index] / totalExpenses) * 100);
        return `${label}: ${percentage}%`;
    });
    
    new Chart(expenseBreakdownCtx, {
        type: 'pie',
        data: {
            labels: formattedLabels,
            datasets: [{
                data: categoryData,
                backgroundColor: backgroundColors.slice(0, categoryLabels.length),
                borderWidth: 1,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        padding: 20,
                        boxWidth: 12
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            return `$${value.toLocaleString()}`;
                        }
                    }
                }
            }
        }
    });

    // Dark mode toggle
    document.getElementById('themeToggle').addEventListener('click', function() {
        document.body.classList.toggle('dark-mode');
        this.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
    });
    </script>
</body>
</html>

<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "finance_tracker");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Fetch summary data
$total_income = $conn->query("SELECT SUM(amount) as total FROM incomes")->fetch_assoc()['total'] ?: 0;
$total_expenses = $conn->query("SELECT SUM(amount) as total FROM expenses")->fetch_assoc()['total'] ?: 0;
$total_savings = $conn->query("SELECT SUM(current_amount) as total FROM savings_goals")->fetch_assoc()['total'] ?: 0;
$total_investments = 50000; // Sample data as per screenshot
$total_debts = 267500; // Sample data as per screenshot
$net_worth = $total_income + $total_investments - $total_debts;

// For chart: Monthly income and expenses
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
        $key = date('M', mktime(0, 0, 0, $row['month'], 10));
        if (!isset($monthly_data[$key])) {
            $monthly_data[$key] = ['income' => 0, 'expense' => 0];
        }
        $monthly_data[$key][$row['type']] = floatval($row['total']);
    }
}

// Expense breakdown
$expense_categories = [];
$res = $conn->query("SELECT category, SUM(amount) as total FROM expenses GROUP BY category ORDER BY total DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $expense_categories[$row['category']] = floatval($row['total']);
    }
}

// Recent transactions (combined income and expenses)
$recent_transactions = [];
$res = $conn->query("
    (SELECT date as date, source as category, amount, 'Income' as type FROM incomes ORDER BY date DESC LIMIT 5)
    UNION ALL
    (SELECT date as date, category, amount, 'Expense' as type FROM expenses ORDER BY date DESC LIMIT 5)
    ORDER BY date DESC
    LIMIT 10
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recent_transactions[] = $row;
    }
}

// User profile data (you can customize or load from your user table)
$user = [
    'name' => 'John Smith',
    'email' => 'john@example.com',
    'phone' => '+1 (123) 456-7890',
    'profile_pic' => 'profile.jpg',
    'joined_date' => '2023-01-15'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>FinanceTracker - Dashboard</title>
  <link rel="stylesheet" href="styles.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    /* Additional dashboard-specific styles */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
      margin-bottom: 25px;
    }
    
    .stats-card {
      background: white;
      border-radius: 14px;
      padding: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .stats-title {
      font-size: 14px;
      color: #777;
      margin-bottom: 10px;
    }
    
    .stats-value {
      font-size: 24px;
      font-weight: 700;
    }
    
    .value-positive {
      color: #10b981;
    }
    
    .value-negative {
      color: #ef4444;
    }
    
    .chart-container {
      background: white;
      border-radius: 14px;
      padding: 20px;
      margin-bottom: 25px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .chart-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 15px;
    }
    
    .chart-title {
      font-size: 16px;
      font-weight: 600;
    }

    .dashboard-columns {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 25px;
    }
    
    .column-left, .column-right {
      display: flex;
      flex-direction: column;
      gap: 25px;
    }
    
    .user-profile-card {
      background: white;
      border-radius: 14px;
      padding: 25px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
    }
    
    .user-avatar {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      background: #f0f0f0;
      margin-bottom: 15px;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 36px;
      color: #555;
      font-weight: 600;
    }
    
    .user-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .user-name {
      font-size: 20px;
      font-weight: 600;
      margin-bottom: 5px;
    }
    
    .user-email {
      color: #777;
      margin-bottom: 20px;
    }
    
    .user-actions button {
      background: #ff7f50;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 500;
      transition: background 0.2s;
    }
    
    .user-actions button:hover {
      background: #ff5722;
    }
    
    .transactions-list {
      background: white;
      border-radius: 14px;
      padding: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .transaction-item {
      display: flex;
      justify-content: space-between;
      padding: 12px 0;
      border-bottom: 1px solid #f0f0f0;
    }
    
    .transaction-item:last-child {
      border-bottom: none;
    }
    
    .transaction-details {
      display: flex;
      flex-direction: column;
    }
    
    .transaction-category {
      font-weight: 600;
      margin-bottom: 4px;
    }
    
    .transaction-date {
      font-size: 12px;
      color: #777;
    }
    
    .footer {
      margin-top: 40px;
      padding: 30px 0;
      background: #fff;
      border-top: 1px solid #eee;
    }
    
    .footer-content {
      max-width: 1100px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 40px;
    }
    
    .footer-column h3 {
      font-size: 18px;
      margin-bottom: 15px;
    }
    
    .footer-column p {
      color: #777;
      line-height: 1.6;
    }
    
    .quick-links {
      list-style: none;
      padding: 0;
    }
    
    .quick-links li {
      margin-bottom: 8px;
    }
    
    .quick-links a {
      color: #555;
      text-decoration: none;
    }
    
    .quick-links a:hover {
      color: #ff7f50;
    }
    
    .copyright {
      text-align: center;
      padding-top: 20px;
      color: #777;
      font-size: 14px;
    }
    
    /* Add these styles for the user profile section */
    .profile-stats {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 15px;
      width: 100%;
      margin-top: 20px;
      margin-bottom: 20px;
    }
    
    .profile-stat {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      text-align: center;
    }
    
    .profile-stat-value {
      font-size: 20px;
      font-weight: 600;
      color: #333;
      margin-bottom: 5px;
    }
    
    .profile-stat-label {
      font-size: 12px;
      color: #777;
    }
    
    .user-info-list {
      width: 100%;
      margin-top: 10px;
    }
    
    .user-info-item {
      display: flex;
      padding: 8px 0;
      border-bottom: 1px solid #f0f0f0;
    }
    
    .user-info-label {
      flex: 1;
      color: #777;
    }
    
    .user-info-value {
      flex: 2;
      font-weight: 500;
    }
  </style>
</head>
<body>
  <nav class="navbar">
    <div class="logo">FinanceTracker</div>
    <div class="nav-links">
      <a href="dashboard.php" class="active">Dashboard</a>
      <a href="income.php">Income</a>
      <a href="expenses.php">Expenses</a>
      <a href="savings.php">Savings</a>
      <a href="#">Investments</a>
      <a href="#">Debts</a>
      <a href="#">Reports</a>
      <a href="#">News</a>
    </div>
    <button class="dark-mode-toggle">🌙</button>
  </nav>
  
  <main>
    <h1>Financial Dashboard</h1>
    <p class="subtitle">Welcome to your personal finance dashboard. Here's an overview of your financial data.</p>
    
    <div class="stats-grid">
      <div class="stats-card">
        <div class="stats-title">
          <span style="color: #10b981;">↑</span> Income
        </div>
        <div class="stats-value value-positive">$<?=number_format($total_income)?></div>
        <div class="stats-subtitle">Total income received</div>
      </div>
      
      <div class="stats-card">
        <div class="stats-title">
          <span style="color: #ef4444;">↓</span> Expenses
        </div>
        <div class="stats-value value-negative">$<?=number_format($total_expenses)?></div>
        <div class="stats-subtitle">Total expenses</div>
      </div>
      
      <div class="stats-card">
        <div class="stats-title">
          <span style="color: #3b82f6;">↗</span> Savings
        </div>
        <div class="stats-value">$<?=number_format($total_savings)?></div>
        <div class="stats-subtitle">Total savings</div>
      </div>
      
      <div class="stats-card">
        <div class="stats-title">
          <span style="color: #8b5cf6;">📊</span> Investments
        </div>
        <div class="stats-value">$<?=number_format($total_investments)?></div>
        <div class="stats-subtitle">Total investments</div>
      </div>
      
      <div class="stats-card">
        <div class="stats-title">
          <span style="color: #f59e0b;">💰</span> Debts
        </div>
        <div class="stats-value">$<?=number_format($total_debts)?></div>
        <div class="stats-subtitle">Total debts</div>
      </div>
      
      <div class="stats-card">
        <div class="stats-title">
          <span style="color: <?= $net_worth >= 0 ? '#10b981' : '#ef4444' ?>;">💵</span> Net Worth
        </div>
        <div class="stats-value <?= $net_worth >= 0 ? 'value-positive' : 'value-negative' ?>">$<?=number_format($net_worth)?></div>
        <div class="stats-subtitle">Total assets minus liabilities</div>
      </div>
    </div>
    
    <div class="dashboard-columns">
      <div class="column-left">
        <div class="chart-container">
          <div class="chart-header">
            <div class="chart-title">Income vs Expenses</div>
            <div class="chart-subtitle">Monthly comparison of income and expenses</div>
          </div>
          <canvas id="incomeExpensesChart" height="300"></canvas>
        </div>
        
        <div class="chart-container">
          <div class="chart-header">
            <div class="chart-title">Expense Breakdown</div>
            <div class="chart-subtitle">Distribution by category</div>
          </div>
          <canvas id="expenseBreakdownChart" height="300"></canvas>
        </div>
      </div>
      
      <div class="column-right">
        <div class="user-profile-card">
          <div class="user-avatar">
            <?php if (file_exists($user['profile_pic'])): ?>
              <img src="<?= $user['profile_pic'] ?>" alt="Profile Picture">
            <?php else: ?>
              <?= substr($user['name'], 0, 2) ?>
            <?php endif; ?>
          </div>
          
          <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
          <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
          
          <div class="profile-stats">
            <div class="profile-stat">
              <div class="profile-stat-value"><?= date('Y') - date('Y', strtotime($user['joined_date'])) ?></div>
              <div class="profile-stat-label">Years</div>
            </div>
            <div class="profile-stat">
              <div class="profile-stat-value"><?= rand(20, 40) ?></div>
              <div class="profile-stat-label">Transactions</div>
            </div>
          </div>
          
          <div class="user-info-list">
            <div class="user-info-item">
              <div class="user-info-label">Phone:</div>
              <div class="user-info-value"><?= htmlspecialchars($user['phone']) ?></div>
            </div>
            <div class="user-info-item">
              <div class="user-info-label">Member Since:</div>
              <div class="user-info-value"><?= date('M d, Y', strtotime($user['joined_date'])) ?></div>
            </div>
          </div>
          
          <div class="user-actions">
            <button>Edit Profile</button>
          </div>
        </div>
        
        <div class="transactions-list">
          <div class="chart-header">
            <div class="chart-title">Recent Transactions</div>
            <a href="#" style="color: #ff7f50; text-decoration: none; font-weight: 500;">View All</a>
          </div>
          
          <?php foreach ($recent_transactions as $transaction): ?>
            <div class="transaction-item">
              <div class="transaction-details">
                <div class="transaction-category"><?= htmlspecialchars($transaction['category']) ?></div>
                <div class="transaction-date"><?= date('M d, Y', strtotime($transaction['date'])) ?></div>
              </div>
              <div class="transaction-amount <?= $transaction['type'] == 'Income' ? 'value-positive' : 'value-negative' ?>">
                <?= $transaction['type'] == 'Income' ? '+' : '-' ?>$<?= number_format(abs($transaction['amount']), 2) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </main>
  
  <footer class="footer">
    <div class="footer-content">
      <div class="footer-column">
        <h3>FinanceTracker</h3>
        <p>Track your personal finances, manage expenses, and stay on top of your financial goals.</p>
      </div>
      
      <div class="footer-column">
        <h3>Quick Links</h3>
        <ul class="quick-links">
          <li><a href="dashboard.php">Dashboard</a></li>
          <li><a href="income.php">Income</a></li>
          <li><a href="expenses.php">Expenses</a></li>
          <li><a href="savings.php">Savings</a></li>
          <li><a href="#">Financial News</a></li>
        </ul>
      </div>
      
      <div class="footer-column">
        <h3>Contact</h3>
        <p>Email: support@financetracker.com</p>
        <p>Phone: +1 (123) 456-7890</p>
        <p>Address: 123 Finance St, Money City</p>
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
            borderRadius: 6
          },
          {
            label: 'Expenses',
            data: expenseData,
            backgroundColor: '#ef4444',
            borderRadius: 6
          }
        ]
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              display: true,
              color: '#f0f0f0'
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
    
    new Chart(expenseBreakdownCtx, {
      type: 'pie',
      data: {
        labels: categoryLabels,
        datasets: [{
          data: categoryData,
          backgroundColor: backgroundColors.slice(0, categoryLabels.length)
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'right'
          }
        }
      }
    });

    // Toggle dark mode
    document.querySelector('.dark-mode-toggle').addEventListener('click', function() {
      document.body.classList.toggle('dark-mode');
    });
  </script>
</body>
</html>

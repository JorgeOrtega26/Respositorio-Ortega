<!-- index.php -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard-ELESS</title>
  <link rel="stylesheet" href="./css/maqueta2.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <?php include 'sidebar.php'; ?>

  <div class="main">
    <header>
      <div class="search">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Type in to search...">
      </div>
      <div class="header-actions">
        <button><i class="fas fa-plus"></i> Add</button>
        <button><i class="fas fa-bell"></i><span>2</span></button>
        <button><i class="fas fa-envelope"></i><span>5</span></button>
        <img src="https://via.placeholder.com/32" alt="User">
      </div>
    </header>

    <section class="content">
      <div class="page-header">
        <h2>Live Alerts</h2>
        <div class="sub">
          Rideway | <a href="#">Export to Mail</a>
        </div>
        <div class="toggle-buttons">
          <button>Due Alerts</button>
          <button class="active">Live Alerts</button>
        </div>
      </div>

      <div class="tabs">
        <button class="active">Active Reports</button>
        <button>Calendar</button>
        <button>Recent Reports</button>
        <button>Live Stats</button>
        <button>Export Reports</button>
        <button>Flagged</button>
      </div>

      <div class="metric-cards">
        <div class="card">
          <div class="title">Performed Operations</div>
          <div class="number">24</div>
          <div class="trend up"><i class="fas fa-arrow-up"></i> 23% increase in operations</div>
        </div>
        <div class="card">
          <div class="title">Progressive Impacts</div>
          <div class="number">11</div>
          <div class="trend down"><i class="fas fa-arrow-down"></i> 4 leads less than last week</div>
        </div>
        <div class="card">
          <div class="title">Market Progress</div>
          <div class="number">97</div>
          <div class="trend down"><i class="fas fa-arrow-down"></i> 3.25% less market than 1 yr ago</div>
        </div>
        <div class="card">
          <div class="title">Active Hotpoints</div>
          <div class="number">78</div>
          <div class="trend up"><i class="fas fa-arrow-up"></i> 4 new points compared to yesterday</div>
        </div>
      </div>

      <div class="alerts-cards">
        <div class="card alert-red">
          <i class="fas fa-exclamation-circle"></i>
          <div class="info">
            <div class="headline">3 New Live Alerts</div>
            <div class="subinfo">last due 21/06/2017</div>
          </div>
          <button>View Alerts</button>
        </div>
        <div class="card alert-green">
          <i class="fas fa-check-circle"></i>
          <div class="info">
            <div class="headline">Everything in Check</div>
            <div class="subinfo">last checked: 2 hours ago</div>
          </div>
          <button>View Alerts</button>
        </div>
        <div class="card alert-green">
          <i class="fas fa-check-circle"></i>
          <div class="info">
            <div class="headline">Everything in Check</div>
            <div class="subinfo">last checked: 3 days ago</div>
          </div>
          <button>View Alerts</button>
        </div>
      </div>
    </section>
  </div>
</body>
</html>
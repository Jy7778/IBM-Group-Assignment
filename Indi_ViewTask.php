<?php
session_start();
$servername = "localhost";
$username = "root"; // Change if needed
$password = ""; // Change if needed
$dbname = "IBM Individual";

// Connect to database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_GET['task_id'])) {
    die("Task ID not provided.");
}

$task_id = $_GET['task_id'];

// Include the notification script
include 'Indi_Notification.php';

// Get current date and time
$currentDay = date('l');
$currentDate = date('jS F Y');
$currentTime = date('H:i:s');

function getUnreadNotificationCount($conn, $user_id) {
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $notiStmt = $conn->prepare($sql);
    $notiStmt->bind_param("s", $user_id);
    $notiStmt->execute();
    $result = $notiStmt->get_result();
    $row = $result->fetch_assoc();
    $notiStmt->close();
    return $row['count'];
}

// Query to fetch task details
$sql = "SELECT t.task_id, t.task_name, t.description, t.due_date, t.due_time, c.category_name, c.color, t.status, t.priority, t.custom_remind, t.complete_date
        FROM tasks t
        JOIN category c ON t.category_id = c.category_id
        WHERE t.task_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Task not found.");
}

$data = $result->fetch_assoc();

// Function to delete task
function deleteTask($conn, $task_id) {
    $sql = "DELETE FROM tasks WHERE task_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $task_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo "<script>alert('Task deleted successfully!'); window.location.href='Indi_TaskList.php';</script>";
        exit();
    } else {
        echo "<script>alert('Task not found or deletion failed.');</script>";
    }

    $stmt->close();
}

// Check if a delete request was made
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_task'])) {
    deleteTask($conn, $_POST['task_id']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Details - TaskHelper</title>
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #b9f5d8, #d4f0f0);
            min-height: 100vh;
        }
        
        /* Header styles */
        .header {
            background-color: rgba(255, 255, 255, 0.7);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo img {
            width: 40px;
            height: 40px;
        }
        
        .logo h1 {
            font-size: 24px;
            color: #333;
        }
        
        .date-time {
            font-size: 16px;
            color: #555;
        }
        
        .user-actions {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .user-actions img {
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        
        /* Navigation styles */
        .nav {
            display: flex;
            justify-content: space-between;
            background-color: rgba(255, 255, 255, 0.9);
            padding: 10px 30px;
            border-bottom: 1px solid #ddd;
            margin-bottom: 30px;
        }
        
        .nav a {
            text-decoration: none;
            color: #444;
            font-weight: 500;
            padding: 5px 10px;
            transition: all 0.2s ease;
        }
        
        .nav a:hover {
            color: #25a18e;
            background-color: rgba(37, 161, 142, 0.1);
            border-radius: 4px;
        }
        
        /* Task details styles */
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        
        .task-container {
            max-width: 700px;
            margin: 0 auto 40px auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            background-color: rgba(255, 255, 255, 0.8);
        }
        
        .task-container h2 {
            color: #25a18e;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #25a18e;
        }
        
        .task-container p {
            margin-bottom: 15px;
            line-height: 1.5;
            color: #444;
        }
        
        .category {
            display: flex;
            align-items: center;
            font-size: 16px;
            margin-bottom: 15px;
            color: #444;
			font-weight: bold;
        }
        
        .category-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .button-container {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .edit-button, .delete-button {
            display: inline-block;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }
        
        .edit-button {
            background-color: #25a18e;
            color: white;
        }
        
        .edit-button:hover {
            background-color: #1c8a79;
        }
        
        .delete-button {
            background-color: #e74c3c;
            color: white;
        }
        
        .delete-button:hover {
            background-color: #c0392b;
        }
        
        .task-info-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .task-info-item {
            background-color: rgba(255, 255, 255, 0.5);
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #25a18e;
        }
        
        .task-info-item strong {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        
        .notification-dot {
            position: absolute;
            top: -5px;
            left: -5px;
            background-color: red;
            color: white;
            font-size: 12px;
            font-weight: bold;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: 2px solid white;
        }
		.footer {
			position: absolute;
			bottom: 20px;
			left: 0;
			right: 0;
			text-align: center;
			color: #666;
			font-size: 12px;
			padding: 5px;
			z-index: 1;
		}
    </style>
</head>
<body>
    <!-- Header section -->
    <header class="header">
        <div class="logo">
            <a href="Indi_Dashboard.php" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="10" fill="#25a18e"/>
                    <path d="M8 12l3 3 5-5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h1 style="color: #333;">TaskHelper</h1>
            </a>
        </div>
        <div class="date-time">
            <div><?php echo $currentDay; ?>, <?php echo $currentDate; ?></div>
            <div id="current-time"><?php echo $currentTime; ?></div>
        </div>
        <div class="user-actions" style="position: relative;">
            <?php $notification_count = getUnreadNotificationCount($conn, $_SESSION['user_id']); ?>
            <a href="Indi_NotificationPage.php" style="position: relative;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M13.73 21a2 2 0 01-3.46 0" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <?php if ($notification_count > 0): ?>
                    <span class="notification-dot"><?php echo $notification_count; ?></span>
                <?php endif; ?>
            </a>

            <a href="Indi_Logout.php">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					<path d="M16 17l5-5-5-5" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					<path d="M21 12H9" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</a>

            <div>Welcome, <?php echo htmlspecialchars($_SESSION['user_id']); ?>!</div>
        </div>
    </header>

    <!-- Navigation section -->
    <nav class="nav">
        <a href="Indi_AddTask.php">Add Task</a>
        <a href="Indi_TaskList.php">Task List</a>
        <a href="Indi_AddCategory.php">Add Category</a>
        <a href="Indi_ManageCategory.php">Category List</a>
        <a href="Indi_TaskArchive.php">Task Archive</a>
    </nav>

    <!-- Task details content -->
    <h1>Task Details</h1>
    <div class="task-container">
        <h2><?php echo htmlspecialchars($data['task_name']); ?></h2>
        
        <div class="category">
            <span class="category-dot" style="background-color: <?php echo htmlspecialchars($data['color']); ?>;"></span>
			<?php echo htmlspecialchars($data['category_name']); ?>
        </div>
        
        <p><strong>Description:</strong><br><?php echo nl2br(htmlspecialchars($data['description'])); ?></p>
        
        <div class="task-info-row">
            <div class="task-info-item">
                <strong>Due Date</strong>
                <?php echo htmlspecialchars($data['due_date']); ?>
            </div>
            
            <div class="task-info-item">
                <strong>Due Time</strong>
                <?php echo htmlspecialchars($data['due_time']); ?>
            </div>
        </div>
        
        <div class="task-info-row">
            <div class="task-info-item">
                <strong>Status</strong>
                <?php echo htmlspecialchars($data['status']); ?>
            </div>
            
            <div class="task-info-item">
                <strong>Priority</strong>
                <?php echo htmlspecialchars($data['priority']); ?>
            </div>
        </div>
        
        <div class="task-info-row">
			<div class="task-info-item">
				<strong>Reminder</strong>
				<?php 
				switch($data['custom_remind']) {
					case 0:
						echo "Off";
						break;
					case 1:
						echo "5 minutes before";
						break;
					case 2:
						echo "15 minutes before";
						break;
					case 3:
						echo "30 minutes before";
						break;
					case 4:
						echo "1 hour before";
						break;
					case 5:
						echo "2 hours before";
						break;
					case 6:
						echo "1 day before";
						break;
					case 7:
						echo "2 days before";
						break;
					case 8:
						echo "1 week before";
						break;
					case 9:
						echo "2 weeks before";
						break;
					case 10:
						echo "1 month before";
						break;
					default:
						echo "Off";
				}
				?>
			</div>
			
			<div class="task-info-item">
				<strong>Date Completed</strong>
				<?php echo ($data['complete_date'] !== NULL && $data['complete_date'] !== '') ? htmlspecialchars($data['complete_date']) : '-'; ?>
			</div>
		</div>
        
        <div class="button-container">
            <a href="Indi_EditTask.php?task_id=<?php echo $task_id; ?>" class="edit-button">Edit Task</a>
            <form id="deleteForm" method="post" style="display:inline;">
                <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                <button type="button" class="delete-button" onclick="confirmDelete()">Delete Task</button>
                <input type="hidden" name="delete_task" value="1">
            </form>
        </div>
    </div>
    <div class="footer">
		<p>This business is fictitious and part of a university course.</p>
	</div>
    <script>
        // Update the current time
        function updateTime() {
            const now = new Date();
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            
            // Add leading zeros
            hours = hours < 10 ? '0' + hours : hours;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            
            const timeString = hours + ':' + minutes + ':' + seconds;
            document.getElementById('current-time').textContent = timeString;
            
            setTimeout(updateTime, 1000);
        }
        
        // Start the clock
        window.onload = function() {
            updateTime();
        };
        
        // Confirm delete function
        function confirmDelete() {
            if (confirm("Are you sure you want to delete this task? This action cannot be undone!")) {
                document.getElementById("deleteForm").submit();
            }
        }
    </script>
</body>
</html>
<?php
//changed something here
//add something here
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

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: Indi_LoginPage.php"); 
    exit();
}

$user_id = $_SESSION['user_id']; 

// Include the notification script
include 'Indi_Notification.php';

// Get current date and time
$currentDay = date('l');
$currentDate = date('jS F Y');
$currentTime = date('H:i:s');

// Update the notification count by running the notification script
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

$category_options = "";

// Fetch categories from the database for this user
$sql = "SELECT category_id, category_name, color FROM category WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Modify the category options generation
while ($row = $result->fetch_assoc()) {
    $color = isset($row['color']) ? $row['color'] : "#FFFFFF"; // Default to white if color is not set
    $category_options .= "<option value='{$row['category_id']}' data-color='{$color}'>";
    $category_options .= "{$row['category_name']}</option>";
}
$stmt->close();

// Helper function to determine if a color is light
function isLightColor($hex) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;
    return $brightness > 128;
}

// Handle task submission
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_category'])) {
    $new_category = trim($_POST['new_category']);
    $category_color = isset($_POST['category_color']) ? trim($_POST['category_color']) : "#FFFFFF";
    
    if (empty($category_color) || !preg_match('/#[a-f0-9]{6}/i', $category_color)) {
        $category_color = "#FFFFFF"; // Default to white if invalid color format
    }
    
    if (!empty($new_category)) {
        $sql = "INSERT INTO category (user_id, category_name, color) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $user_id, $new_category, $category_color);
        
        if ($stmt->execute()) {
            $category_id = $conn->insert_id;
            echo json_encode([
                "id" => $category_id, 
                "name" => $new_category,
                "color" => $category_color
            ]); 
            exit(); // Stop further execution
        } else {
            echo json_encode([
                "message" => "Error adding category: " . $conn->error
            ]);
            exit();
        }
        $stmt->close();
    } else {
        echo json_encode(["message" => "Category name cannot be empty"]);
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_task'])) {
    $task_name = trim($_POST['task_name']);
    $description = trim($_POST['description']);
    $due_date = $_POST['due_date'];
    $due_time = $_POST['due_time']; // Get the time value
    $category_id = $_POST['category'];
    $status = $_POST['status'];
    $priority = $_POST['priority'];
    $custom_remind = $_POST['custom_remind']; // Get the reminder value

    // Track missing fields
    $missing_fields = [];

    if (empty($task_name)) $missing_fields[] = "task_name";
    if (empty($description)) $missing_fields[] = "description";
    if (empty($due_date)) $missing_fields[] = "due_date";
    if (empty($due_time)) $missing_fields[] = "due_time"; // Ensure time is also checked
    if (empty($category_id)) $missing_fields[] = "category";

    if (!empty($missing_fields)) {
        echo json_encode(["success" => false, "missing_fields" => $missing_fields]);
        exit(); // Ensure script stops here
    }
    
    // Determine complete_date based on status
    $complete_date = ($status == "Done") ? date("Y-m-d") : NULL;

    // Insert task into database, now including custom_remind
    $sql = "INSERT INTO tasks (user_id, task_name, description, due_date, due_time, category_id, status, priority, complete_date, custom_remind) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssi", $user_id, $task_name, $description, $due_date, $due_time, $category_id, $status, $priority, $complete_date, $custom_remind);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]); // Success response
        exit(); // Stop script
    } else {
        echo json_encode(["success" => false, "message" => "Error adding task."]);
        exit(); // Stop script
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Task</title>
    <link rel="stylesheet" href="styles.css">
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
            display: flex;
            flex-direction: column;
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
        
        /* Main content styles */
        .page-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            flex: 1;
            padding: 20px;
        }
        
        .task-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 350px;
            margin-top: 20px;
        }
        
        h2 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        
        .calendar-container {
            display: flex;
            align-items: center;
        }
        
        .calendar-container input {
            flex: 1;
        }
        
        button {
            width: 100%;
            padding: 10px;
            margin-top: 15px;
            background: #25a18e;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        
        button:hover {
            background: #1c7d6d;
        }
        
        .time-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .time-container select {
            width: 30%;
        }
        
        .time-container span {
            font-size: 18px;
        }
        
        .error {
            color: red;
            text-align: center;
        }
        
        label.error {
            color: red;
        }
        
        /* Color picker styles */
        input[type="color"] {
            width: 100%;
            height: 40px;
            padding: 2px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: white;
            cursor: pointer;
        }
        
        #category option {
            background-color: white !important;
            color: black !important;
            font-weight: normal;
        }
        
        .color-picker-container {
            position: relative;
            margin-bottom: 15px;
        }
        
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 1px solid #ccc;
            position: absolute;
            right: 10px;
            top: 5px;
            pointer-events: none;
        }
        
        select {
            padding-right: 30px;
        }
        
        .custom-select-wrapper {
            position: relative;
            width: 100%;
        }
        
        .custom-select {
            width: 100%;
            padding: 8px 8px 8px 30px;
            border: 1px solid #ccc;
            border-radius: 4px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="black" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-color: white;
        }
        
        .color-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            z-index: 1;
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        .custom-option {
            display: flex;
            align-items: center;
            padding: 8px;
        }
        
        .custom-option-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            margin-right: 8px;
            display: inline-block;
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        select#category {
            background-color: white !important;
            color: black !important;
        }
        
        .dropdown {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .dropbtn {
            width: 100%;
            padding: 8px 8px 8px 30px;
            background-color: white;
            border: 1px solid #ccc;
            border-radius: 4px;
            cursor: pointer;
            text-align: left;
            position: relative;
            color: black;
            font-weight: normal;
        }
        
        .dropbtn::after {
            content: '';
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 5px solid #333;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: white;
            width: 100%;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 10;
            max-height: 250px;
            overflow-y: auto;
            border-radius: 4px;
            margin-top: 2px;
        }
        
        .dropdown-option {
            color: black;
            padding: 8px 8px 8px 30px;
            text-decoration: none;
            display: block;
            cursor: pointer;
            position: relative;
        }
        
        .dropdown-option:hover {
            background-color: #f1f1f1;
        }
        
        .option-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        .show {
            display: block;
        }
        
        .hidden-select {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
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
			position: fixed;
			bottom: 0;
			left: 0;
			right: 0;
			text-align: center;
			color: #666;
			font-size: 12px;
			padding: 5px;
			z-index: 1;
			background-color: rgba(255, 255, 255, 0.7); /* Adding a semi-transparent background */
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
    <!-- Main content section -->
    <div class="page-content">
        <h2>Add Task</h2>
        <div class="task-container">
            <?php if ($error_message): ?>
                <p class="error"><?php echo $error_message; ?></p>
            <?php endif; ?>

            <form method="POST">
                <label for="task_name">Task Name:</label>
                <input type="text" id="task_name" name="task_name">

                <label for="description">Description:</label>
                <textarea id="description" name="description" rows="3"></textarea>

                <label for="due_date">Due Date:</label>
                <input type="date" id="due_date" name="due_date">

                <label for="due_time">Due Time:</label>
                <div class="time-container">
                    <select id="due_hour" name="due_hour" onchange="updateAMPM()">
                        <script>
                            for (let i = 0; i < 24; i++) {
                                let hour = i.toString().padStart(2, '0');
                                document.write(`<option value="${hour}">${hour}</option>`);
                            }
                        </script>
                    </select>
                    <span>:</span>
                    <select id="due_minute" name="due_minute">
                        <script>
                            for (let i = 0; i < 60; i += 5) {
                                let minute = i.toString().padStart(2, '0');
                                document.write(`<option value="${minute}">${minute}</option>`);
                            }
                        </script>
                    </select>
                    <span id="ampm">AM</span>
                </div>

                <label for="category">Category:</label>
                <!-- Custom dropdown implementation -->
                <div class="dropdown" id="category-dropdown">
                    <button type="button" class="dropbtn" id="dropdown-btn">Select a category</button>
                    <div id="dropdown-content" class="dropdown-content">
                        <!-- Dropdown options will be populated via JavaScript -->
                    </div>
                    <select id="category" name="category" class="hidden-select">
                        <option value="">Select a category</option>
                        <?php echo $category_options; ?>
                    </select>
                </div>

                <label>or</label>
                <label for="new_category">Create new category:</label>
                <input type="text" id="new_category" name="new_category">

                <label for="category_color">Select Category Color:</label>
                <div class="color-picker-container">
                    <input type="color" id="category_color" name="category_color" value="#FFFFFF">
                </div>

                <button type="button" id="add_category_btn">Add Category</button>

                <label for="status">Status:</label>
                <select id="status" name="status">
                    <option value="Pending">Pending</option>
                    <option value="Ongoing">Ongoing</option>
                    <option value="Done">Done</option>
                </select>

                <label for="priority">Priority:</label>
                <select id="priority" name="priority">
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                </select>
                
                <label for="custom_remind">Reminder:</label>
                <select id="custom_remind" name="custom_remind">
                    <option value="0">Off</option>
                    <option value="1">5 minutes before</option>
                    <option value="2">15 minutes before</option>
                    <option value="3">30 minutes before</option>
                    <option value="4">1 hour before</option>
                    <option value="5">2 hours before</option>
                    <option value="6">1 day before</option>
                    <option value="7">2 days before</option>
                    <option value="8">1 week before</option>
                    <option value="9">2 weeks before</option>
                    <option value="10">1 month before</option>
                </select>

                <button type="submit" name="add_task">Create Task</button>
            </form>
        </div>
    </div>
	<div class="footer">
		<p>This business is fictitious and part of a university course.</p>
	</div>
	<script>
	// Update the current time every second
        function updateTime() {
            const now = new Date();
            let hours = now.getHours().toString().padStart(2, '0');
            let minutes = now.getMinutes().toString().padStart(2, '0');
            let seconds = now.getSeconds().toString().padStart(2, '0');
            document.getElementById('current-time').textContent = `${hours}:${minutes}:${seconds}`;
        }
        
        // Set initial time and start timer
        updateTime();
        setInterval(updateTime, 1000);
		
	</script>

    <script>
        function updateAMPM() {
            let hour = parseInt(document.getElementById("due_hour").value);
            document.getElementById("ampm").textContent = hour < 12 ? "AM" : "PM";
        }
    </script>
	
	<script>
        document.addEventListener("DOMContentLoaded", function() {
		const select = document.getElementById("category");
		const dropdownBtn = document.getElementById("dropdown-btn");
		const dropdownContent = document.getElementById("dropdown-content");
		
		// Function to update the dropdown button text and dot
		function updateDropdownButton() {
			const selectedOption = select.options[select.selectedIndex];
			const buttonText = selectedOption.textContent;
			
			// Always set text color to black for readability
			dropdownBtn.style.color = "black";
			
			if (selectedOption.value) {
				// If an option with value is selected, show the dot
				const color = selectedOption.getAttribute("data-color") || "#FFFFFF";
				dropdownBtn.innerHTML = `<span class="option-dot" style="background-color: ${color};"></span>${buttonText}`;
			} else {
				// No selection, just show text
				dropdownBtn.textContent = buttonText;
			}
		}
		
		// Initialize dropdown options
		function populateDropdownOptions() {
			// Clear existing options
			dropdownContent.innerHTML = "";
			
			// Add all options from select
			for (let i = 0; i < select.options.length; i++) {
				const option = select.options[i];
				const div = document.createElement("div");
				div.className = "dropdown-option";
				div.textContent = option.textContent;
				div.setAttribute("data-value", option.value);
				div.style.color = "black"; // Ensure text is black
				
				// Add color dot if option has a color
				if (option.value && option.getAttribute("data-color")) {
					const color = option.getAttribute("data-color");
					div.innerHTML = `<span class="option-dot" style="background-color: ${color};"></span>${option.textContent}`;
				}
				
				// On click, update the select and dropdown button
				div.addEventListener("click", function() {
					select.value = this.getAttribute("data-value");
					updateDropdownButton();
					dropdownContent.classList.remove("show");
					
					// Trigger change event on select
					const event = new Event("change", { bubbles: true });
					select.dispatchEvent(event);
				});
				
				dropdownContent.appendChild(div);
			}
		}
		
		// Toggle dropdown when button is clicked
		dropdownBtn.addEventListener("click", function() {
			dropdownContent.classList.toggle("show");
		});
		
		// Close dropdown when clicking outside
		window.addEventListener("click", function(event) {
			if (!event.target.matches('.dropbtn') && !event.target.closest('#dropdown-content')) {
				if (dropdownContent.classList.contains("show")) {
					dropdownContent.classList.remove("show");
				}
			}
		});
		
		// Initialize dropdown
		populateDropdownOptions();
		updateDropdownButton();
		
		// Update dropdown when new category is added
		const addCategoryBtn = document.getElementById("add_category_btn");
		
		addCategoryBtn.addEventListener("click", function(event) {
			event.preventDefault(); // Prevent default form submission

			let newCategory = document.getElementById("new_category").value.trim();
			let categoryColor = document.getElementById("category_color").value;
			
			if (newCategory === "") {
				alert("Category name cannot be empty!");
				return;
			}

			// Create a FormData object for better data handling
			let formData = new FormData();
			formData.append("add_category", "1");
			formData.append("new_category", newCategory);
			formData.append("category_color", categoryColor);

			// Disable button to prevent multiple submissions
			this.disabled = true;
			
			let xhr = new XMLHttpRequest();
			xhr.open("POST", window.location.href, true);
			
			xhr.onreadystatechange = function() {
				if (xhr.readyState === 4 && xhr.status === 200) {
					try {
						// Extract just the JSON part of the response
						let responseText = xhr.responseText;
						let jsonStartPos = responseText.indexOf('{');
						let jsonEndPos = responseText.lastIndexOf('}') + 1;
						let jsonResponse = responseText.substring(jsonStartPos, jsonEndPos);
						
						let response = JSON.parse(jsonResponse);
						
						if (response.id) {
							// Add option to original select element
							let categorySelect = document.getElementById("category");
							let newOption = document.createElement("option");
							newOption.value = response.id;
							newOption.textContent = response.name;
							newOption.dataset.color = response.color || categoryColor;
							categorySelect.appendChild(newOption);
							
							// Select the new option
							categorySelect.value = response.id;
							
							// Update the custom dropdown
							dropdownBtn.innerHTML = `<span class="option-dot" style="background-color: ${categoryColor};"></span>${newCategory}`;
							dropdownBtn.style.color = "black"; // Ensure text is visible
							
							// Re-populate dropdown options
							populateDropdownOptions();
							
							// Clear form fields
							document.getElementById("new_category").value = "";
							document.getElementById("category_color").value = "#FFFFFF";
							document.getElementById("color_preview").style.backgroundColor = "#FFFFFF";
						} else {
							alert(response.message || "Error adding category.");
						}
					} catch (error) {
						console.error("Error parsing JSON:", error);
						console.log("Raw response:", xhr.responseText);
						// Don't show an error alert here - we know it actually worked
					} finally {
						// Re-enable the button
						document.getElementById("add_category_btn").disabled = false;
					}
				}
			};

			xhr.send(formData);
		});
	});
    </script>
	
	<script>
        // Initialize color dots for the dropdown
        document.addEventListener("DOMContentLoaded", function() {
            const categorySelect = document.getElementById("category");
            const colorDot = document.getElementById("category-color-dot");
            
            // Function to update the selected color dot
            function updateSelectedColorDot() {
                const selectedOption = categorySelect.options[categorySelect.selectedIndex];
                if (selectedOption && selectedOption.dataset.color && selectedOption.value !== "") {
                    colorDot.style.backgroundColor = selectedOption.dataset.color;
                    colorDot.style.display = "block";
                } else {
                    colorDot.style.display = "none";
                }
            }
            
            // Initial setup
            updateSelectedColorDot();
            
            // Update on change
            categorySelect.addEventListener("change", updateSelectedColorDot);
            
            // Set up the dropdown options with colored dots (for Chrome)
            if (window.chrome) {
                for (let option of categorySelect.options) {
                    if (option.dataset.color && option.value !== "") {
                        option.innerHTML = `<span class="custom-option"><span class="custom-option-dot" style="background-color: ${option.dataset.color}"></span>${option.textContent}</span>`;
                    }
                }
            }
        });
	</script>
	
	<script>
	
        // Modified Add Category button event handler
        document.getElementById("add_category_btn").addEventListener("click", function(event) {
            event.preventDefault(); // Prevent default form submission

            let newCategory = document.getElementById("new_category").value.trim();
            let categoryColor = document.getElementById("category_color").value;
            
            if (newCategory === "") {
                alert("Category name cannot be empty!");
                return;
            }

            // Create a FormData object for better data handling
            let formData = new FormData();
            formData.append("add_category", "1");
            formData.append("new_category", newCategory);
            formData.append("category_color", categoryColor);

            // Disable button to prevent multiple submissions
            this.disabled = true;
            
            let xhr = new XMLHttpRequest();
            xhr.open("POST", window.location.href, true);
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    try {
                        // Extract just the JSON part of the response
                        let responseText = xhr.responseText;
                        let jsonStartPos = responseText.indexOf('{');
                        let jsonEndPos = responseText.lastIndexOf('}') + 1;
                        let jsonResponse = responseText.substring(jsonStartPos, jsonEndPos);
                        
                        let response = JSON.parse(jsonResponse);
                        
                        if (response.id) {
                            // Add option to original select element
                            let categorySelect = document.getElementById("category");
                            let newOption = document.createElement("option");
                            newOption.value = response.id;
                            newOption.textContent = response.name;
                            newOption.dataset.color = response.color || categoryColor;
                            categorySelect.appendChild(newOption);
                            
                            // Select the new option
                            categorySelect.value = response.id;
                            
                            // Update the custom dropdown
                            const dropdownBtn = document.getElementById("dropdown-btn");
                            dropdownBtn.innerHTML = `<span class="option-dot" style="background-color: ${categoryColor};"></span>${newCategory}`;
                            dropdownBtn.style.color = "black"; // Ensure text is visible
                            
                            // Re-populate dropdown options
                            const dropdownContent = document.getElementById("dropdown-content");
                            dropdownContent.innerHTML = "";
                            
                            for (let i = 0; i < categorySelect.options.length; i++) {
                                const option = categorySelect.options[i];
                                const div = document.createElement("div");
                                div.className = "dropdown-option";
                                div.textContent = option.textContent;
                                div.setAttribute("data-value", option.value);
                                div.style.color = "black"; // Ensure text is black
                                
                                if (option.value && option.dataset.color) {
                                    const color = option.dataset.color;
                                    div.innerHTML = `<span class="option-dot" style="background-color: ${color};"></span>${option.textContent}`;
                                }
                                
                                div.addEventListener("click", function() {
                                    categorySelect.value = this.getAttribute("data-value");
                                    
                                    // Update dropdown button
                                    if (this.getAttribute("data-value")) {
                                        const color = option.dataset.color || "#FFFFFF";
                                        dropdownBtn.innerHTML = `<span class="option-dot" style="background-color: ${color};"></span>${option.textContent}`;
                                    } else {
                                        dropdownBtn.textContent = option.textContent;
                                    }
                                    dropdownBtn.style.color = "black";
                                    
                                    // Hide dropdown
                                    dropdownContent.classList.remove("show");
                                    
                                    // Trigger change event
                                    const event = new Event("change", { bubbles: true });
                                    categorySelect.dispatchEvent(event);
                                });
                                
                                dropdownContent.appendChild(div);
                            }
                            
                            // Clear form fields
                            document.getElementById("new_category").value = "";
                            document.getElementById("category_color").value = "#FFFFFF";
                            document.getElementById("color_preview").style.backgroundColor = "#FFFFFF";
                        } else {
                            alert(response.message || "Error adding category.");
                        }
                    } catch (error) {
                        console.error("Error parsing JSON:", error);
                        console.log("Raw response:", xhr.responseText);
                        // Don't show an error alert here - we know it actually worked
                    } finally {
                        // Re-enable the button
                        document.getElementById("add_category_btn").disabled = false;
                    }
                }
            };

            xhr.send(formData);
        });
		
	// Helper function to determine if a color is light or dark
	function isLightColor(color) {
		// Convert hex to RGB
		const hex = color.replace('#', '');
		const r = parseInt(hex.substr(0, 2), 16);
		const g = parseInt(hex.substr(2, 2), 16);
		const b = parseInt(hex.substr(4, 2), 16);
		
		// Calculate brightness (perceived luminance)
		// Using the formula: (0.299*R + 0.587*G + 0.114*B)
		const brightness = (r * 0.299 + g * 0.587 + b * 0.114);
		
		// Return true if the color is light (brightness > 128)
		return brightness > 128;
	}
	</script>

	<script>
	// Add this to your JavaScript section
	document.getElementById("category_color").addEventListener("input", function() {
		document.getElementById("color_preview").style.backgroundColor = this.value;
	});

	// Initialize the preview on page load
	document.addEventListener("DOMContentLoaded", function() {
		document.getElementById("color_preview").style.backgroundColor = 
			document.getElementById("category_color").value;
	});

	// Update existing categories with colors
	document.addEventListener("DOMContentLoaded", function() {
		const categorySelect = document.getElementById("category");
		for(let i = 0; i < categorySelect.options.length; i++) {
			const option = categorySelect.options[i];
			if(option.dataset.color) {
				option.style.backgroundColor = option.dataset.color;
				option.style.color = isLightColor(option.dataset.color) ? 'black' : 'white';
			}
		}
	});
	</script>

	<script>
	document.querySelector("form").addEventListener("submit", function(event) {
		event.preventDefault(); // Prevent full page reload

		// Reset previous error indicators first
		document.querySelectorAll("label").forEach(label => {
			label.innerHTML = label.innerHTML.replace(" *", ""); 
			label.classList.remove("error");
		});

		let hour = document.getElementById("due_hour").value;
		let minute = document.getElementById("due_minute").value;

		let dueTime = `${hour}:${minute}:00`; // Format to HH:MM:SS (SQL TIME format)

		let formData = new FormData(this);
		formData.append("due_time", dueTime); // Append the combined time value
		formData.append("add_task", "1"); // Make sure this is included

		let xhr = new XMLHttpRequest();
		xhr.open("POST", window.location.href, true);

		xhr.onreadystatechange = function() {
			if (xhr.readyState === 4) {
				console.log("Raw response:", xhr.responseText); // Debugging

				if (xhr.status === 200) {
					try {
						let response = JSON.parse(xhr.responseText);
						console.log("Parsed response:", response); // Debugging

						if (!response.success) {
							if (response.missing_fields && response.missing_fields.length > 0) {
								// Add red stars to labels of missing fields
								response.missing_fields.forEach(field => {
									let label = document.querySelector(`label[for="${field}"]`);
									if (label) {
										label.innerHTML += " <span style='color:red'>*</span>";
										label.classList.add("error");
									}
								});
								alert("Please fill in all required fields marked with *");
							} else {
								alert(response.message || "Error adding task.");
							}
						} else {
							alert("Task Added Successfully!");
							window.location.href = "Indi_TaskList.php"; // Redirect on success
						}
					} catch (error) {
						console.error("Error parsing JSON:", error);
						console.log("Response received:", xhr.responseText);
						alert("Unexpected error. Please check console for details.");
					}
				} else {
					console.error("HTTP Error:", xhr.status, xhr.statusText);
					alert("Unexpected server error. Check console for details.");
				}
			}
		};

		xhr.send(formData);
	});
	</script>

</body>
</html>

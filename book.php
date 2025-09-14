<?php
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$conn = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_NAME']);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Get form inputs
$name = $_POST['name'];
$phone = $_POST['phone'];
$aadhar = $_POST['aadhar'];
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];
$checkin_time = $_POST['checkin_time'];
$rooms = (int)$_POST['rooms'];
$people = (int)$_POST['people'];

// Validate date range
if (strtotime($end_date) < strtotime($start_date)) {
    echo "<script>alert('End date must be after start date'); window.location.href='home.html';</script>";
    exit();
}

// Calculate number of days
$days = (strtotime($end_date) - strtotime($start_date)) / (60*60*24) + 1;
$total_cost = $people * 1400 * $days;

// Check availability for each day
$check = $conn->prepare("SELECT start_date, end_date, rooms_booked FROM bookings WHERE status='confirmed'");
$check->execute();
$res = $check->get_result();

while ($row = $res->fetch_assoc()) {
    $booked_start = strtotime($row['start_date']);
    $booked_end = strtotime($row['end_date']);
    $requested_start = strtotime($start_date);
    $requested_end = strtotime($end_date);

    // If date ranges overlap
    if ($requested_start <= $booked_end && $requested_end >= $booked_start) {
        // Sum rooms booked
        $rooms_already = $row['rooms_booked'];
        if (($rooms_already + $rooms) > 2) {
            echo "<script>alert('Rooms unavailable for one or more days in the selected range.'); window.location.href='home.html';</script>";
            exit();
        }
    }
}

// Store booking as pending
$stmt = $conn->prepare("INSERT INTO bookings 
(name, phone, aadhar, start_date, end_date, checkin_time, rooms_booked, people, total_cost) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssiii", $name, $phone, $aadhar, $start_date, $end_date, $checkin_time, $rooms, $people, $total_cost);
$stmt->execute();

echo "<script>alert('Booking submitted. You will receive confirmation via WhatsApp shortly.'); window.location.href='home.html';</script>";
$conn->close();
?>

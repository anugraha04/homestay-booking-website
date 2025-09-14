<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}
?>

<?php
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$conn = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_NAME']);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Handle confirm/cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $action = $_POST['action'];
    $status = ($action === 'confirm') ? 'confirmed' : 'cancelled';
    $conn->query("UPDATE bookings SET status='$status' WHERE id=$id");

    // Fetch booking details for message
    $res = $conn->query("SELECT name, phone, start_date, end_date FROM bookings WHERE id=$id");
    $booking = $res->fetch_assoc();

    $name = $booking['name'];
    $phone = $booking['phone'];
    $start_date = $booking['start_date'];
    $end_date = $booking['end_date'];

    // WhatsApp Cloud API Setup
    $phone_id = $_ENV['WHATSAPP_PHONE_ID'];
    $token    = $_ENV['WHATSAPP_TOKEN'];
    $customer_phone = "91".$phone;      // Ensure $phone is stored as 10 digits in DB

    // Prepare WhatsApp payload using templates
    if ($status === 'confirmed') {
        // Booking Confirmation Template
        $payload = [
          "messaging_product" => "whatsapp",
          "to" => $customer_phone,
          "type" => "template",
          "template" => [
            "name" => "booking_confirmations",
            "language" => [ "code" => "en_US" ],
            "components" => [[
              "type" => "body",
              "parameters" => [
                [ "type" => "text", "text" => $name ],
                [ "type" => "text", "text" => $start_date ],
                [ "type" => "text", "text" => $end_date ]
              ]
            ]]
          ]
        ];
    } else {
        // Booking Cancellation Template
        $reason = htmlspecialchars($_POST['cancel_msg']);
        $payload = [
          "messaging_product" => "whatsapp",
          "to" => $customer_phone,
          "type" => "template",
          "template" => [
            "name" => "booking_cancellation",
            "language" => [ "code" => "en_US" ],
            "components" => [[
              "type" => "body",
              "parameters" => [
                [ "type" => "text", "text" => $name ],
                [ "type" => "text", "text" => $start_date ],
                [ "type" => "text", "text" => $end_date ],
                [ "type" => "text", "text" => $reason ]
              ]
            ]]
          ]
        ];
    }

    // Send WhatsApp message
    $url = "https://graph.facebook.com/v22.0/765433006654124/messages";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    if ($response === false) {
    echo "<pre>cURL Error: " . curl_error($ch) . "</pre>";
    } else {
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "<pre>WhatsApp API Response ($httpcode): $response</pre>";
   }

    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    error_log("WhatsApp API response ($httpcode): " . $response);

    if ($response === false) {
        error_log("WhatsApp API error: " . curl_error($ch));
    }
    curl_close($ch);

    echo "<script>alert('Booking $status.'); window.location.href='admin.php';</script>";
}

// Fetch all bookings
$result = $conn->query("SELECT * FROM bookings ORDER BY start_date");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #999; padding: 8px; text-align: center; }
        .status-confirmed { color: green; font-weight: bold; }
        .status-cancelled { color: red; font-weight: bold; }
        .status-pending { color: orange; font-weight: bold; }
    </style>
</head>
<body>
<center><h2>Admin Dashboard</h2></center>

<a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
<br><br>

<table>
<tr>
  <th>Name</th>
  <th>Phone</th>
  <th>Aadhar</th>
  <th>Dates</th>
  <th>Check-in</th>
  <th>Rooms</th>
  <th>People</th>
  <th>Total Cost</th>
  <th>Status</th>
  <th>Action</th>
</tr>
<?php while ($row = $result->fetch_assoc()): ?>
<tr>
  <td><?= $row['name'] ?></td>
  <td><?= $row['phone'] ?></td>
  <td><?= $row['aadhar'] ?></td>
  <td><?= $row['start_date'] ?> to <?= $row['end_date'] ?></td>
  <td><?= $row['checkin_time'] ?></td>
  <td><?= $row['rooms_booked'] ?></td>
  <td><?= $row['people'] ?></td>
  <td>₹<?= $row['total_cost'] ?></td>
  <td class="status-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></td>
  <td>
    <?php if ($row['status'] === 'pending'): ?>
      <form method="POST" style="display:inline-block;">
        <input type="hidden" name="id" value="<?= $row['id'] ?>">
        <button name="action" value="confirm">✅ Confirm</button>
      </form>
      <form method="POST" style="display:inline-block;">
        <input type="hidden" name="id" value="<?= $row['id'] ?>">
        <input type="text" name="cancel_msg" placeholder="Reason" required>
        <button name="action" value="cancel">❌ Cancel</button>
      </form>
    <?php endif; ?>
  </td>
</tr>
<?php endwhile; ?>
</table>
</body>
</html>

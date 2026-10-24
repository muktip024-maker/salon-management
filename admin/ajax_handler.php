<?php
/**
 * AJAX Endpoint Handler
 * Salon Management System
 * Executive Procedural MySQLi Architecture ONLY
 * Robust Server-Side PHP Validation for All Form Controls
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

if (!verify_csrf_token($token)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid security token (CSRF). Please refresh and try again.']);
    exit();
}

// --------------------------------------------------------------------------
// 1. APPOINTMENT ACTIONS (ADD, EDIT, STATUS, DELETE)
// --------------------------------------------------------------------------
if ($action === 'add' || $action === 'edit') {
    $appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $appointment_date = isset($_POST['appointment_date']) ? mysqli_real_escape_string($conn, trim($_POST['appointment_date'])) : '';
    $appointment_time = isset($_POST['appointment_time']) ? mysqli_real_escape_string($conn, trim($_POST['appointment_time'])) : '';
    $status = isset($_POST['status']) ? mysqli_real_escape_string($conn, trim($_POST['status'])) : 'Pending';
    $notes = isset($_POST['notes']) ? mysqli_real_escape_string($conn, trim($_POST['notes'])) : '';
    $selected_services = isset($_POST['services']) && is_array($_POST['services']) ? $_POST['services'] : [];
    $selected_staff = isset($_POST['staff']) && is_array($_POST['staff']) ? $_POST['staff'] : [];

    // Server-Side PHP Validation
    if ($action === 'edit' && $appointment_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid appointment ID parameter.']);
        exit();
    }

    if ($customer_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please select a valid customer.']);
        exit();
    } else {
        $c_chk = mysqli_query($conn, "SELECT customer_id FROM customers WHERE customer_id = $customer_id LIMIT 1");
        if (!$c_chk || mysqli_num_rows($c_chk) === 0) {
            echo json_encode(['status' => 'error', 'message' => 'The selected customer does not exist.']);
            exit();
        }
    }

    if (empty($appointment_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide a valid appointment date (YYYY-MM-DD).']);
        exit();
    }

    if (empty($appointment_time)) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide a valid appointment time.']);
        exit();
    }

    $allowed_statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
    if (!in_array($status, $allowed_statuses)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid appointment status selected.']);
        exit();
    }

    if (count($selected_services) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please select at least one service.']);
        exit();
    }

    if (strlen($notes) > 500) {
        echo json_encode(['status' => 'error', 'message' => 'Notes cannot exceed 500 characters.']);
        exit();
    }

    // Process Transaction (Procedural MySQLi)
    mysqli_query($conn, "START TRANSACTION");
    $success = true;

    if ($action === 'add') {
        $appt_sql = "INSERT INTO appointments (customer_id, appointment_date, appointment_time, status, notes) 
                     VALUES ($customer_id, '$appointment_date', '$appointment_time', '$status', '$notes')";
        if (mysqli_query($conn, $appt_sql)) {
            $appointment_id = mysqli_insert_id($conn);
        } else {
            $success = false;
        }
    } else {
        $update_sql = "UPDATE appointments SET customer_id = $customer_id, appointment_date = '$appointment_date', appointment_time = '$appointment_time', status = '$status', notes = '$notes' WHERE appointment_id = $appointment_id";
        if (mysqli_query($conn, $update_sql)) {
            mysqli_query($conn, "DELETE FROM appointment_services WHERE appointment_id = $appointment_id");
            mysqli_query($conn, "DELETE FROM appointment_staff WHERE appointment_id = $appointment_id");
        } else {
            $success = false;
        }
    }

    if ($success && $appointment_id > 0) {
        // Insert Services
        foreach ($selected_services as $srv_id) {
            $srv_id_int = (int)$srv_id;
            $price_query = mysqli_query($conn, "SELECT price FROM services WHERE service_id = $srv_id_int LIMIT 1");
            if ($price_query && $price_row = mysqli_fetch_assoc($price_query)) {
                $service_price = (float)$price_row['price'];
                $insert_srv = "INSERT INTO appointment_services (appointment_id, service_id, service_price) 
                               VALUES ($appointment_id, $srv_id_int, $service_price)";
                if (!mysqli_query($conn, $insert_srv)) {
                    $success = false;
                    break;
                }
            } else {
                $success = false;
                break;
            }
        }

        // Insert Staff
        if ($success && count($selected_staff) > 0) {
            foreach ($selected_staff as $stf_id) {
                $stf_id_int = (int)$stf_id;
                $insert_stf = "INSERT INTO appointment_staff (appointment_id, staff_id) 
                               VALUES ($appointment_id, $stf_id_int)";
                if (!mysqli_query($conn, $insert_stf)) {
                    $success = false;
                    break;
                }
            }
        }
    }

    if ($success) {
        mysqli_query($conn, "COMMIT");
        $msg = ($action === 'add') ? 'Appointment #' . $appointment_id . ' booked successfully!' : 'Appointment #' . $appointment_id . ' updated successfully!';
        echo json_encode(['status' => 'success', 'message' => $msg]);
    } else {
        mysqli_query($conn, "ROLLBACK");
        echo json_encode(['status' => 'error', 'message' => 'Database operation failed. Transaction rolled back.']);
    }
    exit();
}

// Quick Status Update
if ($action === 'update_status') {
    $appointment_id = (int)$_POST['appointment_id'];
    $new_status = mysqli_real_escape_string($conn, trim($_POST['status']));
    $allowed_statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];

    if ($appointment_id <= 0 || !in_array($new_status, $allowed_statuses)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid status parameters.']);
        exit();
    }

    $sql = "UPDATE appointments SET status = '$new_status' WHERE appointment_id = $appointment_id";
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['status' => 'success', 'message' => 'Appointment #' . $appointment_id . ' status updated to ' . $new_status . '!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update status.']);
    }
    exit();
}

// --------------------------------------------------------------------------
// 2. CUSTOMER ACTIONS (ADD, EDIT, DELETE)
// --------------------------------------------------------------------------
if ($action === 'add_customer' || $action === 'edit_customer') {
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $password = trim($_POST['password']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    if (empty($name) || strlen($name) < 2) {
        echo json_encode(['status' => 'error', 'message' => 'Full name must be at least 2 characters.']);
        exit();
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.']);
        exit();
    }

    $clean_phone = preg_replace('/[\s\-\(\)\+]/', '', $phone);
    if (empty($phone) || !preg_match('/^\d{7,15}$/', $clean_phone)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid 10-15 digit phone number.']);
        exit();
    }

    if ($action === 'add_customer' && (empty($password) || strlen($password) < 6)) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters.']);
        exit();
    }

    // Check Uniqueness
    $uniq_sql = ($action === 'add_customer') 
        ? "SELECT customer_id FROM customers WHERE email = '$email' OR phone = '$phone' LIMIT 1"
        : "SELECT customer_id FROM customers WHERE (email = '$email' OR phone = '$phone') AND customer_id != $customer_id LIMIT 1";
    $uniq_res = mysqli_query($conn, $uniq_sql);
    if ($uniq_res && mysqli_num_rows($uniq_res) > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email address or phone number is already registered.']);
        exit();
    }

    if ($action === 'add_customer') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $hash_escaped = mysqli_real_escape_string($conn, $hash);
        $sql = "INSERT INTO customers (name, email, phone, password, gender, address, status) VALUES ('$name', '$email', '$phone', '$hash_escaped', '$gender', '$address', '$status')";
    } else {
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $hash_escaped = mysqli_real_escape_string($conn, $hash);
            $sql = "UPDATE customers SET name = '$name', email = '$email', phone = '$phone', password = '$hash_escaped', gender = '$gender', address = '$address', status = '$status' WHERE customer_id = $customer_id";
        } else {
            $sql = "UPDATE customers SET name = '$name', email = '$email', phone = '$phone', gender = '$gender', address = '$address', status = '$status' WHERE customer_id = $customer_id";
        }
    }

    if (mysqli_query($conn, $sql)) {
        $msg = ($action === 'add_customer') ? 'Customer added successfully!' : 'Customer updated successfully!';
        echo json_encode(['status' => 'success', 'message' => $msg]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
    exit();
}

// --------------------------------------------------------------------------
// 3. SERVICES CATALOG ACTIONS (ADD, EDIT)
// --------------------------------------------------------------------------
if ($action === 'add_service' || $action === 'edit_service') {
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $service_name = mysqli_real_escape_string($conn, trim($_POST['service_name']));
    $price = isset($_POST['price']) ? (float)$_POST['price'] : -1;
    $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 0;
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));

    if ($category_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please select a valid service category.']);
        exit();
    }

    if (empty($service_name) || strlen($service_name) < 2) {
        echo json_encode(['status' => 'error', 'message' => 'Service name must be at least 2 characters.']);
        exit();
    }

    if ($price < 0) {
        echo json_encode(['status' => 'error', 'message' => 'Price must be a valid non-negative number.']);
        exit();
    }

    if ($duration <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Duration must be greater than 0 minutes.']);
        exit();
    }

    if ($action === 'add_service') {
        $sql = "INSERT INTO services (category_id, service_name, description, price, duration, status) 
                VALUES ($category_id, '$service_name', '$description', $price, $duration, '$status')";
    } else {
        $sql = "UPDATE services SET category_id = $category_id, service_name = '$service_name', description = '$description', price = $price, duration = $duration, status = '$status' WHERE service_id = $service_id";
    }

    if (mysqli_query($conn, $sql)) {
        $msg = ($action === 'add_service') ? 'Service added successfully!' : 'Service updated successfully!';
        echo json_encode(['status' => 'success', 'message' => $msg]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action or unhandled request.']);
exit();

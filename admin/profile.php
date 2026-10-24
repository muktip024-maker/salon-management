<?php
/**
 * Admin Profile & Account Settings
 * Salon Management System
 * Executive Dribbble SaaS Redesign (Matches appointments.php, customers.php & staff.php)
 * Uses Procedural MySQLi ONLY
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

$page_title = "Admin Profile";
$current_page = "profile";

$admin_id = (int)$_SESSION['admin_id'];

// Helper function for initials
if (!function_exists('get_initials')) {
    function get_initials($name) {
        $words = explode(' ', trim($name));
        $initials = '';
        foreach ($words as $w) {
            if (!empty($w)) $initials .= strtoupper($w[0]);
        }
        return substr($initials, 0, 2) ?: 'A';
    }
}

// Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($token)) {
        set_flash('danger', 'Invalid CSRF token. Operation cancelled.');
        header("Location: profile.php");
        exit();
    }

    // 1. UPDATE PROFILE INFORMATION
    if ($action === 'update_profile') {
        $name = mysqli_real_escape_string($conn, trim($_POST['name']));
        $email = mysqli_real_escape_string($conn, trim($_POST['email']));

        if (empty($name) || empty($email)) {
            set_flash('danger', 'Name and Email are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'Invalid email address format.');
        } else {
            // Check unique email among other admins
            $check_sql = "SELECT admin_id FROM admins WHERE email = '$email' AND admin_id != $admin_id LIMIT 1";
            $check_res = mysqli_query($conn, $check_sql);

            if ($check_res && mysqli_num_rows($check_res) > 0) {
                set_flash('danger', 'This email address is already in use by another admin account.');
            } else {
                $update_sql = "UPDATE admins SET name = '$name', email = '$email' WHERE admin_id = $admin_id";
                if (mysqli_query($conn, $update_sql)) {
                    set_flash('success', 'Profile updated successfully.');
                    // Refresh current admin session variable
                    $current_admin['name'] = $name;
                    $current_admin['email'] = $email;
                } else {
                    set_flash('danger', 'Failed to update profile.');
                }
            }
        }
        header("Location: profile.php");
        exit();
    }

    // 2. CHANGE PASSWORD
    if ($action === 'change_password') {
        $current_password = trim($_POST['current_password']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            set_flash('danger', 'All password fields are required.');
        } elseif ($new_password !== $confirm_password) {
            set_flash('danger', 'New password and confirmation password do not match.');
        } elseif (strlen($new_password) < 6) {
            set_flash('danger', 'New password must be at least 6 characters long.');
        } else {
            // Verify current password from database
            $pass_sql = "SELECT password FROM admins WHERE admin_id = $admin_id LIMIT 1";
            $pass_res = mysqli_query($conn, $pass_sql);

            if ($pass_res && $row = mysqli_fetch_assoc($pass_res)) {
                $stored_hash = $row['password'];
                $is_valid = password_verify($current_password, $stored_hash) || ($current_password === $stored_hash);

                if ($is_valid) {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $new_hash_escaped = mysqli_real_escape_string($conn, $new_hash);

                    $update_pass_sql = "UPDATE admins SET password = '$new_hash_escaped' WHERE admin_id = $admin_id";
                    if (mysqli_query($conn, $update_pass_sql)) {
                        set_flash('success', 'Password changed successfully.');
                    } else {
                        set_flash('danger', 'Failed to update password.');
                    }
                } else {
                    set_flash('danger', 'Current password is incorrect.');
                }
            }
        }
        header("Location: profile.php");
        exit();
    }
}

// Fetch fresh admin profile data
$res = mysqli_query($conn, "SELECT admin_id, name, email, status, created_at FROM admins WHERE admin_id = $admin_id LIMIT 1");
if ($res && $admin_data = mysqli_fetch_assoc($res)) {
    $current_admin = $admin_data;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="row g-4 mb-4">
    <!-- Left Side: Admin Profile Hero Card -->
    <div class="col-lg-4">
        <div class="table-card text-center p-4">
            <div class="position-relative d-inline-block mb-3">
                <div class="avatar-circle-sm bg-gold-subtle text-gold fw-bold shadow-sm" style="width: 86px; height: 86px; font-size: 2.2rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                    <?php echo get_initials($current_admin['name']); ?>
                </div>
                <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-light rounded-circle" style="width:16px; height:16px;" title="Online"></span>
            </div>
            
            <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($current_admin['name']); ?></h5>
            <p class="text-muted small mb-3"><i class="far fa-envelope me-1"></i><?php echo htmlspecialchars($current_admin['email']); ?></p>
            
            <div class="mb-4">
                <span class="status-pill-btn active">
                    <span class="status-dot dot-green"></span>
                    <span class="fw-bold fs-7">Active Super Administrator</span>
                </span>
            </div>

            <div class="border-top pt-3 text-start">
                <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                    <span class="text-muted small"><i class="fas fa-hashtag me-2 text-primary"></i>Admin ID</span>
                    <strong class="text-dark">#<?php echo $current_admin['admin_id']; ?></strong>
                </div>
                <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                    <span class="text-muted small"><i class="fas fa-user-shield me-2 text-gold"></i>Role Privilege</span>
                    <span class="badge bg-light text-dark border">Full System Access</span>
                </div>
                <div class="d-flex align-items-center justify-content-between py-2">
                    <span class="text-muted small"><i class="far fa-calendar-alt me-2 text-primary"></i>Account Created</span>
                    <span class="text-dark small font-weight-600"><?php echo date('M d, Y', strtotime($current_admin['created_at'])); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Side: Edit Profile & Password Forms -->
    <div class="col-lg-8">
        <!-- Update Info Form -->
        <div class="table-card mb-4">
            <div class="table-card-header p-3">
                <h5 class="table-card-title mb-0">
                    <i class="fas fa-user-edit text-primary me-2"></i>Update Profile Information
                </h5>
            </div>
            <div class="p-4 text-start">
                <form action="profile.php" method="POST" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="mb-3">
                        <label class="form-label-custom">Full Name *</label>
                        <input type="text" name="name" class="form-control form-control-custom" value="<?php echo htmlspecialchars($current_admin['name']); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label-custom">Email Address *</label>
                        <input type="email" name="email" class="form-control form-control-custom" value="<?php echo htmlspecialchars($current_admin['email']); ?>" required>
                    </div>

                    <button type="submit" class="btn btn-accent text-white font-weight-700 px-4 py-2 shadow-sm" style="border-radius:30px; font-size: 0.875rem;">
                        <i class="fas fa-save me-2"></i> Save Profile Details
                    </button>
                </form>
            </div>
        </div>

        <!-- Change Password Form -->
        <div class="table-card">
            <div class="table-card-header p-3">
                <h5 class="table-card-title mb-0">
                    <i class="fas fa-key text-warning me-2"></i>Change Password & Security
                </h5>
            </div>
            <div class="p-4 text-start">
                <form action="profile.php" method="POST" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label class="form-label-custom">Current Password *</label>
                        <input type="password" name="current_password" class="form-control form-control-custom" placeholder="••••••••" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label-custom">New Password *</label>
                            <input type="password" name="new_password" class="form-control form-control-custom" placeholder="••••••••" required>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label-custom">Confirm New Password *</label>
                            <input type="password" name="confirm_password" class="form-control form-control-custom" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-dark font-weight-700 px-4 py-2 shadow-sm" style="border-radius:30px; font-size: 0.875rem;">
                        <i class="fas fa-lock me-2"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

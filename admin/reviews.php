<?php
/**
 * Customer Review Management Module
 * Salon Management System
 * Executive Dribbble SaaS Redesign (Matches appointments.php, customers.php, staff.php & services.php)
 * Uses Procedural MySQLi ONLY
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

$page_title = "Customer Reviews";
$current_page = "reviews";

// Helper function for initials
if (!function_exists('get_initials')) {
    function get_initials($name) {
        $words = explode(' ', trim($name));
        $initials = '';
        foreach ($words as $w) {
            if (!empty($w)) $initials .= strtoupper($w[0]);
        }
        return substr($initials, 0, 2) ?: 'C';
    }
}

// Handle Actions (TOGGLE STATUS, DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($token)) {
        set_flash('danger', 'Invalid CSRF token. Operation cancelled.');
        header("Location: reviews.php");
        exit();
    }

    // 1. TOGGLE VISIBILITY STATUS
    if ($action === 'toggle_status') {
        $review_id = (int)$_POST['review_id'];
        $current_st = mysqli_real_escape_string($conn, $_POST['current_status']);
        $new_st = ($current_st === 'visible') ? 'hidden' : 'visible';

        $toggle_sql = "UPDATE reviews SET status = '$new_st' WHERE review_id = $review_id";
        if (mysqli_query($conn, $toggle_sql)) {
            set_flash('success', "Review visibility changed to " . ucfirst($new_st) . ".");
        } else {
            set_flash('danger', 'Failed to change review status.');
        }
        header("Location: reviews.php");
        exit();
    }

    // 2. DELETE REVIEW
    if ($action === 'delete') {
        $review_id = (int)$_POST['review_id'];
        if ($review_id > 0) {
            $delete_sql = "DELETE FROM reviews WHERE review_id = $review_id";
            if (mysqli_query($conn, $delete_sql)) {
                set_flash('success', 'Review deleted successfully.');
            } else {
                set_flash('danger', 'Failed to delete review.');
            }
        }
        header("Location: reviews.php");
        exit();
    }
}

// Executive Statistics Queries (Procedural MySQLi)
$stat_tot_rev = mysqli_query($conn, "SELECT COUNT(*) AS total FROM reviews");
$total_rev_count = ($stat_tot_rev && $r = mysqli_fetch_assoc($stat_tot_rev)) ? (int)$r['total'] : 0;

$stat_avg_rat = mysqli_query($conn, "SELECT COALESCE(AVG(rating), 0.0) AS total FROM reviews");
$avg_rating_val = ($stat_avg_rat && $r = mysqli_fetch_assoc($stat_avg_rat)) ? (float)$r['total'] : 0.0;

$stat_5star = mysqli_query($conn, "SELECT COUNT(*) AS total FROM reviews WHERE rating = 5");
$five_star_count = ($stat_5star && $r = mysqli_fetch_assoc($stat_5star)) ? (int)$r['total'] : 0;

$stat_hidden_rev = mysqli_query($conn, "SELECT COUNT(*) AS total FROM reviews WHERE status = 'hidden'");
$hidden_rev_count = ($stat_hidden_rev && $r = mysqli_fetch_assoc($stat_hidden_rev)) ? (int)$r['total'] : 0;

// Search & Filter Setup
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$rating_filter = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_clauses = [];
if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where_clauses[] = "(c.name LIKE '%$search_escaped%' OR r.comment LIKE '%$search_escaped%')";
}
if ($rating_filter > 0) {
    $where_clauses[] = "r.rating = $rating_filter";
}
if (!empty($status_filter)) {
    $status_escaped = mysqli_real_escape_string($conn, $status_filter);
    $where_clauses[] = "r.status = '$status_escaped'";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Total records
$count_sql = "SELECT COUNT(*) AS total FROM reviews r JOIN customers c ON r.customer_id = c.customer_id $where_sql";
$count_res = mysqli_query($conn, $count_sql);
$total_records = 0;
if ($count_res && $row = mysqli_fetch_assoc($count_res)) {
    $total_records = (int)$row['total'];
}
$total_pages = ceil($total_records / $limit);

// Main Query with JOINs
$sql = "SELECT r.review_id, r.customer_id, r.appointment_id, r.rating, r.comment, r.status, r.created_at, c.name AS customer_name, c.email AS customer_email 
        FROM reviews r 
        JOIN customers c ON r.customer_id = c.customer_id 
        $where_sql 
        ORDER BY r.review_id DESC LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $sql);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';
?>

<!-- Executive Mini Stat Overview Row (Matches appointments.php & customers.php) -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-purple">
                <i class="fas fa-star"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($total_rev_count); ?></div>
                <div class="mini-stat-label">Total Customer Reviews</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-green">
                <i class="fas fa-thumbs-up"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($avg_rating_val, 1); ?> <span class="fs-6 text-warning">★</span></div>
                <div class="mini-stat-label">Average Satisfaction</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-gold">
                <i class="fas fa-award"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($five_star_count); ?></div>
                <div class="mini-stat-label">5-Star Excellence Ratings</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-amber">
                <i class="fas fa-eye-slash"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($hidden_rev_count); ?></div>
                <div class="mini-stat-label">Hidden / Moderated Reviews</div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Reviews Table Container -->
<div class="table-card">
    <div class="table-card-header d-flex flex-wrap align-items-center justify-content-between gap-3 p-3">
        <!-- Left Side: Table Title -->
        <div class="d-flex align-items-center gap-3">
            <h5 class="table-card-title mb-0">
                <i class="fas fa-star text-warning me-2"></i>Reviews & Feedback
                <span class="badge bg-light text-dark border rounded-pill fs-7 ms-2"><?php echo $total_records; ?></span>
            </h5>
        </div>

        <!-- Right Side: Integrated Search & Filters -->
        <form action="reviews.php" method="GET" class="d-flex flex-wrap align-items-center gap-2 m-0 ms-auto">
            <select name="rating" class="form-select form-select-custom" onchange="this.form.submit()" style="border-radius:30px; width: 140px; font-size: 0.85rem; padding: 0.4rem 0.75rem;">
                <option value="0">All Ratings</option>
                <option value="5" <?php echo ($rating_filter === 5) ? 'selected' : ''; ?>>5 Stars ★★★★★</option>
                <option value="4" <?php echo ($rating_filter === 4) ? 'selected' : ''; ?>>4 Stars ★★★★☆</option>
                <option value="3" <?php echo ($rating_filter === 3) ? 'selected' : ''; ?>>3 Stars ★★★☆☆</option>
                <option value="2" <?php echo ($rating_filter === 2) ? 'selected' : ''; ?>>2 Stars ★★☆☆☆</option>
                <option value="1" <?php echo ($rating_filter === 1) ? 'selected' : ''; ?>>1 Star ★☆☆☆☆</option>
            </select>

            <select name="status" class="form-select form-select-custom" onchange="this.form.submit()" style="border-radius:30px; width: 150px; font-size: 0.85rem; padding: 0.4rem 0.75rem;">
                <option value="">All Visibilities</option>
                <option value="visible" <?php echo ($status_filter === 'visible') ? 'selected' : ''; ?>>Visible</option>
                <option value="hidden" <?php echo ($status_filter === 'hidden') ? 'selected' : ''; ?>>Hidden</option>
            </select>

            <div class="input-group" style="width: 220px;">
                <span class="input-group-text bg-light border-end-0" style="border-radius:30px 0 0 30px; padding: 0.4rem 0.75rem;"><i class="fas fa-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search customer, comment..." value="<?php echo htmlspecialchars($search); ?>" style="border-radius:0 30px 30px 0; font-size:0.85rem; padding: 0.4rem 0.75rem;">
            </div>

            <button type="submit" class="btn btn-dark font-weight-700 px-3 py-1-5" style="border-radius:30px; font-size: 0.85rem;">Filter</button>
            <?php if (!empty($search) || $rating_filter > 0 || !empty($status_filter)): ?>
                <a href="reviews.php" class="btn btn-light border px-3 py-1-5" style="border-radius:30px; font-size: 0.85rem;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive-custom">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>CUSTOMER</th>
                    <th>APPOINTMENT</th>
                    <th>RATING</th>
                    <th>COMMENT</th>
                    <th>STATUS</th>
                    <th>DATE</th>
                    <th class="text-end">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($rev = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><strong class="text-muted">#<?php echo $rev['review_id']; ?></strong></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-circle-sm bg-gold-subtle text-gold fw-bold">
                                        <?php echo get_initials($rev['customer_name']); ?>
                                    </div>
                                    <div>
                                        <strong class="text-dark fs-6 d-block"><?php echo htmlspecialchars($rev['customer_name']); ?></strong>
                                        <small class="text-muted"><i class="far fa-envelope me-1"></i><?php echo htmlspecialchars($rev['customer_email']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="chip-selected-item cursor-default fs-8">
                                    <i class="fas fa-calendar-check me-1 text-primary"></i>Appt #<?php echo $rev['appointment_id']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-inline-flex gap-1 text-warning fs-7">
                                    <?php 
                                    $r = (int)$rev['rating'];
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo ($i <= $r) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-muted"></i>';
                                    }
                                    ?>
                                </div>
                            </td>
                            <td>
                                <span class="fst-italic text-dark fs-7">"<?php echo htmlspecialchars($rev['comment']); ?>"</span>
                            </td>
                            <td>
                                <form action="reviews.php" method="POST" class="d-inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="review_id" value="<?php echo $rev['review_id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $rev['status']; ?>">
                                    <button type="submit" class="border-0 bg-transparent p-0" title="Click to toggle status">
                                        <?php 
                                        $st = strtolower($rev['status']);
                                        $dot_cls = ($st === 'visible') ? 'dot-green' : 'dot-red';
                                        ?>
                                        <span class="status-pill-btn <?php echo $st; ?>">
                                            <span class="status-dot <?php echo $dot_cls; ?>"></span>
                                            <span class="fw-bold fs-7"><?php echo ucfirst($rev['status']); ?></span>
                                        </span>
                                    </button>
                                </form>
                            </td>
                            <td><small class="text-muted"><i class="far fa-calendar-alt me-1"></i><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></small></td>
                            <td class="text-end">
                                <form action="reviews.php" method="POST" class="d-inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="review_id" value="<?php echo $rev['review_id']; ?>">
                                    <button type="submit" class="btn-action btn-action-delete confirm-delete" data-confirm="Are you sure you want to delete this review?" title="Delete Review">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No reviews found matching filters.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <small class="text-muted">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> reviews</small>
            <nav>
                <ul class="pagination pagination-custom pagination-sm m-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="reviews.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&rating=<?php echo $rating_filter; ?>&status=<?php echo urlencode($status_filter); ?>">Previous</a>
                    </li>
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <li class="page-item <?php echo ($p === $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="reviews.php?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>&rating=<?php echo $rating_filter; ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="reviews.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&rating=<?php echo $rating_filter; ?>&status=<?php echo urlencode($status_filter); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

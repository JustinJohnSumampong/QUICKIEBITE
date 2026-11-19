<?php
require_once "config/database.php";
require_once "includes/functions.php";
require_login();

// 1. Fetch order details
$id = (int)($_GET['id'] ?? 0);
$s = $pdo->prepare("
    SELECT o.*, 
           u.name AS rider_name, 
           u.phone AS rider_phone,
           a.address, 
           a.phone AS recipient_phone
    FROM orders o 
    JOIN delivery_addresses a ON a.id = o.delivery_address_id 
    LEFT JOIN users u ON u.id = o.rider_id 
    WHERE o.id = ? AND o.user_id = ?
");
$s->execute([$id, $_SESSION['user']['id']]);
$o = $s->fetch();

if (!$o) {
    die("Order not found.");
}

// 2. Fetch order items
$itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemStmt->execute([$id]);
$orderItems = $itemStmt->fetchAll();

// 3. Define the tracking steps
$steps = [
    'Pending', 
    'Confirmed', 
    'Preparing', 
    'Ready for Pickup', 
    'Assigned to Rider', 
    'Picked Up', 
    'Out for Delivery', 
    'Delivered'
];

// 4. Determine current step index
$idx = array_search($o['order_status'], $steps);
$isCancelled = ($o['order_status'] === 'Cancelled' || $o['order_status'] === 'Refunded');

// If it's not a standard step and not cancelled, default to 0
if ($idx === false && !$isCancelled) {
    $idx = 0;
}

// 5. Determine badge color based on status
$status = strtolower($o['order_status']);
$badgeClass = 'text-bg-secondary';

if ($status === 'pending') {
    $badgeClass = 'text-bg-warning';
} elseif (in_array($status, ['confirmed', 'preparing', 'ready for pickup'])) {
    $badgeClass = 'text-bg-info';
} elseif (in_array($status, ['assigned to rider', 'picked up', 'out for delivery'])) {
    $badgeClass = 'text-bg-primary';
} elseif ($status === 'delivered') {
    $badgeClass = 'text-bg-success';
} elseif (in_array($status, ['cancelled', 'refunded'])) {
    $badgeClass = 'text-bg-danger';
}

$page_title = "Track Order";
include "includes/header.php";
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center">
        <h2 class="fw-bold">Track Order</h2>
        <a href="orders.php" class="btn btn-outline-secondary btn-sm">← Back to Orders</a>
    </div>
    
    <div class="card p-4 shadow-sm mt-3">
        <!-- Order Header -->
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h5 class="mb-0">Order #<?= e($o['order_number']) ?></h5>
                <small class="text-muted">Placed on <?= date('M d, Y h:i A', strtotime($o['created_at'])) ?></small>
            </div>
            <span class="badge <?= $badgeClass ?> fs-6"><?= e($o['order_status']) ?></span>
        </div>
        
        <hr>
        
        <!-- Tracking Steps -->
        <?php if ($isCancelled): ?>
            <div class="alert alert-danger">
                <strong>This order has been <?= e($o['order_status']) ?>.</strong> 
                If you have any questions, please contact support.
            </div>
        <?php else: ?>
            <div class="tracking-steps mt-3">
                <?php foreach ($steps as $i => $st): ?>
                    <div class="status-step <?= $i <= $idx ? 'active' : '' ?>">
                        <strong>
                            <?= $i <= $idx ? '✓' : '○' ?> 
                            <?= e($st) ?>
                        </strong>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <hr>
        
        <div class="row mt-3">
            <!-- Delivery Info -->
            <div class="col-md-6">
                <h6 class="fw-bold">Delivery Details</h6>
                <p class="mb-1"><strong>Address:</strong><br><?= nl2br(e($o['address'])) ?></p>
                <p class="mb-1"><strong>Phone:</strong> <?= e($o['recipient_phone']) ?></p>
                
                <?php if ($o['rider_name']): ?>
                    <hr>
                    <h6 class="fw-bold">Your Rider</h6>
                    <p class="mb-1"><strong>Name:</strong> <?= e($o['rider_name']) ?></p>
                    <?php if (!empty($o['rider_phone'])): ?>
                        <p class="mb-1">
                            <strong>Phone:</strong> 
                            <a href="tel:<?= e($o['rider_phone']) ?>"><?= e($o['rider_phone']) ?></a>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Order Items -->
            <div class="col-md-6">
                <h6 class="fw-bold">Order Items</h6>
                <ul class="list-group list-group-flush">
                    <?php foreach ($orderItems as $item): ?>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span><?= e($item['food_name']) ?> × <?= $item['quantity'] ?></span>
                            <b><?= money($item['subtotal']) ?></b>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="d-flex justify-content-between mt-3">
                    <strong>Total Paid:</strong>
                    <strong class="text-danger"><?= money($o['total_amount']) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>

<?php
session_start();
include "../db.php";

date_default_timezone_set("Asia/Kolkata");

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: ../index.php");
    exit;
}

$adminName = $_SESSION["user_name"] ?? "Admin";
$hostel = "girls";

function girlsEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function girlsCount($conn, $sql)
{
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_assoc($result);

    return (int)($row["total"] ?? 0);
}

/* STUDENT STATISTICS */

$totalStudents = girlsCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'student'
       AND hostel = 'girls'"
);

$assignedStudents = girlsCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users AS u
     INNER JOIN rooms AS r
         ON r.id = u.room_id
     WHERE u.role = 'student'
       AND u.hostel = 'girls'
       AND r.hostel = 'girls'
       AND r.status <> 'Archived'"
);

$unassignedStudents = max(
    0,
    $totalStudents - $assignedStudents
);

/* WARDEN STATISTICS */

$totalWardens = girlsCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'warden'
       AND hostel = 'girls'"
);

/* ROOM STATISTICS */

$totalRooms = girlsCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM rooms
     WHERE hostel = 'girls'
       AND status <> 'Archived'"
);

$totalCapacity = girlsCount(
    $conn,
    "SELECT COALESCE(SUM(capacity), 0) AS total
     FROM rooms
     WHERE hostel = 'girls'
       AND status <> 'Archived'"
);

$availableBeds = max(
    0,
    $totalCapacity - $assignedStudents
);

$occupancyPercent = $totalCapacity > 0
    ? min(
        100,
        round(
            ($assignedStudents / $totalCapacity) * 100
        )
    )
    : 0;

/* WARDEN LIST */

$wardens = mysqli_query(
    $conn,
    "SELECT id, name, email
     FROM users
     WHERE role = 'warden'
       AND hostel = 'girls'
     ORDER BY name ASC"
);

/* STUDENT LIST */

$students = mysqli_query(
    $conn,
    "SELECT
        u.id,
        u.name,
        u.email,
        u.room_id,
        r.room_number,
        r.block,
        r.floor,
        r.hostel AS room_hostel,
        r.status AS room_status
     FROM users AS u
     LEFT JOIN rooms AS r
         ON r.id = u.room_id
     WHERE u.role = 'student'
       AND u.hostel = 'girls'
     ORDER BY u.name ASC"
);

/* ROOM LIST */

$rooms = mysqli_query(
    $conn,
    "SELECT
        r.id,
        r.room_number,
        r.block,
        r.floor,
        r.capacity,
        r.status,
        COUNT(u.id) AS occupied_beds
     FROM rooms AS r
     LEFT JOIN users AS u
         ON u.room_id = r.id
        AND u.role = 'student'
        AND u.hostel = 'girls'
     WHERE r.hostel = 'girls'
       AND r.status <> 'Archived'
     GROUP BY
        r.id,
        r.room_number,
        r.block,
        r.floor,
        r.capacity,
        r.status
     ORDER BY
        r.block,
        r.floor,
        r.room_number"
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Girls Hostel - UniStay</title>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
>

<style>
:root {
    --navy:#1F2747;
    --teal:#0E6077;
    --teal-soft:#E2F1F3;
    --mustard:#E8B23D;
    --mustard-soft:#FFF2D3;
    --blue:#4387A5;
    --blue-soft:#E5F0F5;
    --green:#36A878;
    --green-soft:#E3F5EC;
    --background:#EFF4F7;
    --border:#DFE7EC;
    --text:#1E2639;
    --muted:#718092;
    --shadow:0 8px 24px rgba(31,39,71,.06);
}

* {
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:"Segoe UI",Arial,sans-serif;
}

body {
    background:var(--background);
    color:var(--text);
}

.dashboard {
    display:flex;
    min-height:100vh;
}

/* SIDEBAR */

.sidebar {
    width:230px;
    min-width:230px;
    min-height:100vh;
    position:sticky;
    top:0;
    align-self:flex-start;
    padding:30px 18px;
    background:white;
    border-right:1px solid var(--border);
    z-index:50;
}

.logo {
    position:relative;
    display:flex;
    align-items:center;
    gap:12px;
    margin-bottom:50px;
    padding-left:8px;
}

.logo::after {
    content:"Live  Learn  Belong";
    position:absolute;
    left:61px;
    top:31px;
    color:#7C8796;
    font-size:10px;
    letter-spacing:.3px;
}

.logo-box {
    width:44px;
    height:44px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:11px;
    color:white;
    background:linear-gradient(135deg,#0C556C,#168295);
    font-size:21px;
    font-weight:800;
    box-shadow:0 8px 18px rgba(14,96,119,.2);
}

.logo h2 {
    color:#162238;
    font-size:24px;
    font-weight:800;
}

.menu a {
    display:flex;
    align-items:center;
    gap:13px;
    padding:13px 14px;
    margin-bottom:8px;
    border-radius:10px;
    color:#45576E;
    text-decoration:none;
    font-size:14px;
    font-weight:500;
    transition:.18s ease;
}

.menu a i {
    width:22px;
    text-align:center;
    color:#395875;
    font-size:15px;
}

.menu a:hover {
    background:#F0F7F9;
    color:var(--teal);
}

.menu a.active {
    background:#DDF0F3;
    color:var(--teal);
    font-weight:700;
}

.menu a.active i {
    color:var(--teal);
}

/* MAIN */

.main {
    flex:1;
    min-width:0;
    padding:28px 32px 50px;
}

.topbar {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
    margin-bottom:24px;
}

.page-title {
    display:flex;
    align-items:center;
    gap:14px;
}

.page-icon {
    width:50px;
    height:50px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:13px;
    color:white;
    background:linear-gradient(
        135deg,
        var(--navy),
        var(--teal)
    );
    font-size:20px;
}

.topbar h1 {
    font-size:30px;
    letter-spacing:-.6px;
}

.topbar p {
    margin-top:5px;
    color:var(--muted);
    font-size:14px;
}

.admin-info {
    display:flex;
    align-items:center;
    gap:10px;
    padding:8px 14px 8px 8px;
    border-radius:30px;
    background:white;
    border:1px solid var(--border);
}

.admin-avatar {
    width:40px;
    height:40px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:50%;
    color:white;
    background:linear-gradient(
        135deg,
        var(--navy),
        var(--teal)
    );
    font-size:14px;
    font-weight:800;
}

.admin-info strong {
    display:block;
    font-size:14px;
}

.admin-info span {
    display:block;
    margin-top:2px;
    color:var(--muted);
    font-size:11px;
}

/* HERO */

.hero {
    position:relative;
    overflow:hidden;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
    padding:30px 34px;
    margin-bottom:20px;
    border-radius:20px;
    color:white;
    background:linear-gradient(
        120deg,
        #1F2747,
        #17475E,
        #0E6077,
        #25899A
    );
    box-shadow:0 13px 30px rgba(18,71,91,.15);
}

.hero h2 {
    font-size:28px;
    margin-bottom:9px;
}

.hero p {
    max-width:650px;
    color:rgba(255,255,255,.85);
    font-size:14px;
    line-height:1.6;
}

.hero-icon {
    font-size:70px;
    color:rgba(255,255,255,.2);
}

.back-link {
    display:inline-flex;
    align-items:center;
    gap:8px;
    margin-top:17px;
    padding:9px 14px;
    border-radius:20px;
    background:var(--mustard);
    color:var(--navy);
    text-decoration:none;
    font-size:12px;
    font-weight:700;
}

/* STATISTICS */

.cards {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:14px;
    margin-bottom:20px;
}

.card {
    display:flex;
    align-items:center;
    gap:14px;
    min-height:120px;
    padding:18px;
    background:white;
    border:1px solid var(--border);
    border-radius:14px;
    box-shadow:var(--shadow);
}

.card:nth-child(1) {
    border-top:4px solid var(--teal);
}

.card:nth-child(2) {
    border-top:4px solid var(--blue);
}

.card:nth-child(3) {
    border-top:4px solid var(--green);
}

.card:nth-child(4) {
    border-top:4px solid var(--mustard);
}

.card-icon {
    width:48px;
    height:48px;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-shrink:0;
    border-radius:12px;
    color:var(--teal);
    background:var(--teal-soft);
    font-size:19px;
}

.card p {
    color:var(--muted);
    font-size:12px;
    text-transform:uppercase;
}

.card h2 {
    margin-top:5px;
    font-size:27px;
}

/* PANELS */

.panel {
    margin-bottom:20px;
    overflow:hidden;
    background:white;
    border:1px solid var(--border);
    border-radius:16px;
    box-shadow:var(--shadow);
}

.panel-head {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:15px;
    padding:20px;
    border-bottom:1px solid #EDF1F3;
}

.panel-heading {
    display:flex;
    align-items:center;
    gap:11px;
}

.panel-icon {
    width:43px;
    height:43px;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-shrink:0;
    border-radius:11px;
    color:var(--teal);
    background:var(--teal-soft);
}

.panel-head h2 {
    font-size:20px;
}

.panel-head p {
    margin-top:3px;
    color:var(--muted);
    font-size:13px;
}

.panel-body {
    padding:20px;
}

.info-grid {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:14px;
}

.info-box {
    padding:17px;
    border-radius:12px;
    background:#F8FAFB;
    border:1px solid #E7EDF0;
}

.info-box span {
    display:block;
    color:var(--muted);
    font-size:12px;
}

.info-box strong {
    display:block;
    margin-top:7px;
    font-size:24px;
}

.progress-track {
    height:9px;
    overflow:hidden;
    margin-top:15px;
    border-radius:20px;
    background:#E9EFF2;
}

.progress-fill {
    height:100%;
    border-radius:20px;
    background:linear-gradient(
        90deg,
        var(--teal),
        var(--green)
    );
}

/* TABLES */

.table-wrap {
    overflow-x:auto;
}

table {
    width:100%;
    border-collapse:collapse;
}

thead {
    background:#F5F8FA;
}

th {
    padding:14px 15px;
    text-align:left;
    color:#5B6B7B;
    font-size:12px;
    text-transform:uppercase;
}

td {
    padding:15px;
    border-bottom:1px solid #E9EEF1;
    color:#405165;
    font-size:13px;
}

tbody tr:last-child td {
    border-bottom:none;
}

tbody tr:hover {
    background:#FAFCFD;
}

.user-cell {
    display:flex;
    align-items:center;
    gap:10px;
}

.user-avatar {
    width:38px;
    height:38px;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-shrink:0;
    border-radius:10px;
    color:white;
    background:linear-gradient(
        135deg,
        var(--navy),
        var(--teal)
    );
    font-weight:800;
}

.user-name {
    font-size:14px;
    font-weight:700;
    color:var(--text);
}

.user-id {
    margin-top:3px;
    color:#919CA7;
    font-size:11px;
}

.badge {
    display:inline-block;
    padding:7px 11px;
    border-radius:20px;
    font-size:11px;
    font-weight:700;
}

.badge-green {
    color:#21815A;
    background:var(--green-soft);
}

.badge-yellow {
    color:#956305;
    background:var(--mustard-soft);
}

.badge-blue {
    color:var(--teal);
    background:var(--teal-soft);
}

.badge-red {
    color:#A33E48;
    background:#FBE8EA;
}

.empty {
    padding:30px;
    text-align:center;
    color:var(--muted);
    font-size:13px;
}

/* RESPONSIVE */

@media(max-width:1150px) {
    .cards {
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:900px) {
    .sidebar {
        width:205px;
        min-width:205px;
    }

    .main {
        padding:22px;
    }

    .info-grid {
        grid-template-columns:1fr;
    }
}

@media(max-width:700px) {
    .sidebar {
        display:none;
    }

    .hero-icon {
        display:none;
    }

    .topbar {
        align-items:flex-start;
    }

    .admin-info > div:last-child {
        display:none;
    }
}

@media(max-width:520px) {
    .main {
        padding:14px;
    }

    .cards {
        grid-template-columns:1fr;
    }

    .hero {
        padding:23px;
    }

    .topbar h1 {
        font-size:25px;
    }
}
</style>
</head>

<body>

<div class="dashboard">

    <?php include "sidebar.php"; ?>

    <div class="main">

        <!-- TOPBAR -->

        <div class="topbar">

            <div class="page-title">

                <div class="page-icon">
                    <i class="fa-solid fa-building"></i>
                </div>

                <div>
                    <h1>Girls Hostel</h1>
                    <p>
                        Students, wardens and room
                        allocation overview.
                    </p>
                </div>

            </div>

            <div class="admin-info">

                <div class="admin-avatar">
                    <?php
                    echo girlsEscape(
                        strtoupper(
                            substr($adminName, 0, 1)
                        )
                    );
                    ?>
                </div>

                <div>
                    <strong>
                        <?php echo girlsEscape($adminName); ?>
                    </strong>

                    <span>System Admin</span>
                </div>

            </div>

        </div>

        <!-- HERO -->

        <div class="hero">

            <div>

                <h2>Girls Hostel Overview</h2>

                <p>
                    Monitor Girls Hostel residents,
                    assigned wardens, room capacity
                    and accommodation details.
                </p>

                <a
                    href="dashboard.php"
                    class="back-link"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Dashboard
                </a>

            </div>

            <div class="hero-icon">
                <i class="fa-solid fa-building"></i>
            </div>

        </div>

        <!-- STATISTICS -->

        <div class="cards">

            <div class="card">

                <div class="card-icon">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>

                <div>
                    <p>Total Students</p>
                    <h2><?php echo $totalStudents; ?></h2>
                </div>

            </div>

            <div class="card">

                <div class="card-icon">
                    <i class="fa-solid fa-user-shield"></i>
                </div>

                <div>
                    <p>Total Wardens</p>
                    <h2><?php echo $totalWardens; ?></h2>
                </div>

            </div>

            <div class="card">

                <div class="card-icon">
                    <i class="fa-solid fa-bed"></i>
                </div>

                <div>
                    <p>Total Rooms</p>
                    <h2><?php echo $totalRooms; ?></h2>
                </div>

            </div>

            <div class="card">

                <div class="card-icon">
                    <i class="fa-solid fa-door-open"></i>
                </div>

                <div>
                    <p>Available Beds</p>
                    <h2><?php echo $availableBeds; ?></h2>
                </div>

            </div>

        </div>

        <!-- ALLOCATION SUMMARY -->

        <div class="panel">

            <div class="panel-head">

                <div class="panel-heading">

                    <div class="panel-icon">
                        <i class="fa-solid fa-chart-pie"></i>
                    </div>

                    <div>
                        <h2>Accommodation Overview</h2>
                        <p>
                            Girls Hostel room occupancy.
                        </p>
                    </div>

                </div>

            </div>

            <div class="panel-body">

                <div class="info-grid">

                    <div class="info-box">
                        <span>Total Bed Capacity</span>
                        <strong>
                            <?php echo $totalCapacity; ?>
                        </strong>
                    </div>

                    <div class="info-box">
                        <span>Students Assigned</span>
                        <strong>
                            <?php echo $assignedStudents; ?>
                        </strong>
                    </div>

                    <div class="info-box">
                        <span>Students Unassigned</span>
                        <strong>
                            <?php echo $unassignedStudents; ?>
                        </strong>
                    </div>

                </div>

                <div
                    style="
                        display:flex;
                        justify-content:space-between;
                        margin-top:20px;
                        font-size:13px;
                        color:#526475;
                        font-weight:650;
                    "
                >
                    <span>Occupancy Progress</span>
                    <span>
                        <?php echo $occupancyPercent; ?>%
                    </span>
                </div>

                <div class="progress-track">

                    <div
                        class="progress-fill"
                        style="width:<?php
                            echo $occupancyPercent;
                        ?>%;"
                    ></div>

                </div>

            </div>

        </div>

        <!-- WARDENS -->

        <div class="panel">

            <div class="panel-head">

                <div class="panel-heading">

                    <div class="panel-icon">
                        <i class="fa-solid fa-user-shield"></i>
                    </div>

                    <div>
                        <h2>Assigned Wardens</h2>
                        <p>
                            Girls Hostel warden accounts.
                        </p>
                    </div>

                </div>

            </div>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>Warden</th>
                            <th>Email</th>
                            <th>Hostel</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (
                        $wardens &&
                        mysqli_num_rows($wardens) > 0
                    ): ?>

                        <?php while (
                            $warden = mysqli_fetch_assoc($wardens)
                        ): ?>

                        <tr>

                            <td>
                                <div class="user-cell">

                                    <div class="user-avatar">
                                        <?php
                                        echo girlsEscape(
                                            strtoupper(
                                                substr(
                                                    $warden["name"],
                                                    0,
                                                    1
                                                )
                                            )
                                        );
                                        ?>
                                    </div>

                                    <div>
                                        <div class="user-name">
                                            <?php
                                            echo girlsEscape(
                                                $warden["name"]
                                            );
                                            ?>
                                        </div>

                                        <div class="user-id">
                                            ID:
                                            <?php
                                            echo (int)$warden["id"];
                                            ?>
                                        </div>
                                    </div>

                                </div>
                            </td>

                            <td>
                                <?php
                                echo girlsEscape(
                                    $warden["email"]
                                );
                                ?>
                            </td>

                            <td>
                                <span class="badge badge-blue">
                                    Girls Hostel
                                </span>
                            </td>

                        </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="3" class="empty">
                                No warden assigned to
                                Girls Hostel yet.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

        <!-- STUDENTS -->

        <div class="panel">

            <div class="panel-head">

                <div class="panel-heading">

                    <div class="panel-icon">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>

                    <div>
                        <h2>Hostel Students</h2>
                        <p>
                            Students assigned to Girls Hostel.
                        </p>
                    </div>

                </div>

            </div>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Email</th>
                            <th>Room</th>
                            <th>Allocation</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (
                        $students &&
                        mysqli_num_rows($students) > 0
                    ): ?>

                        <?php while (
                            $student = mysqli_fetch_assoc($students)
                        ): ?>

                        <tr>

                            <td>

                                <div class="user-cell">

                                    <div class="user-avatar">
                                        <?php
                                        echo girlsEscape(
                                            strtoupper(
                                                substr(
                                                    $student["name"],
                                                    0,
                                                    1
                                                )
                                            )
                                        );
                                        ?>
                                    </div>

                                    <div>
                                        <div class="user-name">
                                            <?php
                                            echo girlsEscape(
                                                $student["name"]
                                            );
                                            ?>
                                        </div>

                                        <div class="user-id">
                                            ID:
                                            <?php
                                            echo (int)$student["id"];
                                            ?>
                                        </div>
                                    </div>

                                </div>

                            </td>

                            <td>
                                <?php
                                echo girlsEscape(
                                    $student["email"]
                                );
                                ?>
                            </td>

                            <td>

                                <?php
                                $validRoom =
                                    !empty($student["room_id"]) &&
                                    $student["room_hostel"] === "girls" &&
                                    $student["room_status"] !== "Archived";

                                if ($validRoom) {
                                    echo girlsEscape(
                                        $student["room_number"]
                                    );
                                } else {
                                    echo "—";
                                }
                                ?>

                            </td>

                            <td>

                                <?php if ($validRoom): ?>

                                    <span class="badge badge-green">
                                        Assigned
                                    </span>

                                <?php elseif (
                                    !empty($student["room_id"])
                                ): ?>

                                    <span class="badge badge-red">
                                        Needs Review
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-yellow">
                                        Unassigned
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="4" class="empty">
                                No students registered
                                in Girls Hostel yet.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

        <!-- ROOMS -->

        <div class="panel">

            <div class="panel-head">

                <div class="panel-heading">

                    <div class="panel-icon">
                        <i class="fa-solid fa-bed"></i>
                    </div>

                    <div>
                        <h2>Hostel Rooms</h2>
                        <p>
                            Girls Hostel room inventory
                            and occupancy.
                        </p>
                    </div>

                </div>

            </div>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Block</th>
                            <th>Floor</th>
                            <th>Capacity</th>
                            <th>Occupied</th>
                            <th>Availability</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (
                        $rooms &&
                        mysqli_num_rows($rooms) > 0
                    ): ?>

                        <?php while (
                            $room = mysqli_fetch_assoc($rooms)
                        ): ?>

                        <?php
                        $occupied = (int)$room["occupied_beds"];
                        $capacity = (int)$room["capacity"];
                        $remaining = max(
                            0,
                            $capacity - $occupied
                        );
                        ?>

                        <tr>

                            <td>
                                <strong>
                                    <?php
                                    echo girlsEscape(
                                        $room["room_number"]
                                    );
                                    ?>
                                </strong>
                            </td>

                            <td>
                                <?php
                                echo girlsEscape($room["block"]);
                                ?>
                            </td>

                            <td>
                                <?php
                                echo girlsEscape($room["floor"]);
                                ?>
                            </td>

                            <td>
                                <?php echo $capacity; ?>
                            </td>

                            <td>
                                <?php echo $occupied; ?>
                            </td>

                            <td>

                                <?php if (
                                    $room["status"] !== "Available"
                                ): ?>

                                    <span class="badge badge-yellow">
                                        <?php
                                        echo girlsEscape(
                                            $room["status"]
                                        );
                                        ?>
                                    </span>

                                <?php elseif ($remaining > 0): ?>

                                    <span class="badge badge-green">
                                        <?php echo $remaining; ?>
                                        Beds Available
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-red">
                                        Full
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="empty">
                                No active Girls Hostel
                                rooms available.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>
</html>

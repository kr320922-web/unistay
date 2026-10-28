<?php

session_start();
include "../db.php";

date_default_timezone_set("Asia/Kolkata");


/* =========================================================
   WARDEN ONLY
   ========================================================= */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] != "warden"
) {
    header("Location: ../index.php");
    exit;
}

$wardenName =
    $_SESSION["user_name"] ?? "Warden";


/* =========================================================
   COMPLAINT COUNTS
   ========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Pending'),0) AS pending,
        COALESCE(SUM(status = 'In Progress'),0) AS in_progress,
        COALESCE(SUM(status = 'Resolved'),0) AS resolved
     FROM complaints"
);

$complaintData =
    mysqli_fetch_assoc($result);

$totalComplaints =
    intval($complaintData["total"] ?? 0);

$pendingComplaints =
    intval($complaintData["pending"] ?? 0);

$inProgressComplaints =
    intval($complaintData["in_progress"] ?? 0);

$resolvedComplaints =
    intval($complaintData["resolved"] ?? 0);


/* =========================================================
   TOTAL STUDENTS
   ========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'student'"
);

$totalStudents =
    intval(
        mysqli_fetch_assoc($result)["total"] ?? 0
    );


/* =========================================================
   PENDING LEAVES
   ========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM leave_requests
     WHERE status = 'Pending'"
);

$pendingLeaves =
    intval(
        mysqli_fetch_assoc($result)["total"] ?? 0
    );


/* =========================================================
   PENDING LAUNDRY
   ========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM laundry_requests
     WHERE status = 'Pending'"
);

$pendingLaundry =
    intval(
        mysqli_fetch_assoc($result)["total"] ?? 0
    );


/* =========================================================
   ACTIVE SOS
   ========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM emergency_alerts
     WHERE status IN ('Active','Responding')"
);

$activeSOS =
    intval(
        mysqli_fetch_assoc($result)["total"] ?? 0
    );


/* =========================================================
   VISITORS INSIDE
   ========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM visitor_entries
     WHERE status = 'Inside'"
);

$visitorsInside =
    intval(
        mysqli_fetch_assoc($result)["total"] ?? 0
    );


/* =========================================================
   WAITING PARCELS
   ========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM parcels
     WHERE status = 'Waiting for Pickup'"
);

$waitingParcels =
    intval(
        mysqli_fetch_assoc($result)["total"] ?? 0
    );


/* =========================================================
   UPCOMING EVENTS
   ========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM events
     WHERE status = 'Scheduled'
     AND event_date >= CURDATE()"
);

$upcomingEvents =
    intval(
        mysqli_fetch_assoc($result)["total"] ?? 0
    );


/* =========================================================
   TOTAL NOTICES
   ========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM notices"
);

$totalNotices =
    intval(
        mysqli_fetch_assoc($result)["total"] ?? 0
    );


/* =========================================================
   NEEDS ATTENTION
   ========================================================= */

$attentionCount =
    $pendingComplaints +
    $pendingLeaves +
    $pendingLaundry +
    $activeSOS +
    $waitingParcels;


/* =========================================================
   NEXT EVENT
   ========================================================= */

$nextEventResult =
    mysqli_query(
        $conn,
        "SELECT
            title,
            event_date,
            start_time,
            venue
         FROM events
         WHERE status = 'Scheduled'
         AND event_date >= CURDATE()
         ORDER BY
            event_date ASC,
            start_time ASC
         LIMIT 1"
    );

$nextEvent = null;

if ($nextEventResult) {
    $nextEvent =
        mysqli_fetch_assoc(
            $nextEventResult
        );
}


/* =========================================================
   LATEST SOS
   ========================================================= */

$latestSOSResult =
    mysqli_query(
        $conn,
        "SELECT
            e.emergency_type,
            e.location,
            e.status,
            e.created_at,
            u.name
         FROM emergency_alerts AS e
         INNER JOIN users AS u
            ON e.user_id = u.id
         WHERE e.status IN ('Active','Responding')
         ORDER BY e.id DESC
         LIMIT 1"
    );

$latestSOS = null;

if ($latestSOSResult) {
    $latestSOS =
        mysqli_fetch_assoc(
            $latestSOSResult
        );
}


/* =========================================================
   RECENT COMPLAINTS
   ========================================================= */

$recentComplaints =
    mysqli_query(
        $conn,
        "SELECT
            complaints.id,
            complaints.category,
            complaints.location,
            complaints.status,
            complaints.priority,
            complaints.created_at,
            users.name
         FROM complaints
         LEFT JOIN users
            ON complaints.user_id = users.id
         ORDER BY complaints.id DESC
         LIMIT 5"
    );


/* =========================================================
   COMPLAINT PERCENTAGES
   ========================================================= */

$pendingPercent = 0;
$progressPercent = 0;
$resolvedPercent = 0;

if ($totalComplaints > 0) {

    $pendingPercent =
        round(
            ($pendingComplaints /
            $totalComplaints) * 100
        );

    $progressPercent =
        round(
            ($inProgressComplaints /
            $totalComplaints) * 100
        );

    $resolvedPercent =
        round(
            ($resolvedComplaints /
            $totalComplaints) * 100
        );
}


/* =========================================================
   HELPER
   ========================================================= */

function getInitial($name)
{
    $name = trim($name);

    if ($name === "") {
        return "W";
    }

    return strtoupper(
        substr($name, 0, 1)
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Warden Dashboard - UniStay</title>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
>

<style>

:root {

    --navy:#1F2747;

    --teal:#0E6077;
    --teal2:#168295;

    --mustard:#E8B23D;
    --blue:#4387A5;

    --green:#36B486;
    --red:#E76871;

    --background:#EFF4F7;

    --border:#DFE7EC;

    --text:#1E2639;
    --muted:#718092;

    --teal-soft:#DDF2F4;
    --blue-soft:#E5F0F5;
    --green-soft:#E3F5EC;
    --yellow-soft:#FFF2D3;
    --red-soft:#FBE8EA;

    --shadow:
        0 8px 24px
        rgba(31,39,71,0.055);
}


/* =========================================================
   GLOBAL
   ========================================================= */

* {

    margin:0;
    padding:0;

    box-sizing:border-box;

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;
}


body {

    background:
        var(--background);

    color:
        var(--text);
}


.dashboard {

    display:flex;

    min-height:100vh;
}


/* =========================================================
   SIDEBAR
   ========================================================= */

.sidebar {

    width:230px;
    min-width:230px;

    min-height:100vh;

    position:sticky;

    top:0;

    align-self:flex-start;

    padding:
        30px 18px;

    background:white;

    border-right:
        1px solid
        var(--border);

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

    content:
        "Live  Learn  Belong";

    position:absolute;

    left:61px;
    top:31px;

    color:#7C8796;

    font-size:10px;
}


.logo-box {

    width:44px;
    height:44px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:11px;

    color:white;

    background:
        linear-gradient(
            135deg,
            #0C556C,
            #0E7485
        );

    font-size:21px;

    font-weight:800;
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

    padding:
        13px 14px;

    margin-bottom:8px;

    border-radius:10px;

    color:#45576E;

    text-decoration:none;

    font-size:14px;

    font-weight:500;
}


.menu a i {

    width:22px;

    text-align:center;
}


.menu a:hover {

    color:
        var(--teal);

    background:#F0F7F9;
}


.menu a.active {

    color:
        var(--teal);

    background:#DFF0F4;

    font-weight:700;
}


/* =========================================================
   MAIN
   ========================================================= */

.main {

    flex:1;

    min-width:0;

    padding:
        28px 32px 50px;
}


/* =========================================================
   TOPBAR
   ========================================================= */

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

    background:
        linear-gradient(
            135deg,
            var(--navy),
            var(--teal)
        );

    font-size:20px;
}


.topbar h1 {

    font-size:30px;
}


.topbar p {

    margin-top:5px;

    color:
        var(--muted);

    font-size:14px;
}


.warden-info {

    display:flex;

    align-items:center;

    gap:10px;

    padding:
        8px 14px 8px 8px;

    border-radius:30px;

    background:white;

    border:
        1px solid
        var(--border);
}


.warden-avatar {

    width:40px;
    height:40px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:50%;

    color:white;

    background:
        linear-gradient(
            135deg,
            var(--teal),
            var(--teal2)
        );

    font-size:14px;

    font-weight:800;
}


.warden-info strong {

    display:block;

    font-size:14px;
}


.warden-info span {

    display:block;

    margin-top:2px;

    color:
        var(--muted);

    font-size:11px;
}


/* =========================================================
   HERO
   ========================================================= */

.hero {

    display:grid;

    grid-template-columns:
        minmax(0,1fr)
        340px;

    align-items:center;

    gap:30px;

    min-height:220px;

    padding:
        32px 34px;

    margin-bottom:20px;

    border-radius:20px;

    color:white;

    background:
        linear-gradient(
            125deg,
            #1F2747,
            #154A60,
            #0E6077,
            #25899A
        );

    box-shadow:
        0 13px 30px
        rgba(18,71,91,0.15);
}


.hero-label {

    color:#F5D47C;

    font-size:12px;

    font-weight:700;

    text-transform:uppercase;

    letter-spacing:1px;
}


.hero h2 {

    margin-top:7px;

    font-size:34px;
}


.hero p {

    max-width:600px;

    margin-top:9px;

    color:
        rgba(255,255,255,0.83);

    font-size:15px;

    line-height:1.6;
}


.hero-date {

    display:inline-flex;

    align-items:center;

    gap:7px;

    margin-top:18px;

    padding:
        8px 12px;

    border-radius:20px;

    background:
        rgba(255,255,255,0.12);

    font-size:12px;
}


.hero-box {

    padding:18px;

    border-radius:15px;

    background:
        rgba(255,255,255,0.11);

    border:
        1px solid
        rgba(255,255,255,0.13);
}


.hero-box-head {

    display:flex;

    align-items:center;

    gap:12px;

    padding-bottom:14px;

    border-bottom:
        1px solid
        rgba(255,255,255,0.14);
}


.hero-box-icon {

    width:48px;
    height:48px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:12px;

    color:
        var(--navy);

    background:
        var(--mustard);

    font-size:19px;
}


.hero-box strong {

    font-size:15px;
}


.hero-box p {

    margin-top:3px;

    font-size:11px;
}


.hero-stats {

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:6px;

    margin-top:13px;
}


.hero-stat {

    text-align:center;
}


.hero-stat span {

    display:block;

    color:
        rgba(255,255,255,0.65);

    font-size:9px;

    text-transform:uppercase;
}


.hero-stat strong {

    display:block;

    margin-top:4px;

    font-size:20px;
}


/* =========================================================
   MAIN CARDS
   ========================================================= */

.cards {

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:14px;

    margin-bottom:20px;
}


.card {

    min-height:125px;

    display:flex;

    align-items:center;

    gap:14px;

    padding:18px;

    border-radius:14px;

    background:white;

    border:
        1px solid
        var(--border);

    box-shadow:
        var(--shadow);
}


.card-icon {

    width:50px;
    height:50px;

    flex-shrink:0;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:13px;

    font-size:19px;
}


.card:nth-child(1) {

    border-top:
        4px solid
        var(--mustard);
}


.card:nth-child(1)
.card-icon {

    color:#966604;

    background:
        var(--yellow-soft);
}


.card:nth-child(2) {

    border-top:
        4px solid
        var(--blue);
}


.card:nth-child(2)
.card-icon {

    color:
        var(--blue);

    background:
        var(--blue-soft);
}


.card:nth-child(3) {

    border-top:
        4px solid
        var(--green);
}


.card:nth-child(3)
.card-icon {

    color:
        var(--green);

    background:
        var(--green-soft);
}


.card:nth-child(4) {

    border-top:
        4px solid
        var(--teal);
}


.card:nth-child(4)
.card-icon {

    color:
        var(--teal);

    background:
        var(--teal-soft);
}


.card p {

    color:
        var(--muted);

    font-size:12px;

    text-transform:uppercase;
}


.card h2 {

    margin-top:5px;

    font-size:29px;
}


/* =========================================================
   OPERATIONS
   ========================================================= */

.operations {

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:14px;

    margin-bottom:20px;
}


.operation-card {

    position:relative;

    display:flex;

    align-items:center;

    gap:14px;

    min-height:105px;

    padding:17px;

    border-radius:14px;

    color:
        var(--text);

    text-decoration:none;

    background:white;

    border:
        1px solid
        var(--border);

    box-shadow:
        var(--shadow);

    transition:0.18s;
}


.operation-card:hover {

    transform:
        translateY(-2px);
}


.operation-icon {

    width:47px;
    height:47px;

    flex-shrink:0;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:12px;

    font-size:18px;
}


.operation-card.sos
.operation-icon {

    color:
        var(--red);

    background:
        var(--red-soft);
}


.operation-card.visitor
.operation-icon {

    color:
        var(--blue);

    background:
        var(--blue-soft);
}


.operation-card.parcel
.operation-icon {

    color:#996707;

    background:
        var(--yellow-soft);
}


.operation-card.event
.operation-icon {

    color:
        var(--teal);

    background:
        var(--teal-soft);
}


.operation-label {

    color:
        var(--muted);

    font-size:11px;

    text-transform:uppercase;
}


.operation-number {

    margin-top:2px;

    font-size:25px;

    font-weight:800;
}


.operation-note {

    margin-top:3px;

    color:
        var(--muted);

    font-size:10px;
}


/* =========================================================
   CONTENT
   ========================================================= */

.content-grid {

    display:grid;

    grid-template-columns:
        minmax(0,1.55fr)
        minmax(300px,0.65fr);

    gap:20px;

    margin-bottom:20px;
}


.panel {

    overflow:hidden;

    border-radius:15px;

    background:white;

    border:
        1px solid
        var(--border);

    box-shadow:
        var(--shadow);
}


.panel-head {

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    padding:20px;

    border-bottom:
        1px solid
        #EDF1F3;
}


.panel-heading {

    display:flex;

    align-items:center;

    gap:11px;
}


.panel-icon {

    width:41px;
    height:41px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:10px;

    color:
        var(--teal);

    background:
        var(--teal-soft);

    font-size:15px;
}


.panel-head h2 {

    font-size:19px;
}


.panel-head p {

    margin-top:3px;

    color:
        var(--muted);

    font-size:12px;
}


.view-link {

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:
        7px 11px;

    border-radius:20px;

    color:
        var(--teal);

    background:
        var(--teal-soft);

    text-decoration:none;

    font-size:11px;

    font-weight:700;
}


/* =========================================================
   TABLE
   ========================================================= */

.table-wrap {

    width:100%;

    overflow-x:auto;
}


table {

    width:100%;

    border-collapse:collapse;
}


th {

    padding:
        13px 15px;

    text-align:left;

    color:#6B7887;

    background:#F6F9FA;

    border-bottom:
        1px solid
        #E7EDF0;

    font-size:11px;

    text-transform:uppercase;
}


td {

    padding:15px;

    border-bottom:
        1px solid
        #EDF1F3;

    color:#455669;

    font-size:13px;
}


.student-cell {

    display:flex;

    align-items:center;

    gap:9px;

    color:
        var(--text);

    font-weight:650;
}


.mini-avatar {

    width:33px;
    height:33px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:50%;

    color:white;

    background:
        linear-gradient(
            135deg,
            var(--teal),
            #3B96A6
        );

    font-size:11px;

    font-weight:800;
}


.status,
.priority {

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:
        6px 9px;

    border-radius:20px;

    font-size:10px;

    font-weight:700;
}


.pending {

    color:#936200;

    background:#FFF0C7;
}


.in-progress {

    color:#306F9E;

    background:#E3EFF8;
}


.resolved {

    color:#267A5D;

    background:#E2F4EB;
}


.low {

    color:#336F96;

    background:#E7F2F8;
}


.medium {

    color:#956A11;

    background:#FFF0CA;
}


.high {

    color:#B26119;

    background:#FDE9D9;
}


.urgent {

    color:#B4424B;

    background:#FCE4E6;
}


/* =========================================================
   ATTENTION PANEL
   ========================================================= */

.attention-list {

    padding:13px;
}


.attention-item {

    display:grid;

    grid-template-columns:
        39px 1fr auto;

    gap:10px;

    align-items:center;

    padding:11px;

    margin-bottom:8px;

    border-radius:10px;

    background:#F7F9FA;
}


.attention-item:last-child {

    margin-bottom:0;
}


.attention-icon {

    width:39px;
    height:39px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:10px;

    color:
        var(--teal);

    background:
        var(--teal-soft);
}


.attention-item.danger
.attention-icon {

    color:
        var(--red);

    background:
        var(--red-soft);
}


.attention-item.warning
.attention-icon {

    color:#976700;

    background:
        var(--yellow-soft);
}


.attention-item.blue
.attention-icon {

    color:
        var(--blue);

    background:
        var(--blue-soft);
}


.attention-title {

    font-size:12px;

    font-weight:700;
}


.attention-sub {

    margin-top:2px;

    color:
        var(--muted);

    font-size:10px;

    line-height:1.4;
}


.attention-count {

    min-width:28px;

    padding:
        5px 7px;

    text-align:center;

    border-radius:20px;

    color:
        var(--teal);

    background:
        var(--teal-soft);

    font-size:11px;

    font-weight:800;
}


.attention-item a {

    color:inherit;

    text-decoration:none;
}


/* =========================================================
   QUICK ACTIONS
   ========================================================= */

.quick-grid {

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:12px;

    padding:
        18px 20px 21px;
}


.quick-card {

    min-height:105px;

    display:flex;

    align-items:center;

    gap:11px;

    padding:14px;

    border-radius:12px;

    color:
        var(--text);

    text-decoration:none;

    background:#F8FAFB;

    border:
        1px solid
        #E7EDF0;

    transition:0.18s;
}


.quick-card:hover {

    transform:
        translateY(-2px);

    border-color:#CADDE2;
}


.quick-icon {

    width:43px;
    height:43px;

    flex-shrink:0;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:11px;

    color:
        var(--teal);

    background:
        var(--teal-soft);

    font-size:16px;
}


.quick-title {

    font-size:12px;

    font-weight:750;
}


.quick-subtitle {

    margin-top:3px;

    color:
        var(--muted);

    font-size:10px;

    line-height:1.4;
}


.quick-count {

    margin-top:5px;

    color:
        var(--teal);

    font-size:11px;

    font-weight:800;
}


/* =========================================================
   EMPTY
   ========================================================= */

.empty {

    padding:30px;

    text-align:center;

    color:
        var(--muted);

    font-size:12px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media(max-width:1150px) {

    .cards,
    .operations {

        grid-template-columns:
            repeat(2,1fr);
    }


    .content-grid {

        grid-template-columns:1fr;
    }


    .quick-grid {

        grid-template-columns:
            repeat(2,1fr);
    }
}


@media(max-width:850px) {

    .sidebar {

        width:205px;
        min-width:205px;
    }


    .main {

        padding:22px;
    }


    .hero {

        grid-template-columns:1fr;
    }
}


@media(max-width:700px) {

    .sidebar {

        display:none;
    }


    .topbar {

        align-items:flex-start;
    }


    .warden-info > div:last-child {

        display:none;
    }
}


@media(max-width:520px) {

    .main {

        padding:14px;
    }


    .cards,
    .operations,
    .quick-grid {

        grid-template-columns:1fr;
    }


    .hero {

        padding:23px;
    }


    .hero h2 {

        font-size:29px;
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

<i class="fa-solid fa-user-shield"></i>

</div>


<div>

<h1>
Warden Dashboard
</h1>

<p>
Monitor hostel activities and student requests.
</p>

</div>


</div>



<div class="warden-info">


<div class="warden-avatar">

<?php
echo getInitial(
    $wardenName
);
?>

</div>


<div>

<strong>

<?php
echo htmlspecialchars(
    $wardenName
);
?>

</strong>

<span>
Hostel Warden
</span>

</div>


</div>


</div>



<!-- HERO -->

<div class="hero">


<div>


<div class="hero-label">
Hostel Management
</div>


<h2>

Welcome,
<?php
echo htmlspecialchars(
    $wardenName
);
?> 👋

</h2>


<p>

Manage student requests, monitor hostel operations
and respond quickly to important activities from
one central dashboard.

</p>


<div class="hero-date">

<i class="fa-regular fa-calendar"></i>

<?php
echo date(
    "l, d F Y"
);
?>

</div>


</div>



<div class="hero-box">


<div class="hero-box-head">


<div class="hero-box-icon">

<i class="fa-solid fa-shield-halved"></i>

</div>


<div>

<strong>
Hostel Overview
</strong>

<p>
Live management summary
</p>

</div>


</div>



<div class="hero-stats">


<div class="hero-stat">

<span>
Students
</span>

<strong>
<?php echo $totalStudents; ?>
</strong>

</div>



<div class="hero-stat">

<span>
Requests
</span>

<strong>
<?php echo $attentionCount; ?>
</strong>

</div>



<div class="hero-stat">

<span>
SOS
</span>

<strong>
<?php echo $activeSOS; ?>
</strong>

</div>


</div>


</div>


</div>



<!-- MAIN COMPLAINT CARDS -->

<div class="cards">


<div class="card">

<div class="card-icon">

<i class="fa-regular fa-clock"></i>

</div>

<div>

<p>
Pending Complaints
</p>

<h2>
<?php echo $pendingComplaints; ?>
</h2>

</div>

</div>



<div class="card">

<div class="card-icon">

<i class="fa-solid fa-spinner"></i>

</div>

<div>

<p>
In Progress
</p>

<h2>
<?php echo $inProgressComplaints; ?>
</h2>

</div>

</div>



<div class="card">

<div class="card-icon">

<i class="fa-solid fa-circle-check"></i>

</div>

<div>

<p>
Resolved
</p>

<h2>
<?php echo $resolvedComplaints; ?>
</h2>

</div>

</div>



<div class="card">

<div class="card-icon">

<i class="fa-solid fa-user-graduate"></i>

</div>

<div>

<p>
Total Students
</p>

<h2>
<?php echo $totalStudents; ?>
</h2>

</div>

</div>


</div>



<!-- NEW OPERATIONS -->

<div class="operations">


<a
    href="emergency.php"
    class="operation-card sos"
>

<div class="operation-icon">

<i class="fa-solid fa-triangle-exclamation"></i>

</div>


<div>

<div class="operation-label">
Active SOS
</div>

<div class="operation-number">

<?php echo $activeSOS; ?>

</div>

<div class="operation-note">
Requires attention
</div>

</div>

</a>



<a
    href="visitor_parcel.php"
    class="operation-card visitor"
>

<div class="operation-icon">

<i class="fa-solid fa-door-open"></i>

</div>


<div>

<div class="operation-label">
Visitors Inside
</div>

<div class="operation-number">

<?php echo $visitorsInside; ?>

</div>

<div class="operation-note">
Currently in hostel
</div>

</div>

</a>



<a
    href="visitor_parcel.php"
    class="operation-card parcel"
>

<div class="operation-icon">

<i class="fa-solid fa-box-open"></i>

</div>


<div>

<div class="operation-label">
Waiting Parcels
</div>

<div class="operation-number">

<?php echo $waitingParcels; ?>

</div>

<div class="operation-note">
Awaiting pickup
</div>

</div>

</a>



<a
    href="events.php"
    class="operation-card event"
>

<div class="operation-icon">

<i class="fa-solid fa-calendar-days"></i>

</div>


<div>

<div class="operation-label">
Upcoming Events
</div>

<div class="operation-number">

<?php echo $upcomingEvents; ?>

</div>

<div class="operation-note">
Scheduled activities
</div>

</div>

</a>


</div>



<!-- CONTENT -->

<div class="content-grid">


<!-- RECENT COMPLAINTS -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">


<div class="panel-icon">

<i class="fa-solid fa-screwdriver-wrench"></i>

</div>


<div>

<h2>
Recent Complaints
</h2>

<p>
Latest issues reported by students.
</p>

</div>


</div>


<a
    href="complaints.php"
    class="view-link"
>

View All

<i class="fa-solid fa-arrow-right"></i>

</a>


</div>



<div class="table-wrap">


<table>


<thead>

<tr>

<th>Student</th>
<th>Category</th>
<th>Location</th>
<th>Priority</th>
<th>Status</th>

</tr>

</thead>


<tbody>


<?php

if (
    $recentComplaints &&
    mysqli_num_rows(
        $recentComplaints
    ) > 0
) {

    while (
        $complaint =
        mysqli_fetch_assoc(
            $recentComplaints
        )
    ) {

        $statusClass =
            strtolower(
                str_replace(
                    " ",
                    "-",
                    $complaint["status"]
                )
            );

        $priorityClass =
            strtolower(
                $complaint["priority"]
            );

        $studentName =
            $complaint["name"] ??
            "Unknown";

?>


<tr>


<td>

<div class="student-cell">

<div class="mini-avatar">

<?php
echo getInitial(
    $studentName
);
?>

</div>

<?php
echo htmlspecialchars(
    $studentName
);
?>

</div>

</td>


<td>

<?php
echo htmlspecialchars(
    $complaint["category"]
);
?>

</td>


<td>

<i
    class="fa-solid fa-location-dot"
    style="color:#168295;"
></i>

<?php
echo htmlspecialchars(
    $complaint["location"]
);
?>

</td>


<td>

<span
    class="priority <?php echo $priorityClass; ?>"
>

<?php
echo htmlspecialchars(
    $complaint["priority"]
);
?>

</span>

</td>


<td>

<span
    class="status <?php echo $statusClass; ?>"
>

<?php
echo htmlspecialchars(
    $complaint["status"]
);
?>

</span>

</td>


</tr>


<?php

    }

} else {

?>


<tr>

<td
    colspan="5"
    class="empty"
>

No complaints available.

</td>

</tr>


<?php } ?>


</tbody>


</table>


</div>


</div>



<!-- NEEDS ATTENTION -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">


<div class="panel-icon">

<i class="fa-solid fa-bell"></i>

</div>


<div>

<h2>
Needs Attention
</h2>

<p>
Important hostel activities.
</p>

</div>


</div>


</div>



<div class="attention-list">


<div class="attention-item danger">


<div class="attention-icon">

<i class="fa-solid fa-triangle-exclamation"></i>

</div>


<div>

<div class="attention-title">
Emergency SOS
</div>

<div class="attention-sub">

<?php

if ($latestSOS) {

    echo htmlspecialchars(
        $latestSOS["name"]
    );

    echo " • ";

    echo htmlspecialchars(
        $latestSOS["emergency_type"]
    );

    echo " • ";

    echo htmlspecialchars(
        $latestSOS["status"]
    );

} else {

    echo "No active emergency alerts.";
}

?>

</div>

</div>


<div class="attention-count">

<?php echo $activeSOS; ?>

</div>


</div>



<div class="attention-item warning">


<div class="attention-icon">

<i class="fa-solid fa-box-open"></i>

</div>


<div>

<div class="attention-title">
Parcels Waiting
</div>

<div class="attention-sub">
Students need to collect their parcels.
</div>

</div>


<div class="attention-count">

<?php echo $waitingParcels; ?>

</div>


</div>



<div class="attention-item blue">


<div class="attention-icon">

<i class="fa-solid fa-calendar-check"></i>

</div>


<div>

<div class="attention-title">
Leave Requests
</div>

<div class="attention-sub">
Waiting for approval or rejection.
</div>

</div>


<div class="attention-count">

<?php echo $pendingLeaves; ?>

</div>


</div>



<div class="attention-item">


<div class="attention-icon">

<i class="fa-solid fa-shirt"></i>

</div>


<div>

<div class="attention-title">
Laundry Requests
</div>

<div class="attention-sub">
Pending student laundry bookings.
</div>

</div>


<div class="attention-count">

<?php echo $pendingLaundry; ?>

</div>


</div>



<?php if ($nextEvent) { ?>


<div class="attention-item">


<div class="attention-icon">

<i class="fa-solid fa-calendar-days"></i>

</div>


<div>

<div class="attention-title">

<?php
echo htmlspecialchars(
    $nextEvent["title"]
);
?>

</div>


<div class="attention-sub">

<?php

echo date(
    "d M Y",
    strtotime(
        $nextEvent["event_date"]
    )
);

echo " • ";

echo date(
    "h:i A",
    strtotime(
        $nextEvent["start_time"]
    )
);

echo " • ";

echo htmlspecialchars(
    $nextEvent["venue"]
);

?>

</div>

</div>


<div class="attention-count">

<i class="fa-solid fa-chevron-right"></i>

</div>


</div>


<?php } ?>


</div>


</div>


</div>



<!-- QUICK ACTIONS -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">


<div class="panel-icon">

<i class="fa-solid fa-bolt"></i>

</div>


<div>

<h2>
Quick Actions
</h2>

<p>
Manage frequently used hostel services.
</p>

</div>


</div>


</div>



<div class="quick-grid">


<a
    href="complaints.php"
    class="quick-card"
>

<div class="quick-icon">

<i class="fa-solid fa-screwdriver-wrench"></i>

</div>

<div>

<div class="quick-title">
Complaints
</div>

<div class="quick-subtitle">
Review hostel issues
</div>

<div class="quick-count">

<?php echo $pendingComplaints; ?>
 Pending

</div>

</div>

</a>



<a
    href="leave_requests.php"
    class="quick-card"
>

<div class="quick-icon">

<i class="fa-solid fa-calendar-check"></i>

</div>

<div>

<div class="quick-title">
Leave Requests
</div>

<div class="quick-subtitle">
Approve student leaves
</div>

<div class="quick-count">

<?php echo $pendingLeaves; ?>
 Pending

</div>

</div>

</a>



<a
    href="laundry.php"
    class="quick-card"
>

<div class="quick-icon">

<i class="fa-solid fa-shirt"></i>

</div>

<div>

<div class="quick-title">
Laundry
</div>

<div class="quick-subtitle">
Manage laundry requests
</div>

<div class="quick-count">

<?php echo $pendingLaundry; ?>
 Pending

</div>

</div>

</a>



<a
    href="emergency.php"
    class="quick-card"
>

<div class="quick-icon">

<i class="fa-solid fa-triangle-exclamation"></i>

</div>

<div>

<div class="quick-title">
Emergency SOS
</div>

<div class="quick-subtitle">
Respond to emergencies
</div>

<div class="quick-count">

<?php echo $activeSOS; ?>
 Active

</div>

</div>

</a>



<a
    href="visitor_parcel.php"
    class="quick-card"
>

<div class="quick-icon">

<i class="fa-solid fa-door-open"></i>

</div>

<div>

<div class="quick-title">
Visitors & Parcels
</div>

<div class="quick-subtitle">
Reception management
</div>

<div class="quick-count">

<?php echo $waitingParcels; ?>
 Parcels

</div>

</div>

</a>



<a
    href="events.php"
    class="quick-card"
>

<div class="quick-icon">

<i class="fa-solid fa-calendar-days"></i>

</div>

<div>

<div class="quick-title">
Events
</div>

<div class="quick-subtitle">
Create and manage events
</div>

<div class="quick-count">

<?php echo $upcomingEvents; ?>
 Upcoming

</div>

</div>

</a>



<a
    href="notices.php"
    class="quick-card"
>

<div class="quick-icon">

<i class="fa-solid fa-bullhorn"></i>

</div>

<div>

<div class="quick-title">
Notices
</div>

<div class="quick-subtitle">
Post hostel updates
</div>

<div class="quick-count">

<?php echo $totalNotices; ?>
 Total

</div>

</div>

</a>



<a
    href="students.php"
    class="quick-card"
>

<div class="quick-icon">

<i class="fa-solid fa-user-graduate"></i>

</div>

<div>

<div class="quick-title">
Students
</div>

<div class="quick-subtitle">
View student records
</div>

<div class="quick-count">

<?php echo $totalStudents; ?>
 Students

</div>

</div>

</a>


</div>


</div>


</div>


</div>


</div>


</body>

</html>
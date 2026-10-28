<?php

session_start();
include "../db.php";

date_default_timezone_set("Asia/Kolkata");


/* =========================================================
   ONLY WARDEN
   ========================================================= */

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["user_role"] != "warden"
) {
    header("Location: ../index.php");
    exit;
}

$wardenName = $_SESSION["user_name"];

$success = "";
$error = "";


/* =========================================================
   UPDATE LAUNDRY REQUEST
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $requestId =
        intval($_POST["request_id"] ?? 0);

    $status =
        trim($_POST["status"] ?? "");

    $wardenRemark =
        trim($_POST["warden_remark"] ?? "");


    $allowedStatuses = [
        "Pending",
        "In Progress",
        "Completed",
        "Rejected"
    ];


    if (
        $requestId <= 0 ||
        !in_array($status, $allowedStatuses)
    ) {

        $error =
            "Invalid laundry request.";

    } else {

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE laundry_requests
             SET status = ?,
                 warden_remark = ?
             WHERE id = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ssi",
            $status,
            $wardenRemark,
            $requestId
        );


        if (mysqli_stmt_execute($stmt)) {

            $success =
                "Laundry request updated successfully.";

        } else {

            $error =
                "Unable to update laundry request.";
        }
    }
}


/* =========================================================
   COUNTS
   ========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM laundry_requests
     WHERE status = 'Pending'"
);

$pendingCount =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM laundry_requests
     WHERE status = 'In Progress'"
);

$progressCount =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM laundry_requests
     WHERE status = 'Completed'"
);

$completedCount =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM laundry_requests
     WHERE status = 'Rejected'"
);

$rejectedCount =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


$totalRequests =
    $pendingCount +
    $progressCount +
    $completedCount +
    $rejectedCount;

$activeRequests =
    $pendingCount +
    $progressCount;


/* =========================================================
   ALL REQUESTS
   ========================================================= */

$requests = mysqli_query(
    $conn,
    "SELECT
        laundry_requests.id,
        laundry_requests.laundry_date,
        laundry_requests.time_slot,
        laundry_requests.clothes_count,
        laundry_requests.service_type,
        laundry_requests.status,
        laundry_requests.warden_remark,
        laundry_requests.created_at,
        users.name,
        users.email

     FROM laundry_requests

     LEFT JOIN users
     ON laundry_requests.user_id = users.id

     ORDER BY
        CASE
            WHEN laundry_requests.status = 'Pending' THEN 1
            WHEN laundry_requests.status = 'In Progress' THEN 2
            WHEN laundry_requests.status = 'Completed' THEN 3
            ELSE 4
        END,
        laundry_requests.id DESC"
);


/* =========================================================
   HELPERS
   ========================================================= */

function statusClass($status)
{
    return strtolower(
        str_replace(
            " ",
            "-",
            $status
        )
    );
}


function getInitial($name)
{
    $name = trim($name);

    if ($name == "") {
        return "U";
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

<title>
Laundry Management - UniStay
</title>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
>


<style>

/* =========================================================
   COLORS
   ========================================================= */

:root {

    --navy:#1F2747;
    --deep-navy:#14354F;

    --teal:#0E6077;
    --teal-2:#168295;
    --teal-soft:#E1F0F3;

    --mustard:#E8B23D;
    --mustard-soft:#FFF2D1;

    --blue:#4387A5;
    --blue-soft:#E5F0F5;

    --orange:#E98C3A;
    --orange-soft:#FDEADB;

    --green:#36A878;
    --green-soft:#E3F5EC;

    --red:#D9656C;
    --red-soft:#FBE8EA;

    --background:#EFF4F7;
    --card:#FFFFFF;

    --border:#DFE7EC;

    --text:#1E2639;
    --muted:#718092;

    --shadow:
        0 8px 24px
        rgba(31,39,71,0.06);
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
            #168295
        );

    font-size:21px;
    font-weight:800;

    box-shadow:
        0 8px 18px
        rgba(14,96,119,0.20);
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

    transition:
        0.18s ease;
}

.menu a i {

    width:22px;

    text-align:center;

    color:#395875;

    font-size:15px;
}

.menu a:hover {

    background:
        #F0F7F9;

    color:
        var(--teal);
}

.menu a.active {

    background:
        #DDF0F3;

    color:
        var(--teal);

    font-weight:700;
}

.menu a.active i {

    color:
        var(--teal);
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

    justify-content:space-between;
    align-items:center;

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

    flex-shrink:0;

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

    box-shadow:
        0 7px 17px
        rgba(14,96,119,0.18);
}

.topbar h1 {

    color:
        var(--text);

    font-size:30px;

    letter-spacing:
        -0.6px;
}

.topbar p {

    margin-top:5px;

    color:
        var(--muted);

    font-size:14px;
}


/* =========================================================
   WARDEN PROFILE
   ========================================================= */

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

    box-shadow:
        0 4px 15px
        rgba(31,39,71,0.035);
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
            #3596A5
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

    position:relative;

    overflow:hidden;

    min-height:215px;

    display:grid;

    grid-template-columns:
        minmax(0,1fr)
        330px;

    align-items:center;

    gap:30px;

    margin-bottom:20px;

    padding:
        30px 33px;

    border-radius:20px;

    color:white;

    background:
        linear-gradient(
            120deg,
            #1F2747 0%,
            #17475E 38%,
            #0E6077 72%,
            #25899A 100%
        );

    box-shadow:
        0 13px 30px
        rgba(18,71,91,0.15);
}

.hero::before {

    content:"";

    position:absolute;

    width:300px;
    height:300px;

    right:-100px;
    top:-185px;

    border-radius:50%;

    background:
        rgba(255,255,255,0.07);
}

.hero::after {

    content:"";

    position:absolute;

    width:180px;
    height:180px;

    left:43%;
    bottom:-145px;

    border-radius:50%;

    background:
        rgba(232,178,61,0.13);
}

.hero-content {

    position:relative;

    z-index:2;
}

.hero-label {

    color:
        #F6D77C;

    font-size:12px;

    font-weight:700;

    text-transform:uppercase;

    letter-spacing:1px;
}

.hero h2 {

    margin-top:7px;

    font-size:33px;

    line-height:1.2;

    letter-spacing:-0.6px;
}

.hero p {

    max-width:600px;

    margin-top:9px;

    color:
        rgba(255,255,255,0.84);

    font-size:15px;

    line-height:1.6;
}

.hero-chip {

    display:inline-flex;

    align-items:center;

    gap:7px;

    margin-top:17px;

    padding:
        8px 13px;

    border-radius:20px;

    color:#273449;

    background:
        var(--mustard);

    font-size:12px;

    font-weight:700;
}


/* HERO SIDE */

.hero-side {

    position:relative;

    z-index:2;

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:10px;
}

.hero-mini {

    padding:16px;

    border-radius:14px;

    background:
        rgba(255,255,255,0.12);

    border:
        1px solid
        rgba(255,255,255,0.15);
}

.hero-mini-icon {

    width:40px;
    height:40px;

    display:flex;

    align-items:center;
    justify-content:center;

    margin-bottom:11px;

    border-radius:10px;

    background:
        var(--mustard);

    color:
        var(--navy);

    font-size:16px;
}

.hero-mini:nth-child(2)
.hero-mini-icon {

    background:#D8EDF2;

    color:
        var(--teal);
}

.hero-mini span {

    display:block;

    color:
        rgba(255,255,255,0.72);

    font-size:11px;

    text-transform:uppercase;
}

.hero-mini strong {

    display:block;

    margin-top:4px;

    color:white;

    font-size:25px;
}


/* =========================================================
   SUMMARY CARDS
   ========================================================= */

.cards {

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:15px;

    margin-bottom:20px;
}

.card {

    min-height:122px;

    display:flex;

    align-items:center;

    gap:14px;

    padding:17px;

    border-radius:14px;

    background:white;

    border:
        1px solid
        var(--border);

    box-shadow:
        var(--shadow);
}

.card:nth-child(1) {

    border-top:
        4px solid
        var(--mustard);
}

.card:nth-child(2) {

    border-top:
        4px solid
        var(--blue);
}

.card:nth-child(3) {

    border-top:
        4px solid
        var(--green);
}

.card:nth-child(4) {

    border-top:
        4px solid
        var(--red);
}

.card-icon {

    width:50px;
    height:50px;

    flex-shrink:0;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:13px;

    font-size:18px;
}

.card:nth-child(1)
.card-icon {

    color:#9C6805;

    background:
        var(--mustard-soft);
}

.card:nth-child(2)
.card-icon {

    color:
        var(--blue);

    background:
        var(--blue-soft);
}

.card:nth-child(3)
.card-icon {

    color:
        var(--green);

    background:
        var(--green-soft);
}

.card:nth-child(4)
.card-icon {

    color:
        var(--red);

    background:
        var(--red-soft);
}

.card p {

    color:
        var(--muted);

    font-size:12px;

    text-transform:uppercase;

    letter-spacing:0.4px;
}

.card h2 {

    margin-top:4px;

    color:
        var(--text);

    font-size:28px;
}


/* =========================================================
   PANEL
   ========================================================= */

.panel {

    overflow:hidden;

    border-radius:16px;

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

    padding:20px 22px;

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

.panel-head h2 {

    color:
        var(--text);

    font-size:20px;
}

.panel-head p {

    margin-top:3px;

    color:
        var(--muted);

    font-size:13px;
}

.request-count {

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

    font-size:12px;

    font-weight:700;
}


/* =========================================================
   MESSAGES
   ========================================================= */

.success-message,
.error-message {

    display:flex;

    align-items:center;

    gap:10px;

    margin:
        16px 20px 0;

    padding:
        14px 16px;

    border-radius:11px;

    font-size:13px;

    font-weight:600;
}

.success-message {

    color:#17684D;

    background:
        #E4F6ED;

    border:
        1px solid
        #C9E9DA;
}

.error-message {

    color:#A64048;

    background:
        #FDEDEE;

    border:
        1px solid
        #F5D3D6;
}


/* =========================================================
   REQUEST LIST
   ========================================================= */

.request-list {

    padding:18px;
}

.request-card {

    position:relative;

    overflow:hidden;

    margin-bottom:16px;

    padding:18px;

    border-radius:14px;

    background:#FBFCFD;

    border:
        1px solid
        #E4EBEE;

    transition:
        0.18s ease;
}

.request-card:last-child {

    margin-bottom:0;
}

.request-card:hover {

    transform:
        translateY(-1px);

    box-shadow:
        0 7px 20px
        rgba(31,39,71,0.05);
}

.request-card::before {

    content:"";

    position:absolute;

    top:0;
    bottom:0;
    left:0;

    width:5px;

    background:
        var(--teal);
}

.request-card.pending::before {

    background:
        var(--mustard);
}

.request-card.in-progress::before {

    background:
        var(--blue);
}

.request-card.completed::before {

    background:
        var(--green);
}

.request-card.rejected::before {

    background:
        var(--red);
}


/* =========================================================
   REQUEST TOP
   ========================================================= */

.request-top {

    display:flex;

    align-items:flex-start;
    justify-content:space-between;

    gap:15px;

    margin-bottom:16px;
}

.student-wrap {

    display:flex;

    align-items:center;

    gap:12px;
}

.student-avatar {

    width:46px;
    height:46px;

    flex-shrink:0;

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

    font-size:15px;

    font-weight:800;
}

.student-name {

    color:
        var(--text);

    font-size:16px;

    font-weight:750;
}

.student-email {

    margin-top:3px;

    color:
        var(--muted);

    font-size:12px;
}

.request-id {

    margin-top:4px;

    color:#8A95A1;

    font-size:11px;
}


/* =========================================================
   STATUS
   ========================================================= */

.status {

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:
        7px 11px;

    border-radius:20px;

    white-space:nowrap;

    font-size:11px;

    font-weight:700;
}

.pending {

    background:
        var(--mustard-soft);

    color:#956305;
}

.in-progress {

    background:
        var(--blue-soft);

    color:#326B88;
}

.completed {

    background:
        var(--green-soft);

    color:#187451;
}

.rejected {

    background:
        var(--red-soft);

    color:#AE444C;
}


/* =========================================================
   DETAILS
   ========================================================= */

.details {

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:11px;

    margin-bottom:16px;
}

.detail {

    min-height:90px;

    padding:13px;

    border-radius:11px;

    background:white;

    border:
        1px solid
        #E7EDF0;
}

.detail-icon {

    width:30px;
    height:30px;

    display:flex;

    align-items:center;
    justify-content:center;

    margin-bottom:8px;

    border-radius:8px;

    font-size:12px;
}

.detail:nth-child(1)
.detail-icon {

    color:
        var(--teal);

    background:
        var(--teal-soft);
}

.detail:nth-child(2)
.detail-icon {

    color:#986507;

    background:
        var(--mustard-soft);
}

.detail:nth-child(3)
.detail-icon {

    color:
        var(--orange);

    background:
        var(--orange-soft);
}

.detail:nth-child(4)
.detail-icon {

    color:
        var(--blue);

    background:
        var(--blue-soft);
}

.detail-label {

    margin-bottom:4px;

    color:
        var(--muted);

    font-size:11px;

    text-transform:uppercase;

    letter-spacing:0.3px;
}

.detail-value {

    color:
        var(--text);

    font-size:13px;

    font-weight:700;

    line-height:1.4;
}


/* =========================================================
   EXISTING REMARK
   ========================================================= */

.current-remark {

    display:flex;

    align-items:flex-start;

    gap:9px;

    margin-bottom:15px;

    padding:12px 13px;

    border-radius:10px;

    color:#526474;

    background:
        linear-gradient(
            90deg,
            #EEF7F8,
            #FFF8E9
        );

    border:
        1px solid
        #E0EAE9;

    font-size:13px;

    line-height:1.5;
}

.current-remark i {

    margin-top:3px;

    color:
        var(--teal);
}

.current-remark strong {

    color:
        var(--text);
}


/* =========================================================
   UPDATE FORM
   ========================================================= */

.update-area {

    padding-top:16px;

    border-top:
        1px solid
        #E5EBEE;
}

.update-label {

    margin-bottom:10px;

    color:
        var(--text);

    font-size:13px;

    font-weight:700;
}

.update-label i {

    margin-right:6px;

    color:
        var(--teal);
}

.update-form {

    display:grid;

    grid-template-columns:
        200px minmax(220px,1fr) 125px;

    gap:11px;

    align-items:center;
}

.form-control {

    width:100%;

    padding:
        11px 12px;

    outline:none;

    border:
        1px solid
        #D5E0E5;

    border-radius:9px;

    background:white;

    color:
        var(--text);

    font-size:13px;
}

.form-control:focus {

    border-color:
        #5B9DAC;

    box-shadow:
        0 0 0 3px
        rgba(14,96,119,0.08);
}

.update-btn {

    display:flex;

    align-items:center;
    justify-content:center;

    gap:7px;

    border:none;

    padding:
        12px 14px;

    border-radius:9px;

    color:white;

    background:
        linear-gradient(
            135deg,
            #0D566C,
            #168295
        );

    cursor:pointer;

    font-size:13px;

    font-weight:700;

    box-shadow:
        0 5px 13px
        rgba(14,96,119,0.15);

    transition:
        0.18s ease;
}

.update-btn:hover {

    transform:
        translateY(-1px);

    background:
        linear-gradient(
            135deg,
            #0A4B5E,
            #0E6E7E
        );
}


/* =========================================================
   APPLIED DATE
   ========================================================= */

.applied-time {

    display:flex;

    align-items:center;

    gap:6px;

    margin-top:12px;

    color:
        #87929D;

    font-size:11px;
}


/* =========================================================
   EMPTY
   ========================================================= */

.empty {

    padding:
        60px 20px;

    text-align:center;

    color:
        var(--muted);
}

.empty-icon {

    width:72px;
    height:72px;

    margin:
        0 auto 15px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:20px;

    color:
        var(--teal);

    background:
        linear-gradient(
            135deg,
            #DDF0F3,
            #FFF1CB
        );

    font-size:28px;
}

.empty h3 {

    color:
        var(--text);

    font-size:17px;
}

.empty p {

    margin-top:5px;

    font-size:13px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media(max-width:1100px) {

    .hero {

        grid-template-columns:
            1fr 280px;
    }

    .cards {

        grid-template-columns:
            repeat(2,1fr);
    }

    .details {

        grid-template-columns:
            repeat(2,1fr);
    }

    .update-form {

        grid-template-columns:1fr;
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

    .hero-side {

        max-width:380px;
    }
}


@media(max-width:700px) {

    .sidebar {

        display:none;
    }

    .topbar {

        align-items:flex-start;
    }

    .warden-info {

        padding:7px;
    }

    .warden-info > div:last-child {

        display:none;
    }
}


@media(max-width:560px) {

    .main {

        padding:14px;
    }

    .topbar h1 {

        font-size:25px;
    }

    .hero {

        padding:23px;
    }

    .hero h2 {

        font-size:27px;
    }

    .hero-side {

        grid-template-columns:1fr 1fr;

        width:100%;
    }

    .cards {

        grid-template-columns:1fr;
    }

    .details {

        grid-template-columns:1fr;
    }

    .request-top {

        flex-direction:column;
    }
}

</style>

</head>


<body>


<div class="dashboard">


<!-- =========================================================
     COMMON WARDEN SIDEBAR
     ========================================================= -->

<?php include "sidebar.php"; ?>



<!-- =========================================================
     MAIN
     ========================================================= -->

<div class="main">


<!-- =========================================================
     TOPBAR
     ========================================================= -->

<div class="topbar">


<div class="page-title">


<div class="page-icon">

<i class="fa-solid fa-shirt"></i>

</div>


<div>

<h1>
Laundry Management
</h1>

<p>
Review and manage student laundry requests.
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



<!-- =========================================================
     HERO
     ========================================================= -->

<div class="hero">


<div class="hero-content">


<div class="hero-label">

Laundry Operations

</div>


<h2>
Clean clothes.
Smooth queue. 🧺
</h2>


<p>

Manage student laundry bookings, update their
processing status and provide remarks so students
always know the progress of their request.

</p>


<div class="hero-chip">

<i class="fa-solid fa-wand-magic-sparkles"></i>

Easy Request Tracking

</div>


</div>



<div class="hero-side">


<div class="hero-mini">


<div class="hero-mini-icon">

<i class="fa-solid fa-layer-group"></i>

</div>


<span>
All Requests
</span>


<strong>

<?php echo $totalRequests; ?>

</strong>


</div>



<div class="hero-mini">


<div class="hero-mini-icon">

<i class="fa-solid fa-arrows-rotate"></i>

</div>


<span>
Active Now
</span>


<strong>

<?php echo $activeRequests; ?>

</strong>


</div>


</div>


</div>



<!-- =========================================================
     SUMMARY CARDS
     ========================================================= -->

<div class="cards">


<!-- PENDING -->

<div class="card">


<div class="card-icon">

<i class="fa-solid fa-clock"></i>

</div>


<div>

<p>
Pending
</p>

<h2>

<?php echo $pendingCount; ?>

</h2>

</div>


</div>



<!-- IN PROGRESS -->

<div class="card">


<div class="card-icon">

<i class="fa-solid fa-arrows-rotate"></i>

</div>


<div>

<p>
In Progress
</p>

<h2>

<?php echo $progressCount; ?>

</h2>

</div>


</div>



<!-- COMPLETED -->

<div class="card">


<div class="card-icon">

<i class="fa-solid fa-circle-check"></i>

</div>


<div>

<p>
Completed
</p>

<h2>

<?php echo $completedCount; ?>

</h2>

</div>


</div>



<!-- REJECTED -->

<div class="card">


<div class="card-icon">

<i class="fa-solid fa-circle-xmark"></i>

</div>


<div>

<p>
Rejected
</p>

<h2>

<?php echo $rejectedCount; ?>

</h2>

</div>


</div>


</div>



<!-- =========================================================
     REQUEST PANEL
     ========================================================= -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">


<div class="panel-icon">

<i class="fa-solid fa-shirt"></i>

</div>


<div>

<h2>
Student Laundry Requests
</h2>

<p>
Review bookings and update their current progress.
</p>

</div>


</div>



<div class="request-count">

<i class="fa-solid fa-list-check"></i>

<?php echo $totalRequests; ?> Requests

</div>


</div>



<!-- =========================================================
     MESSAGES
     ========================================================= -->

<?php if (!empty($success)) { ?>


<div class="success-message">

<i class="fa-solid fa-circle-check"></i>

<?php
echo htmlspecialchars(
    $success
);
?>

</div>


<?php } ?>



<?php if (!empty($error)) { ?>


<div class="error-message">

<i class="fa-solid fa-circle-exclamation"></i>

<?php
echo htmlspecialchars(
    $error
);
?>

</div>


<?php } ?>



<div class="request-list">


<?php

if (
    $requests &&
    mysqli_num_rows($requests) > 0
) {

    while (
        $request =
        mysqli_fetch_assoc($requests)
    ) {

        $class =
            statusClass(
                $request["status"]
            );

        $studentName =
            $request["name"] ??
            "Unknown Student";

?>


<!-- =====================================================
     REQUEST CARD
     ===================================================== -->

<div class="request-card <?php echo $class; ?>">


<div class="request-top">


<div class="student-wrap">


<div class="student-avatar">

<?php
echo getInitial(
    $studentName
);
?>

</div>


<div>


<div class="student-name">

<?php
echo htmlspecialchars(
    $studentName
);
?>

</div>


<div class="student-email">

<?php
echo htmlspecialchars(
    $request["email"] ?? ""
);
?>

</div>


<div class="request-id">

Request #<?php echo intval($request["id"]); ?>

</div>


</div>


</div>



<span class="status <?php echo $class; ?>">


<?php if ($request["status"] == "Pending") { ?>

<i class="fa-solid fa-clock"></i>

<?php } elseif ($request["status"] == "In Progress") { ?>

<i class="fa-solid fa-arrows-rotate"></i>

<?php } elseif ($request["status"] == "Completed") { ?>

<i class="fa-solid fa-circle-check"></i>

<?php } else { ?>

<i class="fa-solid fa-circle-xmark"></i>

<?php } ?>


<?php
echo htmlspecialchars(
    $request["status"]
);
?>

</span>


</div>



<!-- =====================================================
     DETAILS
     ===================================================== -->

<div class="details">


<div class="detail">


<div class="detail-icon">

<i class="fa-regular fa-calendar"></i>

</div>


<div class="detail-label">
Laundry Date
</div>


<div class="detail-value">

<?php

echo date(
    "d M Y",
    strtotime(
        $request["laundry_date"]
    )
);

?>

</div>


</div>



<div class="detail">


<div class="detail-icon">

<i class="fa-regular fa-clock"></i>

</div>


<div class="detail-label">
Time Slot
</div>


<div class="detail-value">

<?php
echo htmlspecialchars(
    $request["time_slot"]
);
?>

</div>


</div>



<div class="detail">


<div class="detail-icon">

<i class="fa-solid fa-shirt"></i>

</div>


<div class="detail-label">
Clothes
</div>


<div class="detail-value">

<?php
echo intval(
    $request["clothes_count"]
);
?>

Items

</div>


</div>



<div class="detail">


<div class="detail-icon">

<i class="fa-solid fa-soap"></i>

</div>


<div class="detail-label">
Service Type
</div>


<div class="detail-value">

<?php
echo htmlspecialchars(
    $request["service_type"]
);
?>

</div>


</div>


</div>



<!-- =====================================================
     CURRENT REMARK
     ===================================================== -->

<?php
if (
    !empty(
        trim(
            $request["warden_remark"] ?? ""
        )
    )
) {
?>


<div class="current-remark">


<i class="fa-solid fa-message"></i>


<div>

<strong>
Current Warden Remark:
</strong>

<?php
echo htmlspecialchars(
    $request["warden_remark"]
);
?>

</div>


</div>


<?php } ?>



<!-- =====================================================
     UPDATE
     ===================================================== -->

<div class="update-area">


<div class="update-label">

<i class="fa-solid fa-pen-to-square"></i>

Update Request

</div>



<form
    method="POST"
    class="update-form"
>


<input
    type="hidden"
    name="request_id"
    value="<?php echo intval($request["id"]); ?>"
>



<select
    name="status"
    class="form-control"
    required
>


<option
    value="Pending"
    <?php
    if (
        $request["status"] == "Pending"
    ) {
        echo "selected";
    }
    ?>
>
Pending
</option>


<option
    value="In Progress"
    <?php
    if (
        $request["status"] == "In Progress"
    ) {
        echo "selected";
    }
    ?>
>
In Progress
</option>


<option
    value="Completed"
    <?php
    if (
        $request["status"] == "Completed"
    ) {
        echo "selected";
    }
    ?>
>
Completed
</option>


<option
    value="Rejected"
    <?php
    if (
        $request["status"] == "Rejected"
    ) {
        echo "selected";
    }
    ?>
>
Rejected
</option>


</select>



<input
    type="text"
    name="warden_remark"
    class="form-control"
    placeholder="Add optional remark for student"
    value="<?php
    echo htmlspecialchars(
        $request["warden_remark"] ?? ""
    );
    ?>"
>



<button
    type="submit"
    class="update-btn"
>

<i class="fa-solid fa-floppy-disk"></i>

Update

</button>


</form>


<div class="applied-time">

<i class="fa-regular fa-clock"></i>

Request submitted:

<?php

echo date(
    "d M Y, h:i A",
    strtotime(
        $request["created_at"]
    )
);

?>

</div>


</div>


</div>


<?php

    }

} else {

?>


<!-- =====================================================
     EMPTY
     ===================================================== -->

<div class="empty">


<div class="empty-icon">

<i class="fa-solid fa-shirt"></i>

</div>


<h3>
No laundry requests yet
</h3>


<p>
Student laundry bookings will appear here.
</p>


</div>


<?php } ?>


</div>


</div>


</div>


</div>



<script>

setTimeout(function () {

    const success =
        document.querySelector(
            ".success-message"
        );

    const error =
        document.querySelector(
            ".error-message"
        );


    if (success) {

        success.style.opacity =
            "0";

        success.style.transition =
            "opacity 0.4s ease";


        setTimeout(function () {

            success.style.display =
                "none";

        }, 400);
    }


    if (error) {

        error.style.opacity =
            "0";

        error.style.transition =
            "opacity 0.4s ease";


        setTimeout(function () {

            error.style.display =
                "none";

        }, 400);
    }

}, 3000);

</script>


</body>
</html>
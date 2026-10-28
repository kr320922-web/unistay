<?php

session_start();
include "../db.php";

date_default_timezone_set("Asia/Kolkata");


/* =========================================================
   ONLY ADMIN
   ========================================================= */

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["user_role"] != "admin"
) {
    header("Location: ../index.php");
    exit;
}

$adminName = $_SESSION["user_name"];

$message = "";
$messageType = "";


/* =========================================================
   DELETE USER
   ========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] == "POST" &&
    isset($_POST["delete_user"])
) {

    $deleteId =
        intval($_POST["user_id"] ?? 0);


    /* ADMIN CANNOT DELETE OWN ACCOUNT */

    if (
        $deleteId ==
        $_SESSION["user_id"]
    ) {

        $message =
            "You cannot delete your own account.";

        $messageType =
            "error";

    } elseif ($deleteId <= 0) {

        $message =
            "Invalid user.";

        $messageType =
            "error";

    } else {

        $stmt = mysqli_prepare(
            $conn,
            "DELETE FROM users
             WHERE id = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $deleteId
        );


        if (
            mysqli_stmt_execute($stmt)
        ) {

            $message =
                "User deleted successfully.";

            $messageType =
                "success";

        } else {

            $message =
                "Unable to delete user.";

            $messageType =
                "error";
        }
    }
}


/* =========================================================
   COUNTS
   ========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users"
);

$totalUsers =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'student'"
);

$totalStudents =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'warden'"
);

$totalWardens =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'admin'"
);

$totalAdmins =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


/* =========================================================
   GET ALL USERS
   ========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT
        id,
        name,
        email,
        role,
        created_at
     FROM users
     ORDER BY id DESC"
);


/* =========================================================
   HELPER
   ========================================================= */

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
Manage Users - UniStay
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
    --teal-soft:#E2F1F3;

    --mustard:#E8B23D;
    --mustard-soft:#FFF2D3;

    --blue:#4387A5;
    --blue-soft:#E5F0F5;

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

.page {

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

    transition:0.18s ease;
}


.menu a i {

    width:22px;

    text-align:center;

    color:#395875;

    font-size:15px;
}


.menu a:hover {

    background:#F0F7F9;

    color:
        var(--teal);
}


.menu a.active {

    background:#DDF0F3;

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

    letter-spacing:-0.6px;
}


.topbar p {

    margin-top:5px;

    color:
        var(--muted);

    font-size:14px;
}


/* =========================================================
   ADMIN INFO
   ========================================================= */

.admin-info {

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


.admin-avatar {

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

    min-height:210px;

    display:grid;

    grid-template-columns:
        minmax(0,1fr)
        330px;

    align-items:center;

    gap:30px;

    margin-bottom:20px;

    padding:
        31px 34px;

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

    right:-105px;
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

    left:45%;
    bottom:-140px;

    border-radius:50%;

    background:
        rgba(232,178,61,0.13);
}


.hero-content {

    position:relative;

    z-index:2;
}


.hero-label {

    color:#F6D77C;

    font-size:12px;

    font-weight:700;

    text-transform:uppercase;

    letter-spacing:1px;
}


.hero h2 {

    margin-top:7px;

    font-size:33px;

    line-height:1.2;

    letter-spacing:-0.7px;
}


.hero p {

    max-width:590px;

    margin-top:9px;

    color:
        rgba(255,255,255,0.85);

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


/* =========================================================
   HERO SIDE
   ========================================================= */

.hero-side {

    position:relative;

    z-index:2;

    padding:18px;

    border-radius:15px;

    background:
        rgba(255,255,255,0.11);

    border:
        1px solid
        rgba(255,255,255,0.15);
}


.hero-side-top {

    display:flex;

    align-items:center;

    gap:12px;

    padding-bottom:13px;

    border-bottom:
        1px solid
        rgba(255,255,255,0.14);
}


.hero-side-icon {

    width:48px;
    height:48px;

    flex-shrink:0;

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


.hero-side strong {

    display:block;

    font-size:15px;
}


.hero-side p {

    margin-top:3px;

    color:
        rgba(255,255,255,0.73);

    font-size:12px;
}


.hero-counts {

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:8px;

    padding-top:13px;
}


.hero-count {

    text-align:center;
}


.hero-count span {

    display:block;

    color:
        rgba(255,255,255,0.68);

    font-size:10px;

    text-transform:uppercase;
}


.hero-count b {

    display:block;

    margin-top:4px;

    color:white;

    font-size:20px;
}


/* =========================================================
   SUMMARY CARDS
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


.card:nth-child(1) {

    border-top:
        4px solid
        var(--teal);
}


.card:nth-child(2) {

    border-top:
        4px solid
        var(--blue);
}


.card:nth-child(3) {

    border-top:
        4px solid
        var(--mustard);
}


.card:nth-child(4) {

    border-top:
        4px solid
        var(--green);
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


.card:nth-child(1)
.card-icon {

    color:
        var(--teal);

    background:
        var(--teal-soft);
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

    color:#956506;

    background:
        var(--mustard-soft);
}


.card:nth-child(4)
.card-icon {

    color:
        var(--green);

    background:
        var(--green-soft);
}


.card p {

    color:
        var(--muted);

    font-size:12px;

    text-transform:uppercase;

    letter-spacing:0.35px;
}


.card h2 {

    margin-top:4px;

    color:
        var(--text);

    font-size:29px;
}


/* =========================================================
   MESSAGE
   ========================================================= */

.message {

    display:flex;

    align-items:center;

    gap:9px;

    margin-bottom:20px;

    padding:
        14px 16px;

    border-radius:11px;

    font-size:13px;

    font-weight:600;
}


.success {

    color:#17684D;

    background:
        var(--green-soft);

    border:
        1px solid
        #C7E7D7;
}


.error {

    color:#A64048;

    background:
        var(--red-soft);

    border:
        1px solid
        #F3D2D5;
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

    gap:20px;

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


/* =========================================================
   PANEL ACTIONS
   ========================================================= */

.panel-actions {

    display:flex;

    align-items:center;

    gap:10px;
}


.search-box {

    position:relative;

    width:260px;
}


.search-box i {

    position:absolute;

    left:13px;
    top:50%;

    transform:
        translateY(-50%);

    color:
        var(--teal);

    font-size:13px;
}


.search-box input {

    width:100%;

    padding:
        10px 12px 10px 36px;

    outline:none;

    border:
        1px solid
        #D7E1E6;

    border-radius:9px;

    background:
        #F8FAFB;

    color:
        var(--text);

    font-size:13px;
}


.search-box input:focus {

    background:white;

    border-color:
        #59A0AE;

    box-shadow:
        0 0 0 3px
        rgba(14,96,119,0.07);
}


.add-btn {

    display:inline-flex;

    align-items:center;

    gap:7px;

    white-space:nowrap;

    padding:
        10px 14px;

    border-radius:9px;

    color:white;

    background:
        linear-gradient(
            135deg,
            var(--teal),
            var(--teal-2)
        );

    text-decoration:none;

    font-size:12px;

    font-weight:700;

    box-shadow:
        0 5px 12px
        rgba(14,96,119,0.15);
}


.add-btn:hover {

    background:
        linear-gradient(
            135deg,
            #0A5367,
            #107486
        );
}


/* =========================================================
   TABLE
   ========================================================= */

.table-wrap {

    overflow-x:auto;
}


table {

    width:100%;

    border-collapse:collapse;
}


thead {

    background:
        #F5F8FA;
}


th {

    padding:
        14px 15px;

    text-align:left;

    color:
        #526475;

    font-size:12px;

    font-weight:750;

    text-transform:uppercase;

    letter-spacing:0.3px;

    border-bottom:
        1px solid
        #DFE8EB;
}


td {

    padding:
        16px 15px;

    border-bottom:
        1px solid
        #E9EEF1;

    color:
        #344456;

    font-size:13px;

    vertical-align:middle;
}


tbody tr {

    transition:
        0.16s ease;
}


tbody tr:hover {

    background:
        #F8FBFC;
}


tbody tr:last-child td {

    border-bottom:none;
}


/* =========================================================
   USER
   ========================================================= */

.user-cell {

    display:flex;

    align-items:center;

    gap:11px;

    min-width:190px;
}


.user-avatar {

    width:43px;
    height:43px;

    flex-shrink:0;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:12px;

    color:white;

    background:
        linear-gradient(
            135deg,
            var(--navy),
            var(--teal)
        );

    font-size:14px;

    font-weight:800;

    box-shadow:
        0 5px 13px
        rgba(14,96,119,0.15);
}


.user-name {

    color:
        var(--text);

    font-size:14px;

    font-weight:750;
}


.user-id {

    margin-top:3px;

    color:
        #939EA8;

    font-size:10px;
}


.email-cell {

    color:
        var(--muted);

    font-size:13px;
}


/* =========================================================
   ROLE BADGES
   ========================================================= */

.role {

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:
        7px 10px;

    border-radius:20px;

    font-size:11px;

    font-weight:700;

    text-transform:capitalize;
}


.student {

    color:
        var(--teal);

    background:
        var(--teal-soft);
}


.warden {

    color:#976606;

    background:
        var(--mustard-soft);
}


.admin {

    color:
        var(--blue);

    background:
        var(--blue-soft);
}


/* =========================================================
   CREATED DATE
   ========================================================= */

.created-date {

    min-width:130px;
}


.created-date strong {

    display:block;

    color:
        var(--text);

    font-size:12px;
}


.created-date span {

    display:block;

    margin-top:3px;

    color:
        var(--muted);

    font-size:11px;
}


/* =========================================================
   DELETE
   ========================================================= */

.delete-btn {

    width:36px;
    height:36px;

    display:flex;

    align-items:center;
    justify-content:center;

    border:none;

    border-radius:9px;

    color:#AA424A;

    background:
        var(--red-soft);

    cursor:pointer;

    font-size:12px;

    transition:0.18s ease;
}


.delete-btn:hover {

    background:#F5D4D7;

    transform:
        translateY(-1px);
}


.you-badge {

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:
        7px 10px;

    border-radius:20px;

    color:#267356;

    background:
        var(--green-soft);

    font-size:11px;

    font-weight:700;
}


/* =========================================================
   EMPTY
   ========================================================= */

.empty {

    padding:
        55px 20px;

    text-align:center;

    color:
        var(--muted);
}


.empty-icon {

    width:70px;
    height:70px;

    margin:
        0 auto 14px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:20px;

    color:
        var(--teal);

    background:
        linear-gradient(
            135deg,
            var(--teal-soft),
            var(--mustard-soft)
        );

    font-size:27px;
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

@media(max-width:1150px) {

    .cards {

        grid-template-columns:
            repeat(2,1fr);
    }


    .hero {

        grid-template-columns:1fr;
    }


    .hero-side {

        max-width:430px;
    }


    .panel-head {

        align-items:flex-start;

        flex-direction:column;
    }


    .panel-actions {

        width:100%;
    }


    .search-box {

        flex:1;
        width:auto;
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
}


@media(max-width:700px) {

    .sidebar {

        display:none;
    }


    .topbar {

        align-items:flex-start;
    }


    .admin-info {

        padding:7px;
    }


    .admin-info > div:last-child {

        display:none;
    }
}


@media(max-width:560px) {

    .main {

        padding:14px;
    }


    .cards {

        grid-template-columns:1fr;
    }


    .hero {

        padding:23px;
    }


    .hero h2 {

        font-size:27px;
    }


    .panel-actions {

        align-items:stretch;

        flex-direction:column;
    }


    .search-box,
    .add-btn {

        width:100%;
    }


    .add-btn {

        justify-content:center;
    }
}

</style>

</head>


<body>


<div class="page">


<!-- =========================================================
     COMMON ADMIN SIDEBAR
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

<i class="fa-solid fa-users-gear"></i>

</div>


<div>

<h1>
Manage Users
</h1>

<p>
View and manage Student, Warden and Admin accounts.
</p>

</div>


</div>



<div class="admin-info">


<div class="admin-avatar">

<?php
echo getInitial(
    $adminName
);
?>

</div>


<div>

<strong>

<?php
echo htmlspecialchars(
    $adminName
);
?>

</strong>

<span>
System Admin
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
User Management
</div>


<h2>
Every user.
One organized directory.
</h2>


<p>

View all registered UniStay accounts, identify
their roles and manage access from one central
administration screen.

</p>


<div class="hero-chip">

<i class="fa-solid fa-shield-halved"></i>

Role-Based User Control

</div>


</div>



<div class="hero-side">


<div class="hero-side-top">


<div class="hero-side-icon">

<i class="fa-solid fa-users"></i>

</div>


<div>

<strong>
Account Overview
</strong>

<p>
Current registered UniStay users
</p>

</div>


</div>



<div class="hero-counts">


<div class="hero-count">

<span>
Users
</span>

<b>

<?php echo $totalUsers; ?>

</b>

</div>



<div class="hero-count">

<span>
Students
</span>

<b>

<?php echo $totalStudents; ?>

</b>

</div>



<div class="hero-count">

<span>
Wardens
</span>

<b>

<?php echo $totalWardens; ?>

</b>

</div>


</div>


</div>


</div>



<!-- =========================================================
     SUMMARY CARDS
     ========================================================= -->

<div class="cards">


<div class="card">


<div class="card-icon">

<i class="fa-solid fa-users"></i>

</div>


<div>

<p>
Total Users
</p>

<h2>

<?php echo $totalUsers; ?>

</h2>

</div>


</div>



<div class="card">


<div class="card-icon">

<i class="fa-solid fa-user-graduate"></i>

</div>


<div>

<p>
Students
</p>

<h2>

<?php echo $totalStudents; ?>

</h2>

</div>


</div>



<div class="card">


<div class="card-icon">

<i class="fa-solid fa-user-shield"></i>

</div>


<div>

<p>
Wardens
</p>

<h2>

<?php echo $totalWardens; ?>

</h2>

</div>


</div>



<div class="card">


<div class="card-icon">

<i class="fa-solid fa-user-gear"></i>

</div>


<div>

<p>
Admins
</p>

<h2>

<?php echo $totalAdmins; ?>

</h2>

</div>


</div>


</div>



<!-- =========================================================
     MESSAGE
     ========================================================= -->

<?php if ($message != "") { ?>


<div
    id="message"
    class="message <?php echo $messageType; ?>"
>


<?php if ($messageType == "success") { ?>

<i class="fa-solid fa-circle-check"></i>

<?php } else { ?>

<i class="fa-solid fa-circle-exclamation"></i>

<?php } ?>


<?php
echo htmlspecialchars(
    $message
);
?>


</div>


<?php } ?>



<!-- =========================================================
     USERS PANEL
     ========================================================= -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">


<div class="panel-icon">

<i class="fa-solid fa-address-book"></i>

</div>


<div>

<h2>
User Directory
</h2>

<p>
All registered UniStay accounts and roles.
</p>

</div>


</div>



<div class="panel-actions">


<div class="search-box">


<i class="fa-solid fa-magnifying-glass"></i>


<input
    type="text"
    id="userSearch"
    placeholder="Search name, email or role..."
>


</div>



<a
    href="add_user.php"
    class="add-btn"
>

<i class="fa-solid fa-plus"></i>

Add User

</a>


</div>


</div>



<?php

if (
    $result &&
    mysqli_num_rows($result) > 0
) {

?>


<div class="table-wrap">


<table id="usersTable">


<thead>


<tr>

<th>
User
</th>

<th>
Email
</th>

<th>
Role
</th>

<th>
Created
</th>

<th>
Action
</th>

</tr>


</thead>



<tbody>


<?php

while (
    $user =
    mysqli_fetch_assoc($result)
) {

?>


<tr>


<!-- =====================================================
     USER
     ===================================================== -->

<td>


<div class="user-cell">


<div class="user-avatar">

<?php
echo getInitial(
    $user["name"]
);
?>

</div>


<div>


<div class="user-name">

<?php
echo htmlspecialchars(
    $user["name"]
);
?>

</div>


<div class="user-id">

User ID:
<?php
echo intval(
    $user["id"]
);
?>

</div>


</div>


</div>


</td>



<!-- =====================================================
     EMAIL
     ===================================================== -->

<td>


<div class="email-cell">

<?php
echo htmlspecialchars(
    $user["email"]
);
?>

</div>


</td>



<!-- =====================================================
     ROLE
     ===================================================== -->

<td>


<span
    class="role <?php echo htmlspecialchars($user["role"]); ?>"
>


<?php

if (
    $user["role"] == "student"
) {

    echo '<i class="fa-solid fa-user-graduate"></i>';

} elseif (
    $user["role"] == "warden"
) {

    echo '<i class="fa-solid fa-user-shield"></i>';

} else {

    echo '<i class="fa-solid fa-user-gear"></i>';
}

?>


<?php
echo htmlspecialchars(
    ucfirst($user["role"])
);
?>


</span>


</td>



<!-- =====================================================
     CREATED
     ===================================================== -->

<td>


<div class="created-date">


<?php

if (
    !empty(
        $user["created_at"]
    )
) {

?>


<strong>

<?php

echo date(
    "d M Y",
    strtotime(
        $user["created_at"]
    )
);

?>

</strong>


<span>

<?php

echo date(
    "h:i A",
    strtotime(
        $user["created_at"]
    )
);

?>

</span>


<?php

} else {

?>


<strong>
—
</strong>


<?php } ?>


</div>


</td>



<!-- =====================================================
     ACTION
     ===================================================== -->

<td>


<?php

if (
    intval($user["id"]) !=
    intval($_SESSION["user_id"])
) {

?>


<form
    method="POST"
    onsubmit="
        return confirm(
            'Are you sure you want to delete this user?'
        );
    "
>


<input
    type="hidden"
    name="user_id"
    value="<?php echo intval($user["id"]); ?>"
>


<button
    type="submit"
    name="delete_user"
    class="delete-btn"
    title="Delete User"
>

<i class="fa-solid fa-trash"></i>

</button>


</form>


<?php

} else {

?>


<span class="you-badge">

<i class="fa-solid fa-user-check"></i>

You

</span>


<?php } ?>


</td>


</tr>


<?php } ?>


</tbody>


</table>


</div>



<!-- =========================================================
     SEARCH EMPTY
     ========================================================= -->

<div
    id="searchEmpty"
    class="empty"
    style="display:none;"
>


<div class="empty-icon">

<i class="fa-solid fa-magnifying-glass"></i>

</div>


<h3>
No matching user found
</h3>


<p>
Try another name, email address or role.
</p>


</div>


<?php

} else {

?>


<div class="empty">


<div class="empty-icon">

<i class="fa-solid fa-users"></i>

</div>


<h3>
No users available
</h3>


<p>
New UniStay accounts will appear here.
</p>


</div>


<?php } ?>


</div>


</div>


</div>



<script>

/* =========================================================
   USER SEARCH
   ========================================================= */

const searchInput =
    document.getElementById(
        "userSearch"
    );


if (searchInput) {

    searchInput.addEventListener(
        "keyup",
        function () {

            const filter =
                searchInput.value
                .toLowerCase()
                .trim();

            const rows =
                document.querySelectorAll(
                    "#usersTable tbody tr"
                );

            let visibleRows = 0;


            rows.forEach(
                function (row) {

                    const text =
                        row.innerText
                        .toLowerCase();


                    if (
                        text.includes(filter)
                    ) {

                        row.style.display =
                            "";

                        visibleRows++;

                    } else {

                        row.style.display =
                            "none";
                    }

                }
            );


            const table =
                document.getElementById(
                    "usersTable"
                );

            const empty =
                document.getElementById(
                    "searchEmpty"
                );


            if (
                visibleRows === 0 &&
                filter !== ""
            ) {

                table.style.display =
                    "none";

                empty.style.display =
                    "block";

            } else {

                table.style.display =
                    "table";

                empty.style.display =
                    "none";
            }

        }
    );
}


/* =========================================================
   MESSAGE FADE
   ========================================================= */

const message =
    document.getElementById(
        "message"
    );


if (message) {

    setTimeout(function () {

        message.style.opacity =
            "0";

        message.style.transition =
            "opacity 0.4s ease";


        setTimeout(function () {

            message.style.display =
                "none";

        }, 400);

    }, 3000);
}

</script>


</body>

</html>
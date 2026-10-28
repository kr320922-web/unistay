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
        mysqli_fetch_assoc($result)["total"]
    );


/* =========================================================
   ASSIGNED STUDENTS
   ========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'student'
     AND room_id IS NOT NULL"
);

$assignedStudents =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


/* =========================================================
   UNASSIGNED STUDENTS
   ========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'student'
     AND room_id IS NULL"
);

$unassignedStudents =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


/* =========================================================
   ASSIGNMENT PERCENTAGE
   ========================================================= */

$assignmentPercentage =
    ($totalStudents > 0)
    ? round(
        ($assignedStudents / $totalStudents) * 100
    )
    : 0;


/* =========================================================
   GET STUDENTS
   ========================================================= */

$students = mysqli_query(
    $conn,
    "SELECT
        users.id,
        users.name,
        users.email,
        users.room_id,

        rooms.room_number,
        rooms.block,
        rooms.floor,
        rooms.capacity,

        (
            SELECT COUNT(*)
            FROM users AS room_users
            WHERE room_users.room_id = users.room_id
            AND room_users.role = 'student'
        ) AS occupancy

     FROM users

     LEFT JOIN rooms
     ON users.room_id = rooms.id

     WHERE users.role = 'student'

     ORDER BY users.name ASC"
);


/* =========================================================
   HELPER
   ========================================================= */

function getInitial($name)
{
    $name = trim($name);

    if ($name == "") {
        return "S";
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
Students - UniStay
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
   WARDEN INFO
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

    min-height:220px;

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

    width:310px;
    height:310px;

    right:-100px;
    top:-190px;

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

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:10px;
}


.hero-stat {

    min-height:130px;

    padding:17px;

    border-radius:15px;

    background:
        rgba(255,255,255,0.12);

    border:
        1px solid
        rgba(255,255,255,0.15);
}


.hero-stat-icon {

    width:42px;
    height:42px;

    display:flex;

    align-items:center;
    justify-content:center;

    margin-bottom:12px;

    border-radius:11px;

    background:
        var(--mustard);

    color:
        var(--navy);

    font-size:16px;
}


.hero-stat:nth-child(2)
.hero-stat-icon {

    color:
        var(--teal);

    background:
        #DAEEF2;
}


.hero-stat span {

    display:block;

    color:
        rgba(255,255,255,0.72);

    font-size:11px;

    text-transform:uppercase;

    letter-spacing:0.3px;
}


.hero-stat strong {

    display:block;

    margin-top:4px;

    color:white;

    font-size:26px;
}


/* =========================================================
   SUMMARY CARDS
   ========================================================= */

.cards {

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:15px;

    margin-bottom:20px;
}


.card {

    min-height:125px;

    display:flex;

    align-items:center;

    gap:15px;

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
        var(--green);
}


.card:nth-child(3) {

    border-top:
        4px solid
        var(--mustard);
}


.card-icon {

    width:51px;
    height:51px;

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
        var(--green);

    background:
        var(--green-soft);
}


.card:nth-child(3)
.card-icon {

    color:#986507;

    background:
        var(--mustard-soft);
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

    font-size:29px;
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


/* =========================================================
   SEARCH
   ========================================================= */

.search-box {

    position:relative;

    width:270px;

    flex-shrink:0;
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
        11px 13px 11px 37px;

    outline:none;

    border:
        1px solid
        #D7E1E6;

    border-radius:10px;

    background:
        #F8FAFB;

    color:
        var(--text);

    font-size:13px;
}


.search-box input:focus {

    background:white;

    border-color:
        #66A3B0;

    box-shadow:
        0 0 0 3px
        rgba(14,96,119,0.07);
}


/* =========================================================
   TABLE
   ========================================================= */

.table-container {

    overflow-x:auto;
}


table {

    width:100%;

    border-collapse:collapse;
}


thead {

    background:
        #F4F8F9;
}


th {

    text-align:left;

    padding:
        14px 15px;

    color:
        #526475;

    font-size:12px;

    font-weight:750;

    text-transform:uppercase;

    letter-spacing:0.25px;

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
   STUDENT
   ========================================================= */

.student-info-cell {

    display:flex;

    align-items:center;

    gap:12px;

    min-width:210px;
}


.avatar {

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


.student-name {

    color:
        var(--text);

    font-size:14px;

    font-weight:750;

    margin-bottom:3px;
}


.student-email {

    color:
        var(--muted);

    font-size:12px;
}


.student-id {

    margin-top:3px;

    color:
        #98A1AB;

    font-size:10px;
}


/* =========================================================
   ROOM
   ========================================================= */

.room-number {

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:
        7px 10px;

    border-radius:8px;

    color:
        var(--teal);

    background:
        var(--teal-soft);

    font-size:12px;

    font-weight:750;
}


.block-text {

    font-weight:700;

    color:
        var(--text);
}


.floor-text {

    display:inline-flex;

    align-items:center;

    gap:5px;
}


/* =========================================================
   OCCUPANCY
   ========================================================= */

.occupancy-wrap {

    min-width:105px;
}


.occupancy-text {

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:7px;

    margin-bottom:6px;

    color:
        var(--text);

    font-size:12px;

    font-weight:700;
}


.occupancy-bar {

    height:6px;

    overflow:hidden;

    border-radius:10px;

    background:
        #E8EEF1;
}


.occupancy-fill {

    height:100%;

    border-radius:10px;

    background:
        linear-gradient(
            90deg,
            var(--teal),
            #55A6B4
        );
}


.occupancy-fill.full-fill {

    background:
        linear-gradient(
            90deg,
            #D9656C,
            #E98C3A
        );
}


/* =========================================================
   STATUS
   ========================================================= */

.status {

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:
        7px 10px;

    border-radius:20px;

    white-space:nowrap;

    font-size:11px;

    font-weight:700;
}


.assigned {

    color:#187451;

    background:
        var(--green-soft);
}


.unassigned {

    color:#976607;

    background:
        var(--mustard-soft);
}


.full {

    color:#AE444C;

    background:
        var(--red-soft);
}


.available {

    color:
        var(--teal);

    background:
        var(--teal-soft);
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

@media(max-width:1150px) {

    .hero {

        grid-template-columns:
            1fr 280px;
    }


    .panel-head {

        align-items:flex-start;

        flex-direction:column;
    }


    .search-box {

        width:100%;
    }
}


@media(max-width:900px) {

    .cards {

        grid-template-columns:1fr;
    }


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

        max-width:360px;
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


    .hero {

        padding:23px;
    }


    .hero h2 {

        font-size:27px;
    }


    .hero-side {

        width:100%;
    }


    .topbar h1 {

        font-size:25px;
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

<i class="fa-solid fa-users"></i>

</div>


<div>

<h1>
Students
</h1>

<p>
View hostel students and their room allocation details.
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
Student Directory
</div>


<h2>
Know your residents.
Manage smarter. 👩‍🎓
</h2>


<p>

View registered hostel students, their room allocation,
block, floor and occupancy information from one
organized directory.

</p>


<div class="hero-chip">

<i class="fa-solid fa-building-user"></i>

Hostel Resident Overview

</div>


</div>



<div class="hero-side">


<div class="hero-stat">


<div class="hero-stat-icon">

<i class="fa-solid fa-user-check"></i>

</div>


<span>
Allocation Rate
</span>


<strong>

<?php
echo $assignmentPercentage;
?>%

</strong>


</div>



<div class="hero-stat">


<div class="hero-stat-icon">

<i class="fa-solid fa-bed"></i>

</div>


<span>
Still Unassigned
</span>


<strong>

<?php
echo $unassignedStudents;
?>

</strong>


</div>


</div>


</div>



<!-- =========================================================
     SUMMARY CARDS
     ========================================================= -->

<div class="cards">


<!-- TOTAL -->

<div class="card">


<div class="card-icon">

<i class="fa-solid fa-users"></i>

</div>


<div>

<p>
Total Students
</p>


<h2>

<?php
echo $totalStudents;
?>

</h2>

</div>


</div>



<!-- ASSIGNED -->

<div class="card">


<div class="card-icon">

<i class="fa-solid fa-bed"></i>

</div>


<div>

<p>
Room Assigned
</p>


<h2>

<?php
echo $assignedStudents;
?>

</h2>

</div>


</div>



<!-- UNASSIGNED -->

<div class="card">


<div class="card-icon">

<i class="fa-solid fa-user-clock"></i>

</div>


<div>

<p>
Room Not Assigned
</p>


<h2>

<?php
echo $unassignedStudents;
?>

</h2>

</div>


</div>


</div>



<!-- =========================================================
     STUDENT DIRECTORY
     ========================================================= -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">


<div class="panel-icon">

<i class="fa-solid fa-address-book"></i>

</div>


<div>

<h2>
Student Directory
</h2>

<p>
Room and hostel information of registered students.
</p>

</div>


</div>



<div class="search-box">


<i class="fa-solid fa-magnifying-glass"></i>


<input
    type="text"
    id="studentSearch"
    placeholder="Search student, email or room..."
>


</div>


</div>



<?php

if (
    $students &&
    mysqli_num_rows($students) > 0
) {

?>


<div class="table-container">


<table id="studentsTable">


<thead>


<tr>

<th>
Student
</th>

<th>
Room
</th>

<th>
Block
</th>

<th>
Floor
</th>

<th>
Occupancy
</th>

<th>
Allocation
</th>

<th>
Room Status
</th>

</tr>


</thead>



<tbody>


<?php

while (
    $student =
    mysqli_fetch_assoc($students)
) {


    $hasRoom =
        !empty(
            $student["room_id"]
        );


    $occupancy =
        intval(
            $student["occupancy"]
        );


    $capacity =
        intval(
            $student["capacity"]
        );


    $isFull =
        $hasRoom &&
        $capacity > 0 &&
        $occupancy >= $capacity;


    $occupancyPercentage =
        (
            $hasRoom &&
            $capacity > 0
        )
        ?
        min(
            100,
            round(
                ($occupancy / $capacity) * 100
            )
        )
        :
        0;

?>


<tr>


<!-- =====================================================
     STUDENT
     ===================================================== -->

<td>


<div class="student-info-cell">


<div class="avatar">

<?php

echo getInitial(
    $student["name"]
);

?>

</div>


<div>


<div class="student-name">

<?php

echo htmlspecialchars(
    $student["name"]
);

?>

</div>


<div class="student-email">

<?php

echo htmlspecialchars(
    $student["email"]
);

?>

</div>


<div class="student-id">

Student ID:
<?php
echo intval(
    $student["id"]
);
?>

</div>


</div>


</div>


</td>



<!-- =====================================================
     ROOM
     ===================================================== -->

<td>


<?php if ($hasRoom) { ?>


<span class="room-number">

<i class="fa-solid fa-door-open"></i>

<?php

echo htmlspecialchars(
    $student["room_number"]
);

?>

</span>


<?php } else { ?>


<span style="color:#9AA4AE;">
—

</span>


<?php } ?>


</td>



<!-- =====================================================
     BLOCK
     ===================================================== -->

<td>


<?php if (!empty($student["block"])) { ?>


<span class="block-text">

<?php

echo htmlspecialchars(
    $student["block"]
);

?>

</span>


<?php } else { ?>

—

<?php } ?>


</td>



<!-- =====================================================
     FLOOR
     ===================================================== -->

<td>


<?php if (!empty($student["floor"])) { ?>


<span class="floor-text">

<i
    class="fa-solid fa-layer-group"
    style="color:#4387A5;"
></i>


<?php

echo htmlspecialchars(
    $student["floor"]
);

?>

</span>


<?php } else { ?>

—

<?php } ?>


</td>



<!-- =====================================================
     OCCUPANCY
     ===================================================== -->

<td>


<?php if ($hasRoom) { ?>


<div class="occupancy-wrap">


<div class="occupancy-text">

<span>

<?php
echo $occupancy;
?>
/
<?php
echo $capacity;
?>

</span>


<span>

<?php
echo $occupancyPercentage;
?>%

</span>


</div>


<div class="occupancy-bar">


<div
    class="occupancy-fill <?php echo $isFull ? 'full-fill' : ''; ?>"
    style="
        width:
        <?php echo $occupancyPercentage; ?>%;
    "
></div>


</div>


</div>


<?php } else { ?>

—

<?php } ?>


</td>



<!-- =====================================================
     ALLOCATION
     ===================================================== -->

<td>


<?php if ($hasRoom) { ?>


<span class="status assigned">

<i class="fa-solid fa-circle-check"></i>

Assigned

</span>


<?php } else { ?>


<span class="status unassigned">

<i class="fa-solid fa-clock"></i>

Not Assigned

</span>


<?php } ?>


</td>



<!-- =====================================================
     ROOM STATUS
     ===================================================== -->

<td>


<?php

if (!$hasRoom) {

?>


<span style="color:#9AA4AE;">
—
</span>


<?php

} elseif ($isFull) {

?>


<span class="status full">

<i class="fa-solid fa-users"></i>

Full

</span>


<?php

} else {

?>


<span class="status available">

<i class="fa-solid fa-door-open"></i>

Available

</span>


<?php

}

?>


</td>


</tr>


<?php } ?>


</tbody>


</table>


</div>


<!-- SEARCH EMPTY -->

<div
    id="searchEmpty"
    class="empty"
    style="display:none;"
>


<div class="empty-icon">

<i class="fa-solid fa-magnifying-glass"></i>

</div>


<h3>
No matching student found
</h3>


<p>
Try another student name, email or room number.
</p>


</div>


<?php

} else {

?>


<div class="empty">


<div class="empty-icon">

<i class="fa-solid fa-user-graduate"></i>

</div>


<h3>
No students available
</h3>


<p>
Registered hostel students will appear here.
</p>


</div>


<?php } ?>


</div>


</div>


</div>



<script>

/* =========================================================
   STUDENT SEARCH
   ========================================================= */

const searchInput =
    document.getElementById(
        "studentSearch"
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
                    "#studentsTable tbody tr"
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
                    "studentsTable"
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

</script>


</body>
</html>
<?php

session_start();
include "../db.php";

date_default_timezone_set("Asia/Kolkata");


/* =========================================================
   ONLY STUDENT CAN ACCESS
   ========================================================= */

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["user_role"] != "student"
) {
    header("Location: ../index.php");
    exit;
}

$userId   = $_SESSION["user_id"];
$userName = $_SESSION["user_name"];


/* =========================================================
   GET STUDENT ROOM
   ========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        users.room_id,
        rooms.room_number,
        rooms.block,
        rooms.floor,
        rooms.capacity,
        rooms.status
     FROM users
     LEFT JOIN rooms
        ON users.room_id = rooms.id
     WHERE users.id = ?"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $userId
);

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);

$room =
    mysqli_fetch_assoc($result);


/* =========================================================
   DEFAULT VALUES
   ========================================================= */

$roomAssigned = false;

$occupancy = 0;

$capacity = 0;

$occupancyPercentage = 0;

$roomStatus = "Not Assigned";

$roommates = null;


/* =========================================================
   ROOM DATA
   ========================================================= */

if (
    $room &&
    !empty($room["room_id"])
) {

    $roomAssigned = true;

    $roomId =
        intval($room["room_id"]);

    $capacity =
        intval($room["capacity"]);


    /* CURRENT OCCUPANCY */

    $occupancyStmt =
        mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS total
             FROM users
             WHERE room_id = ?
             AND role = 'student'"
        );

    mysqli_stmt_bind_param(
        $occupancyStmt,
        "i",
        $roomId
    );

    mysqli_stmt_execute(
        $occupancyStmt
    );

    $occupancyResult =
        mysqli_stmt_get_result(
            $occupancyStmt
        );

    $occupancyRow =
        mysqli_fetch_assoc(
            $occupancyResult
        );

    $occupancy =
        intval(
            $occupancyRow["total"]
        );


    /* OCCUPANCY PERCENTAGE */

    if ($capacity > 0) {

        $occupancyPercentage =
            round(
                ($occupancy / $capacity) * 100
            );

        if ($occupancyPercentage > 100) {
            $occupancyPercentage = 100;
        }
    }


    /* ROOM STATUS */

    if (
        $capacity > 0 &&
        $occupancy >= $capacity
    ) {

        $roomStatus = "Full";

    } else {

        $roomStatus = "Available";
    }


    /* GET ROOMMATES */

    $roommateStmt =
        mysqli_prepare(
            $conn,
            "SELECT
                id,
                name,
                email
             FROM users
             WHERE room_id = ?
             AND role = 'student'
             AND id != ?
             ORDER BY name ASC"
        );

    mysqli_stmt_bind_param(
        $roommateStmt,
        "ii",
        $roomId,
        $userId
    );

    mysqli_stmt_execute(
        $roommateStmt
    );

    $roommates =
        mysqli_stmt_get_result(
            $roommateStmt
        );
}


/* =========================================================
   HELPER
   ========================================================= */

function getInitial($name)
{
    return strtoupper(
        substr(
            $name,
            0,
            1
        )
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
My Room - UniStay
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

    --navy: #1F2747;
    --deep-navy: #14354F;

    --teal: #0E6077;
    --teal-light: #2B8094;

    --mustard: #E8B23D;

    --blue: #4387A5;

    --green: #36A878;

    --red: #D9656C;

    --background: #EFF4F7;

    --card: #FFFFFF;

    --soft: #F6F9FA;

    --border: #DFE7EC;

    --text: #1E2639;

    --muted: #718092;

    --shadow:
        0 8px 24px
        rgba(31,39,71,0.055);
}


/* =========================================================
   GLOBAL
   ========================================================= */

* {

    margin: 0;

    padding: 0;

    box-sizing: border-box;

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

    display: flex;

    min-height: 100vh;
}


/* =========================================================
   SIDEBAR
   ========================================================= */

.sidebar {

    width: 230px;

    min-width: 230px;

    min-height: 100vh;

    position: sticky;

    top: 0;

    align-self: flex-start;

    padding:
        30px
        18px;

    background:
        white;

    border-right:
        1px solid
        var(--border);

    z-index: 50;
}


.logo {

    position: relative;

    display: flex;

    align-items: center;

    gap: 12px;

    margin-bottom: 50px;

    padding-left: 8px;
}


.logo::after {

    content:
        "Live  Learn  Belong";

    position: absolute;

    left: 61px;

    top: 30px;

    color:
        #7C8796;

    font-size:
        9px;

    letter-spacing:
        0.3px;
}


.logo-box {

    width: 44px;

    height: 44px;

    display: flex;

    align-items: center;

    justify-content: center;

    color:
        white;

    border-radius:
        11px;

    background:
        linear-gradient(
            135deg,
            #0C556C,
            #0E7485
        );

    font-size:
        21px;

    font-weight:
        800;

    box-shadow:
        0 8px 18px
        rgba(14,96,119,0.20);
}


.logo h2 {

    color:
        #162238;

    font-size:
        24px;

    font-weight:
        800;

    letter-spacing:
        -0.8px;
}


.menu a {

    display: flex;

    align-items: center;

    gap: 13px;

    padding:
        13px
        14px;

    margin-bottom:
        8px;

    border-radius:
        10px;

    color:
        #45576E;

    text-decoration:
        none;

    font-size:
        14px;

    font-weight:
        500;

    transition:
        0.18s ease;
}


.menu a i {

    width: 22px;

    text-align: center;

    color:
        #395875;

    font-size:
        15px;
}


.menu a:hover {

    color:
        var(--teal);

    background:
        #F0F7F9;
}


.menu a.active {

    color:
        var(--teal);

    background:
        #DFF0F4;

    font-weight:
        700;
}


.menu a.active i {

    color:
        var(--teal);
}


/* =========================================================
   MAIN
   ========================================================= */

.main {

    flex: 1;

    min-width: 0;

    padding:
        28px
        32px
        50px;
}


/* =========================================================
   TOP BAR
   ========================================================= */

.topbar {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom:
        24px;
}


.page-title {

    display: flex;

    align-items: center;

    gap: 14px;
}


.page-icon {

    width: 47px;

    height: 47px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 13px;

    color:
        white;

    background:
        linear-gradient(
            135deg,
            var(--navy),
            var(--teal)
        );

    font-size:
        19px;

    box-shadow:
        0 7px 17px
        rgba(14,96,119,0.18);
}


.topbar h1 {

    color:
        var(--text);

    font-size:
        28px;

    letter-spacing:
        -0.6px;
}


.topbar p {

    margin-top:
        4px;

    color:
        var(--muted);

    font-size:
        13px;
}


.student-info {

    display: flex;

    align-items: center;

    gap: 10px;

    padding:
        8px
        13px
        8px
        8px;

    border-radius:
        30px;

    background:
        white;

    border:
        1px solid
        var(--border);

    box-shadow:
        0 4px 15px
        rgba(31,39,71,0.035);
}


.student-avatar {

    width: 37px;

    height: 37px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius:
        50%;

    color:
        white;

    background:
        linear-gradient(
            135deg,
            var(--teal),
            #2C91A4
        );

    font-size:
        13px;

    font-weight:
        800;
}


.student-info strong {

    display: block;

    color:
        var(--text);

    font-size:
        12px;
}


.student-info span {

    display: block;

    margin-top:
        1px;

    color:
        var(--muted);

    font-size:
        9px;
}


/* =========================================================
   ROOM HERO
   ========================================================= */

.room-hero {

    position: relative;

    overflow: hidden;

    min-height: 245px;

    margin-bottom:
        20px;

    padding:
        31px
        34px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 30px;

    color:
        white;

    border-radius:
        20px;

    background:
        linear-gradient(
            125deg,
            #1F2747 0%,
            #12465D 38%,
            #0E6077 72%,
            #24899A 100%
        );

    box-shadow:
        0 13px 30px
        rgba(18,71,91,0.16);
}


.room-hero::before {

    content: "";

    position: absolute;

    width:
        300px;

    height:
        300px;

    right:
        -80px;

    top:
        -170px;

    border-radius:
        50%;

    background:
        rgba(255,255,255,0.07);
}


.room-hero::after {

    content: "";

    position: absolute;

    width:
        180px;

    height:
        180px;

    right:
        240px;

    bottom:
        -135px;

    border-radius:
        50%;

    border:
        30px solid
        rgba(255,255,255,0.04);
}


.hero-left {

    position: relative;

    z-index: 3;

    display: flex;

    align-items: center;

    gap: 22px;
}


.hero-room-icon {

    width: 82px;

    height: 82px;

    flex-shrink: 0;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius:
        22px;

    color:
        var(--navy);

    background:
        var(--mustard);

    font-size:
        31px;

    box-shadow:
        0 10px 22px
        rgba(0,0,0,0.12);
}


.hero-label {

    font-size:
        13px;

    opacity:
        0.78;

    text-transform:
        uppercase;

    letter-spacing:
        1px;
}


.room-number {

    margin-top:
        6px;

    font-size:
        46px;

    line-height:
        1;

    font-weight:
        800;

    letter-spacing:
        -1.2px;
}


.room-location {

    margin-top:
        10px;

    font-size:
        15px;

    color:
        rgba(255,255,255,0.84);
}


.hero-badges {

    display: flex;

    align-items: center;

    gap: 9px;

    margin-top:
        18px;

    flex-wrap:
        wrap;
}


.hero-badge {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding:
        7px
        11px;

    border-radius:
        20px;

    background:
        rgba(255,255,255,0.12);

    border:
        1px solid
        rgba(255,255,255,0.12);

    font-size:
        11px;
}


/* HERO RIGHT */

.hero-right {

    width:
        310px;

    position: relative;

    z-index: 3;

    padding:
        19px;

    border-radius:
        16px;

    background:
        rgba(255,255,255,0.11);

    border:
        1px solid
        rgba(255,255,255,0.12);

    backdrop-filter:
        blur(5px);
}


.hero-right-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom:
        18px;
}


.hero-right-title {

    font-size:
        14px;

    font-weight:
        700;
}


.room-status {

    padding:
        6px
        10px;

    border-radius:
        20px;

    font-size:
        10px;

    font-weight:
        800;
}


.room-status.full {

    color:
        #784E00;

    background:
        #FBE5A5;
}


.room-status.available {

    color:
        #16654B;

    background:
        #D9F4E8;
}


.occupancy-row {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom:
        8px;

    font-size:
        12px;
}


.occupancy-row span {

    color:
        rgba(255,255,255,0.75);
}


.occupancy-row strong {

    font-size:
        14px;
}


.occupancy-track {

    width:
        100%;

    height:
        9px;

    overflow:
        hidden;

    border-radius:
        20px;

    background:
        rgba(255,255,255,0.16);
}


.occupancy-fill {

    height:
        100%;

    border-radius:
        inherit;

    background:
        linear-gradient(
            90deg,
            var(--mustard),
            #F6CE74
        );
}


.hero-note {

    margin-top:
        15px;

    color:
        rgba(255,255,255,0.75);

    font-size:
        11px;

    line-height:
        1.55;
}


/* =========================================================
   DETAIL CARDS
   ========================================================= */

.cards {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap:
        15px;

    margin-bottom:
        20px;
}


.card {

    position: relative;

    overflow: hidden;

    min-height:
        145px;

    padding:
        19px;

    border-radius:
        14px;

    background:
        white;

    border:
        1px solid
        var(--border);

    box-shadow:
        var(--shadow);

    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}


.card:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 10px 26px
        rgba(31,39,71,0.075);
}


.card::after {

    content: "";

    position: absolute;

    right:
        -25px;

    bottom:
        -35px;

    width:
        90px;

    height:
        90px;

    border-radius:
        50%;

    background:
        rgba(14,96,119,0.035);
}


.card-icon {

    width: 43px;

    height: 43px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-bottom:
        17px;

    border-radius:
        11px;

    color:
        var(--teal);

    background:
        #E3F1F4;

    font-size:
        17px;
}


.card:nth-child(2)
.card-icon {

    color:
        #A46D0B;

    background:
        #FFF1CD;
}


.card:nth-child(3)
.card-icon {

    color:
        #397DA0;

    background:
        #E5F1F7;
}


.card:nth-child(4)
.card-icon {

    color:
        var(--navy);

    background:
        #E7EAF1;
}


.card p {

    margin-bottom:
        4px;

    color:
        var(--muted);

    font-size:
        11px;

    text-transform:
        uppercase;

    letter-spacing:
        0.5px;
}


.card h3 {

    color:
        var(--text);

    font-size:
        20px;

    font-weight:
        750;
}


.card h3.available {

    color:
        var(--green);
}


.card h3.full {

    color:
        #B77A13;
}


/* =========================================================
   LOWER GRID
   ========================================================= */

.lower-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1.45fr)
        minmax(300px, 0.55fr);

    gap:
        20px;
}


/* =========================================================
   ROOMMATES PANEL
   ========================================================= */

.panel {

    background:
        white;

    border:
        1px solid
        var(--border);

    border-radius:
        15px;

    box-shadow:
        var(--shadow);
}


.panel-head {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap:
        15px;

    padding:
        20px
        21px;

    border-bottom:
        1px solid
        #EEF1F3;
}


.panel-title {

    display: flex;

    align-items: center;

    gap:
        10px;
}


.panel-title-icon {

    width:
        36px;

    height:
        36px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius:
        10px;

    color:
        var(--teal);

    background:
        #E4F1F4;
}


.panel-head h2 {

    font-size:
        18px;
}


.panel-head p {

    margin-top:
        2px;

    color:
        var(--muted);

    font-size:
        11px;
}


.occupied-pill {

    display: inline-flex;

    align-items: center;

    gap:
        6px;

    padding:
        7px
        10px;

    border-radius:
        20px;

    color:
        var(--teal);

    background:
        #E5F2F4;

    font-size:
        10px;

    font-weight:
        700;
}


.roommate-list {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(220px,1fr)
        );

    gap:
        12px;

    padding:
        18px
        20px
        21px;
}


.roommate {

    display: flex;

    align-items: center;

    gap:
        13px;

    min-height:
        78px;

    padding:
        13px;

    border-radius:
        12px;

    background:
        #F8FAFB;

    border:
        1px solid
        #E9EEF1;
}


.avatar {

    width:
        49px;

    height:
        49px;

    flex-shrink:
        0;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius:
        50%;

    color:
        white;

    background:
        linear-gradient(
            135deg,
            #0F667C,
            #3692A3
        );

    font-size:
        17px;

    font-weight:
        800;
}


.roommate:nth-child(2)
.avatar {

    background:
        linear-gradient(
            135deg,
            #E3A530,
            #F2C461
        );

    color:
        var(--navy);
}


.roommate:nth-child(3)
.avatar {

    background:
        linear-gradient(
            135deg,
            #3C7895,
            #61A3BE
        );
}


.roommate-name {

    color:
        var(--text);

    font-size:
        13px;

    font-weight:
        700;
}


.roommate-you {

    display:
        inline-flex;

    margin-left:
        5px;

    padding:
        3px
        6px;

    border-radius:
        20px;

    background:
        #DFF1F3;

    color:
        var(--teal);

    font-size:
        7px;

    text-transform:
        uppercase;

    vertical-align:
        middle;
}


.roommate-email {

    margin-top:
        3px;

    color:
        var(--muted);

    font-size:
        10px;
}


.roommate-bed {

    margin-top:
        5px;

    color:
        #456273;

    font-size:
        9px;

    font-weight:
        600;
}


/* =========================================================
   ROOM SUPPORT
   ========================================================= */

.support-panel {

    padding-bottom:
        18px;
}


.support-list {

    display: flex;

    flex-direction: column;

    gap:
        9px;

    padding:
        17px
        18px
        0;
}


.support-link {

    display: grid;

    grid-template-columns:
        40px
        1fr
        17px;

    gap:
        11px;

    align-items: center;

    min-height:
        62px;

    padding:
        10px;

    border-radius:
        11px;

    text-decoration:
        none;

    color:
        var(--text);

    background:
        #F7F9FA;

    border:
        1px solid
        #E9EEF1;

    transition:
        0.18s ease;
}


.support-link:hover {

    border-color:
        #CFE0E5;

    transform:
        translateX(2px);
}


.support-icon {

    width:
        40px;

    height:
        40px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius:
        10px;

    color:
        white;

    background:
        var(--teal);
}


.support-link:nth-child(2)
.support-icon {

    color:
        var(--navy);

    background:
        var(--mustard);
}


.support-link:nth-child(3)
.support-icon {

    background:
        var(--blue);
}


.support-name {

    font-size:
        11px;

    font-weight:
        700;
}


.support-text {

    margin-top:
        2px;

    color:
        var(--muted);

    font-size:
        9px;
}


/* =========================================================
   NO ROOM
   ========================================================= */

.empty-room {

    min-height:
        380px;

    display: flex;

    align-items: center;

    justify-content: center;

    text-align:
        center;

    padding:
        45px;

    border-radius:
        18px;

    background:
        white;

    border:
        1px solid
        var(--border);

    box-shadow:
        var(--shadow);
}


.empty-content {

    max-width:
        440px;
}


.empty-icon {

    width:
        84px;

    height:
        84px;

    margin:
        0 auto 20px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius:
        24px;

    color:
        var(--teal);

    background:
        #E1F0F3;

    font-size:
        34px;
}


.empty-room h2 {

    margin-bottom:
        9px;

    color:
        var(--text);

    font-size:
        24px;
}


.empty-room p {

    color:
        var(--muted);

    font-size:
        13px;

    line-height:
        1.7;
}


.no-roommate {

    grid-column:
        1 / -1;

    padding:
        30px;

    border-radius:
        12px;

    background:
        #F7F9FA;

    color:
        var(--muted);

    text-align:
        center;

    font-size:
        12px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media(max-width: 1150px) {

    .cards {

        grid-template-columns:
            repeat(2,1fr);
    }


    .lower-grid {

        grid-template-columns:
            1fr;
    }
}


@media(max-width: 900px) {

    .sidebar {

        width:
            205px;

        min-width:
            205px;
    }


    .main {

        padding:
            22px;
    }


    .room-hero {

        flex-direction:
            column;

        align-items:
            stretch;
    }


    .hero-right {

        width:
            100%;
    }
}


@media(max-width: 700px) {

    .sidebar {

        display:
            none;
    }


    .topbar {

        align-items:
            flex-start;
    }


    .student-info {

        padding:
            7px;
    }


    .student-info > div:last-child {

        display:
            none;
    }


    .hero-left {

        align-items:
            flex-start;
    }


    .room-number {

        font-size:
            37px;
    }
}


@media(max-width: 520px) {

    .main {

        padding:
            14px;
    }


    .cards {

        grid-template-columns:
            1fr;
    }


    .room-hero {

        padding:
            23px;
    }


    .hero-room-icon {

        width:
            65px;

        height:
            65px;
    }


    .roommate-list {

        grid-template-columns:
            1fr;
    }
}

</style>

</head>


<body>


<div class="dashboard">


<!-- =========================================================
     COMMON SIDEBAR
     ========================================================= -->

<?php include "sidebar.php"; ?>



<!-- =========================================================
     MAIN
     ========================================================= -->

<div class="main">


<!-- =========================================================
     TOP BAR
     ========================================================= -->

<div class="topbar">


<div class="page-title">


<div class="page-icon">

<i class="fa-solid fa-bed"></i>

</div>


<div>

<h1>
My Room
</h1>

<p>
Your room, occupancy and roommate details.
</p>

</div>


</div>



<div class="student-info">


<div class="student-avatar">

<?php
echo getInitial(
    $userName
);
?>

</div>


<div>

<strong>

<?php
echo htmlspecialchars(
    $userName
);
?>

</strong>

<span>
Student
</span>

</div>


</div>


</div>



<?php if ($roomAssigned) { ?>


<!-- =========================================================
     ROOM HERO
     ========================================================= -->

<div class="room-hero">


<div class="hero-left">


<div class="hero-room-icon">

<i class="fa-solid fa-house"></i>

</div>


<div>


<div class="hero-label">
Your Assigned Room
</div>


<div class="room-number">

<?php
echo htmlspecialchars(
    $room["room_number"]
);
?>

</div>


<div class="room-location">

<i class="fa-solid fa-location-dot"></i>

&nbsp;

<?php
echo htmlspecialchars(
    $room["block"]
);
?>

&nbsp; • &nbsp;

<?php
echo htmlspecialchars(
    $room["floor"]
);
?>

</div>



<div class="hero-badges">


<div class="hero-badge">

<i class="fa-solid fa-user-group"></i>

<?php echo $occupancy; ?>

/

<?php echo $capacity; ?>

Occupied

</div>


<div class="hero-badge">

<i class="fa-solid fa-bed"></i>

<?php echo htmlspecialchars($roomStatus); ?>

</div>


</div>


</div>


</div>



<div class="hero-right">


<div class="hero-right-top">


<div class="hero-right-title">

Room Occupancy

</div>


<div class="room-status <?php echo strtolower($roomStatus); ?>">

<?php
echo htmlspecialchars(
    $roomStatus
);
?>

</div>


</div>


<div class="occupancy-row">

<span>
Current Occupancy
</span>

<strong>

<?php echo $occupancy; ?>

/

<?php echo $capacity; ?>

</strong>

</div>


<div class="occupancy-track">


<div
    class="occupancy-fill"
    style="
        width:
        <?php
        echo $occupancyPercentage;
        ?>%;
    "
>
</div>


</div>


<div class="hero-note">

Your room currently has
<strong>
<?php echo $occupancy; ?>
</strong>
student<?php echo ($occupancy == 1) ? "" : "s"; ?>
out of
<strong>
<?php echo $capacity; ?>
</strong>
available spaces.

</div>


</div>


</div>



<!-- =========================================================
     ROOM DETAILS
     ========================================================= -->

<div class="cards">


<div class="card">


<div class="card-icon">

<i class="fa-solid fa-building"></i>

</div>


<p>
Block
</p>


<h3>

<?php
echo htmlspecialchars(
    $room["block"]
);
?>

</h3>


</div>



<div class="card">


<div class="card-icon">

<i class="fa-solid fa-layer-group"></i>

</div>


<p>
Floor
</p>


<h3>

<?php
echo htmlspecialchars(
    $room["floor"]
);
?>

</h3>


</div>



<div class="card">


<div class="card-icon">

<i class="fa-solid fa-users"></i>

</div>


<p>
Occupancy
</p>


<h3>

<?php echo $occupancy; ?>

/

<?php echo $capacity; ?>

</h3>


</div>



<div class="card">


<div class="card-icon">

<i class="fa-solid fa-circle-check"></i>

</div>


<p>
Room Status
</p>


<h3 class="<?php echo strtolower($roomStatus); ?>">

<?php
echo htmlspecialchars(
    $roomStatus
);
?>

</h3>


</div>


</div>



<!-- =========================================================
     LOWER CONTENT
     ========================================================= -->

<div class="lower-grid">


<!-- =========================================================
     ROOMMATES
     ========================================================= -->

<div class="panel">


<div class="panel-head">


<div class="panel-title">


<div class="panel-title-icon">

<i class="fa-solid fa-user-group"></i>

</div>


<div>

<h2>
My Roommates
</h2>

<p>
Students currently sharing your room.
</p>

</div>


</div>



<div class="occupied-pill">

<i class="fa-solid fa-users"></i>

<?php echo $occupancy; ?>

/

<?php echo $capacity; ?>

Occupied

</div>


</div>



<div class="roommate-list">


<!-- CURRENT USER -->

<div class="roommate">


<div class="avatar">

<?php
echo getInitial(
    $userName
);
?>

</div>


<div>


<div class="roommate-name">

<?php
echo htmlspecialchars(
    $userName
);
?>

<span class="roommate-you">
You
</span>

</div>


<div class="roommate-email">
Current Student
</div>


<div class="roommate-bed">
Bed 1
</div>


</div>


</div>



<?php

$bedNumber = 2;

if (
    $roommates &&
    mysqli_num_rows($roommates) > 0
) {

    while (
        $roommate =
        mysqli_fetch_assoc($roommates)
    ) {

?>


<div class="roommate">


<div class="avatar">

<?php
echo getInitial(
    $roommate["name"]
);
?>

</div>


<div>


<div class="roommate-name">

<?php
echo htmlspecialchars(
    $roommate["name"]
);
?>

</div>


<div class="roommate-email">

<?php
echo htmlspecialchars(
    $roommate["email"]
);
?>

</div>


<div class="roommate-bed">

Bed
<?php echo $bedNumber; ?>

</div>


</div>


</div>


<?php

        $bedNumber++;

    }

}

?>


<?php if ($occupancy <= 1) { ?>


<div class="no-roommate">

<i class="fa-solid fa-user-plus"></i>

&nbsp;

No roommate assigned yet.

</div>


<?php } ?>


</div>


</div>



<!-- =========================================================
     ROOM SUPPORT
     ========================================================= -->

<div class="panel support-panel">


<div class="panel-head">


<div class="panel-title">


<div class="panel-title-icon">

<i class="fa-solid fa-headset"></i>

</div>


<div>

<h2>
Room Support
</h2>

<p>
Quick access to hostel services.
</p>

</div>


</div>


</div>



<div class="support-list">


<a
    href="complaints.php"
    class="support-link"
>


<div class="support-icon">

<i class="fa-solid fa-screwdriver-wrench"></i>

</div>


<div>

<div class="support-name">
Report Room Issue
</div>

<div class="support-text">
Wi-Fi, electricity, water or maintenance.
</div>

</div>


<i class="fa-solid fa-chevron-right"></i>


</a>



<a
    href="laundry.php"
    class="support-link"
>


<div class="support-icon">

<i class="fa-solid fa-shirt"></i>

</div>


<div>

<div class="support-name">
Laundry Service
</div>

<div class="support-text">
Book or check your laundry request.
</div>

</div>


<i class="fa-solid fa-chevron-right"></i>


</a>



<a
    href="notices.php"
    class="support-link"
>


<div class="support-icon">

<i class="fa-solid fa-bullhorn"></i>

</div>


<div>

<div class="support-name">
Hostel Notices
</div>

<div class="support-text">
Check latest hostel announcements.
</div>

</div>


<i class="fa-solid fa-chevron-right"></i>


</a>


</div>


</div>


</div>



<?php } else { ?>


<!-- =========================================================
     NO ROOM ASSIGNED
     ========================================================= -->

<div class="empty-room">


<div class="empty-content">


<div class="empty-icon">

<i class="fa-solid fa-bed"></i>

</div>


<h2>
No Room Assigned
</h2>


<p>

Your room has not been assigned yet.
Once the hostel administration assigns a room,
your room number, floor, occupancy and roommate
information will appear here.

</p>


</div>


</div>


<?php } ?>


</div>


</div>


</body>

</html>
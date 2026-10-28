<?php

session_start();
include "../db.php";

date_default_timezone_set("Asia/Kolkata");


/* =========================================================
   ONLY ADMIN CAN ACCESS
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
   FLASH MESSAGE
   ========================================================= */

if (isset($_SESSION["room_message"])) {

    $message =
        $_SESSION["room_message"];

    $messageType =
        $_SESSION["room_message_type"] ?? "";

    unset($_SESSION["room_message"]);
    unset($_SESSION["room_message_type"]);
}


/* =========================================================
   HANDLE FORMS
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $action =
        $_POST["action"] ?? "";


    /* =====================================================
       CREATE NEW ROOM
       ===================================================== */

    if ($action == "create_room") {

        $roomNumber =
            trim($_POST["room_number"] ?? "");

        $block =
            trim($_POST["block"] ?? "");

        $floor =
            trim($_POST["floor"] ?? "");

        $capacity =
            intval($_POST["capacity"] ?? 0);


        if (
            $roomNumber == "" ||
            $block == "" ||
            $floor == "" ||
            $capacity <= 0
        ) {

            $_SESSION["room_message"] =
                "Please fill all room details correctly.";

            $_SESSION["room_message_type"] =
                "error";

        } else {

            /* CHECK DUPLICATE ROOM */

            $check = mysqli_prepare(
                $conn,
                "SELECT id
                 FROM rooms
                 WHERE room_number = ?
                 AND block = ?"
            );

            mysqli_stmt_bind_param(
                $check,
                "ss",
                $roomNumber,
                $block
            );

            mysqli_stmt_execute($check);

            $checkResult =
                mysqli_stmt_get_result($check);


            if (
                mysqli_num_rows($checkResult) > 0
            ) {

                $_SESSION["room_message"] =
                    "This room already exists.";

                $_SESSION["room_message_type"] =
                    "error";

            } else {

                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO rooms
                    (
                        room_number,
                        block,
                        floor,
                        capacity,
                        status
                    )
                    VALUES (?, ?, ?, ?, 'Available')"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "sssi",
                    $roomNumber,
                    $block,
                    $floor,
                    $capacity
                );


                if (mysqli_stmt_execute($stmt)) {

                    $_SESSION["room_message"] =
                        "Room created successfully!";

                    $_SESSION["room_message_type"] =
                        "success";

                } else {

                    $_SESSION["room_message"] =
                        "Unable to create room.";

                    $_SESSION["room_message_type"] =
                        "error";
                }
            }
        }

        header("Location: rooms.php");
        exit;
    }


    /* =====================================================
       ASSIGN ROOM
       ===================================================== */

    if ($action == "assign_room") {

        $studentId =
            intval($_POST["student_id"] ?? 0);

        $roomId =
            intval($_POST["room_id"] ?? 0);


        /* CHECK STUDENT */

        $studentStmt = mysqli_prepare(
            $conn,
            "SELECT
                id,
                name,
                room_id
             FROM users
             WHERE id = ?
             AND role = 'student'"
        );

        mysqli_stmt_bind_param(
            $studentStmt,
            "i",
            $studentId
        );

        mysqli_stmt_execute($studentStmt);

        $studentResult =
            mysqli_stmt_get_result($studentStmt);

        $student =
            mysqli_fetch_assoc($studentResult);


        /* CHECK ROOM */

        $roomStmt = mysqli_prepare(
            $conn,
            "SELECT
                id,
                room_number,
                capacity
             FROM rooms
             WHERE id = ?"
        );

        mysqli_stmt_bind_param(
            $roomStmt,
            "i",
            $roomId
        );

        mysqli_stmt_execute($roomStmt);

        $roomResult =
            mysqli_stmt_get_result($roomStmt);

        $room =
            mysqli_fetch_assoc($roomResult);


        if (!$student || !$room) {

            $_SESSION["room_message"] =
                "Invalid student or room selected.";

            $_SESSION["room_message_type"] =
                "error";

            header("Location: rooms.php");
            exit;
        }


        /*
        COUNT OCCUPANCY

        Selected student is excluded so
        room shifting does not incorrectly
        increase occupancy.
        */

        $countStmt = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS total
             FROM users
             WHERE room_id = ?
             AND role = 'student'
             AND id != ?"
        );

        mysqli_stmt_bind_param(
            $countStmt,
            "ii",
            $roomId,
            $studentId
        );

        mysqli_stmt_execute($countStmt);

        $countResult =
            mysqli_stmt_get_result($countStmt);

        $countRow =
            mysqli_fetch_assoc($countResult);

        $occupancy =
            intval($countRow["total"]);


        /* CAPACITY CHECK */

        if (
            $occupancy >=
            intval($room["capacity"])
        ) {

            $_SESSION["room_message"] =
                "Room is already full. Select another room.";

            $_SESSION["room_message_type"] =
                "error";

        } else {

            $update = mysqli_prepare(
                $conn,
                "UPDATE users
                 SET room_id = ?
                 WHERE id = ?
                 AND role = 'student'"
            );

            mysqli_stmt_bind_param(
                $update,
                "ii",
                $roomId,
                $studentId
            );


            if (mysqli_stmt_execute($update)) {

                $_SESSION["room_message"] =
                    "Room assigned successfully!";

                $_SESSION["room_message_type"] =
                    "success";

            } else {

                $_SESSION["room_message"] =
                    "Unable to assign room.";

                $_SESSION["room_message_type"] =
                    "error";
            }
        }

        header("Location: rooms.php");
        exit;
    }


    /* =====================================================
       UNASSIGN STUDENT
       ===================================================== */

    if ($action == "unassign_room") {

        $studentId =
            intval($_POST["student_id"] ?? 0);


        $stmt = mysqli_prepare(
            $conn,
            "UPDATE users
             SET room_id = NULL
             WHERE id = ?
             AND role = 'student'"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $studentId
        );


        if (mysqli_stmt_execute($stmt)) {

            $_SESSION["room_message"] =
                "Student removed from room.";

            $_SESSION["room_message_type"] =
                "success";

        } else {

            $_SESSION["room_message"] =
                "Unable to remove student from room.";

            $_SESSION["room_message_type"] =
                "error";
        }

        header("Location: rooms.php");
        exit;
    }
}


/* =========================================================
   GET ROOMS
   ========================================================= */

$rooms = mysqli_query(
    $conn,
    "SELECT
        rooms.id,
        rooms.room_number,
        rooms.block,
        rooms.floor,
        rooms.capacity,
        rooms.created_at,
        COUNT(users.id) AS occupancy

     FROM rooms

     LEFT JOIN users
     ON rooms.id = users.room_id
     AND users.role = 'student'

     GROUP BY rooms.id

     ORDER BY
        rooms.block ASC,
        rooms.room_number ASC"
);


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
        rooms.block

     FROM users

     LEFT JOIN rooms
     ON users.room_id = rooms.id

     WHERE users.role = 'student'

     ORDER BY users.name ASC"
);


/* =========================================================
   STUDENT OPTIONS
   ========================================================= */

$studentOptions = mysqli_query(
    $conn,
    "SELECT
        users.id,
        users.name,
        users.email,
        rooms.room_number

     FROM users

     LEFT JOIN rooms
     ON users.room_id = rooms.id

     WHERE users.role = 'student'

     ORDER BY users.name ASC"
);


/* =========================================================
   ROOM OPTIONS
   ========================================================= */

$roomOptions = mysqli_query(
    $conn,
    "SELECT
        rooms.id,
        rooms.room_number,
        rooms.block,
        rooms.capacity,
        COUNT(users.id) AS occupancy

     FROM rooms

     LEFT JOIN users
     ON rooms.id = users.room_id
     AND users.role = 'student'

     GROUP BY rooms.id

     ORDER BY
        rooms.block ASC,
        rooms.room_number ASC"
);


/* =========================================================
   COUNTS
   ========================================================= */

$totalRoomsResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM rooms"
);

$totalRooms =
    intval(
        mysqli_fetch_assoc(
            $totalRoomsResult
        )["total"]
    );


$totalAssignedResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'student'
     AND room_id IS NOT NULL"
);

$totalAssigned =
    intval(
        mysqli_fetch_assoc(
            $totalAssignedResult
        )["total"]
    );


$totalUnassignedResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'student'
     AND room_id IS NULL"
);

$totalUnassigned =
    intval(
        mysqli_fetch_assoc(
            $totalUnassignedResult
        )["total"]
    );


/* =========================================================
   TOTAL BED CAPACITY
   ========================================================= */

$capacityResult = mysqli_query(
    $conn,
    "SELECT
        COALESCE(SUM(capacity), 0)
        AS total_capacity
     FROM rooms"
);

$totalCapacity =
    intval(
        mysqli_fetch_assoc(
            $capacityResult
        )["total_capacity"]
    );


/* =========================================================
   FULL ROOMS
   ========================================================= */

$fullRoomResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM
     (
        SELECT
            rooms.id

        FROM rooms

        LEFT JOIN users
        ON rooms.id = users.room_id
        AND users.role = 'student'

        GROUP BY
            rooms.id,
            rooms.capacity

        HAVING
            COUNT(users.id) >= rooms.capacity
     ) AS full_rooms"
);

$fullRooms =
    intval(
        mysqli_fetch_assoc(
            $fullRoomResult
        )["total"]
    );


/* =========================================================
   AVAILABLE ROOMS
   ========================================================= */

$availableRooms =
    max(
        0,
        $totalRooms - $fullRooms
    );


/* =========================================================
   OCCUPANCY PERCENTAGE
   ========================================================= */

$occupancyPercentage = 0;

if ($totalCapacity > 0) {

    $occupancyPercentage =
        round(
            ($totalAssigned / $totalCapacity) * 100
        );

    $occupancyPercentage =
        min(
            100,
            $occupancyPercentage
        );
}


/* =========================================================
   HELPER
   ========================================================= */

function getInitial($name)
{
    $name = trim($name);

    if ($name == "") {
        return "A";
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
Room Management - UniStay
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

    --orange:#E98C3A;
    --orange-soft:#FDEADB;

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
   ADMIN PROFILE
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

    min-height:220px;

    display:grid;

    grid-template-columns:
        minmax(0,1fr)
        340px;

    align-items:center;

    gap:30px;

    margin-bottom:20px;

    padding:
        32px 34px;

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

    right:-105px;
    top:-190px;

    border-radius:50%;

    background:
        rgba(255,255,255,0.07);
}


.hero::after {

    content:"";

    position:absolute;

    width:185px;
    height:185px;

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
   HERO RIGHT
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


.hero-side-head {

    display:flex;

    align-items:center;

    gap:12px;

    padding-bottom:14px;

    border-bottom:
        1px solid
        rgba(255,255,255,0.14);
}


.hero-side-icon {

    width:49px;
    height:49px;

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


.hero-mini-grid {

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:8px;

    padding-top:14px;
}


.hero-mini {

    text-align:center;
}


.hero-mini span {

    display:block;

    color:
        rgba(255,255,255,0.67);

    font-size:10px;

    text-transform:uppercase;
}


.hero-mini b {

    display:block;

    margin-top:4px;

    color:white;

    font-size:20px;
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
        var(--green);
}


.card:nth-child(3) {

    border-top:
        4px solid
        var(--mustard);
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

    color:#966507;

    background:
        var(--mustard-soft);
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

    letter-spacing:0.35px;
}


.card h2 {

    margin-top:4px;

    color:
        var(--text);

    font-size:29px;
}


/* =========================================================
   CAPACITY OVERVIEW
   ========================================================= */

.capacity-panel {

    margin-bottom:20px;

    padding:18px 20px;

    border-radius:14px;

    background:white;

    border:
        1px solid
        var(--border);

    box-shadow:
        var(--shadow);
}


.capacity-head {

    display:flex;

    align-items:center;
    justify-content:space-between;

    gap:15px;

    margin-bottom:10px;
}


.capacity-title {

    display:flex;

    align-items:center;

    gap:9px;

    font-size:13px;

    font-weight:700;
}


.capacity-title i {

    color:
        var(--teal);
}


.capacity-percent {

    color:
        var(--teal);

    font-size:13px;

    font-weight:800;
}


.capacity-track {

    height:9px;

    overflow:hidden;

    border-radius:20px;

    background:
        #E7EEF1;
}


.capacity-fill {

    height:100%;

    border-radius:20px;

    background:
        linear-gradient(
            90deg,
            var(--teal),
            var(--green)
        );
}


.capacity-text {

    display:flex;

    justify-content:space-between;

    gap:15px;

    margin-top:9px;

    color:
        var(--muted);

    font-size:12px;
}


/* =========================================================
   FORM GRID
   ========================================================= */

.form-grid {

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:20px;

    margin-bottom:20px;
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
   FORM
   ========================================================= */

.room-form {

    padding:20px;
}


.form-group {

    margin-bottom:16px;
}


.form-group label {

    display:flex;

    align-items:center;

    gap:7px;

    margin-bottom:7px;

    color:#344557;

    font-size:13px;

    font-weight:700;
}


.form-group label i {

    width:17px;

    text-align:center;

    color:
        var(--teal);
}


.form-control {

    width:100%;

    padding:
        12px 13px;

    outline:none;

    border:
        1px solid
        #D7E1E6;

    border-radius:9px;

    background:#FBFCFD;

    color:
        var(--text);

    font-size:13px;

    transition:0.18s ease;
}


.form-control:focus {

    background:white;

    border-color:
        #59A0AE;

    box-shadow:
        0 0 0 3px
        rgba(14,96,119,0.08);
}


.form-note {

    display:flex;

    align-items:flex-start;

    gap:8px;

    margin-bottom:16px;

    padding:11px 12px;

    border-radius:9px;

    color:#526474;

    background:
        linear-gradient(
            90deg,
            #EEF7F8,
            #FFF8E8
        );

    border:
        1px solid
        #E1EAE9;

    font-size:12px;

    line-height:1.5;
}


.form-note i {

    margin-top:2px;

    color:#A97109;
}


.btn {

    width:100%;

    display:flex;

    align-items:center;
    justify-content:center;

    gap:8px;

    padding:13px;

    border:none;

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
        0 6px 15px
        rgba(14,96,119,0.18);

    transition:0.18s ease;
}


.btn:hover {

    transform:
        translateY(-1px);

    background:
        linear-gradient(
            135deg,
            #0B4B5E,
            #0E6E7E
        );
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
        10px 12px 10px 37px;

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


/* =========================================================
   TABLE
   ========================================================= */

.table-wrapper {

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
   ROOM
   ========================================================= */

.room-cell {

    display:flex;

    align-items:center;

    gap:10px;

    min-width:135px;
}


.room-icon {

    width:38px;
    height:38px;

    flex-shrink:0;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:10px;

    color:
        var(--teal);

    background:
        var(--teal-soft);

    font-size:14px;
}


.room-name {

    color:
        var(--text);

    font-size:14px;

    font-weight:750;
}


.room-id {

    margin-top:2px;

    color:
        var(--muted);

    font-size:11px;
}


/* =========================================================
   OCCUPANCY
   ========================================================= */

.occupancy-wrap {

    min-width:120px;
}


.occupancy-top {

    display:flex;

    align-items:center;
    justify-content:space-between;

    gap:8px;

    margin-bottom:6px;

    font-size:12px;

    font-weight:700;
}


.occupancy-track {

    height:6px;

    overflow:hidden;

    border-radius:20px;

    background:
        #E7EEF1;
}


.occupancy-fill {

    height:100%;

    border-radius:20px;

    background:
        linear-gradient(
            90deg,
            var(--teal),
            var(--green)
        );
}


.occupancy-fill.full-fill {

    background:
        linear-gradient(
            90deg,
            var(--orange),
            var(--red)
        );
}


/* =========================================================
   BADGES
   ========================================================= */

.badge {

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


.available {

    color:#1D7658;

    background:
        var(--green-soft);
}


.full {

    color:#AA414A;

    background:
        var(--red-soft);
}


.assigned {

    color:
        var(--teal);

    background:
        var(--teal-soft);
}


.unassigned {

    color:#956506;

    background:
        var(--mustard-soft);
}


/* =========================================================
   STUDENT
   ========================================================= */

.student-cell {

    display:flex;

    align-items:center;

    gap:11px;

    min-width:185px;
}


.student-avatar {

    width:41px;
    height:41px;

    flex-shrink:0;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:11px;

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


.student-name {

    color:
        var(--text);

    font-size:14px;

    font-weight:750;
}


.student-email {

    margin-top:2px;

    color:
        var(--muted);

    font-size:12px;
}


/* =========================================================
   UNASSIGN
   ========================================================= */

.unassign-btn {

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:
        8px 11px;

    border:none;

    border-radius:9px;

    color:#AA414A;

    background:
        var(--red-soft);

    cursor:pointer;

    font-size:11px;

    font-weight:700;

    transition:0.18s ease;
}


.unassign-btn:hover {

    background:#F5D4D7;

    transform:
        translateY(-1px);
}


.no-action {

    color:
        #929DA8;

    font-size:12px;
}


/* =========================================================
   SECTION SPACING
   ========================================================= */

.room-section {

    margin-bottom:20px;
}


.student-section {

    margin-bottom:20px;
}


/* =========================================================
   EMPTY
   ========================================================= */

.empty {

    padding:
        45px 20px;

    text-align:center;

    color:
        var(--muted);

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


    .search-box {

        width:100%;
    }
}


@media(max-width:950px) {

    .form-grid {

        grid-template-columns:1fr;
    }


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


@media(max-width:540px) {

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


    .hero-mini-grid {

        grid-template-columns:1fr;
    }


    .capacity-text {

        flex-direction:column;

        gap:4px;
    }
}

</style>

</head>


<body>


<div class="dashboard">


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

<i class="fa-solid fa-bed"></i>

</div>


<div>

<h1>
Room Management
</h1>

<p>
Create hostel rooms and manage student allocation.
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
Hostel Allocation
</div>


<h2>
Right student.
Right room.
Better stay.
</h2>


<p>

Create hostel rooms, monitor available capacity
and assign students while preventing any room
from exceeding its allowed occupancy.

</p>


<div class="hero-chip">

<i class="fa-solid fa-building-user"></i>

Smart Capacity Control

</div>


</div>



<div class="hero-side">


<div class="hero-side-head">


<div class="hero-side-icon">

<i class="fa-solid fa-building"></i>

</div>


<div>

<strong>
Room Overview
</strong>

<p>
Current hostel allocation summary
</p>

</div>


</div>



<div class="hero-mini-grid">


<div class="hero-mini">

<span>
Rooms
</span>

<b>

<?php echo $totalRooms; ?>

</b>

</div>



<div class="hero-mini">

<span>
Available
</span>

<b>

<?php echo $availableRooms; ?>

</b>

</div>



<div class="hero-mini">

<span>
Full
</span>

<b>

<?php echo $fullRooms; ?>

</b>

</div>


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
     SUMMARY CARDS
     ========================================================= -->

<div class="cards">


<div class="card">


<div class="card-icon">

<i class="fa-solid fa-door-open"></i>

</div>


<div>

<p>
Total Rooms
</p>

<h2>

<?php echo $totalRooms; ?>

</h2>

</div>


</div>



<div class="card">


<div class="card-icon">

<i class="fa-solid fa-user-check"></i>

</div>


<div>

<p>
Students Assigned
</p>

<h2>

<?php echo $totalAssigned; ?>

</h2>

</div>


</div>



<div class="card">


<div class="card-icon">

<i class="fa-solid fa-user-clock"></i>

</div>


<div>

<p>
Without Room
</p>

<h2>

<?php echo $totalUnassigned; ?>

</h2>

</div>


</div>



<div class="card">


<div class="card-icon">

<i class="fa-solid fa-lock"></i>

</div>


<div>

<p>
Full Rooms
</p>

<h2>

<?php echo $fullRooms; ?>

</h2>

</div>


</div>


</div>



<!-- =========================================================
     CAPACITY OVERVIEW
     ========================================================= -->

<div class="capacity-panel">


<div class="capacity-head">


<div class="capacity-title">

<i class="fa-solid fa-chart-simple"></i>

Overall Hostel Bed Occupancy

</div>


<div class="capacity-percent">

<?php echo $occupancyPercentage; ?>%

</div>


</div>



<div class="capacity-track">


<div
    class="capacity-fill"
    style="
        width:
        <?php echo $occupancyPercentage; ?>%;
    "
></div>


</div>



<div class="capacity-text">


<span>

<?php echo $totalAssigned; ?>

occupied beds

</span>


<span>

<?php echo $totalCapacity; ?>

total bed capacity

</span>


</div>


</div>



<!-- =========================================================
     FORMS
     ========================================================= -->

<div class="form-grid">


<!-- =====================================================
     CREATE ROOM
     ===================================================== -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">


<div class="panel-icon">

<i class="fa-solid fa-plus"></i>

</div>


<div>

<h2>
Create Room
</h2>

<p>
Add a new hostel room and capacity.
</p>

</div>


</div>


</div>



<form
    method="POST"
    class="room-form"
>


<input
    type="hidden"
    name="action"
    value="create_room"
>


<div class="form-group">


<label>

<i class="fa-solid fa-door-closed"></i>

Room Number

</label>


<input
    type="text"
    name="room_number"
    class="form-control"
    placeholder="Example: A-102"
    required
>


</div>



<div class="form-group">


<label>

<i class="fa-solid fa-building"></i>

Block

</label>


<input
    type="text"
    name="block"
    class="form-control"
    placeholder="Example: Block A"
    required
>


</div>



<div class="form-group">


<label>

<i class="fa-solid fa-layer-group"></i>

Floor

</label>


<input
    type="text"
    name="floor"
    class="form-control"
    placeholder="Example: 1st Floor"
    required
>


</div>



<div class="form-group">


<label>

<i class="fa-solid fa-users"></i>

Room Capacity

</label>


<input
    type="number"
    name="capacity"
    class="form-control"
    min="1"
    max="10"
    value="2"
    required
>


</div>



<div class="form-note">

<i class="fa-solid fa-circle-info"></i>

<div>
The same room number cannot be created twice
inside the same block.
</div>

</div>



<button
    type="submit"
    class="btn"
>

<i class="fa-solid fa-plus"></i>

Create Room

</button>


</form>


</div>



<!-- =====================================================
     ASSIGN ROOM
     ===================================================== -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">


<div class="panel-icon">

<i class="fa-solid fa-bed"></i>

</div>


<div>

<h2>
Assign Room
</h2>

<p>
Allocate or shift a student to another room.
</p>

</div>


</div>


</div>



<form
    method="POST"
    class="room-form"
>


<input
    type="hidden"
    name="action"
    value="assign_room"
>


<div class="form-group">


<label>

<i class="fa-solid fa-user-graduate"></i>

Select Student

</label>


<select
    name="student_id"
    class="form-control"
    required
>


<option value="">
Choose Student
</option>


<?php

if ($studentOptions) {

    while (
        $studentOption =
        mysqli_fetch_assoc($studentOptions)
    ) {

?>


<option
    value="<?php echo intval($studentOption["id"]); ?>"
>


<?php

echo htmlspecialchars(
    $studentOption["name"]
);

echo " - ";

echo htmlspecialchars(
    $studentOption["email"]
);


if (
    !empty(
        $studentOption["room_number"]
    )
) {

    echo " (Currently: ";

    echo htmlspecialchars(
        $studentOption["room_number"]
    );

    echo ")";
}

?>


</option>


<?php

    }
}

?>


</select>


</div>



<div class="form-group">


<label>

<i class="fa-solid fa-door-open"></i>

Select Room

</label>


<select
    name="room_id"
    class="form-control"
    required
>


<option value="">
Choose Room
</option>


<?php

if ($roomOptions) {

    while (
        $roomOption =
        mysqli_fetch_assoc($roomOptions)
    ) {

        $optionOccupancy =
            intval(
                $roomOption["occupancy"]
            );

        $optionCapacity =
            intval(
                $roomOption["capacity"]
            );

        $optionFull =
            $optionOccupancy >=
            $optionCapacity;

?>


<option
    value="<?php echo intval($roomOption["id"]); ?>"
    <?php
    if ($optionFull) {
        echo "disabled";
    }
    ?>
>


<?php

echo htmlspecialchars(
    $roomOption["room_number"]
);

echo " - ";

echo htmlspecialchars(
    $roomOption["block"]
);

echo " (";

echo $optionOccupancy;

echo "/";

echo $optionCapacity;

echo ")";


if ($optionFull) {

    echo " - FULL";
}

?>


</option>


<?php

    }
}

?>


</select>


</div>



<div class="form-note">

<i class="fa-solid fa-circle-info"></i>

<div>
Full rooms are automatically disabled.
A student already having a room can also be shifted.
</div>

</div>



<button
    type="submit"
    class="btn"
>

<i class="fa-solid fa-check"></i>

Assign Room

</button>


</form>


</div>


</div>



<!-- =========================================================
     ROOM LIST
     ========================================================= -->

<div class="panel room-section">


<div class="panel-head">


<div class="panel-heading">


<div class="panel-icon">

<i class="fa-solid fa-building"></i>

</div>


<div>

<h2>
Hostel Rooms
</h2>

<p>
Capacity and occupancy of every room.
</p>

</div>


</div>



<div class="search-box">


<i class="fa-solid fa-magnifying-glass"></i>


<input
    type="text"
    id="roomSearch"
    placeholder="Search room, block or floor..."
>


</div>


</div>



<div class="table-wrapper">


<table id="roomsTable">


<thead>


<tr>

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
Capacity
</th>

<th>
Occupancy
</th>

<th>
Status
</th>

</tr>


</thead>



<tbody>


<?php

if (
    $rooms &&
    mysqli_num_rows($rooms) > 0
) {


    while (
        $room =
        mysqli_fetch_assoc($rooms)
    ) {


        $roomOccupancy =
            intval(
                $room["occupancy"]
            );


        $roomCapacity =
            intval(
                $room["capacity"]
            );


        $isFull =
            $roomCapacity > 0 &&
            $roomOccupancy >=
            $roomCapacity;


        $roomPercentage =
            ($roomCapacity > 0)
            ?
            min(
                100,
                round(
                    (
                        $roomOccupancy /
                        $roomCapacity
                    ) * 100
                )
            )
            :
            0;

?>


<tr>


<td>


<div class="room-cell">


<div class="room-icon">

<i class="fa-solid fa-bed"></i>

</div>


<div>


<div class="room-name">

<?php
echo htmlspecialchars(
    $room["room_number"]
);
?>

</div>


<div class="room-id">

Room ID:
<?php echo intval($room["id"]); ?>

</div>


</div>


</div>


</td>



<td>

<strong>

<?php
echo htmlspecialchars(
    $room["block"]
);
?>

</strong>

</td>



<td>

<i
    class="fa-solid fa-layer-group"
    style="
        color:#4387A5;
        margin-right:5px;
    "
></i>

<?php
echo htmlspecialchars(
    $room["floor"]
);
?>

</td>



<td>

<?php echo $roomCapacity; ?>

Beds

</td>



<td>


<div class="occupancy-wrap">


<div class="occupancy-top">


<span>

<?php
echo $roomOccupancy;
?> /
<?php
echo $roomCapacity;
?>

</span>


<span>

<?php
echo $roomPercentage;
?>%

</span>


</div>



<div class="occupancy-track">


<div
    class="occupancy-fill <?php echo $isFull ? 'full-fill' : ''; ?>"
    style="
        width:
        <?php echo $roomPercentage; ?>%;
    "
></div>


</div>


</div>


</td>



<td>


<?php if ($isFull) { ?>


<span class="badge full">

<i class="fa-solid fa-lock"></i>

Full

</span>


<?php } else { ?>


<span class="badge available">

<i class="fa-solid fa-circle-check"></i>

Available

</span>


<?php } ?>


</td>


</tr>


<?php

    }

} else {

?>


<tr>

<td
    colspan="6"
    class="empty"
>

No rooms available.

</td>

</tr>


<?php } ?>


</tbody>


</table>


</div>



<div
    id="roomSearchEmpty"
    class="empty"
    style="display:none;"
>

No matching room found.

</div>


</div>



<!-- =========================================================
     STUDENT ROOM ALLOCATION
     ========================================================= -->

<div class="panel student-section">


<div class="panel-head">


<div class="panel-heading">


<div class="panel-icon">

<i class="fa-solid fa-users"></i>

</div>


<div>

<h2>
Student Room Allocation
</h2>

<p>
View assigned rooms and remove allocation when needed.
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



<div class="table-wrapper">


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
Allocation
</th>

<th>
Action
</th>

</tr>


</thead>



<tbody>


<?php

if (
    $students &&
    mysqli_num_rows($students) > 0
) {


    while (
        $student =
        mysqli_fetch_assoc($students)
    ) {

?>


<tr>


<td>


<div class="student-cell">


<div class="student-avatar">

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


</div>


</div>


</td>



<td>


<?php

if (
    !empty(
        $student["room_number"]
    )
) {

?>


<span class="badge assigned">

<i class="fa-solid fa-bed"></i>

<?php
echo htmlspecialchars(
    $student["room_number"]
);
?>

</span>


<?php

} else {

    echo "—";
}

?>


</td>



<td>


<?php

if (
    !empty(
        $student["block"]
    )
) {

    echo htmlspecialchars(
        $student["block"]
    );

} else {

    echo "—";
}

?>


</td>



<td>


<?php

if (
    !empty(
        $student["room_id"]
    )
) {

?>


<span class="badge available">

<i class="fa-solid fa-circle-check"></i>

Assigned

</span>


<?php

} else {

?>


<span class="badge unassigned">

<i class="fa-regular fa-clock"></i>

Not Assigned

</span>


<?php } ?>


</td>



<td>


<?php

if (
    !empty(
        $student["room_id"]
    )
) {

?>


<form
    method="POST"
    style="display:inline;"
    onsubmit="
        return confirm(
            'Remove this student from the room?'
        );
    "
>


<input
    type="hidden"
    name="action"
    value="unassign_room"
>


<input
    type="hidden"
    name="student_id"
    value="<?php echo intval($student["id"]); ?>"
>


<button
    type="submit"
    class="unassign-btn"
>

<i class="fa-solid fa-xmark"></i>

Unassign

</button>


</form>


<?php

} else {

?>


<span class="no-action">

No room assigned

</span>


<?php } ?>


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

No students available.

</td>

</tr>


<?php } ?>


</tbody>


</table>


</div>



<div
    id="studentSearchEmpty"
    class="empty"
    style="display:none;"
>

No matching student found.

</div>


</div>


</div>


</div>



<script>

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


/* =========================================================
   ROOM SEARCH
   ========================================================= */

const roomSearch =
    document.getElementById(
        "roomSearch"
    );


if (roomSearch) {

    roomSearch.addEventListener(
        "keyup",
        function () {

            const filter =
                roomSearch.value
                .toLowerCase()
                .trim();


            const rows =
                document.querySelectorAll(
                    "#roomsTable tbody tr"
                );


            let visible = 0;


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

                        visible++;

                    } else {

                        row.style.display =
                            "none";
                    }

                }
            );


            const table =
                document.getElementById(
                    "roomsTable"
                );


            const empty =
                document.getElementById(
                    "roomSearchEmpty"
                );


            if (
                visible === 0 &&
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
   STUDENT SEARCH
   ========================================================= */

const studentSearch =
    document.getElementById(
        "studentSearch"
    );


if (studentSearch) {

    studentSearch.addEventListener(
        "keyup",
        function () {

            const filter =
                studentSearch.value
                .toLowerCase()
                .trim();


            const rows =
                document.querySelectorAll(
                    "#studentsTable tbody tr"
                );


            let visible = 0;


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

                        visible++;

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
                    "studentSearchEmpty"
                );


            if (
                visible === 0 &&
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
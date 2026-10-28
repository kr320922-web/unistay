<?php

session_start();
include "../db.php";

date_default_timezone_set("Asia/Kolkata");


/* =========================================================
   ONLY WARDEN CAN ACCESS
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
   FLASH MESSAGE
   ========================================================= */

$message =
    $_SESSION["leave_message"] ?? "";

$messageType =
    $_SESSION["leave_message_type"] ?? "";

unset(
    $_SESSION["leave_message"]
);

unset(
    $_SESSION["leave_message_type"]
);


/* =========================================================
   UPDATE LEAVE REQUEST
   ========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] == "POST"
) {

    $leaveId =
        intval(
            $_POST["leave_id"] ?? 0
        );

    $status =
        trim(
            $_POST["status"] ?? ""
        );

    $remark =
        trim(
            $_POST["warden_remark"] ?? ""
        );


    $allowedStatus = [
        "Pending",
        "Approved",
        "Rejected"
    ];


    if (
        $leaveId > 0 &&
        in_array(
            $status,
            $allowedStatus
        )
    ) {

        $stmt =
            mysqli_prepare(
                $conn,
                "UPDATE leave_requests
                 SET
                    status = ?,
                    warden_remark = ?
                 WHERE id = ?"
            );


        mysqli_stmt_bind_param(
            $stmt,
            "ssi",
            $status,
            $remark,
            $leaveId
        );


        if (
            mysqli_stmt_execute($stmt)
        ) {

            $_SESSION["leave_message"] =
                "Leave request updated successfully!";

            $_SESSION["leave_message_type"] =
                "success";

        } else {

            $_SESSION["leave_message"] =
                "Unable to update leave request.";

            $_SESSION["leave_message_type"] =
                "error";
        }


        header(
            "Location: leave_requests.php"
        );

        exit;

    } else {

        $message =
            "Invalid status selected.";

        $messageType =
            "error";
    }
}


/* =========================================================
   COUNTS
   ========================================================= */

$pendingResult =
    mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM leave_requests
         WHERE status = 'Pending'"
    );

$pendingLeaves =
    intval(
        mysqli_fetch_assoc(
            $pendingResult
        )["total"]
    );


$approvedResult =
    mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM leave_requests
         WHERE status = 'Approved'"
    );

$approvedLeaves =
    intval(
        mysqli_fetch_assoc(
            $approvedResult
        )["total"]
    );


$rejectedResult =
    mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM leave_requests
         WHERE status = 'Rejected'"
    );

$rejectedLeaves =
    intval(
        mysqli_fetch_assoc(
            $rejectedResult
        )["total"]
    );


$totalLeaves =
    $pendingLeaves +
    $approvedLeaves +
    $rejectedLeaves;


/* =========================================================
   GET ALL LEAVE REQUESTS
   ========================================================= */

$leaveRequests =
    mysqli_query(
        $conn,
        "SELECT
            leave_requests.id,
            leave_requests.from_date,
            leave_requests.to_date,
            leave_requests.reason,
            leave_requests.status,
            leave_requests.warden_remark,
            leave_requests.created_at,
            users.name,
            users.email

         FROM leave_requests

         LEFT JOIN users
            ON leave_requests.user_id = users.id

         ORDER BY
            CASE
                WHEN leave_requests.status = 'Pending'
                    THEN 1
                WHEN leave_requests.status = 'Approved'
                    THEN 2
                ELSE 3
            END,
            leave_requests.id DESC"
    );


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
Leave Requests - UniStay
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
    --teal-2: #168295;

    --mustard: #E8B23D;

    --blue: #4387A5;
    --green: #36A878;
    --red: #D9656C;
    --orange: #E98C3A;

    --background: #EFF4F7;

    --card: #FFFFFF;

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
        30px 18px;

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

    top: 31px;

    color:
        #7C8796;

    font-size:
        10px;

    letter-spacing:
        0.3px;
}


.logo-box {

    width: 44px;

    height: 44px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 11px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #0C556C,
            #0E7485
        );

    font-size: 21px;

    font-weight: 800;

    box-shadow:
        0 8px 18px
        rgba(14,96,119,0.20);
}


.logo h2 {

    color:
        #162238;

    font-size: 24px;

    font-weight: 800;

    letter-spacing:
        -0.8px;
}


.menu a {

    display: flex;

    align-items: center;

    gap: 13px;

    padding:
        13px 14px;

    margin-bottom: 8px;

    border-radius: 10px;

    color:
        #45576E;

    text-decoration: none;

    font-size: 14px;

    font-weight: 500;

    transition:
        0.18s ease;
}


.menu a i {

    width: 22px;

    text-align: center;

    color:
        #395875;

    font-size: 15px;
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

    font-weight: 700;
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
        28px 32px 50px;
}


/* =========================================================
   TOPBAR
   ========================================================= */

.topbar {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 24px;
}


.page-title {

    display: flex;

    align-items: center;

    gap: 14px;
}


.page-icon {

    width: 50px;

    height: 50px;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

    border-radius: 13px;

    color: white;

    background:
        linear-gradient(
            135deg,
            var(--navy),
            var(--teal)
        );

    font-size: 20px;

    box-shadow:
        0 7px 17px
        rgba(14,96,119,0.18);
}


.topbar h1 {

    color:
        var(--text);

    font-size: 30px;

    letter-spacing:
        -0.6px;
}


.topbar p {

    margin-top: 5px;

    color:
        var(--muted);

    font-size: 14px;
}


/* =========================================================
   WARDEN PROFILE
   ========================================================= */

.warden-info {

    display: flex;

    align-items: center;

    gap: 10px;

    padding:
        8px 14px 8px 8px;

    border-radius: 30px;

    background: white;

    border:
        1px solid
        var(--border);

    box-shadow:
        0 4px 15px
        rgba(31,39,71,0.035);
}


.warden-avatar {

    width: 40px;

    height: 40px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 50%;

    color: white;

    background:
        linear-gradient(
            135deg,
            var(--teal),
            #2C91A4
        );

    font-size: 14px;

    font-weight: 800;
}


.warden-info strong {

    display: block;

    font-size: 14px;
}


.warden-info span {

    display: block;

    margin-top: 2px;

    color:
        var(--muted);

    font-size: 11px;
}


/* =========================================================
   HERO
   ========================================================= */

.leave-hero {

    position: relative;

    overflow: hidden;

    min-height: 205px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 30px;

    margin-bottom: 20px;

    padding:
        30px 33px;

    border-radius: 20px;

    color: white;

    background:
        linear-gradient(
            125deg,
            #1F2747 0%,
            #154A60 42%,
            #0E6077 75%,
            #25899A 100%
        );

    box-shadow:
        0 13px 30px
        rgba(18,71,91,0.15);
}


.leave-hero::before {

    content: "";

    position: absolute;

    width: 290px;

    height: 290px;

    right: -90px;

    top: -170px;

    border-radius: 50%;

    background:
        rgba(255,255,255,0.07);
}


.leave-hero::after {

    content: "";

    position: absolute;

    width: 180px;

    height: 180px;

    right: 230px;

    bottom: -140px;

    border-radius: 50%;

    border:
        28px solid
        rgba(255,255,255,0.04);
}


.hero-text {

    position: relative;

    z-index: 2;
}


.hero-label {

    color:
        rgba(255,255,255,0.75);

    font-size: 12px;

    text-transform: uppercase;

    letter-spacing: 1px;
}


.hero-text h2 {

    margin-top: 7px;

    font-size: 33px;

    letter-spacing:
        -0.8px;
}


.hero-text p {

    max-width: 590px;

    margin-top: 9px;

    color:
        rgba(255,255,255,0.84);

    font-size: 15px;

    line-height: 1.6;
}


.hero-chip {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    margin-top: 17px;

    padding:
        8px 12px;

    border-radius: 20px;

    background:
        rgba(255,255,255,0.12);

    border:
        1px solid
        rgba(255,255,255,0.12);

    font-size: 12px;
}


/* HERO CARD */

.hero-card {

    position: relative;

    z-index: 2;

    width: 300px;

    flex-shrink: 0;

    padding: 18px;

    border-radius: 15px;

    background:
        rgba(255,255,255,0.11);

    border:
        1px solid
        rgba(255,255,255,0.13);
}


.hero-card-top {

    display: flex;

    align-items: center;

    gap: 12px;
}


.hero-card-icon {

    width: 49px;

    height: 49px;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

    border-radius: 12px;

    color:
        var(--navy);

    background:
        var(--mustard);

    font-size: 19px;
}


.hero-card strong {

    display: block;

    font-size: 15px;
}


.hero-card p {

    margin-top: 4px;

    color:
        rgba(255,255,255,0.77);

    font-size: 12px;

    line-height: 1.5;
}


/* =========================================================
   SUMMARY CARDS
   ========================================================= */

.cards {

    display: grid;

    grid-template-columns:
        repeat(4,1fr);

    gap: 14px;

    margin-bottom: 20px;
}


.card {

    min-height: 120px;

    display: flex;

    align-items: center;

    gap: 14px;

    padding: 17px;

    border-radius: 14px;

    background: white;

    border:
        1px solid
        var(--border);

    box-shadow:
        var(--shadow);
}


.card-icon {

    width: 48px;

    height: 48px;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

    border-radius: 12px;

    font-size: 18px;
}


.card:nth-child(1)
.card-icon {

    color:
        var(--teal);

    background:
        #E2F0F3;
}


.card:nth-child(2)
.card-icon {

    color:
        #986706;

    background:
        #FFF0CC;
}


.card:nth-child(3)
.card-icon {

    color:
        var(--green);

    background:
        #E4F5ED;
}


.card:nth-child(4)
.card-icon {

    color:
        var(--red);

    background:
        #FCE4E6;
}


.card p {

    color:
        var(--muted);

    font-size: 12px;

    text-transform: uppercase;

    letter-spacing:
        0.4px;
}


.card h2 {

    margin-top: 4px;

    color:
        var(--text);

    font-size: 27px;

    font-weight: 800;
}


/* =========================================================
   MESSAGE
   ========================================================= */

.status-message {

    display: flex;

    align-items: center;

    gap: 10px;

    margin-bottom: 20px;

    padding:
        14px 16px;

    border-radius: 11px;

    font-size: 13px;

    font-weight: 600;
}


.alert-success {

    color:
        #17684D;

    background:
        #E4F6ED;

    border:
        1px solid
        #C9E9DA;
}


.alert-error {

    color:
        #A64048;

    background:
        #FDEDEE;

    border:
        1px solid
        #F5D3D6;
}


/* =========================================================
   SECTION HEADING
   ========================================================= */

.section-head {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin:
        27px 0 16px;
}


.section-heading {

    display: flex;

    align-items: center;

    gap: 11px;
}


.section-icon {

    width: 42px;

    height: 42px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 11px;

    color:
        var(--teal);

    background:
        #DDEEF2;

    font-size: 16px;
}


.section-head h2 {

    color:
        var(--text);

    font-size: 21px;
}


.section-head p {

    margin-top: 3px;

    color:
        var(--muted);

    font-size: 13px;
}


.section-count {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding:
        7px 11px;

    border-radius: 20px;

    color:
        var(--teal);

    background:
        #E3F1F4;

    font-size: 11px;

    font-weight: 700;
}


/* =========================================================
   LEAVE CARD
   ========================================================= */

.leave-card {

    position: relative;

    overflow: hidden;

    margin-bottom: 16px;

    padding: 21px;

    border-radius: 15px;

    background: white;

    border:
        1px solid
        var(--border);

    box-shadow:
        var(--shadow);

    transition:
        0.18s ease;
}


.leave-card:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 10px 27px
        rgba(31,39,71,0.07);
}


.leave-card::before {

    content: "";

    position: absolute;

    left: 0;

    top: 0;

    bottom: 0;

    width: 5px;

    background:
        var(--teal);
}


.leave-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 20px;
}


.student-wrap {

    display: flex;

    align-items: center;

    gap: 12px;
}


.student-avatar {

    width: 47px;

    height: 47px;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

    border-radius: 50%;

    color: white;

    background:
        linear-gradient(
            135deg,
            var(--teal),
            #3A94A4
        );

    font-size: 14px;

    font-weight: 800;
}


.student-name {

    color:
        var(--text);

    font-size: 18px;

    font-weight: 750;
}


.student-email {

    margin-top: 3px;

    color:
        var(--muted);

    font-size: 12px;
}


/* =========================================================
   STATUS
   ========================================================= */

.status {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding:
        7px 11px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 700;

    height: fit-content;
}


.pending {

    color:
        #936200;

    background:
        #FFF0C7;
}


.approved {

    color:
        #267A5D;

    background:
        #E2F4EB;
}


.rejected {

    color:
        #B4424B;

    background:
        #FCE4E6;
}


/* =========================================================
   DATE DETAILS
   ========================================================= */

.leave-details {

    display: grid;

    grid-template-columns:
        repeat(3,1fr);

    gap: 12px;

    margin-top: 18px;
}


.detail-box {

    padding: 13px;

    border-radius: 10px;

    background:
        #F7F9FA;

    border:
        1px solid
        #E8EEF1;
}


.detail-icon {

    width: 32px;

    height: 32px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-bottom: 8px;

    border-radius: 8px;

    color:
        var(--teal);

    background:
        #E4F1F4;

    font-size: 12px;
}


.detail-label {

    color:
        var(--muted);

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing:
        0.3px;
}


.detail-value {

    margin-top: 5px;

    color:
        #344557;

    font-size: 13px;

    font-weight: 700;
}


/* =========================================================
   REASON
   ========================================================= */

.reason {

    margin-top: 14px;

    padding:
        14px 15px;

    border-radius: 10px;

    color:
        #536273;

    background:
        #F7F9FA;

    border:
        1px solid
        #E8EEF1;

    font-size: 13px;

    line-height: 1.6;
}


.reason-title {

    display: flex;

    align-items: center;

    gap: 7px;

    margin-bottom: 5px;

    color:
        #35495A;

    font-size: 12px;

    font-weight: 750;
}


.reason-title i {

    color:
        var(--teal);
}


/* =========================================================
   UPDATE AREA
   ========================================================= */

.update-area {

    margin-top: 17px;

    padding-top: 17px;

    border-top:
        1px solid
        #E7EDF0;
}


.update-heading {

    margin-bottom: 10px;

    color:
        #536273;

    font-size: 12px;

    font-weight: 700;
}


.update-form {

    display: grid;

    grid-template-columns:
        minmax(160px,0.8fr)
        minmax(260px,1.5fr)
        auto;

    gap: 12px;

    align-items: end;
}


.form-group label {

    display: block;

    margin-bottom: 7px;

    color:
        #344557;

    font-size: 12px;

    font-weight: 700;
}


.form-control {

    width: 100%;

    padding:
        12px 13px;

    outline: none;

    border:
        1px solid
        #D7E1E6;

    border-radius: 9px;

    background:
        #FBFCFD;

    color:
        var(--text);

    font-size: 13px;
}


.form-control:focus {

    border-color:
        #66A6B5;

    background:
        white;

    box-shadow:
        0 0 0 3px
        rgba(14,96,119,0.08);
}


.update-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    min-height: 43px;

    padding:
        12px 18px;

    border: none;

    border-radius: 9px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #0D566C,
            #0E7485
        );

    cursor: pointer;

    font-size: 13px;

    font-weight: 700;

    box-shadow:
        0 6px 15px
        rgba(14,96,119,0.16);

    transition:
        0.18s ease;
}


.update-btn:hover {

    transform:
        translateY(-1px);

    background:
        linear-gradient(
            135deg,
            #0B4B5E,
            #0C6777
        );
}


/* =========================================================
   APPLIED DATE
   ========================================================= */

.applied-date {

    display: flex;

    align-items: center;

    gap: 6px;

    margin-top: 14px;

    color:
        #8995A2;

    font-size: 11px;
}


/* =========================================================
   EMPTY
   ========================================================= */

.empty {

    padding:
        60px 25px;

    border-radius: 15px;

    text-align: center;

    color:
        var(--muted);

    background: white;

    border:
        1px solid
        var(--border);

    box-shadow:
        var(--shadow);
}


.empty-icon {

    width: 72px;

    height: 72px;

    margin:
        0 auto 15px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 20px;

    color:
        var(--teal);

    background:
        #E3F1F4;

    font-size: 27px;
}


.empty h3 {

    color:
        var(--text);

    font-size: 17px;
}


.empty p {

    margin-top: 5px;

    font-size: 13px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media(max-width: 1150px) {

    .cards {

        grid-template-columns:
            repeat(2,1fr);
    }


    .update-form {

        grid-template-columns:
            1fr 1fr;
    }


    .update-btn {

        grid-column:
            1 / -1;
    }
}


@media(max-width: 850px) {

    .sidebar {

        width: 205px;

        min-width: 205px;
    }


    .main {

        padding: 22px;
    }


    .leave-hero {

        flex-direction: column;

        align-items: flex-start;
    }


    .hero-card {

        width: 100%;
    }


    .leave-details {

        grid-template-columns: 1fr;
    }
}


@media(max-width: 700px) {

    .sidebar {

        display: none;
    }


    .topbar {

        align-items: flex-start;
    }


    .warden-info {

        padding: 7px;
    }


    .warden-info > div:last-child {

        display: none;
    }


    .leave-header {

        flex-direction: column;
    }


    .update-form {

        grid-template-columns: 1fr;
    }


    .update-btn {

        grid-column: auto;
    }
}


@media(max-width: 520px) {

    .main {

        padding: 14px;
    }


    .cards {

        grid-template-columns: 1fr;
    }


    .leave-hero {

        padding: 23px;
    }


    .hero-text h2 {

        font-size: 28px;
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

<i class="fa-solid fa-calendar-check"></i>

</div>


<div>

<h1>
Leave Requests
</h1>

<p>
Review and manage student leave applications.
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

<div class="leave-hero">


<div class="hero-text">


<div class="hero-label">

Student Leave Management

</div>


<h2>
Review requests.
Make decisions.
</h2>


<p>

View student leave applications, check their dates and
reason, then approve or reject each request directly
from the warden panel.

</p>


<div class="hero-chip">

<i class="fa-solid fa-shield-halved"></i>

Warden Approval Required

</div>


</div>



<div class="hero-card">


<div class="hero-card-top">


<div class="hero-card-icon">

<i class="fa-solid fa-calendar-day"></i>

</div>


<div>

<strong>
Pending Applications
</strong>

<p>

You currently have
<strong>
<?php echo $pendingLeaves; ?>
</strong>
request<?php echo ($pendingLeaves == 1) ? "" : "s"; ?>
waiting for review.

</p>


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

<i class="fa-solid fa-list-check"></i>

</div>


<div>

<p>
Total Requests
</p>

<h2>

<?php echo $totalLeaves; ?>

</h2>

</div>


</div>



<div class="card">


<div class="card-icon">

<i class="fa-regular fa-clock"></i>

</div>


<div>

<p>
Pending
</p>

<h2>

<?php echo $pendingLeaves; ?>

</h2>

</div>


</div>



<div class="card">


<div class="card-icon">

<i class="fa-solid fa-circle-check"></i>

</div>


<div>

<p>
Approved
</p>

<h2>

<?php echo $approvedLeaves; ?>

</h2>

</div>


</div>



<div class="card">


<div class="card-icon">

<i class="fa-solid fa-circle-xmark"></i>

</div>


<div>

<p>
Rejected
</p>

<h2>

<?php echo $rejectedLeaves; ?>

</h2>

</div>


</div>


</div>



<!-- =========================================================
     MESSAGE
     ========================================================= -->

<?php if ($message != "") { ?>


<div
    id="statusMessage"
    class="status-message <?php
        echo ($messageType == "success")
            ? "alert-success"
            : "alert-error";
    ?>"
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
     REQUEST SECTION
     ========================================================= -->

<div class="section-head">


<div class="section-heading">


<div class="section-icon">

<i class="fa-solid fa-file-signature"></i>

</div>


<div>

<h2>
Student Applications
</h2>

<p>
Pending requests appear first for faster review.
</p>

</div>


</div>



<div class="section-count">

<i class="fa-solid fa-calendar"></i>

<?php echo $totalLeaves; ?>

Request<?php echo ($totalLeaves == 1) ? "" : "s"; ?>

</div>


</div>



<!-- =========================================================
     LEAVE REQUESTS
     ========================================================= -->

<?php

if (
    $leaveRequests &&
    mysqli_num_rows($leaveRequests) > 0
) {


    while (
        $leave =
        mysqli_fetch_assoc($leaveRequests)
    ) {


        $statusClass =
            strtolower(
                str_replace(
                    " ",
                    "-",
                    $leave["status"]
                )
            );


        $studentName =
            $leave["name"]
            ?? "Unknown Student";


        $fromDate =
            new DateTime(
                $leave["from_date"]
            );


        $toDate =
            new DateTime(
                $leave["to_date"]
            );


        $duration =
            $fromDate->diff(
                $toDate
            )->days + 1;

?>


<div class="leave-card">


<!-- HEADER -->

<div class="leave-header">


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

<i class="fa-regular fa-envelope"></i>

&nbsp;

<?php
echo htmlspecialchars(
    $leave["email"] ?? ""
);
?>

</div>


</div>


</div>



<span
    class="status <?php echo $statusClass; ?>"
>


<?php

if (
    $leave["status"] == "Pending"
) {

    echo '<i class="fa-regular fa-clock"></i>';

} elseif (
    $leave["status"] == "Approved"
) {

    echo '<i class="fa-solid fa-check"></i>';

} else {

    echo '<i class="fa-solid fa-xmark"></i>';
}

?>


<?php
echo htmlspecialchars(
    $leave["status"]
);
?>

</span>


</div>



<!-- DATE DETAILS -->

<div class="leave-details">


<div class="detail-box">


<div class="detail-icon">

<i class="fa-solid fa-calendar-day"></i>

</div>


<div class="detail-label">
From
</div>


<div class="detail-value">

<?php

echo date(
    "d M Y",
    strtotime(
        $leave["from_date"]
    )
);

?>

</div>


</div>



<div class="detail-box">


<div class="detail-icon">

<i class="fa-solid fa-calendar-check"></i>

</div>


<div class="detail-label">
To
</div>


<div class="detail-value">

<?php

echo date(
    "d M Y",
    strtotime(
        $leave["to_date"]
    )
);

?>

</div>


</div>



<div class="detail-box">


<div class="detail-icon">

<i class="fa-solid fa-hourglass-half"></i>

</div>


<div class="detail-label">
Duration
</div>


<div class="detail-value">

<?php echo $duration; ?>

Day<?php echo ($duration == 1) ? "" : "s"; ?>

</div>


</div>


</div>



<!-- REASON -->

<div class="reason">


<div class="reason-title">

<i class="fa-solid fa-message"></i>

Leave Reason

</div>


<?php

echo nl2br(
    htmlspecialchars(
        $leave["reason"]
    )
);

?>


</div>



<!-- UPDATE -->

<div class="update-area">


<div class="update-heading">

Review this leave application

</div>


<form
    method="POST"
    class="update-form"
>


<input
    type="hidden"
    name="leave_id"
    value="<?php
        echo intval(
            $leave["id"]
        );
    ?>"
>


<div class="form-group">


<label>

<i class="fa-solid fa-gavel"></i>

Decision

</label>


<select
    name="status"
    class="form-control"
    required
>


<option
    value="Pending"
    <?php
    if (
        $leave["status"] == "Pending"
    ) {
        echo "selected";
    }
    ?>
>

Pending

</option>


<option
    value="Approved"
    <?php
    if (
        $leave["status"] == "Approved"
    ) {
        echo "selected";
    }
    ?>
>

Approved

</option>


<option
    value="Rejected"
    <?php
    if (
        $leave["status"] == "Rejected"
    ) {
        echo "selected";
    }
    ?>
>

Rejected

</option>


</select>


</div>



<div class="form-group">


<label>

<i class="fa-solid fa-comment-dots"></i>

Warden Remark

</label>


<input
    type="text"
    name="warden_remark"
    class="form-control"
    placeholder="Add an optional remark..."
    value="<?php
        echo htmlspecialchars(
            $leave["warden_remark"]
            ?? ""
        );
    ?>"
>


</div>



<button
    type="submit"
    class="update-btn"
>

<i class="fa-solid fa-floppy-disk"></i>

Update Request

</button>


</form>


</div>



<!-- APPLIED DATE -->

<div class="applied-date">

<i class="fa-regular fa-clock"></i>

Applied on

<?php

echo date(
    "d M Y, h:i A",
    strtotime(
        $leave["created_at"]
    )
);

?>

</div>


</div>


<?php

    }

} else {

?>


<div class="empty">


<div class="empty-icon">

<i class="fa-regular fa-calendar-xmark"></i>

</div>


<h3>
No leave requests available
</h3>


<p>
Student leave applications will appear here.
</p>


</div>


<?php } ?>


</div>


</div>



<script>

const statusMessage =
    document.getElementById(
        "statusMessage"
    );


if (statusMessage) {

    setTimeout(function () {

        statusMessage.style.opacity =
            "0";

        statusMessage.style.transition =
            "opacity 0.4s ease";

        setTimeout(function () {

            statusMessage.style.display =
                "none";

        }, 400);

    }, 3000);
}

</script>


</body>

</html>
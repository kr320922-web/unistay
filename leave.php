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

$message = "";
$messageType = "";


/* =========================================================
   APPLY LEAVE
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $fromDate =
        $_POST["from_date"] ?? "";

    $toDate =
        $_POST["to_date"] ?? "";

    $reason =
        trim(
            $_POST["reason"] ?? ""
        );


    if (
        $fromDate == "" ||
        $toDate == "" ||
        $reason == ""
    ) {

        $message =
            "Please fill all fields.";

        $messageType =
            "error";

    } elseif (
        $fromDate < date("Y-m-d")
    ) {

        $message =
            "Leave date cannot be in the past.";

        $messageType =
            "error";

    } elseif (
        $toDate < $fromDate
    ) {

        $message =
            "Return date cannot be before leave date.";

        $messageType =
            "error";

    } else {

        $stmt =
            mysqli_prepare(
                $conn,
                "INSERT INTO leave_requests
                (
                    user_id,
                    from_date,
                    to_date,
                    reason
                )
                VALUES (?, ?, ?, ?)"
            );


        mysqli_stmt_bind_param(
            $stmt,
            "isss",
            $userId,
            $fromDate,
            $toDate,
            $reason
        );


        if (
            mysqli_stmt_execute($stmt)
        ) {

            $message =
                "Leave request submitted successfully!";

            $messageType =
                "success";

        } else {

            $message =
                "Unable to submit leave request.";

            $messageType =
                "error";
        }
    }
}


/* =========================================================
   LEAVE SUMMARY
   ========================================================= */

$summaryStmt =
    mysqli_prepare(
        $conn,
        "SELECT
            COUNT(*) AS total,
            SUM(status = 'Pending') AS pending,
            SUM(status = 'Approved') AS approved,
            SUM(status = 'Rejected') AS rejected
         FROM leave_requests
         WHERE user_id = ?"
    );


mysqli_stmt_bind_param(
    $summaryStmt,
    "i",
    $userId
);


mysqli_stmt_execute(
    $summaryStmt
);


$summaryResult =
    mysqli_stmt_get_result(
        $summaryStmt
    );


$summary =
    mysqli_fetch_assoc(
        $summaryResult
    );


$totalLeaves =
    intval(
        $summary["total"] ?? 0
    );

$pendingLeaves =
    intval(
        $summary["pending"] ?? 0
    );

$approvedLeaves =
    intval(
        $summary["approved"] ?? 0
    );

$rejectedLeaves =
    intval(
        $summary["rejected"] ?? 0
    );


/* =========================================================
   GET STUDENT LEAVE REQUESTS
   ========================================================= */

$stmt =
    mysqli_prepare(
        $conn,
        "SELECT
            id,
            from_date,
            to_date,
            reason,
            status,
            warden_remark,
            created_at
         FROM leave_requests
         WHERE user_id = ?
         ORDER BY id DESC"
    );


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $userId
);


mysqli_stmt_execute($stmt);


$leaveRequests =
    mysqli_stmt_get_result($stmt);


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

    --navy:
        #1F2747;

    --deep-navy:
        #14354F;

    --teal:
        #0E6077;

    --teal-2:
        #168295;

    --mustard:
        #E8B23D;

    --blue:
        #4387A5;

    --green:
        #36A878;

    --red:
        #D9656C;

    --orange:
        #E98C3A;

    --background:
        #EFF4F7;

    --card:
        #FFFFFF;

    --soft:
        #F6F9FA;

    --border:
        #DFE7EC;

    --text:
        #1E2639;

    --muted:
        #718092;

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
   TOPBAR
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

    width: 48px;

    height: 48px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius:
        13px;

    color:
        white;

    background:
        linear-gradient(
            135deg,
            var(--navy),
            var(--teal)
        );

    font-size:
        18px;

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


/* STUDENT */

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
   HERO
   ========================================================= */

.leave-hero {

    position: relative;

    overflow: hidden;

    min-height:
        190px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap:
        30px;

    margin-bottom:
        19px;

    padding:
        28px
        31px;

    border-radius:
        19px;

    color:
        white;

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

    width:
        250px;

    height:
        250px;

    right:
        -80px;

    top:
        -150px;

    border-radius:
        50%;

    background:
        rgba(255,255,255,0.07);
}


.leave-hero::after {

    content: "";

    position: absolute;

    width:
        180px;

    height:
        180px;

    right:
        220px;

    bottom:
        -140px;

    border-radius:
        50%;

    border:
        28px solid
        rgba(255,255,255,0.04);
}


.hero-text {

    position:
        relative;

    z-index:
        2;
}


.hero-label {

    color:
        rgba(255,255,255,0.74);

    font-size:
        11px;

    text-transform:
        uppercase;

    letter-spacing:
        1px;
}


.hero-text h2 {

    margin-top:
        7px;

    font-size:
        29px;

    letter-spacing:
        -0.7px;
}


.hero-text p {

    max-width:
        560px;

    margin-top:
        8px;

    color:
        rgba(255,255,255,0.80);

    font-size:
        13px;

    line-height:
        1.6;
}


/* HERO CARD */

.hero-card {

    width:
        280px;

    flex-shrink:
        0;

    position:
        relative;

    z-index:
        2;

    padding:
        17px;

    border-radius:
        14px;

    background:
        rgba(255,255,255,0.11);

    border:
        1px solid
        rgba(255,255,255,0.13);
}


.hero-card-icon {

    width:
        39px;

    height:
        39px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        10px;

    margin-bottom:
        10px;

    color:
        var(--navy);

    background:
        var(--mustard);

    font-size:
        15px;
}


.hero-card strong {

    display:
        block;

    font-size:
        13px;
}


.hero-card p {

    margin-top:
        4px;

    color:
        rgba(255,255,255,0.75);

    font-size:
        10px;

    line-height:
        1.55;
}


/* =========================================================
   SUMMARY
   ========================================================= */

.summary-grid {

    display: grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:
        14px;

    margin-bottom:
        19px;
}


.summary-card {

    min-height:
        112px;

    display:
        flex;

    align-items:
        center;

    gap:
        13px;

    padding:
        16px;

    border-radius:
        13px;

    background:
        white;

    border:
        1px solid
        var(--border);

    box-shadow:
        var(--shadow);
}


.summary-icon {

    width:
        46px;

    height:
        46px;

    flex-shrink:
        0;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        12px;

    color:
        var(--teal);

    background:
        #E2F0F3;

    font-size:
        17px;
}


.summary-card:nth-child(2)
.summary-icon {

    color:
        #9A6807;

    background:
        #FFF0CC;
}


.summary-card:nth-child(3)
.summary-icon {

    color:
        var(--green);

    background:
        #E4F5ED;
}


.summary-card:nth-child(4)
.summary-icon {

    color:
        var(--red);

    background:
        #FBE7E9;
}


.summary-label {

    color:
        var(--muted);

    font-size:
        10px;

    text-transform:
        uppercase;

    letter-spacing:
        0.45px;
}


.summary-value {

    margin-top:
        3px;

    color:
        var(--text);

    font-size:
        23px;

    font-weight:
        800;
}


/* =========================================================
   ALERT
   ========================================================= */

.alert {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

    margin-bottom:
        18px;

    padding:
        13px
        15px;

    border-radius:
        11px;

    font-size:
        12px;

    font-weight:
        600;
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
   CONTENT
   ========================================================= */

.content-grid {

    display:
        grid;

    grid-template-columns:
        minmax(340px,0.76fr)
        minmax(0,1.24fr);

    gap:
        19px;

    align-items:
        start;
}


/* =========================================================
   PANEL
   ========================================================= */

.panel {

    overflow:
        hidden;

    border-radius:
        15px;

    background:
        white;

    border:
        1px solid
        var(--border);

    box-shadow:
        var(--shadow);
}


.panel-head {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        12px;

    padding:
        19px
        20px;

    border-bottom:
        1px solid
        #EDF1F3;
}


.panel-heading {

    display:
        flex;

    align-items:
        center;

    gap:
        11px;
}


.panel-icon {

    width:
        39px;

    height:
        39px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

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
        10px;
}


.count-pill {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        5px;

    padding:
        6px
        9px;

    border-radius:
        20px;

    color:
        var(--teal);

    background:
        #E5F2F4;

    font-size:
        9px;

    font-weight:
        700;
}


/* =========================================================
   FORM
   ========================================================= */

.leave-form {

    padding:
        20px;
}


.date-grid {

    display:
        grid;

    grid-template-columns:
        repeat(2,1fr);

    gap:
        12px;
}


.form-group {

    margin-bottom:
        18px;
}


.form-group label {

    display:
        flex;

    align-items:
        center;

    gap:
        7px;

    margin-bottom:
        7px;

    color:
        #27374A;

    font-size:
        12px;

    font-weight:
        700;
}


.form-group label i {

    color:
        var(--teal);
}


input,
textarea {

    width:
        100%;

    padding:
        12px
        13px;

    outline:
        none;

    border:
        1px solid
        #D9E2E7;

    border-radius:
        9px;

    background:
        #FBFCFD;

    color:
        var(--text);

    font-size:
        13px;

    transition:
        0.18s ease;
}


input:focus,
textarea:focus {

    border-color:
        #69A9B7;

    background:
        white;

    box-shadow:
        0 0 0 3px
        rgba(14,96,119,0.08);
}


textarea {

    min-height:
        130px;

    resize:
        vertical;

    line-height:
        1.55;
}


.input-help {

    margin-top:
        5px;

    color:
        #929DA8;

    font-size:
        9px;
}


.leave-note {

    display:
        flex;

    align-items:
        flex-start;

    gap:
        9px;

    margin-bottom:
        17px;

    padding:
        11px;

    border-radius:
        9px;

    color:
        #536575;

    background:
        #F2F7F9;

    font-size:
        9px;

    line-height:
        1.5;
}


.leave-note i {

    margin-top:
        2px;

    color:
        var(--teal);
}


.submit-btn {

    width:
        100%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        9px;

    padding:
        13px;

    border:
        none;

    border-radius:
        9px;

    color:
        white;

    background:
        linear-gradient(
            135deg,
            #0D566C,
            #0E7485
        );

    cursor:
        pointer;

    font-size:
        13px;

    font-weight:
        700;

    box-shadow:
        0 7px 16px
        rgba(14,96,119,0.17);

    transition:
        0.18s ease;
}


.submit-btn:hover {

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
   REQUEST LIST
   ========================================================= */

.leave-list {

    max-height:
        760px;

    overflow-y:
        auto;

    padding:
        15px;
}


.leave-list::-webkit-scrollbar {

    width:
        6px;
}


.leave-list::-webkit-scrollbar-thumb {

    border-radius:
        20px;

    background:
        #C9D7DC;
}


/* LEAVE CARD */

.leave-card {

    position:
        relative;

    overflow:
        hidden;

    margin-bottom:
        12px;

    padding:
        16px;

    border:
        1px solid
        #E5ECEF;

    border-radius:
        12px;

    background:
        #FBFCFD;

    transition:
        0.18s ease;
}


.leave-card:last-child {

    margin-bottom:
        0;
}


.leave-card:hover {

    transform:
        translateY(-1px);

    border-color:
        #CCDDE2;

    box-shadow:
        0 6px 18px
        rgba(31,39,71,0.045);
}


.leave-card::before {

    content: "";

    position:
        absolute;

    left:
        0;

    top:
        0;

    bottom:
        0;

    width:
        4px;

    background:
        var(--teal);
}


.leave-top {

    display:
        flex;

    align-items:
        flex-start;

    justify-content:
        space-between;

    gap:
        15px;
}


.leave-date-wrap {

    display:
        flex;

    align-items:
        center;

    gap:
        11px;
}


.calendar-icon {

    width:
        41px;

    height:
        41px;

    flex-shrink:
        0;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        10px;

    color:
        var(--teal);

    background:
        #E2F0F3;

    font-size:
        15px;
}


.date-title {

    color:
        var(--text);

    font-size:
        13px;

    font-weight:
        750;
}


.date-range {

    margin-top:
        4px;

    color:
        var(--muted);

    font-size:
        10px;
}


/* STATUS */

.status {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        5px;

    flex-shrink:
        0;

    padding:
        6px
        9px;

    border-radius:
        20px;

    font-size:
        9px;

    font-weight:
        700;
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


/* REASON */

.reason-box {

    margin-top:
        13px;

    padding:
        11px
        12px;

    border-radius:
        9px;

    color:
        #536273;

    background:
        white;

    border:
        1px solid
        #EDF1F3;

    font-size:
        11px;

    line-height:
        1.6;
}


.reason-title {

    display:
        flex;

    align-items:
        center;

    gap:
        6px;

    margin-bottom:
        4px;

    color:
        #33495D;

    font-size:
        10px;

    font-weight:
        700;
}


.reason-title i {

    color:
        var(--teal);
}


/* REMARK */

.remark {

    display:
        flex;

    align-items:
        flex-start;

    gap:
        9px;

    margin-top:
        10px;

    padding:
        11px;

    border-radius:
        9px;

    color:
        #526174;

    background:
        #F2F7F9;

    border:
        1px solid
        #E5ECEF;

    font-size:
        10px;

    line-height:
        1.55;
}


.remark-icon {

    color:
        var(--mustard);

    margin-top:
        1px;
}


.remark strong {

    color:
        #33495D;
}


/* FOOTER */

.leave-footer {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        12px;

    flex-wrap:
        wrap;

    margin-top:
        12px;
}


.applied-date {

    display:
        flex;

    align-items:
        center;

    gap:
        5px;

    color:
        #8C98A4;

    font-size:
        9px;
}


.duration {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        5px;

    padding:
        5px
        8px;

    border-radius:
        20px;

    color:
        var(--teal);

    background:
        #E6F2F4;

    font-size:
        9px;

    font-weight:
        700;
}


/* =========================================================
   EMPTY
   ========================================================= */

.empty {

    padding:
        60px
        20px;

    text-align:
        center;

    color:
        var(--muted);
}


.empty-icon {

    width:
        70px;

    height:
        70px;

    margin:
        auto
        auto
        16px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        20px;

    color:
        var(--teal);

    background:
        #E3F1F4;

    font-size:
        27px;
}


.empty h3 {

    color:
        var(--text);

    font-size:
        15px;
}


.empty p {

    margin-top:
        5px;

    font-size:
        11px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media(max-width: 1100px) {

    .summary-grid {

        grid-template-columns:
            repeat(2,1fr);
    }


    .content-grid {

        grid-template-columns:
            1fr;
    }
}


@media(max-width: 850px) {

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


    .leave-hero {

        flex-direction:
            column;

        align-items:
            flex-start;
    }


    .hero-card {

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


    .date-grid {

        grid-template-columns:
            1fr;
    }
}


@media(max-width: 520px) {

    .main {

        padding:
            14px;
    }


    .summary-grid {

        grid-template-columns:
            1fr;
    }


    .leave-footer {

        align-items:
            flex-start;

        flex-direction:
            column;
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

<i class="fa-solid fa-calendar-check"></i>

</div>


<div>

<h1>
Leave Request
</h1>

<p>
Apply for hostel leave and track its status.
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



<!-- =========================================================
     HERO
     ========================================================= -->

<div class="leave-hero">


<div class="hero-text">


<div class="hero-label">

Hostel Leave Management

</div>


<h2>
Planning to go out?
Apply here.
</h2>


<p>

Submit your leave dates and reason for approval.
You can track whether your request is pending,
approved or rejected by the hostel warden.

</p>


</div>



<div class="hero-card">


<div class="hero-card-icon">

<i class="fa-solid fa-shield-halved"></i>

</div>


<strong>
Approval Required
</strong>


<p>

Your leave request remains pending until the
warden reviews and approves or rejects it.

</p>


</div>


</div>



<!-- =========================================================
     SUMMARY CARDS
     ========================================================= -->

<div class="summary-grid">


<div class="summary-card">


<div class="summary-icon">

<i class="fa-solid fa-list-check"></i>

</div>


<div>

<div class="summary-label">
Total Requests
</div>


<div class="summary-value">

<?php echo $totalLeaves; ?>

</div>


</div>


</div>



<div class="summary-card">


<div class="summary-icon">

<i class="fa-regular fa-clock"></i>

</div>


<div>

<div class="summary-label">
Pending
</div>


<div class="summary-value">

<?php echo $pendingLeaves; ?>

</div>


</div>


</div>



<div class="summary-card">


<div class="summary-icon">

<i class="fa-solid fa-circle-check"></i>

</div>


<div>

<div class="summary-label">
Approved
</div>


<div class="summary-value">

<?php echo $approvedLeaves; ?>

</div>


</div>


</div>



<div class="summary-card">


<div class="summary-icon">

<i class="fa-solid fa-circle-xmark"></i>

</div>


<div>

<div class="summary-label">
Rejected
</div>


<div class="summary-value">

<?php echo $rejectedLeaves; ?>

</div>


</div>


</div>


</div>



<!-- =========================================================
     MESSAGE
     ========================================================= -->

<?php if ($message != "") { ?>


<div
    id="statusMessage"
    class="alert <?php echo ($messageType == 'success') ? 'alert-success' : 'alert-error'; ?>"
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
     CONTENT GRID
     ========================================================= -->

<div class="content-grid">


<!-- =========================================================
     APPLY LEAVE
     ========================================================= -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">


<div class="panel-icon">

<i class="fa-solid fa-calendar-plus"></i>

</div>


<div>

<h2>
Apply for Leave
</h2>

<p>
Enter your leave details below.
</p>

</div>


</div>


</div>



<form
    method="POST"
    class="leave-form"
>


<div class="date-grid">


<div class="form-group">


<label>

<i class="fa-regular fa-calendar"></i>

From Date

</label>


<input
    type="date"
    name="from_date"
    min="<?php echo date('Y-m-d'); ?>"
    required
>


</div>



<div class="form-group">


<label>

<i class="fa-regular fa-calendar-check"></i>

To Date

</label>


<input
    type="date"
    name="to_date"
    min="<?php echo date('Y-m-d'); ?>"
    required
>


</div>


</div>



<div class="form-group">


<label>

<i class="fa-solid fa-align-left"></i>

Reason for Leave

</label>


<textarea
    name="reason"
    placeholder="Example: Going home for a family function..."
    required
></textarea>


<div class="input-help">

Give a short and clear reason for your leave request.

</div>


</div>



<div class="leave-note">


<i class="fa-solid fa-circle-info"></i>


<div>

Leave cannot start in the past and the return
date must be the same as or after your leave date.

</div>


</div>



<button
    type="submit"
    class="submit-btn"
>


<i class="fa-solid fa-paper-plane"></i>

Submit Leave Request


</button>


</form>


</div>



<!-- =========================================================
     MY LEAVE REQUESTS
     ========================================================= -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">


<div class="panel-icon">

<i class="fa-solid fa-clock-rotate-left"></i>

</div>


<div>

<h2>
My Leave Requests
</h2>

<p>
Track your leave approval history.
</p>

</div>


</div>



<div class="count-pill">

<i class="fa-solid fa-calendar-days"></i>

<?php echo $totalLeaves; ?>

Request<?php echo ($totalLeaves == 1) ? "" : "s"; ?>

</div>


</div>



<div class="leave-list">


<?php

if (
    $leaveRequests &&
    mysqli_num_rows($leaveRequests) > 0
) {

?>


<?php

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


    $startDate =
        new DateTime(
            $leave["from_date"]
        );

    $endDate =
        new DateTime(
            $leave["to_date"]
        );


    $duration =
        $startDate
        ->diff($endDate)
        ->days + 1;

?>


<div class="leave-card">


<div class="leave-top">


<div class="leave-date-wrap">


<div class="calendar-icon">

<i class="fa-regular fa-calendar-days"></i>

</div>


<div>


<div class="date-title">

Leave Period

</div>


<div class="date-range">


<?php

echo date(
    "d M Y",
    strtotime(
        $leave["from_date"]
    )
);

?>


&nbsp;

<i class="fa-solid fa-arrow-right"></i>

&nbsp;


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

} elseif (
    $leave["status"] == "Rejected"
) {

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



<div class="reason-box">


<div class="reason-title">

<i class="fa-solid fa-message"></i>

Reason

</div>


<?php

echo nl2br(
    htmlspecialchars(
        $leave["reason"]
    )
);

?>


</div>



<?php

if (
    !empty(
        $leave["warden_remark"]
    )
) {

?>


<div class="remark">


<div class="remark-icon">

<i class="fa-solid fa-comment-dots"></i>

</div>


<div>


<strong>
Warden Remark
</strong>


<br>


<?php
echo htmlspecialchars(
    $leave["warden_remark"]
);
?>


</div>


</div>


<?php } ?>



<div class="leave-footer">


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



<div class="duration">

<i class="fa-solid fa-hourglass-half"></i>

<?php echo $duration; ?>

Day<?php echo ($duration == 1) ? "" : "s"; ?>


</div>


</div>


</div>


<?php } ?>


<?php } else { ?>


<div class="empty">


<div class="empty-icon">

<i class="fa-regular fa-calendar-xmark"></i>

</div>


<h3>
No leave requests yet
</h3>


<p>

Your submitted leave applications will appear here.

</p>


</div>


<?php } ?>


</div>


</div>


</div>


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
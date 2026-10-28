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
   PRIORITY FUNCTION
   ========================================================= */

function getPriority($count)
{
    if ($count >= 7) {
        return "Urgent";
    } elseif ($count >= 5) {
        return "High";
    } elseif ($count >= 3) {
        return "Medium";
    } else {
        return "Low";
    }
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


/* =========================================================
   FLASH MESSAGE
   ========================================================= */

$message = "";

if (
    isset(
        $_SESSION["complaint_message"]
    )
) {

    $message =
        $_SESSION["complaint_message"];

    unset(
        $_SESSION["complaint_message"]
    );
}


/* =========================================================
   UPDATE COMMON ISSUE STATUS
   ========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] == "POST"
) {

    $category =
        trim(
            $_POST["category"] ?? ""
        );

    $location =
        trim(
            $_POST["location"] ?? ""
        );

    $status =
        trim(
            $_POST["status"] ?? ""
        );


    $allowedStatuses = [
        "Pending",
        "In Progress",
        "Resolved"
    ];


    if (
        $category != "" &&
        $location != "" &&
        in_array(
            $status,
            $allowedStatuses
        )
    ) {

        /*
        UPDATE ALL ACTIVE COMPLAINTS
        WITH SAME CATEGORY + LOCATION
        */

        $stmt =
            mysqli_prepare(
                $conn,
                "UPDATE complaints
                 SET status = ?
                 WHERE category = ?
                 AND location = ?
                 AND status != 'Resolved'"
            );


        mysqli_stmt_bind_param(
            $stmt,
            "sss",
            $status,
            $category,
            $location
        );


        if (
            mysqli_stmt_execute($stmt)
        ) {

            $_SESSION["complaint_message"] =
                "Common issue status updated successfully!";

        } else {

            $_SESSION["complaint_message"] =
                "Unable to update issue status.";
        }
    }


    header(
        "Location: complaints.php"
    );

    exit;
}


/* =========================================================
   SUMMARY DATA
   ========================================================= */

/* TOTAL ACTIVE REPORTS */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM complaints
     WHERE status != 'Resolved'"
);

$activeReports =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


/* TOTAL RESOLVED REPORTS */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM complaints
     WHERE status = 'Resolved'"
);

$resolvedReports =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


/* ACTIVE ISSUE GROUPS */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM (
        SELECT category, location
        FROM complaints
        WHERE status != 'Resolved'
        GROUP BY category, location
     ) AS grouped_issues"
);

$activeGroups =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


/* URGENT ACTIVE GROUPS */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM (
        SELECT category, location
        FROM complaints
        WHERE status != 'Resolved'
        GROUP BY category, location
        HAVING COUNT(*) >= 7
     ) AS urgent_issues"
);

$urgentGroups =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


/* =========================================================
   ACTIVE ISSUES
   ========================================================= */

$activeIssues = mysqli_query(
    $conn,
    "SELECT

        complaints.category,
        complaints.location,

        COUNT(complaints.id)
        AS report_count,

        GROUP_CONCAT(
            DISTINCT users.name
            ORDER BY users.name
            SEPARATOR ', '
        ) AS students,

        MIN(complaints.created_at)
        AS first_reported,

        MAX(complaints.created_at)
        AS latest_reported,

        CASE

            WHEN SUM(
                CASE
                    WHEN complaints.status = 'In Progress'
                    THEN 1
                    ELSE 0
                END
            ) > 0

            THEN 'In Progress'

            ELSE 'Pending'

        END AS common_status

     FROM complaints

     LEFT JOIN users
        ON complaints.user_id = users.id

     WHERE complaints.status != 'Resolved'

     GROUP BY
        complaints.category,
        complaints.location

     ORDER BY
        report_count DESC,
        latest_reported DESC"
);


/* =========================================================
   RESOLVED HISTORY
   ========================================================= */

$resolvedIssues = mysqli_query(
    $conn,
    "SELECT

        complaints.category,
        complaints.location,

        COUNT(complaints.id)
        AS report_count,

        GROUP_CONCAT(
            DISTINCT users.name
            ORDER BY users.name
            SEPARATOR ', '
        ) AS students,

        MIN(complaints.created_at)
        AS first_reported,

        MAX(complaints.created_at)
        AS latest_reported

     FROM complaints

     LEFT JOIN users
        ON complaints.user_id = users.id

     WHERE complaints.status = 'Resolved'

     GROUP BY
        complaints.category,
        complaints.location

     ORDER BY
        latest_reported DESC"
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

<title>
Complaints - UniStay
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

    margin:
        0;

    padding:
        0;

    box-sizing:
        border-box;

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

    display:
        flex;

    min-height:
        100vh;
}


/* =========================================================
   SIDEBAR
   ========================================================= */

.sidebar {

    width:
        230px;

    min-width:
        230px;

    min-height:
        100vh;

    position:
        sticky;

    top:
        0;

    align-self:
        flex-start;

    padding:
        30px 18px;

    background:
        white;

    border-right:
        1px solid var(--border);

    z-index:
        50;
}


.logo {

    position:
        relative;

    display:
        flex;

    align-items:
        center;

    gap:
        12px;

    margin-bottom:
        50px;

    padding-left:
        8px;
}


.logo::after {

    content:
        "Live  Learn  Belong";

    position:
        absolute;

    left:
        61px;

    top:
        31px;

    color:
        #7C8796;

    font-size:
        10px;

    letter-spacing:
        0.3px;
}


.logo-box {

    width:
        44px;

    height:
        44px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        11px;

    color:
        white;

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

    display:
        flex;

    align-items:
        center;

    gap:
        13px;

    padding:
        13px 14px;

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

    width:
        22px;

    text-align:
        center;

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

    flex:
        1;

    min-width:
        0;

    padding:
        28px 32px 50px;
}


/* =========================================================
   TOPBAR
   ========================================================= */

.topbar {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        20px;

    margin-bottom:
        24px;
}


.page-title {

    display:
        flex;

    align-items:
        center;

    gap:
        14px;
}


.page-icon {

    width:
        50px;

    height:
        50px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    flex-shrink:
        0;

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
        20px;

    box-shadow:
        0 7px 17px
        rgba(14,96,119,0.18);
}


.topbar h1 {

    color:
        var(--text);

    font-size:
        30px;

    letter-spacing:
        -0.6px;
}


.topbar p {

    margin-top:
        5px;

    color:
        var(--muted);

    font-size:
        14px;
}


/* =========================================================
   WARDEN PROFILE
   ========================================================= */

.warden-info {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

    padding:
        8px 14px 8px 8px;

    border-radius:
        30px;

    background:
        white;

    border:
        1px solid var(--border);

    box-shadow:
        0 4px 15px
        rgba(31,39,71,0.035);
}


.warden-avatar {

    width:
        40px;

    height:
        40px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

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
        14px;

    font-weight:
        800;
}


.warden-info strong {

    display:
        block;

    font-size:
        14px;
}


.warden-info span {

    display:
        block;

    margin-top:
        2px;

    color:
        var(--muted);

    font-size:
        11px;
}


/* =========================================================
   HERO
   ========================================================= */

.issue-hero {

    position:
        relative;

    overflow:
        hidden;

    min-height:
        205px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        30px;

    margin-bottom:
        20px;

    padding:
        30px 33px;

    border-radius:
        20px;

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


.issue-hero::before {

    content:
        "";

    position:
        absolute;

    width:
        290px;

    height:
        290px;

    right:
        -90px;

    top:
        -170px;

    border-radius:
        50%;

    background:
        rgba(255,255,255,0.07);
}


.issue-hero::after {

    content:
        "";

    position:
        absolute;

    width:
        180px;

    height:
        180px;

    right:
        230px;

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
        rgba(255,255,255,0.75);

    font-size:
        12px;

    text-transform:
        uppercase;

    letter-spacing:
        1px;
}


.hero-text h2 {

    margin-top:
        7px;

    font-size:
        33px;

    letter-spacing:
        -0.8px;
}


.hero-text p {

    max-width:
        590px;

    margin-top:
        9px;

    color:
        rgba(255,255,255,0.84);

    font-size:
        15px;

    line-height:
        1.6;
}


.hero-chip {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        7px;

    margin-top:
        17px;

    padding:
        8px 12px;

    border-radius:
        20px;

    background:
        rgba(255,255,255,0.12);

    border:
        1px solid
        rgba(255,255,255,0.12);

    font-size:
        12px;
}


/* HERO RIGHT */

.hero-card {

    position:
        relative;

    z-index:
        2;

    width:
        300px;

    flex-shrink:
        0;

    padding:
        18px;

    border-radius:
        15px;

    background:
        rgba(255,255,255,0.11);

    border:
        1px solid
        rgba(255,255,255,0.13);
}


.hero-card-top {

    display:
        flex;

    align-items:
        center;

    gap:
        12px;
}


.hero-card-icon {

    width:
        48px;

    height:
        48px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    flex-shrink:
        0;

    border-radius:
        12px;

    color:
        var(--navy);

    background:
        var(--mustard);

    font-size:
        19px;
}


.hero-card strong {

    display:
        block;

    font-size:
        15px;
}


.hero-card p {

    margin-top:
        4px;

    color:
        rgba(255,255,255,0.77);

    font-size:
        12px;

    line-height:
        1.5;
}


/* =========================================================
   SUMMARY
   ========================================================= */

.summary-grid {

    display:
        grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:
        14px;

    margin-bottom:
        20px;
}


.summary-card {

    min-height:
        120px;

    display:
        flex;

    align-items:
        center;

    gap:
        14px;

    padding:
        17px;

    border-radius:
        14px;

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
        48px;

    height:
        48px;

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

    font-size:
        18px;
}


.summary-card:nth-child(1)
.summary-icon {

    color:
        #986706;

    background:
        #FFF0CC;
}


.summary-card:nth-child(2)
.summary-icon {

    color:
        var(--teal);

    background:
        #E2F0F3;
}


.summary-card:nth-child(3)
.summary-icon {

    color:
        #B4424B;

    background:
        #FCE4E6;
}


.summary-card:nth-child(4)
.summary-icon {

    color:
        var(--green);

    background:
        #E4F5ED;
}


.summary-label {

    color:
        var(--muted);

    font-size:
        12px;

    text-transform:
        uppercase;

    letter-spacing:
        0.4px;
}


.summary-value {

    margin-top:
        4px;

    color:
        var(--text);

    font-size:
        27px;

    font-weight:
        800;
}


/* =========================================================
   MESSAGE
   ========================================================= */

.message {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

    margin-bottom:
        20px;

    padding:
        14px 16px;

    border-radius:
        11px;

    color:
        #17684D;

    background:
        #E4F6ED;

    border:
        1px solid
        #C9E9DA;

    font-size:
        13px;

    font-weight:
        600;
}


/* =========================================================
   SECTION HEADER
   ========================================================= */

.section-head {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        15px;

    margin:
        27px 0 16px;
}


.section-heading {

    display:
        flex;

    align-items:
        center;

    gap:
        11px;
}


.section-icon {

    width:
        42px;

    height:
        42px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        11px;

    color:
        var(--teal);

    background:
        #DDEEF2;

    font-size:
        16px;
}


.section-head h2 {

    color:
        var(--text);

    font-size:
        21px;
}


.section-head p {

    margin-top:
        3px;

    color:
        var(--muted);

    font-size:
        13px;
}


.section-count {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        6px;

    padding:
        7px 11px;

    border-radius:
        20px;

    color:
        var(--teal);

    background:
        #E3F1F4;

    font-size:
        11px;

    font-weight:
        700;
}


/* =========================================================
   ISSUE CARD
   ========================================================= */

.issue-card {

    position:
        relative;

    overflow:
        hidden;

    margin-bottom:
        15px;

    padding:
        21px;

    border-radius:
        15px;

    background:
        white;

    border:
        1px solid
        var(--border);

    box-shadow:
        var(--shadow);

    transition:
        0.18s ease;
}


.issue-card:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 10px 27px
        rgba(31,39,71,0.07);
}


.issue-card::before {

    content:
        "";

    position:
        absolute;

    left:
        0;

    top:
        0;

    bottom:
        0;

    width:
        5px;

    background:
        var(--teal);
}


.issue-header {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        flex-start;

    gap:
        20px;
}


.issue-title-wrap {

    display:
        flex;

    align-items:
        center;

    gap:
        12px;
}


.issue-main-icon {

    width:
        45px;

    height:
        45px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    flex-shrink:
        0;

    border-radius:
        11px;

    color:
        var(--teal);

    background:
        #E2F0F3;

    font-size:
        17px;
}


.issue-category {

    color:
        var(--text);

    font-size:
        18px;

    font-weight:
        750;
}


.location {

    display:
        flex;

    align-items:
        center;

    gap:
        6px;

    margin-top:
        5px;

    color:
        var(--muted);

    font-size:
        13px;
}


.location i {

    color:
        var(--teal);
}


/* =========================================================
   BADGES
   ========================================================= */

.badges {

    display:
        flex;

    align-items:
        center;

    justify-content:
        flex-end;

    gap:
        7px;

    flex-wrap:
        wrap;
}


.badge {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        5px;

    padding:
        6px 10px;

    border-radius:
        20px;

    font-size:
        11px;

    font-weight:
        700;
}


.low {

    color:
        #336F96;

    background:
        #E7F2F8;
}


.medium {

    color:
        #956A11;

    background:
        #FFF0CA;
}


.high {

    color:
        #B26119;

    background:
        #FDE9D9;
}


.urgent {

    color:
        #B4424B;

    background:
        #FCE4E6;
}


.pending {

    color:
        #936200;

    background:
        #FFF0C7;
}


.in-progress {

    color:
        #306F9E;

    background:
        #E3EFF8;
}


.resolved {

    color:
        #267A5D;

    background:
        #E2F4EB;
}


/* =========================================================
   ISSUE DETAILS
   ========================================================= */

.issue-details {

    display:
        grid;

    grid-template-columns:
        1.3fr 1fr 1fr;

    gap:
        12px;

    margin-top:
        18px;
}


.detail-box {

    min-height:
        92px;

    padding:
        13px;

    border-radius:
        10px;

    background:
        #F7F9FA;

    border:
        1px solid
        #E8EEF1;
}


.detail-icon {

    width:
        31px;

    height:
        31px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    margin-bottom:
        8px;

    border-radius:
        8px;

    color:
        var(--teal);

    background:
        #E4F1F4;

    font-size:
        12px;
}


.detail-label {

    color:
        var(--muted);

    font-size:
        11px;

    text-transform:
        uppercase;

    letter-spacing:
        0.3px;
}


.detail-value {

    margin-top:
        5px;

    color:
        #344557;

    font-size:
        13px;

    font-weight:
        650;

    line-height:
        1.5;
}


/* =========================================================
   UPDATE
   ========================================================= */

.update-area {

    margin-top:
        18px;

    padding-top:
        17px;

    border-top:
        1px solid
        #E7EDF0;
}


.update-label {

    margin-bottom:
        9px;

    color:
        #536273;

    font-size:
        12px;

    font-weight:
        700;
}


.update-form {

    display:
        flex;

    gap:
        11px;

    align-items:
        center;
}


.update-form select {

    flex:
        1;

    min-width:
        0;

    padding:
        12px 13px;

    outline:
        none;

    border:
        1px solid
        #D7E1E6;

    border-radius:
        9px;

    background:
        #FBFCFD;

    color:
        var(--text);

    font-size:
        13px;
}


.update-form select:focus {

    border-color:
        #66A6B5;

    box-shadow:
        0 0 0 3px
        rgba(14,96,119,0.08);
}


.update-btn {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        7px;

    padding:
        12px 18px;

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
   RESOLVED
   ========================================================= */

.history-section {

    margin-top:
        35px;
}


.resolved-card {

    opacity:
        0.96;
}


.resolved-card::before {

    background:
        var(--green);
}


.resolved-card
.issue-main-icon {

    color:
        var(--green);

    background:
        #E4F5ED;
}


/* =========================================================
   EMPTY
   ========================================================= */

.empty {

    padding:
        55px 25px;

    margin-bottom:
        25px;

    border-radius:
        15px;

    text-align:
        center;

    color:
        var(--muted);

    background:
        white;

    border:
        1px solid
        var(--border);

    box-shadow:
        var(--shadow);
}


.empty-icon {

    width:
        70px;

    height:
        70px;

    margin:
        0 auto 15px;

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
        17px;
}


.empty p {

    margin-top:
        5px;

    font-size:
        13px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media(max-width: 1150px) {

    .summary-grid {

        grid-template-columns:
            repeat(2,1fr);
    }


    .issue-details {

        grid-template-columns:
            1fr 1fr;
    }


    .issue-details
    .detail-box:first-child {

        grid-column:
            1 / -1;
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


    .issue-hero {

        flex-direction:
            column;

        align-items:
            flex-start;
    }


    .hero-card {

        width:
            100%;
    }


    .issue-header {

        flex-direction:
            column;
    }


    .badges {

        justify-content:
            flex-start;
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


    .warden-info {

        padding:
            7px;
    }


    .warden-info > div:last-child {

        display:
            none;
    }


    .issue-details {

        grid-template-columns:
            1fr;
    }


    .issue-details
    .detail-box:first-child {

        grid-column:
            auto;
    }


    .update-form {

        flex-direction:
            column;

        align-items:
            stretch;
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


    .issue-hero {

        padding:
            23px;
    }


    .hero-text h2 {

        font-size:
            28px;
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

<i class="fa-solid fa-triangle-exclamation"></i>

</div>


<div>

<h1>
Smart Issue Detector
</h1>

<p>
Detect, group and manage repeated hostel complaints.
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

<div class="issue-hero">


<div class="hero-text">


<div class="hero-label">

Smart Complaint Management

</div>


<h2>
One issue.
Multiple reports.
One action.
</h2>


<p>

UniStay automatically groups complaints with the same
category and location so repeated hostel problems can
be identified and handled together.

</p>


<div class="hero-chip">

<i class="fa-solid fa-wand-magic-sparkles"></i>

Automatic Priority Detection

</div>


</div>



<div class="hero-card">


<div class="hero-card-top">


<div class="hero-card-icon">

<i class="fa-solid fa-layer-group"></i>

</div>


<div>

<strong>
Smart Grouping
</strong>

<p>

Repeated complaints from the same location
are combined into one common issue.

</p>

</div>


</div>


</div>


</div>



<!-- =========================================================
     SUMMARY
     ========================================================= -->

<div class="summary-grid">


<div class="summary-card">


<div class="summary-icon">

<i class="fa-solid fa-layer-group"></i>

</div>


<div>

<div class="summary-label">
Active Issues
</div>


<div class="summary-value">

<?php echo $activeGroups; ?>

</div>


</div>


</div>



<div class="summary-card">


<div class="summary-icon">

<i class="fa-solid fa-file-circle-exclamation"></i>

</div>


<div>

<div class="summary-label">
Active Reports
</div>


<div class="summary-value">

<?php echo $activeReports; ?>

</div>


</div>


</div>



<div class="summary-card">


<div class="summary-icon">

<i class="fa-solid fa-fire"></i>

</div>


<div>

<div class="summary-label">
Urgent Issues
</div>


<div class="summary-value">

<?php echo $urgentGroups; ?>

</div>


</div>


</div>



<div class="summary-card">


<div class="summary-icon">

<i class="fa-solid fa-circle-check"></i>

</div>


<div>

<div class="summary-label">
Resolved Reports
</div>


<div class="summary-value">

<?php echo $resolvedReports; ?>

</div>


</div>


</div>


</div>



<!-- =========================================================
     MESSAGE
     ========================================================= -->

<?php if ($message != "") { ?>


<div
    id="successMessage"
    class="message"
>

<i class="fa-solid fa-circle-check"></i>

<?php
echo htmlspecialchars(
    $message
);
?>

</div>


<?php } ?>



<!-- =========================================================
     ACTIVE ISSUES HEADING
     ========================================================= -->

<div class="section-head">


<div class="section-heading">


<div class="section-icon">

<i class="fa-solid fa-screwdriver-wrench"></i>

</div>


<div>

<h2>
Active Issues
</h2>

<p>
Pending and in-progress hostel problems.
</p>

</div>


</div>



<div class="section-count">

<i class="fa-solid fa-layer-group"></i>

<?php echo $activeGroups; ?>

Active

</div>


</div>



<!-- =========================================================
     ACTIVE ISSUES
     ========================================================= -->

<?php

if (
    $activeIssues &&
    mysqli_num_rows($activeIssues) > 0
) {

    while (
        $issue =
        mysqli_fetch_assoc($activeIssues)
    ) {


        $reportCount =
            intval(
                $issue["report_count"]
            );


        $priority =
            getPriority(
                $reportCount
            );


        $priorityClass =
            strtolower(
                $priority
            );


        $statusClass =
            strtolower(
                str_replace(
                    " ",
                    "-",
                    $issue["common_status"]
                )
            );

?>


<div class="issue-card">


<div class="issue-header">


<div class="issue-title-wrap">


<div class="issue-main-icon">


<?php

$issueIcon =
    "fa-triangle-exclamation";


if (
    $issue["category"] == "Wi-Fi"
) {

    $issueIcon =
        "fa-wifi";

} elseif (
    $issue["category"] == "Electricity"
) {

    $issueIcon =
        "fa-bolt";

} elseif (
    $issue["category"] == "Plumbing"
) {

    $issueIcon =
        "fa-faucet-drip";

} elseif (
    $issue["category"] == "Cleaning"
) {

    $issueIcon =
        "fa-broom";

} elseif (
    $issue["category"] == "Furniture"
) {

    $issueIcon =
        "fa-chair";

} elseif (
    $issue["category"] == "Mess"
) {

    $issueIcon =
        "fa-utensils";
}

?>


<i class="fa-solid <?php echo $issueIcon; ?>"></i>


</div>



<div>


<div class="issue-category">

<?php
echo htmlspecialchars(
    $issue["category"]
);
?>

</div>


<div class="location">

<i class="fa-solid fa-location-dot"></i>

<?php
echo htmlspecialchars(
    $issue["location"]
);
?>

</div>


</div>


</div>



<div class="badges">


<span
    class="badge <?php echo $priorityClass; ?>"
>

<i class="fa-solid fa-users"></i>

<?php echo $reportCount; ?>

Report<?php echo ($reportCount == 1) ? "" : "s"; ?>

</span>



<span
    class="badge <?php echo $priorityClass; ?>"
>

<i class="fa-solid fa-flag"></i>

<?php echo $priority; ?>

Priority

</span>



<span
    class="badge <?php echo $statusClass; ?>"
>


<?php

if (
    $issue["common_status"] == "Pending"
) {

    echo '<i class="fa-regular fa-clock"></i>';

} else {

    echo '<i class="fa-solid fa-spinner"></i>';
}

?>


<?php
echo htmlspecialchars(
    $issue["common_status"]
);
?>

</span>


</div>


</div>



<!-- DETAILS -->

<div class="issue-details">


<div class="detail-box">


<div class="detail-icon">

<i class="fa-solid fa-user-group"></i>

</div>


<div class="detail-label">
Students
</div>


<div class="detail-value">

<?php
echo htmlspecialchars(
    $issue["students"] ?? "-"
);
?>

</div>


</div>



<div class="detail-box">


<div class="detail-icon">

<i class="fa-regular fa-clock"></i>

</div>


<div class="detail-label">
First Reported
</div>


<div class="detail-value">

<?php

echo date(
    "d M Y, h:i A",
    strtotime(
        $issue["first_reported"]
    )
);

?>

</div>


</div>



<div class="detail-box">


<div class="detail-icon">

<i class="fa-solid fa-clock-rotate-left"></i>

</div>


<div class="detail-label">
Latest Report
</div>


<div class="detail-value">

<?php

echo date(
    "d M Y, h:i A",
    strtotime(
        $issue["latest_reported"]
    )
);

?>

</div>


</div>


</div>



<!-- =========================================================
     UPDATE STATUS
     ========================================================= -->

<div class="update-area">


<div class="update-label">

Update status for all
<?php echo $reportCount; ?>
report<?php echo ($reportCount == 1) ? "" : "s"; ?>
of this common issue:

</div>


<form
    method="POST"
    class="update-form"
>


<input
    type="hidden"
    name="category"
    value="<?php
        echo htmlspecialchars(
            $issue["category"]
        );
    ?>"
>


<input
    type="hidden"
    name="location"
    value="<?php
        echo htmlspecialchars(
            $issue["location"]
        );
    ?>"
>


<select
    name="status"
    required
>


<option
    value="Pending"
    <?php

    if (
        $issue["common_status"] == "Pending"
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
        $issue["common_status"] == "In Progress"
    ) {
        echo "selected";
    }

    ?>
>

In Progress

</option>



<option value="Resolved">

Resolved

</option>


</select>



<button
    type="submit"
    class="update-btn"
>

<i class="fa-solid fa-floppy-disk"></i>

Update Status

</button>


</form>


</div>


</div>


<?php

    }

} else {

?>


<div class="empty">


<div class="empty-icon">

<i class="fa-solid fa-circle-check"></i>

</div>


<h3>
No active hostel issues
</h3>


<p>

All reported issues have been resolved.

</p>


</div>


<?php } ?>



<!-- =========================================================
     RESOLVED HISTORY
     ========================================================= -->

<div class="history-section">


<div class="section-head">


<div class="section-heading">


<div class="section-icon">

<i class="fa-solid fa-clock-rotate-left"></i>

</div>


<div>

<h2>
Resolved History
</h2>

<p>
Previously resolved hostel issues.
</p>

</div>


</div>


</div>



<?php

if (
    $resolvedIssues &&
    mysqli_num_rows($resolvedIssues) > 0
) {

    while (
        $issue =
        mysqli_fetch_assoc($resolvedIssues)
    ) {


        $reportCount =
            intval(
                $issue["report_count"]
            );


        $priority =
            getPriority(
                $reportCount
            );


        $priorityClass =
            strtolower(
                $priority
            );

?>


<div class="issue-card resolved-card">


<div class="issue-header">


<div class="issue-title-wrap">


<div class="issue-main-icon">

<i class="fa-solid fa-circle-check"></i>

</div>


<div>


<div class="issue-category">

<?php
echo htmlspecialchars(
    $issue["category"]
);
?>

</div>


<div class="location">

<i class="fa-solid fa-location-dot"></i>

<?php
echo htmlspecialchars(
    $issue["location"]
);
?>

</div>


</div>


</div>



<div class="badges">


<span
    class="badge <?php echo $priorityClass; ?>"
>

<i class="fa-solid fa-users"></i>

<?php echo $reportCount; ?>

Report<?php echo ($reportCount == 1) ? "" : "s"; ?>

</span>



<span
    class="badge <?php echo $priorityClass; ?>"
>

<i class="fa-solid fa-flag"></i>

<?php echo $priority; ?>

Priority

</span>



<span class="badge resolved">

<i class="fa-solid fa-check"></i>

Resolved

</span>


</div>


</div>



<div class="issue-details">


<div class="detail-box">


<div class="detail-icon">

<i class="fa-solid fa-user-group"></i>

</div>


<div class="detail-label">

Students

</div>


<div class="detail-value">

<?php

echo htmlspecialchars(
    $issue["students"] ?? "-"
);

?>

</div>


</div>



<div class="detail-box">


<div class="detail-icon">

<i class="fa-regular fa-clock"></i>

</div>


<div class="detail-label">

First Reported

</div>


<div class="detail-value">

<?php

echo date(
    "d M Y, h:i A",
    strtotime(
        $issue["first_reported"]
    )
);

?>

</div>


</div>



<div class="detail-box">


<div class="detail-icon">

<i class="fa-solid fa-circle-check"></i>

</div>


<div class="detail-label">

Last Reported

</div>


<div class="detail-value">

<?php

echo date(
    "d M Y, h:i A",
    strtotime(
        $issue["latest_reported"]
    )
);

?>

</div>


</div>


</div>


</div>


<?php

    }

} else {

?>


<div class="empty">


<div class="empty-icon">

<i class="fa-regular fa-folder-open"></i>

</div>


<h3>
No resolved complaints yet
</h3>


<p>

Resolved common issues will appear here.

</p>


</div>


<?php } ?>


</div>


</div>


</div>



<script>

const successMessage =
    document.getElementById(
        "successMessage"
    );


if (successMessage) {

    setTimeout(function () {

        successMessage.style.opacity =
            "0";

        successMessage.style.transition =
            "opacity 0.4s ease";

        setTimeout(function () {

            successMessage.style.display =
                "none";

        }, 400);

    }, 3000);
}

</script>


</body>

</html>
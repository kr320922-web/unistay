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
    $_SESSION["user_role"] !== "warden"
) {
    header("Location: ../index.php");
    exit;
}


$wardenName =
    $_SESSION["user_name"] ?? "Warden";


/* =========================================================
   FLASH MESSAGE
   ========================================================= */

$message = "";
$messageType = "";

if (isset($_SESSION["sos_message"])) {

    $message =
        $_SESSION["sos_message"];

    $messageType =
        $_SESSION["sos_message_type"] ?? "";

    unset($_SESSION["sos_message"]);
    unset($_SESSION["sos_message_type"]);
}


/* =========================================================
   UPDATE SOS ALERT
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action =
        $_POST["action"] ?? "";


    if ($action === "update_alert") {

        $alertId =
            intval(
                $_POST["alert_id"] ?? 0
            );

        $status =
            trim(
                $_POST["status"] ?? ""
            );

        $wardenRemark =
            trim(
                $_POST["warden_remark"] ?? ""
            );


        $allowedStatuses = [
            "Active",
            "Responding",
            "Resolved"
        ];


        if (
            $alertId <= 0 ||
            !in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {

            $_SESSION["sos_message"] =
                "Invalid SOS details.";

            $_SESSION["sos_message_type"] =
                "error";

        } else {

            if ($status === "Resolved") {

                $stmt =
                    mysqli_prepare(
                        $conn,
                        "UPDATE emergency_alerts

                         SET
                            status = ?,
                            warden_remark = ?,
                            resolved_at = NOW()

                         WHERE id = ?"
                    );

            } else {

                $stmt =
                    mysqli_prepare(
                        $conn,
                        "UPDATE emergency_alerts

                         SET
                            status = ?,
                            warden_remark = ?,
                            resolved_at = NULL

                         WHERE id = ?"
                    );
            }


            if (!$stmt) {

                die(
                    "SOS update query error: " .
                    mysqli_error($conn)
                );
            }


            mysqli_stmt_bind_param(
                $stmt,
                "ssi",
                $status,
                $wardenRemark,
                $alertId
            );


            if (mysqli_stmt_execute($stmt)) {

                $_SESSION["sos_message"] =
                    "SOS alert updated successfully.";

                $_SESSION["sos_message_type"] =
                    "success";

            } else {

                $_SESSION["sos_message"] =
                    "Unable to update SOS alert.";

                $_SESSION["sos_message_type"] =
                    "error";
            }


            mysqli_stmt_close($stmt);
        }


        header(
            "Location: emergency.php"
        );

        exit;
    }
}


/* =========================================================
   SUMMARY COUNTS
   ========================================================= */

$countQuery =
    "SELECT

        COUNT(*) AS total,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'Active'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS active_count,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'Responding'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS responding_count,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'Resolved'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS resolved_count

     FROM emergency_alerts";


$countResult =
    mysqli_query(
        $conn,
        $countQuery
    );


if (!$countResult) {

    die(
        "SOS count query error: " .
        mysqli_error($conn)
    );
}


$countData =
    mysqli_fetch_assoc(
        $countResult
    );


$totalAlerts =
    intval(
        $countData["total"] ?? 0
    );

$activeAlerts =
    intval(
        $countData["active_count"] ?? 0
    );

$respondingAlerts =
    intval(
        $countData["responding_count"] ?? 0
    );

$resolvedAlerts =
    intval(
        $countData["resolved_count"] ?? 0
    );


/* =========================================================
   GET ALL SOS ALERTS
   ========================================================= */

$alertQuery =
    "SELECT

        e.id,
        e.user_id,
        e.emergency_type,
        e.location,
        e.message,
        e.status,
        e.warden_remark,
        e.created_at,
        e.updated_at,
        e.resolved_at,

        u.name AS student_name,
        u.email AS student_email,

        r.room_number,
        r.block

     FROM emergency_alerts AS e

     INNER JOIN users AS u
        ON e.user_id = u.id

     LEFT JOIN rooms AS r
        ON u.room_id = r.id

     ORDER BY

        CASE
            WHEN e.status = 'Active'
            THEN 1

            WHEN e.status = 'Responding'
            THEN 2

            ELSE 3
        END,

        e.id DESC";


$alerts =
    mysqli_query(
        $conn,
        $alertQuery
    );


if (!$alerts) {

    die(
        "SOS report query error: " .
        mysqli_error($conn)
    );
}


/* =========================================================
   HELPERS
   ========================================================= */

function getInitial($name)
{
    $name = trim($name);

    if ($name === "") {
        return "S";
    }

    return strtoupper(
        substr(
            $name,
            0,
            1
        )
    );
}


function getEmergencyIcon($type)
{
    switch ($type) {

        case "Medical":
            return "fa-kit-medical";

        case "Security":
            return "fa-shield-halved";

        case "Fire":
            return "fa-fire";

        case "Accident":
            return "fa-triangle-exclamation";

        case "Harassment":
            return "fa-person-circle-exclamation";

        default:
            return "fa-bell";
    }
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
Emergency SOS - UniStay
</title>


<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
>


<style>

:root {

    --navy:#1F2747;

    --teal:#0E6077;
    --teal2:#168295;

    --red:#E76871;
    --red-dark:#C94E59;
    --red-soft:#FCEAEC;

    --orange:#E79542;
    --orange-soft:#FFF0DF;

    --mustard:#E8B23D;
    --mustard-soft:#FFF2D3;

    --blue:#4387A5;
    --blue-soft:#E5F0F5;

    --green:#36B486;
    --green-soft:#E3F5EC;

    --background:#EFF4F7;

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

    background:#DDF0F3;

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
            var(--red-dark),
            var(--red)
        );

    font-size:21px;
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


/* =========================================================
   WARDEN PROFILE
   ========================================================= */

.profile {

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


.profile-avatar {

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


.profile strong {

    display:block;

    font-size:14px;
}


.profile span {

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

    display:grid;

    grid-template-columns:
        minmax(0,1fr)
        355px;

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
            120deg,
            #1F2747 0%,
            #5D354D 47%,
            #B84F5B 100%
        );
}


.hero::before {

    content:"";

    position:absolute;

    width:300px;
    height:300px;

    right:-100px;
    top:-180px;

    border-radius:50%;

    background:
        rgba(255,255,255,0.07);
}


.hero-content {

    position:relative;

    z-index:2;
}


.hero-label {

    color:#FFD59D;

    font-size:12px;

    font-weight:700;

    letter-spacing:1px;

    text-transform:uppercase;
}


.hero h2 {

    margin-top:7px;

    font-size:34px;
}


.hero p {

    max-width:610px;

    margin-top:10px;

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

    color:
        var(--navy);

    background:#FFD18C;

    font-size:12px;

    font-weight:700;
}


/* =========================================================
   HERO STATS
   ========================================================= */

.hero-stats {

    position:relative;

    z-index:2;

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:10px;

    padding:17px;

    border-radius:15px;

    background:
        rgba(255,255,255,0.11);

    border:
        1px solid
        rgba(255,255,255,0.15);
}


.hero-stat {

    padding:13px;

    text-align:center;

    border-radius:11px;

    background:
        rgba(255,255,255,0.07);
}


.hero-stat i {

    color:#FFD18C;

    font-size:17px;
}


.hero-stat strong {

    display:block;

    margin-top:5px;

    font-size:22px;
}


.hero-stat span {

    display:block;

    margin-top:2px;

    color:
        rgba(255,255,255,0.72);

    font-size:10px;
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

    min-height:120px;

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
        var(--navy);
}


.card:nth-child(1)
.card-icon {

    color:
        var(--navy);

    background:#E9EBF1;
}


.card:nth-child(2) {

    border-top:
        4px solid
        var(--red);
}


.card:nth-child(2)
.card-icon {

    color:
        var(--red);

    background:
        var(--red-soft);
}


.card:nth-child(3) {

    border-top:
        4px solid
        var(--blue);
}


.card:nth-child(3)
.card-icon {

    color:
        var(--blue);

    background:
        var(--blue-soft);
}


.card:nth-child(4) {

    border-top:
        4px solid
        var(--green);
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
}


.card h2 {

    margin-top:4px;

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

    font-weight:650;
}


.message.success {

    color:#17684D;

    background:
        var(--green-soft);
}


.message.error {

    color:#A64048;

    background:
        var(--red-soft);
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

    gap:18px;

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

    display:flex;

    align-items:center;

    justify-content:center;

    flex-shrink:0;

    border-radius:11px;

    color:
        var(--red);

    background:
        var(--red-soft);

    font-size:17px;
}


.panel-head h2 {

    font-size:20px;
}


.panel-head p {

    margin-top:3px;

    color:
        var(--muted);

    font-size:13px;
}


/* =========================================================
   FILTERS
   ========================================================= */

.filters {

    display:flex;

    gap:9px;

    align-items:center;
}


.search {

    min-width:245px;

    padding:
        10px 12px;

    border:
        1px solid
        #D7E1E6;

    border-radius:9px;

    outline:none;

    background:#F8FAFB;

    font-size:12px;
}


.filter {

    padding:
        10px 11px;

    border:
        1px solid
        #D7E1E6;

    border-radius:9px;

    outline:none;

    background:#F8FAFB;

    color:
        var(--text);

    font-size:12px;
}


/* =========================================================
   SOS LIST
   ========================================================= */

.alert-list {

    padding:18px;
}


.alert-card {

    display:grid;

    grid-template-columns:
        72px
        minmax(0,1fr)
        250px;

    gap:16px;

    align-items:start;

    margin-bottom:15px;

    padding:15px;

    border-radius:14px;

    border:
        1px solid
        #E1E8EC;

    background:#FBFCFD;
}


.alert-card:last-child {

    margin-bottom:0;
}


.alert-card[data-status="active"] {

    border-left:
        4px solid
        var(--red);
}


.alert-card[data-status="responding"] {

    border-left:
        4px solid
        var(--blue);
}


.alert-card[data-status="resolved"] {

    border-left:
        4px solid
        var(--green);
}


/* =========================================================
   ALERT ICON
   ========================================================= */

.emergency-icon {

    width:64px;
    height:64px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:15px;

    color:
        var(--red);

    background:
        var(--red-soft);

    font-size:23px;
}


/* =========================================================
   ALERT DETAILS
   ========================================================= */

.alert-top {

    display:flex;

    justify-content:space-between;

    align-items:flex-start;

    gap:12px;
}


.alert-title {

    font-size:17px;

    font-weight:800;
}


.alert-id {

    margin-top:3px;

    color:
        var(--muted);

    font-size:10px;
}


.status {

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:
        7px 10px;

    border-radius:20px;

    white-space:nowrap;

    font-size:10px;

    font-weight:700;
}


.status.active {

    color:#A63E48;

    background:
        var(--red-soft);
}


.status.responding {

    color:#306F9E;

    background:
        var(--blue-soft);
}


.status.resolved {

    color:#267A5D;

    background:
        var(--green-soft);
}


/* =========================================================
   STUDENT
   ========================================================= */

.student {

    display:flex;

    align-items:center;

    gap:9px;

    margin-top:12px;
}


.student-avatar {

    width:37px;
    height:37px;

    display:flex;

    align-items:center;

    justify-content:center;

    flex-shrink:0;

    border-radius:9px;

    color:white;

    background:
        linear-gradient(
            135deg,
            var(--navy),
            var(--teal)
        );

    font-size:12px;

    font-weight:800;
}


.student strong {

    display:block;

    font-size:12px;
}


.student span {

    display:block;

    margin-top:2px;

    color:
        var(--muted);

    font-size:10px;
}


/* =========================================================
   META
   ========================================================= */

.alert-meta {

    display:flex;

    flex-wrap:wrap;

    gap:8px 15px;

    margin-top:12px;

    color:
        var(--muted);

    font-size:11px;
}


.alert-meta span {

    display:flex;

    align-items:center;

    gap:5px;
}


.alert-message {

    margin-top:11px;

    padding:11px;

    border-radius:9px;

    color:#536575;

    background:white;

    border:
        1px solid
        #E5EAED;

    font-size:12px;

    line-height:1.5;
}


/* =========================================================
   WARDEN UPDATE
   ========================================================= */

.update-box {

    padding:13px;

    border-radius:12px;

    background:white;

    border:
        1px solid
        #E2E9ED;
}


.update-box h4 {

    margin-bottom:9px;

    font-size:12px;
}


.update-box label {

    display:block;

    margin:
        9px 0 5px;

    color:#506173;

    font-size:10px;

    font-weight:700;
}


.control {

    width:100%;

    padding:
        9px 10px;

    border:
        1px solid
        #D9E3E8;

    border-radius:8px;

    outline:none;

    background:#F9FBFC;

    color:
        var(--text);

    font-size:11px;
}


textarea.control {

    min-height:70px;

    resize:vertical;
}


.update-btn {

    width:100%;

    margin-top:10px;

    padding:10px;

    border:none;

    border-radius:8px;

    color:white;

    background:
        linear-gradient(
            135deg,
            var(--teal),
            var(--teal2)
        );

    cursor:pointer;

    font-size:11px;

    font-weight:700;
}


/* =========================================================
   ACTIVE WARNING
   ========================================================= */

.live-warning {

    display:flex;

    align-items:center;

    gap:10px;

    margin-bottom:20px;

    padding:14px 16px;

    border-radius:12px;

    color:#A53D47;

    background:
        var(--red-soft);

    border:
        1px solid
        #F2CDD0;

    font-size:13px;

    font-weight:650;
}


.live-dot {

    width:10px;
    height:10px;

    flex-shrink:0;

    border-radius:50%;

    background:
        var(--red);

    box-shadow:
        0 0 0 5px
        rgba(231,104,113,0.14);
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


.empty i {

    margin-bottom:12px;

    color:
        var(--teal);

    font-size:35px;
}


.empty h3 {

    color:
        var(--text);

    font-size:17px;
}


.empty p {

    margin-top:5px;

    font-size:12px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media(max-width:1250px) {

    .hero {

        grid-template-columns:1fr;
    }


    .hero-stats {

        max-width:440px;
    }


    .alert-card {

        grid-template-columns:
            65px
            minmax(0,1fr);
    }


    .update-box {

        grid-column:
            1 / -1;
    }
}


@media(max-width:1050px) {

    .cards {

        grid-template-columns:
            repeat(2,1fr);
    }


    .panel-head {

        align-items:flex-start;

        flex-direction:column;
    }


    .filters {

        width:100%;
    }


    .search {

        flex:1;

        min-width:0;
    }
}


@media(max-width:700px) {

    .sidebar {

        display:none;
    }


    .main {

        padding:18px;
    }


    .profile > div:last-child {

        display:none;
    }
}


@media(max-width:550px) {

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


    .filters {

        flex-direction:column;

        align-items:stretch;
    }


    .search,
    .filter {

        width:100%;
    }


    .alert-card {

        grid-template-columns:1fr;
    }

}

</style>

</head>


<body>


<div class="dashboard">


<?php include "sidebar.php"; ?>


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
Emergency SOS
</h1>

<p>
Monitor and respond to student emergency alerts.
</p>

</div>


</div>



<div class="profile">


<div class="profile-avatar">

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
Emergency Response Centre
</div>


<h2>
Respond quickly when students need help.
</h2>


<p>

View SOS alerts raised by hostel residents,
check their location and emergency details,
and track each case until it is resolved.

</p>


<div class="hero-chip">

<i class="fa-solid fa-shield-heart"></i>

Active → Responding → Resolved

</div>


</div>



<div class="hero-stats">


<div class="hero-stat">

<i class="fa-solid fa-list"></i>

<strong>
<?php echo $totalAlerts; ?>
</strong>

<span>
Total Alerts
</span>

</div>


<div class="hero-stat">

<i class="fa-solid fa-bell"></i>

<strong>
<?php echo $activeAlerts; ?>
</strong>

<span>
Active
</span>

</div>


<div class="hero-stat">

<i class="fa-solid fa-person-running"></i>

<strong>
<?php echo $respondingAlerts; ?>
</strong>

<span>
Responding
</span>

</div>


<div class="hero-stat">

<i class="fa-solid fa-circle-check"></i>

<strong>
<?php echo $resolvedAlerts; ?>
</strong>

<span>
Resolved
</span>

</div>


</div>


</div>



<!-- =========================================================
     SUMMARY CARDS
     ========================================================= -->

<div class="cards">


<div class="card">


<div class="card-icon">

<i class="fa-solid fa-list"></i>

</div>


<div>

<p>
Total Alerts
</p>

<h2>
<?php echo $totalAlerts; ?>
</h2>

</div>


</div>



<div class="card">


<div class="card-icon">

<i class="fa-solid fa-bell"></i>

</div>


<div>

<p>
Active
</p>

<h2>
<?php echo $activeAlerts; ?>
</h2>

</div>


</div>



<div class="card">


<div class="card-icon">

<i class="fa-solid fa-person-running"></i>

</div>


<div>

<p>
Responding
</p>

<h2>
<?php echo $respondingAlerts; ?>
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
<?php echo $resolvedAlerts; ?>
</h2>

</div>


</div>


</div>



<!-- =========================================================
     ACTIVE WARNING
     ========================================================= -->

<?php if ($activeAlerts > 0) { ?>


<div class="live-warning">

<div class="live-dot"></div>

<strong>
<?php echo $activeAlerts; ?>
active SOS alert<?php echo ($activeAlerts > 1) ? "s" : ""; ?>
</strong>

currently require attention.

</div>


<?php } ?>



<!-- =========================================================
     MESSAGE
     ========================================================= -->

<?php if ($message !== "") { ?>


<div
    id="flashMessage"
    class="message <?php echo htmlspecialchars($messageType); ?>"
>


<?php

if ($messageType === "success") {

    echo '<i class="fa-solid fa-circle-check"></i>';

} else {

    echo '<i class="fa-solid fa-circle-exclamation"></i>';
}

?>


<?php
echo htmlspecialchars(
    $message
);
?>


</div>


<?php } ?>



<!-- =========================================================
     ALERT PANEL
     ========================================================= -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">


<div class="panel-icon">

<i class="fa-solid fa-satellite-dish"></i>

</div>


<div>

<h2>
Student SOS Alerts
</h2>

<p>
Review emergency details and update response status.
</p>

</div>


</div>



<div class="filters">


<input
    type="text"
    id="searchBox"
    class="search"
    placeholder="Search student, room, location..."
>


<select
    id="typeFilter"
    class="filter"
>


<option value="all">
All Types
</option>


<option value="medical">
Medical
</option>


<option value="security">
Security
</option>


<option value="fire">
Fire
</option>


<option value="accident">
Accident
</option>


<option value="harassment">
Harassment
</option>


<option value="other">
Other
</option>


</select>



<select
    id="statusFilter"
    class="filter"
>


<option value="all">
All Status
</option>


<option value="active">
Active
</option>


<option value="responding">
Responding
</option>


<option value="resolved">
Resolved
</option>


</select>


</div>


</div>



<?php

if (
    $alerts &&
    mysqli_num_rows(
        $alerts
    ) > 0
) {

?>


<div class="alert-list">


<?php

while (
    $alert =
    mysqli_fetch_assoc(
        $alerts
    )
) {

    $statusClass =
        strtolower(
            $alert["status"]
        );

    $typeClass =
        strtolower(
            $alert["emergency_type"]
        );

?>


<div
    class="alert-card"
    data-type="<?php echo htmlspecialchars($typeClass); ?>"
    data-status="<?php echo htmlspecialchars($statusClass); ?>"
>


<!-- ICON -->

<div class="emergency-icon">


<i
    class="fa-solid <?php
    echo getEmergencyIcon(
        $alert["emergency_type"]
    );
    ?>"
></i>


</div>



<!-- DETAILS -->

<div>


<div class="alert-top">


<div>


<div class="alert-title">

<?php
echo htmlspecialchars(
    $alert["emergency_type"]
);
?>

Emergency

</div>


<div class="alert-id">

SOS ID #<?php echo intval($alert["id"]); ?>

</div>


</div>



<span
    class="status <?php echo $statusClass; ?>"
>


<?php

if ($alert["status"] === "Active") {

    echo '<i class="fa-solid fa-bell"></i>';

} elseif ($alert["status"] === "Responding") {

    echo '<i class="fa-solid fa-person-running"></i>';

} else {

    echo '<i class="fa-solid fa-circle-check"></i>';
}

?>


<?php
echo htmlspecialchars(
    $alert["status"]
);
?>


</span>


</div>



<!-- STUDENT -->

<div class="student">


<div class="student-avatar">

<?php
echo getInitial(
    $alert["student_name"]
);
?>

</div>


<div>


<strong>

<?php
echo htmlspecialchars(
    $alert["student_name"]
);
?>

</strong>


<span>

<?php
echo htmlspecialchars(
    $alert["student_email"]
);
?>


<?php

if (
    !empty(
        $alert["room_number"]
    )
) {

    echo " • Room ";

    echo htmlspecialchars(
        $alert["room_number"]
    );
}

?>


</span>


</div>


</div>



<!-- META -->

<div class="alert-meta">


<span>

<i class="fa-solid fa-location-dot"></i>

<?php
echo htmlspecialchars(
    $alert["location"]
);
?>

</span>



<span>

<i class="fa-regular fa-calendar"></i>

<?php

echo date(
    "d M Y",
    strtotime(
        $alert["created_at"]
    )
);

?>

</span>



<span>

<i class="fa-regular fa-clock"></i>

<?php

echo date(
    "h:i A",
    strtotime(
        $alert["created_at"]
    )
);

?>

</span>


</div>



<div class="alert-message">

<strong>
Student Message:
</strong>

<br>

<?php

echo nl2br(
    htmlspecialchars(
        $alert["message"]
    )
);

?>

</div>



<?php

if (
    $alert["status"] === "Resolved" &&
    !empty(
        $alert["resolved_at"]
    )
) {

?>


<div
    style="
        margin-top:9px;
        color:#718092;
        font-size:10px;
    "
>

<i class="fa-solid fa-circle-check"></i>

Resolved on

<?php

echo date(
    "d M Y, h:i A",
    strtotime(
        $alert["resolved_at"]
    )
);

?>

</div>


<?php } ?>


</div>



<!-- =========================================================
     WARDEN UPDATE
     ========================================================= -->

<div class="update-box">


<h4>

<i class="fa-solid fa-user-shield"></i>

Warden Response

</h4>


<form method="POST">


<input
    type="hidden"
    name="action"
    value="update_alert"
>


<input
    type="hidden"
    name="alert_id"
    value="<?php echo intval($alert["id"]); ?>"
>


<label>
Status
</label>


<select
    name="status"
    class="control"
    required
>


<option
    value="Active"
    <?php
    echo (
        $alert["status"] === "Active"
    )
        ? "selected"
        : "";
    ?>
>

Active

</option>


<option
    value="Responding"
    <?php
    echo (
        $alert["status"] === "Responding"
    )
        ? "selected"
        : "";
    ?>
>

Responding

</option>


<option
    value="Resolved"
    <?php
    echo (
        $alert["status"] === "Resolved"
    )
        ? "selected"
        : "";
    ?>
>

Resolved

</option>


</select>



<label>
Remark
</label>


<textarea
    name="warden_remark"
    class="control"
    placeholder="Example: Warden is on the way to Room A-101."
><?php
echo htmlspecialchars(
    $alert["warden_remark"] ?? ""
);
?></textarea>



<button
    type="submit"
    class="update-btn"
>


<i class="fa-solid fa-floppy-disk"></i>

Update SOS


</button>


</form>


</div>


</div>


<?php } ?>


</div>



<div
    id="noResults"
    class="empty"
    style="display:none;"
>


<i class="fa-solid fa-magnifying-glass"></i>

<h3>
No matching SOS alerts
</h3>

<p>
Try changing the search or filter.
</p>


</div>


<?php

} else {

?>


<div class="empty">


<i class="fa-solid fa-shield-heart"></i>

<h3>
No SOS alerts yet
</h3>

<p>
Emergency alerts raised by students will appear here.
</p>


</div>


<?php } ?>


</div>


</div>


</div>



<script>

/* =========================================================
   SEARCH + FILTER
   ========================================================= */

const searchBox =
    document.getElementById(
        "searchBox"
    );


const typeFilter =
    document.getElementById(
        "typeFilter"
    );


const statusFilter =
    document.getElementById(
        "statusFilter"
    );


function filterAlerts() {

    const search =
        searchBox
        ?
        searchBox.value
        .toLowerCase()
        .trim()
        :
        "";


    const type =
        typeFilter
        ?
        typeFilter.value
        :
        "all";


    const status =
        statusFilter
        ?
        statusFilter.value
        :
        "all";


    const cards =
        document.querySelectorAll(
            ".alert-card"
        );


    let visible =
        0;


    cards.forEach(
        function(card) {

            const text =
                card.innerText
                .toLowerCase();


            const cardType =
                card.dataset.type;


            const cardStatus =
                card.dataset.status;


            const searchMatch =
                text.includes(
                    search
                );


            const typeMatch =
                type === "all" ||
                cardType === type;


            const statusMatch =
                status === "all" ||
                cardStatus === status;


            if (
                searchMatch &&
                typeMatch &&
                statusMatch
            ) {

                card.style.display =
                    "grid";

                visible++;

            } else {

                card.style.display =
                    "none";
            }
        }
    );


    const noResults =
        document.getElementById(
            "noResults"
        );


    if (noResults) {

        noResults.style.display =
            visible === 0
            ? "block"
            : "none";
    }
}


if (searchBox) {

    searchBox.addEventListener(
        "input",
        filterAlerts
    );
}


if (typeFilter) {

    typeFilter.addEventListener(
        "change",
        filterAlerts
    );
}


if (statusFilter) {

    statusFilter.addEventListener(
        "change",
        filterAlerts
    );
}


/* =========================================================
   FLASH MESSAGE
   ========================================================= */

const flashMessage =
    document.getElementById(
        "flashMessage"
    );


if (flashMessage) {

    setTimeout(
        function() {

            flashMessage.style.opacity =
                "0";

            flashMessage.style.transition =
                "opacity 0.4s ease";

        },
        3500
    );
}

</script>


</body>

</html>
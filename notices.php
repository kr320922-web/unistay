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

$wardenId = $_SESSION["user_id"];
$wardenName = $_SESSION["user_name"];

$success = "";
$error = "";


/* =========================================================
   ADD NOTICE
   ========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] == "POST" &&
    isset($_POST["add_notice"])
) {

    $title =
        trim($_POST["title"] ?? "");

    $description =
        trim($_POST["description"] ?? "");

    $noticeDate =
        trim($_POST["notice_date"] ?? "");


    if (
        empty($title) ||
        empty($description) ||
        empty($noticeDate)
    ) {

        $error =
            "Please fill all fields.";

    } else {

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO notices
            (
                title,
                description,
                notice_date,
                created_by
            )
            VALUES (?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "sssi",
            $title,
            $description,
            $noticeDate,
            $wardenId
        );


        if (mysqli_stmt_execute($stmt)) {

            $success =
                "Notice posted successfully.";

        } else {

            $error =
                "Unable to post notice.";
        }
    }
}


/* =========================================================
   DELETE NOTICE
   ========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] == "POST" &&
    isset($_POST["delete_notice"])
) {

    $noticeId =
        intval($_POST["notice_id"] ?? 0);


    if ($noticeId > 0) {

        $stmt = mysqli_prepare(
            $conn,
            "DELETE FROM notices
             WHERE id = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $noticeId
        );


        if (mysqli_stmt_execute($stmt)) {

            $success =
                "Notice deleted successfully.";

        } else {

            $error =
                "Unable to delete notice.";
        }
    }
}


/* =========================================================
   SUMMARY COUNTS
   ========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM notices"
);

$totalNotices =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM notices
     WHERE notice_date = CURDATE()"
);

$todayNotices =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM notices
     WHERE YEAR(notice_date) = YEAR(CURDATE())
     AND MONTH(notice_date) = MONTH(CURDATE())"
);

$monthNotices =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


/* =========================================================
   GET ALL NOTICES
   ========================================================= */

$notices = mysqli_query(
    $conn,
    "SELECT
        notices.id,
        notices.title,
        notices.description,
        notices.notice_date,
        notices.created_at,
        users.name AS creator_name

     FROM notices

     LEFT JOIN users
     ON notices.created_by = users.id

     ORDER BY
        notices.notice_date DESC,
        notices.id DESC"
);


/* =========================================================
   HELPERS
   ========================================================= */

function getInitial($name)
{
    $name = trim($name);

    if ($name == "") {
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

<title>
Notices - UniStay
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

    transition:0.18s ease;
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
   WARDEN
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
        320px;

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

    right:-110px;
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


/* HERO ART */

.hero-art {

    position:relative;

    z-index:2;

    min-height:145px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:18px;

    background:
        rgba(255,255,255,0.11);

    border:
        1px solid
        rgba(255,255,255,0.15);
}

.hero-art-circle {

    width:92px;
    height:92px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:50%;

    color:
        var(--navy);

    background:
        var(--mustard);

    font-size:38px;

    box-shadow:
        0 12px 24px
        rgba(0,0,0,0.16);
}

.hero-art-badge {

    position:absolute;

    right:18px;
    bottom:17px;

    display:flex;

    align-items:center;

    gap:6px;

    padding:
        7px 11px;

    border-radius:20px;

    background:white;

    color:
        var(--teal);

    font-size:11px;

    font-weight:700;
}


/* =========================================================
   SUMMARY
   ========================================================= */

.summary-grid {

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:15px;

    margin-bottom:20px;
}

.summary-card {

    min-height:122px;

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

.summary-card:nth-child(1) {

    border-top:
        4px solid
        var(--teal);
}

.summary-card:nth-child(2) {

    border-top:
        4px solid
        var(--mustard);
}

.summary-card:nth-child(3) {

    border-top:
        4px solid
        var(--blue);
}

.summary-icon {

    width:50px;
    height:50px;

    flex-shrink:0;

    display:flex;
    align-items:center;
    justify-content:center;

    border-radius:13px;

    font-size:19px;
}

.summary-card:nth-child(1)
.summary-icon {

    color:
        var(--teal);

    background:
        var(--teal-soft);
}

.summary-card:nth-child(2)
.summary-icon {

    color:#986507;

    background:
        var(--mustard-soft);
}

.summary-card:nth-child(3)
.summary-icon {

    color:
        var(--blue);

    background:
        var(--blue-soft);
}

.summary-label {

    color:
        var(--muted);

    font-size:12px;

    text-transform:uppercase;

    letter-spacing:0.4px;
}

.summary-value {

    margin-top:4px;

    color:
        var(--text);

    font-size:28px;

    font-weight:800;
}


/* =========================================================
   CONTENT
   ========================================================= */

.content-grid {

    display:grid;

    grid-template-columns:
        minmax(340px,0.85fr)
        minmax(0,1.4fr);

    gap:20px;

    align-items:start;
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

.notice-count {

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:
        7px 11px;

    border-radius:20px;

    color:#946506;

    background:
        var(--mustard-soft);

    font-size:12px;

    font-weight:700;
}


/* =========================================================
   MESSAGE
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
   FORM
   ========================================================= */

.notice-form {

    padding:20px;
}

.form-group {

    margin-bottom:17px;
}

.form-group label {

    display:flex;

    align-items:center;

    gap:8px;

    margin-bottom:7px;

    color:#344557;

    font-size:13px;

    font-weight:700;
}

.form-group label i {

    width:17px;

    text-align:center;
}

.form-group:nth-child(1)
label i {

    color:
        var(--teal);
}

.form-group:nth-child(2)
label i {

    color:#AD760B;
}

.form-group:nth-child(3)
label i {

    color:
        var(--blue);
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

    border-color:
        #59A0AE;

    background:white;

    box-shadow:
        0 0 0 3px
        rgba(14,96,119,0.08);
}

textarea.form-control {

    min-height:145px;

    resize:vertical;

    line-height:1.55;

    border-left:
        4px solid
        var(--blue);
}

.form-note {

    display:flex;

    align-items:flex-start;

    gap:9px;

    margin-bottom:17px;

    padding:12px;

    border-radius:9px;

    color:#536575;

    background:
        linear-gradient(
            90deg,
            #EEF7F8,
            #FFF8E8
        );

    border:
        1px solid
        #E0EAE9;

    font-size:12px;

    line-height:1.5;
}

.form-note i {

    margin-top:2px;

    color:#A97209;
}

.submit-btn {

    width:100%;

    display:flex;

    align-items:center;
    justify-content:center;

    gap:8px;

    border:none;

    padding:13px;

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

.submit-btn:hover {

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
   NOTICE LIST
   ========================================================= */

.notice-list {

    max-height:820px;

    overflow-y:auto;

    padding:16px;
}

.notice-list::-webkit-scrollbar {

    width:6px;
}

.notice-list::-webkit-scrollbar-thumb {

    background:#C8D7DC;

    border-radius:20px;
}

.notice-card {

    position:relative;

    overflow:hidden;

    margin-bottom:15px;

    padding:18px;

    border-radius:14px;

    background:#FBFCFD;

    border:
        1px solid
        #E4EBEE;

    transition:0.18s ease;
}

.notice-card:last-child {

    margin-bottom:0;
}

.notice-card:hover {

    transform:
        translateY(-1px);

    box-shadow:
        0 7px 20px
        rgba(31,39,71,0.05);
}

.notice-card::before {

    content:"";

    position:absolute;

    top:0;
    bottom:0;
    left:0;

    width:5px;

    background:
        linear-gradient(
            to bottom,
            var(--teal),
            var(--blue)
        );
}


/* =========================================================
   NOTICE TOP
   ========================================================= */

.notice-top {

    display:flex;

    align-items:flex-start;
    justify-content:space-between;

    gap:15px;

    margin-bottom:13px;
}

.notice-title-wrap {

    display:flex;

    align-items:flex-start;

    gap:11px;
}

.notice-symbol {

    width:40px;
    height:40px;

    flex-shrink:0;

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

.notice-title {

    color:
        var(--text);

    font-size:17px;

    font-weight:750;

    line-height:1.4;
}

.notice-id {

    margin-top:3px;

    color:#8C97A3;

    font-size:11px;
}

.notice-date {

    display:inline-flex;

    align-items:center;

    gap:6px;

    flex-shrink:0;

    padding:
        7px 10px;

    border-radius:20px;

    color:#946506;

    background:
        var(--mustard-soft);

    font-size:11px;

    font-weight:700;
}


/* =========================================================
   DESCRIPTION
   ========================================================= */

.notice-description {

    margin:
        0 0 15px 51px;

    color:#566779;

    font-size:13px;

    line-height:1.65;
}


/* =========================================================
   NOTICE FOOTER
   ========================================================= */

.notice-bottom {

    display:flex;

    align-items:center;
    justify-content:space-between;

    gap:15px;

    margin-left:51px;

    padding-top:13px;

    border-top:
        1px solid
        #E6ECEF;
}

.notice-author {

    display:flex;

    align-items:center;

    gap:9px;

    color:
        var(--muted);

    font-size:12px;
}

.author-icon {

    width:30px;
    height:30px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:8px;

    color:
        var(--blue);

    background:
        var(--blue-soft);

    font-size:11px;
}

.notice-author strong {

    color:
        var(--text);
}

.created-time {

    display:block;

    margin-top:2px;

    color:#8994A0;

    font-size:11px;
}

.delete-btn {

    display:flex;

    align-items:center;
    justify-content:center;

    gap:6px;

    border:none;

    padding:
        9px 12px;

    border-radius:9px;

    color:
        #AB424A;

    background:
        var(--red-soft);

    cursor:pointer;

    font-size:12px;

    font-weight:700;

    transition:0.18s ease;
}

.delete-btn:hover {

    background:#F7DADC;

    transform:
        translateY(-1px);
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
            1fr 260px;
    }

    .content-grid {

        grid-template-columns:
            1fr;
    }
}

@media(max-width:900px) {

    .summary-grid {

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

    .hero-art {

        max-width:350px;
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

    .notice-top {

        flex-direction:column;
    }

    .notice-description,
    .notice-bottom {

        margin-left:0;
    }

    .notice-bottom {

        align-items:flex-start;

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

<i class="fa-solid fa-bullhorn"></i>

</div>


<div>

<h1>
Notices
</h1>

<p>
Post hostel announcements and updates for students.
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
Hostel Communication
</div>


<h2>
One notice.
Everyone stays informed. 📢
</h2>


<p>

Share important hostel announcements, maintenance
updates, schedules and reminders directly with students
through their UniStay portal.

</p>


<div class="hero-chip">

<i class="fa-solid fa-paper-plane"></i>

Instant Student Updates

</div>


</div>



<div class="hero-art">


<div class="hero-art-circle">

<i class="fa-solid fa-bullhorn"></i>

</div>


<div class="hero-art-badge">

<i class="fa-solid fa-users"></i>

Student Notice Board

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
Total Notices
</div>

<div class="summary-value">

<?php echo $totalNotices; ?>

</div>

</div>


</div>



<div class="summary-card">


<div class="summary-icon">

<i class="fa-solid fa-calendar-day"></i>

</div>


<div>

<div class="summary-label">
Posted Today
</div>

<div class="summary-value">

<?php echo $todayNotices; ?>

</div>

</div>


</div>



<div class="summary-card">


<div class="summary-icon">

<i class="fa-solid fa-calendar"></i>

</div>


<div>

<div class="summary-label">
This Month
</div>

<div class="summary-value">

<?php echo $monthNotices; ?>

</div>

</div>


</div>


</div>



<!-- =========================================================
     CONTENT
     ========================================================= -->

<div class="content-grid">


<!-- =========================================================
     ADD NOTICE
     ========================================================= -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">


<div class="panel-icon">

<i class="fa-solid fa-pen-to-square"></i>

</div>


<div>

<h2>
Post New Notice
</h2>

<p>
Create an announcement for students.
</p>

</div>


</div>


</div>



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



<form
    method="POST"
    class="notice-form"
>


<div class="form-group">


<label>

<i class="fa-solid fa-heading"></i>

Notice Title

</label>


<input
    type="text"
    name="title"
    class="form-control"
    placeholder="Example: Hostel Maintenance"
    required
>


</div>



<div class="form-group">


<label>

<i class="fa-regular fa-calendar"></i>

Notice Date

</label>


<input
    type="date"
    name="notice_date"
    class="form-control"
    value="<?php echo date('Y-m-d'); ?>"
    required
>


</div>



<div class="form-group">


<label>

<i class="fa-solid fa-align-left"></i>

Description

</label>


<textarea
    name="description"
    class="form-control"
    placeholder="Write notice details here..."
    required
></textarea>


</div>



<div class="form-note">


<i class="fa-solid fa-circle-info"></i>


<div>

Once posted, this notice will become available
to students in their UniStay Notices section.

</div>


</div>



<button
    type="submit"
    name="add_notice"
    class="submit-btn"
>

<i class="fa-solid fa-paper-plane"></i>

Post Notice

</button>


</form>


</div>



<!-- =========================================================
     POSTED NOTICES
     ========================================================= -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">


<div class="panel-icon">

<i class="fa-solid fa-bullhorn"></i>

</div>


<div>

<h2>
Posted Notices
</h2>

<p>
Recent hostel announcements and updates.
</p>

</div>


</div>



<div class="notice-count">

<i class="fa-solid fa-list"></i>

<?php echo $totalNotices; ?> Notices

</div>


</div>



<div class="notice-list">


<?php

if (
    $notices &&
    mysqli_num_rows($notices) > 0
) {


    while (
        $notice =
        mysqli_fetch_assoc($notices)
    ) {

?>


<div class="notice-card">


<div class="notice-top">


<div class="notice-title-wrap">


<div class="notice-symbol">

<i class="fa-solid fa-bell"></i>

</div>


<div>


<div class="notice-title">

<?php
echo htmlspecialchars(
    $notice["title"]
);
?>

</div>


<div class="notice-id">

Notice #<?php echo intval($notice["id"]); ?>

</div>


</div>


</div>



<div class="notice-date">

<i class="fa-regular fa-calendar"></i>

<?php

echo date(
    "d M Y",
    strtotime(
        $notice["notice_date"]
    )
);

?>

</div>


</div>



<div class="notice-description">

<?php

echo nl2br(
    htmlspecialchars(
        $notice["description"]
    )
);

?>

</div>



<div class="notice-bottom">


<div class="notice-author">


<div class="author-icon">

<i class="fa-solid fa-user-shield"></i>

</div>


<div>

Posted by

<strong>

<?php

echo htmlspecialchars(
    $notice["creator_name"]
    ?? "Warden"
);

?>

</strong>


<div class="created-time">

<?php

if (
    !empty(
        $notice["created_at"]
    )
) {

    echo date(
        "d M Y, h:i A",
        strtotime(
            $notice["created_at"]
        )
    );
}

?>

</div>


</div>


</div>



<form
    method="POST"
    onsubmit="
        return confirm(
            'Delete this notice?'
        );
    "
>


<input
    type="hidden"
    name="notice_id"
    value="<?php echo intval($notice["id"]); ?>"
>


<button
    type="submit"
    name="delete_notice"
    class="delete-btn"
>

<i class="fa-solid fa-trash"></i>

Delete

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

<i class="fa-solid fa-bullhorn"></i>

</div>


<h3>
No notices posted yet
</h3>


<p>
New hostel announcements will appear here.
</p>


</div>


<?php } ?>


</div>


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
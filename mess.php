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

$message = "";
$messageType = "";


/* =========================================================
   FOOD IMAGES
   ========================================================= */

$breakfastImage =
    "https://images.pexels.com/photos/35539315/pexels-photo-35539315/free-photo-of-traditional-south-indian-breakfast-spread.jpeg?auto=compress&dpr=1&h=750&w=1260";

$lunchImage =
    "https://images.pexels.com/photos/35539324/pexels-photo-35539324/free-photo-of-authentic-indian-thali-with-variety-of-dishes.jpeg?auto=compress&dpr=1&h=750&w=1260";

$snacksImage =
    "https://images.pexels.com/photos/23266987/pexels-photo-23266987/free-photo-of-traditional-desi-pakora-with-mint-and-green-sauces-popular-pakistani-snack.jpeg?auto=compress&dpr=1&h=750&w=1260";

$dinnerImage =
    "https://images.pexels.com/photos/8818723/pexels-photo-8818723.jpeg?auto=compress&cs=tinysrgb&w=1000";


/* =========================================================
   ADD / UPDATE MENU
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $menuDate =
        trim($_POST["menu_date"] ?? "");

    $breakfast =
        trim($_POST["breakfast"] ?? "");

    $lunch =
        trim($_POST["lunch"] ?? "");

    $snacks =
        trim($_POST["snacks"] ?? "");

    $dinner =
        trim($_POST["dinner"] ?? "");


    if (
        $menuDate == "" ||
        $breakfast == "" ||
        $lunch == "" ||
        $snacks == "" ||
        $dinner == ""
    ) {

        $message =
            "Please fill all menu details.";

        $messageType =
            "error";

    } else {

        $stmt =
            mysqli_prepare(
                $conn,
                "INSERT INTO mess_menu
                (
                    menu_date,
                    breakfast,
                    lunch,
                    snacks,
                    dinner
                )
                VALUES (?, ?, ?, ?, ?)

                ON DUPLICATE KEY UPDATE

                    breakfast = VALUES(breakfast),
                    lunch = VALUES(lunch),
                    snacks = VALUES(snacks),
                    dinner = VALUES(dinner)"
            );


        mysqli_stmt_bind_param(
            $stmt,
            "sssss",
            $menuDate,
            $breakfast,
            $lunch,
            $snacks,
            $dinner
        );


        if (
            mysqli_stmt_execute($stmt)
        ) {

            $message =
                "Mess menu saved successfully!";

            $messageType =
                "success";

        } else {

            $message =
                "Unable to save mess menu.";

            $messageType =
                "error";
        }
    }
}


/* =========================================================
   SUMMARY
   ========================================================= */

$totalResult =
    mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM mess_menu"
    );

$totalMenus =
    intval(
        mysqli_fetch_assoc(
            $totalResult
        )["total"]
    );


$todayResult =
    mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM mess_menu
         WHERE menu_date = CURDATE()"
    );

$todayMenu =
    intval(
        mysqli_fetch_assoc(
            $todayResult
        )["total"]
    );


$upcomingResult =
    mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM mess_menu
         WHERE menu_date > CURDATE()"
    );

$upcomingMenus =
    intval(
        mysqli_fetch_assoc(
            $upcomingResult
        )["total"]
    );


/* =========================================================
   GET MENUS
   ========================================================= */

$menus =
    mysqli_query(
        $conn,
        "SELECT *
         FROM mess_menu
         ORDER BY menu_date DESC
         LIMIT 15"
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
Mess Management - UniStay
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

    --orange:#E98C3A;
    --orange-soft:#FDEADB;

    --blue:#4387A5;
    --blue-soft:#E5F0F5;

    --green:#36A878;
    --green-soft:#E3F5EC;

    --red:#D9656C;

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
        1px solid var(--border);

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

    letter-spacing:0.3px;
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

    letter-spacing:-0.8px;
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

    color:
        var(--teal);

    background:
        #F0F7F9;
}


.menu a.active {

    color:
        var(--teal);

    background:
        #DDF0F3;

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
        1px solid var(--border);

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

.mess-hero {

    position:relative;

    overflow:hidden;

    min-height:235px;

    display:grid;

    grid-template-columns:
        minmax(0,1fr)
        350px;

    align-items:center;

    gap:30px;

    margin-bottom:20px;

    padding:
        30px 30px 30px 34px;

    border-radius:20px;

    color:white;

    background:
        linear-gradient(
            120deg,
            #1F2747 0%,
            #16465E 35%,
            #0E6077 70%,
            #25899A 100%
        );

    box-shadow:
        0 13px 30px
        rgba(18,71,91,0.15);
}


.mess-hero::before {

    content:"";

    position:absolute;

    width:280px;
    height:280px;

    left:40%;

    bottom:-210px;

    border-radius:50%;

    background:
        rgba(232,178,61,0.12);
}


.hero-text {

    position:relative;

    z-index:2;
}


.hero-label {

    color:
        #F7D77C;

    font-size:12px;

    font-weight:700;

    text-transform:uppercase;

    letter-spacing:1px;
}


.hero-text h2 {

    margin-top:7px;

    max-width:600px;

    font-size:33px;

    letter-spacing:-0.7px;
}


.hero-text p {

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

    color:#29364B;

    background:
        var(--mustard);

    font-size:12px;

    font-weight:750;
}


/* =========================================================
   HERO IMAGE
   ========================================================= */

.hero-food {

    position:relative;

    z-index:2;

    height:180px;

    overflow:hidden;

    border-radius:17px;

    border:
        2px solid
        rgba(255,255,255,0.23);

    box-shadow:
        0 14px 30px
        rgba(5,28,42,0.25);
}


.hero-food img {

    width:100%;
    height:100%;

    display:block;

    object-fit:cover;

    object-position:center;
}


.hero-food::after {

    content:"";

    position:absolute;

    inset:0;

    background:
        linear-gradient(
            to top,
            rgba(18,38,55,0.78),
            rgba(18,38,55,0.05) 65%
        );
}


.hero-image-text {

    position:absolute;

    left:17px;
    right:17px;
    bottom:15px;

    z-index:2;
}


.hero-image-text strong {

    display:block;

    font-size:15px;
}


.hero-image-text span {

    display:block;

    margin-top:3px;

    color:
        rgba(255,255,255,0.83);

    font-size:11px;
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

    min-height:125px;

    display:flex;

    align-items:center;

    gap:15px;

    padding:18px;

    border-radius:14px;

    background:white;

    border:
        1px solid var(--border);

    box-shadow:
        var(--shadow);
}


.summary-card:nth-child(1) {

    border-top:
        4px solid var(--teal);
}


.summary-card:nth-child(2) {

    border-top:
        4px solid var(--mustard);
}


.summary-card:nth-child(3) {

    border-top:
        4px solid var(--blue);
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

    color:#9B6805;

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


.summary-text {

    margin-top:4px;

    color:
        var(--text);

    font-size:19px;

    font-weight:800;
}


/* =========================================================
   MESSAGE
   ========================================================= */

.status-message {

    display:flex;

    align-items:center;

    gap:10px;

    margin-bottom:20px;

    padding:
        14px 16px;

    border-radius:11px;

    font-size:13px;

    font-weight:600;
}


.success {

    color:#17684D;

    background:#E4F6ED;

    border:
        1px solid #C9E9DA;
}


.error {

    color:#A64048;

    background:#FDEDEE;

    border:
        1px solid #F5D3D6;
}


/* =========================================================
   CONTENT
   ========================================================= */

.content-grid {

    display:grid;

    grid-template-columns:
        minmax(360px,0.86fr)
        minmax(0,1.4fr);

    gap:20px;

    align-items:start;
}


/* =========================================================
   PANEL
   ========================================================= */

.panel {

    overflow:hidden;

    border-radius:15px;

    background:white;

    border:
        1px solid var(--border);

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
        1px solid #EDF1F3;
}


.panel-heading {

    display:flex;

    align-items:center;

    gap:11px;
}


.panel-icon {

    width:42px;
    height:42px;

    flex-shrink:0;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:10px;

    color:
        var(--teal);

    background:
        var(--teal-soft);

    font-size:16px;
}


.panel-head h2 {

    color:
        var(--text);

    font-size:19px;
}


.panel-head p {

    margin-top:3px;

    color:
        var(--muted);

    font-size:12px;

    line-height:1.5;
}


.count-pill {

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:
        7px 11px;

    border-radius:20px;

    color:#926109;

    background:
        var(--mustard-soft);

    font-size:11px;

    font-weight:700;
}


/* =========================================================
   FORM
   ========================================================= */

.menu-form {

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

    width:18px;

    text-align:center;
}


.form-group:nth-child(1)
label i {

    color:
        var(--teal);
}


.form-group:nth-child(2)
label i {

    color:#B77C08;
}


.form-group:nth-child(3)
label i {

    color:
        var(--teal);
}


.form-group:nth-child(4)
label i {

    color:
        var(--orange);
}


.form-group:nth-child(5)
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
        1px solid #D7E1E6;

    border-radius:9px;

    background:#FBFCFD;

    color:
        var(--text);

    font-size:13px;

    transition:0.18s ease;
}


.form-control:focus {

    border-color:#59A0AE;

    background:white;

    box-shadow:
        0 0 0 3px
        rgba(14,96,119,0.08);
}


textarea.form-control {

    min-height:82px;

    resize:vertical;

    line-height:1.5;
}


.form-group:nth-child(2)
.form-control {

    border-left:
        4px solid
        var(--mustard);
}


.form-group:nth-child(3)
.form-control {

    border-left:
        4px solid
        var(--teal);
}


.form-group:nth-child(4)
.form-control {

    border-left:
        4px solid
        var(--orange);
}


.form-group:nth-child(5)
.form-control {

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
            #EDF6F7,
            #FFF8E8
        );

    border:
        1px solid #DFE9E7;

    font-size:11px;

    line-height:1.5;
}


.form-note i {

    margin-top:2px;

    color:#AC760B;
}


.save-btn {

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


.save-btn:hover {

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
   MENU LIST
   ========================================================= */

.menu-list {

    max-height:870px;

    overflow-y:auto;

    padding:15px;
}


.menu-list::-webkit-scrollbar {

    width:6px;
}


.menu-list::-webkit-scrollbar-thumb {

    border-radius:20px;

    background:#C9D7DC;
}


.menu-card {

    position:relative;

    overflow:hidden;

    margin-bottom:16px;

    padding:17px;

    border-radius:14px;

    background:#FBFCFD;

    border:
        1px solid #E3EAEE;

    transition:0.18s ease;
}


.menu-card:last-child {

    margin-bottom:0;
}


.menu-card:hover {

    transform:
        translateY(-1px);

    box-shadow:
        0 7px 20px
        rgba(31,39,71,0.05);
}


.menu-card::before {

    content:"";

    position:absolute;

    left:0;

    top:0;

    bottom:0;

    width:5px;

    background:
        linear-gradient(
            to bottom,
            var(--mustard) 0%,
            var(--mustard) 25%,
            var(--teal) 25%,
            var(--teal) 50%,
            var(--orange) 50%,
            var(--orange) 75%,
            var(--blue) 75%,
            var(--blue) 100%
        );
}


/* =========================================================
   MENU DATE
   ========================================================= */

.menu-date {

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    margin-bottom:14px;
}


.date-wrap {

    display:flex;

    align-items:center;

    gap:11px;
}


.date-icon {

    width:42px;
    height:42px;

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


.menu-date h3 {

    color:
        var(--text);

    font-size:15px;
}


.menu-day {

    margin-top:3px;

    color:
        var(--muted);

    font-size:11px;
}


.today-badge {

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:
        6px 11px;

    border-radius:20px;

    color:#8B5E08;

    background:
        var(--mustard-soft);

    font-size:11px;

    font-weight:700;
}


/* =========================================================
   MEALS
   ========================================================= */

.meal-grid {

    display:grid;

    grid-template-columns:
        repeat(2,1fr);

    gap:12px;
}


.meal {

    overflow:hidden;

    min-width:0;

    border-radius:12px;

    background:white;

    border:
        1px solid #E6ECEF;

    box-shadow:
        0 4px 12px
        rgba(31,39,71,0.035);
}


.meal-image {

    position:relative;

    height:130px;

    overflow:hidden;
}


.meal-image img {

    width:100%;
    height:100%;

    display:block;

    object-fit:cover;

    transition:
        transform 0.35s ease;
}


.meal:hover
.meal-image img {

    transform:scale(1.05);
}


.meal-image::after {

    content:"";

    position:absolute;

    inset:0;

    background:
        linear-gradient(
            to top,
            rgba(20,30,45,0.32),
            transparent 65%
        );
}


.meal-body {

    min-height:122px;

    padding:13px;
}


.meal:nth-child(1)
.meal-body {

    background:
        var(--mustard-soft);
}


.meal:nth-child(2)
.meal-body {

    background:
        var(--teal-soft);
}


.meal:nth-child(3)
.meal-body {

    background:
        var(--orange-soft);
}


.meal:nth-child(4)
.meal-body {

    background:
        var(--blue-soft);
}


.meal-top {

    display:flex;

    align-items:center;

    gap:8px;

    margin-bottom:8px;
}


.meal-icon {

    width:33px;
    height:33px;

    flex-shrink:0;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:8px;

    background:
        rgba(255,255,255,0.8);

    font-size:13px;
}


.meal:nth-child(1)
.meal-icon {

    color:#9B6805;
}


.meal:nth-child(2)
.meal-icon {

    color:
        var(--teal);
}


.meal:nth-child(3)
.meal-icon {

    color:#B86722;
}


.meal:nth-child(4)
.meal-icon {

    color:
        var(--blue);
}


.meal-title {

    color:#3D4B59;

    font-size:12px;

    font-weight:800;

    text-transform:uppercase;

    letter-spacing:0.3px;
}


.meal-food {

    color:
        var(--text);

    font-size:13px;

    line-height:1.55;
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

    width:70px;
    height:70px;

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
            #FFF0C8
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

    .mess-hero {

        grid-template-columns:
            1fr 300px;
    }


    .content-grid {

        grid-template-columns:1fr;
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


    .mess-hero {

        grid-template-columns:1fr;
    }


    .hero-food {

        width:100%;
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


    .meal-grid {

        grid-template-columns:1fr;
    }


    .meal-image {

        height:180px;
    }
}


@media(max-width:520px) {

    .main {

        padding:14px;
    }


    .mess-hero {

        padding:23px;
    }


    .hero-text h2 {

        font-size:28px;
    }
}


/* Source credit appears only when an online matched photo is found */
.photo-credit {
    position: absolute;
    bottom: 6px;
    right: 7px;
    z-index: 3;
    padding: 3px 6px;
    border-radius: 5px;
    background: rgba(18,32,45,.72);
    color: white !important;
    font-size: 9px;
    text-decoration: none;
}
.photo-credit:hover { text-decoration: underline; }
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

<i class="fa-solid fa-utensils"></i>

</div>


<div>

<h1>
Mess Management
</h1>

<p>
Manage daily hostel meals and menu schedules.
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

<div class="mess-hero">


<div class="hero-text">


<div class="hero-label">

Hostel Dining Management

</div>


<h2>
Plan meals.
Keep students informed. 🍽️
</h2>


<p>

Create and update daily hostel menus for breakfast,
lunch, snacks and dinner. Students can view the
latest menu directly from their UniStay portal.

</p>


<div class="hero-chip">

<i class="fa-solid fa-calendar-check"></i>

Daily Menu Management

</div>


</div>



<div class="hero-food">


<img
    src="<?php echo htmlspecialchars($lunchImage); ?>"
    alt="Indian hostel meal"
>


<div class="hero-image-text">

<strong>
Fresh • Balanced • Everyday
</strong>

<span>
A better dining experience at UniStay
</span>

</div>


</div>


</div>



<!-- =========================================================
     SUMMARY
     ========================================================= -->

<div class="summary-grid">


<div class="summary-card">


<div class="summary-icon">

<i class="fa-solid fa-calendar-days"></i>

</div>


<div>

<div class="summary-label">
Total Menus
</div>

<div class="summary-value">

<?php echo $totalMenus; ?>

</div>

</div>


</div>



<div class="summary-card">


<div class="summary-icon">

<i class="fa-solid fa-utensils"></i>

</div>


<div>

<div class="summary-label">
Today's Menu
</div>

<div class="summary-text">

<?php

echo ($todayMenu > 0)
    ? "Ready"
    : "Not Set";

?>

</div>

</div>


</div>



<div class="summary-card">


<div class="summary-icon">

<i class="fa-solid fa-calendar-plus"></i>

</div>


<div>

<div class="summary-label">
Upcoming Menus
</div>

<div class="summary-value">

<?php echo $upcomingMenus; ?>

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
    class="status-message <?php echo $messageType; ?>"
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
     CONTENT
     ========================================================= -->

<div class="content-grid">


<!-- =========================================================
     ADD / UPDATE MENU
     ========================================================= -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">


<div class="panel-icon">

<i class="fa-solid fa-pen-to-square"></i>

</div>


<div>

<h2>
Add / Update Menu
</h2>

<p>
Enter meals for the selected date.
</p>

</div>


</div>


</div>



<form
    method="POST"
    class="menu-form"
>


<div class="form-group">

<label>

<i class="fa-regular fa-calendar"></i>

Menu Date

</label>


<input
    type="date"
    name="menu_date"
    class="form-control"
    value="<?php echo date('Y-m-d'); ?>"
    required
>

</div>



<div class="form-group">

<label>

<i class="fa-solid fa-mug-hot"></i>

Breakfast

</label>


<textarea
    name="breakfast"
    class="form-control"
    placeholder="Example: Aloo Paratha, Curd, Tea"
    required
></textarea>

</div>



<div class="form-group">

<label>

<i class="fa-solid fa-bowl-food"></i>

Lunch

</label>


<textarea
    name="lunch"
    class="form-control"
    placeholder="Example: Dal, Rice, Roti, Salad"
    required
></textarea>

</div>



<div class="form-group">

<label>

<i class="fa-solid fa-cookie-bite"></i>

Snacks

</label>


<textarea
    name="snacks"
    class="form-control"
    placeholder="Example: Samosa, Tea"
    required
></textarea>

</div>



<div class="form-group">

<label>

<i class="fa-solid fa-plate-wheat"></i>

Dinner

</label>


<textarea
    name="dinner"
    class="form-control"
    placeholder="Example: Paneer, Roti, Rice, Sweet"
    required
></textarea>

</div>



<div class="form-note">

<i class="fa-solid fa-circle-info"></i>

<div>

If a menu already exists for the selected date,
saving it again will update the existing menu instead
of creating a duplicate.

</div>

</div>



<button
    type="submit"
    class="save-btn"
>

<i class="fa-solid fa-floppy-disk"></i>

Save Menu

</button>


</form>


</div>



<!-- =========================================================
     SAVED MENU
     ========================================================= -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">


<div class="panel-icon">

<i class="fa-solid fa-calendar-days"></i>

</div>


<div>

<h2>
Saved Mess Menus
</h2>

<p>
Recently added hostel meal schedules.
</p>

</div>


</div>



<div class="count-pill">

<i class="fa-solid fa-utensils"></i>

Latest 15

</div>


</div>



<div class="menu-list">


<?php

if (
    $menus &&
    mysqli_num_rows($menus) > 0
) {


    while (
        $menu =
        mysqli_fetch_assoc($menus)
    ) {


        $isToday =
            $menu["menu_date"]
            == date("Y-m-d");

?>


<div class="menu-card">


<div class="menu-date">


<div class="date-wrap">


<div class="date-icon">

<i class="fa-regular fa-calendar"></i>

</div>


<div>


<h3>

<?php

echo date(
    "d M Y",
    strtotime(
        $menu["menu_date"]
    )
);

?>

</h3>


<div class="menu-day">

<?php

echo date(
    "l",
    strtotime(
        $menu["menu_date"]
    )
);

?>

</div>


</div>


</div>



<?php if ($isToday) { ?>


<div class="today-badge">

<i class="fa-solid fa-star"></i>

Today

</div>


<?php } ?>


</div>



<div class="meal-grid">


<!-- =====================================================
     BREAKFAST
     ===================================================== -->

<div class="meal">


<div class="meal-image">

<img
    src="<?php echo htmlspecialchars($breakfastImage); ?>"
    class="auto-food-photo" data-food="<?php echo htmlspecialchars($menu['breakfast'], ENT_QUOTES, 'UTF-8'); ?>" data-fallback="<?php echo htmlspecialchars($breakfastImage, ENT_QUOTES, 'UTF-8'); ?>" alt="Breakfast"
    loading="lazy"
>

</div>


<div class="meal-body">


<div class="meal-top">


<div class="meal-icon">

<i class="fa-solid fa-mug-hot"></i>

</div>


<div class="meal-title">

Breakfast

</div>


</div>


<div class="meal-food">

<?php

echo nl2br(
    htmlspecialchars(
        $menu["breakfast"]
    )
);

?>

</div>


</div>


</div>



<!-- =====================================================
     LUNCH
     ===================================================== -->

<div class="meal">


<div class="meal-image">

<img
    src="<?php echo htmlspecialchars($lunchImage); ?>"
    class="auto-food-photo" data-food="<?php echo htmlspecialchars($menu['lunch'], ENT_QUOTES, 'UTF-8'); ?>" data-fallback="<?php echo htmlspecialchars($lunchImage, ENT_QUOTES, 'UTF-8'); ?>" alt="Lunch"
    loading="lazy"
>

</div>


<div class="meal-body">


<div class="meal-top">


<div class="meal-icon">

<i class="fa-solid fa-bowl-food"></i>

</div>


<div class="meal-title">

Lunch

</div>


</div>


<div class="meal-food">

<?php

echo nl2br(
    htmlspecialchars(
        $menu["lunch"]
    )
);

?>

</div>


</div>


</div>



<!-- =====================================================
     SNACKS
     ===================================================== -->

<div class="meal">


<div class="meal-image">

<img
    src="<?php echo htmlspecialchars($snacksImage); ?>"
    class="auto-food-photo" data-food="<?php echo htmlspecialchars($menu['snacks'], ENT_QUOTES, 'UTF-8'); ?>" data-fallback="<?php echo htmlspecialchars($snacksImage, ENT_QUOTES, 'UTF-8'); ?>" alt="Snacks"
    loading="lazy"
>

</div>


<div class="meal-body">


<div class="meal-top">


<div class="meal-icon">

<i class="fa-solid fa-cookie-bite"></i>

</div>


<div class="meal-title">

Snacks

</div>


</div>


<div class="meal-food">

<?php

echo nl2br(
    htmlspecialchars(
        $menu["snacks"]
    )
);

?>

</div>


</div>


</div>



<!-- =====================================================
     DINNER
     ===================================================== -->

<div class="meal">


<div class="meal-image">

<img
    src="<?php echo htmlspecialchars($dinnerImage); ?>"
    class="auto-food-photo" data-food="<?php echo htmlspecialchars($menu['dinner'], ENT_QUOTES, 'UTF-8'); ?>" data-fallback="<?php echo htmlspecialchars($dinnerImage, ENT_QUOTES, 'UTF-8'); ?>" alt="Dinner"
    loading="lazy"
>

</div>


<div class="meal-body">


<div class="meal-top">


<div class="meal-icon">

<i class="fa-solid fa-plate-wheat"></i>

</div>


<div class="meal-title">

Dinner

</div>


</div>


<div class="meal-food">

<?php

echo nl2br(
    htmlspecialchars(
        $menu["dinner"]
    )
);

?>

</div>


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

<i class="fa-solid fa-utensils"></i>

</div>


<h3>
No mess menu added yet
</h3>


<p>
Saved meal schedules will appear here.
</p>


</div>


<?php } ?>


</div>


</div>


</div>


</div>


</div>



<script>

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


<script src="../assets/js/mess-food-images.js?v=1" defer></script>

</body>

</html>
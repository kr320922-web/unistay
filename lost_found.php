<?php

/* =========================================================
   SHOW ERRORS - SO PAGE NEVER STAYS BLANK
   ========================================================= */

ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);


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

if (isset($_SESSION["lost_found_message"])) {

    $message =
        $_SESSION["lost_found_message"];

    $messageType =
        $_SESSION["lost_found_message_type"] ?? "";

    unset($_SESSION["lost_found_message"]);
    unset($_SESSION["lost_found_message_type"]);
}


/* =========================================================
   UPDATE REPORT
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action =
        $_POST["action"] ?? "";

    if ($action === "update_report") {

        $reportId =
            intval(
                $_POST["report_id"] ?? 0
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
            "Open",
            "Matched",
            "Returned"
        ];


        if (
            $reportId <= 0 ||
            !in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {

            $_SESSION["lost_found_message"] =
                "Invalid report details.";

            $_SESSION["lost_found_message_type"] =
                "error";

        } else {

            $stmt =
                mysqli_prepare(
                    $conn,
                    "UPDATE lost_found
                     SET
                        status = ?,
                        warden_remark = ?
                     WHERE id = ?"
                );


            if (!$stmt) {

                die(
                    "Update query error: " .
                    mysqli_error($conn)
                );
            }


            mysqli_stmt_bind_param(
                $stmt,
                "ssi",
                $status,
                $wardenRemark,
                $reportId
            );


            if (mysqli_stmt_execute($stmt)) {

                $_SESSION["lost_found_message"] =
                    "Report updated successfully.";

                $_SESSION["lost_found_message_type"] =
                    "success";

            } else {

                $_SESSION["lost_found_message"] =
                    "Unable to update report.";

                $_SESSION["lost_found_message_type"] =
                    "error";
            }


            mysqli_stmt_close($stmt);
        }


        header("Location: lost_found.php");
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
                    WHEN report_type = 'Lost'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS lost_count,

        COALESCE(
            SUM(
                CASE
                    WHEN report_type = 'Found'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS found_count,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'Open'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS open_count,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'Matched'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS matched_count,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'Returned'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS returned_count

     FROM lost_found";


$countResult =
    mysqli_query(
        $conn,
        $countQuery
    );


if (!$countResult) {

    die(
        "Count query error: " .
        mysqli_error($conn)
    );
}


$countData =
    mysqli_fetch_assoc(
        $countResult
    );


$totalReports =
    intval(
        $countData["total"] ?? 0
    );

$lostCount =
    intval(
        $countData["lost_count"] ?? 0
    );

$foundCount =
    intval(
        $countData["found_count"] ?? 0
    );

$openCount =
    intval(
        $countData["open_count"] ?? 0
    );

$matchedCount =
    intval(
        $countData["matched_count"] ?? 0
    );

$returnedCount =
    intval(
        $countData["returned_count"] ?? 0
    );


/* =========================================================
   GET ALL REPORTS
   ========================================================= */

$reportQuery =
    "SELECT

        lf.id,
        lf.user_id,
        lf.report_type,
        lf.item_name,
        lf.category,
        lf.location,
        lf.item_date,
        lf.description,
        lf.image,
        lf.status,
        lf.warden_remark,
        lf.created_at,
        lf.updated_at,

        u.name AS student_name,
        u.email AS student_email,

        r.room_number,
        r.block

     FROM lost_found AS lf

     INNER JOIN users AS u
        ON lf.user_id = u.id

     LEFT JOIN rooms AS r
        ON u.room_id = r.id

     ORDER BY

        CASE
            WHEN lf.status = 'Open'
            THEN 1

            WHEN lf.status = 'Matched'
            THEN 2

            ELSE 3
        END,

        lf.id DESC";


$reports =
    mysqli_query(
        $conn,
        $reportQuery
    );


if (!$reports) {

    die(
        "Report query error: " .
        mysqli_error($conn)
    );
}


/* =========================================================
   HELPER
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
Lost & Found - UniStay
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

    --mustard:#E8B23D;

    --blue:#4387A5;
    --green:#36B486;
    --red:#E76871;

    --background:#EFF4F7;

    --border:#DFE7EC;

    --text:#1E2639;
    --muted:#718092;

    --teal-soft:#DDF2F4;
    --mustard-soft:#FFF2D3;
    --blue-soft:#E5F0F5;
    --green-soft:#E3F5EC;
    --red-soft:#FBE8EA;

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


.menu a:hover,
.menu a.active {

    color:
        var(--teal);

    background:
        #DDF0F3;
}


.menu a.active {

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

    justify-content:space-between;

    align-items:center;

    gap:20px;

    margin-bottom:24px;
}


.title-wrap {

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
            var(--navy),
            var(--teal)
        );

    font-size:20px;
}


.title-wrap h1 {

    font-size:30px;
}


.title-wrap p {

    margin-top:5px;

    color:
        var(--muted);

    font-size:14px;
}


.warden-profile {

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
            var(--teal2)
        );

    font-weight:800;
}


.warden-profile strong {

    display:block;

    font-size:14px;
}


.warden-profile span {

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

    display:grid;

    grid-template-columns:
        1fr 350px;

    align-items:center;

    gap:30px;

    padding:
        32px 34px;

    margin-bottom:20px;

    border-radius:20px;

    color:white;

    background:
        linear-gradient(
            120deg,
            #1F2747,
            #17475E,
            #0E6077,
            #25899A
        );
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
}


.hero p {

    max-width:620px;

    margin-top:9px;

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

    background:
        var(--mustard);

    font-size:12px;

    font-weight:700;
}


.hero-stats {

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:10px;

    padding:16px;

    border-radius:15px;

    background:
        rgba(255,255,255,0.11);
}


.hero-stat {

    padding:12px;

    text-align:center;

    border-radius:11px;

    background:
        rgba(255,255,255,0.07);
}


.hero-stat strong {

    display:block;

    font-size:23px;
}


.hero-stat span {

    display:block;

    margin-top:3px;

    color:
        rgba(255,255,255,0.72);

    font-size:10px;
}


/* =========================================================
   SUMMARY
   ========================================================= */

.cards {

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:14px;

    margin-bottom:20px;
}


.card {

    display:flex;

    align-items:center;

    gap:14px;

    min-height:120px;

    padding:18px;

    border-radius:14px;

    background:white;

    border:
        1px solid
        var(--border);

    box-shadow:
        0 8px 24px
        rgba(31,39,71,0.05);
}


.card-icon {

    width:50px;
    height:50px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:13px;

    font-size:19px;
}


.card:nth-child(1) {

    border-top:
        4px solid
        var(--teal);
}


.card:nth-child(1)
.card-icon {

    color:
        var(--teal);

    background:
        var(--teal-soft);
}


.card:nth-child(2) {

    border-top:
        4px solid
        var(--mustard);
}


.card:nth-child(2)
.card-icon {

    color:#936305;

    background:
        var(--mustard-soft);
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
        0 8px 24px
        rgba(31,39,71,0.05);
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


.panel-title {

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

    border-radius:11px;

    color:
        var(--teal);

    background:
        var(--teal-soft);
}


.panel-title h2 {

    font-size:20px;
}


.panel-title p {

    margin-top:3px;

    color:
        var(--muted);

    font-size:13px;
}


/* =========================================================
   FILTER
   ========================================================= */

.filters {

    display:flex;

    gap:9px;
}


.search {

    padding:
        10px 12px;

    min-width:220px;

    border:
        1px solid
        #D7E1E6;

    border-radius:9px;

    background:#F8FAFB;

    outline:none;
}


.filter {

    padding:
        10px;

    border:
        1px solid
        #D7E1E6;

    border-radius:9px;

    background:#F8FAFB;

    outline:none;
}


/* =========================================================
   REPORTS
   ========================================================= */

.report-list {

    padding:18px;
}


.report-card {

    display:grid;

    grid-template-columns:
        110px
        minmax(0,1fr)
        250px;

    gap:16px;

    align-items:start;

    padding:15px;

    margin-bottom:15px;

    border-radius:14px;

    background:
        #FBFCFD;

    border:
        1px solid
        #E1E8EC;
}


.report-card:last-child {

    margin-bottom:0;
}


.item-image {

    width:110px;
    height:110px;

    display:flex;

    align-items:center;

    justify-content:center;

    overflow:hidden;

    border-radius:12px;

    color:
        var(--teal);

    background:
        var(--teal-soft);

    font-size:30px;
}


.item-image img {

    width:100%;
    height:100%;

    object-fit:cover;
}


.report-header {

    display:flex;

    justify-content:space-between;

    align-items:flex-start;

    gap:10px;
}


.item-name {

    font-size:17px;

    font-weight:750;
}


.type {

    display:inline-flex;

    margin-top:6px;

    padding:
        5px 9px;

    border-radius:20px;

    font-size:10px;

    font-weight:700;
}


.type.lost {

    color:#A64048;

    background:
        var(--red-soft);
}


.type.found {

    color:#17684D;

    background:
        var(--green-soft);
}


.status {

    display:inline-flex;

    padding:
        7px 10px;

    border-radius:20px;

    font-size:10px;

    font-weight:700;
}


.status.open {

    color:#946405;

    background:
        var(--mustard-soft);
}


.status.matched {

    color:
        var(--blue);

    background:
        var(--blue-soft);
}


.status.returned {

    color:#17684D;

    background:
        var(--green-soft);
}


.student {

    display:flex;

    align-items:center;

    gap:9px;

    margin-top:11px;
}


.student-avatar {

    width:36px;
    height:36px;

    display:flex;

    align-items:center;

    justify-content:center;

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


.meta {

    display:flex;

    flex-wrap:wrap;

    gap:7px 15px;

    margin-top:11px;

    color:
        var(--muted);

    font-size:11px;
}


.description {

    margin-top:10px;

    color:#526479;

    font-size:12px;

    line-height:1.5;
}


/* =========================================================
   UPDATE FORM
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

    margin-bottom:10px;

    font-size:13px;
}


.update-box label {

    display:block;

    margin:
        8px 0 5px;

    font-size:11px;

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

    background:
        #F9FBFC;

    font-size:12px;
}


textarea.control {

    min-height:68px;

    resize:vertical;
}


.update-btn {

    width:100%;

    margin-top:10px;

    padding:10px;

    border:0;

    border-radius:8px;

    color:white;

    background:
        linear-gradient(
            135deg,
            var(--teal),
            var(--teal2)
        );

    cursor:pointer;

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


.empty i {

    margin-bottom:12px;

    color:
        var(--teal);

    font-size:35px;
}


.empty h3 {

    margin-bottom:5px;

    color:
        var(--text);
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media(max-width:1200px) {

    .hero {

        grid-template-columns:1fr;
    }


    .report-card {

        grid-template-columns:
            100px
            1fr;
    }


    .update-box {

        grid-column:
            1 / -1;
    }
}


@media(max-width:1000px) {

    .cards {

        grid-template-columns:
            repeat(2,1fr);
    }


    .panel-head {

        align-items:flex-start;

        flex-direction:column;
    }
}


@media(max-width:700px) {

    .sidebar {

        display:none;
    }


    .main {

        padding:16px;
    }
}


@media(max-width:550px) {

    .cards {

        grid-template-columns:1fr;
    }


    .filters {

        width:100%;

        flex-direction:column;
    }


    .search,
    .filter {

        width:100%;
    }


    .report-card {

        grid-template-columns:1fr;
    }


    .item-image {

        width:100%;
        height:190px;
    }

}

</style>

</head>


<body>


<div class="dashboard">


<?php include "sidebar.php"; ?>


<div class="main">


<!-- TOPBAR -->

<div class="topbar">


<div class="title-wrap">


<div class="page-icon">

<i class="fa-solid fa-magnifying-glass-location"></i>

</div>


<div>

<h1>
Lost & Found
</h1>

<p>
Manage student lost and found reports.
</p>

</div>


</div>



<div class="warden-profile">


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



<!-- HERO -->

<div class="hero">


<div>


<div class="hero-label">
Recovery Management
</div>


<h2>
Help belongings find their way home.
</h2>


<p>

Review Lost and Found reports submitted by students,
identify possible matches and update each case until
the item is returned to its owner.

</p>


<div class="hero-chip">

<i class="fa-solid fa-link"></i>

Open → Matched → Returned

</div>


</div>



<div class="hero-stats">


<div class="hero-stat">

<strong>
<?php echo $lostCount; ?>
</strong>

<span>
Lost
</span>

</div>


<div class="hero-stat">

<strong>
<?php echo $foundCount; ?>
</strong>

<span>
Found
</span>

</div>


<div class="hero-stat">

<strong>
<?php echo $matchedCount; ?>
</strong>

<span>
Matched
</span>

</div>


<div class="hero-stat">

<strong>
<?php echo $returnedCount; ?>
</strong>

<span>
Returned
</span>

</div>


</div>


</div>



<!-- SUMMARY -->

<div class="cards">


<div class="card">

<div class="card-icon">

<i class="fa-solid fa-clipboard-list"></i>

</div>

<div>

<p>
Total Reports
</p>

<h2>
<?php echo $totalReports; ?>
</h2>

</div>

</div>



<div class="card">

<div class="card-icon">

<i class="fa-regular fa-clock"></i>

</div>

<div>

<p>
Open
</p>

<h2>
<?php echo $openCount; ?>
</h2>

</div>

</div>



<div class="card">

<div class="card-icon">

<i class="fa-solid fa-link"></i>

</div>

<div>

<p>
Matched
</p>

<h2>
<?php echo $matchedCount; ?>
</h2>

</div>

</div>



<div class="card">

<div class="card-icon">

<i class="fa-solid fa-circle-check"></i>

</div>

<div>

<p>
Returned
</p>

<h2>
<?php echo $returnedCount; ?>
</h2>

</div>

</div>


</div>



<!-- MESSAGE -->

<?php if ($message !== "") { ?>


<div
    id="message"
    class="message <?php echo htmlspecialchars($messageType); ?>"
>

<?php
echo htmlspecialchars(
    $message
);
?>

</div>


<?php } ?>



<!-- REPORT PANEL -->

<div class="panel">


<div class="panel-head">


<div class="panel-title">


<div class="panel-icon">

<i class="fa-solid fa-box-open"></i>

</div>


<div>

<h2>
Student Reports
</h2>

<p>
Search reports and update recovery progress.
</p>

</div>


</div>



<div class="filters">


<input
    type="text"
    id="searchBox"
    class="search"
    placeholder="Search item, student, room..."
>


<select
    id="typeFilter"
    class="filter"
>

<option value="all">
All Types
</option>

<option value="lost">
Lost
</option>

<option value="found">
Found
</option>

</select>


<select
    id="statusFilter"
    class="filter"
>

<option value="all">
All Status
</option>

<option value="open">
Open
</option>

<option value="matched">
Matched
</option>

<option value="returned">
Returned
</option>

</select>


</div>


</div>



<?php

if (
    mysqli_num_rows(
        $reports
    ) > 0
) {

?>


<div class="report-list">


<?php

while (
    $report =
    mysqli_fetch_assoc(
        $reports
    )
) {

    $typeClass =
        strtolower(
            $report["report_type"]
        );

    $statusClass =
        strtolower(
            $report["status"]
        );

?>


<div
    class="report-card"
    data-type="<?php echo htmlspecialchars($typeClass); ?>"
    data-status="<?php echo htmlspecialchars($statusClass); ?>"
>


<!-- IMAGE -->

<div class="item-image">


<?php

if (
    !empty(
        $report["image"]
    )
) {

?>


<img
    src="../<?php echo htmlspecialchars($report["image"]); ?>"
    alt="Item"
>


<?php

} else {

?>


<i class="fa-solid fa-box-open"></i>


<?php } ?>


</div>



<!-- DETAILS -->

<div>


<div class="report-header">


<div>


<div class="item-name">

<?php
echo htmlspecialchars(
    $report["item_name"]
);
?>

</div>


<span
    class="type <?php echo $typeClass; ?>"
>

<?php
echo htmlspecialchars(
    $report["report_type"]
);
?>

</span>


</div>



<span
    class="status <?php echo $statusClass; ?>"
>

<?php
echo htmlspecialchars(
    $report["status"]
);
?>

</span>


</div>



<div class="student">


<div class="student-avatar">

<?php
echo getInitial(
    $report["student_name"]
);
?>

</div>


<div>


<strong>

<?php
echo htmlspecialchars(
    $report["student_name"]
);
?>

</strong>


<span>

<?php
echo htmlspecialchars(
    $report["student_email"]
);
?>


<?php

if (
    !empty(
        $report["room_number"]
    )
) {

    echo " • Room ";

    echo htmlspecialchars(
        $report["room_number"]
    );
}

?>

</span>


</div>


</div>



<div class="meta">


<span>

<i class="fa-solid fa-tag"></i>

<?php
echo htmlspecialchars(
    $report["category"]
);
?>

</span>


<span>

<i class="fa-solid fa-location-dot"></i>

<?php
echo htmlspecialchars(
    $report["location"]
);
?>

</span>


<span>

<i class="fa-regular fa-calendar"></i>

<?php

echo date(
    "d M Y",
    strtotime(
        $report["item_date"]
    )
);

?>

</span>


</div>



<div class="description">

<?php

echo nl2br(
    htmlspecialchars(
        $report["description"]
    )
);

?>

</div>


</div>



<!-- WARDEN UPDATE -->

<div class="update-box">


<h4>

<i class="fa-solid fa-user-shield"></i>

Warden Update

</h4>


<form method="POST">


<input
    type="hidden"
    name="action"
    value="update_report"
>


<input
    type="hidden"
    name="report_id"
    value="<?php echo intval($report["id"]); ?>"
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
    value="Open"
    <?php
    echo (
        $report["status"] === "Open"
    )
    ? "selected"
    : "";
    ?>
>
Open
</option>


<option
    value="Matched"
    <?php
    echo (
        $report["status"] === "Matched"
    )
    ? "selected"
    : "";
    ?>
>
Matched
</option>


<option
    value="Returned"
    <?php
    echo (
        $report["status"] === "Returned"
    )
    ? "selected"
    : "";
    ?>
>
Returned
</option>


</select>



<label>
Remark
</label>


<textarea
    name="warden_remark"
    class="control"
    placeholder="Example: Matching item found at reception."
><?php
echo htmlspecialchars(
    $report["warden_remark"] ?? ""
);
?></textarea>



<button
    type="submit"
    class="update-btn"
>

<i class="fa-solid fa-floppy-disk"></i>

Update Report

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
No matching reports
</h3>

<p>
Try changing your search or filters.
</p>

</div>


<?php

} else {

?>


<div class="empty">

<i class="fa-solid fa-box-open"></i>

<h3>
No reports yet
</h3>

<p>
Student Lost & Found reports will appear here.
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


function filterReports() {

    const search =
        searchBox.value
        .toLowerCase()
        .trim();

    const type =
        typeFilter.value;

    const status =
        statusFilter.value;

    const cards =
        document.querySelectorAll(
            ".report-card"
        );

    let visible = 0;


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
                text.includes(search);

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
        filterReports
    );
}


if (typeFilter) {

    typeFilter.addEventListener(
        "change",
        filterReports
    );
}


if (statusFilter) {

    statusFilter.addEventListener(
        "change",
        filterReports
    );
}


/* MESSAGE */

const message =
    document.getElementById(
        "message"
    );


if (message) {

    setTimeout(
        function() {

            message.style.opacity =
                "0";

            message.style.transition =
                "0.4s";

        },
        3000
    );
}

</script>


</body>

</html>
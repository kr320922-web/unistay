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
   SUMMARY
   ========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM student_fees"
);

$totalRecords =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM student_fees
     WHERE status = 'Paid'"
);

$paidCount =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM student_fees
     WHERE status = 'Partial'"
);

$partialCount =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM student_fees
     WHERE status = 'Due'"
);

$dueCount =
    intval(
        mysqli_fetch_assoc($result)["total"]
    );


/* =========================================================
   AMOUNT SUMMARY
   ========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT
        COALESCE(SUM(total_amount), 0) AS total_amount,
        COALESCE(SUM(paid_amount), 0) AS total_paid
     FROM student_fees"
);

$amountRow =
    mysqli_fetch_assoc($result);

$totalAmount =
    floatval(
        $amountRow["total_amount"]
    );

$totalPaid =
    floatval(
        $amountRow["total_paid"]
    );

$totalDue =
    max(
        0,
        $totalAmount - $totalPaid
    );


/* =========================================================
   GET FEES
   ========================================================= */

$fees = mysqli_query(
    $conn,
    "SELECT
        student_fees.id,
        student_fees.user_id,
        student_fees.fee_title,
        student_fees.total_amount,
        student_fees.paid_amount,
        student_fees.due_date,
        student_fees.payment_date,
        student_fees.payment_method,
        student_fees.status,
        student_fees.created_at,

        users.name,
        users.email,

        rooms.room_number,
        rooms.block

     FROM student_fees

     INNER JOIN users
     ON student_fees.user_id = users.id

     LEFT JOIN rooms
     ON users.room_id = rooms.id

     WHERE users.role = 'student'

     ORDER BY
        CASE
            WHEN student_fees.status = 'Due' THEN 1
            WHEN student_fees.status = 'Partial' THEN 2
            ELSE 3
        END,
        student_fees.due_date ASC,
        student_fees.id DESC"
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
Student Fees - UniStay
</title>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
>

<style>

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
        330px;

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
}

.hero::before {
    content:"";

    position:absolute;

    width:300px;
    height:300px;

    right:-100px;
    top:-185px;

    border-radius:50%;

    background:
        rgba(255,255,255,0.07);
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

    color:
        var(--navy);

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

.hero-icon {
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

.hero-money {
    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:10px;

    padding-top:14px;
}

.hero-money-box {
    text-align:center;
}

.hero-money-box span {
    display:block;

    color:
        rgba(255,255,255,0.65);

    font-size:10px;

    text-transform:uppercase;
}

.hero-money-box b {
    display:block;

    margin-top:5px;

    font-size:17px;
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

    display:flex;
    align-items:center;
    justify-content:center;

    flex-shrink:0;

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
    color:#976606;

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
}

.card h2 {
    margin-top:4px;

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
        var(--teal);

    background:
        var(--teal-soft);

    font-size:16px;
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

    align-items:center;

    gap:10px;
}

.search-box {
    position:relative;

    width:250px;
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

.search-box input,
.status-filter {
    border:
        1px solid
        #D7E1E6;

    border-radius:9px;

    outline:none;

    background:
        #F8FAFB;

    color:
        var(--text);

    font-size:13px;
}

.search-box input {
    width:100%;

    padding:
        10px 12px 10px 37px;
}

.status-filter {
    padding:
        10px 12px;
}


/* =========================================================
   TABLE
   ========================================================= */

.table-wrap {
    overflow-x:auto;
}

table {
    width:100%;

    border-collapse:collapse;
}

thead {
    background:#F5F8FA;
}

th {
    padding:
        14px 15px;

    text-align:left;

    color:#526475;

    font-size:12px;
    font-weight:750;

    text-transform:uppercase;

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

    color:#344456;

    font-size:13px;

    vertical-align:middle;
}

tbody tr:hover {
    background:#F8FBFC;
}

tbody tr:last-child td {
    border-bottom:none;
}


/* =========================================================
   STUDENT
   ========================================================= */

.student-cell {
    display:flex;

    align-items:center;

    gap:11px;

    min-width:190px;
}

.avatar {
    width:42px;
    height:42px;

    display:flex;
    align-items:center;
    justify-content:center;

    flex-shrink:0;

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
    margin-top:3px;

    color:
        var(--muted);

    font-size:11px;
}


/* =========================================================
   ROOM
   ========================================================= */

.room-badge {
    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:
        7px 10px;

    border-radius:9px;

    color:
        var(--teal);

    background:
        var(--teal-soft);

    font-size:11px;

    font-weight:700;
}


/* =========================================================
   MONEY
   ========================================================= */

.money {
    font-weight:700;

    white-space:nowrap;
}

.due-money {
    color:
        var(--red);
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

.status-paid {
    color:#1B7657;

    background:
        var(--green-soft);
}

.status-partial {
    color:#976606;

    background:
        var(--mustard-soft);
}

.status-due {
    color:#AA414A;

    background:
        var(--red-soft);
}


/* =========================================================
   OVERDUE
   ========================================================= */

.overdue {
    display:block;

    margin-top:5px;

    color:
        var(--red);

    font-size:10px;

    font-weight:700;
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

    display:flex;
    align-items:center;
    justify-content:center;

    margin:
        0 auto 15px;

    border-radius:20px;

    color:
        var(--teal);

    background:
        linear-gradient(
            135deg,
            var(--teal-soft),
            var(--mustard-soft)
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

    .filters {
        width:100%;
    }

    .search-box {
        flex:1;
        width:auto;
    }
}


@media(max-width:850px) {

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

    .warden-info > div:last-child {
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
        align-items:stretch;

        flex-direction:column;
    }

    .search-box,
    .status-filter {
        width:100%;
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

<i class="fa-solid fa-indian-rupee-sign"></i>

</div>


<div>

<h1>
Student Fees
</h1>

<p>
View hostel fee payment status of students.
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
Fee Monitoring
</div>


<h2>
Track payments.
Spot pending dues.
</h2>


<p>

View student hostel fee records, payment status,
due amounts and payment information from one
organized screen.

</p>


<div class="hero-chip">

<i class="fa-solid fa-receipt"></i>

Student Payment Overview

</div>


</div>



<div class="hero-side">


<div class="hero-side-head">


<div class="hero-icon">

<i class="fa-solid fa-wallet"></i>

</div>


<div>

<strong>
Fee Collection
</strong>

<p>
Current payment summary
</p>

</div>


</div>



<div class="hero-money">


<div class="hero-money-box">

<span>
Collected
</span>

<b>

₹<?php
echo number_format(
    $totalPaid,
    0
);
?>

</b>

</div>



<div class="hero-money-box">

<span>
Outstanding
</span>

<b>

₹<?php
echo number_format(
    $totalDue,
    0
);
?>

</b>

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

<i class="fa-solid fa-file-invoice-dollar"></i>

</div>


<div>

<p>
Fee Records
</p>

<h2>

<?php echo $totalRecords; ?>

</h2>

</div>


</div>



<div class="card">


<div class="card-icon">

<i class="fa-solid fa-circle-check"></i>

</div>


<div>

<p>
Paid
</p>

<h2>

<?php echo $paidCount; ?>

</h2>

</div>


</div>



<div class="card">


<div class="card-icon">

<i class="fa-solid fa-circle-half-stroke"></i>

</div>


<div>

<p>
Partial
</p>

<h2>

<?php echo $partialCount; ?>

</h2>

</div>


</div>



<div class="card">


<div class="card-icon">

<i class="fa-solid fa-circle-exclamation"></i>

</div>


<div>

<p>
Due
</p>

<h2>

<?php echo $dueCount; ?>

</h2>

</div>


</div>


</div>



<!-- =========================================================
     FEE LIST
     ========================================================= -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">


<div class="panel-icon">

<i class="fa-solid fa-receipt"></i>

</div>


<div>

<h2>
Student Fee Records
</h2>

<p>
Payment status and outstanding hostel fee details.
</p>

</div>


</div>



<div class="filters">


<div class="search-box">

<i class="fa-solid fa-magnifying-glass"></i>

<input
    type="text"
    id="feeSearch"
    placeholder="Search student, room or fee..."
>

</div>


<select
    id="statusFilter"
    class="status-filter"
>

<option value="all">
All Status
</option>

<option value="paid">
Paid
</option>

<option value="partial">
Partial
</option>

<option value="due">
Due
</option>

</select>


</div>


</div>



<?php

if (
    $fees &&
    mysqli_num_rows($fees) > 0
) {

?>


<div class="table-wrap">


<table id="feesTable">


<thead>


<tr>

<th>
Student
</th>

<th>
Room
</th>

<th>
Fee
</th>

<th>
Total
</th>

<th>
Paid
</th>

<th>
Due
</th>

<th>
Due Date
</th>

<th>
Status
</th>

<th>
Payment
</th>

</tr>


</thead>



<tbody>


<?php

while (
    $fee =
    mysqli_fetch_assoc($fees)
) {


    $total =
        floatval(
            $fee["total_amount"]
        );

    $paid =
        floatval(
            $fee["paid_amount"]
        );

    $due =
        max(
            0,
            $total - $paid
        );


    $status =
        strtolower(
            $fee["status"]
        );


    $isOverdue =
        $fee["status"] != "Paid" &&
        !empty($fee["due_date"]) &&
        strtotime($fee["due_date"]) <
        strtotime(date("Y-m-d"));

?>


<tr
    data-status="<?php echo $status; ?>"
>


<td>


<div class="student-cell">


<div class="avatar">

<?php
echo getInitial(
    $fee["name"]
);
?>

</div>


<div>


<div class="student-name">

<?php
echo htmlspecialchars(
    $fee["name"]
);
?>

</div>


<div class="student-email">

<?php
echo htmlspecialchars(
    $fee["email"]
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
        $fee["room_number"]
    )
) {

?>


<span class="room-badge">

<i class="fa-solid fa-bed"></i>

<?php
echo htmlspecialchars(
    $fee["room_number"]
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
echo htmlspecialchars(
    $fee["fee_title"]
);
?>

</td>



<td>

<span class="money">

₹<?php
echo number_format(
    $total,
    0
);
?>

</span>

</td>



<td>

<span class="money">

₹<?php
echo number_format(
    $paid,
    0
);
?>

</span>

</td>



<td>

<span class="money due-money">

₹<?php
echo number_format(
    $due,
    0
);
?>

</span>

</td>



<td>


<?php

if (
    !empty(
        $fee["due_date"]
    )
) {

    echo date(
        "d M Y",
        strtotime(
            $fee["due_date"]
        )
    );

} else {

    echo "—";
}

?>


<?php if ($isOverdue) { ?>


<span class="overdue">

<i class="fa-solid fa-triangle-exclamation"></i>

Overdue

</span>


<?php } ?>


</td>



<td>


<?php

if (
    $fee["status"] == "Paid"
) {

?>


<span class="status status-paid">

<i class="fa-solid fa-circle-check"></i>

Paid

</span>


<?php

} elseif (
    $fee["status"] == "Partial"
) {

?>


<span class="status status-partial">

<i class="fa-solid fa-circle-half-stroke"></i>

Partial

</span>


<?php

} else {

?>


<span class="status status-due">

<i class="fa-solid fa-clock"></i>

Due

</span>


<?php } ?>


</td>



<td>


<?php

if (
    $fee["status"] == "Paid" ||
    $fee["status"] == "Partial"
) {

?>


<strong>

<?php

echo !empty(
    $fee["payment_method"]
)
?
htmlspecialchars(
    $fee["payment_method"]
)
:
"Recorded";

?>

</strong>


<?php

if (
    !empty(
        $fee["payment_date"]
    )
) {

?>


<div
    style="
        margin-top:3px;
        color:#718092;
        font-size:11px;
    "
>

<?php

echo date(
    "d M Y",
    strtotime(
        $fee["payment_date"]
    )
);

?>

</div>


<?php } ?>


<?php

} else {

    echo "—";
}

?>


</td>


</tr>


<?php } ?>


</tbody>


</table>


</div>



<div
    id="searchEmpty"
    class="empty"
    style="display:none;"
>


<div class="empty-icon">

<i class="fa-solid fa-magnifying-glass"></i>

</div>


<h3>
No matching fee record
</h3>


<p>
Try another student, room or payment status.
</p>


</div>


<?php

} else {

?>


<div class="empty">


<div class="empty-icon">

<i class="fa-solid fa-receipt"></i>

</div>


<h3>
No fee records available yet
</h3>


<p>
Fee records added by Admin will appear here.
</p>


</div>


<?php } ?>


</div>


</div>


</div>



<script>

/* =========================================================
   SEARCH + STATUS FILTER
   ========================================================= */

const feeSearch =
    document.getElementById(
        "feeSearch"
    );

const statusFilter =
    document.getElementById(
        "statusFilter"
    );


function filterFees() {

    const search =
        feeSearch
        ?
        feeSearch.value
        .toLowerCase()
        .trim()
        :
        "";


    const status =
        statusFilter
        ?
        statusFilter.value
        :
        "all";


    const rows =
        document.querySelectorAll(
            "#feesTable tbody tr"
        );


    let visible = 0;


    rows.forEach(
        function (row) {

            const rowText =
                row.innerText
                .toLowerCase();

            const rowStatus =
                row.getAttribute(
                    "data-status"
                );


            const searchMatch =
                rowText.includes(
                    search
                );


            const statusMatch =
                status === "all" ||
                rowStatus === status;


            if (
                searchMatch &&
                statusMatch
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
            "feesTable"
        );

    const empty =
        document.getElementById(
            "searchEmpty"
        );


    if (
        rows.length > 0
    ) {

        if (visible === 0) {

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
}


if (feeSearch) {

    feeSearch.addEventListener(
        "keyup",
        filterFees
    );
}


if (statusFilter) {

    statusFilter.addEventListener(
        "change",
        filterFees
    );
}

</script>


</body>
</html>
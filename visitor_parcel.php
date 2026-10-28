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

if (isset($_SESSION["vp_message"])) {

    $message =
        $_SESSION["vp_message"];

    $messageType =
        $_SESSION["vp_message_type"] ?? "";

    unset($_SESSION["vp_message"]);
    unset($_SESSION["vp_message_type"]);
}


/* =========================================================
   HANDLE ACTIONS
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action =
        $_POST["action"] ?? "";


    /* =====================================================
       ADD VISITOR
       ===================================================== */

    if ($action === "add_visitor") {

        $studentId =
            intval(
                $_POST["student_id"] ?? 0
            );

        $visitorName =
            trim(
                $_POST["visitor_name"] ?? ""
            );

        $visitorPhone =
            trim(
                $_POST["visitor_phone"] ?? ""
            );

        $relation =
            trim(
                $_POST["relation"] ?? ""
            );

        $purpose =
            trim(
                $_POST["purpose"] ?? ""
            );

        $visitDate =
            trim(
                $_POST["visit_date"] ?? ""
            );

        $inTime =
            trim(
                $_POST["in_time"] ?? ""
            );


        if (
            $studentId <= 0 ||
            $visitorName === "" ||
            $relation === "" ||
            $visitDate === "" ||
            $inTime === ""
        ) {

            $_SESSION["vp_message"] =
                "Please fill all required visitor details.";

            $_SESSION["vp_message_type"] =
                "error";

            header("Location: visitor_parcel.php");
            exit;
        }


        $stmt =
            mysqli_prepare(
                $conn,
                "INSERT INTO visitor_entries
                (
                    student_id,
                    visitor_name,
                    visitor_phone,
                    relation,
                    purpose,
                    visit_date,
                    in_time,
                    status
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, 'Inside')"
            );


        if (!$stmt) {

            die(
                "Visitor query error: " .
                mysqli_error($conn)
            );
        }


        mysqli_stmt_bind_param(
            $stmt,
            "issssss",
            $studentId,
            $visitorName,
            $visitorPhone,
            $relation,
            $purpose,
            $visitDate,
            $inTime
        );


        if (mysqli_stmt_execute($stmt)) {

            $_SESSION["vp_message"] =
                "Visitor entry added successfully.";

            $_SESSION["vp_message_type"] =
                "success";

        } else {

            $_SESSION["vp_message"] =
                "Unable to add visitor entry.";

            $_SESSION["vp_message_type"] =
                "error";
        }


        mysqli_stmt_close($stmt);

        header("Location: visitor_parcel.php");
        exit;
    }


    /* =====================================================
       CHECKOUT VISITOR
       ===================================================== */

    if ($action === "checkout_visitor") {

        $visitorId =
            intval(
                $_POST["visitor_id"] ?? 0
            );


        if ($visitorId > 0) {

            $stmt =
                mysqli_prepare(
                    $conn,
                    "UPDATE visitor_entries

                     SET
                        status = 'Checked Out',
                        out_time = CURTIME()

                     WHERE id = ?
                     AND status = 'Inside'"
                );


            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $visitorId
            );


            mysqli_stmt_execute($stmt);


            if (
                mysqli_stmt_affected_rows(
                    $stmt
                ) > 0
            ) {

                $_SESSION["vp_message"] =
                    "Visitor checked out successfully.";

                $_SESSION["vp_message_type"] =
                    "success";

            } else {

                $_SESSION["vp_message"] =
                    "Visitor is already checked out.";

                $_SESSION["vp_message_type"] =
                    "error";
            }


            mysqli_stmt_close($stmt);
        }


        header("Location: visitor_parcel.php");
        exit;
    }


    /* =====================================================
       ADD PARCEL
       ===================================================== */

    if ($action === "add_parcel") {

        $studentId =
            intval(
                $_POST["student_id"] ?? 0
            );

        $courierName =
            trim(
                $_POST["courier_name"] ?? ""
            );

        $trackingNumber =
            trim(
                $_POST["tracking_number"] ?? ""
            );

        $parcelType =
            trim(
                $_POST["parcel_type"] ?? ""
            );

        $receivedDate =
            trim(
                $_POST["received_date"] ?? ""
            );

        $receivedTime =
            trim(
                $_POST["received_time"] ?? ""
            );

        $wardenRemark =
            trim(
                $_POST["warden_remark"] ?? ""
            );


        if (
            $studentId <= 0 ||
            $courierName === "" ||
            $receivedDate === "" ||
            $receivedTime === ""
        ) {

            $_SESSION["vp_message"] =
                "Please fill all required parcel details.";

            $_SESSION["vp_message_type"] =
                "error";

            header("Location: visitor_parcel.php");
            exit;
        }


        $stmt =
            mysqli_prepare(
                $conn,
                "INSERT INTO parcels
                (
                    student_id,
                    courier_name,
                    tracking_number,
                    parcel_type,
                    received_date,
                    received_time,
                    status,
                    warden_remark
                )
                VALUES
                (?, ?, ?, ?, ?, ?, 'Waiting for Pickup', ?)"
            );


        if (!$stmt) {

            die(
                "Parcel query error: " .
                mysqli_error($conn)
            );
        }


        mysqli_stmt_bind_param(
            $stmt,
            "issssss",
            $studentId,
            $courierName,
            $trackingNumber,
            $parcelType,
            $receivedDate,
            $receivedTime,
            $wardenRemark
        );


        if (mysqli_stmt_execute($stmt)) {

            $_SESSION["vp_message"] =
                "Parcel received and added successfully.";

            $_SESSION["vp_message_type"] =
                "success";

        } else {

            $_SESSION["vp_message"] =
                "Unable to add parcel.";

            $_SESSION["vp_message_type"] =
                "error";
        }


        mysqli_stmt_close($stmt);

        header("Location: visitor_parcel.php");
        exit;
    }


    /* =====================================================
       MARK PARCEL COLLECTED
       ===================================================== */

    if ($action === "collect_parcel") {

        $parcelId =
            intval(
                $_POST["parcel_id"] ?? 0
            );


        if ($parcelId > 0) {

            $stmt =
                mysqli_prepare(
                    $conn,
                    "UPDATE parcels

                     SET
                        status = 'Collected',
                        collected_at = NOW()

                     WHERE id = ?
                     AND status = 'Waiting for Pickup'"
                );


            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $parcelId
            );


            mysqli_stmt_execute($stmt);


            if (
                mysqli_stmt_affected_rows(
                    $stmt
                ) > 0
            ) {

                $_SESSION["vp_message"] =
                    "Parcel marked as collected.";

                $_SESSION["vp_message_type"] =
                    "success";

            } else {

                $_SESSION["vp_message"] =
                    "Parcel is already collected.";

                $_SESSION["vp_message_type"] =
                    "error";
            }


            mysqli_stmt_close($stmt);
        }


        header("Location: visitor_parcel.php");
        exit;
    }
}


/* =========================================================
   STUDENTS FOR DROPDOWNS
   ========================================================= */

$students =
    mysqli_query(
        $conn,
        "SELECT
            u.id,
            u.name,
            u.email,
            r.room_number

         FROM users AS u

         LEFT JOIN rooms AS r
            ON u.room_id = r.id

         WHERE u.role = 'student'

         ORDER BY u.name ASC"
    );


/* SECOND COPY FOR PARCEL DROPDOWN */

$parcelStudents =
    mysqli_query(
        $conn,
        "SELECT
            u.id,
            u.name,
            u.email,
            r.room_number

         FROM users AS u

         LEFT JOIN rooms AS r
            ON u.room_id = r.id

         WHERE u.role = 'student'

         ORDER BY u.name ASC"
    );


/* =========================================================
   SUMMARY
   ========================================================= */

$result =
    mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM visitor_entries
         WHERE status = 'Inside'"
    );

$visitorsInside =
    intval(
        mysqli_fetch_assoc(
            $result
        )["total"]
    );


$result =
    mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM visitor_entries
         WHERE status = 'Checked Out'
         AND visit_date = CURDATE()"
    );

$checkedOutToday =
    intval(
        mysqli_fetch_assoc(
            $result
        )["total"]
    );


$result =
    mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM parcels
         WHERE status = 'Waiting for Pickup'"
    );

$waitingParcels =
    intval(
        mysqli_fetch_assoc(
            $result
        )["total"]
    );


$result =
    mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM parcels
         WHERE status = 'Collected'
         AND DATE(collected_at) = CURDATE()"
    );

$collectedToday =
    intval(
        mysqli_fetch_assoc(
            $result
        )["total"]
    );


/* =========================================================
   VISITOR RECORDS
   ========================================================= */

$visitorRecords =
    mysqli_query(
        $conn,
        "SELECT

            v.id,
            v.visitor_name,
            v.visitor_phone,
            v.relation,
            v.purpose,
            v.visit_date,
            v.in_time,
            v.out_time,
            v.status,

            u.name AS student_name,
            u.email AS student_email,

            r.room_number

         FROM visitor_entries AS v

         INNER JOIN users AS u
            ON v.student_id = u.id

         LEFT JOIN rooms AS r
            ON u.room_id = r.id

         ORDER BY

            CASE
                WHEN v.status = 'Inside'
                THEN 1
                ELSE 2
            END,

            v.visit_date DESC,
            v.in_time DESC"
    );


/* =========================================================
   PARCEL RECORDS
   ========================================================= */

$parcelRecords =
    mysqli_query(
        $conn,
        "SELECT

            p.id,
            p.courier_name,
            p.tracking_number,
            p.parcel_type,
            p.received_date,
            p.received_time,
            p.status,
            p.collected_at,
            p.warden_remark,

            u.name AS student_name,
            u.email AS student_email,

            r.room_number

         FROM parcels AS p

         INNER JOIN users AS u
            ON p.student_id = u.id

         LEFT JOIN rooms AS r
            ON u.room_id = r.id

         ORDER BY

            CASE
                WHEN p.status = 'Waiting for Pickup'
                THEN 1
                ELSE 2
            END,

            p.received_date DESC,
            p.received_time DESC"
    );


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
Visitors & Parcels - UniStay
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
    --teal-soft:#DDF2F4;

    --mustard:#E8B23D;
    --mustard-soft:#FFF2D3;

    --blue:#4387A5;
    --blue-soft:#E5F0F5;

    --green:#36B486;
    --green-soft:#E3F5EC;

    --red:#E76871;
    --red-soft:#FBE8EA;

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


.menu a i {

    width:22px;

    text-align:center;
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

    display:grid;

    grid-template-columns:
        minmax(0,1fr)
        360px;

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

    font-size:34px;
}


.hero p {

    max-width:620px;

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

    padding:17px;

    border-radius:15px;

    background:
        rgba(255,255,255,0.11);
}


.hero-stat {

    padding:13px;

    text-align:center;

    border-radius:11px;

    background:
        rgba(255,255,255,0.07);
}


.hero-stat strong {

    display:block;

    font-size:22px;
}


.hero-stat span {

    display:block;

    margin-top:3px;

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

    min-height:115px;

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
        var(--red);

    background:
        var(--red-soft);
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

    color:
        var(--mustard);

    background:
        var(--mustard-soft);
}


.card:nth-child(4)
.card-icon {

    color:
        var(--blue);

    background:
        var(--blue-soft);
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
   TABS
   ========================================================= */

.tabs {

    display:flex;

    gap:8px;

    margin-bottom:20px;
}


.tab-btn {

    display:flex;

    align-items:center;

    gap:7px;

    padding:
        11px 18px;

    border:none;

    border-radius:10px;

    cursor:pointer;

    color:
        var(--muted);

    background:white;

    border:
        1px solid
        var(--border);

    font-size:13px;

    font-weight:700;
}


.tab-btn.active {

    color:white;

    background:
        var(--teal);

    border-color:
        var(--teal);
}


/* =========================================================
   TAB CONTENT
   ========================================================= */

.tab-content {

    display:none;
}


.tab-content.active {

    display:block;
}


/* =========================================================
   GRID
   ========================================================= */

.content-grid {

    display:grid;

    grid-template-columns:
        370px
        minmax(0,1fr);

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

    display:flex;

    align-items:center;

    justify-content:center;

    flex-shrink:0;

    border-radius:11px;

    color:
        var(--teal);

    background:
        var(--teal-soft);

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
   FORM
   ========================================================= */

.form-wrap {

    padding:20px;
}


.form-group {

    margin-bottom:14px;
}


.form-row {

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:10px;
}


.form-group label {

    display:block;

    margin-bottom:6px;

    color:#344557;

    font-size:13px;

    font-weight:700;
}


.form-control {

    width:100%;

    padding:
        11px 12px;

    border:
        1px solid
        #D7E1E6;

    border-radius:9px;

    outline:none;

    background:#FBFCFD;

    color:
        var(--text);

    font-size:13px;
}


.form-control:focus {

    border-color:
        var(--teal);

    background:white;
}


textarea.form-control {

    min-height:86px;

    resize:vertical;
}


.submit-btn {

    width:100%;

    padding:12px;

    border:none;

    border-radius:9px;

    cursor:pointer;

    color:white;

    background:
        linear-gradient(
            135deg,
            var(--teal),
            var(--teal2)
        );

    font-size:13px;

    font-weight:750;
}


/* =========================================================
   SEARCH
   ========================================================= */

.search {

    width:240px;

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


/* =========================================================
   LIST
   ========================================================= */

.record-list {

    padding:18px;
}


.record-card {

    display:grid;

    grid-template-columns:
        52px
        minmax(0,1fr)
        auto;

    gap:13px;

    align-items:start;

    padding:14px;

    margin-bottom:13px;

    border-radius:13px;

    background:#FBFCFD;

    border:
        1px solid
        #E1E8EC;
}


.record-card:last-child {

    margin-bottom:0;
}


.record-icon {

    width:50px;
    height:50px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:12px;

    color:
        var(--teal);

    background:
        var(--teal-soft);

    font-size:18px;
}


.record-title {

    font-size:15px;

    font-weight:800;
}


.student-line {

    margin-top:4px;

    color:
        var(--teal);

    font-size:11px;

    font-weight:650;
}


.meta {

    display:flex;

    flex-wrap:wrap;

    gap:7px 13px;

    margin-top:9px;

    color:
        var(--muted);

    font-size:10px;
}


.description {

    margin-top:8px;

    color:#536575;

    font-size:11px;

    line-height:1.45;
}


.status {

    display:inline-flex;

    padding:
        6px 9px;

    border-radius:20px;

    white-space:nowrap;

    font-size:10px;

    font-weight:700;
}


.status.inside {

    color:#A64048;

    background:
        var(--red-soft);
}


.status.checked-out {

    color:#267A5D;

    background:
        var(--green-soft);
}


.status.waiting-for-pickup {

    color:#946405;

    background:
        var(--mustard-soft);
}


.status.collected {

    color:#267A5D;

    background:
        var(--green-soft);
}


.action-btn {

    margin-top:8px;

    padding:
        7px 10px;

    border:none;

    border-radius:8px;

    cursor:pointer;

    color:white;

    background:
        var(--teal);

    font-size:10px;

    font-weight:700;
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
}


.empty i {

    margin-bottom:10px;

    color:
        var(--teal);

    font-size:32px;
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

@media(max-width:1150px) {

    .hero {

        grid-template-columns:1fr;
    }


    .hero-stats {

        max-width:430px;
    }


    .content-grid {

        grid-template-columns:1fr;
    }
}


@media(max-width:950px) {

    .cards {

        grid-template-columns:
            repeat(2,1fr);
    }
}


@media(max-width:700px) {

    .sidebar {

        display:none;
    }


    .main {

        padding:18px;
    }
}


@media(max-width:520px) {

    .main {

        padding:14px;
    }


    .cards {

        grid-template-columns:1fr;
    }


    .hero {

        padding:23px;
    }


    .form-row {

        grid-template-columns:1fr;
    }


    .panel-head {

        align-items:flex-start;

        flex-direction:column;
    }


    .search {

        width:100%;
    }


    .record-card {

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

<i class="fa-solid fa-door-open"></i>

</div>


<div>

<h1>
Visitors & Parcels
</h1>

<p>
Manage hostel visitor access and student deliveries.
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


<div>


<div class="hero-label">
Front Desk Management
</div>


<h2>
Keep visitors and deliveries organised.
</h2>


<p>

Record visitor entry and exit details, receive student
parcels and track them until they are collected from
the hostel reception.

</p>


<div class="hero-chip">

<i class="fa-solid fa-shield-halved"></i>

Secure Entry • Simple Pickup

</div>


</div>



<div class="hero-stats">


<div class="hero-stat">

<strong>
<?php echo $visitorsInside; ?>
</strong>

<span>
Visitors Inside
</span>

</div>


<div class="hero-stat">

<strong>
<?php echo $checkedOutToday; ?>
</strong>

<span>
Checked Out Today
</span>

</div>


<div class="hero-stat">

<strong>
<?php echo $waitingParcels; ?>
</strong>

<span>
Waiting Parcels
</span>

</div>


<div class="hero-stat">

<strong>
<?php echo $collectedToday; ?>
</strong>

<span>
Collected Today
</span>

</div>


</div>


</div>



<!-- =========================================================
     SUMMARY
     ========================================================= -->

<div class="cards">


<div class="card">

<div class="card-icon">

<i class="fa-solid fa-person-walking-arrow-right"></i>

</div>

<div>

<p>
Visitors Inside
</p>

<h2>
<?php echo $visitorsInside; ?>
</h2>

</div>

</div>



<div class="card">

<div class="card-icon">

<i class="fa-solid fa-door-closed"></i>

</div>

<div>

<p>
Checked Out Today
</p>

<h2>
<?php echo $checkedOutToday; ?>
</h2>

</div>

</div>



<div class="card">

<div class="card-icon">

<i class="fa-solid fa-box"></i>

</div>

<div>

<p>
Waiting Parcels
</p>

<h2>
<?php echo $waitingParcels; ?>
</h2>

</div>

</div>



<div class="card">

<div class="card-icon">

<i class="fa-solid fa-circle-check"></i>

</div>

<div>

<p>
Collected Today
</p>

<h2>
<?php echo $collectedToday; ?>
</h2>

</div>

</div>


</div>



<!-- =========================================================
     MESSAGE
     ========================================================= -->

<?php if ($message !== "") { ?>

<div
    id="flashMessage"
    class="message <?php echo htmlspecialchars($messageType); ?>"
>

<?php
echo htmlspecialchars(
    $message
);
?>

</div>

<?php } ?>



<!-- =========================================================
     TABS
     ========================================================= -->

<div class="tabs">


<button
    class="tab-btn active"
    onclick="showTab('visitors', this)"
>

<i class="fa-solid fa-users"></i>

Visitors

</button>


<button
    class="tab-btn"
    onclick="showTab('parcels', this)"
>

<i class="fa-solid fa-box"></i>

Parcels

</button>


</div>



<!-- =========================================================
     VISITORS TAB
     ========================================================= -->

<div
    id="visitors"
    class="tab-content active"
>


<div class="content-grid">


<!-- ADD VISITOR -->

<div class="panel">


<div class="panel-head">

<div class="panel-heading">

<div class="panel-icon">

<i class="fa-solid fa-user-plus"></i>

</div>

<div>

<h2>
Visitor Entry
</h2>

<p>
Record a new hostel visitor.
</p>

</div>

</div>

</div>



<form
    method="POST"
    class="form-wrap"
>


<input
    type="hidden"
    name="action"
    value="add_visitor"
>


<div class="form-group">

<label>
Student
</label>

<select
    name="student_id"
    class="form-control"
    required
>

<option value="">
Select Student
</option>

<?php

while (
    $student =
    mysqli_fetch_assoc(
        $students
    )
) {

?>

<option
    value="<?php echo intval($student["id"]); ?>"
>

<?php

echo htmlspecialchars(
    $student["name"]
);

if (
    !empty(
        $student["room_number"]
    )
) {

    echo " — Room ";

    echo htmlspecialchars(
        $student["room_number"]
    );
}

?>

</option>

<?php } ?>

</select>

</div>



<div class="form-group">

<label>
Visitor Name
</label>

<input
    type="text"
    name="visitor_name"
    class="form-control"
    placeholder="Example: Rajesh Sharma"
    required
>

</div>



<div class="form-row">


<div class="form-group">

<label>
Phone
</label>

<input
    type="text"
    name="visitor_phone"
    class="form-control"
    placeholder="Phone number"
>

</div>



<div class="form-group">

<label>
Relation
</label>

<select
    name="relation"
    class="form-control"
    required
>

<option value="">
Select
</option>

<option value="Parent">
Parent
</option>

<option value="Sibling">
Sibling
</option>

<option value="Relative">
Relative
</option>

<option value="Friend">
Friend
</option>

<option value="Guardian">
Guardian
</option>

<option value="Other">
Other
</option>

</select>

</div>


</div>



<div class="form-group">

<label>
Purpose
</label>

<textarea
    name="purpose"
    class="form-control"
    placeholder="Example: Meeting student / dropping documents..."
></textarea>

</div>



<div class="form-row">


<div class="form-group">

<label>
Visit Date
</label>

<input
    type="date"
    name="visit_date"
    class="form-control"
    value="<?php echo date("Y-m-d"); ?>"
    required
>

</div>



<div class="form-group">

<label>
In Time
</label>

<input
    type="time"
    name="in_time"
    class="form-control"
    value="<?php echo date("H:i"); ?>"
    required
>

</div>


</div>



<button
    type="submit"
    class="submit-btn"
>

<i class="fa-solid fa-right-to-bracket"></i>

Add Visitor Entry

</button>


</form>


</div>



<!-- VISITOR LIST -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">

<div class="panel-icon">

<i class="fa-solid fa-users"></i>

</div>

<div>

<h2>
Visitor Records
</h2>

<p>
Current and previous visitor entries.
</p>

</div>

</div>


<input
    type="text"
    id="visitorSearch"
    class="search"
    placeholder="Search visitor or student..."
>


</div>



<?php

if (
    $visitorRecords &&
    mysqli_num_rows(
        $visitorRecords
    ) > 0
) {

?>


<div class="record-list">


<?php

while (
    $visitor =
    mysqli_fetch_assoc(
        $visitorRecords
    )
) {

    $statusClass =
        strtolower(
            str_replace(
                " ",
                "-",
                $visitor["status"]
            )
        );

?>


<div
    class="record-card visitor-record"
>


<div class="record-icon">

<i class="fa-solid fa-user-group"></i>

</div>



<div>


<div class="record-title">

<?php
echo htmlspecialchars(
    $visitor["visitor_name"]
);
?>

</div>


<div class="student-line">

Visiting:

<?php
echo htmlspecialchars(
    $visitor["student_name"]
);
?>

<?php

if (
    !empty(
        $visitor["room_number"]
    )
) {

    echo " • Room ";

    echo htmlspecialchars(
        $visitor["room_number"]
    );
}

?>

</div>



<div class="meta">


<span>

<i class="fa-solid fa-user-tag"></i>

<?php
echo htmlspecialchars(
    $visitor["relation"]
);
?>

</span>


<?php if (
    !empty(
        $visitor["visitor_phone"]
    )
) { ?>

<span>

<i class="fa-solid fa-phone"></i>

<?php
echo htmlspecialchars(
    $visitor["visitor_phone"]
);
?>

</span>

<?php } ?>


<span>

<i class="fa-regular fa-calendar"></i>

<?php

echo date(
    "d M Y",
    strtotime(
        $visitor["visit_date"]
    )
);

?>

</span>


<span>

<i class="fa-solid fa-right-to-bracket"></i>

<?php

echo date(
    "h:i A",
    strtotime(
        $visitor["in_time"]
    )
);

?>

</span>


<?php if (
    !empty(
        $visitor["out_time"]
    )
) { ?>

<span>

<i class="fa-solid fa-right-from-bracket"></i>

<?php

echo date(
    "h:i A",
    strtotime(
        $visitor["out_time"]
    )
);

?>

</span>

<?php } ?>


</div>



<?php if (
    !empty(
        $visitor["purpose"]
    )
) { ?>


<div class="description">

<?php
echo htmlspecialchars(
    $visitor["purpose"]
);
?>

</div>


<?php } ?>


</div>



<div>


<span
    class="status <?php echo $statusClass; ?>"
>

<?php
echo htmlspecialchars(
    $visitor["status"]
);
?>

</span>


<?php if (
    $visitor["status"] === "Inside"
) { ?>


<form method="POST">


<input
    type="hidden"
    name="action"
    value="checkout_visitor"
>


<input
    type="hidden"
    name="visitor_id"
    value="<?php echo intval($visitor["id"]); ?>"
>


<button
    type="submit"
    class="action-btn"
>

Check Out

</button>


</form>


<?php } ?>


</div>


</div>


<?php } ?>


</div>


<?php

} else {

?>


<div class="empty">

<i class="fa-solid fa-users"></i>

<h3>
No visitor records
</h3>

<p>
Visitor entries will appear here.
</p>

</div>


<?php } ?>


</div>


</div>


</div>



<!-- =========================================================
     PARCEL TAB
     ========================================================= -->

<div
    id="parcels"
    class="tab-content"
>


<div class="content-grid">


<!-- ADD PARCEL -->

<div class="panel">


<div class="panel-head">

<div class="panel-heading">

<div class="panel-icon">

<i class="fa-solid fa-box-open"></i>

</div>

<div>

<h2>
Receive Parcel
</h2>

<p>
Record a parcel received for a student.
</p>

</div>

</div>

</div>



<form
    method="POST"
    class="form-wrap"
>


<input
    type="hidden"
    name="action"
    value="add_parcel"
>


<div class="form-group">

<label>
Student
</label>

<select
    name="student_id"
    class="form-control"
    required
>

<option value="">
Select Student
</option>

<?php

while (
    $student =
    mysqli_fetch_assoc(
        $parcelStudents
    )
) {

?>

<option
    value="<?php echo intval($student["id"]); ?>"
>

<?php

echo htmlspecialchars(
    $student["name"]
);

if (
    !empty(
        $student["room_number"]
    )
) {

    echo " — Room ";

    echo htmlspecialchars(
        $student["room_number"]
    );
}

?>

</option>

<?php } ?>

</select>

</div>



<div class="form-group">

<label>
Courier / Sender
</label>

<input
    type="text"
    name="courier_name"
    class="form-control"
    placeholder="Example: Amazon / Flipkart / DTDC"
    required
>

</div>



<div class="form-row">


<div class="form-group">

<label>
Tracking Number
</label>

<input
    type="text"
    name="tracking_number"
    class="form-control"
    placeholder="Optional"
>

</div>



<div class="form-group">

<label>
Parcel Type
</label>

<select
    name="parcel_type"
    class="form-control"
>

<option value="">
Select Type
</option>

<option value="Package">
Package
</option>

<option value="Document">
Document
</option>

<option value="Food">
Food
</option>

<option value="Medicine">
Medicine
</option>

<option value="Other">
Other
</option>

</select>

</div>


</div>



<div class="form-row">


<div class="form-group">

<label>
Received Date
</label>

<input
    type="date"
    name="received_date"
    class="form-control"
    value="<?php echo date("Y-m-d"); ?>"
    required
>

</div>



<div class="form-group">

<label>
Received Time
</label>

<input
    type="time"
    name="received_time"
    class="form-control"
    value="<?php echo date("H:i"); ?>"
    required
>

</div>


</div>



<div class="form-group">

<label>
Warden Remark
</label>

<textarea
    name="warden_remark"
    class="form-control"
    placeholder="Example: Collect from reception before 8 PM."
></textarea>

</div>



<button
    type="submit"
    class="submit-btn"
>

<i class="fa-solid fa-box"></i>

Add Parcel

</button>


</form>


</div>



<!-- PARCEL LIST -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">

<div class="panel-icon">

<i class="fa-solid fa-boxes-stacked"></i>

</div>

<div>

<h2>
Parcel Records
</h2>

<p>
Track pending and collected deliveries.
</p>

</div>

</div>


<input
    type="text"
    id="parcelSearch"
    class="search"
    placeholder="Search parcel or student..."
>


</div>



<?php

if (
    $parcelRecords &&
    mysqli_num_rows(
        $parcelRecords
    ) > 0
) {

?>


<div class="record-list">


<?php

while (
    $parcel =
    mysqli_fetch_assoc(
        $parcelRecords
    )
) {

    $statusClass =
        strtolower(
            str_replace(
                " ",
                "-",
                $parcel["status"]
            )
        );

?>


<div
    class="record-card parcel-record"
>


<div class="record-icon">

<i class="fa-solid fa-box"></i>

</div>



<div>


<div class="record-title">

<?php
echo htmlspecialchars(
    $parcel["courier_name"]
);
?>

</div>


<div class="student-line">

For:

<?php
echo htmlspecialchars(
    $parcel["student_name"]
);
?>

<?php

if (
    !empty(
        $parcel["room_number"]
    )
) {

    echo " • Room ";

    echo htmlspecialchars(
        $parcel["room_number"]
    );
}

?>

</div>



<div class="meta">


<?php if (
    !empty(
        $parcel["parcel_type"]
    )
) { ?>

<span>

<i class="fa-solid fa-tag"></i>

<?php
echo htmlspecialchars(
    $parcel["parcel_type"]
);
?>

</span>

<?php } ?>


<?php if (
    !empty(
        $parcel["tracking_number"]
    )
) { ?>

<span>

<i class="fa-solid fa-barcode"></i>

<?php
echo htmlspecialchars(
    $parcel["tracking_number"]
);
?>

</span>

<?php } ?>


<span>

<i class="fa-regular fa-calendar"></i>

<?php

echo date(
    "d M Y",
    strtotime(
        $parcel["received_date"]
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
        $parcel["received_time"]
    )
);

?>

</span>


</div>



<?php if (
    !empty(
        $parcel["warden_remark"]
    )
) { ?>


<div class="description">

<strong>
Remark:
</strong>

<?php
echo htmlspecialchars(
    $parcel["warden_remark"]
);
?>

</div>


<?php } ?>


<?php if (
    !empty(
        $parcel["collected_at"]
    )
) { ?>


<div class="description">

Collected:

<?php

echo date(
    "d M Y, h:i A",
    strtotime(
        $parcel["collected_at"]
    )
);

?>

</div>


<?php } ?>


</div>



<div>


<span
    class="status <?php echo $statusClass; ?>"
>

<?php
echo htmlspecialchars(
    $parcel["status"]
);
?>

</span>


<?php if (
    $parcel["status"] ===
    "Waiting for Pickup"
) { ?>


<form method="POST">


<input
    type="hidden"
    name="action"
    value="collect_parcel"
>


<input
    type="hidden"
    name="parcel_id"
    value="<?php echo intval($parcel["id"]); ?>"
>


<button
    type="submit"
    class="action-btn"
>

Mark Collected

</button>


</form>


<?php } ?>


</div>


</div>


<?php } ?>


</div>


<?php

} else {

?>


<div class="empty">

<i class="fa-solid fa-box-open"></i>

<h3>
No parcel records
</h3>

<p>
Received parcels will appear here.
</p>

</div>


<?php } ?>


</div>


</div>


</div>


</div>


</div>



<script>

/* =========================================================
   TAB SWITCHING
   ========================================================= */

function showTab(tabName, button) {

    const tabs =
        document.querySelectorAll(
            ".tab-content"
        );


    const buttons =
        document.querySelectorAll(
            ".tab-btn"
        );


    tabs.forEach(
        function(tab) {

            tab.classList.remove(
                "active"
            );
        }
    );


    buttons.forEach(
        function(btn) {

            btn.classList.remove(
                "active"
            );
        }
    );


    document
        .getElementById(tabName)
        .classList.add("active");


    button.classList.add(
        "active"
    );
}


/* =========================================================
   VISITOR SEARCH
   ========================================================= */

const visitorSearch =
    document.getElementById(
        "visitorSearch"
    );


if (visitorSearch) {

    visitorSearch.addEventListener(
        "input",
        function() {

            const value =
                visitorSearch.value
                .toLowerCase()
                .trim();


            document
                .querySelectorAll(
                    ".visitor-record"
                )
                .forEach(
                    function(card) {

                        card.style.display =
                            card.innerText
                            .toLowerCase()
                            .includes(value)
                            ? "grid"
                            : "none";
                    }
                );
        }
    );
}


/* =========================================================
   PARCEL SEARCH
   ========================================================= */

const parcelSearch =
    document.getElementById(
        "parcelSearch"
    );


if (parcelSearch) {

    parcelSearch.addEventListener(
        "input",
        function() {

            const value =
                parcelSearch.value
                .toLowerCase()
                .trim();


            document
                .querySelectorAll(
                    ".parcel-record"
                )
                .forEach(
                    function(card) {

                        card.style.display =
                            card.innerText
                            .toLowerCase()
                            .includes(value)
                            ? "grid"
                            : "none";
                    }
                );
        }
    );
}


/* =========================================================
   FLASH
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

<?php

session_start();
include "../db.php";

date_default_timezone_set("Asia/Kolkata");


/* =========================================================
   WARDEN AUTH
   ========================================================= */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "warden"
) {
    header("Location: ../index.php");
    exit;
}

$wardenName = $_SESSION["user_name"] ?? "Warden";


/* =========================================================
   FLASH MESSAGE
   ========================================================= */

$message = "";
$messageType = "";

if (isset($_SESSION["room_change_message"])) {

    $message =
        $_SESSION["room_change_message"];

    $messageType =
        $_SESSION["room_change_message_type"] ?? "";

    unset($_SESSION["room_change_message"]);
    unset($_SESSION["room_change_message_type"]);
}


/* =========================================================
   APPROVE / REJECT
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action =
        $_POST["action"] ?? "";

    $requestId =
        intval($_POST["request_id"] ?? 0);

    $wardenRemark =
        trim($_POST["warden_remark"] ?? "");


    /* =====================================================
       APPROVE
       ===================================================== */

    if (
        $action === "approve" &&
        $requestId > 0
    ) {

        mysqli_begin_transaction($conn);

        try {

            /* LOCK REQUEST */

            $stmt = mysqli_prepare(
                $conn,
                "SELECT
                    id,
                    user_id,
                    current_room_id,
                    requested_room_id,
                    status

                 FROM room_change_requests

                 WHERE id = ?

                 FOR UPDATE"
            );


            if (!$stmt) {
                throw new Exception(
                    "Unable to load request."
                );
            }


            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $requestId
            );

            mysqli_stmt_execute($stmt);

            $result =
                mysqli_stmt_get_result($stmt);

            $request =
                mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);


            if (!$request) {
                throw new Exception(
                    "Room change request not found."
                );
            }


            if ($request["status"] !== "Pending") {
                throw new Exception(
                    "This request has already been processed."
                );
            }


            $studentId =
                intval($request["user_id"]);

            $requestedRoomId =
                intval($request["requested_room_id"]);

            $originalRoomId =
                $request["current_room_id"] !== null
                ? intval($request["current_room_id"])
                : null;


            /* =================================================
               LOCK STUDENT
               ================================================= */

            $stmt = mysqli_prepare(
                $conn,
                "SELECT room_id

                 FROM users

                 WHERE id = ?
                 AND role = 'student'

                 FOR UPDATE"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $studentId
            );

            mysqli_stmt_execute($stmt);

            $result =
                mysqli_stmt_get_result($stmt);

            $student =
                mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);


            if (!$student) {
                throw new Exception(
                    "Student account not found."
                );
            }


            $actualRoomId =
                $student["room_id"] !== null
                ? intval($student["room_id"])
                : null;


            if ($actualRoomId !== $originalRoomId) {
                throw new Exception(
                    "Student's room has changed after this request was submitted."
                );
            }


            /* =================================================
               LOCK REQUESTED ROOM
               ================================================= */

            $stmt = mysqli_prepare(
                $conn,
                "SELECT
                    id,
                    room_number,
                    capacity

                 FROM rooms

                 WHERE id = ?

                 FOR UPDATE"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $requestedRoomId
            );

            mysqli_stmt_execute($stmt);

            $result =
                mysqli_stmt_get_result($stmt);

            $requestedRoom =
                mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);


            if (!$requestedRoom) {
                throw new Exception(
                    "Requested room no longer exists."
                );
            }


            /* =================================================
               CHECK CURRENT CAPACITY
               ================================================= */

            $stmt = mysqli_prepare(
                $conn,
                "SELECT COUNT(*) AS total

                 FROM users

                 WHERE room_id = ?
                 AND role = 'student'"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $requestedRoomId
            );

            mysqli_stmt_execute($stmt);

            $result =
                mysqli_stmt_get_result($stmt);

            $occupancyRow =
                mysqli_fetch_assoc($result);

            $occupancy =
                intval($occupancyRow["total"] ?? 0);

            mysqli_stmt_close($stmt);


            $capacity =
                intval($requestedRoom["capacity"]);


            if ($occupancy >= $capacity) {
                throw new Exception(
                    "Requested room is currently full."
                );
            }


            /* =================================================
               MOVE STUDENT
               ================================================= */

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE users

                 SET room_id = ?

                 WHERE id = ?
                 AND role = 'student'"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $requestedRoomId,
                $studentId
            );

            mysqli_stmt_execute($stmt);

            mysqli_stmt_close($stmt);


            /* =================================================
               APPROVE REQUEST
               ================================================= */

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE room_change_requests

                 SET
                    status = 'Approved',
                    warden_remark = ?,
                    decision_date = NOW()

                 WHERE id = ?
                 AND status = 'Pending'"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "si",
                $wardenRemark,
                $requestId
            );

            mysqli_stmt_execute($stmt);

            mysqli_stmt_close($stmt);


            mysqli_commit($conn);


            $_SESSION["room_change_message"] =
                "Room change approved successfully. Student moved to Room " .
                $requestedRoom["room_number"] .
                ".";

            $_SESSION["room_change_message_type"] =
                "success";


        } catch (Exception $e) {

            mysqli_rollback($conn);

            $_SESSION["room_change_message"] =
                $e->getMessage();

            $_SESSION["room_change_message_type"] =
                "error";
        }


        header("Location: room_change_requests.php");
        exit;
    }


    /* =====================================================
       REJECT
       ===================================================== */

    if (
        $action === "reject" &&
        $requestId > 0
    ) {

        if ($wardenRemark === "") {

            $_SESSION["room_change_message"] =
                "Please enter a reason before rejecting the request.";

            $_SESSION["room_change_message_type"] =
                "error";

            header("Location: room_change_requests.php");
            exit;
        }


        $stmt = mysqli_prepare(
            $conn,
            "UPDATE room_change_requests

             SET
                status = 'Rejected',
                warden_remark = ?,
                decision_date = NOW()

             WHERE id = ?
             AND status = 'Pending'"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "si",
            $wardenRemark,
            $requestId
        );

        mysqli_stmt_execute($stmt);


        if (mysqli_stmt_affected_rows($stmt) > 0) {

            $_SESSION["room_change_message"] =
                "Room change request rejected.";

            $_SESSION["room_change_message_type"] =
                "success";

        } else {

            $_SESSION["room_change_message"] =
                "This request is no longer pending.";

            $_SESSION["room_change_message_type"] =
                "error";
        }

        mysqli_stmt_close($stmt);

        header("Location: room_change_requests.php");
        exit;
    }
}


/* =========================================================
   SUMMARY
   ========================================================= */

$summaryResult = mysqli_query(
    $conn,
    "SELECT

        COUNT(*) AS total,

        COALESCE(
            SUM(status = 'Pending'),
            0
        ) AS pending,

        COALESCE(
            SUM(status = 'Approved'),
            0
        ) AS approved,

        COALESCE(
            SUM(status = 'Rejected'),
            0
        ) AS rejected

     FROM room_change_requests"
);


$summary =
    mysqli_fetch_assoc($summaryResult);


$totalRequests =
    intval($summary["total"] ?? 0);

$pendingRequests =
    intval($summary["pending"] ?? 0);

$approvedRequests =
    intval($summary["approved"] ?? 0);

$rejectedRequests =
    intval($summary["rejected"] ?? 0);


/* =========================================================
   ALL REQUESTS
   ========================================================= */

$requests = mysqli_query(
    $conn,
    "SELECT

        rcr.id,
        rcr.user_id,
        rcr.current_room_id,
        rcr.requested_room_id,
        rcr.reason,
        rcr.status,
        rcr.warden_remark,
        rcr.decision_date,
        rcr.created_at,

        u.name AS student_name,
        u.email AS student_email,
        u.room_id AS actual_room_id,

        old_room.room_number
            AS old_room_number,

        old_room.block
            AS old_block,

        old_room.floor
            AS old_floor,

        new_room.room_number
            AS requested_room_number,

        new_room.block
            AS requested_block,

        new_room.floor
            AS requested_floor,

        new_room.capacity
            AS requested_capacity,

        (
            SELECT COUNT(*)

            FROM users occupants

            WHERE
                occupants.room_id =
                rcr.requested_room_id

            AND occupants.role = 'student'

        ) AS requested_occupancy

     FROM room_change_requests rcr

     INNER JOIN users u
        ON rcr.user_id = u.id

     LEFT JOIN rooms old_room
        ON rcr.current_room_id =
           old_room.id

     LEFT JOIN rooms new_room
        ON rcr.requested_room_id =
           new_room.id

     ORDER BY

        CASE
            WHEN rcr.status = 'Pending' THEN 1
            WHEN rcr.status = 'Approved' THEN 2
            ELSE 3
        END,

        rcr.id DESC"
);


/* =========================================================
   HELPER
   ========================================================= */

function getInitial($name)
{
    $name = trim($name);

    if ($name === "") {
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
Room Change Requests - UniStay
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


html,
body {

    width:100%;
    min-height:100%;
}


body {

    background:
        var(--background);

    color:
        var(--text);

    overflow-x:hidden;
}


.dashboard {

    display:flex;

    width:100%;

    min-height:100vh;
}


/* =========================================================
   SIDEBAR - FIXED
   ========================================================= */

.sidebar {

    width:230px !important;
    min-width:230px !important;

    height:100vh !important;

    position:sticky;

    top:0;

    align-self:flex-start;

    overflow-y:auto !important;
    overflow-x:hidden;

    padding:
        28px 16px !important;

    background:white;

    border-right:
        1px solid
        var(--border);

    z-index:20;
}


.logo {

    position:relative;

    display:flex;

    align-items:center;

    gap:12px;

    margin-bottom:48px;

    padding-left:7px;
}


.logo::after {

    content:"Live  Learn  Belong";

    position:absolute;

    top:32px;
    left:60px;

    color:#7C8796;

    font-size:10px;

    white-space:nowrap;
}


.logo-box {

    width:44px;
    height:44px;

    min-width:44px;

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

    white-space:nowrap;
}


.menu {

    width:100%;
}


.menu a {

    width:100%;

    display:flex !important;

    align-items:center;

    gap:12px;

    padding:
        12px 13px;

    margin-bottom:7px;

    border-radius:10px;

    color:#45576E;

    text-decoration:none !important;

    font-size:13px;

    font-weight:500;

    line-height:1.3;
}


.menu a i {

    width:22px;

    min-width:22px;

    text-align:center;

    font-size:14px;
}


.menu a:hover {

    color:
        var(--teal);

    background:#F0F7F9;
}


.menu a.active {

    color:
        var(--teal);

    background:#DDF0F4;

    font-weight:700;
}


/* =========================================================
   MAIN
   ========================================================= */

.main {

    flex:1;

    width:
        calc(100% - 230px);

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

    min-width:50px;

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

    line-height:1.15;
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

    flex-shrink:0;

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

    min-width:40px;

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

    width:100%;

    display:grid;

    grid-template-columns:
        minmax(0,1fr)
        350px;

    gap:30px;

    align-items:center;

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

    overflow:hidden;
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

    line-height:1.2;
}


.hero p {

    max-width:650px;

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

    border:
        1px solid
        rgba(255,255,255,0.12);
}


.hero-stat {

    padding:13px;

    text-align:center;

    border-radius:11px;

    background:
        rgba(255,255,255,0.08);
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
        repeat(4,minmax(0,1fr));

    gap:14px;

    margin-bottom:20px;
}


.card {

    min-height:112px;

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

    min-width:50px;

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

    color:#986807;

    background:
        var(--mustard-soft);
}


.card:nth-child(3)
.card-icon {

    color:
        var(--green);

    background:
        var(--green-soft);
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

    font-size:11px;

    text-transform:uppercase;
}


.card h2 {

    margin-top:4px;

    font-size:28px;
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

    width:100%;

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

    min-width:43px;

    display:flex;

    align-items:center;

    justify-content:center;

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
   FILTERS
   ========================================================= */

.filters {

    display:flex;

    gap:8px;
}


.search,
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


.search {

    width:220px;
}


.search:focus,
.filter:focus {

    border-color:
        var(--teal);
}


/* =========================================================
   REQUEST LIST
   ========================================================= */

.request-list {

    padding:18px;
}


.request-card {

    margin-bottom:15px;

    padding:18px;

    border-radius:14px;

    background:#FBFCFD;

    border:
        1px solid
        #E1E8EC;
}


.request-card:last-child {

    margin-bottom:0;
}


.request-top {

    display:flex;

    align-items:flex-start;

    justify-content:space-between;

    gap:15px;
}


.student-info {

    display:flex;

    align-items:center;

    gap:11px;
}


.avatar {

    width:45px;
    height:45px;

    min-width:45px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:12px;

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

    font-size:15px;

    font-weight:800;
}


.student-email {

    margin-top:3px;

    color:
        var(--muted);

    font-size:11px;
}


.status {

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:
        6px 9px;

    border-radius:20px;

    font-size:10px;

    font-weight:700;
}


.status.pending {

    color:#966604;

    background:
        var(--mustard-soft);
}


.status.approved {

    color:#267A5D;

    background:
        var(--green-soft);
}


.status.rejected {

    color:#A64048;

    background:
        var(--red-soft);
}


/* =========================================================
   ROOM ROUTE
   ========================================================= */

.room-route {

    display:grid;

    grid-template-columns:
        minmax(0,1fr)
        auto
        minmax(0,1fr);

    gap:12px;

    align-items:center;

    margin-top:15px;

    padding:14px;

    border-radius:12px;

    background:white;

    border:
        1px solid
        #E5EBEE;
}


.room-box {

    min-width:0;

    padding:12px;

    border-radius:10px;

    background:#F5F8FA;
}


.room-label {

    color:
        var(--muted);

    font-size:9px;

    font-weight:700;

    text-transform:uppercase;
}


.room-number {

    margin-top:4px;

    font-size:18px;

    font-weight:800;
}


.room-meta {

    margin-top:5px;

    color:
        var(--muted);

    font-size:10px;
}


.route-arrow {

    color:
        var(--teal);

    font-size:18px;
}


/* =========================================================
   REASON / ACTIONS
   ========================================================= */

.reason {

    margin-top:13px;

    padding:12px;

    border-radius:10px;

    color:#526479;

    background:#F5F8FA;

    font-size:12px;

    line-height:1.55;
}


.request-date {

    margin-top:10px;

    color:
        var(--muted);

    font-size:10px;
}


.warning-box {

    margin-top:13px;

    padding:11px 12px;

    border-radius:9px;

    color:#936408;

    background:
        var(--mustard-soft);

    font-size:11px;

    line-height:1.5;
}


.action-section {

    display:grid;

    grid-template-columns:
        minmax(0,1fr)
        auto
        auto;

    gap:8px;

    align-items:end;

    margin-top:14px;

    padding-top:14px;

    border-top:
        1px solid
        #E5EBEE;
}


.remark-input {

    width:100%;

    min-height:39px;

    padding:
        10px 11px;

    border:
        1px solid
        #D5E0E5;

    border-radius:9px;

    outline:none;

    background:white;

    font-size:12px;
}


.remark-input:focus {

    border-color:
        var(--teal);
}


.action-btn {

    min-height:39px;

    padding:
        9px 14px;

    border:none;

    border-radius:9px;

    cursor:pointer;

    font-size:11px;

    font-weight:750;
}


.approve-btn {

    color:white;

    background:
        var(--green);
}


.reject-btn {

    color:white;

    background:
        var(--red);
}


.processed-note {

    margin-top:13px;

    padding:
        11px 12px;

    border-radius:9px;

    color:
        var(--muted);

    background:#F4F7F8;

    font-size:11px;

    line-height:1.55;
}


/* =========================================================
   EMPTY
   ========================================================= */

.empty {

    padding:
        50px 20px;

    text-align:center;

    color:
        var(--muted);
}


.empty i {

    margin-bottom:10px;

    color:
        var(--teal);

    font-size:34px;
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


    .cards {

        grid-template-columns:
            repeat(2,1fr);
    }
}


@media(max-width:800px) {

    .sidebar {

        display:none;
    }


    .main {

        width:100%;

        padding:18px;
    }


    .panel-head {

        align-items:flex-start;

        flex-direction:column;
    }


    .filters {

        width:100%;

        flex-direction:column;
    }


    .search,
    .filter {

        width:100%;
    }


    .action-section {

        grid-template-columns:1fr;
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


    .room-route {

        grid-template-columns:1fr;
    }


    .route-arrow {

        text-align:center;

        transform:rotate(90deg);
    }


    .profile > div:last-child {

        display:none;
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

<i class="fa-solid fa-arrow-right-arrow-left"></i>

</div>

<div>

<h1>
Room Change Requests
</h1>

<p>
Review and manage student room change requests.
</p>

</div>

</div>


<div class="profile">

<div class="profile-avatar">

<?php echo getInitial($wardenName); ?>

</div>

<div>

<strong>

<?php
echo htmlspecialchars($wardenName);
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
Smart Room Management
</div>

<h2>
Manage room movement safely.
</h2>

<p>

Review student requests, check requested room
availability and approve room changes without
overbooking hostel rooms.

</p>

<div class="hero-chip">

<i class="fa-solid fa-shield-halved"></i>

Capacity Checked Before Approval

</div>

</div>


<div class="hero-stats">

<div class="hero-stat">

<strong>
<?php echo $totalRequests; ?>
</strong>

<span>
Total Requests
</span>

</div>


<div class="hero-stat">

<strong>
<?php echo $pendingRequests; ?>
</strong>

<span>
Pending
</span>

</div>


<div class="hero-stat">

<strong>
<?php echo $approvedRequests; ?>
</strong>

<span>
Approved
</span>

</div>


<div class="hero-stat">

<strong>
<?php echo $rejectedRequests; ?>
</strong>

<span>
Rejected
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

<i class="fa-solid fa-file-lines"></i>

</div>

<div>

<p>
Total Requests
</p>

<h2>
<?php echo $totalRequests; ?>
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
<?php echo $pendingRequests; ?>
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
<?php echo $approvedRequests; ?>
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
<?php echo $rejectedRequests; ?>
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
echo htmlspecialchars($message);
?>

</div>

<?php } ?>



<!-- =========================================================
     REQUESTS PANEL
     ========================================================= -->

<div class="panel">

<div class="panel-head">

<div class="panel-heading">

<div class="panel-icon">

<i class="fa-solid fa-bed"></i>

</div>

<div>

<h2>
Student Requests
</h2>

<p>
Approve or reject room change applications.
</p>

</div>

</div>


<div class="filters">

<input
    type="text"
    id="searchBox"
    class="search"
    placeholder="Search student or room..."
>

<select
    id="statusFilter"
    class="filter"
>

<option value="all">
All Status
</option>

<option value="pending">
Pending
</option>

<option value="approved">
Approved
</option>

<option value="rejected">
Rejected
</option>

</select>

</div>

</div>



<?php

if (
    $requests &&
    mysqli_num_rows($requests) > 0
) {

?>

<div class="request-list">


<?php

while (
    $request =
    mysqli_fetch_assoc($requests)
) {

    $statusClass =
        strtolower($request["status"]);


    $occupancy =
        intval(
            $request["requested_occupancy"] ?? 0
        );


    $capacity =
        intval(
            $request["requested_capacity"] ?? 0
        );


    $roomFull =
        $capacity > 0 &&
        $occupancy >= $capacity;


    $actualRoom =
        $request["actual_room_id"] !== null
        ? intval($request["actual_room_id"])
        : null;


    $requestedOldRoom =
        $request["current_room_id"] !== null
        ? intval($request["current_room_id"])
        : null;


    $roomChangedSinceRequest =
        (
            $request["status"] === "Pending" &&
            $actualRoom !== $requestedOldRoom
        );

?>


<div
    class="request-card"
    data-status="<?php echo $statusClass; ?>"
>


<div class="request-top">

<div class="student-info">

<div class="avatar">

<?php
echo getInitial(
    $request["student_name"]
);
?>

</div>

<div>

<div class="student-name">

<?php
echo htmlspecialchars(
    $request["student_name"]
);
?>

</div>

<div class="student-email">

<?php
echo htmlspecialchars(
    $request["student_email"]
);
?>

</div>

</div>

</div>


<span
    class="status <?php echo $statusClass; ?>"
>

<?php
echo htmlspecialchars(
    $request["status"]
);
?>

</span>

</div>



<!-- ROOM CHANGE -->

<div class="room-route">

<div class="room-box">

<div class="room-label">
Current Room
</div>

<div class="room-number">

<?php

echo !empty(
    $request["old_room_number"]
)
    ? htmlspecialchars(
        $request["old_room_number"]
    )
    : "Not Assigned";

?>

</div>

<div class="room-meta">

<?php

if (!empty($request["old_room_number"])) {

    echo htmlspecialchars(
        $request["old_block"]
    );

    echo " • ";

    echo htmlspecialchars(
        $request["old_floor"]
    );

} else {

    echo "No current room";
}

?>

</div>

</div>


<div class="route-arrow">

<i class="fa-solid fa-arrow-right"></i>

</div>


<div class="room-box">

<div class="room-label">
Requested Room
</div>

<div class="room-number">

<?php

echo !empty(
    $request["requested_room_number"]
)
    ? htmlspecialchars(
        $request["requested_room_number"]
    )
    : "Unavailable";

?>

</div>

<div class="room-meta">

<?php

if (
    !empty(
        $request["requested_room_number"]
    )
) {

    echo htmlspecialchars(
        $request["requested_block"]
    );

    echo " • ";

    echo htmlspecialchars(
        $request["requested_floor"]
    );

    echo " • ";

    echo $occupancy;

    echo "/";

    echo $capacity;

    echo " occupied";
}

?>

</div>

</div>

</div>



<div class="reason">

<strong>
Student Reason:
</strong>

<br>

<?php

echo nl2br(
    htmlspecialchars(
        $request["reason"]
    )
);

?>

</div>


<div class="request-date">

Requested on

<?php

echo date(
    "d M Y, h:i A",
    strtotime(
        $request["created_at"]
    )
);

?>

</div>



<?php if ($request["status"] === "Pending") { ?>


<?php if ($roomChangedSinceRequest) { ?>

<div class="warning-box">

<i class="fa-solid fa-triangle-exclamation"></i>

Student's current room has changed after this
request was submitted. This old request cannot
be approved.

</div>

<?php } elseif ($roomFull) { ?>

<div class="warning-box">

<i class="fa-solid fa-triangle-exclamation"></i>

Requested room is currently full. Approval is
disabled until a bed becomes available.

</div>

<?php } ?>


<div class="action-section">


<input
    type="text"
    id="remark_<?php echo intval($request["id"]); ?>"
    class="remark-input"
    placeholder="Warden remark (required for rejection)"
>


<form
    method="POST"
    onsubmit="
        return prepareRoomAction(
            this,
            <?php echo intval($request["id"]); ?>,
            'approve'
        );
    "
>

<input
    type="hidden"
    name="action"
    value="approve"
>

<input
    type="hidden"
    name="request_id"
    value="<?php echo intval($request["id"]); ?>"
>

<input
    type="hidden"
    name="warden_remark"
    class="hidden-remark"
>

<button
    type="submit"
    class="action-btn approve-btn"

    <?php

    if (
        $roomFull ||
        $roomChangedSinceRequest
    ) {

        echo "disabled
              style='opacity:.45;
              cursor:not-allowed;'";
    }

    ?>
>

<i class="fa-solid fa-check"></i>

Approve

</button>

</form>



<form
    method="POST"
    onsubmit="
        return prepareRoomAction(
            this,
            <?php echo intval($request["id"]); ?>,
            'reject'
        );
    "
>

<input
    type="hidden"
    name="action"
    value="reject"
>

<input
    type="hidden"
    name="request_id"
    value="<?php echo intval($request["id"]); ?>"
>

<input
    type="hidden"
    name="warden_remark"
    class="hidden-remark"
>

<button
    type="submit"
    class="action-btn reject-btn"
>

<i class="fa-solid fa-xmark"></i>

Reject

</button>

</form>

</div>


<?php } else { ?>

<div class="processed-note">

<strong>
Warden Remark:
</strong>

<?php

echo !empty(
    $request["warden_remark"]
)
    ? htmlspecialchars(
        $request["warden_remark"]
    )
    : "No remark";

?>


<?php if (!empty($request["decision_date"])) { ?>

<br><br>

<strong>
Decision:
</strong>

<?php

echo date(
    "d M Y, h:i A",
    strtotime(
        $request["decision_date"]
    )
);

?>

<?php } ?>

</div>

<?php } ?>


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
No matching requests
</h3>

<p>
Try another search or status filter.
</p>

</div>


<?php } else { ?>

<div class="empty">

<i class="fa-solid fa-bed"></i>

<h3>
No room change requests
</h3>

<p>
Student room change requests will appear here.
</p>

</div>

<?php } ?>


</div>

</div>

</div>



<script>

/* =========================================================
   APPROVE / REJECT
   ========================================================= */

function prepareRoomAction(
    form,
    requestId,
    action
) {

    const remarkInput =
        document.getElementById(
            "remark_" + requestId
        );


    const remark =
        remarkInput
        ? remarkInput.value.trim()
        : "";


    const hiddenRemark =
        form.querySelector(
            ".hidden-remark"
        );


    hiddenRemark.value =
        remark;


    if (
        action === "reject" &&
        remark === ""
    ) {

        alert(
            "Please enter a reason before rejecting the request."
        );

        remarkInput.focus();

        return false;
    }


    if (action === "approve") {

        return confirm(
            "Approve this room change? The student's actual room will be updated immediately."
        );
    }


    return confirm(
        "Reject this room change request?"
    );
}


/* =========================================================
   SEARCH / FILTER
   ========================================================= */

const searchBox =
    document.getElementById(
        "searchBox"
    );


const statusFilter =
    document.getElementById(
        "statusFilter"
    );


function filterRequests() {

    const search =
        searchBox.value
        .toLowerCase()
        .trim();


    const selectedStatus =
        statusFilter.value;


    const cards =
        document.querySelectorAll(
            ".request-card"
        );


    let visible = 0;


    cards.forEach(
        function(card) {

            const text =
                card.innerText
                .toLowerCase();


            const cardStatus =
                card.dataset.status;


            const searchMatches =
                text.includes(search);


            const statusMatches =
                selectedStatus === "all" ||
                cardStatus === selectedStatus;


            if (
                searchMatches &&
                statusMatches
            ) {

                card.style.display =
                    "block";

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
        filterRequests
    );
}


if (statusFilter) {

    statusFilter.addEventListener(
        "change",
        filterRequests
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
                "opacity .4s ease";

        },
        3500
    );
}

</script>

</body>

</html>
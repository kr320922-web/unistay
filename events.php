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

$wardenId =
    intval($_SESSION["user_id"]);

$wardenName =
    $_SESSION["user_name"] ?? "Warden";


/* =========================================================
   FLASH MESSAGE
   ========================================================= */

$message = "";
$messageType = "";

if (isset($_SESSION["event_message"])) {

    $message =
        $_SESSION["event_message"];

    $messageType =
        $_SESSION["event_message_type"] ?? "";

    unset($_SESSION["event_message"]);
    unset($_SESSION["event_message_type"]);
}


/* =========================================================
   ADD / UPDATE / STATUS / DELETE
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action =
        $_POST["action"] ?? "";


    /* =====================================================
       ADD EVENT
       ===================================================== */

    if ($action === "add_event") {

        $title =
            trim($_POST["title"] ?? "");

        $category =
            trim($_POST["category"] ?? "");

        $description =
            trim($_POST["description"] ?? "");

        $venue =
            trim($_POST["venue"] ?? "");

        $eventDate =
            trim($_POST["event_date"] ?? "");

        $startTime =
            trim($_POST["start_time"] ?? "");

        $endTime =
            trim($_POST["end_time"] ?? "");

        $registrationDeadline =
            trim(
                $_POST["registration_deadline"] ?? ""
            );

        $capacityRaw =
            trim($_POST["capacity"] ?? "");


        $allowedCategories = [
            "Cultural",
            "Sports",
            "Academic",
            "Workshop",
            "Celebration",
            "Other"
        ];


        if (
            $title === "" ||
            !in_array(
                $category,
                $allowedCategories,
                true
            ) ||
            $description === "" ||
            $venue === "" ||
            $eventDate === "" ||
            $startTime === ""
        ) {

            $_SESSION["event_message"] =
                "Please fill all required event details.";

            $_SESSION["event_message_type"] =
                "error";

            header("Location: events.php");
            exit;
        }


        if ($eventDate < date("Y-m-d")) {

            $_SESSION["event_message"] =
                "Event date cannot be in the past.";

            $_SESSION["event_message_type"] =
                "error";

            header("Location: events.php");
            exit;
        }


        if (
            $registrationDeadline !== "" &&
            $registrationDeadline > $eventDate
        ) {

            $_SESSION["event_message"] =
                "Registration deadline cannot be after event date.";

            $_SESSION["event_message_type"] =
                "error";

            header("Location: events.php");
            exit;
        }


        if (
            $endTime !== "" &&
            $endTime <= $startTime
        ) {

            $_SESSION["event_message"] =
                "End time must be after start time.";

            $_SESSION["event_message_type"] =
                "error";

            header("Location: events.php");
            exit;
        }


        $capacity = null;

        if ($capacityRaw !== "") {

            $capacity =
                intval($capacityRaw);

            if ($capacity <= 0) {

                $_SESSION["event_message"] =
                    "Capacity must be greater than 0.";

                $_SESSION["event_message_type"] =
                    "error";

                header("Location: events.php");
                exit;
            }
        }


        $endTimeValue =
            ($endTime === "")
            ? null
            : $endTime;

        $deadlineValue =
            ($registrationDeadline === "")
            ? null
            : $registrationDeadline;


        $stmt =
            mysqli_prepare(
                $conn,
                "INSERT INTO events
                (
                    title,
                    category,
                    description,
                    venue,
                    event_date,
                    start_time,
                    end_time,
                    registration_deadline,
                    capacity,
                    status,
                    created_by
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?)"
            );


        if (!$stmt) {

            die(
                "Event query error: " .
                mysqli_error($conn)
            );
        }


        mysqli_stmt_bind_param(
            $stmt,
            "ssssssssii",
            $title,
            $category,
            $description,
            $venue,
            $eventDate,
            $startTime,
            $endTimeValue,
            $deadlineValue,
            $capacity,
            $wardenId
        );


        if (mysqli_stmt_execute($stmt)) {

            $_SESSION["event_message"] =
                "Event created successfully.";

            $_SESSION["event_message_type"] =
                "success";

        } else {

            $_SESSION["event_message"] =
                "Unable to create event.";

            $_SESSION["event_message_type"] =
                "error";
        }


        mysqli_stmt_close($stmt);

        header("Location: events.php");
        exit;
    }


    /* =====================================================
       UPDATE EVENT
       ===================================================== */

    if ($action === "update_event") {

        $eventId =
            intval($_POST["event_id"] ?? 0);

        $title =
            trim($_POST["title"] ?? "");

        $category =
            trim($_POST["category"] ?? "");

        $description =
            trim($_POST["description"] ?? "");

        $venue =
            trim($_POST["venue"] ?? "");

        $eventDate =
            trim($_POST["event_date"] ?? "");

        $startTime =
            trim($_POST["start_time"] ?? "");

        $endTime =
            trim($_POST["end_time"] ?? "");

        $registrationDeadline =
            trim(
                $_POST["registration_deadline"] ?? ""
            );

        $capacityRaw =
            trim($_POST["capacity"] ?? "");


        $allowedCategories = [
            "Cultural",
            "Sports",
            "Academic",
            "Workshop",
            "Celebration",
            "Other"
        ];


        if (
            $eventId <= 0 ||
            $title === "" ||
            !in_array(
                $category,
                $allowedCategories,
                true
            ) ||
            $description === "" ||
            $venue === "" ||
            $eventDate === "" ||
            $startTime === ""
        ) {

            $_SESSION["event_message"] =
                "Please enter valid event details.";

            $_SESSION["event_message_type"] =
                "error";

            header("Location: events.php");
            exit;
        }


        if (
            $registrationDeadline !== "" &&
            $registrationDeadline > $eventDate
        ) {

            $_SESSION["event_message"] =
                "Registration deadline cannot be after event date.";

            $_SESSION["event_message_type"] =
                "error";

            header(
                "Location: events.php?edit_id=" .
                $eventId
            );

            exit;
        }


        if (
            $endTime !== "" &&
            $endTime <= $startTime
        ) {

            $_SESSION["event_message"] =
                "End time must be after start time.";

            $_SESSION["event_message_type"] =
                "error";

            header(
                "Location: events.php?edit_id=" .
                $eventId
            );

            exit;
        }


        $capacity = null;

        if ($capacityRaw !== "") {

            $capacity =
                intval($capacityRaw);


            /* CURRENT REGISTRATIONS */

            $countStmt =
                mysqli_prepare(
                    $conn,
                    "SELECT COUNT(*) AS total
                     FROM event_registrations
                     WHERE event_id = ?"
                );

            mysqli_stmt_bind_param(
                $countStmt,
                "i",
                $eventId
            );

            mysqli_stmt_execute(
                $countStmt
            );

            $countResult =
                mysqli_stmt_get_result(
                    $countStmt
                );

            $registeredCount =
                intval(
                    mysqli_fetch_assoc(
                        $countResult
                    )["total"]
                );

            mysqli_stmt_close(
                $countStmt
            );


            if (
                $capacity <= 0 ||
                $capacity < $registeredCount
            ) {

                $_SESSION["event_message"] =
                    "Capacity cannot be lower than current registrations (" .
                    $registeredCount .
                    ").";

                $_SESSION["event_message_type"] =
                    "error";

                header(
                    "Location: events.php?edit_id=" .
                    $eventId
                );

                exit;
            }
        }


        $endTimeValue =
            ($endTime === "")
            ? null
            : $endTime;

        $deadlineValue =
            ($registrationDeadline === "")
            ? null
            : $registrationDeadline;


        $stmt =
            mysqli_prepare(
                $conn,
                "UPDATE events
                 SET
                    title = ?,
                    category = ?,
                    description = ?,
                    venue = ?,
                    event_date = ?,
                    start_time = ?,
                    end_time = ?,
                    registration_deadline = ?,
                    capacity = ?
                 WHERE id = ?"
            );


        mysqli_stmt_bind_param(
            $stmt,
            "ssssssssii",
            $title,
            $category,
            $description,
            $venue,
            $eventDate,
            $startTime,
            $endTimeValue,
            $deadlineValue,
            $capacity,
            $eventId
        );


        if (mysqli_stmt_execute($stmt)) {

            $_SESSION["event_message"] =
                "Event updated successfully.";

            $_SESSION["event_message_type"] =
                "success";

        } else {

            $_SESSION["event_message"] =
                "Unable to update event.";

            $_SESSION["event_message_type"] =
                "error";
        }


        mysqli_stmt_close($stmt);

        header("Location: events.php");
        exit;
    }


    /* =====================================================
       CHANGE STATUS
       ===================================================== */

    if ($action === "change_status") {

        $eventId =
            intval($_POST["event_id"] ?? 0);

        $status =
            trim($_POST["status"] ?? "");


        if (
            $eventId <= 0 ||
            !in_array(
                $status,
                [
                    "Scheduled",
                    "Cancelled"
                ],
                true
            )
        ) {

            $_SESSION["event_message"] =
                "Invalid event status.";

            $_SESSION["event_message_type"] =
                "error";

        } else {

            $stmt =
                mysqli_prepare(
                    $conn,
                    "UPDATE events
                     SET status = ?
                     WHERE id = ?"
                );

            mysqli_stmt_bind_param(
                $stmt,
                "si",
                $status,
                $eventId
            );


            if (mysqli_stmt_execute($stmt)) {

                $_SESSION["event_message"] =
                    "Event status updated.";

                $_SESSION["event_message_type"] =
                    "success";

            } else {

                $_SESSION["event_message"] =
                    "Unable to update event status.";

                $_SESSION["event_message_type"] =
                    "error";
            }


            mysqli_stmt_close($stmt);
        }


        header("Location: events.php");
        exit;
    }


    /* =====================================================
       DELETE EVENT
       ===================================================== */

    if ($action === "delete_event") {

        $eventId =
            intval($_POST["event_id"] ?? 0);


        if ($eventId > 0) {

            $stmt =
                mysqli_prepare(
                    $conn,
                    "DELETE FROM events
                     WHERE id = ?"
                );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $eventId
            );


            if (mysqli_stmt_execute($stmt)) {

                $_SESSION["event_message"] =
                    "Event deleted successfully.";

                $_SESSION["event_message_type"] =
                    "success";

            } else {

                $_SESSION["event_message"] =
                    "Unable to delete event.";

                $_SESSION["event_message_type"] =
                    "error";
            }


            mysqli_stmt_close($stmt);
        }


        header("Location: events.php");
        exit;
    }
}


/* =========================================================
   EDIT EVENT
   ========================================================= */

$editEvent = null;

$editId =
    intval(
        $_GET["edit_id"] ?? 0
    );


if ($editId > 0) {

    $stmt =
        mysqli_prepare(
            $conn,
            "SELECT *
             FROM events
             WHERE id = ?
             LIMIT 1"
        );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $editId
    );

    mysqli_stmt_execute($stmt);

    $editResult =
        mysqli_stmt_get_result(
            $stmt
        );

    $editEvent =
        mysqli_fetch_assoc(
            $editResult
        );

    mysqli_stmt_close($stmt);
}


/* =========================================================
   PARTICIPANT LIST
   ========================================================= */

$participantEvent = null;
$participants = null;

$participantsId =
    intval(
        $_GET["participants_id"] ?? 0
    );


if ($participantsId > 0) {

    $stmt =
        mysqli_prepare(
            $conn,
            "SELECT
                id,
                title,
                event_date,
                venue,
                capacity
             FROM events
             WHERE id = ?
             LIMIT 1"
        );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $participantsId
    );

    mysqli_stmt_execute($stmt);

    $eventResult =
        mysqli_stmt_get_result(
            $stmt
        );

    $participantEvent =
        mysqli_fetch_assoc(
            $eventResult
        );

    mysqli_stmt_close($stmt);


    if ($participantEvent) {

        $stmt =
            mysqli_prepare(
                $conn,
                "SELECT
                    er.id,
                    er.registered_at,
                    u.name,
                    u.email,
                    r.room_number

                 FROM event_registrations AS er

                 INNER JOIN users AS u
                    ON er.user_id = u.id

                 LEFT JOIN rooms AS r
                    ON u.room_id = r.id

                 WHERE er.event_id = ?

                 ORDER BY er.registered_at ASC"
            );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $participantsId
        );

        mysqli_stmt_execute($stmt);

        $participants =
            mysqli_stmt_get_result(
                $stmt
            );
    }
}


/* =========================================================
   SUMMARY
   ========================================================= */

$summaryResult =
    mysqli_query(
        $conn,
        "SELECT

            COUNT(*) AS total,

            COALESCE(
                SUM(
                    CASE
                        WHEN status = 'Scheduled'
                        AND event_date >= CURDATE()
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS upcoming,

            COALESCE(
                SUM(
                    CASE
                        WHEN event_date = CURDATE()
                        AND status = 'Scheduled'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS today_count,

            COALESCE(
                SUM(
                    CASE
                        WHEN status = 'Cancelled'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS cancelled

         FROM events"
    );


$summary =
    mysqli_fetch_assoc(
        $summaryResult
    );


$totalEvents =
    intval(
        $summary["total"] ?? 0
    );

$upcomingEvents =
    intval(
        $summary["upcoming"] ?? 0
    );

$todayEvents =
    intval(
        $summary["today_count"] ?? 0
    );

$cancelledEvents =
    intval(
        $summary["cancelled"] ?? 0
    );


/* =========================================================
   ALL EVENTS
   ========================================================= */

$events =
    mysqli_query(
        $conn,
        "SELECT

            e.*,

            COUNT(er.id) AS registered_count

         FROM events AS e

         LEFT JOIN event_registrations AS er
            ON e.id = er.event_id

         GROUP BY e.id

         ORDER BY

            CASE
                WHEN e.status = 'Scheduled'
                AND e.event_date >= CURDATE()
                THEN 1

                WHEN e.status = 'Scheduled'
                THEN 2

                ELSE 3
            END,

            e.event_date ASC,
            e.start_time ASC"
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
        substr(
            $name,
            0,
            1
        )
    );
}


function getCategoryIcon($category)
{
    switch ($category) {

        case "Cultural":
            return "fa-masks-theater";

        case "Sports":
            return "fa-volleyball";

        case "Academic":
            return "fa-graduation-cap";

        case "Workshop":
            return "fa-laptop-code";

        case "Celebration":
            return "fa-cake-candles";

        default:
            return "fa-calendar-star";
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
Events - UniStay
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
        1fr
        350px;

    align-items:center;

    gap:28px;

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

    color:#F7D374;

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
        var(--blue);
}


.card:nth-child(2)
.card-icon {

    color:
        var(--blue);

    background:
        var(--blue-soft);
}


.card:nth-child(3) {

    border-top:
        4px solid
        var(--mustard);
}


.card:nth-child(3)
.card-icon {

    color:#956500;

    background:
        var(--mustard-soft);
}


.card:nth-child(4) {

    border-top:
        4px solid
        var(--red);
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
   GRID
   ========================================================= */

.content-grid {

    display:grid;

    grid-template-columns:
        390px
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

.event-form {

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

    box-shadow:
        0 0 0 3px
        rgba(14,96,119,0.08);
}


textarea.form-control {

    min-height:100px;

    resize:vertical;
}


.submit-btn {

    width:100%;

    padding:12px;

    border:none;

    border-radius:9px;

    color:white;

    background:
        linear-gradient(
            135deg,
            var(--teal),
            var(--teal2)
        );

    cursor:pointer;

    font-size:13px;

    font-weight:750;
}


.cancel-edit {

    display:block;

    margin-top:9px;

    padding:10px;

    text-align:center;

    border-radius:9px;

    color:
        var(--muted);

    background:#F1F4F6;

    text-decoration:none;

    font-size:12px;

    font-weight:650;
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

    font-size:12px;
}


.search {

    width:220px;
}


/* =========================================================
   EVENTS
   ========================================================= */

.event-list {

    padding:18px;
}


.event-card {

    margin-bottom:14px;

    padding:16px;

    border-radius:14px;

    background:#FBFCFD;

    border:
        1px solid
        #E1E8EC;
}


.event-card:last-child {

    margin-bottom:0;
}


.event-top {

    display:flex;

    justify-content:space-between;

    gap:15px;

    align-items:flex-start;
}


.event-main {

    display:flex;

    gap:13px;
}


.event-icon {

    width:52px;
    height:52px;

    flex-shrink:0;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:13px;

    color:
        var(--teal);

    background:
        var(--teal-soft);

    font-size:19px;
}


.event-title {

    font-size:17px;

    font-weight:800;
}


.category {

    display:inline-flex;

    margin-top:5px;

    padding:
        5px 8px;

    border-radius:20px;

    color:
        var(--teal);

    background:
        var(--teal-soft);

    font-size:10px;

    font-weight:700;
}


.status {

    display:inline-flex;

    padding:
        6px 9px;

    border-radius:20px;

    font-size:10px;

    font-weight:700;
}


.status.scheduled {

    color:#267A5D;

    background:
        var(--green-soft);
}


.status.cancelled {

    color:#A64048;

    background:
        var(--red-soft);
}


.event-meta {

    display:flex;

    flex-wrap:wrap;

    gap:8px 15px;

    margin-top:12px;

    color:
        var(--muted);

    font-size:11px;
}


.event-description {

    margin-top:11px;

    color:#526479;

    font-size:12px;

    line-height:1.5;
}


.registration-row {

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:12px;

    margin-top:13px;

    padding-top:12px;

    border-top:
        1px solid
        #E4EAED;
}


.registration-info {

    font-size:12px;

    color:
        var(--muted);
}


.registration-info strong {

    color:
        var(--text);
}


.actions {

    display:flex;

    align-items:center;

    gap:7px;

    flex-wrap:wrap;
}


.action-link,
.action-btn {

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:5px;

    min-height:34px;

    padding:
        7px 10px;

    border:none;

    border-radius:8px;

    cursor:pointer;

    text-decoration:none;

    font-size:10px;

    font-weight:700;
}


.participant-btn {

    color:
        var(--teal);

    background:
        var(--teal-soft);
}


.edit-btn {

    color:
        var(--blue);

    background:
        var(--blue-soft);
}


.cancel-btn {

    color:#A64048;

    background:
        var(--red-soft);
}


.restore-btn {

    color:#267A5D;

    background:
        var(--green-soft);
}


.delete-btn {

    color:#A64048;

    background:#F6E4E6;
}


/* =========================================================
   PARTICIPANTS
   ========================================================= */

.participant-panel {

    margin-top:20px;
}


.participant-table {

    width:100%;

    border-collapse:collapse;
}


.participant-table th {

    padding:
        12px 15px;

    text-align:left;

    color:
        var(--muted);

    background:#F6F9FA;

    font-size:11px;

    text-transform:uppercase;
}


.participant-table td {

    padding:
        13px 15px;

    border-top:
        1px solid
        #EDF1F3;

    font-size:12px;

    color:#4C5D70;
}


.student-cell {

    display:flex;

    align-items:center;

    gap:9px;
}


.student-avatar {

    width:34px;
    height:34px;

    flex-shrink:0;

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

    font-size:11px;

    font-weight:800;
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

@media(max-width:1200px) {

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


    .panel-head {

        align-items:flex-start;

        flex-direction:column;
    }


    .filters {

        width:100%;
    }


    .search {

        flex:1;
        width:auto;
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


    .hero h2 {

        font-size:27px;
    }


    .form-row {

        grid-template-columns:1fr;
    }


    .filters {

        flex-direction:column;
    }


    .search,
    .filter {

        width:100%;
    }


    .event-top,
    .registration-row {

        flex-direction:column;
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


<div class="page-title">


<div class="page-icon">

<i class="fa-solid fa-calendar-days"></i>

</div>


<div>

<h1>
Events
</h1>

<p>
Create hostel events and manage registrations.
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
<?php echo htmlspecialchars($wardenName); ?>
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
Hostel Community
</div>


<h2>
Plan activities students look forward to.
</h2>


<p>

Create cultural events, sports activities,
workshops and celebrations while keeping
registrations and participant capacity organised.

</p>


<div class="hero-chip">

<i class="fa-solid fa-users"></i>

Create • Register • Participate

</div>


</div>



<div class="hero-stats">


<div class="hero-stat">

<strong>
<?php echo $totalEvents; ?>
</strong>

<span>
Total
</span>

</div>


<div class="hero-stat">

<strong>
<?php echo $upcomingEvents; ?>
</strong>

<span>
Upcoming
</span>

</div>


<div class="hero-stat">

<strong>
<?php echo $todayEvents; ?>
</strong>

<span>
Today
</span>

</div>


<div class="hero-stat">

<strong>
<?php echo $cancelledEvents; ?>
</strong>

<span>
Cancelled
</span>

</div>


</div>


</div>



<!-- SUMMARY -->

<div class="cards">


<div class="card">

<div class="card-icon">

<i class="fa-solid fa-calendar-days"></i>

</div>

<div>

<p>Total Events</p>

<h2>
<?php echo $totalEvents; ?>
</h2>

</div>

</div>



<div class="card">

<div class="card-icon">

<i class="fa-solid fa-calendar-check"></i>

</div>

<div>

<p>Upcoming</p>

<h2>
<?php echo $upcomingEvents; ?>
</h2>

</div>

</div>



<div class="card">

<div class="card-icon">

<i class="fa-solid fa-star"></i>

</div>

<div>

<p>Today</p>

<h2>
<?php echo $todayEvents; ?>
</h2>

</div>

</div>



<div class="card">

<div class="card-icon">

<i class="fa-solid fa-ban"></i>

</div>

<div>

<p>Cancelled</p>

<h2>
<?php echo $cancelledEvents; ?>
</h2>

</div>

</div>


</div>



<!-- MESSAGE -->

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



<div class="content-grid">


<!-- =========================================================
     CREATE / EDIT EVENT
     ========================================================= -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">

<div class="panel-icon">

<i
    class="fa-solid <?php
    echo $editEvent
        ? 'fa-pen'
        : 'fa-plus';
    ?>"
></i>

</div>


<div>

<h2>

<?php
echo $editEvent
    ? "Edit Event"
    : "Create Event";
?>

</h2>

<p>
Enter event information below.
</p>

</div>

</div>


</div>



<form
    method="POST"
    class="event-form"
>


<input
    type="hidden"
    name="action"
    value="<?php
    echo $editEvent
        ? 'update_event'
        : 'add_event';
    ?>"
>


<?php if ($editEvent) { ?>

<input
    type="hidden"
    name="event_id"
    value="<?php
    echo intval(
        $editEvent["id"]
    );
    ?>"
>

<?php } ?>


<div class="form-group">

<label>
Event Title
</label>

<input
    type="text"
    name="title"
    class="form-control"
    placeholder="Example: Hostel Fresher Night"
    value="<?php
    echo htmlspecialchars(
        $editEvent["title"] ?? ""
    );
    ?>"
    required
>

</div>



<div class="form-group">

<label>
Category
</label>

<select
    name="category"
    class="form-control"
    required
>

<option value="">
Select Category
</option>

<?php

$categories = [
    "Cultural",
    "Sports",
    "Academic",
    "Workshop",
    "Celebration",
    "Other"
];

foreach ($categories as $category) {

?>

<option
    value="<?php echo $category; ?>"
    <?php
    echo (
        ($editEvent["category"] ?? "")
        === $category
    )
        ? "selected"
        : "";
    ?>
>

<?php echo $category; ?>

</option>

<?php } ?>

</select>

</div>



<div class="form-group">

<label>
Description
</label>

<textarea
    name="description"
    class="form-control"
    placeholder="Write a short description..."
    required
><?php
echo htmlspecialchars(
    $editEvent["description"] ?? ""
);
?></textarea>

</div>



<div class="form-group">

<label>
Venue
</label>

<input
    type="text"
    name="venue"
    class="form-control"
    placeholder="Example: Main Auditorium"
    value="<?php
    echo htmlspecialchars(
        $editEvent["venue"] ?? ""
    );
    ?>"
    required
>

</div>



<div class="form-row">


<div class="form-group">

<label>
Event Date
</label>

<input
    type="date"
    name="event_date"
    class="form-control"
    value="<?php
    echo htmlspecialchars(
        $editEvent["event_date"] ?? ""
    );
    ?>"
    required
>

</div>



<div class="form-group">

<label>
Registration Deadline
</label>

<input
    type="date"
    name="registration_deadline"
    class="form-control"
    value="<?php
    echo htmlspecialchars(
        $editEvent["registration_deadline"] ?? ""
    );
    ?>"
>

</div>


</div>



<div class="form-row">


<div class="form-group">

<label>
Start Time
</label>

<input
    type="time"
    name="start_time"
    class="form-control"
    value="<?php
    echo htmlspecialchars(
        $editEvent["start_time"] ?? ""
    );
    ?>"
    required
>

</div>



<div class="form-group">

<label>
End Time
</label>

<input
    type="time"
    name="end_time"
    class="form-control"
    value="<?php
    echo htmlspecialchars(
        $editEvent["end_time"] ?? ""
    );
    ?>"
>

</div>


</div>



<div class="form-group">

<label>
Participant Capacity
</label>

<input
    type="number"
    name="capacity"
    min="1"
    class="form-control"
    placeholder="Leave blank for unlimited"
    value="<?php
    echo htmlspecialchars(
        $editEvent["capacity"] ?? ""
    );
    ?>"
>

</div>



<button
    type="submit"
    class="submit-btn"
>

<i
    class="fa-solid <?php
    echo $editEvent
        ? 'fa-floppy-disk'
        : 'fa-plus';
    ?>"
></i>

<?php
echo $editEvent
    ? "Update Event"
    : "Create Event";
?>

</button>


<?php if ($editEvent) { ?>

<a
    href="events.php"
    class="cancel-edit"
>

Cancel Editing

</a>

<?php } ?>


</form>


</div>



<!-- =========================================================
     EVENT LIST
     ========================================================= -->

<div class="panel">


<div class="panel-head">


<div class="panel-heading">

<div class="panel-icon">

<i class="fa-solid fa-calendar-days"></i>

</div>


<div>

<h2>
All Events
</h2>

<p>
Manage events and participant registrations.
</p>

</div>

</div>



<div class="filters">

<input
    type="text"
    id="searchBox"
    class="search"
    placeholder="Search event..."
>

<select
    id="statusFilter"
    class="filter"
>

<option value="all">
All Status
</option>

<option value="scheduled">
Scheduled
</option>

<option value="cancelled">
Cancelled
</option>

</select>

</div>


</div>



<?php

if (
    $events &&
    mysqli_num_rows(
        $events
    ) > 0
) {

?>


<div class="event-list">


<?php

while (
    $event =
    mysqli_fetch_assoc(
        $events
    )
) {

    $statusClass =
        strtolower(
            $event["status"]
        );

    $registeredCount =
        intval(
            $event["registered_count"]
        );

?>


<div
    class="event-card"
    data-status="<?php
    echo htmlspecialchars(
        $statusClass
    );
    ?>"
>


<div class="event-top">


<div class="event-main">


<div class="event-icon">

<i
    class="fa-solid <?php
    echo getCategoryIcon(
        $event["category"]
    );
    ?>"
></i>

</div>


<div>

<div class="event-title">

<?php
echo htmlspecialchars(
    $event["title"]
);
?>

</div>


<span class="category">

<?php
echo htmlspecialchars(
    $event["category"]
);
?>

</span>

</div>


</div>



<span
    class="status <?php echo $statusClass; ?>"
>

<?php
echo htmlspecialchars(
    $event["status"]
);
?>

</span>


</div>



<div class="event-meta">


<span>

<i class="fa-regular fa-calendar"></i>

<?php

echo date(
    "d M Y",
    strtotime(
        $event["event_date"]
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
        $event["start_time"]
    )
);

if (
    !empty(
        $event["end_time"]
    )
) {

    echo " - ";

    echo date(
        "h:i A",
        strtotime(
            $event["end_time"]
        )
    );
}

?>

</span>


<span>

<i class="fa-solid fa-location-dot"></i>

<?php
echo htmlspecialchars(
    $event["venue"]
);
?>

</span>


<?php

if (
    !empty(
        $event["registration_deadline"]
    )
) {

?>

<span>

<i class="fa-solid fa-hourglass-half"></i>

Register by

<?php

echo date(
    "d M Y",
    strtotime(
        $event["registration_deadline"]
    )
);

?>

</span>

<?php } ?>


</div>



<div class="event-description">

<?php

echo nl2br(
    htmlspecialchars(
        $event["description"]
    )
);

?>

</div>



<div class="registration-row">


<div class="registration-info">

<strong>

<?php
echo $registeredCount;
?>

</strong>

registered

<?php

if (
    !empty(
        $event["capacity"]
    )
) {

    echo " / ";

    echo intval(
        $event["capacity"]
    );

    echo " seats";
}

?>

</div>



<div class="actions">


<a
    href="events.php?participants_id=<?php echo intval($event["id"]); ?>"
    class="action-link participant-btn"
>

<i class="fa-solid fa-users"></i>

Participants

</a>



<a
    href="events.php?edit_id=<?php echo intval($event["id"]); ?>"
    class="action-link edit-btn"
>

<i class="fa-solid fa-pen"></i>

Edit

</a>



<form method="POST">


<input
    type="hidden"
    name="action"
    value="change_status"
>


<input
    type="hidden"
    name="event_id"
    value="<?php echo intval($event["id"]); ?>"
>


<?php if ($event["status"] === "Scheduled") { ?>


<input
    type="hidden"
    name="status"
    value="Cancelled"
>


<button
    type="submit"
    class="action-btn cancel-btn"
>

<i class="fa-solid fa-ban"></i>

Cancel

</button>


<?php } else { ?>


<input
    type="hidden"
    name="status"
    value="Scheduled"
>


<button
    type="submit"
    class="action-btn restore-btn"
>

<i class="fa-solid fa-rotate-left"></i>

Restore

</button>


<?php } ?>


</form>



<form
    method="POST"
    onsubmit="return confirm('Delete this event and all registrations?');"
>


<input
    type="hidden"
    name="action"
    value="delete_event"
>


<input
    type="hidden"
    name="event_id"
    value="<?php echo intval($event["id"]); ?>"
>


<button
    type="submit"
    class="action-btn delete-btn"
>

<i class="fa-solid fa-trash"></i>

Delete

</button>


</form>


</div>


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
No matching events
</h3>

<p>
Try changing your search or filter.
</p>

</div>


<?php

} else {

?>


<div class="empty">

<i class="fa-solid fa-calendar-plus"></i>

<h3>
No events created yet
</h3>

<p>
Create your first hostel event from the form.
</p>

</div>


<?php } ?>


</div>


</div>



<!-- =========================================================
     PARTICIPANT LIST
     ========================================================= -->

<?php if ($participantEvent) { ?>


<div class="panel participant-panel">


<div class="panel-head">


<div class="panel-heading">

<div class="panel-icon">

<i class="fa-solid fa-users"></i>

</div>


<div>

<h2>
Participants — <?php
echo htmlspecialchars(
    $participantEvent["title"]
);
?>
</h2>

<p>

<?php

echo date(
    "d M Y",
    strtotime(
        $participantEvent["event_date"]
    )
);

?>

• <?php
echo htmlspecialchars(
    $participantEvent["venue"]
);
?>

</p>

</div>


</div>


<a
    href="events.php"
    class="action-link participant-btn"
>

<i class="fa-solid fa-xmark"></i>

Close

</a>


</div>



<?php

if (
    $participants &&
    mysqli_num_rows(
        $participants
    ) > 0
) {

?>


<div style="overflow-x:auto;">


<table class="participant-table">


<thead>

<tr>

<th>
Student
</th>

<th>
Email
</th>

<th>
Room
</th>

<th>
Registered On
</th>

</tr>

</thead>


<tbody>


<?php

while (
    $participant =
    mysqli_fetch_assoc(
        $participants
    )
) {

?>


<tr>


<td>

<div class="student-cell">

<div class="student-avatar">

<?php
echo getInitial(
    $participant["name"]
);
?>

</div>

<strong>

<?php
echo htmlspecialchars(
    $participant["name"]
);
?>

</strong>

</div>

</td>


<td>

<?php
echo htmlspecialchars(
    $participant["email"]
);
?>

</td>


<td>

<?php

echo !empty(
    $participant["room_number"]
)
    ? htmlspecialchars(
        $participant["room_number"]
    )
    : "Not Assigned";

?>

</td>


<td>

<?php

echo date(
    "d M Y, h:i A",
    strtotime(
        $participant["registered_at"]
    )
);

?>

</td>


</tr>


<?php } ?>


</tbody>


</table>


</div>


<?php

} else {

?>


<div class="empty">

<i class="fa-solid fa-user-group"></i>

<h3>
No registrations yet
</h3>

<p>
Students who register will appear here.
</p>

</div>


<?php } ?>


</div>


<?php } ?>


</div>


</div>



<script>

/* =========================================================
   EVENT SEARCH
   ========================================================= */

const searchBox =
    document.getElementById(
        "searchBox"
    );

const statusFilter =
    document.getElementById(
        "statusFilter"
    );


function filterEvents() {

    const search =
        searchBox
        ?
        searchBox.value
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


    const cards =
        document.querySelectorAll(
            ".event-card"
        );


    let visible = 0;


    cards.forEach(
        function(card) {

            const text =
                card.innerText
                .toLowerCase();

            const cardStatus =
                card.dataset.status;


            const searchMatch =
                text.includes(search);

            const statusMatch =
                status === "all" ||
                cardStatus === status;


            if (
                searchMatch &&
                statusMatch
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
        filterEvents
    );
}


if (statusFilter) {

    statusFilter.addEventListener(
        "change",
        filterEvents
    );
}


/* =========================================================
   MESSAGE AUTO HIDE
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
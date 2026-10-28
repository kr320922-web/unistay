<?php

session_start();
include "../db.php";

date_default_timezone_set("Asia/Kolkata");

/* =========================================================
   STUDENT AUTH
   ========================================================= */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "student"
) {
    header("Location: ../index.php");
    exit;
}

$userId = intval($_SESSION["user_id"]);
$userName = $_SESSION["user_name"] ?? "Student";


/* =========================================================
   FLASH MESSAGE
   ========================================================= */

$message = "";
$messageType = "";

if (isset($_SESSION["room_change_message"])) {

    $message = $_SESSION["room_change_message"];

    $messageType =
        $_SESSION["room_change_message_type"] ?? "";

    unset($_SESSION["room_change_message"]);
    unset($_SESSION["room_change_message_type"]);
}


/* =========================================================
   CURRENT STUDENT + ROOM
   ========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        u.id,
        u.name,
        u.email,
        u.room_id,
        r.room_number,
        r.block,
        r.floor,
        r.capacity

     FROM users u

     LEFT JOIN rooms r
        ON u.room_id = r.id

     WHERE u.id = ?
     AND u.role = 'student'

     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $userId
);

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);

$student =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


$currentRoomId =
    !empty($student["room_id"])
    ? intval($student["room_id"])
    : 0;


/* =========================================================
   CURRENT ROOM OCCUPANCY
   ========================================================= */

$currentOccupancy = 0;

if ($currentRoomId > 0) {

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
        $currentRoomId
    );

    mysqli_stmt_execute($stmt);

    $result =
        mysqli_stmt_get_result($stmt);

    $row =
        mysqli_fetch_assoc($result);

    $currentOccupancy =
        intval($row["total"] ?? 0);

    mysqli_stmt_close($stmt);
}


/* =========================================================
   SUBMIT REQUEST
   ========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["submit_request"])
) {

    if ($currentRoomId <= 0) {

        $_SESSION["room_change_message"] =
            "You do not have a room assigned yet.";

        $_SESSION["room_change_message_type"] =
            "error";

        header("Location: room_change.php");
        exit;
    }


    $requestedRoomId =
        intval(
            $_POST["requested_room_id"] ?? 0
        );

    $reason =
        trim(
            $_POST["reason"] ?? ""
        );


    if (
        $requestedRoomId <= 0 ||
        $reason === ""
    ) {

        $_SESSION["room_change_message"] =
            "Please select a room and enter the reason.";

        $_SESSION["room_change_message_type"] =
            "error";

        header("Location: room_change.php");
        exit;
    }


    if ($requestedRoomId === $currentRoomId) {

        $_SESSION["room_change_message"] =
            "You are already staying in this room.";

        $_SESSION["room_change_message_type"] =
            "error";

        header("Location: room_change.php");
        exit;
    }


    /* CHECK EXISTING PENDING REQUEST */

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id

         FROM room_change_requests

         WHERE user_id = ?
         AND status = 'Pending'

         LIMIT 1"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $userId
    );

    mysqli_stmt_execute($stmt);

    $pendingResult =
        mysqli_stmt_get_result($stmt);

    $existingPending =
        mysqli_fetch_assoc($pendingResult);

    mysqli_stmt_close($stmt);


    if ($existingPending) {

        $_SESSION["room_change_message"] =
            "You already have a pending room change request.";

        $_SESSION["room_change_message_type"] =
            "error";

        header("Location: room_change.php");
        exit;
    }


    /* CHECK REQUESTED ROOM */

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            r.id,
            r.room_number,
            r.capacity,
            COUNT(u.id) AS occupancy

         FROM rooms r

         LEFT JOIN users u
            ON u.room_id = r.id
            AND u.role = 'student'

         WHERE r.id = ?

         GROUP BY
            r.id,
            r.room_number,
            r.capacity

         LIMIT 1"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $requestedRoomId
    );

    mysqli_stmt_execute($stmt);

    $roomResult =
        mysqli_stmt_get_result($stmt);

    $requestedRoom =
        mysqli_fetch_assoc($roomResult);

    mysqli_stmt_close($stmt);


    if (!$requestedRoom) {

        $_SESSION["room_change_message"] =
            "Selected room does not exist.";

        $_SESSION["room_change_message_type"] =
            "error";

        header("Location: room_change.php");
        exit;
    }


    if (
        intval($requestedRoom["occupancy"]) >=
        intval($requestedRoom["capacity"])
    ) {

        $_SESSION["room_change_message"] =
            "Selected room is currently full.";

        $_SESSION["room_change_message_type"] =
            "error";

        header("Location: room_change.php");
        exit;
    }


    /* INSERT REQUEST */

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO room_change_requests
        (
            user_id,
            current_room_id,
            requested_room_id,
            reason,
            status
        )

        VALUES
        (?, ?, ?, ?, 'Pending')"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "iiis",
        $userId,
        $currentRoomId,
        $requestedRoomId,
        $reason
    );


    if (mysqli_stmt_execute($stmt)) {

        $_SESSION["room_change_message"] =
            "Room change request submitted successfully.";

        $_SESSION["room_change_message_type"] =
            "success";

    } else {

        $_SESSION["room_change_message"] =
            "Unable to submit room change request.";

        $_SESSION["room_change_message_type"] =
            "error";
    }

    mysqli_stmt_close($stmt);


    header("Location: room_change.php");
    exit;
}


/* =========================================================
   ACTIVE PENDING REQUEST
   ========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT

        rcr.id,
        rcr.reason,
        rcr.status,
        rcr.created_at,

        old_room.room_number
            AS old_room,

        new_room.room_number
            AS new_room

     FROM room_change_requests rcr

     LEFT JOIN rooms old_room
        ON rcr.current_room_id =
           old_room.id

     LEFT JOIN rooms new_room
        ON rcr.requested_room_id =
           new_room.id

     WHERE rcr.user_id = ?
     AND rcr.status = 'Pending'

     ORDER BY rcr.id DESC

     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $userId
);

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);

$pendingRequest =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/* =========================================================
   AVAILABLE ROOMS
   ========================================================= */

$availableRooms = [];

$sql =
    "SELECT

        r.id,
        r.room_number,
        r.block,
        r.floor,
        r.capacity,

        COUNT(u.id)
            AS occupancy

     FROM rooms r

     LEFT JOIN users u
        ON u.room_id = r.id
        AND u.role = 'student'

     WHERE r.id != " .
     intval($currentRoomId) . "

     GROUP BY
        r.id,
        r.room_number,
        r.block,
        r.floor,
        r.capacity

     HAVING COUNT(u.id) < r.capacity

     ORDER BY
        r.block,
        r.floor,
        r.room_number";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if ($result) {

    while (
        $room =
        mysqli_fetch_assoc($result)
    ) {

        $availableRooms[] =
            $room;
    }
}


/* =========================================================
   REQUEST HISTORY
   ========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT

        rcr.*,

        old_room.room_number
            AS old_room,

        new_room.room_number
            AS new_room

     FROM room_change_requests rcr

     LEFT JOIN rooms old_room
        ON rcr.current_room_id =
           old_room.id

     LEFT JOIN rooms new_room
        ON rcr.requested_room_id =
           new_room.id

     WHERE rcr.user_id = ?

     ORDER BY rcr.id DESC"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $userId
);

mysqli_stmt_execute($stmt);

$history =
    mysqli_stmt_get_result($stmt);


/* =========================================================
   SUMMARY
   ========================================================= */

$stmtSummary = mysqli_prepare(
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

     FROM room_change_requests

     WHERE user_id = ?"
);

mysqli_stmt_bind_param(
    $stmtSummary,
    "i",
    $userId
);

mysqli_stmt_execute($stmtSummary);

$result =
    mysqli_stmt_get_result($stmtSummary);

$summary =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($stmtSummary);


$totalRequests =
    intval($summary["total"] ?? 0);

$approvedRequests =
    intval($summary["approved"] ?? 0);

$rejectedRequests =
    intval($summary["rejected"] ?? 0);


function getInitial($name)
{
    $name = trim($name);

    return $name === ""
        ? "S"
        : strtoupper(substr($name, 0, 1));
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
Room Change - UniStay
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

    --green:#36B486;
    --red:#E76871;

    --bg:#EFF4F7;
    --border:#DFE7EC;

    --text:#1E2639;
    --muted:#718092;

    --teal-soft:#DDF2F4;
    --green-soft:#E3F5EC;
    --red-soft:#FBE8EA;
    --mustard-soft:#FFF2D3;
}


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
        var(--bg);

    color:
        var(--text);
}


.dashboard {

    display:flex;

    min-height:100vh;
}


.sidebar {

    width:230px !important;
    min-width:230px !important;
}


.main {

    flex:1;

    min-width:0;

    padding:
        28px 32px 50px;
}


/* TOPBAR */

.topbar {

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:20px;

    margin-bottom:24px;
}


.title {

    display:flex;

    align-items:center;

    gap:14px;
}


.title-icon {

    width:50px;
    height:50px;

    display:flex;

    justify-content:center;

    align-items:center;

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


.title h1 {

    font-size:30px;
}


.title p {

    margin-top:4px;

    color:
        var(--muted);

    font-size:13px;
}


.profile {

    display:flex;

    align-items:center;

    gap:9px;

    padding:
        7px 13px 7px 7px;

    border-radius:30px;

    background:white;

    border:
        1px solid var(--border);
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
        var(--teal);

    font-weight:800;
}


/* HERO */

.hero {

    display:grid;

    grid-template-columns:
        1fr
        300px;

    gap:25px;

    align-items:center;

    margin-bottom:20px;

    padding:
        30px 32px;

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


.hero small {

    color:#F6D77C;

    font-weight:800;

    text-transform:uppercase;
}


.hero h2 {

    margin-top:7px;

    font-size:33px;
}


.hero p {

    margin-top:10px;

    max-width:600px;

    color:
        rgba(255,255,255,.82);

    line-height:1.6;

    font-size:14px;
}


.room-box {

    padding:18px;

    border-radius:14px;

    background:
        rgba(255,255,255,.12);
}


.room-box span {

    color:
        rgba(255,255,255,.7);

    font-size:10px;

    text-transform:uppercase;
}


.room-box h3 {

    margin-top:5px;

    font-size:28px;
}


/* SUMMARY */

.summary {

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:14px;

    margin-bottom:20px;
}


.summary-card {

    padding:18px;

    border-radius:14px;

    background:white;

    border:
        1px solid var(--border);
}


.summary-card p {

    color:
        var(--muted);

    font-size:11px;

    text-transform:uppercase;
}


.summary-card h2 {

    margin-top:5px;

    font-size:27px;
}


/* MESSAGE */

.message {

    margin-bottom:20px;

    padding:14px 16px;

    border-radius:10px;

    font-size:13px;

    font-weight:700;
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


/* CONTENT */

.grid {

    display:grid;

    grid-template-columns:
        360px
        1fr;

    gap:20px;

    align-items:start;
}


.panel {

    margin-bottom:20px;

    overflow:hidden;

    border-radius:16px;

    background:white;

    border:
        1px solid var(--border);
}


.panel-head {

    padding:19px 20px;

    border-bottom:
        1px solid #EDF1F3;
}


.panel-head h2 {

    font-size:19px;
}


.panel-head p {

    margin-top:3px;

    color:
        var(--muted);

    font-size:12px;
}


/* FORM */

.form {

    padding:20px;
}


.form-group {

    margin-bottom:15px;
}


label {

    display:block;

    margin-bottom:6px;

    font-size:12px;

    font-weight:700;
}


select,
textarea {

    width:100%;

    padding:11px;

    border:
        1px solid #D5E0E5;

    border-radius:9px;

    outline:none;

    background:#FBFCFD;

    font-size:12px;
}


textarea {

    min-height:120px;

    resize:vertical;
}


select:focus,
textarea:focus {

    border-color:
        var(--teal);
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

    font-weight:800;
}


/* AVAILABLE ROOMS */

.rooms {

    display:grid;

    grid-template-columns:
        repeat(2,1fr);

    gap:12px;

    padding:18px;
}


.available-room {

    padding:15px;

    border-radius:12px;

    border:
        1px solid #E1E8EC;

    background:#FAFCFD;
}


.available-room h3 {

    font-size:16px;
}


.available-room p {

    margin-top:5px;

    color:
        var(--muted);

    font-size:11px;
}


.available {

    display:inline-block;

    margin-top:10px;

    padding:5px 8px;

    border-radius:20px;

    color:#267A5D;

    background:
        var(--green-soft);

    font-size:9px;

    font-weight:800;
}


/* PENDING */

.pending {

    margin:20px;

    padding:17px;

    border-radius:12px;

    background:
        var(--mustard-soft);
}


.pending h3 {

    font-size:16px;
}


.pending-route {

    margin-top:10px;

    font-weight:800;

    color:
        var(--teal);
}


.pending p {

    margin-top:9px;

    color:#6E6245;

    font-size:11px;

    line-height:1.5;
}


/* TABLE */

.table-wrap {

    overflow-x:auto;
}


table {

    width:100%;

    border-collapse:collapse;
}


th {

    padding:12px 14px;

    text-align:left;

    color:
        var(--muted);

    background:#F7F9FA;

    font-size:10px;
}


td {

    padding:13px 14px;

    border-bottom:
        1px solid #EDF1F3;

    font-size:11px;
}


.status {

    display:inline-block;

    padding:5px 8px;

    border-radius:20px;

    font-size:9px;

    font-weight:800;
}


.status.Pending {

    color:#946509;

    background:
        var(--mustard-soft);
}


.status.Approved {

    color:#267A5D;

    background:
        var(--green-soft);
}


.status.Rejected {

    color:#A64048;

    background:
        var(--red-soft);
}


.empty {

    padding:40px 20px;

    text-align:center;

    color:
        var(--muted);

    font-size:12px;
}


@media(max-width:1000px) {

    .hero {

        grid-template-columns:1fr;
    }


    .grid {

        grid-template-columns:1fr;
    }
}


@media(max-width:800px) {

    .summary {

        grid-template-columns:
            repeat(2,1fr);
    }


    .rooms {

        grid-template-columns:1fr;
    }
}

</style>

</head>


<body>

<div class="dashboard">

<?php include "sidebar.php"; ?>

<div class="main">


<div class="topbar">

<div class="title">

<div class="title-icon">

<i class="fa-solid fa-arrow-right-arrow-left"></i>

</div>

<div>

<h1>
Room Change
</h1>

<p>
Request a different hostel room.
</p>

</div>

</div>


<div class="profile">

<div class="profile-avatar">

<?php echo getInitial($userName); ?>

</div>

<div>

<strong>
<?php echo htmlspecialchars($userName); ?>
</strong>

</div>

</div>

</div>



<div class="hero">

<div>

<small>
Smart Room Management
</small>

<h2>
Need a different room?
</h2>

<p>

Choose from currently available rooms and send
a request to the Warden. Your room will change
only after approval.

</p>

</div>


<div class="room-box">

<span>
Current Room
</span>

<h3>

<?php

echo !empty($student["room_number"])
    ? htmlspecialchars(
        $student["room_number"]
    )
    : "Not Assigned";

?>

</h3>

<?php if ($currentRoomId > 0) { ?>

<p>

<?php
echo htmlspecialchars(
    $student["block"]
);
?>

•

<?php
echo htmlspecialchars(
    $student["floor"]
);
?>

<br><br>

<?php echo $currentOccupancy; ?>

/

<?php
echo intval(
    $student["capacity"]
);
?>

Students

</p>

<?php } ?>

</div>

</div>



<div class="summary">

<div class="summary-card">

<p>
Available Rooms
</p>

<h2>
<?php echo count($availableRooms); ?>
</h2>

</div>


<div class="summary-card">

<p>
Pending
</p>

<h2>
<?php echo $pendingRequest ? 1 : 0; ?>
</h2>

</div>


<div class="summary-card">

<p>
Approved
</p>

<h2>
<?php echo $approvedRequests; ?>
</h2>

</div>


<div class="summary-card">

<p>
Rejected
</p>

<h2>
<?php echo $rejectedRequests; ?>
</h2>

</div>

</div>



<?php if ($message !== "") { ?>

<div
    class="message <?php echo htmlspecialchars($messageType); ?>"
>

<?php
echo htmlspecialchars($message);
?>

</div>

<?php } ?>



<div class="grid">


<div class="panel">

<div class="panel-head">

<h2>
Room Change Request
</h2>

<p>
Select an available room.
</p>

</div>


<?php if ($pendingRequest) { ?>

<div class="pending">

<h3>
Request Under Review
</h3>

<div class="pending-route">

<?php
echo htmlspecialchars(
    $pendingRequest["old_room"]
);
?>

→

<?php
echo htmlspecialchars(
    $pendingRequest["new_room"]
);
?>

</div>

<p>

<strong>
Reason:
</strong>

<?php
echo htmlspecialchars(
    $pendingRequest["reason"]
);
?>

</p>

<p>

Waiting for Warden approval.

</p>

</div>


<?php } elseif ($currentRoomId <= 0) { ?>

<div class="empty">

You need an assigned room before requesting
a room change.

</div>


<?php } else { ?>

<form
    method="POST"
    class="form"
>


<div class="form-group">

<label>
Preferred Room
</label>

<select
    name="requested_room_id"
    required
>

<option value="">
Select Available Room
</option>

<?php foreach ($availableRooms as $room) { ?>

<?php

$vacant =
    intval($room["capacity"]) -
    intval($room["occupancy"]);

?>

<option
    value="<?php echo intval($room["id"]); ?>"
>

<?php
echo htmlspecialchars(
    $room["room_number"]
);
?>

—

<?php
echo htmlspecialchars(
    $room["block"]
);
?>

—

<?php echo $vacant; ?>

bed<?php echo $vacant != 1 ? "s" : ""; ?> available

</option>

<?php } ?>

</select>

</div>


<div class="form-group">

<label>
Reason for Room Change
</label>

<textarea
    name="reason"
    maxlength="500"
    placeholder="Why do you want to change your room?"
    required
></textarea>

</div>


<button
    type="submit"
    name="submit_request"
    class="submit-btn"
>

<i class="fa-solid fa-paper-plane"></i>

Submit Request

</button>

</form>

<?php } ?>

</div>



<div class="panel">

<div class="panel-head">

<h2>
Available Rooms
</h2>

<p>
Rooms with vacant beds.
</p>

</div>


<?php if (count($availableRooms) > 0) { ?>

<div class="rooms">

<?php foreach ($availableRooms as $room) { ?>

<?php

$vacant =
    intval($room["capacity"]) -
    intval($room["occupancy"]);

?>

<div class="available-room">

<h3>

<i class="fa-solid fa-bed"></i>

<?php
echo htmlspecialchars(
    $room["room_number"]
);
?>

</h3>

<p>

<?php
echo htmlspecialchars(
    $room["block"]
);
?>

•

<?php
echo htmlspecialchars(
    $room["floor"]
);
?>

</p>

<p>

Occupancy:

<?php
echo intval(
    $room["occupancy"]
);
?>

/

<?php
echo intval(
    $room["capacity"]
);
?>

</p>

<span class="available">

<?php echo $vacant; ?>

Bed<?php echo $vacant != 1 ? "s" : ""; ?> Available

</span>

</div>

<?php } ?>

</div>


<?php } else { ?>

<div class="empty">

No vacant room is available right now.

<br><br>

You can use
<strong>Smart Room Swap</strong>
if another student wants to exchange rooms.

</div>

<?php } ?>

</div>

</div>



<div class="panel">

<div class="panel-head">

<h2>
Request History
</h2>

<p>
Your previous room change requests.
</p>

</div>


<?php if (
    $history &&
    mysqli_num_rows($history) > 0
) { ?>

<div class="table-wrap">

<table>

<thead>

<tr>

<th>
Room Change
</th>

<th>
Reason
</th>

<th>
Status
</th>

<th>
Warden Remark
</th>

<th>
Date
</th>

</tr>

</thead>


<tbody>

<?php while (
    $row =
    mysqli_fetch_assoc($history)
) { ?>

<tr>

<td>

<strong>

<?php
echo htmlspecialchars(
    $row["old_room"] ?? "-"
);
?>

→

<?php
echo htmlspecialchars(
    $row["new_room"] ?? "-"
);
?>

</strong>

</td>


<td>

<?php
echo htmlspecialchars(
    $row["reason"]
);
?>

</td>


<td>

<span
    class="status <?php echo htmlspecialchars($row["status"]); ?>"
>

<?php
echo htmlspecialchars(
    $row["status"]
);
?>

</span>

</td>


<td>

<?php

echo !empty(
    $row["warden_remark"]
)
    ? htmlspecialchars(
        $row["warden_remark"]
    )
    : "—";

?>

</td>


<td>

<?php

echo date(
    "d M Y",
    strtotime(
        $row["created_at"]
    )
);

?>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>


<?php } else { ?>

<div class="empty">

No room change requests yet.

</div>

<?php } ?>

</div>


</div>
</div>

</body>
</html>
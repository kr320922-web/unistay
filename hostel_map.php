<?php

session_start();
include "../db.php";

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "student"
) {
    header("Location: ../index.php");
    exit;
}

$userId   = intval($_SESSION["user_id"]);
$userName = $_SESSION["user_name"] ?? "Student";


/* =========================================================
   CURRENT STUDENT
   ========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        u.room_id,
        r.room_number,
        r.block,
        r.floor
     FROM users u
     LEFT JOIN rooms r
        ON u.room_id = r.id
     WHERE u.id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);

$currentStudent =
    mysqli_fetch_assoc(
        mysqli_stmt_get_result($stmt)
    );

mysqli_stmt_close($stmt);

$currentRoomId =
    !empty($currentStudent["room_id"])
    ? intval($currentStudent["room_id"])
    : 0;


/* =========================================================
   ROOMS + OCCUPANTS
   ========================================================= */

$rooms = [];

$sql = "
    SELECT
        r.id,
        r.room_number,
        r.block,
        r.floor,
        r.capacity,

        COUNT(u.id) AS occupancy,

        GROUP_CONCAT(
            u.name
            ORDER BY u.name
            SEPARATOR '||'
        ) AS occupants

    FROM rooms r

    LEFT JOIN users u
        ON u.room_id = r.id
        AND u.role = 'student'

    GROUP BY
        r.id,
        r.room_number,
        r.block,
        r.floor,
        r.capacity

    ORDER BY
        r.block,
        r.floor,
        r.room_number
";

$result = mysqli_query($conn, $sql);

while ($room = mysqli_fetch_assoc($result)) {

    $capacity  = intval($room["capacity"]);
    $occupancy = intval($room["occupancy"]);

    if ($occupancy <= 0) {

        $status = "available";

    } elseif ($occupancy < $capacity) {

        $status = "partial";

    } else {

        $status = "full";
    }

    $room["status"] = $status;

    $room["vacant"] =
        max(0, $capacity - $occupancy);

    $room["is_current"] =
        intval($room["id"]) === $currentRoomId;

    $rooms[] = $room;
}


/* =========================================================
   GROUP BY BLOCK / FLOOR
   ========================================================= */

$mapData = [];

foreach ($rooms as $room) {

    $mapData[
        $room["block"]
    ][
        $room["floor"]
    ][] = $room;
}


/* =========================================================
   DEFAULT VIEW = CURRENT STUDENT ROOM
   ========================================================= */

$defaultBlock =
    $currentStudent["block"] ?? "";

$defaultFloor =
    $currentStudent["floor"] ?? "";

if (
    $defaultBlock === "" ||
    $defaultFloor === ""
) {

    if (!empty($mapData)) {

        $defaultBlock =
            array_key_first($mapData);

        $defaultFloor =
            array_key_first(
                $mapData[$defaultBlock]
            );
    }
}


function safe($text)
{
    return htmlspecialchars(
        $text ?? "",
        ENT_QUOTES,
        "UTF-8"
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

<title>Hostel Map - UniStay</title>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
>

<style>

:root {

    --navy:#132B49;
    --navy2:#1F3657;

    --blue:#4D8ED8;
    --blue-soft:#DDEBFF;

    --green:#64B982;
    --green-soft:#E6F6EA;

    --yellow:#E8B23D;
    --yellow-soft:#FFF3D2;

    --red:#E76E73;
    --red-soft:#FFE3E4;

    --bg:#EDF3F7;

    --text:#17253B;
    --muted:#748296;

    --line:#CCD7DF;
}


* {

    box-sizing:border-box;

    margin:0;
    padding:0;

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


.layout {

    display:flex;

    min-height:100vh;
}


.main {

    flex:1;

    min-width:0;

    padding:24px;
}


/* =========================================================
   TOP
   ========================================================= */

.topbar {

    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:18px;
}


.heading {

    display:flex;

    gap:12px;

    align-items:center;
}


.heading-icon {

    width:48px;
    height:48px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:13px;

    background:
        linear-gradient(
            135deg,
            #1F2747,
            #0E6077
        );

    color:white;

    font-size:19px;
}


.heading h1 {

    font-size:27px;
}


.heading p {

    margin-top:3px;

    color:
        var(--muted);

    font-size:11px;
}


.profile {

    display:flex;

    align-items:center;

    gap:8px;

    padding:
        6px 13px 6px 6px;

    border-radius:30px;

    background:white;

    border:
        1px solid #D9E3E9;
}


.avatar {

    width:38px;
    height:38px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:50%;

    background:#0E6077;

    color:white;

    font-weight:800;
}


/* =========================================================
   MAIN WHITE BOARD
   ========================================================= */

.board {

    overflow:hidden;

    border-radius:18px;

    background:white;

    border:
        1px solid #D8E3EA;

    box-shadow:
        0 10px 35px
        rgba(31,39,71,.07);
}


.board-top {

    display:flex;

    justify-content:space-between;

    gap:20px;

    align-items:flex-start;

    padding:
        22px 25px 18px;

    border-bottom:
        1px solid #E7EDF1;
}


.hostel-title h2 {

    font-family:Georgia, serif;

    color:
        var(--navy);

    font-size:28px;
}


.hostel-title h3 {

    margin-top:3px;

    font-family:Georgia, serif;

    font-weight:500;

    color:
        var(--navy);

    font-size:17px;
}


.tagline {

    margin-top:8px;

    color:#56789D;

    font-family:Georgia, serif;

    font-size:12px;

    font-style:italic;
}


.board-controls {

    display:flex;

    align-items:center;

    gap:10px;
}


.board-controls select {

    padding:
        9px 12px;

    border:
        1px solid #D5E0E7;

    border-radius:8px;

    background:white;

    outline:none;

    font-size:10px;
}


.compass {

    width:52px;

    text-align:center;

    color:
        var(--navy);

    font-size:11px;

    font-weight:800;
}


.compass i {

    display:block;

    margin-top:3px;

    font-size:28px;
}


/* =========================================================
   LEGEND
   ========================================================= */

.legend {

    display:flex;

    gap:18px;

    flex-wrap:wrap;

    padding:
        11px 25px;

    border-bottom:
        1px solid #E8EEF2;

    background:#FAFCFD;
}


.legend span {

    display:flex;

    align-items:center;

    gap:6px;

    color:
        #4F5F74;

    font-size:9px;
}


.legend-box {

    width:12px;
    height:12px;

    border-radius:2px;

    border:
        1px solid rgba(0,0,0,.15);
}


.legend-box.your {
    background:#9EC5FF;
}

.legend-box.available {
    background:#BEE8C7;
}

.legend-box.partial {
    background:#FFE3A1;
}

.legend-box.full {
    background:#FFB4B5;
}


/* =========================================================
   CONTENT
   ========================================================= */

.content {

    display:grid;

    grid-template-columns:
        minmax(0,1fr)
        280px;

    min-height:610px;
}


.map-area {

    position:relative;

    overflow:auto;

    padding:
        25px;

    background:
        #F9FBFC;
}


.floor-view {

    display:none;
}


.floor-view.active {

    display:block;
}


/* =========================================================
   BLUEPRINT BUILDING
   ========================================================= */

.blueprint {

    min-width:820px;

    padding:
        18px;

    border:
        7px solid
        var(--navy);

    background:white;

    box-shadow:
        0 0 0 2px
        #B7C7D2;
}


.room-row {

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:0;
}


.top-row {

    border-bottom:
        5px solid
        var(--navy);
}


.bottom-row {

    border-top:
        5px solid
        var(--navy);
}


.room {

    position:relative;

    min-height:135px;

    padding:16px 12px;

    cursor:pointer;

    border-right:
        4px solid
        var(--navy);

    background:white;

    transition:.15s;
}


.room:last-child {

    border-right:none;
}


.room:hover {

    filter:
        brightness(.97);
}


.room.available {

    background:
        #D9F1DE;
}


.room.partial {

    background:
        #FFE9AE;
}


.room.full {

    background:
        #FFC5C6;
}


.room.your-room {

    background:
        #B9D8FF;

    box-shadow:
        inset 0 0 0 4px
        #2979D4;
}


.room-window {

    position:absolute;

    top:-5px;
    left:50%;

    width:45px;
    height:9px;

    transform:
        translateX(-50%);

    border:
        2px solid
        var(--navy);

    background:white;
}


.room-door {

    position:absolute;

    bottom:-5px;
    left:50%;

    width:33px;
    height:8px;

    transform:
        translateX(-50%);

    background:white;

    border-left:
        2px solid
        var(--navy);

    border-right:
        2px solid
        var(--navy);
}


.bottom-row
.room-door {

    top:-5px;
    bottom:auto;
}


.room-number {

    margin-top:12px;

    text-align:center;

    color:
        #14243A;

    font-size:15px;

    font-weight:800;
}


.room.your-room
.room-number {

    color:
        #154D94;
}


.your-star {

    margin-top:10px;

    text-align:center;

    color:#216CC2;

    font-size:24px;
}


.your-text {

    margin-top:3px;

    text-align:center;

    color:#154D94;

    font-size:9px;

    font-weight:800;
}


.bed-icon {

    display:flex;

    justify-content:center;

    margin-top:12px;

    color:
        rgba(19,43,73,.55);

    font-size:24px;
}


.room-stats {

    margin-top:8px;

    text-align:center;

    color:#415268;

    font-size:8px;
}


/* =========================================================
   CORRIDOR
   ========================================================= */

.corridor {

    position:relative;

    display:flex;

    align-items:center;

    justify-content:center;

    height:105px;

    background:
        #F9FAFA;

    color:
        var(--navy);

    font-size:13px;

    font-weight:800;

    letter-spacing:1.5px;
}


.corridor::before,
.corridor::after {

    content:"↔";

    position:absolute;

    top:43px;

    color:#52677F;

    font-size:25px;

    font-weight:400;
}


.corridor::before {
    left:28%;
}

.corridor::after {
    right:28%;
}


/* =========================================================
   FACILITY STRIP
   ========================================================= */

.facility-row {

    display:grid;

    grid-template-columns:
        1fr 1.1fr 1.1fr 1fr;

    border-top:
        5px solid
        var(--navy);
}


.facility {

    min-height:105px;

    display:flex;

    align-items:center;

    justify-content:center;

    flex-direction:column;

    gap:6px;

    text-align:center;

    border-right:
        4px solid
        var(--navy);

    background:#F3F6F7;

    color:
        var(--navy);

    font-size:10px;

    font-weight:800;
}


.facility:last-child {

    border-right:none;
}


.facility i {

    font-size:23px;
}


.washroom {

    background:#F0F5F8;
}


/* =========================================================
   ENTRANCE
   ========================================================= */

.entrance {

    width:220px;

    margin:
        0 auto;

    padding:
        13px;

    text-align:center;

    border-left:
        5px solid
        var(--navy);

    border-right:
        5px solid
        var(--navy);

    border-bottom:
        5px solid
        var(--navy);

    color:
        var(--navy);

    background:white;

    font-size:10px;

    font-weight:800;
}


.entrance i {

    display:block;

    margin-bottom:4px;

    color:
        var(--teal);

    font-size:17px;
}


/* =========================================================
   RIGHT DETAIL PANEL
   ========================================================= */

.details {

    min-height:610px;

    padding:20px;

    border-left:
        1px solid #DCE5EB;

    background:
        #FBFCFD;
}


.details h2 {

    color:
        var(--navy);

    font-size:20px;
}


.room-visual {

    height:125px;

    display:flex;

    align-items:center;

    justify-content:center;

    margin:
        14px 0;

    border-radius:8px;

    background:
        linear-gradient(
            135deg,
            #DFE8ED,
            #F7F9FA
        );

    border:
        1px solid #D3DEE5;
}


.room-visual i {

    color:
        #6B7E91;

    font-size:58px;
}


.detail-row {

    display:flex;

    justify-content:space-between;

    gap:10px;

    padding:
        8px 0;

    border-bottom:
        1px solid #E0E7EB;

    font-size:10px;
}


.detail-row strong {

    color:
        #243349;
}


.status-line {

    display:flex;

    align-items:center;

    gap:6px;
}


.status-dot {

    width:10px;
    height:10px;

    border-radius:50%;
}


.status-dot.available {
    background:var(--green);
}

.status-dot.partial {
    background:var(--yellow);
}

.status-dot.full {
    background:var(--red);
}

.status-dot.your {
    background:var(--blue);
}


/* =========================================================
   FACILITIES
   ========================================================= */

.facility-list {

    margin-top:17px;
}


.facility-list h3 {

    margin-bottom:10px;

    font-size:12px;
}


.facility-list div {

    display:flex;

    gap:9px;

    align-items:center;

    margin-bottom:10px;

    color:#405167;

    font-size:9px;
}


.facility-list i {

    width:17px;

    color:
        var(--navy);
}


/* =========================================================
   OCCUPANTS
   ========================================================= */

.occupant-box {

    margin-top:18px;

    padding:12px;

    border-radius:9px;

    background:
        #E8F1FA;
}


.occupant-box strong {

    display:block;

    margin-bottom:8px;

    font-size:10px;
}


.occupant-name {

    margin-top:5px;

    font-size:9px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media(max-width:1050px) {

    .content {

        grid-template-columns:1fr;
    }


    .details {

        border-left:none;

        border-top:
            1px solid #DCE5EB;
    }
}


@media(max-width:750px) {

    .main {

        padding:14px;
    }


    .board-top {

        flex-direction:column;
    }
}

</style>

</head>


<body>

<div class="layout">

<?php include "sidebar.php"; ?>


<div class="main">


<div class="topbar">

<div class="heading">

<div class="heading-icon">

<i class="fa-solid fa-map-location-dot"></i>

</div>

<div>

<h1>Hostel Map</h1>

<p>
Interactive digital floor plan of UniStay hostel
</p>

</div>

</div>


<div class="profile">

<div class="avatar">

<?php
echo strtoupper(
    substr($userName, 0, 1)
);
?>

</div>

<div>

<strong>
<?php echo safe($userName); ?>
</strong>

</div>

</div>

</div>



<div class="board">


<div class="board-top">

<div class="hostel-title">

<h2 id="mainBlockTitle">

Girls Hostel – Block <?php echo safe($defaultBlock); ?>

</h2>

<h3 id="mainFloorTitle">

Floor <?php echo safe($defaultFloor); ?>

</h3>

<div class="tagline">

— Find Your Space, Feel at Home

</div>

</div>



<div class="board-controls">

<select id="blockSelect">

<?php foreach ($mapData as $block => $floors) { ?>

<option
    value="<?php echo safe($block); ?>"
    <?php
    echo (
        (string)$block ===
        (string)$defaultBlock
    ) ? "selected" : "";
    ?>
>

Block <?php echo safe($block); ?>

</option>

<?php } ?>

</select>


<select id="floorSelect">

<?php

foreach (
    $mapData as
    $block => $floors
) {

    foreach (
        $floors as
        $floor => $floorRooms
    ) {

?>

<option
    value="<?php echo safe($block . "___" . $floor); ?>"
    data-block="<?php echo safe($block); ?>"

    <?php

    echo (
        (string)$block ===
        (string)$defaultBlock
        &&
        (string)$floor ===
        (string)$defaultFloor
    ) ? "selected" : "";

    ?>
>

Floor <?php echo safe($floor); ?>

</option>

<?php

    }
}

?>

</select>


<div class="compass">

N

<i class="fa-solid fa-location-arrow"></i>

</div>

</div>

</div>



<div class="legend">

<span>
<i class="legend-box your"></i>
Your Room
</span>

<span>
<i class="legend-box available"></i>
Available
</span>

<span>
<i class="legend-box partial"></i>
Partially Occupied
</span>

<span>
<i class="legend-box full"></i>
Fully Occupied
</span>

<span>
<i class="fa-solid fa-computer-mouse"></i>
Click any room
</span>

</div>



<div class="content">


<div class="map-area">


<?php

foreach (
    $mapData as
    $block => $floors
) {

    foreach (
        $floors as
        $floor => $floorRooms
    ) {


        $half =
            ceil(
                count($floorRooms) / 2
            );

        $topRooms =
            array_slice(
                $floorRooms,
                0,
                $half
            );

        $bottomRooms =
            array_slice(
                $floorRooms,
                $half
            );

?>


<div
    class="floor-view"

    data-block="<?php echo safe($block); ?>"

    data-floor="<?php echo safe($floor); ?>"
>


<div class="blueprint">


<div class="room-row top-row">


<?php foreach ($topRooms as $room) { ?>


<div
    class="room
    <?php echo safe($room["status"]); ?>
    <?php echo $room["is_current"] ? "your-room" : ""; ?>"

    data-room="<?php echo safe($room["room_number"]); ?>"

    data-block="<?php echo safe($room["block"]); ?>"

    data-floor="<?php echo safe($room["floor"]); ?>"

    data-capacity="<?php echo intval($room["capacity"]); ?>"

    data-occupancy="<?php echo intval($room["occupancy"]); ?>"

    data-vacant="<?php echo intval($room["vacant"]); ?>"

    data-status="<?php echo safe($room["status"]); ?>"

    data-current="<?php echo $room["is_current"] ? "1" : "0"; ?>"

    data-occupants="<?php echo safe($room["occupants"]); ?>"

    onclick="showRoom(this)"
>


<div class="room-window"></div>


<div class="room-number">

<?php echo safe($room["room_number"]); ?>

</div>


<?php if ($room["is_current"]) { ?>

<div class="your-star">

<i class="fa-solid fa-star"></i>

</div>

<div class="your-text">
YOUR ROOM
</div>

<?php } else { ?>

<div class="bed-icon">

<i class="fa-solid fa-bed"></i>

</div>

<?php } ?>


<div class="room-stats">

<?php echo intval($room["occupancy"]); ?>

/

<?php echo intval($room["capacity"]); ?>

occupied

</div>


<div class="room-door"></div>

</div>


<?php } ?>


</div>



<div class="corridor">

CORRIDOR

</div>



<div class="room-row bottom-row">


<?php foreach ($bottomRooms as $room) { ?>


<div
    class="room
    <?php echo safe($room["status"]); ?>
    <?php echo $room["is_current"] ? "your-room" : ""; ?>"

    data-room="<?php echo safe($room["room_number"]); ?>"

    data-block="<?php echo safe($room["block"]); ?>"

    data-floor="<?php echo safe($room["floor"]); ?>"

    data-capacity="<?php echo intval($room["capacity"]); ?>"

    data-occupancy="<?php echo intval($room["occupancy"]); ?>"

    data-vacant="<?php echo intval($room["vacant"]); ?>"

    data-status="<?php echo safe($room["status"]); ?>"

    data-current="<?php echo $room["is_current"] ? "1" : "0"; ?>"

    data-occupants="<?php echo safe($room["occupants"]); ?>"

    onclick="showRoom(this)"
>


<div class="room-door"></div>


<div class="room-number">

<?php echo safe($room["room_number"]); ?>

</div>


<?php if ($room["is_current"]) { ?>

<div class="your-star">

<i class="fa-solid fa-star"></i>

</div>

<div class="your-text">

YOUR ROOM

</div>

<?php } else { ?>

<div class="bed-icon">

<i class="fa-solid fa-bed"></i>

</div>

<?php } ?>


<div class="room-stats">

<?php echo intval($room["occupancy"]); ?>

/

<?php echo intval($room["capacity"]); ?>

occupied

</div>


<div class="room-window"
     style="top:auto; bottom:-5px;">
</div>

</div>


<?php } ?>


</div>



<div class="facility-row">


<div class="facility">

<i class="fa-solid fa-stairs"></i>

STAIRCASE

<span>
Floor Access
</span>

</div>


<div class="facility">

<i class="fa-solid fa-couch"></i>

COMMON ROOM

<span>
Student Lounge
</span>

</div>


<div class="facility washroom">

<i class="fa-solid fa-person-dress"></i>

GIRLS WASHROOM

</div>


<div class="facility washroom">

<i class="fa-solid fa-sink"></i>

WASH AREA

</div>


</div>


</div>



<div class="entrance">

<i class="fa-solid fa-arrow-up"></i>

MAIN ENTRANCE

</div>


</div>


<?php

    }
}

?>


</div>



<!-- RIGHT DETAILS -->

<div class="details">


<h2 id="detailRoom">

Select a Room

</h2>


<div class="room-visual">

<i class="fa-solid fa-bed"></i>

</div>


<div class="detail-row">

<span>
Block
</span>

<strong id="detailBlock">
—
</strong>

</div>


<div class="detail-row">

<span>
Floor
</span>

<strong id="detailFloor">
—
</strong>

</div>


<div class="detail-row">

<span>
Room No.
</span>

<strong id="detailNumber">
—
</strong>

</div>


<div class="detail-row">

<span>
Room Type
</span>

<strong id="detailType">
—
</strong>

</div>


<div class="detail-row">

<span>
Capacity
</span>

<strong id="detailCapacity">
—
</strong>

</div>


<div class="detail-row">

<span>
Occupied
</span>

<strong id="detailOccupied">
—
</strong>

</div>


<div class="detail-row">

<span>
Available
</span>

<strong id="detailVacant">
—
</strong>

</div>


<div class="detail-row">

<span>
Status
</span>

<strong class="status-line">

<i
    id="detailStatusDot"
    class="status-dot"
></i>

<span id="detailStatus">
—
</span>

</strong>

</div>



<div class="facility-list">

<h3>
Facilities
</h3>

<div>

<i class="fa-solid fa-bed"></i>

Bed for every student

</div>

<div>

<i class="fa-solid fa-chair"></i>

Study Table

</div>

<div>

<i class="fa-solid fa-box-archive"></i>

Storage / Almirah

</div>

<div>

<i class="fa-solid fa-wifi"></i>

Wi-Fi

</div>

</div>



<div class="occupant-box">

<strong>

<i class="fa-solid fa-user-group"></i>

Occupants

</strong>

<div id="occupantList">

Select a room to view occupants.

</div>

</div>


</div>


</div>


</div>


</div>

</div>



<script>

const blockSelect =
    document.getElementById(
        "blockSelect"
    );

const floorSelect =
    document.getElementById(
        "floorSelect"
    );


const originalFloors =
    Array.from(
        floorSelect.options
    );


function rebuildFloors() {

    const block =
        blockSelect.value;


    floorSelect.innerHTML =
        "";


    originalFloors
        .filter(
            option =>
                option.dataset.block ===
                block
        )
        .forEach(
            option => {

                floorSelect.appendChild(
                    option.cloneNode(true)
                );
            }
        );


    showMap();
}


function showMap() {

    if (
        floorSelect.options.length === 0
    ) {
        return;
    }


    const block =
        blockSelect.value;


    const combined =
        floorSelect.value;


    const floor =
        combined
            .split("___")
            .slice(1)
            .join("___");


    document
        .querySelectorAll(
            ".floor-view"
        )
        .forEach(
            view => {

                view.classList.toggle(
                    "active",
                    view.dataset.block === block
                    &&
                    view.dataset.floor === floor
                );
            }
        );


    document.getElementById(
        "mainBlockTitle"
    ).textContent =
        "Girls Hostel – Block " +
        block;


    document.getElementById(
        "mainFloorTitle"
    ).textContent =
        "Floor " +
        floor;


    setTimeout(
        selectCurrentRoom,
        50
    );
}


function showRoom(room) {

    const number =
        room.dataset.room;

    const block =
        room.dataset.block;

    const floor =
        room.dataset.floor;

    const capacity =
        parseInt(
            room.dataset.capacity
        );

    const occupancy =
        parseInt(
            room.dataset.occupancy
        );

    const vacant =
        parseInt(
            room.dataset.vacant
        );

    const status =
        room.dataset.status;

    const isCurrent =
        room.dataset.current === "1";

    const occupants =
        room.dataset.occupants;


    document.getElementById(
        "detailRoom"
    ).textContent =
        number;


    document.getElementById(
        "detailBlock"
    ).textContent =
        block;


    document.getElementById(
        "detailFloor"
    ).textContent =
        floor;


    document.getElementById(
        "detailNumber"
    ).textContent =
        number;


    document.getElementById(
        "detailCapacity"
    ).textContent =
        capacity;


    document.getElementById(
        "detailOccupied"
    ).textContent =
        occupancy;


    document.getElementById(
        "detailVacant"
    ).textContent =
        vacant;


    document.getElementById(
        "detailType"
    ).textContent =
        capacity === 1
        ? "Single Sharing"
        : capacity === 2
        ? "Double Sharing"
        : capacity === 3
        ? "Triple Sharing"
        : capacity + " Sharing";


    let statusText = "";

    if (isCurrent) {

        statusText =
            "Your Room";

    } else if (status === "available") {

        statusText =
            "Available";

    } else if (status === "partial") {

        statusText =
            "Partially Occupied";

    } else {

        statusText =
            "Fully Occupied";
    }


    document.getElementById(
        "detailStatus"
    ).textContent =
        statusText;


    const dot =
        document.getElementById(
            "detailStatusDot"
        );


    dot.className =
        "status-dot " +
        (
            isCurrent
            ? "your"
            : status
        );


    const occupantList =
        document.getElementById(
            "occupantList"
        );


    if (
        !occupants ||
        occupants.trim() === ""
    ) {

        occupantList.innerHTML =
            '<div class="occupant-name">No student assigned</div>';

    } else {

        const names =
            occupants.split("||");


        occupantList.innerHTML =
            names.map(
                (name, index) =>
                    '<div class="occupant-name">' +
                    (index + 1) +
                    '. ' +
                    escapeHtml(name) +
                    '</div>'
            ).join("");
    }
}


function escapeHtml(text) {

    const div =
        document.createElement("div");

    div.textContent =
        text;

    return div.innerHTML;
}


function selectCurrentRoom() {

    const currentRoom =
        document.querySelector(
            ".floor-view.active .your-room"
        );


    if (currentRoom) {

        showRoom(currentRoom);

    } else {

        const firstRoom =
            document.querySelector(
                ".floor-view.active .room"
            );


        if (firstRoom) {

            showRoom(firstRoom);
        }
    }
}


blockSelect.addEventListener(
    "change",
    rebuildFloors
);


floorSelect.addEventListener(
    "change",
    showMap
);


/* start with student's own floor */

blockSelect.value =
    <?php echo json_encode((string)$defaultBlock); ?>;


rebuildFloors();


const requiredFloor =
    <?php
    echo json_encode(
        (string)$defaultBlock .
        "___" .
        (string)$defaultFloor
    );
    ?>;


if (
    Array.from(
        floorSelect.options
    ).some(
        option =>
            option.value ===
            requiredFloor
    )
) {

    floorSelect.value =
        requiredFloor;
}


showMap();

</script>


</body>
</html>
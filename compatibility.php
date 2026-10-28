<?php

session_start();
include "../db.php";

date_default_timezone_set("Asia/Kolkata");

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
   FLASH
   ========================================================= */

$message = "";
$messageType = "";

if (isset($_SESSION["compatibility_message"])) {

    $message = $_SESSION["compatibility_message"];

    $messageType =
        $_SESSION["compatibility_message_type"] ?? "";

    unset($_SESSION["compatibility_message"]);
    unset($_SESSION["compatibility_message_type"]);
}


/* =========================================================
   CURRENT STUDENT
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
        r.floor

     FROM users u

     LEFT JOIN rooms r
        ON u.room_id = r.id

     WHERE u.id = ?
     AND u.role = 'student'

     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);

$currentStudent =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


$currentRoomId =
    !empty($currentStudent["room_id"])
    ? intval($currentStudent["room_id"])
    : 0;


/* =========================================================
   SAVE PREFERENCES
   ========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["save_preferences"])
) {

    $fields = [
        "sleep_time",
        "wake_time",
        "cleanliness",
        "study_style",
        "noise_preference",
        "social_level",
        "guest_preference",
        "light_preference",
        "fan_ac_preference",
        "food_preference",
        "sharing_preference"
    ];

    $values = [];

    foreach ($fields as $field) {

        $values[$field] =
            trim($_POST[$field] ?? "");

        if ($values[$field] === "") {

            $_SESSION["compatibility_message"] =
                "Please answer all 11 questions.";

            $_SESSION["compatibility_message_type"] =
                "error";

            header("Location: compatibility.php");
            exit;
        }
    }


    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO roommate_preferences
        (
            user_id,
            sleep_time,
            wake_time,
            cleanliness,
            study_style,
            noise_preference,
            social_level,
            guest_preference,
            light_preference,
            fan_ac_preference,
            food_preference,
            sharing_preference
        )

        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)

        ON DUPLICATE KEY UPDATE

            sleep_time = VALUES(sleep_time),
            wake_time = VALUES(wake_time),
            cleanliness = VALUES(cleanliness),
            study_style = VALUES(study_style),
            noise_preference = VALUES(noise_preference),
            social_level = VALUES(social_level),
            guest_preference = VALUES(guest_preference),
            light_preference = VALUES(light_preference),
            fan_ac_preference = VALUES(fan_ac_preference),
            food_preference = VALUES(food_preference),
            sharing_preference = VALUES(sharing_preference)"
    );


    mysqli_stmt_bind_param(
        $stmt,
        "isssssssssss",
        $userId,
        $values["sleep_time"],
        $values["wake_time"],
        $values["cleanliness"],
        $values["study_style"],
        $values["noise_preference"],
        $values["social_level"],
        $values["guest_preference"],
        $values["light_preference"],
        $values["fan_ac_preference"],
        $values["food_preference"],
        $values["sharing_preference"]
    );


    if (mysqli_stmt_execute($stmt)) {

        $_SESSION["compatibility_message"] =
            "Preferences saved. Your compatibility results are ready.";

        $_SESSION["compatibility_message_type"] =
            "success";

    } else {

        $_SESSION["compatibility_message"] =
            "Unable to save preferences.";

        $_SESSION["compatibility_message_type"] =
            "error";
    }

    mysqli_stmt_close($stmt);

    header("Location: compatibility.php");
    exit;
}


/* =========================================================
   MY PREFERENCES
   ========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM roommate_preferences
     WHERE user_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);

$myPreferences =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/* =========================================================
   COMPATIBILITY LOGIC
   ========================================================= */

function flexibleValue($value)
{
    return in_array(
        $value,
        ["Flexible", "No Preference"],
        true
    );
}


function preferenceScore(
    $field,
    $mine,
    $other
) {

    if ($mine === $other) {
        return 10;
    }

    if (
        flexibleValue($mine) ||
        flexibleValue($other)
    ) {
        return 7;
    }


    $ordered = [

        "sleep_time" => [
            "Early",
            "Normal",
            "Late"
        ],

        "wake_time" => [
            "Early",
            "Normal",
            "Late"
        ],

        "cleanliness" => [
            "Very Clean",
            "Moderate",
            "Flexible"
        ],

        "social_level" => [
            "Private",
            "Balanced",
            "Very Social"
        ],

        "fan_ac_preference" => [
            "Low",
            "Medium",
            "High"
        ]
    ];


    if (isset($ordered[$field])) {

        $a =
            array_search(
                $mine,
                $ordered[$field],
                true
            );

        $b =
            array_search(
                $other,
                $ordered[$field],
                true
            );

        if (
            $a !== false &&
            $b !== false
        ) {

            $difference =
                abs($a - $b);

            if ($difference === 1) {
                return 5;
            }

            if ($difference >= 2) {
                return 1;
            }
        }
    }

    return 3;
}


function calculateCompatibility(
    $mine,
    $other
) {

    $fields = [

        "sleep_time" =>
            "Sleep Timing",

        "wake_time" =>
            "Wake-up Timing",

        "cleanliness" =>
            "Cleanliness",

        "study_style" =>
            "Study Style",

        "noise_preference" =>
            "Noise",

        "social_level" =>
            "Social Nature",

        "guest_preference" =>
            "Guests",

        "light_preference" =>
            "Night Light",

        "fan_ac_preference" =>
            "Fan / AC",

        "food_preference" =>
            "Food",

        "sharing_preference" =>
            "Sharing"
    ];


    $score = 0;

    $matches = [];
    $differences = [];


    foreach (
        $fields as
        $field => $label
    ) {

        $points =
            preferenceScore(
                $field,
                $mine[$field],
                $other[$field]
            );

        $score += $points;


        if ($points >= 7) {

            $matches[] = $label;

        } elseif ($points <= 3) {

            $differences[] = $label;
        }
    }


    return [

        "percentage" =>
            round(($score / 110) * 100),

        "matches" =>
            $matches,

        "differences" =>
            $differences
    ];
}


/* =========================================================
   OTHER STUDENTS
   ========================================================= */

$compatibilityResults = [];


if ($myPreferences) {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT

            u.id,
            u.name,
            u.email,
            u.room_id,

            r.room_number,

            rp.sleep_time,
            rp.wake_time,
            rp.cleanliness,
            rp.study_style,
            rp.noise_preference,
            rp.social_level,
            rp.guest_preference,
            rp.light_preference,
            rp.fan_ac_preference,
            rp.food_preference,
            rp.sharing_preference

         FROM users u

         INNER JOIN roommate_preferences rp
            ON u.id = rp.user_id

         LEFT JOIN rooms r
            ON u.room_id = r.id

         WHERE
            u.role = 'student'
            AND u.id != ?

         ORDER BY u.name ASC"
    );


    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $userId
    );

    mysqli_stmt_execute($stmt);

    $result =
        mysqli_stmt_get_result($stmt);


    while (
        $person =
        mysqli_fetch_assoc($result)
    ) {

        $resultScore =
            calculateCompatibility(
                $myPreferences,
                $person
            );


        $person["compatibility"] =
            $resultScore["percentage"];

        $person["matches"] =
            $resultScore["matches"];

        $person["differences"] =
            $resultScore["differences"];


        $person["current_roommate"] =
            (
                $currentRoomId > 0 &&
                intval($person["room_id"]) ===
                $currentRoomId
            );


        $compatibilityResults[] =
            $person;
    }

    mysqli_stmt_close($stmt);


    usort(
        $compatibilityResults,
        function($a, $b) {

            if (
                $a["current_roommate"] &&
                !$b["current_roommate"]
            ) {
                return -1;
            }

            if (
                !$a["current_roommate"] &&
                $b["current_roommate"]
            ) {
                return 1;
            }

            return
                $b["compatibility"]
                <=>
                $a["compatibility"];
        }
    );
}


/* =========================================================
   SUMMARY
   ========================================================= */

$totalProfiles =
    count($compatibilityResults);

$highestScore = 0;

$currentRoomScore = null;

$currentRoomTotal = 0;

$currentRoomCount = 0;


foreach (
    $compatibilityResults as $person
) {

    if (
        $person["compatibility"] >
        $highestScore
    ) {

        $highestScore =
            $person["compatibility"];
    }


    if ($person["current_roommate"]) {

        $currentRoomTotal +=
            $person["compatibility"];

        $currentRoomCount++;
    }
}


if ($currentRoomCount > 0) {

    $currentRoomScore =
        round(
            $currentRoomTotal /
            $currentRoomCount
        );
}


/* =========================================================
   HELPERS
   ========================================================= */

function checkedChoice(
    $preferences,
    $field,
    $value
) {

    return (
        is_array($preferences) &&
        isset($preferences[$field]) &&
        $preferences[$field] === $value
    )
        ? "checked"
        : "";
}


function initialLetter($name)
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
Roommate Compatibility - UniStay
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

    --background:#EFF4F7;

    --border:#DFE7EC;

    --text:#1E2639;
    --muted:#718092;

    --tealSoft:#E2F2F4;
    --greenSoft:#E3F5EC;
    --redSoft:#FBE8EA;
    --yellowSoft:#FFF3D6;

    --shadow:
        0 10px 28px
        rgba(31,39,71,.07);
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
        var(--background);

    color:
        var(--text);
}


.page-layout {

    display:flex;

    min-height:100vh;
}


.main {

    flex:1;

    min-width:0;

    padding:
        28px 32px 55px;
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


.title-area {

    display:flex;

    align-items:center;

    gap:14px;
}


.title-icon {

    width:52px;
    height:52px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:15px;

    color:white;

    background:
        linear-gradient(
            135deg,
            var(--navy),
            var(--teal)
        );

    font-size:20px;
}


.title-area h1 {

    font-size:29px;
}


.title-area p {

    margin-top:4px;

    color:
        var(--muted);

    font-size:13px;
}


.profile {

    display:flex;

    align-items:center;

    gap:10px;

    padding:
        7px 14px 7px 7px;

    border:
        1px solid
        var(--border);

    border-radius:30px;

    background:white;
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

    font-weight:800;
}


.profile strong {

    display:block;

    font-size:13px;
}


.profile span {

    display:block;

    margin-top:2px;

    color:
        var(--muted);

    font-size:10px;
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
        330px;

    gap:30px;

    align-items:center;

    padding:
        36px;

    margin-bottom:20px;

    border-radius:22px;

    color:white;

    background:
        linear-gradient(
            120deg,
            #1F2747,
            #17475E,
            #0E6077,
            #23879A
        );
}


.hero::after {

    content:"";

    position:absolute;

    width:260px;
    height:260px;

    right:-80px;
    top:-100px;

    border-radius:50%;

    background:
        rgba(255,255,255,.06);
}


.hero-label {

    color:#F9D978;

    font-size:11px;

    font-weight:800;

    letter-spacing:1px;

    text-transform:uppercase;
}


.hero h2 {

    margin-top:9px;

    font-size:35px;

    line-height:1.15;
}


.hero p {

    max-width:650px;

    margin-top:11px;

    color:
        rgba(255,255,255,.82);

    font-size:14px;

    line-height:1.65;
}


.hero-tags {

    display:flex;

    flex-wrap:wrap;

    gap:8px;

    margin-top:18px;
}


.hero-tag {

    padding:
        7px 11px;

    border-radius:20px;

    background:
        rgba(255,255,255,.10);

    border:
        1px solid
        rgba(255,255,255,.10);

    font-size:10px;
}


.hero-tag.yellow {

    color:
        var(--navy);

    background:
        var(--mustard);

    border:none;

    font-weight:800;
}


.hero-side {

    position:relative;

    z-index:2;

    padding:20px;

    border-radius:17px;

    background:
        rgba(255,255,255,.10);

    border:
        1px solid
        rgba(255,255,255,.13);
}


.hero-room-icon {

    width:46px;
    height:46px;

    display:flex;

    align-items:center;

    justify-content:center;

    margin-bottom:14px;

    border-radius:12px;

    color:
        var(--navy);

    background:
        var(--mustard);
}


.hero-side small {

    color:
        rgba(255,255,255,.65);

    font-size:9px;

    text-transform:uppercase;
}


.hero-side h3 {

    margin-top:5px;

    font-size:27px;
}


.hero-side p {

    margin-top:5px;

    font-size:11px;
}


/* =========================================================
   MESSAGE
   ========================================================= */

.message {

    margin-bottom:20px;

    padding:
        14px 16px;

    border-radius:12px;

    font-size:12px;

    font-weight:700;
}


.message.success {

    color:#1E7558;

    background:
        var(--greenSoft);
}


.message.error {

    color:#A44249;

    background:
        var(--redSoft);
}


/* =========================================================
   STATS
   ========================================================= */

.stats {

    display:grid;

    grid-template-columns:
        repeat(4,minmax(0,1fr));

    gap:14px;

    margin-bottom:22px;
}


.stat {

    display:flex;

    align-items:center;

    gap:13px;

    min-height:105px;

    padding:17px;

    border-radius:15px;

    background:white;

    border:
        1px solid
        var(--border);

    box-shadow:
        var(--shadow);
}


.stat-icon {

    width:46px;
    height:46px;

    display:flex;

    align-items:center;

    justify-content:center;

    flex-shrink:0;

    border-radius:12px;

    font-size:17px;
}


.stat:nth-child(1)
.stat-icon {

    color:
        var(--teal);

    background:
        var(--tealSoft);
}


.stat:nth-child(2)
.stat-icon {

    color:#9B6A0B;

    background:
        var(--yellowSoft);
}


.stat:nth-child(3)
.stat-icon {

    color:
        var(--green);

    background:
        var(--greenSoft);
}


.stat:nth-child(4)
.stat-icon {

    color:#397A9B;

    background:#E5F0F5;
}


.stat small {

    color:
        var(--muted);

    font-size:9px;

    text-transform:uppercase;
}


.stat h3 {

    margin-top:3px;

    font-size:23px;
}


/* =========================================================
   CONTENT
   ========================================================= */

.content-grid {

    display:grid;

    grid-template-columns:
        minmax(0,1.5fr)
        320px;

    gap:20px;

    align-items:start;

    margin-bottom:22px;
}


.panel {

    overflow:hidden;

    border-radius:18px;

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

    gap:11px;

    padding:
        20px 22px;

    border-bottom:
        1px solid
        #EDF1F3;
}


.panel-icon {

    width:42px;
    height:42px;

    display:flex;

    align-items:center;

    justify-content:center;

    flex-shrink:0;

    border-radius:11px;

    color:
        var(--teal);

    background:
        var(--tealSoft);
}


.panel-head h2 {

    font-size:18px;
}


.panel-head p {

    margin-top:3px;

    color:
        var(--muted);

    font-size:11px;
}


/* =========================================================
   QUESTIONS
   ========================================================= */

.question-list {

    padding:18px;
}


.question-card {

    margin-bottom:12px;

    padding:16px;

    border-radius:13px;

    background:#FAFCFD;

    border:
        1px solid
        #E3E9ED;
}


.question-card:last-child {

    margin-bottom:0;
}


.question-head {

    display:flex;

    align-items:center;

    gap:10px;

    margin-bottom:12px;
}


.question-number {

    width:28px;
    height:28px;

    display:flex;

    align-items:center;

    justify-content:center;

    flex-shrink:0;

    border-radius:8px;

    color:white;

    background:
        var(--teal);

    font-size:10px;

    font-weight:800;
}


.question-title {

    font-size:13px;

    font-weight:750;
}


.choice-row {

    display:flex;

    flex-wrap:wrap;

    gap:8px;
}


.choice {

    position:relative;
}


.choice input {

    position:absolute;

    opacity:0;
}


.choice label {

    display:block;

    padding:
        8px 11px;

    border:
        1px solid
        #D9E3E7;

    border-radius:20px;

    color:#586A7C;

    background:white;

    cursor:pointer;

    font-size:10px;

    font-weight:650;

    transition:.18s;
}


.choice input:checked + label {

    color:white;

    border-color:
        var(--teal);

    background:
        var(--teal);

    box-shadow:
        0 5px 12px
        rgba(14,96,119,.18);
}


.choice label:hover {

    border-color:
        var(--teal);
}


.save-wrap {

    padding:
        0 18px 20px;
}


.save-btn {

    width:100%;

    padding:13px;

    border:none;

    border-radius:11px;

    color:white;

    background:
        linear-gradient(
            135deg,
            var(--teal),
            var(--teal2)
        );

    cursor:pointer;

    font-size:12px;

    font-weight:800;

    box-shadow:
        0 8px 18px
        rgba(14,96,119,.18);
}


/* =========================================================
   SIDE INFO
   ========================================================= */

.info-panel {

    position:sticky;

    top:20px;
}


.info-content {

    padding:20px;
}


.score-example {

    display:flex;

    align-items:center;

    justify-content:center;

    width:130px;
    height:130px;

    margin:
        5px auto 20px;

    border-radius:50%;

    background:
        conic-gradient(
            var(--green) 0 82%,
            #E6ECEF 82% 100%
        );
}


.score-inner {

    width:98px;
    height:98px;

    display:flex;

    align-items:center;

    justify-content:center;

    flex-direction:column;

    border-radius:50%;

    background:white;
}


.score-inner strong {

    color:
        var(--teal);

    font-size:24px;
}


.score-inner span {

    color:
        var(--muted);

    font-size:8px;

    text-transform:uppercase;
}


.info-step {

    display:flex;

    gap:10px;

    margin-bottom:15px;
}


.info-step-icon {

    width:31px;
    height:31px;

    display:flex;

    align-items:center;

    justify-content:center;

    flex-shrink:0;

    border-radius:8px;

    color:
        var(--teal);

    background:
        var(--tealSoft);

    font-size:11px;
}


.info-step strong {

    display:block;

    font-size:11px;
}


.info-step p {

    margin-top:3px;

    color:
        var(--muted);

    font-size:9px;

    line-height:1.45;
}


.info-note {

    margin-top:17px;

    padding:11px;

    border-radius:9px;

    color:#796222;

    background:
        var(--yellowSoft);

    font-size:9px;

    line-height:1.5;
}


/* =========================================================
   RESULTS
   ========================================================= */

.results-panel {

    margin-top:0;
}


.results-grid {

    display:grid;

    grid-template-columns:
        repeat(2,minmax(0,1fr));

    gap:14px;

    padding:18px;
}


.match-card {

    position:relative;

    overflow:hidden;

    padding:17px;

    border-radius:15px;

    background:#FAFCFD;

    border:
        1px solid
        #E1E8EC;
}


.match-card.current {

    border:
        2px solid
        var(--mustard);

    background:
        #FFFDF7;
}


.current-badge {

    display:inline-flex;

    margin-bottom:10px;

    padding:
        5px 8px;

    border-radius:20px;

    color:#8B620E;

    background:
        var(--yellowSoft);

    font-size:8px;

    font-weight:800;

    text-transform:uppercase;
}


.match-head {

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:12px;
}


.person-box {

    display:flex;

    align-items:center;

    gap:10px;
}


.person-avatar {

    width:44px;
    height:44px;

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

    font-weight:800;
}


.person-name {

    font-size:14px;

    font-weight:800;
}


.person-room {

    margin-top:3px;

    color:
        var(--muted);

    font-size:9px;
}


.match-score {

    width:60px;
    height:60px;

    display:flex;

    align-items:center;

    justify-content:center;

    flex-shrink:0;

    border-radius:50%;

    color:
        var(--teal);

    background:
        var(--tealSoft);

    font-size:15px;

    font-weight:850;
}


.match-progress {

    height:7px;

    margin-top:14px;

    overflow:hidden;

    border-radius:20px;

    background:#E7EDF0;
}


.match-progress span {

    display:block;

    height:100%;

    border-radius:20px;

    background:
        linear-gradient(
            90deg,
            var(--teal),
            var(--green)
        );
}


.match-section-title {

    margin-top:13px;

    margin-bottom:7px;

    color:
        var(--muted);

    font-size:8px;

    font-weight:800;

    text-transform:uppercase;
}


.tags {

    display:flex;

    flex-wrap:wrap;

    gap:5px;
}


.tag {

    padding:
        5px 7px;

    border-radius:20px;

    font-size:8px;

    font-weight:700;
}


.tag.good {

    color:#28785D;

    background:
        var(--greenSoft);
}


.tag.bad {

    color:#A3424A;

    background:
        var(--redSoft);
}


/* EMPTY */

.empty {

    padding:
        50px 25px;

    text-align:center;

    color:
        var(--muted);
}


.empty-icon {

    width:60px;
    height:60px;

    display:flex;

    align-items:center;

    justify-content:center;

    margin:
        0 auto 12px;

    border-radius:16px;

    color:
        var(--teal);

    background:
        var(--tealSoft);

    font-size:23px;
}


.empty h3 {

    color:
        var(--text);

    font-size:16px;
}


.empty p {

    max-width:420px;

    margin:
        6px auto 0;

    font-size:10px;

    line-height:1.5;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media(max-width:1150px) {

    .hero {

        grid-template-columns:1fr;
    }


    .hero-side {

        max-width:380px;
    }


    .content-grid {

        grid-template-columns:1fr;
    }


    .info-panel {

        position:static;
    }
}


@media(max-width:850px) {

    .stats {

        grid-template-columns:
            repeat(2,1fr);
    }


    .results-grid {

        grid-template-columns:1fr;
    }
}


@media(max-width:600px) {

    .main {

        padding:15px;
    }


    .stats {

        grid-template-columns:1fr;
    }


    .hero {

        padding:24px;
    }


    .hero h2 {

        font-size:27px;
    }
}

</style>

</head>


<body>


<div class="page-layout">


<?php include "sidebar.php"; ?>


<div class="main">


<!-- TOPBAR -->

<div class="topbar">


<div class="title-area">


<div class="title-icon">

<i class="fa-solid fa-user-group"></i>

</div>


<div>

<h1>
Roommate Compatibility
</h1>

<p>
Compare living habits before choosing a room or roommate.
</p>

</div>


</div>



<div class="profile">


<div class="profile-avatar">

<?php
echo initialLetter(
    $userName
);
?>

</div>


<div>

<strong>
<?php
echo htmlspecialchars(
    $userName
);
?>
</strong>

<span>
Student
</span>

</div>


</div>


</div>



<!-- HERO -->

<div class="hero">


<div>


<div class="hero-label">
Smart Roommate Matching
</div>


<h2>
A better room starts with better compatibility.
</h2>


<p>

UniStay compares everyday living habits such as
sleep schedule, cleanliness, study environment,
noise and sharing preferences to help students
understand roommate compatibility.

</p>


<div class="hero-tags">

<span class="hero-tag yellow">

<i class="fa-solid fa-wand-magic-sparkles"></i>

Smart Match

</span>

<span class="hero-tag">
11 Living Preferences
</span>

<span class="hero-tag">
Transparent Score
</span>

</div>


</div>



<div class="hero-side">


<div class="hero-room-icon">

<i class="fa-solid fa-bed"></i>

</div>


<small>
Your Current Room
</small>


<h3>

<?php

echo !empty(
    $currentStudent["room_number"]
)
    ? htmlspecialchars(
        $currentStudent["room_number"]
    )
    : "Not Assigned";

?>

</h3>


<p>

<?php

if (
    !empty(
        $currentStudent["room_number"]
    )
) {

    echo htmlspecialchars(
        $currentStudent["block"]
    );

    echo " • ";

    echo htmlspecialchars(
        $currentStudent["floor"]
    );

} else {

    echo "No room assigned yet";
}

?>

</p>


</div>


</div>



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



<!-- STATS -->

<div class="stats">


<div class="stat">

<div class="stat-icon">

<i class="fa-solid fa-clipboard-check"></i>

</div>

<div>

<small>
Your Profile
</small>

<h3>

<?php
echo $myPreferences
    ? "Ready"
    : "Pending";
?>

</h3>

</div>

</div>



<div class="stat">

<div class="stat-icon">

<i class="fa-solid fa-users"></i>

</div>

<div>

<small>
Students Compared
</small>

<h3>
<?php echo $totalProfiles; ?>
</h3>

</div>

</div>



<div class="stat">

<div class="stat-icon">

<i class="fa-solid fa-bed"></i>

</div>

<div>

<small>
Current Room Match
</small>

<h3>

<?php

echo $currentRoomScore !== null
    ? $currentRoomScore . "%"
    : "—";

?>

</h3>

</div>

</div>



<div class="stat">

<div class="stat-icon">

<i class="fa-solid fa-chart-line"></i>

</div>

<div>

<small>
Highest Match
</small>

<h3>

<?php

echo $totalProfiles > 0
    ? $highestScore . "%"
    : "—";

?>

</h3>

</div>

</div>


</div>



<div class="content-grid">


<!-- QUESTIONS -->

<div class="panel">


<div class="panel-head">


<div class="panel-icon">

<i class="fa-solid fa-sliders"></i>

</div>


<div>

<h2>
Your Living Preferences
</h2>

<p>
Choose the option closest to your normal routine.
</p>

</div>


</div>



<form method="POST">


<div class="question-list">


<!-- 1 -->

<div class="question-card">

<div class="question-head">

<div class="question-number">
01
</div>

<div class="question-title">
When do you usually sleep?
</div>

</div>

<div class="choice-row">

<?php
$options = ["Early", "Normal", "Late"];

foreach ($options as $option) {
?>

<div class="choice">

<input
    type="radio"
    id="sleep_<?php echo md5($option); ?>"
    name="sleep_time"
    value="<?php echo $option; ?>"
    <?php echo checkedChoice($myPreferences, "sleep_time", $option); ?>
    required
>

<label for="sleep_<?php echo md5($option); ?>">

<?php echo $option; ?>

</label>

</div>

<?php } ?>

</div>

</div>



<!-- 2 -->

<div class="question-card">

<div class="question-head">

<div class="question-number">
02
</div>

<div class="question-title">
When do you usually wake up?
</div>

</div>

<div class="choice-row">

<?php
$options = ["Early", "Normal", "Late"];

foreach ($options as $option) {
?>

<div class="choice">

<input
    type="radio"
    id="wake_<?php echo md5($option); ?>"
    name="wake_time"
    value="<?php echo $option; ?>"
    <?php echo checkedChoice($myPreferences, "wake_time", $option); ?>
    required
>

<label for="wake_<?php echo md5($option); ?>">

<?php echo $option; ?>

</label>

</div>

<?php } ?>

</div>

</div>



<!-- 3 -->

<div class="question-card">

<div class="question-head">

<div class="question-number">
03
</div>

<div class="question-title">
How important is room cleanliness?
</div>

</div>

<div class="choice-row">

<?php
$options = [
    "Very Clean",
    "Moderate",
    "Flexible"
];

foreach ($options as $option) {
?>

<div class="choice">

<input
    type="radio"
    id="clean_<?php echo md5($option); ?>"
    name="cleanliness"
    value="<?php echo $option; ?>"
    <?php echo checkedChoice($myPreferences, "cleanliness", $option); ?>
    required
>

<label for="clean_<?php echo md5($option); ?>">

<?php echo $option; ?>

</label>

</div>

<?php } ?>

</div>

</div>



<!-- 4 -->

<div class="question-card">

<div class="question-head">

<div class="question-number">
04
</div>

<div class="question-title">
What study environment do you prefer?
</div>

</div>

<div class="choice-row">

<?php
$options = [
    "Silent",
    "Background Music",
    "Flexible"
];

foreach ($options as $option) {
?>

<div class="choice">

<input
    type="radio"
    id="study_<?php echo md5($option); ?>"
    name="study_style"
    value="<?php echo $option; ?>"
    <?php echo checkedChoice($myPreferences, "study_style", $option); ?>
    required
>

<label for="study_<?php echo md5($option); ?>">

<?php echo $option; ?>

</label>

</div>

<?php } ?>

</div>

</div>



<!-- 5 -->

<div class="question-card">

<div class="question-head">

<div class="question-number">
05
</div>

<div class="question-title">
How much room noise are you comfortable with?
</div>

</div>

<div class="choice-row">

<?php
$options = [
    "Quiet",
    "Moderate",
    "No Preference"
];

foreach ($options as $option) {
?>

<div class="choice">

<input
    type="radio"
    id="noise_<?php echo md5($option); ?>"
    name="noise_preference"
    value="<?php echo $option; ?>"
    <?php echo checkedChoice($myPreferences, "noise_preference", $option); ?>
    required
>

<label for="noise_<?php echo md5($option); ?>">

<?php echo $option; ?>

</label>

</div>

<?php } ?>

</div>

</div>



<!-- 6 -->

<div class="question-card">

<div class="question-head">

<div class="question-number">
06
</div>

<div class="question-title">
How social are you in the room?
</div>

</div>

<div class="choice-row">

<?php
$options = [
    "Private",
    "Balanced",
    "Very Social"
];

foreach ($options as $option) {
?>

<div class="choice">

<input
    type="radio"
    id="social_<?php echo md5($option); ?>"
    name="social_level"
    value="<?php echo $option; ?>"
    <?php echo checkedChoice($myPreferences, "social_level", $option); ?>
    required
>

<label for="social_<?php echo md5($option); ?>">

<?php echo $option; ?>

</label>

</div>

<?php } ?>

</div>

</div>



<!-- 7 -->

<div class="question-card">

<div class="question-head">

<div class="question-number">
07
</div>

<div class="question-title">
How often are you comfortable with guests?
</div>

</div>

<div class="choice-row">

<?php
$options = [
    "Rare Guests",
    "Occasional Guests",
    "No Preference"
];

foreach ($options as $option) {
?>

<div class="choice">

<input
    type="radio"
    id="guest_<?php echo md5($option); ?>"
    name="guest_preference"
    value="<?php echo $option; ?>"
    <?php echo checkedChoice($myPreferences, "guest_preference", $option); ?>
    required
>

<label for="guest_<?php echo md5($option); ?>">

<?php echo $option; ?>

</label>

</div>

<?php } ?>

</div>

</div>



<!-- 8 -->

<div class="question-card">

<div class="question-head">

<div class="question-number">
08
</div>

<div class="question-title">
What night-light setup do you prefer?
</div>

</div>

<div class="choice-row">

<?php
$options = [
    "Lights Off Early",
    "Study Light",
    "No Preference"
];

foreach ($options as $option) {
?>

<div class="choice">

<input
    type="radio"
    id="light_<?php echo md5($option); ?>"
    name="light_preference"
    value="<?php echo $option; ?>"
    <?php echo checkedChoice($myPreferences, "light_preference", $option); ?>
    required
>

<label for="light_<?php echo md5($option); ?>">

<?php echo $option; ?>

</label>

</div>

<?php } ?>

</div>

</div>



<!-- 9 -->

<div class="question-card">

<div class="question-head">

<div class="question-number">
09
</div>

<div class="question-title">
Your usual fan / AC preference?
</div>

</div>

<div class="choice-row">

<?php
$options = [
    "Low",
    "Medium",
    "High",
    "No Preference"
];

foreach ($options as $option) {
?>

<div class="choice">

<input
    type="radio"
    id="fan_<?php echo md5($option); ?>"
    name="fan_ac_preference"
    value="<?php echo $option; ?>"
    <?php echo checkedChoice($myPreferences, "fan_ac_preference", $option); ?>
    required
>

<label for="fan_<?php echo md5($option); ?>">

<?php echo $option; ?>

</label>

</div>

<?php } ?>

</div>

</div>



<!-- 10 -->

<div class="question-card">

<div class="question-head">

<div class="question-number">
10
</div>

<div class="question-title">
What is your food preference?
</div>

</div>

<div class="choice-row">

<?php
$options = [
    "Vegetarian",
    "Non-Vegetarian",
    "No Preference"
];

foreach ($options as $option) {
?>

<div class="choice">

<input
    type="radio"
    id="food_<?php echo md5($option); ?>"
    name="food_preference"
    value="<?php echo $option; ?>"
    <?php echo checkedChoice($myPreferences, "food_preference", $option); ?>
    required
>

<label for="food_<?php echo md5($option); ?>">

<?php echo $option; ?>

</label>

</div>

<?php } ?>

</div>

</div>



<!-- 11 -->

<div class="question-card">

<div class="question-head">

<div class="question-number">
11
</div>

<div class="question-title">
How do you feel about sharing personal items?
</div>

</div>

<div class="choice-row">

<?php
$options = [
    "Ask Before Sharing",
    "Comfortable Sharing",
    "No Preference"
];

foreach ($options as $option) {
?>

<div class="choice">

<input
    type="radio"
    id="share_<?php echo md5($option); ?>"
    name="sharing_preference"
    value="<?php echo $option; ?>"
    <?php echo checkedChoice($myPreferences, "sharing_preference", $option); ?>
    required
>

<label for="share_<?php echo md5($option); ?>">

<?php echo $option; ?>

</label>

</div>

<?php } ?>

</div>

</div>


</div>


<div class="save-wrap">

<button
    type="submit"
    name="save_preferences"
    class="save-btn"
>

<i class="fa-solid fa-wand-magic-sparkles"></i>

<?php

echo $myPreferences
    ? "Update Compatibility Profile"
    : "Create Compatibility Profile";

?>

</button>

</div>


</form>


</div>



<!-- HOW IT WORKS -->

<div class="panel info-panel">


<div class="panel-head">

<div class="panel-icon">

<i class="fa-solid fa-chart-pie"></i>

</div>

<div>

<h2>
How Matching Works
</h2>

<p>
Simple preference-based comparison.
</p>

</div>

</div>


<div class="info-content">


<div class="score-example">

<div class="score-inner">

<strong>
82%
</strong>

<span>
Example Match
</span>

</div>

</div>



<div class="info-step">

<div class="info-step-icon">

<i class="fa-solid fa-list-check"></i>

</div>

<div>

<strong>
Complete preferences
</strong>

<p>
Answer all 11 living-habit questions.
</p>

</div>

</div>



<div class="info-step">

<div class="info-step-icon">

<i class="fa-solid fa-code-compare"></i>

</div>

<div>

<strong>
UniStay compares answers
</strong>

<p>
Similar and flexible choices receive higher points.
</p>

</div>

</div>



<div class="info-step">

<div class="info-step-icon">

<i class="fa-solid fa-percent"></i>

</div>

<div>

<strong>
Compatibility score
</strong>

<p>
The total is converted into an easy percentage.
</p>

</div>

</div>



<div class="info-step">

<div class="info-step-icon">

<i class="fa-solid fa-people-arrows-left-right"></i>

</div>

<div>

<strong>
Use it for room decisions
</strong>

<p>
Later this score also supports Smart Room Swap.
</p>

</div>

</div>


<div class="info-note">

<i class="fa-solid fa-circle-info"></i>

The score is guidance only. UniStay does not
automatically choose a roommate for the student.

</div>


</div>


</div>


</div>



<!-- RESULTS -->

<div class="panel results-panel">


<div class="panel-head">

<div class="panel-icon">

<i class="fa-solid fa-user-check"></i>

</div>

<div>

<h2>
Your Compatibility Matches
</h2>

<p>
See where your living habits match with other students.
</p>

</div>

</div>



<?php if (!$myPreferences) { ?>


<div class="empty">


<div class="empty-icon">

<i class="fa-solid fa-clipboard-question"></i>

</div>


<h3>
Create your compatibility profile
</h3>


<p>

Answer the questions above first. Your roommate
matches will appear here automatically.

</p>


</div>


<?php } elseif (
    count($compatibilityResults) === 0
) { ?>


<div class="empty">


<div class="empty-icon">

<i class="fa-solid fa-user-group"></i>

</div>


<h3>
Waiting for other students
</h3>


<p>

Your profile is ready. Compatibility matches will
appear once another student completes their preferences.

</p>


</div>


<?php } else { ?>


<div class="results-grid">


<?php foreach (
    $compatibilityResults as $person
) { ?>


<div
    class="match-card <?php echo $person["current_roommate"] ? "current" : ""; ?>"
>


<?php if ($person["current_roommate"]) { ?>

<div class="current-badge">

<i class="fa-solid fa-bed"></i>

Current Roommate

</div>

<?php } ?>



<div class="match-head">


<div class="person-box">


<div class="person-avatar">

<?php
echo initialLetter(
    $person["name"]
);
?>

</div>


<div>


<div class="person-name">

<?php
echo htmlspecialchars(
    $person["name"]
);
?>

</div>


<div class="person-room">

<i class="fa-solid fa-door-open"></i>

Room

<?php

echo !empty(
    $person["room_number"]
)
    ? htmlspecialchars(
        $person["room_number"]
    )
    : "Not Assigned";

?>

</div>


</div>


</div>



<div class="match-score">

<?php
echo intval(
    $person["compatibility"]
);
?>%

</div>


</div>



<div class="match-progress">

<span
    style="width:<?php echo intval($person["compatibility"]); ?>%;"
></span>

</div>



<div class="match-section-title">
Similar Habits
</div>


<div class="tags">


<?php

if (
    count(
        $person["matches"]
    ) > 0
) {

    foreach (
        array_slice(
            $person["matches"],
            0,
            6
        ) as $match
    ) {

?>

<span class="tag good">

<i class="fa-solid fa-check"></i>

<?php
echo htmlspecialchars(
    $match
);
?>

</span>

<?php

    }

} else {

?>

<span class="tag">
No strong similarities
</span>

<?php } ?>


</div>



<div class="match-section-title">
Different Habits
</div>


<div class="tags">


<?php

if (
    count(
        $person["differences"]
    ) > 0
) {

    foreach (
        array_slice(
            $person["differences"],
            0,
            5
        ) as $difference
    ) {

?>

<span class="tag bad">

<i class="fa-solid fa-circle-exclamation"></i>

<?php
echo htmlspecialchars(
    $difference
);
?>

</span>

<?php

    }

} else {

?>

<span class="tag good">

<i class="fa-solid fa-check"></i>

No major differences

</span>

<?php } ?>


</div>


</div>


<?php } ?>


</div>


<?php } ?>


</div>


</div>


</div>



<script>

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
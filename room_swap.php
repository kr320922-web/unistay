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

$userId   = intval($_SESSION["user_id"]);
$userName = $_SESSION["user_name"] ?? "Student";


/* =========================================================
   HELPERS
   ========================================================= */

function getInitial($name)
{
    $name = trim($name);

    return $name === ""
        ? "S"
        : strtoupper(substr($name, 0, 1));
}


function isFlexibleValue($value)
{
    return in_array(
        $value,
        [
            "Flexible",
            "No Preference"
        ],
        true
    );
}


function calculatePreferenceScore(
    $field,
    $myValue,
    $otherValue
) {

    if ($myValue === $otherValue) {
        return 10;
    }


    if (
        isFlexibleValue($myValue) ||
        isFlexibleValue($otherValue)
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

        $myPosition =
            array_search(
                $myValue,
                $ordered[$field],
                true
            );

        $otherPosition =
            array_search(
                $otherValue,
                $ordered[$field],
                true
            );


        if (
            $myPosition !== false &&
            $otherPosition !== false
        ) {

            $difference =
                abs(
                    $myPosition -
                    $otherPosition
                );


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


    foreach ($fields as $field) {

        if (
            !isset($mine[$field]) ||
            !isset($other[$field])
        ) {
            return null;
        }
    }


    $score = 0;


    foreach ($fields as $field) {

        $score +=
            calculatePreferenceScore(
                $field,
                $mine[$field],
                $other[$field]
            );
    }


    return round(
        ($score / 110) * 100
    );
}


/* =========================================================
   FLASH MESSAGE
   ========================================================= */

$message = "";
$messageType = "";

if (isset($_SESSION["swap_message"])) {

    $message =
        $_SESSION["swap_message"];

    $messageType =
        $_SESSION["swap_message_type"] ?? "";

    unset($_SESSION["swap_message"]);
    unset($_SESSION["swap_message_type"]);
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
        r.room_number

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

$currentResult =
    mysqli_stmt_get_result($stmt);

$currentStudent =
    mysqli_fetch_assoc($currentResult);

mysqli_stmt_close($stmt);


$currentRoomId =
    !empty($currentStudent["room_id"])
    ? intval($currentStudent["room_id"])
    : 0;


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

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $userId
);

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);

$myPreferences =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/* =========================================================
   POST ACTIONS
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /* =====================================================
       SEND SWAP REQUEST
       ===================================================== */

    if (isset($_POST["send_swap"])) {

        $receiverId =
            intval(
                $_POST["receiver_id"] ?? 0
            );

        $reason =
            trim(
                $_POST["reason"] ?? ""
            );


        if ($currentRoomId <= 0) {

            $_SESSION["swap_message"] =
                "You must have an assigned room before requesting a room swap.";

            $_SESSION["swap_message_type"] =
                "error";

            header("Location: room_swap.php");
            exit;
        }


        if (
            $receiverId <= 0 ||
            $receiverId === $userId ||
            $reason === ""
        ) {

            $_SESSION["swap_message"] =
                "Please select a student and enter a reason.";

            $_SESSION["swap_message_type"] =
                "error";

            header("Location: room_swap.php");
            exit;
        }


        mysqli_begin_transaction($conn);


        try {


            /* LOCK REQUESTER */

            $stmt = mysqli_prepare(
                $conn,
                "SELECT
                    id,
                    room_id

                 FROM users

                 WHERE id = ?
                 AND role = 'student'

                 FOR UPDATE"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $userId
            );

            mysqli_stmt_execute($stmt);

            $result =
                mysqli_stmt_get_result($stmt);

            $requester =
                mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);


            if (
                !$requester ||
                empty($requester["room_id"])
            ) {
                throw new Exception(
                    "Your room assignment is unavailable."
                );
            }


            /* LOCK RECEIVER */

            $stmt = mysqli_prepare(
                $conn,
                "SELECT
                    id,
                    name,
                    room_id

                 FROM users

                 WHERE id = ?
                 AND role = 'student'

                 FOR UPDATE"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $receiverId
            );

            mysqli_stmt_execute($stmt);

            $result =
                mysqli_stmt_get_result($stmt);

            $receiver =
                mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);


            if (
                !$receiver ||
                empty($receiver["room_id"])
            ) {
                throw new Exception(
                    "Selected student does not have an assigned room."
                );
            }


            $requesterRoomId =
                intval(
                    $requester["room_id"]
                );

            $receiverRoomId =
                intval(
                    $receiver["room_id"]
                );


            if (
                $requesterRoomId ===
                $receiverRoomId
            ) {
                throw new Exception(
                    "You cannot send a swap request to a student from your own room."
                );
            }


            /* CHECK ACTIVE SWAPS */

            $stmt = mysqli_prepare(
                $conn,
                "SELECT id

                 FROM room_swap_requests

                 WHERE status IN (
                    'Pending Student Approval',
                    'Accepted by Student'
                 )

                 AND
                 (
                    requester_id IN (?, ?)
                    OR
                    receiver_id IN (?, ?)
                 )

                 LIMIT 1

                 FOR UPDATE"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "iiii",
                $userId,
                $receiverId,
                $userId,
                $receiverId
            );

            mysqli_stmt_execute($stmt);

            $activeResult =
                mysqli_stmt_get_result($stmt);

            $existingActive =
                mysqli_fetch_assoc($activeResult);

            mysqli_stmt_close($stmt);


            if ($existingActive) {
                throw new Exception(
                    "You or the selected student already has an active room swap request."
                );
            }


            /* COMPATIBILITY SNAPSHOT */

            $compatibilityScore = null;


            if ($myPreferences) {

                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT *

                     FROM roommate_preferences

                     WHERE user_id = ?

                     LIMIT 1"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $receiverId
                );

                mysqli_stmt_execute($stmt);

                $prefResult =
                    mysqli_stmt_get_result($stmt);

                $receiverPreferences =
                    mysqli_fetch_assoc($prefResult);

                mysqli_stmt_close($stmt);


                if ($receiverPreferences) {

                    $compatibilityScore =
                        calculateCompatibility(
                            $myPreferences,
                            $receiverPreferences
                        );
                }
            }


            /* INSERT */

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO room_swap_requests
                (
                    requester_id,
                    receiver_id,
                    requester_room_id,
                    receiver_room_id,
                    reason,
                    compatibility_score,
                    status
                )

                VALUES
                (?, ?, ?, ?, ?, ?, 'Pending Student Approval')"
            );


            mysqli_stmt_bind_param(
                $stmt,
                "iiiisi",
                $userId,
                $receiverId,
                $requesterRoomId,
                $receiverRoomId,
                $reason,
                $compatibilityScore
            );


            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception(
                    "Unable to send room swap request."
                );
            }

            mysqli_stmt_close($stmt);


            mysqli_commit($conn);


            $_SESSION["swap_message"] =
                "Room swap request sent to " .
                $receiver["name"] .
                ".";

            $_SESSION["swap_message_type"] =
                "success";


        } catch (Exception $e) {

            mysqli_rollback($conn);

            $_SESSION["swap_message"] =
                $e->getMessage();

            $_SESSION["swap_message_type"] =
                "error";
        }


        header("Location: room_swap.php");
        exit;
    }



    /* =====================================================
       ACCEPT REQUEST
       ===================================================== */

    if (isset($_POST["accept_swap"])) {

        $requestId =
            intval(
                $_POST["request_id"] ?? 0
            );

        $receiverRemark =
            trim(
                $_POST["receiver_remark"] ?? ""
            );


        mysqli_begin_transaction($conn);


        try {

            $stmt = mysqli_prepare(
                $conn,
                "SELECT *

                 FROM room_swap_requests

                 WHERE id = ?

                 FOR UPDATE"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $requestId
            );

            mysqli_stmt_execute($stmt);

            $result =
                mysqli_stmt_get_result($stmt);

            $swap =
                mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);


            if (!$swap) {
                throw new Exception(
                    "Swap request not found."
                );
            }


            if (
                intval($swap["receiver_id"]) !==
                $userId
            ) {
                throw new Exception(
                    "You cannot accept this request."
                );
            }


            if (
                $swap["status"] !==
                "Pending Student Approval"
            ) {
                throw new Exception(
                    "This swap request has already been processed."
                );
            }


            /* CURRENT ROOMS */

            $stmt = mysqli_prepare(
                $conn,
                "SELECT id, room_id

                 FROM users

                 WHERE id IN (?, ?)

                 FOR UPDATE"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $swap["requester_id"],
                $swap["receiver_id"]
            );

            mysqli_stmt_execute($stmt);

            $usersResult =
                mysqli_stmt_get_result($stmt);

            $rooms = [];


            while (
                $row =
                mysqli_fetch_assoc($usersResult)
            ) {
                $rooms[intval($row["id"])] =
                    intval($row["room_id"]);
            }

            mysqli_stmt_close($stmt);


            if (
                !isset(
                    $rooms[
                        intval(
                            $swap["requester_id"]
                        )
                    ]
                ) ||
                !isset(
                    $rooms[
                        intval(
                            $swap["receiver_id"]
                        )
                    ]
                )
            ) {
                throw new Exception(
                    "Student room information is unavailable."
                );
            }


            if (
                $rooms[
                    intval(
                        $swap["requester_id"]
                    )
                ] !==
                intval(
                    $swap["requester_room_id"]
                )
                ||
                $rooms[
                    intval(
                        $swap["receiver_id"]
                    )
                ] !==
                intval(
                    $swap["receiver_room_id"]
                )
            ) {
                throw new Exception(
                    "One of the room assignments has changed. This swap is no longer valid."
                );
            }


            $stmt = mysqli_prepare(
                $conn,
                "UPDATE room_swap_requests

                 SET
                    status = 'Accepted by Student',
                    receiver_remark = ?,
                    receiver_decision_at = NOW()

                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "si",
                $receiverRemark,
                $requestId
            );

            mysqli_stmt_execute($stmt);

            mysqli_stmt_close($stmt);


            mysqli_commit($conn);


            $_SESSION["swap_message"] =
                "Swap request accepted. It is now waiting for Warden approval.";

            $_SESSION["swap_message_type"] =
                "success";


        } catch (Exception $e) {

            mysqli_rollback($conn);

            $_SESSION["swap_message"] =
                $e->getMessage();

            $_SESSION["swap_message_type"] =
                "error";
        }


        header("Location: room_swap.php");
        exit;
    }



    /* =====================================================
       REJECT REQUEST
       ===================================================== */

    if (isset($_POST["reject_swap"])) {

        $requestId =
            intval(
                $_POST["request_id"] ?? 0
            );

        $receiverRemark =
            trim(
                $_POST["receiver_remark"] ?? ""
            );


        if ($receiverRemark === "") {

            $_SESSION["swap_message"] =
                "Please enter a reason before rejecting the request.";

            $_SESSION["swap_message_type"] =
                "error";

            header("Location: room_swap.php");
            exit;
        }


        $stmt = mysqli_prepare(
            $conn,
            "UPDATE room_swap_requests

             SET
                status = 'Rejected by Student',
                receiver_remark = ?,
                receiver_decision_at = NOW()

             WHERE id = ?
             AND receiver_id = ?
             AND status = 'Pending Student Approval'"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "sii",
            $receiverRemark,
            $requestId,
            $userId
        );

        mysqli_stmt_execute($stmt);


        if (
            mysqli_stmt_affected_rows($stmt) > 0
        ) {

            $_SESSION["swap_message"] =
                "Room swap request rejected.";

            $_SESSION["swap_message_type"] =
                "success";

        } else {

            $_SESSION["swap_message"] =
                "Unable to reject this request.";

            $_SESSION["swap_message_type"] =
                "error";
        }

        mysqli_stmt_close($stmt);


        header("Location: room_swap.php");
        exit;
    }



    /* =====================================================
       CANCEL REQUEST
       ===================================================== */

    if (isset($_POST["cancel_swap"])) {

        $requestId =
            intval(
                $_POST["request_id"] ?? 0
            );


        $stmt = mysqli_prepare(
            $conn,
            "UPDATE room_swap_requests

             SET status = 'Cancelled'

             WHERE id = ?
             AND requester_id = ?

             AND status IN (
                'Pending Student Approval',
                'Accepted by Student'
             )"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $requestId,
            $userId
        );

        mysqli_stmt_execute($stmt);


        if (
            mysqli_stmt_affected_rows($stmt) > 0
        ) {

            $_SESSION["swap_message"] =
                "Room swap request cancelled.";

            $_SESSION["swap_message_type"] =
                "success";

        } else {

            $_SESSION["swap_message"] =
                "This swap request cannot be cancelled.";

            $_SESSION["swap_message_type"] =
                "error";
        }

        mysqli_stmt_close($stmt);


        header("Location: room_swap.php");
        exit;
    }
}


/* =========================================================
   CHECK WHETHER CURRENT USER HAS ACTIVE SWAP
   ========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT id

     FROM room_swap_requests

     WHERE status IN (
        'Pending Student Approval',
        'Accepted by Student'
     )

     AND
     (
        requester_id = ?
        OR
        receiver_id = ?
     )

     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $userId,
    $userId
);

mysqli_stmt_execute($stmt);

$activeResult =
    mysqli_stmt_get_result($stmt);

$hasActiveSwap =
    mysqli_fetch_assoc($activeResult)
    ? true
    : false;

mysqli_stmt_close($stmt);


/* =========================================================
   AVAILABLE STUDENTS
   ========================================================= */

$candidates = [];


if ($currentRoomId > 0) {

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
            rp.sharing_preference,

            (
                SELECT COUNT(*)

                FROM room_swap_requests rs

                WHERE
                    rs.status IN (
                        'Pending Student Approval',
                        'Accepted by Student'
                    )

                AND
                (
                    rs.requester_id = u.id
                    OR
                    rs.receiver_id = u.id
                )

            ) AS active_swap_count

         FROM users u

         INNER JOIN rooms r
            ON u.room_id = r.id

         LEFT JOIN roommate_preferences rp
            ON u.id = rp.user_id

         WHERE
            u.role = 'student'

         AND u.id != ?

         AND u.room_id != ?

         ORDER BY u.name ASC"
    );


    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $userId,
        $currentRoomId
    );

    mysqli_stmt_execute($stmt);

    $result =
        mysqli_stmt_get_result($stmt);


    while (
        $student =
        mysqli_fetch_assoc($result)
    ) {

        $compatibility = null;


        if (
            $myPreferences &&
            !empty($student["sleep_time"])
        ) {

            $compatibility =
                calculateCompatibility(
                    $myPreferences,
                    $student
                );
        }


        $student["compatibility"] =
            $compatibility;


        $candidates[] =
            $student;
    }


    mysqli_stmt_close($stmt);
}


/* =========================================================
   INCOMING REQUESTS
   ========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        rs.*,

        u.name AS requester_name,
        u.email AS requester_email,

        r1.room_number AS requester_room,
        r2.room_number AS receiver_room

     FROM room_swap_requests rs

     INNER JOIN users u
        ON rs.requester_id = u.id

     LEFT JOIN rooms r1
        ON rs.requester_room_id = r1.id

     LEFT JOIN rooms r2
        ON rs.receiver_room_id = r2.id

     WHERE rs.receiver_id = ?

     AND rs.status = 'Pending Student Approval'

     ORDER BY rs.id DESC"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $userId
);

mysqli_stmt_execute($stmt);

$incomingRequests =
    mysqli_stmt_get_result($stmt);


/* =========================================================
   OUTGOING ACTIVE REQUEST
   ========================================================= */

$stmtOutgoing = mysqli_prepare(
    $conn,
    "SELECT
        rs.*,

        u.name AS receiver_name,

        r1.room_number AS requester_room,
        r2.room_number AS receiver_room

     FROM room_swap_requests rs

     INNER JOIN users u
        ON rs.receiver_id = u.id

     LEFT JOIN rooms r1
        ON rs.requester_room_id = r1.id

     LEFT JOIN rooms r2
        ON rs.receiver_room_id = r2.id

     WHERE rs.requester_id = ?

     AND rs.status IN (
        'Pending Student Approval',
        'Accepted by Student'
     )

     ORDER BY rs.id DESC

     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmtOutgoing,
    "i",
    $userId
);

mysqli_stmt_execute($stmtOutgoing);

$outgoingResult =
    mysqli_stmt_get_result($stmtOutgoing);

$outgoingRequest =
    mysqli_fetch_assoc($outgoingResult);

mysqli_stmt_close($stmtOutgoing);


/* =========================================================
   HISTORY
   ========================================================= */

$stmtHistory = mysqli_prepare(
    $conn,
    "SELECT

        rs.*,

        requester.name
            AS requester_name,

        receiver.name
            AS receiver_name,

        r1.room_number
            AS requester_room,

        r2.room_number
            AS receiver_room

     FROM room_swap_requests rs

     INNER JOIN users requester
        ON rs.requester_id =
           requester.id

     INNER JOIN users receiver
        ON rs.receiver_id =
           receiver.id

     LEFT JOIN rooms r1
        ON rs.requester_room_id =
           r1.id

     LEFT JOIN rooms r2
        ON rs.receiver_room_id =
           r2.id

     WHERE
        rs.requester_id = ?
        OR
        rs.receiver_id = ?

     ORDER BY rs.id DESC"
);

mysqli_stmt_bind_param(
    $stmtHistory,
    "ii",
    $userId,
    $userId
);

mysqli_stmt_execute($stmtHistory);

$history =
    mysqli_stmt_get_result($stmtHistory);


/* =========================================================
   SUMMARY
   ========================================================= */

$stmtSummary = mysqli_prepare(
    $conn,
    "SELECT

        COUNT(*) AS total,

        COALESCE(
            SUM(status = 'Approved'),
            0
        ) AS approved,

        COALESCE(
            SUM(
                status IN (
                    'Pending Student Approval',
                    'Accepted by Student'
                )
            ),
            0
        ) AS active

     FROM room_swap_requests

     WHERE
        requester_id = ?
        OR receiver_id = ?"
);

mysqli_stmt_bind_param(
    $stmtSummary,
    "ii",
    $userId,
    $userId
);

mysqli_stmt_execute($stmtSummary);

$result =
    mysqli_stmt_get_result($stmtSummary);

$summary =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($stmtSummary);


$totalSwaps =
    intval($summary["total"] ?? 0);

$approvedSwaps =
    intval($summary["approved"] ?? 0);

$activeSwaps =
    intval($summary["active"] ?? 0);

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
Smart Room Swap - UniStay
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
    --blue:#4387A5;

    --bg:#EFF4F7;
    --border:#DFE7EC;

    --text:#1E2639;
    --muted:#718092;

    --teal-soft:#DDF2F4;
    --green-soft:#E3F5EC;
    --red-soft:#FBE8EA;
    --mustard-soft:#FFF2D3;
    --blue-soft:#E5F0F5;

    --shadow:
        0 8px 24px
        rgba(31,39,71,.06);
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


.dashboard {

    display:flex;

    min-height:100vh;
}


.main {

    flex:1;

    min-width:0;

    padding:
        28px 32px 50px;
}


/* =========================================================
   SIDEBAR SAFETY
   ========================================================= */

.sidebar {

    width:230px !important;
    min-width:230px !important;

    background:white;

    border-right:
        1px solid var(--border);
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


.title-box {

    display:flex;

    align-items:center;

    gap:14px;
}


.title-icon {

    width:50px;
    height:50px;

    display:flex;

    align-items:center;

    justify-content:center;

    flex-shrink:0;

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
        8px 14px 8px 8px;

    background:white;

    border:
        1px solid var(--border);

    border-radius:30px;
}


.avatar {

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

    color:
        var(--muted);

    font-size:10px;
}


/* =========================================================
   HERO
   ========================================================= */

.hero {

    display:grid;

    grid-template-columns:
        minmax(0,1fr)
        320px;

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
}


.hero-label {

    color:#F6D77C;

    font-size:11px;

    font-weight:800;

    letter-spacing:1px;

    text-transform:uppercase;
}


.hero h2 {

    margin-top:8px;

    font-size:34px;
}


.hero p {

    max-width:650px;

    margin-top:10px;

    color:
        rgba(255,255,255,.83);

    font-size:14px;

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

    font-size:11px;

    font-weight:800;
}


.hero-room {

    padding:20px;

    border-radius:15px;

    background:
        rgba(255,255,255,.11);

    border:
        1px solid
        rgba(255,255,255,.14);
}


.hero-room small {

    color:
        rgba(255,255,255,.7);

    font-size:10px;

    text-transform:uppercase;
}


.hero-room h3 {

    margin-top:6px;

    font-size:28px;
}


/* =========================================================
   SUMMARY
   ========================================================= */

.summary {

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:14px;

    margin-bottom:20px;
}


.summary-card {

    display:flex;

    align-items:center;

    gap:13px;

    min-height:105px;

    padding:17px;

    border-radius:14px;

    background:white;

    border:
        1px solid var(--border);

    box-shadow:
        var(--shadow);
}


.summary-icon {

    width:48px;
    height:48px;

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


.summary-card p {

    color:
        var(--muted);

    font-size:10px;

    text-transform:uppercase;
}


.summary-card h2 {

    margin-top:4px;

    font-size:26px;
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
   PANELS
   ========================================================= */

.panel {

    margin-bottom:20px;

    overflow:hidden;

    border-radius:16px;

    background:white;

    border:
        1px solid var(--border);

    box-shadow:
        var(--shadow);
}


.panel-head {

    display:flex;

    align-items:center;

    gap:11px;

    padding:20px;

    border-bottom:
        1px solid #EDF1F3;
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
}


.panel-head h2 {

    font-size:19px;
}


.panel-head p {

    margin-top:3px;

    color:
        var(--muted);

    font-size:11px;
}


/* =========================================================
   SEND REQUEST
   ========================================================= */

.send-layout {

    display:grid;

    grid-template-columns:
        minmax(0,1.4fr)
        minmax(300px,.6fr);

    gap:18px;

    padding:18px;
}


.students-grid {

    display:grid;

    grid-template-columns:
        repeat(2,minmax(0,1fr));

    gap:12px;
}


.student-card {

    position:relative;

    padding:15px;

    border-radius:13px;

    background:#FAFCFD;

    border:
        1px solid #E0E8EC;

    cursor:pointer;

    transition:.2s;
}


.student-card:hover {

    border-color:
        var(--teal);

    transform:
        translateY(-2px);
}


.student-card.selected {

    border:
        2px solid var(--teal);

    background:#F3FAFB;
}


.student-card.disabled {

    opacity:.55;

    cursor:not-allowed;

    transform:none;
}


.student-top {

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:10px;
}


.student-person {

    display:flex;

    align-items:center;

    gap:9px;
}


.student-avatar {

    width:40px;
    height:40px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:10px;

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


.student-name {

    font-size:13px;

    font-weight:800;
}


.room-text {

    margin-top:2px;

    color:
        var(--muted);

    font-size:10px;
}


.score {

    min-width:52px;

    padding:
        8px 7px;

    text-align:center;

    border-radius:20px;

    color:
        var(--teal);

    background:
        var(--teal-soft);

    font-size:11px;

    font-weight:800;
}


.score.none {

    color:
        var(--muted);

    background:#EDF2F4;
}


.active-badge {

    margin-top:10px;

    padding:
        6px 8px;

    border-radius:7px;

    color:#946509;

    background:
        var(--mustard-soft);

    font-size:9px;

    font-weight:700;
}


/* FORM */

.swap-form {

    padding:17px;

    border-radius:13px;

    background:#F8FAFB;

    border:
        1px solid #E2E9ED;
}


.swap-form h3 {

    font-size:16px;
}


.swap-form p {

    margin-top:5px;

    color:
        var(--muted);

    font-size:11px;

    line-height:1.5;
}


.selected-name {

    margin-top:14px;

    padding:11px;

    border-radius:9px;

    color:
        var(--teal);

    background:
        var(--teal-soft);

    font-size:12px;

    font-weight:700;
}


textarea {

    width:100%;

    min-height:115px;

    margin-top:12px;

    padding:11px;

    resize:vertical;

    outline:none;

    border:
        1px solid #D5E0E5;

    border-radius:9px;

    font-size:12px;
}


textarea:focus {

    border-color:
        var(--teal);
}


.primary-btn {

    width:100%;

    margin-top:10px;

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

    font-size:12px;

    font-weight:800;
}


/* =========================================================
   REQUEST CARDS
   ========================================================= */

.requests-wrap {

    padding:18px;
}


.request-card {

    margin-bottom:13px;

    padding:16px;

    border-radius:13px;

    background:#FAFCFD;

    border:
        1px solid #E1E8EC;
}


.request-card:last-child {

    margin-bottom:0;
}


.request-top {

    display:flex;

    justify-content:space-between;

    gap:15px;
}


.request-name {

    font-size:14px;

    font-weight:800;
}


.request-meta {

    margin-top:4px;

    color:
        var(--muted);

    font-size:10px;
}


.status {

    display:inline-flex;

    align-items:center;

    padding:
        6px 9px;

    border-radius:20px;

    font-size:9px;

    font-weight:800;
}


.status.pending {

    color:#966604;

    background:
        var(--mustard-soft);
}


.status.accepted {

    color:#28728D;

    background:
        var(--blue-soft);
}


.status.approved {

    color:#267A5D;

    background:
        var(--green-soft);
}


.status.rejected,
.status.cancelled {

    color:#A64048;

    background:
        var(--red-soft);
}


.room-route {

    display:flex;

    align-items:center;

    gap:10px;

    margin-top:12px;

    padding:11px;

    border-radius:9px;

    background:white;

    border:
        1px solid #E5EBEE;

    font-size:12px;

    font-weight:700;
}


.room-route i {

    color:
        var(--teal);
}


.reason {

    margin-top:10px;

    color:#596A7C;

    font-size:11px;

    line-height:1.5;
}


.compatibility {

    margin-top:8px;

    color:
        var(--teal);

    font-size:10px;

    font-weight:800;
}


.actions {

    display:grid;

    grid-template-columns:
        1fr auto auto;

    gap:8px;

    margin-top:13px;

    padding-top:13px;

    border-top:
        1px solid #E5EBEE;
}


.remark {

    padding:9px 10px;

    outline:none;

    border:
        1px solid #D5E0E5;

    border-radius:8px;

    font-size:11px;
}


.accept-btn,
.reject-btn,
.cancel-btn {

    padding:
        9px 12px;

    border:none;

    border-radius:8px;

    cursor:pointer;

    color:white;

    font-size:10px;

    font-weight:800;
}


.accept-btn {

    background:
        var(--green);
}


.reject-btn {

    background:
        var(--red);
}


.cancel-btn {

    background:
        var(--navy);
}


/* =========================================================
   HISTORY
   ========================================================= */

.table-wrap {

    overflow-x:auto;
}


table {

    width:100%;

    border-collapse:collapse;
}


th {

    padding:
        12px 14px;

    text-align:left;

    background:#F6F9FA;

    color:
        var(--muted);

    border-bottom:
        1px solid #E6ECEF;

    font-size:10px;

    text-transform:uppercase;
}


td {

    padding:
        13px 14px;

    color:#536577;

    border-bottom:
        1px solid #EDF1F3;

    font-size:11px;
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

    color:
        var(--teal);

    font-size:31px;

    margin-bottom:10px;
}


.empty h3 {

    color:
        var(--text);

    font-size:16px;
}


.empty p {

    margin-top:5px;

    font-size:11px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media(max-width:1100px) {

    .hero {

        grid-template-columns:1fr;
    }


    .send-layout {

        grid-template-columns:1fr;
    }
}


@media(max-width:850px) {

    .summary {

        grid-template-columns:
            repeat(2,1fr);
    }


    .students-grid {

        grid-template-columns:1fr;
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


    .summary {

        grid-template-columns:1fr;
    }


    .hero {

        padding:23px;
    }


    .hero h2 {

        font-size:27px;
    }


    .actions {

        grid-template-columns:1fr;
    }
}

</style>

</head>


<body>

<div class="dashboard">

<?php include "sidebar.php"; ?>


<div class="main">


<!-- TOP -->

<div class="topbar">

<div class="title-box">

<div class="title-icon">

<i class="fa-solid fa-people-arrows-left-right"></i>

</div>

<div>

<h1>
Smart Room Swap
</h1>

<p>
Exchange rooms with another student safely.
</p>

</div>

</div>


<div class="profile">

<div class="avatar">

<?php echo getInitial($userName); ?>

</div>

<div>

<strong>
<?php echo htmlspecialchars($userName); ?>
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
Smart Room Management
</div>

<h2>
Swap rooms without waiting for vacancy.
</h2>

<p>

Choose a student from another room, compare
roommate compatibility and send a mutual swap
request. The other student must accept before
the Warden can approve the exchange.

</p>

<div class="hero-chip">

<i class="fa-solid fa-user-check"></i>

Student Consent + Warden Approval

</div>

</div>


<div class="hero-room">

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

</div>

</div>



<?php if ($message !== "") { ?>

<div
    id="flashMessage"
    class="message <?php echo htmlspecialchars($messageType); ?>"
>

<?php echo htmlspecialchars($message); ?>

</div>

<?php } ?>



<!-- SUMMARY -->

<div class="summary">

<div class="summary-card">

<div class="summary-icon">
<i class="fa-solid fa-users"></i>
</div>

<div>

<p>
Available Students
</p>

<h2>
<?php echo count($candidates); ?>
</h2>

</div>

</div>


<div class="summary-card">

<div class="summary-icon">
<i class="fa-solid fa-clock"></i>
</div>

<div>

<p>
Active Swaps
</p>

<h2>
<?php echo $activeSwaps; ?>
</h2>

</div>

</div>


<div class="summary-card">

<div class="summary-icon">
<i class="fa-solid fa-circle-check"></i>
</div>

<div>

<p>
Completed
</p>

<h2>
<?php echo $approvedSwaps; ?>
</h2>

</div>

</div>


<div class="summary-card">

<div class="summary-icon">
<i class="fa-solid fa-clock-rotate-left"></i>
</div>

<div>

<p>
Total Requests
</p>

<h2>
<?php echo $totalSwaps; ?>
</h2>

</div>

</div>

</div>



<!-- =========================================================
     INCOMING
     ========================================================= -->

<?php if (
    $incomingRequests &&
    mysqli_num_rows($incomingRequests) > 0
) { ?>

<div class="panel">

<div class="panel-head">

<div class="panel-icon">
<i class="fa-solid fa-inbox"></i>
</div>

<div>

<h2>
Incoming Swap Request
</h2>

<p>
Another student wants to exchange rooms with you.
</p>

</div>

</div>


<div class="requests-wrap">

<?php while (
    $request =
    mysqli_fetch_assoc($incomingRequests)
) { ?>

<div class="request-card">

<div class="request-top">

<div>

<div class="request-name">

<?php
echo htmlspecialchars(
    $request["requester_name"]
);
?>

</div>

<div class="request-meta">

Request received

<?php
echo date(
    "d M Y, h:i A",
    strtotime(
        $request["created_at"]
    )
);
?>

</div>

</div>


<span class="status pending">

Waiting for You

</span>

</div>


<div class="room-route">

<?php
echo htmlspecialchars(
    $request["receiver_room"]
);
?>

<i class="fa-solid fa-arrow-right-arrow-left"></i>

<?php
echo htmlspecialchars(
    $request["requester_room"]
);
?>

</div>


<?php if (
    $request["compatibility_score"] !== null
) { ?>

<div class="compatibility">

<i class="fa-solid fa-percent"></i>

Compatibility:
<?php
echo intval(
    $request["compatibility_score"]
);
?>%

</div>

<?php } ?>


<div class="reason">

<strong>
Reason:
</strong>

<?php
echo htmlspecialchars(
    $request["reason"]
);
?>

</div>


<div class="actions">

<input
    type="text"
    id="receiver_remark_<?php echo intval($request["id"]); ?>"
    class="remark"
    placeholder="Optional remark for acceptance / required for rejection"
>


<form
    method="POST"
    onsubmit="
        return prepareReceiverAction(
            this,
            <?php echo intval($request["id"]); ?>,
            'accept'
        );
    "
>

<input
    type="hidden"
    name="request_id"
    value="<?php echo intval($request["id"]); ?>"
>

<input
    type="hidden"
    name="receiver_remark"
    class="hidden-remark"
>

<button
    type="submit"
    name="accept_swap"
    class="accept-btn"
>

<i class="fa-solid fa-check"></i>
Accept

</button>

</form>


<form
    method="POST"
    onsubmit="
        return prepareReceiverAction(
            this,
            <?php echo intval($request["id"]); ?>,
            'reject'
        );
    "
>

<input
    type="hidden"
    name="request_id"
    value="<?php echo intval($request["id"]); ?>"
>

<input
    type="hidden"
    name="receiver_remark"
    class="hidden-remark"
>

<button
    type="submit"
    name="reject_swap"
    class="reject-btn"
>

<i class="fa-solid fa-xmark"></i>
Reject

</button>

</form>

</div>

</div>

<?php } ?>

</div>

</div>

<?php } ?>



<!-- =========================================================
     OUTGOING
     ========================================================= -->

<?php if ($outgoingRequest) { ?>

<div class="panel">

<div class="panel-head">

<div class="panel-icon">

<i class="fa-solid fa-paper-plane"></i>

</div>

<div>

<h2>
Your Active Request
</h2>

<p>
Track your current room swap request.
</p>

</div>

</div>


<div class="requests-wrap">

<div class="request-card">

<div class="request-top">

<div>

<div class="request-name">

Swap with

<?php
echo htmlspecialchars(
    $outgoingRequest["receiver_name"]
);
?>

</div>

<div class="request-meta">

Sent on

<?php
echo date(
    "d M Y, h:i A",
    strtotime(
        $outgoingRequest["created_at"]
    )
);
?>

</div>

</div>


<?php if (
    $outgoingRequest["status"] ===
    "Accepted by Student"
) { ?>

<span class="status accepted">

Waiting for Warden

</span>

<?php } else { ?>

<span class="status pending">

Waiting for Student

</span>

<?php } ?>

</div>


<div class="room-route">

<?php
echo htmlspecialchars(
    $outgoingRequest["requester_room"]
);
?>

<i class="fa-solid fa-arrow-right-arrow-left"></i>

<?php
echo htmlspecialchars(
    $outgoingRequest["receiver_room"]
);
?>

</div>


<?php if (
    $outgoingRequest["compatibility_score"] !== null
) { ?>

<div class="compatibility">

Compatibility:
<?php
echo intval(
    $outgoingRequest["compatibility_score"]
);
?>%

</div>

<?php } ?>


<div class="reason">

<strong>
Reason:
</strong>

<?php
echo htmlspecialchars(
    $outgoingRequest["reason"]
);
?>

</div>


<form
    method="POST"
    onsubmit="
        return confirm(
            'Cancel this room swap request?'
        );
    "
>

<input
    type="hidden"
    name="request_id"
    value="<?php echo intval($outgoingRequest["id"]); ?>"
>

<button
    type="submit"
    name="cancel_swap"
    class="cancel-btn"
    style="margin-top:12px;"
>

Cancel Request

</button>

</form>

</div>

</div>

</div>

<?php } ?>



<!-- =========================================================
     NEW REQUEST
     ========================================================= -->

<div class="panel">

<div class="panel-head">

<div class="panel-icon">

<i class="fa-solid fa-people-arrows-left-right"></i>

</div>

<div>

<h2>
Find a Room Swap
</h2>

<p>
Choose a student from another room.
</p>

</div>

</div>


<?php if ($currentRoomId <= 0) { ?>

<div class="empty">

<i class="fa-solid fa-bed"></i>

<h3>
No room assigned
</h3>

<p>
You need an assigned hostel room before using Smart Room Swap.
</p>

</div>


<?php } elseif ($hasActiveSwap) { ?>

<div class="empty">

<i class="fa-solid fa-hourglass-half"></i>

<h3>
Active request already exists
</h3>

<p>
Complete or cancel your current swap before starting another one.
</p>

</div>


<?php } elseif (count($candidates) === 0) { ?>

<div class="empty">

<i class="fa-solid fa-user-group"></i>

<h3>
No students available
</h3>

<p>
There are currently no students available for a room swap.
</p>

</div>


<?php } else { ?>


<div class="send-layout">


<div class="students-grid">

<?php foreach ($candidates as $student) { ?>

<?php

$busy =
    intval(
        $student["active_swap_count"]
    ) > 0;

?>

<div
    class="student-card <?php echo $busy ? "disabled" : ""; ?>"

    <?php if (!$busy) { ?>

    onclick="
        selectStudent(
            <?php echo intval($student["id"]); ?>,
            '<?php echo htmlspecialchars(
                addslashes($student["name"])
            ); ?>',
            '<?php echo htmlspecialchars(
                addslashes($student["room_number"])
            ); ?>',
            this
        );
    "

    <?php } ?>
>


<div class="student-top">

<div class="student-person">

<div class="student-avatar">

<?php
echo getInitial(
    $student["name"]
);
?>

</div>

<div>

<div class="student-name">

<?php
echo htmlspecialchars(
    $student["name"]
);
?>

</div>

<div class="room-text">

Room

<?php
echo htmlspecialchars(
    $student["room_number"]
);
?>

</div>

</div>

</div>


<?php if (
    $student["compatibility"] !== null
) { ?>

<div class="score">

<?php
echo intval(
    $student["compatibility"]
);
?>%

</div>

<?php } else { ?>

<div class="score none">

N/A

</div>

<?php } ?>

</div>


<?php if ($busy) { ?>

<div class="active-badge">

<i class="fa-solid fa-clock"></i>

Already involved in another active swap

</div>

<?php } ?>

</div>

<?php } ?>

</div>



<div class="swap-form">

<h3>
Send Swap Request
</h3>

<p>

Select a student. Your request will first go to
that student and then to the Warden.

</p>


<div
    id="selectedStudent"
    class="selected-name"
>

No student selected

</div>


<form
    method="POST"
    onsubmit="
        return confirm(
            'Send this room swap request?'
        );
    "
>

<input
    type="hidden"
    name="receiver_id"
    id="receiverId"
>


<textarea
    name="reason"
    maxlength="500"
    placeholder="Why do you want to swap rooms?"
    required
></textarea>


<button
    type="submit"
    name="send_swap"
    class="primary-btn"
>

<i class="fa-solid fa-paper-plane"></i>

Send Swap Request

</button>

</form>

</div>

</div>

<?php } ?>

</div>



<!-- =========================================================
     HISTORY
     ========================================================= -->

<div class="panel">

<div class="panel-head">

<div class="panel-icon">

<i class="fa-solid fa-clock-rotate-left"></i>

</div>

<div>

<h2>
Swap History
</h2>

<p>
Previous and current room swap requests.
</p>

</div>

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
Students
</th>

<th>
Room Swap
</th>

<th>
Compatibility
</th>

<th>
Status
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

<?php

$statusClass = "pending";

if (
    $row["status"] === "Approved"
) {
    $statusClass = "approved";

} elseif (
    $row["status"] === "Accepted by Student"
) {
    $statusClass = "accepted";

} elseif (
    in_array(
        $row["status"],
        [
            "Rejected by Student",
            "Rejected by Warden"
        ],
        true
    )
) {
    $statusClass = "rejected";

} elseif (
    $row["status"] === "Cancelled"
) {
    $statusClass = "cancelled";
}

?>

<tr>

<td>

<strong>

<?php
echo htmlspecialchars(
    $row["requester_name"]
);
?>

</strong>

↔

<strong>

<?php
echo htmlspecialchars(
    $row["receiver_name"]
);
?>

</strong>

</td>


<td>

<?php
echo htmlspecialchars(
    $row["requester_room"]
);
?>

↔

<?php
echo htmlspecialchars(
    $row["receiver_room"]
);
?>

</td>


<td>

<?php

echo $row["compatibility_score"] !== null
    ? intval(
        $row["compatibility_score"]
    ) . "%"
    : "Not available";

?>

</td>


<td>

<span
    class="status <?php echo $statusClass; ?>"
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

<i class="fa-solid fa-people-arrows-left-right"></i>

<h3>
No swap history
</h3>

<p>
Your room swap requests will appear here.
</p>

</div>

<?php } ?>

</div>


</div>
</div>



<script>

/* =========================================================
   SELECT STUDENT
   ========================================================= */

function selectStudent(
    id,
    name,
    room,
    card
) {

    document
        .querySelectorAll(
            ".student-card"
        )
        .forEach(
            function(item) {

                item.classList.remove(
                    "selected"
                );
            }
        );


    card.classList.add(
        "selected"
    );


    document.getElementById(
        "receiverId"
    ).value = id;


    document.getElementById(
        "selectedStudent"
    ).innerHTML =
        "<i class='fa-solid fa-user-check'></i> "
        + name
        + " — Room "
        + room;
}


/* =========================================================
   ACCEPT / REJECT
   ========================================================= */

function prepareReceiverAction(
    form,
    requestId,
    action
) {

    const field =
        document.getElementById(
            "receiver_remark_" +
            requestId
        );


    const remark =
        field
        ? field.value.trim()
        : "";


    form.querySelector(
        ".hidden-remark"
    ).value = remark;


    if (
        action === "reject" &&
        remark === ""
    ) {

        alert(
            "Please enter a reason before rejecting the request."
        );

        field.focus();

        return false;
    }


    if (action === "accept") {

        return confirm(
            "Accept this room swap? The request will then go to the Warden for final approval."
        );
    }


    return confirm(
        "Reject this room swap request?"
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
                "opacity .4s ease";

        },
        3500
    );
}

</script>

</body>

</html>
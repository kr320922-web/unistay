
<?php
session_start();
require_once "../db.php";
date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION["user_id"]) ||
    ($_SESSION["user_role"] ?? "") !== "student") {
    header("Location: ../index.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

function wcEsc($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function wcLength($value) {
    return function_exists("mb_strlen")
        ? mb_strlen($value, "UTF-8")
        : strlen($value);
}

if (empty($_SESSION["wc_csrf"])) {
    $_SESSION["wc_csrf"] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION["wc_csrf"];
$error = "";
$success = $_SESSION["wc_success"] ?? "";
unset($_SESSION["wc_success"]);
$supportNotice = $_SESSION["wc_support_notice"] ?? "";
$supportError = $_SESSION["wc_support_error"] ?? "";
unset($_SESSION["wc_support_notice"], $_SESSION["wc_support_error"]);

$categories = [
    "Rude Behaviour" => [
        "fa-face-frown",
        "Disrespectful or inappropriate behaviour.",
        "Complaint Regarding Warden's Rude Behaviour"
    ],
    "Harassment" => [
        "fa-shield-heart",
        "Bullying, threats or harassment.",
        "Complaint Regarding Harassment by Warden"
    ],
    "Unfair Treatment" => [
        "fa-scale-balanced",
        "Bias or unequal treatment.",
        "Complaint Regarding Unfair Treatment"
    ],
    "Misuse of Power" => [
        "fa-hand",
        "Abuse of authority or position.",
        "Complaint Regarding Misuse of Authority"
    ],
    "Privacy Concern" => [
        "fa-lock",
        "Violation of personal boundaries.",
        "Complaint Regarding Privacy Violation"
    ],
    "Other" => [
        "fa-ellipsis",
        "A concern not listed above.",
        "Complaint Regarding Warden Conduct"
    ]
];

$old = [
    "category" => "",
    "title" => "",
    "description" => "",
    "incident_date" => "",
    "incident_location" => "",
    "priority" => "Normal",
    "visibility" => "private"
];

/* VERIFY STUDENT */

$stmt = mysqli_prepare(
    $conn,
    "SELECT hostel FROM users
     WHERE id = ? AND role = 'student'
     LIMIT 1"
);

if (!$stmt) {
    http_response_code(500);
    exit("Unable to verify student.");
}

mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

$hostel = $student["hostel"] ?? "";

if (!in_array($hostel, ["boys", "girls"], true)) {
    http_response_code(403);
    exit("Your hostel is not assigned. Please contact Admin.");
}

/* COMMUNITY SUPPORT: TOGGLE ONLY APPROVED SUMMARIES IN SAME HOSTEL */

if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["form_action"] ?? "") === "toggle_community_support") {

    $targetId = filter_var(
        $_POST["community_complaint_id"] ?? null,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    if (!hash_equals($csrf, (string)($_POST["csrf_token"] ?? ""))) {
        $_SESSION["wc_support_error"] = "Security check failed. Please refresh and try again.";
    } elseif (!$targetId) {
        $_SESSION["wc_support_error"] = "Invalid community complaint.";
    } else {
        $supportTransactionStarted = false;
        try {
            mysqli_begin_transaction($conn);
            $supportTransactionStarted = true;

            // Lock the parent row to serialize toggles for this complaint.
            $verifyStmt = mysqli_prepare(
                $conn,
                "SELECT id FROM warden_complaints
                 WHERE id = ? AND hostel = ?
                   AND visibility = 'anonymous'
                   AND public_status = 'approved'
                   AND public_summary IS NOT NULL
                   AND TRIM(public_summary) <> ''
                 LIMIT 1 FOR UPDATE"
            );
            if (!$verifyStmt) {
                throw new RuntimeException("Cannot prepare community complaint verification.");
            }
            mysqli_stmt_bind_param($verifyStmt, "is", $targetId, $hostel);
            if (!mysqli_stmt_execute($verifyStmt)) {
                throw new RuntimeException("Cannot verify community complaint.");
            }
            $verifyResult = mysqli_stmt_get_result($verifyStmt);
            $isEligible = (bool)mysqli_fetch_assoc($verifyResult);
            mysqli_stmt_close($verifyStmt);

            if (!$isEligible) {
                throw new RuntimeException("This summary is not available for support.");
            }

            $checkSupport = mysqli_prepare(
                $conn,
                "SELECT COUNT(*) AS total FROM warden_complaint_supports
                 WHERE complaint_id = ? AND student_id = ?"
            );
            if (!$checkSupport) {
                throw new RuntimeException("Cannot prepare support lookup.");
            }
            mysqli_stmt_bind_param($checkSupport, "ii", $targetId, $userId);
            if (!mysqli_stmt_execute($checkSupport)) {
                throw new RuntimeException("Cannot check existing support.");
            }
            $checkResult = mysqli_stmt_get_result($checkSupport);
            $alreadySupported = ((int)(mysqli_fetch_assoc($checkResult)["total"] ?? 0)) > 0;
            mysqli_stmt_close($checkSupport);

            if ($alreadySupported) {
                $toggleStmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM warden_complaint_supports
                     WHERE complaint_id = ? AND student_id = ?"
                );
                $message = "Your support has been removed.";
            } else {
                $toggleStmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO warden_complaint_supports (complaint_id, student_id)
                     VALUES (?, ?)"
                );
                $message = "You supported this community concern.";
            }
            if (!$toggleStmt) {
                throw new RuntimeException("Cannot prepare support update.");
            }
            mysqli_stmt_bind_param($toggleStmt, "ii", $targetId, $userId);
            if (!mysqli_stmt_execute($toggleStmt)) {
                throw new RuntimeException("Cannot update support.");
            }
            mysqli_stmt_close($toggleStmt);
            mysqli_commit($conn);
            $supportTransactionStarted = false;
            $_SESSION["wc_support_notice"] = $message;
        } catch (Throwable $e) {
            if ($supportTransactionStarted) {
                mysqli_rollback($conn);
            }
            error_log("UniStay community support: " . $e->getMessage());
            $_SESSION["wc_support_error"] =
                "Unable to update support. Please try again or check the support table.";
        }
    }

    header("Location: warden_complaint.php#community-complaints", true, 303);
    exit;
}

/* SUBMIT COMPLAINT */

if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["form_action"] ?? "") !== "toggle_community_support") {

    foreach ($old as $key => $default) {
        $old[$key] = trim((string)($_POST[$key] ?? $default));
    }

    $category = $old["category"];
    $title = $old["title"];
    $description = $old["description"];
    $incidentDate = $old["incident_date"];
    $incidentLocation = $old["incident_location"];
    $priority = $old["priority"];
    $visibility = $old["visibility"];

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($csrf, (string)$_POST["csrf_token"])
    ) {
        $error = "Security verification failed. Please refresh.";

    } elseif (!array_key_exists($category, $categories)) {
        $error = "Please select a category.";

    } elseif (wcLength($title) < 5 || wcLength($title) > 150) {
        $error = "Title must contain 5 to 150 characters.";

    } elseif (
        wcLength($description) < 20 ||
        wcLength($description) > 5000
    ) {
        $error = "Description must contain 20 to 5000 characters.";

    } elseif (wcLength($incidentLocation) > 150) {
        $error = "Incident location is too long.";

    } elseif (!in_array($priority, ["Normal", "High", "Urgent"], true)) {
        $error = "Invalid priority.";

    } elseif (!in_array($visibility, ["private", "anonymous"], true)) {
        $error = "Invalid privacy selection.";
    }

    $dateForDb = null;

    if ($error === "" && $incidentDate !== "") {
        $dateObj = DateTime::createFromFormat("!Y-m-d", $incidentDate);

        if (
            !$dateObj ||
            $dateObj->format("Y-m-d") !== $incidentDate ||
            $incidentDate > date("Y-m-d")
        ) {
            $error = "Please enter a valid incident date.";
        } else {
            $dateForDb = $incidentDate;
        }
    }

    /* EVIDENCE VALIDATION */

    $evidence = null;

    if (
        $error === "" &&
        isset($_FILES["evidence"]) &&
        $_FILES["evidence"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        $file = $_FILES["evidence"];

        if ($file["error"] !== UPLOAD_ERR_OK) {
            $error = "Evidence upload failed.";

        } elseif (
            $file["size"] <= 0 ||
            $file["size"] > 5 * 1024 * 1024
        ) {
            $error = "Evidence must be 5 MB or smaller.";

        } elseif (!is_uploaded_file($file["tmp_name"])) {
            $error = "Invalid uploaded file.";

        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))
                ->file($file["tmp_name"]);

            $allowed = [
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp",
                "application/pdf" => "pdf"
            ];

            if (!isset($allowed[$mime])) {
                $error = "Only JPG, PNG, WEBP and PDF are allowed.";
            } else {
                $evidence = [
                    "tmp" => $file["tmp_name"],
                    "original" => substr(basename($file["name"]), 0, 255),
                    "mime" => $mime,
                    "extension" => $allowed[$mime],
                    "size" => (int)$file["size"]
                ];
            }
        }
    }

    /* DATABASE TRANSACTION */

    if ($error === "") {

        $publicStatus = $visibility === "anonymous"
            ? "pending"
            : "not_requested";

        $storedPath = null;
        $transactionStarted = false;

        try {
            mysqli_begin_transaction($conn);
            $transactionStarted = true;

            $insert = mysqli_prepare(
                $conn,
                "INSERT INTO warden_complaints
                (
                    student_id, hostel, category, title,
                    description, incident_date, incident_location,
                    priority, status, visibility, public_status
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, 'Submitted', ?, ?)"
            );

            if (!$insert) {
                throw new Exception("Cannot prepare complaint insert.");
            }

            mysqli_stmt_bind_param(
                $insert,
                "isssssssss",
                $userId,
                $hostel,
                $category,
                $title,
                $description,
                $dateForDb,
                $incidentLocation,
                $priority,
                $visibility,
                $publicStatus
            );

            if (!mysqli_stmt_execute($insert)) {
                throw new Exception("Cannot save complaint.");
            }

            $complaintId = mysqli_insert_id($conn);
            mysqli_stmt_close($insert);

            $complaintCode =
                "WC-" . date("Y") . "-" .
                str_pad((string)$complaintId, 5, "0", STR_PAD_LEFT);

            $update = mysqli_prepare(
                $conn,
                "UPDATE warden_complaints
                 SET complaint_code = ?
                 WHERE id = ?"
            );

            if (!$update) {
                throw new Exception("Cannot prepare tracking code.");
            }

            mysqli_stmt_bind_param(
                $update, "si", $complaintCode, $complaintId
            );

            if (!mysqli_stmt_execute($update)) {
                throw new Exception("Cannot save tracking code.");
            }

            mysqli_stmt_close($update);

            /* SECURE EVIDENCE STORAGE */

            if ($evidence !== null) {

                $privateDir =
                    dirname(__DIR__, 3) .
                    DIRECTORY_SEPARATOR .
                    "unistay_private" .
                    DIRECTORY_SEPARATOR .
                    "warden_evidence";

                if (
                    !is_dir($privateDir) &&
                    !mkdir($privateDir, 0700, true) &&
                    !is_dir($privateDir)
                ) {
                    throw new Exception("Cannot create evidence directory.");
                }

                $storedName =
                    bin2hex(random_bytes(20)) . "." .
                    $evidence["extension"];

                $storedPath =
                    $privateDir .
                    DIRECTORY_SEPARATOR .
                    $storedName;

                if (!move_uploaded_file($evidence["tmp"], $storedPath)) {
                    throw new Exception("Cannot save evidence.");
                }

                $evStmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO warden_complaint_evidence
                    (
                        complaint_id, stored_filename,
                        original_filename, mime_type, file_size
                    )
                    VALUES (?, ?, ?, ?, ?)"
                );

                if (!$evStmt) {
                    throw new Exception("Cannot prepare evidence record.");
                }

                mysqli_stmt_bind_param(
                    $evStmt,
                    "isssi",
                    $complaintId,
                    $storedName,
                    $evidence["original"],
                    $evidence["mime"],
                    $evidence["size"]
                );

                if (!mysqli_stmt_execute($evStmt)) {
                    throw new Exception("Cannot save evidence record.");
                }

                mysqli_stmt_close($evStmt);
            }

            /* AUDIT HISTORY */

            $action = "Complaint Submitted";
            $details = "Student submitted a warden concern.";
            $actorRole = "student";

            $history = mysqli_prepare(
                $conn,
                "INSERT INTO warden_complaint_history
                (complaint_id, action, details, actor_role)
                VALUES (?, ?, ?, ?)"
            );

            if (!$history) {
                throw new Exception("Cannot prepare history.");
            }

            mysqli_stmt_bind_param(
                $history,
                "isss",
                $complaintId,
                $action,
                $details,
                $actorRole
            );

            if (!mysqli_stmt_execute($history)) {
                throw new Exception("Cannot save history.");
            }

            mysqli_stmt_close($history);

            mysqli_commit($conn);
            $transactionStarted = false;

            $_SESSION["wc_success"] = $complaintCode;
            $_SESSION["wc_csrf"] = bin2hex(random_bytes(32));

            header("Location: warden_complaint.php?submitted=1");
            exit;

        } catch (Throwable $e) {

            if ($transactionStarted) {
                mysqli_rollback($conn);
            }

            if ($storedPath !== null && is_file($storedPath)) {
                unlink($storedPath);
            }

            error_log("UniStay Warden Complaint: " . $e->getMessage());
            $error = "Unable to submit. Please try again.";
        }
    }
}

/* RECENT COMPLAINTS */

$recentComplaints = [];

$recentStmt = mysqli_prepare(
    $conn,
    "SELECT complaint_code, title, status, priority
     FROM warden_complaints
     WHERE student_id = ? AND hostel = ?
     ORDER BY id DESC
     LIMIT 5"
);

if ($recentStmt) {
    mysqli_stmt_bind_param($recentStmt, "is", $userId, $hostel);
    mysqli_stmt_execute($recentStmt);
    $recentResult = mysqli_stmt_get_result($recentStmt);

    while ($row = mysqli_fetch_assoc($recentResult)) {
        $recentComplaints[] = $row;
    }

    mysqli_stmt_close($recentStmt);
}
/* COMMUNITY COMPLAINTS: ONLY ADMIN-APPROVED, REDACTED SUMMARIES */

$communityComplaints = [];
$communityStmt = mysqli_prepare(
    $conn,
    "SELECT wc.id, wc.category, wc.public_summary,
            (SELECT COUNT(*) FROM warden_complaint_supports ws
             WHERE ws.complaint_id = wc.id) AS support_count,
            (SELECT COUNT(*) FROM warden_complaint_supports mine
             WHERE mine.complaint_id = wc.id AND mine.student_id = ?) AS my_support
     FROM warden_complaints wc
     WHERE wc.hostel = ?
       AND wc.visibility = 'anonymous'
       AND wc.public_status = 'approved'
       AND wc.public_summary IS NOT NULL
       AND TRIM(wc.public_summary) <> ''
     ORDER BY wc.id DESC
     LIMIT 12"
);

if ($communityStmt) {
    mysqli_stmt_bind_param($communityStmt, "is", $userId, $hostel);
    if (mysqli_stmt_execute($communityStmt)) {
        $communityResult = mysqli_stmt_get_result($communityStmt);
        while ($communityRow = mysqli_fetch_assoc($communityResult)) {
            $communityComplaints[] = $communityRow;
        }
    } else {
        error_log("UniStay community complaints query failed: " . mysqli_stmt_error($communityStmt));
    }
    mysqli_stmt_close($communityStmt);
} else {
    error_log("UniStay community complaints prepare failed: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Report a Warden Concern | UniStay</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root {
    --green: #075e56;
    --green-dark: #06443f;
    --green-light: #eaf6f0;
    --text: #20343a;
    --muted: #71838a;
    --border: #e1eae5;
    --background: #f8f9f6;
    --white: #ffffff;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: var(--background);
    color: var(--text);
    font-family: "DM Sans", sans-serif;
    font-size: 16px;
    line-height: 1.6;
}

button, input, textarea {
    font: inherit;
}

a {
    text-decoration: none;
    color: inherit;
}

.shell {
    display: flex;
    min-height: 100vh;
}

.main {
    flex: 1;
    min-width: 0;
    padding: 28px clamp(16px, 3vw, 45px) 40px;
}

.container {
    max-width: 1120px;
    margin: 0 auto;
}

/* TOP BAR */

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 25px;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: #4d7068;
    font-size: 15px;
    font-weight: 700;
}

.back-link:hover {
    color: var(--green);
}

.hostel-pill {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 10px 17px;
    border-radius: 30px;
    background: #e6f4eb;
    color: var(--green);
    font-size: 13px;
    font-weight: 800;
}

/* PAGE HEADER */

.eyebrow {
    color: #258675;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 1.7px;
    text-transform: uppercase;
}

.page-header {
    margin-bottom: 25px;
}

.page-header h1 {
    margin: 10px 0;
    font-family: "Manrope", sans-serif;
    font-size: clamp(34px, 3.5vw, 46px);
    font-weight: 800;
    letter-spacing: -1.7px;
    line-height: 1.25;
}

.page-header h1 span {
    color: var(--green);
}

.page-header p {
    max-width: 760px;
    margin: 0;
    color: var(--muted);
    font-size: 16px;
    line-height: 1.75;
}

/* WIZARD */

.wizard {
    overflow: hidden;
    border: 1px solid #e5ede8;
    border-radius: 25px;
    background: white;
    box-shadow: 0 18px 55px rgba(18, 61, 49, .06);
}

.wizard-header {
    padding: 24px 35px;
    border-bottom: 1px solid var(--border);
}

.wizard-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 20px;
}

.wizard-name {
    display: flex;
    align-items: center;
    gap: 11px;
    font-family: "Manrope", sans-serif;
    font-size: 20px;
    font-weight: 800;
}

.wizard-name i {
    color: var(--green);
    font-size: 23px;
}

.step-counter {
    padding: 9px 15px;
    border-radius: 25px;
    background: var(--green-light);
    color: var(--green);
    font-size: 13px;
    font-weight: 800;
}

.progress-track {
    height: 6px;
    overflow: hidden;
    border-radius: 10px;
    background: #e8eeea;
}

.progress-fill {
    width: 16.666%;
    height: 100%;
    border-radius: 10px;
    background: var(--green);
    transition: width .45s ease;
}

.steps {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    margin-top: 18px;
}

.step {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #98a6a1;
    font-size: 13px;
    font-weight: 700;
}

.step-circle {
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    flex-shrink: 0;
    border-radius: 50%;
    background: #f0f3f1;
    color: #82918b;
    font-size: 13px;
}

.step.active {
    color: var(--green);
}

.step.active .step-circle,
.step.completed .step-circle {
    background: var(--green);
    color: white;
}

/* SLIDING WIZARD - COMPACT HEIGHT FIX */

.slide-window {
    overflow: hidden;
    transition: height .35s ease;
}

.slide-track {
    display: flex;
    align-items: flex-start;
    width: 100%;
    transition: transform .52s cubic-bezier(.22, .8, .24, 1);
}

.slide {
    flex: 0 0 100%;
    min-width: 0;
    min-height: 0;
    padding: 30px clamp(24px, 5vw, 70px);
}

.slide-tag {
    color: #248674;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 1.5px;
    text-transform: uppercase;
}

.slide h2 {
    margin: 12px 0 10px;
    font-family: "Manrope", sans-serif;
    font-size: clamp(29px, 3vw, 39px);
    font-weight: 800;
    letter-spacing: -1.4px;
    line-height: 1.3;
}

.slide h2 span {
    color: var(--green);
}

.slide-intro {
    max-width: 720px;
    margin: 0 0 25px;
    color: var(--muted);
    font-size: 16px;
    line-height: 1.75;
}

/* WELCOME */

.welcome-box {
    padding: 30px;
    border: 1px solid #deeee4;
    border-radius: 21px;
    background: linear-gradient(135deg, #edf8f1, #ffffff);
}

.welcome-icon {
    display: grid;
    place-items: center;
    width: 70px;
    height: 70px;
    margin-bottom: 18px;
    border-radius: 20px;
    background: #d7eee0;
    color: var(--green);
    font-size: 32px;
}

.welcome-box h3 {
    margin: 0 0 10px;
    font-family: "Manrope", sans-serif;
    font-size: 27px;
}

.welcome-box p {
    max-width: 690px;
    margin: 0;
    color: #698078;
    font-size: 16px;
    line-height: 1.75;
}

.welcome-features {
    display: flex;
    flex-wrap: wrap;
    gap: 22px;
    margin-top: 22px;
}

.welcome-features span {
    color: #366c5c;
    font-size: 14px;
    font-weight: 800;
}

.welcome-features i {
    margin-right: 7px;
}

/* CATEGORY CARDS */

.category-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.choice {
    position: relative;
    display: block;
    cursor: pointer;
}

.choice input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
}

.category-card {
    display: flex;
    align-items: center;
    gap: 16px;
    min-height: 105px;
    padding: 18px;
    border: 1.5px solid var(--border);
    border-radius: 17px;
    background: white;
    transition: .2s ease;
}

.category-card:hover {
    transform: translateY(-2px);
    border-color: #91c6b0;
}

.category-icon {
    display: grid;
    place-items: center;
    width: 54px;
    height: 54px;
    flex-shrink: 0;
    border-radius: 50%;
    background: #e5f4ec;
    color: var(--green);
    font-size: 22px;
}

.category-card strong {
    display: block;
    margin-bottom: 4px;
    font-size: 16px;
    font-weight: 800;
}

.category-card small {
    display: block;
    color: var(--muted);
    font-size: 14px;
    line-height: 1.5;
}

.category-check {
    display: grid;
    place-items: center;
    width: 22px;
    height: 22px;
    flex-shrink: 0;
    margin-left: auto;
    border: 1.5px solid #cbd9d0;
    border-radius: 50%;
    color: transparent;
    font-size: 11px;
}

.choice input:checked + .category-card,
.choice input:checked + .option-card {
    border-color: #168a75;
    background: #f0faf4;
    box-shadow: 0 0 0 2px rgba(22, 138, 117, .07);
}

.choice input:checked + .category-card .category-check {
    border-color: var(--green);
    background: var(--green);
    color: white;
}

.choice input:focus-visible + .category-card,
.choice input:focus-visible + .option-card {
    outline: 3px solid #a4d8bd;
    outline-offset: 3px;
}

/* INPUTS */

.field {
    margin-bottom: 18px;
}

.field label {
    display: block;
    margin-bottom: 8px;
    font-size: 15px;
    font-weight: 800;
}

.field input,
.field textarea {
    width: 100%;
    padding: 14px 17px;
    border: 1.5px solid #dce8e1;
    border-radius: 13px;
    outline: none;
    background: #fcfdfc;
    color: var(--text);
    font-size: 16px;
}

.field textarea {
    min-height: 145px;
    resize: vertical;
    line-height: 1.7;
}

.field input:focus,
.field textarea:focus {
    border-color: #208e79;
    box-shadow: 0 0 0 3px #e5f4ec;
}

.field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 17px;
}

.hint {
    margin-top: 7px;
    color: var(--muted);
    font-size: 13px;
}

/* PRIORITY AND PRIVACY */

.option-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
}

.option-card {
    display: flex;
    flex-direction: column;
    min-height: 145px;
    padding: 22px;
    border: 1.5px solid var(--border);
    border-radius: 17px;
    background: white;
    transition: .2s ease;
}

.option-card:hover {
    border-color: #9bcbb9;
    transform: translateY(-2px);
}

.option-card > i {
    margin-bottom: 16px;
    color: var(--green);
    font-size: 25px;
}

.option-card strong {
    display: block;
    margin-bottom: 7px;
    font-size: 16px;
    font-weight: 800;
}

.option-card small {
    color: var(--muted);
    font-size: 14px;
    line-height: 1.65;
}

.privacy-grid {
    grid-template-columns: repeat(2, 1fr);
}

.privacy-grid .option-card {
    min-height: 205px;
}

.privacy-warning {
    margin-top: 20px;
    padding: 17px;
    border: 1px solid #efdfbc;
    border-radius: 13px;
    background: #fff9ed;
    color: #78633c;
    font-size: 14px;
    line-height: 1.75;
}

/* EVIDENCE */

.upload-zone {
    display: block;
    margin-top: 20px;
    padding: 25px 20px;
    border: 1.5px dashed #a9cdbb;
    border-radius: 17px;
    background: #f7fbf8;
    text-align: center;
}

.upload-zone > i {
    color: var(--green);
    font-size: 29px;
}

.upload-zone strong {
    display: block;
    margin: 12px 0 5px;
    font-size: 16px;
}

.upload-zone small {
    display: block;
    color: var(--muted);
    font-size: 14px;
}

.upload-zone input {
    display: block;
    max-width: 100%;
    margin: 16px auto 0;
    font-size: 14px;
}

/* REVIEW */

.review-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 13px;
}

.review-item {
    min-width: 0;
    padding: 17px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: #fbfcfb;
}

.review-item.full {
    grid-column: 1 / -1;
}

.review-item span {
    display: block;
    margin-bottom: 7px;
    color: var(--muted);
    font-size: 13px;
    font-weight: 800;
    text-transform: uppercase;
}

.review-item strong {
    display: block;
    font-size: 16px;
    line-height: 1.7;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

/* FOOTER */

.wizard-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 20px 35px;
    border-top: 1px solid var(--border);
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 11px;
    min-width: 140px;
    min-height: 50px;
    padding: 12px 23px;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-size: 15px;
    font-weight: 800;
    transition: .2s ease;
}

.btn:hover {
    transform: translateY(-2px);
}

.btn-back {
    background: #eff3f0;
    color: #566e65;
}

.btn-next,
.btn-submit {
    background: var(--green);
    color: white;
    box-shadow: 0 8px 18px rgba(7, 94, 86, .15);
}

.btn:disabled {
    opacity: .6;
    cursor: wait;
}

.footer-status {
    color: #91a09a;
    font-size: 13px;
    font-weight: 800;
}

/* ALERT */

.alert {
    margin-bottom: 18px;
    padding: 16px 20px;
    border: 1px solid #f0caca;
    border-radius: 13px;
    background: #fff0f0;
    color: #a63e3e;
    font-size: 15px;
    font-weight: 700;
}

/* SUCCESS */

.success-screen {
    padding: 65px 25px;
    text-align: center;
}

.success-icon {
    display: grid;
    place-items: center;
    width: 90px;
    height: 90px;
    margin: 0 auto 23px;
    border-radius: 50%;
    background: #e0f5e7;
    color: var(--green);
    font-size: 37px;
}

.success-screen h2 {
    font-family: "Manrope", sans-serif;
    font-size: 32px;
}

.success-screen p {
    max-width: 520px;
    margin: 13px auto 22px;
    color: var(--muted);
    font-size: 16px;
}

.success-code {
    display: inline-block;
    margin: 10px 0 24px;
    padding: 15px 25px;
    border: 1px dashed #a3cdb6;
    border-radius: 12px;
    background: #f0faf4;
    color: var(--green);
    font-size: 22px;
    font-weight: 800;
}

/* RECENT COMPLAINTS */

.recent {
    margin-top: 26px;
}

.recent h3 {
    margin-bottom: 15px;
    font-size: 19px;
}

.recent-list {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.recent-card {
    padding: 19px;
    border: 1px solid var(--border);
    border-radius: 15px;
    background: white;
}

.recent-card strong {
    display: block;
    margin-bottom: 8px;
    color: var(--green);
    font-size: 15px;
}

.recent-card p {
    margin: 0 0 9px;
    font-size: 15px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.recent-card small {
    color: var(--muted);
    font-size: 13px;
}

/* COMMUNITY COMPLAINTS */

.community-jump {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 22px;
}

.community-jump a {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 11px 17px;
    border: 1px solid #cde3d7;
    border-radius: 12px;
    background: white;
    color: var(--green);
    font-size: 14px;
    font-weight: 800;
}

.community-jump a:first-child {
    background: var(--green);
    border-color: var(--green);
    color: white;
}

.community-jump a:hover { box-shadow: 0 5px 16px rgba(7,94,86,.1); }

.community-section {
    margin-top: 28px;
    padding: 28px;
    border: 1px solid var(--border);
    border-radius: 22px;
    background: #fff;
    scroll-margin-top: 20px;
}

.community-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 20px;
}

.community-heading h2 {
    margin: 6px 0 8px;
    font: 800 clamp(23px, 2.5vw, 30px)/1.3 "Manrope", sans-serif;
    letter-spacing: -.7px;
}

.community-heading p {
    margin: 0;
    color: var(--muted);
    font-size: 15px;
    line-height: 1.7;
    max-width: 720px;
}

.community-count {
    border-radius: 30px;
    padding: 8px 13px;
    background: var(--green-light);
    color: var(--green);
    font-size: 13px;
    font-weight: 800;
    white-space: nowrap;
}

.community-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
}

.community-card {
    min-width: 0;
    padding: 22px;
    border: 1px solid #e1eae5;
    border-radius: 16px;
    background: #fbfdfb;
}

.community-card .community-category {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 11px;
    border-radius: 30px;
    background: #e9f5ee;
    color: var(--green);
    font-size: 12px;
    font-weight: 800;
}

.community-card p {
    margin: 15px 0 0;
    font-size: 15px;
    line-height: 1.8;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}


.community-support-form { margin-top: 19px; }
.community-support-button {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 11px 15px;
    border: 1px solid #bcd9cb;
    border-radius: 12px;
    background: white;
    color: var(--green);
    font: 800 13px/1.4 "Manrope", sans-serif;
    cursor: pointer;
    transition: background .15s, transform .15s;
}
.community-support-button:hover { background: #eaf7ef; transform: translateY(-1px); }
.community-support-button.is-supported {
    background: #e4f5ed;
    border-color: #74b49a;
}
.community-support-button .support-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 25px;
    padding: 3px 7px;
    border-radius: 20px;
    background: #d7efe2;
    font-size: 12px;
}
.community-feedback {
    margin-bottom: 18px;
    padding: 13px 16px;
    border-radius: 11px;
    background: #e8f6ee;
    color: #17664e;
    font-weight: 700;
    font-size: 14px;
}
.community-feedback.is-error { background: #fff0ef; color: #a53a36; }

.community-empty {
    padding: 30px 20px;
    border: 1px dashed #bdd8c9;
    border-radius: 16px;
    background: #f8fcf9;
    text-align: center;
}

.community-empty i {
    display: block;
    margin-bottom: 12px;
    color: var(--green);
    font-size: 27px;
}

.community-empty strong { display: block; font-size: 17px; }
.community-empty p { margin: 7px 0 0; color: var(--muted); font-size: 14px; }

/* MOBILE */

@media (max-width: 1050px) {
    .step-label {
        display: none;
    }
}

@media (max-width: 700px) {
    .shell {
        display: block;
    }

    .main {
        padding: 18px 13px 35px;
    }

    .page-header h1 {
        font-size: 32px;
    }

    .page-header p {
        font-size: 15px;
    }

    .wizard {
        border-radius: 18px;
    }

    .wizard-header {
        padding: 20px;
    }

    .wizard-name {
        font-size: 17px;
    }

    .slide {
        padding: 25px 20px;
    }

    .slide h2 {
        font-size: 28px;
    }

    .slide-intro {
        font-size: 15px;
    }

    .category-grid,
    .option-grid,
    .privacy-grid,
    .field-row,
    .review-grid,
    .recent-list {
        grid-template-columns: 1fr;
    }

    .review-item.full {
        grid-column: auto;
    }

    .community-grid { grid-template-columns: 1fr; }
    .community-section { padding: 20px; }

    .category-card {
        min-height: 90px;
    }

    .welcome-box {
        padding: 24px;
    }

    .wizard-actions {
        padding: 18px 20px;
    }

    .footer-status {
        display: none;
    }

    .btn {
        min-width: 115px;
        padding: 12px 16px;
    }

    .step-circle {
        width: 26px;
        height: 26px;
        font-size: 12px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .slide-track,
    .slide-window,
    .progress-fill,
    .btn,
    .category-card,
    .option-card {
        transition: none;
    }
}
</style>
</head>

<body>
<div class="shell">

    <?php include "sidebar.php"; ?>

    <main class="main">
        <div class="container">

            <div class="topbar">
                <a href="complaints.php" class="back-link">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Complaints
                </a>

                <span class="hostel-pill">
                    <i class="fa-solid fa-building"></i>
                    <?php echo wcEsc(ucfirst($hostel)); ?> Hostel
                </span>
            </div>

            <div class="page-header">
                <div class="eyebrow">
                    UNISTAY / STUDENT WELLBEING
                </div>

                <h1>Your voice <span>matters.</span></h1>

                <p>
                    A dedicated space to report concerns involving your
                    hostel warden. Share your experience, choose your
                    privacy preference and track your submission.
                </p>
                <nav class="community-jump" aria-label="Warden concerns navigation">
                    <a href="#report-form"><i class="fa-solid fa-pen-to-square"></i> Report a Concern</a>
                    <a href="#community-complaints"><i class="fa-solid fa-users"></i> Community Complaints</a>
                </nav>
            </div>

            <?php if ($error !== ""): ?>
                <div class="alert" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php echo wcEsc($error); ?>
                </div>
            <?php endif; ?>

            <section class="wizard" id="report-form">

                <?php if ($success !== ""): ?>

                    <div class="success-screen">
                        <div class="success-icon">
                            <i class="fa-solid fa-check"></i>
                        </div>

                        <h2>Your concern has been submitted.</h2>

                        <p>
                            Your complaint has been saved for Admin review.
                            Keep your tracking ID for future reference.
                        </p>

                        <div class="success-code">
                            <?php echo wcEsc($success); ?>
                        </div>

                        <div>
                            <a href="warden_complaint.php" class="btn btn-next">
                                <i class="fa-solid fa-plus"></i>
                                Submit Another Concern
                            </a>
                        </div>
                    </div>

                <?php else: ?>

                    <div class="wizard-header">
                        <div class="wizard-top">
                            <div class="wizard-name">
                                <i class="fa-solid fa-shield-heart"></i>
                                Warden Concern
                            </div>

                            <div class="step-counter">
                                STEP <span id="stepNumber">1</span> OF 6
                            </div>
                        </div>

                        <div class="progress-track">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>

                        <div class="steps">
                            <?php
                            $stepNames = [
                                "Welcome", "Category", "Details",
                                "Evidence", "Privacy", "Review"
                            ];
                            ?>

                            <?php foreach ($stepNames as $index => $name): ?>
                                <div class="step <?php echo $index === 0 ? 'active' : ''; ?>">
                                    <span class="step-circle">
                                        <?php echo $index + 1; ?>
                                    </span>

                                    <span class="step-label">
                                        <?php echo wcEsc($name); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <form
                        id="concernForm"
                        method="POST"
                        enctype="multipart/form-data"
                        novalidate
                    >
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo wcEsc($csrf); ?>"
                        >

                        <div class="slide-window" id="slideWindow">
                            <div class="slide-track" id="slideTrack">

                                <!-- STEP 1: WELCOME -->

                                <section class="slide">
                                    <div class="slide-tag">
                                        STEP 01 / 06 · WELCOME
                                    </div>

                                    <h2>
                                        A space to <span>speak openly.</span>
                                    </h2>

                                    <p class="slide-intro">
                                        Every concern deserves attention.
                                        This guided form helps you report
                                        your experience one step at a time.
                                    </p>

                                    <div class="welcome-box">
                                        <div class="welcome-icon">
                                            <i class="fa-solid fa-comment-dots"></i>
                                        </div>

                                        <h3>We're here to listen.</h3>

                                        <p>
                                            Describe what happened in your
                                            own words. Your report is intended
                                            for authorized Admin review and
                                            is separate from ordinary hostel
                                            complaints.
                                        </p>

                                        <div class="welcome-features">
                                            <span>
                                                <i class="fa-solid fa-list-check"></i>
                                                Guided reporting
                                            </span>

                                            <span>
                                                <i class="fa-solid fa-lock"></i>
                                                Privacy options
                                            </span>

                                            <span>
                                                <i class="fa-solid fa-hashtag"></i>
                                                Tracking ID
                                            </span>
                                        </div>
                                    </div>
                                </section>

                                <!-- STEP 2: CATEGORY -->

                                <section class="slide">
                                    <div class="slide-tag">
                                        STEP 02 / 06 · CATEGORY
                                    </div>

                                    <h2>
                                        What would you like to
                                        <span>report?</span>
                                    </h2>

                                    <p class="slide-intro">
                                        Select the category that best
                                        describes your concern.
                                    </p>

                                    <div class="category-grid">
                                        <?php foreach ($categories as $name => $info): ?>

                                            <label class="choice">
                                                <input
                                                    type="radio"
                                                    name="category"
                                                    value="<?php echo wcEsc($name); ?>"
                                                    <?php echo $old["category"] === $name ? "checked" : ""; ?>
                                                >

                                                <span class="category-card">
                                                    <span class="category-icon">
                                                        <i class="fa-solid <?php echo wcEsc($info[0]); ?>"></i>
                                                    </span>

                                                    <span>
                                                        <strong>
                                                            <?php echo wcEsc($name); ?>
                                                        </strong>

                                                        <small>
                                                            <?php echo wcEsc($info[1]); ?>
                                                        </small>
                                                    </span>

                                                    <span class="category-check">
                                                        <i class="fa-solid fa-check"></i>
                                                    </span>
                                                </span>
                                            </label>

                                        <?php endforeach; ?>
                                    </div>
                                </section>

                                <!-- STEP 3: DETAILS -->

                                <section class="slide">
                                    <div class="slide-tag">
                                        STEP 03 / 06 · DETAILS
                                    </div>

                                    <h2>
                                        Tell us what <span>happened.</span>
                                    </h2>

                                    <p class="slide-intro">
                                        Explain your experience clearly.
                                        Include relevant details that
                                        will help Admin understand.
                                    </p>

                                    <div class="field">
                                        <label for="title">
                                            Complaint Title *
                                        </label>

                                        <input
                                            type="text"
                                            id="title"
                                            name="title"
                                            minlength="5"
                                            maxlength="150"
                                            placeholder="Title fills automatically after category selection"
                                            value="<?php echo wcEsc($old["title"]); ?>"
                                            required
                                        >

                                        <div class="hint">
                                            Automatically filled from your selected category.
                                            You can edit it if needed.
                                        </div>
                                    </div>

                                    <div class="field">
                                        <label for="description">
                                            Describe Your Experience *
                                        </label>

                                        <textarea
                                            id="description"
                                            name="description"
                                            minlength="20"
                                            maxlength="5000"
                                            placeholder="Describe what happened in your own words..."
                                            required
                                        ><?php echo wcEsc($old["description"]); ?></textarea>

                                        <div class="hint">
                                            <span id="charCount">0</span>/5000 characters.
                                            Minimum 20 characters.
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div class="field">
                                            <label for="incident_date">
                                                Incident Date (Optional)
                                            </label>

                                            <input
                                                type="date"
                                                id="incident_date"
                                                name="incident_date"
                                                max="<?php echo date("Y-m-d"); ?>"
                                                value="<?php echo wcEsc($old["incident_date"]); ?>"
                                            >
                                        </div>

                                        <div class="field">
                                            <label for="incident_location">
                                                Incident Location (Optional)
                                            </label>

                                            <input
                                                type="text"
                                                id="incident_location"
                                                name="incident_location"
                                                maxlength="150"
                                                placeholder="e.g. Hostel office"
                                                value="<?php echo wcEsc($old["incident_location"]); ?>"
                                            >
                                        </div>
                                    </div>
                                </section>

                                <!-- STEP 4: PRIORITY AND EVIDENCE -->

                                <section class="slide">
                                    <div class="slide-tag">
                                        STEP 04 / 06 · PRIORITY & EVIDENCE
                                    </div>

                                    <h2>
                                        How urgent is your
                                        <span>concern?</span>
                                    </h2>

                                    <p class="slide-intro">
                                        Choose the priority level and
                                        optionally attach supporting evidence.
                                    </p>

                                    <div class="option-grid">
                                        <label class="choice">
                                            <input
                                                type="radio"
                                                name="priority"
                                                value="Normal"
                                                <?php echo $old["priority"] === "Normal" ? "checked" : ""; ?>
                                            >

                                            <span class="option-card">
                                                <i class="fa-solid fa-circle-check"></i>
                                                <strong>Normal</strong>
                                                <small>General concern</small>
                                            </span>
                                        </label>

                                        <label class="choice">
                                            <input
                                                type="radio"
                                                name="priority"
                                                value="High"
                                                <?php echo $old["priority"] === "High" ? "checked" : ""; ?>
                                            >

                                            <span class="option-card">
                                                <i class="fa-solid fa-triangle-exclamation"></i>
                                                <strong>High</strong>
                                                <small>Serious concern</small>
                                            </span>
                                        </label>

                                        <label class="choice">
                                            <input
                                                type="radio"
                                                name="priority"
                                                value="Urgent"
                                                <?php echo $old["priority"] === "Urgent" ? "checked" : ""; ?>
                                            >

                                            <span class="option-card">
                                                <i class="fa-solid fa-bolt"></i>
                                                <strong>Urgent</strong>
                                                <small>Needs prompt attention</small>
                                            </span>
                                        </label>
                                    </div>

                                    <div class="upload-zone">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>

                                        <strong>
                                            Attach Evidence (Optional)
                                        </strong>

                                        <small>
                                            JPG, PNG, WEBP or PDF · Maximum 5 MB
                                        </small>

                                        <input
                                            type="file"
                                            id="evidence"
                                            name="evidence"
                                            accept=".jpg,.jpeg,.png,.webp,.pdf"
                                        >
                                    </div>

                                    <div class="hint" style="margin-top:18px">
                                        If you are in immediate danger,
                                        contact campus security or
                                        emergency services directly.
                                    </div>
                                </section>

                                <!-- STEP 5: PRIVACY -->

                                <section class="slide">
                                    <div class="slide-tag">
                                        STEP 05 / 06 · PRIVACY
                                    </div>

                                    <h2>
                                        Choose your <span>privacy.</span>
                                    </h2>

                                    <p class="slide-intro">
                                        Decide how your concern should
                                        be handled.
                                    </p>

                                    <div class="option-grid privacy-grid">
                                        <label class="choice">
                                            <input
                                                type="radio"
                                                name="visibility"
                                                value="private"
                                                <?php echo $old["visibility"] === "private" ? "checked" : ""; ?>
                                            >

                                            <span class="option-card">
                                                <i class="fa-solid fa-lock"></i>

                                                <strong>
                                                    Keep It Private
                                                </strong>

                                                <small>
                                                    Recommended. Your full
                                                    complaint is intended
                                                    only for you and
                                                    authorized Admin review.
                                                    Other students cannot
                                                    see it.
                                                </small>
                                            </span>
                                        </label>

                                        <label class="choice">
                                            <input
                                                type="radio"
                                                name="visibility"
                                                value="anonymous"
                                                <?php echo $old["visibility"] === "anonymous" ? "checked" : ""; ?>
                                            >

                                            <span class="option-card">
                                                <i class="fa-solid fa-users"></i>

                                                <strong>
                                                    Share Anonymously
                                                </strong>

                                                <small>
                                                    Request a redacted
                                                    summary for students
                                                    in your hostel. Admin
                                                    must prepare and approve
                                                    the summary before it
                                                    can be shared.
                                                </small>
                                            </span>
                                        </label>
                                    </div>

                                    <div class="privacy-warning">
                                        <i class="fa-solid fa-circle-info"></i>

                                        <strong>Important:</strong>
                                        Anonymous sharing does not guarantee
                                        complete anonymity. Your student ID
                                        remains stored for ownership and
                                        tracking. Incident details could
                                        indirectly reveal your identity.
                                    </div>
                                </section>

                                <!-- STEP 6: REVIEW -->

                                <section class="slide">
                                    <div class="slide-tag">
                                        STEP 06 / 06 · FINAL REVIEW
                                    </div>

                                    <h2>
                                        Review before you
                                        <span>submit.</span>
                                    </h2>

                                    <p class="slide-intro">
                                        Check your details carefully.
                                        You can go back to edit anything.
                                    </p>

                                    <div class="review-grid">
                                        <div class="review-item">
                                            <span>Hostel</span>
                                            <strong>
                                                <?php echo wcEsc(ucfirst($hostel)); ?> Hostel
                                            </strong>
                                        </div>

                                        <div class="review-item">
                                            <span>Category</span>
                                            <strong id="reviewCategory">—</strong>
                                        </div>

                                        <div class="review-item full">
                                            <span>Title</span>
                                            <strong id="reviewTitle">—</strong>
                                        </div>

                                        <div class="review-item full">
                                            <span>Description</span>
                                            <strong id="reviewDescription">—</strong>
                                        </div>

                                        <div class="review-item">
                                            <span>Incident Date</span>
                                            <strong id="reviewDate">—</strong>
                                        </div>

                                        <div class="review-item">
                                            <span>Location</span>
                                            <strong id="reviewLocation">—</strong>
                                        </div>

                                        <div class="review-item">
                                            <span>Priority</span>
                                            <strong id="reviewPriority">—</strong>
                                        </div>

                                        <div class="review-item">
                                            <span>Privacy</span>
                                            <strong id="reviewPrivacy">—</strong>
                                        </div>

                                        <div class="review-item full">
                                            <span>Evidence</span>
                                            <strong id="reviewEvidence">—</strong>
                                        </div>
                                    </div>
                                </section>

                            </div>
                        </div>

                        <div class="wizard-actions">
                            <button
                                type="button"
                                class="btn btn-back"
                                id="prevBtn"
                                style="visibility:hidden"
                            >
                                <i class="fa-solid fa-arrow-left"></i>
                                Back
                            </button>

                            <span class="footer-status" id="footerStatus">
                                STEP 1 OF 6
                            </span>

                            <button
                                type="button"
                                class="btn btn-next"
                                id="nextBtn"
                            >
                                Get Started
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>

                            <button
                                type="submit"
                                class="btn btn-submit"
                                id="submitBtn"
                                style="display:none"
                            >
                                <i class="fa-solid fa-paper-plane"></i>
                                Submit Concern
                            </button>
                        </div>
                    </form>

                <?php endif; ?>

            </section>

            <!-- Only approved, Admin-redacted summaries from the student's own hostel. -->
            <section class="community-section" id="community-complaints" aria-labelledby="community-title">
                <div class="community-heading">
                    <div>
                        <div class="eyebrow">UNISTAY / COMMUNITY VOICES</div>
                        <h2 id="community-title">Community Complaints</h2>
                        <p>
                            Approved, identity-redacted warden concern summaries shared by students
                            from <?php echo wcEsc(ucfirst($hostel)); ?> Hostel.
                            Full complaints, names, tracking IDs and evidence are never shown here.
                        </p>
                    </div>
                    <span class="community-count">
                        <i class="fa-solid fa-shield-halved"></i>
                        <?php echo count($communityComplaints); ?> Approved
                    </span>
                </div>

                <?php if ($supportNotice !== ""): ?>
                    <div class="community-feedback" role="status"><?php echo wcEsc($supportNotice); ?></div>
                <?php endif; ?>
                <?php if ($supportError !== ""): ?>
                    <div class="community-feedback is-error" role="alert"><?php echo wcEsc($supportError); ?></div>
                <?php endif; ?>

                <?php if (!empty($communityComplaints)): ?>
                    <div class="community-grid">
                        <?php foreach ($communityComplaints as $communityComplaint): ?>
                            <article class="community-card">
                                <span class="community-category">
                                    <i class="fa-solid fa-shield-heart"></i>
                                    <?php echo wcEsc($communityComplaint["category"]); ?>
                                </span>
                                <p><?php echo wcEsc($communityComplaint["public_summary"]); ?></p>
                                <form method="POST" action="warden_complaint.php#community-complaints" class="community-support-form">
                                    <input type="hidden" name="form_action" value="toggle_community_support">
                                    <input type="hidden" name="csrf_token" value="<?php echo wcEsc($csrf); ?>">
                                    <input type="hidden" name="community_complaint_id" value="<?php echo (int)$communityComplaint["id"]; ?>">
                                    <button type="submit"
                                        class="community-support-button <?php echo (int)$communityComplaint["my_support"] > 0 ? "is-supported" : ""; ?>"
                                        aria-pressed="<?php echo (int)$communityComplaint["my_support"] > 0 ? "true" : "false"; ?>"
                                        title="<?php echo (int)$communityComplaint["my_support"] > 0 ? "Click to remove your support" : "Click to support this concern"; ?>">
                                        <i class="fa-<?php echo (int)$communityComplaint["my_support"] > 0 ? "solid" : "regular"; ?> fa-heart" aria-hidden="true"></i>
                                        <?php echo (int)$communityComplaint["my_support"] > 0 ? "You Feel the Same" : "I Feel the Same"; ?>
                                        <span class="support-count" aria-label="<?php echo (int)$communityComplaint["support_count"]; ?> supports"><?php echo (int)$communityComplaint["support_count"]; ?></span>
                                    </button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="community-empty">
                        <i class="fa-solid fa-lock"></i>
                        <strong>No approved community summaries yet</strong>
                        <p>When Admin approves a redacted summary, students from this hostel will see it here.</p>
                    </div>
                <?php endif; ?>
            </section>

            <?php if (!empty($recentComplaints)): ?>
                <section class="recent">
                    <h3>Your Recent Submissions</h3>

                    <div class="recent-list">
                        <?php foreach ($recentComplaints as $complaint): ?>
                            <div class="recent-card">
                                <strong>
                                    <?php echo wcEsc($complaint["complaint_code"]); ?>
                                </strong>

                                <p>
                                    <?php echo wcEsc($complaint["title"]); ?>
                                </p>

                                <small>
                                    <?php echo wcEsc($complaint["status"]); ?>
                                    ·
                                    <?php echo wcEsc($complaint["priority"]); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

        </div>
    </main>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const form = document.getElementById("concernForm");

    if (!form) return;

    const track = document.getElementById("slideTrack");
    const slideWindow = document.getElementById("slideWindow");
    const slides = Array.from(document.querySelectorAll(".slide"));
    const stepItems = Array.from(document.querySelectorAll(".step"));

    const progressFill = document.getElementById("progressFill");
    const stepNumber = document.getElementById("stepNumber");
    const footerStatus = document.getElementById("footerStatus");

    const prevBtn = document.getElementById("prevBtn");
    const nextBtn = document.getElementById("nextBtn");
    const submitBtn = document.getElementById("submitBtn");

    const titleInput = document.getElementById("title");
    const descriptionInput = document.getElementById("description");
    const dateInput = document.getElementById("incident_date");
    const locationInput = document.getElementById("incident_location");
    const evidenceInput = document.getElementById("evidence");
    const charCount = document.getElementById("charCount");

    const autoTitles = <?php
        $autoTitles = [];

        foreach ($categories as $name => $info) {
            $autoTitles[$name] = $info[2];
        }

        echo json_encode(
            $autoTitles,
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        );
    ?>;

    let currentStep = 0;
    let submitting = false;

    const totalSteps = slides.length;

    function selectedValue(name) {
        const input = form.querySelector(
            'input[name="' + name + '"]:checked'
        );

        return input ? input.value : "";
    }

    /* AUTOMATIC EDITABLE COMPLAINT TITLE */

    function updateAutoTitle(category) {
        const currentTitle = titleInput.value.trim();

        const isAutoTitle = Object.values(autoTitles)
            .includes(currentTitle);

        if (currentTitle === "" || isAutoTitle) {
            titleInput.value = autoTitles[category] || "";
        }
    }

    document.querySelectorAll(
        'input[name="category"]'
    ).forEach(function (radio) {

        radio.addEventListener("change", function () {
            updateAutoTitle(this.value);
        });

    });

    if (selectedValue("category")) {
        updateAutoTitle(selectedValue("category"));
    }

    /* DYNAMIC HEIGHT - NO EXTRA BLANK SPACE */

    function updateSlideHeight() {
        if (!slides[currentStep]) return;

        const activeSlide = slides[currentStep];

        slideWindow.style.height =
            activeSlide.scrollHeight + "px";
    }

    function showStep(step) {

        currentStep = Math.max(
            0,
            Math.min(totalSteps - 1, step)
        );

        track.style.transform =
            "translateX(-" + (currentStep * 100) + "%)";

        progressFill.style.width =
            ((currentStep + 1) / totalSteps * 100) + "%";

        stepNumber.textContent = currentStep + 1;

        footerStatus.textContent =
            "STEP " + (currentStep + 1) +
            " OF " + totalSteps;

        stepItems.forEach(function (item, index) {
            item.classList.toggle(
                "active",
                index === currentStep
            );

            item.classList.toggle(
                "completed",
                index < currentStep
            );
        });

        slides.forEach(function (slide, index) {
            slide.setAttribute(
                "aria-hidden",
                index === currentStep ? "false" : "true"
            );

            if ("inert" in slide) {
                slide.inert = index !== currentStep;
            }
        });

        prevBtn.style.visibility =
            currentStep === 0 ? "hidden" : "visible";

        nextBtn.style.display =
            currentStep === totalSteps - 1
                ? "none"
                : "inline-flex";

        submitBtn.style.display =
            currentStep === totalSteps - 1
                ? "inline-flex"
                : "none";

        nextBtn.innerHTML =
            currentStep === 0
                ? 'Get Started <i class="fa-solid fa-arrow-right"></i>'
                : 'Continue <i class="fa-solid fa-arrow-right"></i>';

        if (currentStep === totalSteps - 1) {
            updateReview();
        }

        updateSlideHeight();

        requestAnimationFrame(updateSlideHeight);
    }

    function validateStep(step) {

        if (step === 0) return true;

        if (step === 1) {
            if (!selectedValue("category")) {
                alert("Please select a complaint category.");
                return false;
            }
            return true;
        }

        if (step === 2) {
            const title = titleInput.value.trim();
            const description = descriptionInput.value.trim();

            if (title.length < 5 || title.length > 150) {
                alert("Title must contain 5 to 150 characters.");
                return false;
            }

            if (
                description.length < 20 ||
                description.length > 5000
            ) {
                alert("Description must contain 20 to 5000 characters.");
                return false;
            }

            if (locationInput.value.trim().length > 150) {
                alert("Incident location is too long.");
                return false;
            }

            if (dateInput.value) {
                const today = new Date();

                const year = today.getFullYear();
                const month = String(
                    today.getMonth() + 1
                ).padStart(2, "0");

                const day = String(
                    today.getDate()
                ).padStart(2, "0");

                const todayString =
                    year + "-" + month + "-" + day;

                if (
                    !dateInput.checkValidity() ||
                    dateInput.value > todayString
                ) {
                    alert("Please enter a valid incident date.");
                    return false;
                }
            }

            return true;
        }

        if (step === 3) {
            if (!selectedValue("priority")) {
                alert("Please select a priority.");
                return false;
            }

            if (evidenceInput.files.length > 0) {
                const file = evidenceInput.files[0];

                if (file.size > 5 * 1024 * 1024) {
                    alert("Evidence must be 5 MB or smaller.");
                    return false;
                }

                if (!/\.(jpg|jpeg|png|webp|pdf)$/i.test(file.name)) {
                    alert("Only JPG, PNG, WEBP and PDF are allowed.");
                    return false;
                }
            }

            return true;
        }

        if (step === 4) {
            if (!selectedValue("visibility")) {
                alert("Please choose a privacy option.");
                return false;
            }

            return true;
        }

        return true;
    }

    function updateReview() {
        document.getElementById("reviewCategory").textContent =
            selectedValue("category") || "—";

        document.getElementById("reviewTitle").textContent =
            titleInput.value.trim() || "—";

        document.getElementById("reviewDescription").textContent =
            descriptionInput.value.trim() || "—";

        document.getElementById("reviewDate").textContent =
            dateInput.value || "Not specified";

        document.getElementById("reviewLocation").textContent =
            locationInput.value.trim() || "Not specified";

        document.getElementById("reviewPriority").textContent =
            selectedValue("priority") || "—";

        document.getElementById("reviewPrivacy").textContent =
            selectedValue("visibility") === "private"
                ? "Keep It Private"
                : "Share Anonymously";

        document.getElementById("reviewEvidence").textContent =
            evidenceInput.files.length
                ? evidenceInput.files[0].name
                : "No attachment";
    }

    nextBtn.addEventListener("click", function () {
        if (validateStep(currentStep)) {
            showStep(currentStep + 1);
        }
    });

    prevBtn.addEventListener("click", function () {
        showStep(currentStep - 1);
    });

    descriptionInput.addEventListener("input", function () {
        charCount.textContent =
            descriptionInput.value.length;

        if (currentStep === 2) {
            updateSlideHeight();
        }
    });

    charCount.textContent =
        descriptionInput.value.length;

    window.addEventListener("resize", updateSlideHeight);

    if ("ResizeObserver" in window) {
        const observer = new ResizeObserver(function () {
            updateSlideHeight();
        });

        slides.forEach(function (slide) {
            observer.observe(slide);
        });
    }

    form.addEventListener("submit", function (event) {

        if (submitting) {
            event.preventDefault();
            return;
        }

        for (let step = 1; step <= 4; step++) {
            if (!validateStep(step)) {
                event.preventDefault();
                showStep(step);
                return;
            }
        }

        submitting = true;
        submitBtn.disabled = true;

        submitBtn.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
    });

    showStep(0);

});
</script>

</body>
</html>

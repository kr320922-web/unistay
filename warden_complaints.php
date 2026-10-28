
<?php
session_start();

require_once __DIR__ . "/../db.php";

/*
|--------------------------------------------------------------------------
| ADMIN ACCESS ONLY
|--------------------------------------------------------------------------
*/

$userId = $_SESSION["user_id"]
    ?? $_SESSION["admin_id"]
    ?? null;

$userRole = $_SESSION["user_role"]
    ?? $_SESSION["role"]
    ?? "";

if (!$userId || strtolower((string)$userRole) !== "admin") {
    http_response_code(403);
    exit("Access denied. Only Admin can view Warden Complaints.");
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    exit("Database connection not available.");
}

mysqli_set_charset($conn, "utf8mb4");

date_default_timezone_set("Asia/Kolkata");

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function wcEscape($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function wcDate($value)
{
    if (!$value) {
        return "Not specified";
    }

    $time = strtotime((string)$value);

    return $time
        ? date("d M Y, h:i A", $time)
        : (string)$value;
}

function wcBadge($value)
{
    $value = strtolower(trim((string)$value));

    if (in_array($value, ["approved", "resolved", "closed"], true)) {
        return "green";
    }

    if (in_array($value, ["rejected", "urgent"], true)) {
        return "red";
    }

    if (in_array($value, ["pending", "high", "investigation"], true)) {
        return "orange";
    }

    if ($value === "under review") {
        return "blue";
    }

    return "gray";
}

function wcEnumValues($conn, $column)
{
    $allowedColumns = [
        "status",
        "priority",
        "public_status"
    ];

    if (!in_array($column, $allowedColumns, true)) {
        return [];
    }

    $result = mysqli_query(
        $conn,
        "SHOW COLUMNS FROM `warden_complaints`
         LIKE '" . mysqli_real_escape_string($conn, $column) . "'"
    );

    if (!$result) {
        return [];
    }

    $row = mysqli_fetch_assoc($result);

    mysqli_free_result($result);

    if (!$row || empty($row["Type"])) {
        return [];
    }

    preg_match_all(
        "/'((?:[^'\\\\]|\\\\.)*)'/",
        $row["Type"],
        $matches
    );

    return array_map(
        static function ($value) {
            return stripcslashes($value);
        },
        $matches[1] ?? []
    );
}

function wcReturnUrl($id = 0)
{
    $query = [];

    if ($id > 0) {
        $query["id"] = $id;
    }

    foreach (["search", "status", "priority", "hostel"] as $key) {
        if (isset($_GET[$key]) && is_string($_GET[$key])) {
            $query[$key] = $_GET[$key];
        }
    }

    return "warden_complaints.php"
        . ($query ? "?" . http_build_query($query) : "");
}

/*
|--------------------------------------------------------------------------
| SECURITY TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION["wc_admin_csrf"])) {
    $_SESSION["wc_admin_csrf"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["wc_admin_csrf"];

$error = "";
$success = "";

if (!empty($_SESSION["wc_admin_flash"])) {
    $success = $_SESSION["wc_admin_flash"];
    unset($_SESSION["wc_admin_flash"]);
}

/*
|--------------------------------------------------------------------------
| DATABASE ENUM VALUES
|--------------------------------------------------------------------------
*/

$statusOptions = wcEnumValues($conn, "status");

if (!$statusOptions) {
    $statusOptions = [
        "Submitted",
        "Under Review",
        "Investigation",
        "Resolved",
        "Closed"
    ];
}

$priorityOptions = wcEnumValues($conn, "priority");

if (!$priorityOptions) {
    $priorityOptions = [
        "Normal",
        "High",
        "Urgent"
    ];
}

$publicStatusOptions = wcEnumValues($conn, "public_status");

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim((string)($_GET["search"] ?? ""));
$hostelFilter = (string)($_GET["hostel"] ?? "all");
$statusFilter = (string)($_GET["status"] ?? "all");
$priorityFilter = (string)($_GET["priority"] ?? "all");

if (!in_array($hostelFilter, ["all", "boys", "girls"], true)) {
    $hostelFilter = "all";
}

if (
    $statusFilter !== "all" &&
    !in_array($statusFilter, $statusOptions, true)
) {
    $statusFilter = "all";
}

if (
    $priorityFilter !== "all" &&
    !in_array($priorityFilter, $priorityOptions, true)
) {
    $priorityFilter = "all";
}

$selectedId = max(0, (int)($_GET["id"] ?? 0));

/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedToken = (string)($_POST["csrf_token"] ?? "");

    $complaintId = max(
        0,
        (int)($_POST["complaint_id"] ?? 0)
    );

    $action = (string)($_POST["action"] ?? "");

    if (!hash_equals($csrfToken, $postedToken)) {

        $error = "Security verification failed. Please refresh.";

    } elseif ($complaintId <= 0) {

        $error = "Invalid complaint selected.";

    } else {

        /*
        |--------------------------------------------------------------
        | Fetch complaint before updating
        |--------------------------------------------------------------
        */

        $checkStmt = mysqli_prepare(
            $conn,
            "SELECT id, visibility, public_status, public_summary
             FROM warden_complaints
             WHERE id = ?
             LIMIT 1"
        );

        $currentComplaint = null;

        if ($checkStmt) {

            mysqli_stmt_bind_param(
                $checkStmt,
                "i",
                $complaintId
            );

            mysqli_stmt_execute($checkStmt);

            $checkResult = mysqli_stmt_get_result($checkStmt);

            $currentComplaint = mysqli_fetch_assoc($checkResult);

            mysqli_stmt_close($checkStmt);
        }

        if (!$currentComplaint) {

            $error = "Complaint not found.";

        } elseif ($action === "update_status") {

            /*
            |----------------------------------------------------------
            | Update complaint investigation status
            |----------------------------------------------------------
            */

            $newStatus = trim(
                (string)($_POST["new_status"] ?? "")
            );

            if (!in_array($newStatus, $statusOptions, true)) {

                $error = "Invalid complaint status.";

            } else {

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE warden_complaints
                     SET status = ?
                     WHERE id = ?"
                );

                if ($stmt) {

                    mysqli_stmt_bind_param(
                        $stmt,
                        "si",
                        $newStatus,
                        $complaintId
                    );

                    $saved = mysqli_stmt_execute($stmt);

                    mysqli_stmt_close($stmt);

                    if ($saved) {

                        $_SESSION["wc_admin_flash"] =
                            "Complaint status updated successfully.";

                        header(
                            "Location: " . wcReturnUrl($complaintId)
                        );

                        exit;

                    } else {

                        $error = "Could not update complaint status.";
                    }

                } else {

                    $error = "Could not prepare status update.";
                }
            }

        } elseif (
            $action === "approve_public" ||
            $action === "reject_public"
        ) {

            /*
            |----------------------------------------------------------
            | PUBLIC SHARING APPROVAL
            |----------------------------------------------------------
            */

            $visibility = (string)$currentComplaint["visibility"];

            $publicStatus = (string)$currentComplaint["public_status"];

            $allowedReviewStates = [
                "pending",
                "approved",
                "rejected"
            ];

            if ($visibility !== "anonymous") {

                $error =
                    "This complaint is private. Public sharing cannot be enabled.";

            } elseif (!in_array(
                $publicStatus,
                $allowedReviewStates,
                true
            )) {

                $error =
                    "Public sharing was not requested for this complaint.";

            } elseif ($action === "approve_public") {

                /*
                |------------------------------------------------------
                | Approve only manually reviewed public summary
                |------------------------------------------------------
                */

                $publicSummary = trim(
                    strip_tags(
                        (string)($_POST["public_summary"] ?? "")
                    )
                );

                $reviewConfirmed =
                    ($_POST["privacy_reviewed"] ?? "") === "yes";

                $summaryLength = mb_strlen(
                    $publicSummary,
                    "UTF-8"
                );

                if ($summaryLength < 30) {

                    $error =
                        "Please write a public summary of at least 30 characters.";

                } elseif ($summaryLength > 2000) {

                    $error =
                        "Public summary cannot exceed 2000 characters.";

                } elseif (!$reviewConfirmed) {

                    $error =
                        "Please confirm that all identifying details have been removed.";

                } elseif (!in_array(
                    "approved",
                    $publicStatusOptions,
                    true
                )) {

                    $error =
                        "Database does not support approved public status.";

                } else {

                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE warden_complaints
                         SET public_status = 'approved',
                             public_summary = ?
                         WHERE id = ?
                           AND visibility = 'anonymous'
                           AND public_status IN (
                               'pending',
                               'approved',
                               'rejected'
                           )"
                    );

                    if ($stmt) {

                        mysqli_stmt_bind_param(
                            $stmt,
                            "si",
                            $publicSummary,
                            $complaintId
                        );

                        $saved = mysqli_stmt_execute($stmt);

                        mysqli_stmt_close($stmt);

                        if ($saved) {

                            $_SESSION["wc_admin_flash"] =
                                "Public summary approved successfully.";

                            header(
                                "Location: " . wcReturnUrl($complaintId)
                            );

                            exit;

                        } else {

                            $error =
                                "Could not approve public sharing.";
                        }

                    } else {

                        $error =
                            "Could not prepare approval request.";
                    }
                }

            } else {

                /*
                |------------------------------------------------------
                | Reject public sharing
                | Clear previously approved public summary
                |------------------------------------------------------
                */

                if (!in_array(
                    "rejected",
                    $publicStatusOptions,
                    true
                )) {

                    $error =
                        "Database does not support rejected public status.";

                } else {

                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE warden_complaints
                         SET public_status = 'rejected',
                             public_summary = NULL
                         WHERE id = ?
                           AND visibility = 'anonymous'
                           AND public_status IN (
                               'pending',
                               'approved',
                               'rejected'
                           )"
                    );

                    if ($stmt) {

                        mysqli_stmt_bind_param(
                            $stmt,
                            "i",
                            $complaintId
                        );

                        $saved = mysqli_stmt_execute($stmt);

                        mysqli_stmt_close($stmt);

                        if ($saved) {

                            $_SESSION["wc_admin_flash"] =
                                "Public sharing rejected. Complaint remains confidential.";

                            header(
                                "Location: " . wcReturnUrl($complaintId)
                            );

                            exit;

                        } else {

                            $error =
                                "Could not reject public sharing.";
                        }

                    } else {

                        $error =
                            "Could not prepare rejection request.";
                    }
                }
            }

        } else {

            $error = "Unknown action.";
        }

        $selectedId = $complaintId;
    }
}

/*
|--------------------------------------------------------------------------
| DASHBOARD COUNTS
|--------------------------------------------------------------------------
*/

$stats = [
    "total" => 0,
    "submitted" => 0,
    "urgent" => 0,
    "pending" => 0,
    "approved" => 0
];

$statsResult = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Submitted'), 0) AS submitted,
        COALESCE(SUM(priority = 'Urgent'), 0) AS urgent,
        COALESCE(SUM(
            visibility = 'anonymous'
            AND public_status = 'pending'
        ), 0) AS pending,
        COALESCE(SUM(public_status = 'approved'), 0) AS approved
     FROM warden_complaints"
);

if ($statsResult) {

    $row = mysqli_fetch_assoc($statsResult);

    foreach ($stats as $key => $value) {
        $stats[$key] = (int)($row[$key] ?? 0);
    }

    mysqli_free_result($statsResult);
}

/*
|--------------------------------------------------------------------------
| COMPLAINT LIST WITH FILTERS
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];
$types = "";

if ($hostelFilter !== "all") {

    $where[] = "hostel = ?";
    $params[] = $hostelFilter;
    $types .= "s";
}

if ($statusFilter !== "all") {

    $where[] = "status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if ($priorityFilter !== "all") {

    $where[] = "priority = ?";
    $params[] = $priorityFilter;
    $types .= "s";
}

if ($search !== "") {

    $where[] = "(complaint_code LIKE ? OR title LIKE ?)";

    $pattern = "%" . $search . "%";

    $params[] = $pattern;
    $params[] = $pattern;

    $types .= "ss";
}

$listSql = "
    SELECT
        id,
        complaint_code,
        hostel,
        category,
        title,
        priority,
        status,
        visibility,
        public_status,
        created_at
    FROM warden_complaints
";

if ($where) {
    $listSql .= " WHERE " . implode(" AND ", $where);
}

$listSql .= " ORDER BY created_at DESC, id DESC LIMIT 200";

$complaints = [];

$listStmt = mysqli_prepare($conn, $listSql);

if ($listStmt) {

    if ($params) {
        mysqli_stmt_bind_param(
            $listStmt,
            $types,
            ...$params
        );
    }

    mysqli_stmt_execute($listStmt);

    $listResult = mysqli_stmt_get_result($listStmt);

    while ($row = mysqli_fetch_assoc($listResult)) {
        $complaints[] = $row;
    }

    mysqli_stmt_close($listStmt);
}

/*
|--------------------------------------------------------------------------
| SELECTED COMPLAINT DETAILS
|--------------------------------------------------------------------------
*/

$selectedComplaint = null;

if ($selectedId > 0) {

    $detailStmt = mysqli_prepare(
        $conn,
        "SELECT *
         FROM warden_complaints
         WHERE id = ?
         LIMIT 1"
    );

    if ($detailStmt) {

        mysqli_stmt_bind_param(
            $detailStmt,
            "i",
            $selectedId
        );

        mysqli_stmt_execute($detailStmt);

        $detailResult = mysqli_stmt_get_result($detailStmt);

        $selectedComplaint = mysqli_fetch_assoc($detailResult);

        mysqli_stmt_close($detailStmt);
    }
}

/*
|--------------------------------------------------------------------------
| PRIVATE EVIDENCE RECORDS
|--------------------------------------------------------------------------
*/

$evidenceFiles = [];

if ($selectedComplaint) {

    $tableCheck = mysqli_query(
        $conn,
        "SHOW TABLES LIKE 'warden_complaint_evidence'"
    );

    if ($tableCheck && mysqli_num_rows($tableCheck) > 0) {

        $evidenceStmt = mysqli_prepare(
            $conn,
            "SELECT *
             FROM warden_complaint_evidence
             WHERE complaint_id = ?"
        );

        if ($evidenceStmt) {

            mysqli_stmt_bind_param(
                $evidenceStmt,
                "i",
                $selectedId
            );

            mysqli_stmt_execute($evidenceStmt);

            $evidenceResult = mysqli_stmt_get_result($evidenceStmt);

            while ($file = mysqli_fetch_assoc($evidenceResult)) {
                $evidenceFiles[] = $file;
            }

            mysqli_stmt_close($evidenceStmt);
        }
    }

    if ($tableCheck) {
        mysqli_free_result($tableCheck);
    }
}

/*
|--------------------------------------------------------------------------
| EVIDENCE DOWNLOAD
|--------------------------------------------------------------------------
|
| This path matches the earlier Admin implementation.
| Verify it against the student upload storage directory.
|
*/

if (isset($_GET["download"]) && $selectedComplaint) {

    $downloadId = (int)$_GET["download"];

    $fileRecord = null;

    foreach ($evidenceFiles as $file) {

        if ((int)($file["id"] ?? 0) === $downloadId) {
            $fileRecord = $file;
            break;
        }
    }

    if (!$fileRecord) {
        http_response_code(404);
        exit("Evidence not found.");
    }

    $storedName = basename(
        (string)($fileRecord["stored_filename"] ?? "")
    );

    $privateDir =
        dirname(__DIR__, 3)
        . DIRECTORY_SEPARATOR
        . "unistay_private"
        . DIRECTORY_SEPARATOR
        . "warden_evidence";

    $realDirectory = realpath($privateDir);

    $realFile = $storedName !== ""
        ? realpath($privateDir . DIRECTORY_SEPARATOR . $storedName)
        : false;

    if (
        !$realDirectory ||
        !$realFile ||
        !is_file($realFile) ||
        !is_readable($realFile) ||
        dirname($realFile) !== $realDirectory
    ) {

        http_response_code(404);

        exit(
            "Evidence file not found. Check the private upload directory."
        );
    }

    $originalName = basename(
        (string)(
            $fileRecord["original_filename"]
            ?? "evidence"
        )
    );

    header("Content-Type: application/octet-stream");
    header("X-Content-Type-Options: nosniff");
    header(
        "Content-Disposition: attachment; filename=\"evidence\"; filename*=UTF-8''"
        . rawurlencode($originalName)
    );
    header("Content-Length: " . filesize($realFile));
    header("Cache-Control: private, no-store");

    readfile($realFile);
    exit;
}

$isPublicRequested = $selectedComplaint
    && ($selectedComplaint["visibility"] ?? "") === "anonymous"
    && in_array(
        $selectedComplaint["public_status"] ?? "",
        ["pending", "approved", "rejected"],
        true
    );

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Warden Complaints | UniStay Admin</title>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f5f8f6;
    color: #243c38;
    font-family: "Segoe UI", Arial, sans-serif;
    font-size: 15px;
}

a {
    text-decoration: none;
}

button,
input,
select,
textarea {
    font: inherit;
}

.admin-container {
    display: flex;
    min-height: 100vh;
}

/* ADMIN SIDEBAR */

.admin-container > .sidebar {
    width: 240px;
    min-width: 240px;
    min-height: 100vh;
    padding: 26px 16px;
    background: white;
    border-right: 1px solid #e1eae5;
}

.sidebar .logo {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 35px;
}

.sidebar .logo-box {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 43px;
    height: 43px;
    border-radius: 12px;
    background: #075e56;
    color: white;
    font-size: 23px;
    font-weight: 800;
}

.sidebar .logo h2 {
    margin: 0;
    color: #163a35;
    font-size: 23px;
}

.sidebar .menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px;
    margin-bottom: 7px;
    border-radius: 10px;
    color: #526560;
    font-size: 14px;
    font-weight: 600;
}

.sidebar .menu a i {
    width: 20px;
    text-align: center;
}

.sidebar .menu a:hover,
.sidebar .menu a.active {
    background: #e5f4ed;
    color: #075e56;
}

.main-content {
    flex: 1;
    min-width: 0;
    padding: 32px;
}

.page-header {
    margin-bottom: 27px;
}

.page-header .eyebrow {
    margin-bottom: 7px;
    color: #148572;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1px;
}

.page-header h1 {
    margin: 0 0 9px;
    font-size: 34px;
    letter-spacing: -1px;
}

.page-header p {
    margin: 0;
    color: #748580;
    line-height: 1.7;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    padding: 22px;
    border: 1px solid #e1eae5;
    border-radius: 16px;
    background: white;
}

.stat-icon {
    color: #087164;
    font-size: 21px;
}

.stat-number {
    margin-top: 13px;
    font-size: 30px;
    font-weight: 800;
}

.stat-label {
    margin-top: 5px;
    color: #778781;
    font-size: 12px;
    font-weight: 700;
}

.panel {
    overflow: hidden;
    margin-bottom: 25px;
    border: 1px solid #e1eae5;
    border-radius: 18px;
    background: white;
}

.panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 22px 24px;
    border-bottom: 1px solid #e6eee9;
}

.panel-header h2 {
    margin: 0;
    font-size: 20px;
}

.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 11px;
    padding: 20px 24px;
    border-bottom: 1px solid #e6eee9;
}

.filters input,
.filters select,
.form-select,
.summary-input {
    padding: 12px 13px;
    border: 1px solid #d7e4dc;
    border-radius: 10px;
    background: white;
    color: #243c38;
    outline: none;
}

.filters input {
    flex: 1;
    min-width: 180px;
}

.filters input:focus,
.filters select:focus,
.form-select:focus,
.summary-input:focus {
    border-color: #168b78;
    box-shadow: 0 0 0 3px #168b7817;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 12px 17px;
    border: 0;
    border-radius: 10px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 800;
}

.btn-green {
    background: #075e56;
    color: white;
}

.btn-green:hover {
    background: #064b45;
}

.btn-red {
    background: #fff0f0;
    color: #bb3444;
}

.btn-red:hover {
    background: #ffe2e4;
}

.btn-light {
    background: #e9f5ee;
    color: #075e56;
}

.btn-light:hover {
    background: #d9eee1;
}

.table-scroll {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

th {
    padding: 15px;
    background: #f7faf8;
    color: #74847d;
    font-size: 12px;
    text-transform: uppercase;
    white-space: nowrap;
}

td {
    padding: 16px 15px;
    border-top: 1px solid #eef2ef;
    font-size: 13px;
    vertical-align: middle;
}

td.title-cell {
    min-width: 180px;
    font-weight: 700;
}

.tracking {
    color: #087164;
    font-weight: 800;
    white-space: nowrap;
}

.badge {
    display: inline-block;
    padding: 7px 10px;
    border-radius: 25px;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.badge.green {
    background: #e0f5e9;
    color: #19774c;
}

.badge.red {
    background: #ffe8ea;
    color: #ba3d4b;
}

.badge.orange {
    background: #fff1dc;
    color: #a66a1d;
}

.badge.blue {
    background: #e8f1ff;
    color: #3666a6;
}

.badge.gray {
    background: #edf1ef;
    color: #65766e;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
    padding: 24px;
}

.detail-box {
    padding: 17px;
    border: 1px solid #e4ece6;
    border-radius: 12px;
    background: #fcfdfc;
}

.detail-box.full {
    grid-column: 1 / -1;
}

.detail-label {
    margin-bottom: 9px;
    color: #7a8982;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

.detail-value {
    line-height: 1.8;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
}

.section-content {
    padding: 24px;
}

.section-content h3 {
    margin: 0 0 10px;
    font-size: 19px;
}

.section-content p {
    color: #6c8076;
    line-height: 1.8;
}

.section-content + .section-content {
    border-top: 1px solid #e5ede7;
}

.update-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.summary-input {
    display: block;
    width: 100%;
    min-height: 160px;
    margin: 16px 0;
    resize: vertical;
    line-height: 1.7;
}

.privacy-warning {
    padding: 16px 18px;
    border: 1px solid #efdcb2;
    border-radius: 12px;
    background: #fff9e9;
    color: #82652f;
    line-height: 1.7;
}

.checkbox-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 17px 0;
    line-height: 1.6;
    font-size: 14px;
    font-weight: 600;
}

.checkbox-row input {
    margin-top: 4px;
    accent-color: #075e56;
}

.action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.alert {
    margin-bottom: 20px;
    padding: 16px 20px;
    border-radius: 12px;
    font-weight: 700;
}

.alert.success {
    background: #e3f5e9;
    color: #19774c;
}

.alert.error {
    background: #ffebec;
    color: #af3442;
}

.empty-state {
    padding: 35px;
    color: #819087;
    text-align: center;
}

.evidence-item {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    margin: 6px 8px 6px 0;
    padding: 11px 14px;
    border-radius: 10px;
    background: #edf6f0;
    color: #075e56;
    font-size: 13px;
    font-weight: 700;
}

@media (max-width: 1250px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 900px) {
    .admin-container {
        display: block;
    }

    .admin-container > .sidebar {
        width: 100%;
        min-height: auto;
    }

    .main-content {
        padding: 20px;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .details-grid {
        grid-template-columns: 1fr;
        padding: 16px;
    }

    .detail-box.full {
        grid-column: auto;
    }

    .filters {
        padding: 16px;
    }

    .filters input,
    .filters select,
    .filters .btn {
        width: 100%;
    }

    .section-content {
        padding: 18px;
    }

    .page-header h1 {
        font-size: 28px;
    }
}

</style>

</head>

<body>

<div class="admin-container">

    <?php
    /*
     * Existing Admin Sidebar
     */
    if (file_exists(__DIR__ . "/sidebar.php")) {
        include __DIR__ . "/sidebar.php";
    }
    ?>

    <main class="main-content">

        <div class="page-header">

            <div class="eyebrow">
                UNISTAY ADMIN MANAGEMENT
            </div>

            <h1>Warden Complaints</h1>

            <p>
                Review confidential student complaints,
                update their status and control anonymous
                public sharing.
            </p>

        </div>

        <?php if ($error !== ""): ?>

            <div class="alert error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?php echo wcEscape($error); ?>
            </div>

        <?php endif; ?>

        <?php if ($success !== ""): ?>

            <div class="alert success">
                <i class="fa-solid fa-circle-check"></i>
                <?php echo wcEscape($success); ?>
            </div>

        <?php endif; ?>

        <!-- STATISTICS -->

        <div class="stats-grid">

            <div class="stat-card">
                <i class="fa-solid fa-clipboard-list stat-icon"></i>
                <div class="stat-number">
                    <?php echo $stats["total"]; ?>
                </div>
                <div class="stat-label">
                    Total Complaints
                </div>
            </div>

            <div class="stat-card">
                <i class="fa-solid fa-inbox stat-icon"></i>
                <div class="stat-number">
                    <?php echo $stats["submitted"]; ?>
                </div>
                <div class="stat-label">
                    Submitted
                </div>
            </div>

            <div class="stat-card">
                <i class="fa-solid fa-triangle-exclamation stat-icon"></i>
                <div class="stat-number">
                    <?php echo $stats["urgent"]; ?>
                </div>
                <div class="stat-label">
                    Urgent Complaints
                </div>
            </div>

            <div class="stat-card">
                <i class="fa-solid fa-clock stat-icon"></i>
                <div class="stat-number">
                    <?php echo $stats["pending"]; ?>
                </div>
                <div class="stat-label">
                    Pending Public Approval
                </div>
            </div>

            <div class="stat-card">
                <i class="fa-solid fa-check-double stat-icon"></i>
                <div class="stat-number">
                    <?php echo $stats["approved"]; ?>
                </div>
                <div class="stat-label">
                    Approved Public Summaries
                </div>
            </div>

        </div>

        <!-- COMPLAINT LIST -->

        <section class="panel">

            <div class="panel-header">
                <h2>All Warden Complaints</h2>
            </div>

            <form method="GET" class="filters">

                <input
                    type="text"
                    name="search"
                    placeholder="Search tracking ID or title..."
                    value="<?php echo wcEscape($search); ?>"
                >

                <select name="hostel">

                    <option value="all">
                        All Hostels
                    </option>

                    <option
                        value="boys"
                        <?php echo $hostelFilter === "boys" ? "selected" : ""; ?>
                    >
                        Boys Hostel
                    </option>

                    <option
                        value="girls"
                        <?php echo $hostelFilter === "girls" ? "selected" : ""; ?>
                    >
                        Girls Hostel
                    </option>

                </select>

                <select name="status">

                    <option value="all">
                        All Statuses
                    </option>

                    <?php foreach ($statusOptions as $status): ?>

                        <option
                            value="<?php echo wcEscape($status); ?>"
                            <?php echo $statusFilter === $status ? "selected" : ""; ?>
                        >
                            <?php echo wcEscape($status); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <select name="priority">

                    <option value="all">
                        All Priorities
                    </option>

                    <?php foreach ($priorityOptions as $priority): ?>

                        <option
                            value="<?php echo wcEscape($priority); ?>"
                            <?php echo $priorityFilter === $priority ? "selected" : ""; ?>
                        >
                            <?php echo wcEscape($priority); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <button class="btn btn-green" type="submit">
                    <i class="fa-solid fa-filter"></i>
                    Filter
                </button>

            </form>

            <div class="table-scroll">

                <table>

                    <thead>
                        <tr>
                            <th>Tracking ID</th>
                            <th>Hostel</th>
                            <th>Title</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Public Sharing</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (!$complaints): ?>

                        <tr>
                            <td colspan="7" class="empty-state">
                                No Warden Complaints found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($complaints as $complaint): ?>

                            <tr>

                                <td class="tracking">
                                    <?php echo wcEscape($complaint["complaint_code"]); ?>
                                </td>

                                <td>
                                    <?php echo wcEscape(ucfirst($complaint["hostel"])); ?>
                                </td>

                                <td class="title-cell">
                                    <?php echo wcEscape($complaint["title"]); ?>
                                </td>

                                <td>
                                    <span class="badge <?php echo wcBadge($complaint["priority"]); ?>">
                                        <?php echo wcEscape($complaint["priority"]); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge <?php echo wcBadge($complaint["status"]); ?>">
                                        <?php echo wcEscape($complaint["status"]); ?>
                                    </span>
                                </td>

                                <td>

                                    <?php
                                    $publicStatus =
                                        $complaint["public_status"];

                                    if ($complaint["visibility"] === "private") {
                                        $publicStatus = "Private";
                                    }
                                    ?>

                                    <span class="badge <?php echo wcBadge($publicStatus); ?>">
                                        <?php echo wcEscape(
                                            ucwords(
                                                str_replace(
                                                    "_",
                                                    " ",
                                                    $publicStatus
                                                )
                                            )
                                        ); ?>
                                    </span>

                                </td>

                                <td>

                                    <?php
                                    $viewQuery = http_build_query([
                                        "id" => $complaint["id"],
                                        "search" => $search,
                                        "hostel" => $hostelFilter,
                                        "status" => $statusFilter,
                                        "priority" => $priorityFilter
                                    ]);
                                    ?>

                                    <a
                                        href="warden_complaints.php?<?php echo wcEscape($viewQuery); ?>"
                                        class="btn btn-light"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                        View
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

        <!-- SELECTED COMPLAINT -->

        <?php if ($selectedComplaint): ?>

            <section class="panel" id="complaint-details">

                <div class="panel-header">

                    <h2>
                        Complaint Details:
                        <?php echo wcEscape(
                            $selectedComplaint["complaint_code"]
                        ); ?>
                    </h2>

                    <a
                        href="warden_complaints.php"
                        class="btn btn-light"
                    >
                        <i class="fa-solid fa-xmark"></i>
                        Close
                    </a>

                </div>

                <div class="details-grid">

                    <div class="detail-box">
                        <div class="detail-label">Hostel</div>
                        <div class="detail-value">
                            <?php echo wcEscape(
                                ucfirst($selectedComplaint["hostel"])
                            ); ?>
                        </div>
                    </div>

                    <div class="detail-box">
                        <div class="detail-label">Category</div>
                        <div class="detail-value">
                            <?php echo wcEscape(
                                $selectedComplaint["category"]
                            ); ?>
                        </div>
                    </div>

                    <div class="detail-box">
                        <div class="detail-label">Priority</div>
                        <div class="detail-value">
                            <?php echo wcEscape(
                                $selectedComplaint["priority"]
                            ); ?>
                        </div>
                    </div>

                    <div class="detail-box">
                        <div class="detail-label">Complaint Status</div>
                        <div class="detail-value">
                            <?php echo wcEscape(
                                $selectedComplaint["status"]
                            ); ?>
                        </div>
                    </div>

                    <div class="detail-box full">
                        <div class="detail-label">Complaint Title</div>
                        <div class="detail-value">
                            <?php echo wcEscape(
                                $selectedComplaint["title"]
                            ); ?>
                        </div>
                    </div>

                    <div class="detail-box full">
                        <div class="detail-label">Full Complaint Description</div>
                        <div class="detail-value">
                            <?php echo wcEscape(
                                $selectedComplaint["description"]
                            ); ?>
                        </div>
                    </div>

                    <div class="detail-box">
                        <div class="detail-label">Incident Date</div>
                        <div class="detail-value">
                            <?php echo wcEscape(
                                $selectedComplaint["incident_date"] ?? "Not specified"
                            ); ?>
                        </div>
                    </div>

                    <div class="detail-box">
                        <div class="detail-label">Incident Location</div>
                        <div class="detail-value">
                            <?php echo wcEscape(
                                $selectedComplaint["incident_location"]
                                ?: "Not specified"
                            ); ?>
                        </div>
                    </div>

                    <div class="detail-box">
                        <div class="detail-label">Privacy Preference</div>
                        <div class="detail-value">
                            <?php echo
                                $selectedComplaint["visibility"] === "anonymous"
                                    ? "Anonymous Public Sharing Requested"
                                    : "Private Complaint";
                            ?>
                        </div>
                    </div>

                    <div class="detail-box">
                        <div class="detail-label">Public Approval Status</div>
                        <div class="detail-value">

                            <span class="badge <?php echo wcBadge(
                                $selectedComplaint["public_status"]
                            ); ?>">
                                <?php echo wcEscape(
                                    ucwords(
                                        str_replace(
                                            "_",
                                            " ",
                                            $selectedComplaint["public_status"]
                                        )
                                    )
                                ); ?>
                            </span>

                        </div>
                    </div>

                    <div class="detail-box">
                        <div class="detail-label">Submitted On</div>
                        <div class="detail-value">
                            <?php echo wcEscape(
                                wcDate($selectedComplaint["created_at"])
                            ); ?>
                        </div>
                    </div>

                    <div class="detail-box">
                        <div class="detail-label">Last Updated</div>
                        <div class="detail-value">
                            <?php echo wcEscape(
                                wcDate($selectedComplaint["updated_at"])
                            ); ?>
                        </div>
                    </div>

                </div>

                <!-- EVIDENCE -->

                <?php if ($evidenceFiles): ?>

                    <div class="section-content">

                        <h3>
                            <i class="fa-solid fa-paperclip"></i>
                            Complaint Evidence
                        </h3>

                        <p>
                            Evidence is confidential and available
                            to authorized Admin only.
                        </p>

                        <?php foreach ($evidenceFiles as $file): ?>

                            <?php
                            $fileName =
                                $file["original_filename"]
                                ?? "Evidence File";
                            ?>

                            <a
                                class="evidence-item"
                                href="warden_complaints.php?id=<?php echo (int)$selectedId; ?>&download=<?php echo (int)$file["id"]; ?>"
                            >
                                <i class="fa-solid fa-download"></i>
                                <?php echo wcEscape($fileName); ?>
                            </a>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

                <!-- STATUS UPDATE -->

                <div class="section-content">

                    <h3>Update Complaint Status</h3>

                    <p>
                        Use this to manage the complaint investigation.
                        It does not control public sharing approval.
                    </p>

                    <form method="POST" class="update-row">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo wcEscape($csrfToken); ?>"
                        >

                        <input
                            type="hidden"
                            name="complaint_id"
                            value="<?php echo (int)$selectedComplaint["id"]; ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="update_status"
                        >

                        <select
                            name="new_status"
                            class="form-select"
                            required
                        >

                            <?php foreach ($statusOptions as $status): ?>

                                <option
                                    value="<?php echo wcEscape($status); ?>"
                                    <?php echo
                                        $selectedComplaint["status"] === $status
                                            ? "selected"
                                            : "";
                                    ?>
                                >
                                    <?php echo wcEscape($status); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <button
                            type="submit"
                            class="btn btn-green"
                        >
                            <i class="fa-solid fa-floppy-disk"></i>
                            Save Status
                        </button>

                    </form>

                </div>

                <!-- PUBLIC APPROVAL -->

                <div class="section-content">

                    <h3>
                        <i class="fa-solid fa-shield-halved"></i>
                        Public Sharing Approval
                    </h3>

                    <?php if (!$isPublicRequested): ?>

                        <div class="privacy-warning">

                            <i class="fa-solid fa-lock"></i>

                            <strong>
                                Public sharing is not available.
                            </strong>

                            <p>
                                This complaint is private or the student
                                has not requested anonymous public sharing.
                                Admin cannot publish it without the
                                student's sharing request.
                            </p>

                        </div>

                    <?php else: ?>

                        <p>
                            The student requested anonymous sharing.
                            You can approve a carefully reviewed summary
                            or reject the request.
                        </p>

                        <div style="margin-bottom:18px">

                            <strong>Current Approval Status:</strong>

                            <span class="badge <?php echo wcBadge(
                                $selectedComplaint["public_status"]
                            ); ?>">
                                <?php echo wcEscape(
                                    ucfirst(
                                        $selectedComplaint["public_status"]
                                    )
                                ); ?>
                            </span>

                        </div>

                        <div class="privacy-warning">

                            <strong>
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                Privacy Check Required
                            </strong>

                            <p>
                                Before approval, remove student names,
                                room numbers, phone numbers, email addresses,
                                exact identifying incident details and
                                other sensitive information.
                            </p>

                            <p>
                                Even without names, a complaint may identify
                                someone through its circumstances.
                                Only approve a summary that is safe to share.
                            </p>

                        </div>

                        <form
                            method="POST"
                            id="public-approval-form"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo wcEscape($csrfToken); ?>"
                            >

                            <input
                                type="hidden"
                                name="complaint_id"
                                value="<?php echo (int)$selectedComplaint["id"]; ?>"
                            >

                            <label
                                for="public_summary"
                                style="display:block;margin-top:22px;font-weight:800"
                            >
                                Public Summary for Students
                            </label>

                            <p>
                                Write only the information that students
                                of this hostel should be allowed to see.
                                The original complaint will remain private.
                            </p>

                            <textarea
                                id="public_summary"
                                name="public_summary"
                                class="summary-input"
                                minlength="30"
                                maxlength="2000"
                                placeholder="Example: A student raised a concern about unfair treatment by hostel staff. The matter has been forwarded for administrative review."
                            ><?php echo wcEscape(
                                $_SERVER["REQUEST_METHOD"] === "POST"
                                && (int)($_POST["complaint_id"] ?? 0) === $selectedId
                                && ($_POST["action"] ?? "") === "approve_public"
                                    ? ($_POST["public_summary"] ?? "")
                                    : ($selectedComplaint["public_summary"] ?? "")
                            ); ?></textarea>

                            <label class="checkbox-row">

                                <input
                                    type="checkbox"
                                    name="privacy_reviewed"
                                    value="yes"
                                    id="privacy_reviewed"
                                >

                                <span>
                                    I have manually reviewed the public
                                    summary and removed personal,
                                    identifying and sensitive information.
                                </span>

                            </label>

                            <div class="action-buttons">

                                <button
                                    type="submit"
                                    name="action"
                                    value="approve_public"
                                    class="btn btn-green"
                                    onclick="return validatePublicApproval();"
                                >
                                    <i class="fa-solid fa-circle-check"></i>
                                    Approve Public Sharing
                                </button>

                                <button
                                    type="submit"
                                    name="action"
                                    value="reject_public"
                                    class="btn btn-red"
                                    formnovalidate
                                    onclick="return confirm('Reject public sharing for this complaint? The complaint itself will remain saved.');"
                                >
                                    <i class="fa-solid fa-circle-xmark"></i>
                                    Reject Public Sharing
                                </button>

                            </div>

                        </form>

                    <?php endif; ?>

                </div>

            </section>

        <?php endif; ?>

    </main>

</div>

<script>

function validatePublicApproval() {

    const summary = document.getElementById("public_summary");
    const reviewed = document.getElementById("privacy_reviewed");

    if (!summary || !reviewed) {
        return false;
    }

    const text = summary.value.trim();

    if (text.length < 30) {

        alert(
            "Please write a public summary of at least 30 characters."
        );

        summary.focus();

        return false;
    }

    if (!reviewed.checked) {

        alert(
            "Please confirm that you reviewed and removed identifying details."
        );

        return false;
    }

    return confirm(
        "Approve this summary for anonymous sharing with students of the same hostel?"
    );
}

</script>

</body>
</html>

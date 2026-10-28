
<?php
session_start();
require_once "../db.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["user_role"] ?? "") !== "admin"
) {
    header("Location: ../index.php");
    exit;
}

$adminName = $_SESSION["user_name"] ?? "Admin";
$message = "";
$messageType = "";

if (empty($_SESSION["add_user_csrf"])) {
    $_SESSION["add_user_csrf"] = bin2hex(random_bytes(32));
}

function clean($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function validateUser($name, $email, $password, $role, $hostel) {
    if (
        $name === "" ||
        $email === "" ||
        $password === "" ||
        $role === "" ||
        $hostel === ""
    ) {
        return "Please complete all required fields.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Please enter a valid email address.";
    }

    if (strlen($password) < 6) {
        return "Password must contain at least 6 characters.";
    }

    if (!in_array($role, ["student", "warden"], true)) {
        return "Only Student and Warden accounts can be created.";
    }

    if (!in_array($hostel, ["boys", "girls"], true)) {
        return "Please select a valid hostel.";
    }

    return "";
}

function createUser($conn, $name, $email, $password, $role, $hostel) {
    $check = mysqli_prepare(
        $conn,
        "SELECT id FROM users WHERE email = ? LIMIT 1"
    );

    if (!$check) {
        return "Unable to verify the email address.";
    }

    mysqli_stmt_bind_param($check, "s", $email);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);

    $exists = mysqli_stmt_num_rows($check) > 0;
    mysqli_stmt_close($check);

    if ($exists) {
        return "This email address is already registered.";
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO users (name, email, password, role, hostel)
         VALUES (?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        return "Unable to prepare the account creation request.";
    }

    mysqli_stmt_bind_param(
        $stmt,
        "sssss",
        $name,
        $email,
        $hashedPassword,
        $role,
        $hostel
    );

    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $success ? "" : "Unable to create the account.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["add_user_csrf"], $token)
    ) {
        $message = "Invalid request. Please refresh the page.";
        $messageType = "error";

    } elseif (($_POST["action"] ?? "") === "single") {
        $name = trim($_POST["name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";
        $role = $_POST["role"] ?? "";
        $hostel = $_POST["hostel"] ?? "";

        $error = validateUser(
            $name,
            $email,
            $password,
            $role,
            $hostel
        );

        if ($error === "") {
            $error = createUser(
                $conn,
                $name,
                $email,
                $password,
                $role,
                $hostel
            );
        }

        $message = $error ?: "User account created successfully.";
        $messageType = $error ? "error" : "success";

    } elseif (($_POST["action"] ?? "") === "bulk") {
        $file = $_FILES["csv_file"] ?? null;

        if (
            !$file ||
            $file["error"] !== UPLOAD_ERR_OK ||
            $file["size"] === 0 ||
            $file["size"] > 2 * 1024 * 1024
        ) {
            $message = "Please upload a valid CSV file smaller than 2 MB.";
            $messageType = "error";

        } elseif (
            strtolower(pathinfo($file["name"], PATHINFO_EXTENSION))
            !== "csv"
        ) {
            $message = "Only CSV files are supported.";
            $messageType = "error";

        } else {
            $handle = fopen($file["tmp_name"], "r");

            if (!$handle) {
                $message = "Unable to read the uploaded CSV file.";
                $messageType = "error";

            } else {
                $header = fgetcsv($handle);

                if ($header && isset($header[0])) {
                    $header[0] = preg_replace(
                        '/^\xEF\xBB\xBF/',
                        "",
                        $header[0]
                    );
                }

                $header = array_map(
                    fn($value) => strtolower(trim($value)),
                    $header ?: []
                );

                $expected = [
                    "name",
                    "email",
                    "password",
                    "role",
                    "hostel"
                ];

                if ($header !== $expected) {
                    $message =
                        "Invalid CSV headers. Required format: " .
                        "name,email,password,role,hostel";
                    $messageType = "error";

                } else {
                    $created = 0;
                    $skipped = 0;
                    $errors = [];
                    $processed = 0;
                    $line = 1;
                    $limitReached = false;

                    while (($row = fgetcsv($handle)) !== false) {
                        $line++;

                        if (
                            count($row) === 1 &&
                            trim($row[0]) === ""
                        ) {
                            continue;
                        }

                        if ($processed >= 500) {
                            $limitReached = true;
                            break;
                        }

                        $processed++;

                        if (count($row) !== 5) {
                            $error = "Expected five columns.";
                        } else {
                            [
                                $name,
                                $email,
                                $password,
                                $role,
                                $hostel
                            ] = array_map("trim", $row);

                            $role = strtolower($role);
                            $hostel = strtolower($hostel);

                            $error = validateUser(
                                $name,
                                $email,
                                $password,
                                $role,
                                $hostel
                            );

                            if ($error === "") {
                                $error = createUser(
                                    $conn,
                                    $name,
                                    $email,
                                    $password,
                                    $role,
                                    $hostel
                                );
                            }
                        }

                        if ($error === "") {
                            $created++;
                        } else {
                            $skipped++;

                            if (count($errors) < 5) {
                                $errors[] = "Row $line: $error";
                            }
                        }
                    }

                    $message =
                        "$created account(s) created. " .
                        "$skipped row(s) skipped.";

                    if ($errors) {
                        $message .= " " . implode(" | ", $errors);
                    }

                    if ($limitReached) {
                        $message .=
                            " Only the first 500 records were processed.";
                    }

                    $messageType =
                        $skipped > 0 ? "error" : "success";
                }

                fclose($handle);
            }
        }
    }
}

function countUsers($conn, $role = null) {
    if ($role === null) {
        $result = mysqli_query(
            $conn,
            "SELECT COUNT(*) AS total FROM users"
        );

        return (int)mysqli_fetch_assoc($result)["total"];
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total FROM users WHERE role = ?"
    );

    mysqli_stmt_bind_param($stmt, "s", $role);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $count = (int)mysqli_fetch_assoc($result)["total"];

    mysqli_stmt_close($stmt);

    return $count;
}

$totalUsers = countUsers($conn);
$totalStudents = countUsers($conn, "student");
$totalWardens = countUsers($conn, "warden");

function getInitial($name) {
    return strtoupper(substr(trim($name), 0, 1));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Add User - UniStay</title>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
>

<style>
:root {
    --navy: #1F2747;
    --deep-navy: #14354F;
    --teal: #0E6077;
    --teal-2: #168295;
    --teal-soft: #E2F1F3;
    --mustard: #E8B23D;
    --mustard-soft: #FFF2D3;
    --background: #EFF4F7;
    --border: #DFE7EC;
    --text: #1E2639;
    --muted: #718092;
    --shadow: 0 8px 24px rgba(31,39,71,0.06);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: "Segoe UI", Arial, sans-serif;
}

body {
    background: var(--background);
    color: var(--text);
}

.page {
    display: flex;
    min-height: 100vh;
}

/* SIDEBAR */

.sidebar {
    width: 230px;
    min-width: 230px;
    min-height: 100vh;
    position: sticky;
    top: 0;
    align-self: flex-start;
    padding: 30px 18px;
    background: white;
    border-right: 1px solid var(--border);
    z-index: 50;
}

.logo {
    position: relative;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 50px;
    padding-left: 8px;
}

.logo::after {
    content: "Live  Learn  Belong";
    position: absolute;
    left: 61px;
    top: 31px;
    color: #7C8796;
    font-size: 10px;
}

.logo-box {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    color: white;
    background: linear-gradient(135deg, #0C556C, #168295);
    font-size: 21px;
    font-weight: 800;
}

.logo h2 {
    color: #162238;
    font-size: 24px;
    font-weight: 800;
}

.menu a {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 14px;
    margin-bottom: 8px;
    border-radius: 10px;
    color: #45576E;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
}

.menu a i {
    width: 22px;
    text-align: center;
    color: #395875;
    font-size: 15px;
}

.menu a:hover {
    background: #F0F7F9;
    color: var(--teal);
}

.menu a.active {
    background: #DDF0F3;
    color: var(--teal);
    font-weight: 700;
}

.menu a.active i {
    color: var(--teal);
}

/* MAIN CONTENT */

.main {
    flex: 1;
    min-width: 0;
    padding: 28px 32px 50px;
}

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 24px;
}

.page-title {
    display: flex;
    align-items: center;
    gap: 14px;
}

.page-icon {
    width: 50px;
    height: 50px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    color: white;
    background: linear-gradient(135deg, var(--navy), var(--teal));
    font-size: 20px;
}

.topbar h1 {
    font-size: 30px;
}

.topbar p {
    margin-top: 5px;
    color: var(--muted);
    font-size: 14px;
}

.admin-info {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px 8px 8px;
    border-radius: 30px;
    background: white;
    border: 1px solid var(--border);
}

.admin-avatar {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    color: white;
    background: linear-gradient(135deg, var(--navy), var(--teal));
    font-weight: 800;
}

.admin-info strong {
    display: block;
    font-size: 14px;
}

.admin-info span {
    display: block;
    margin-top: 2px;
    color: var(--muted);
    font-size: 11px;
}

/* HERO */

.hero {
    display: grid;
    grid-template-columns: minmax(0,1fr) 330px;
    align-items: center;
    gap: 30px;
    margin-bottom: 20px;
    padding: 32px;
    border-radius: 20px;
    color: white;
    background: linear-gradient(
        120deg, #1F2747, #17475E, #0E6077, #25899A
    );
    box-shadow: 0 13px 30px rgba(18,71,91,0.15);
}

.hero-label {
    color: #F6D77C;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.hero h2 {
    margin-top: 8px;
    font-size: 32px;
    line-height: 1.2;
}

.hero p {
    margin-top: 10px;
    color: rgba(255,255,255,0.85);
    font-size: 14px;
    line-height: 1.6;
}

.hero-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 17px;
    padding: 8px 13px;
    border-radius: 20px;
    color: var(--navy);
    background: var(--mustard);
    font-size: 12px;
    font-weight: 700;
}

.hero-side {
    padding: 20px;
    border-radius: 15px;
    background: rgba(255,255,255,0.11);
    border: 1px solid rgba(255,255,255,0.15);
}

.hero-side h3 {
    font-size: 17px;
    margin-bottom: 16px;
}

.hero-counts {
    display: grid;
    grid-template-columns: repeat(3,1fr);
    gap: 8px;
}

.hero-count {
    text-align: center;
}

.hero-count span {
    display: block;
    color: rgba(255,255,255,0.75);
    font-size: 11px;
}

.hero-count b {
    display: block;
    margin-top: 5px;
    font-size: 22px;
}

/* PANELS */

.content-grid {
    display: grid;
    grid-template-columns: minmax(380px,1fr) minmax(300px,.7fr);
    gap: 20px;
    align-items: start;
}

.panel {
    overflow: hidden;
    margin-bottom: 20px;
    border-radius: 16px;
    background: white;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
}

.panel-head {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px;
    border-bottom: 1px solid #EDF1F3;
}

.panel-icon {
    width: 43px;
    height: 43px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    color: var(--teal);
    background: var(--teal-soft);
    font-size: 17px;
}

.panel-head h2 {
    font-size: 20px;
}

.panel-head p {
    margin-top: 4px;
    color: var(--muted);
    font-size: 13px;
}

/* FORMS */

.user-form {
    padding: 20px;
}

.form-group {
    margin-bottom: 17px;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 7px;
    color: #344557;
    font-size: 13px;
    font-weight: 700;
}

.form-group label i {
    color: var(--teal);
    width: 17px;
    text-align: center;
}

.form-control {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid #D7E1E6;
    border-radius: 9px;
    background: #FBFCFD;
    color: var(--text);
    font-size: 13px;
    outline: none;
}

.form-control:focus {
    border-color: #59A0AE;
    background: white;
    box-shadow: 0 0 0 3px rgba(14,96,119,0.08);
}

.password-wrap {
    position: relative;
}

.password-wrap input {
    padding-right: 45px;
}

.password-toggle {
    position: absolute;
    right: 13px;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: none;
    color: var(--teal);
    cursor: pointer;
}

.form-note {
    display: flex;
    gap: 9px;
    padding: 12px;
    margin-bottom: 17px;
    border-radius: 9px;
    background: linear-gradient(90deg,#EEF7F8,#FFF8E8);
    color: #526474;
    font-size: 12px;
    line-height: 1.6;
}

.submit-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 13px;
    border: none;
    border-radius: 9px;
    background: linear-gradient(135deg,#0D566C,#168295);
    color: white;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.submit-btn:hover {
    background: #0B4B5E;
}

.status-message {
    margin: 16px 20px 0;
    padding: 14px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    overflow-wrap: anywhere;
}

.success {
    color: #17684D;
    background: #E3F5EC;
    border: 1px solid #C8E9D9;
}

.error {
    color: #A64048;
    background: #FBE8EA;
    border: 1px solid #F3D2D5;
}

/* BULK UPLOAD */

.csv-example {
    padding: 13px;
    margin-bottom: 17px;
    border-radius: 9px;
    background: #F3F7F9;
    border: 1px solid var(--border);
    font-size: 12px;
    overflow-x: auto;
}

.csv-example pre {
    margin-top: 8px;
    font-family: Consolas, monospace;
    line-height: 1.7;
}

/* ROLE INFORMATION */

.role-list {
    padding: 18px;
}

.role-card {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 15px;
    margin-bottom: 13px;
    border-radius: 12px;
}

.role-card:first-child {
    background: #E2F1F3;
}

.role-card:last-child {
    background: #FFF2D3;
    margin-bottom: 0;
}

.role-icon {
    width: 46px;
    height: 46px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    background: white;
    color: var(--teal);
    font-size: 18px;
}

.role-card h3 {
    font-size: 14px;
}

.role-card p {
    margin-top: 4px;
    color: #637383;
    font-size: 12px;
    line-height: 1.5;
}

/* SECURITY */

.security-box {
    padding: 20px;
    border-radius: 16px;
    background: linear-gradient(135deg,var(--navy),var(--deep-navy));
    color: white;
}

.security-box h3 {
    margin-bottom: 8px;
}

.security-box p {
    color: rgba(255,255,255,0.78);
    font-size: 12px;
    line-height: 1.6;
}

/* RESPONSIVE */

@media (max-width: 1050px) {
    .hero {
        grid-template-columns: 1fr;
    }

    .content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 850px) {
    .sidebar {
        width: 205px;
        min-width: 205px;
    }

    .main {
        padding: 22px;
    }
}

@media (max-width: 700px) {
    .sidebar {
        display: none;
    }

    .main {
        padding: 16px;
    }

    .admin-info {
        display: none;
    }

    .hero {
        padding: 23px;
    }

    .hero h2 {
        font-size: 26px;
    }
}
</style>
</head>

<body>

<div class="page">

    <?php include "sidebar.php"; ?>

    <div class="main">

        <div class="topbar">

            <div class="page-title">

                <div class="page-icon">
                    <i class="fa-solid fa-user-plus"></i>
                </div>

                <div>
                    <h1>Add New User</h1>
                    <p>Create Student and Warden accounts.</p>
                </div>

            </div>

            <div class="admin-info">

                <div class="admin-avatar">
                    <?php echo clean(getInitial($adminName)); ?>
                </div>

                <div>
                    <strong><?php echo clean($adminName); ?></strong>
                    <span>System Admin</span>
                </div>

            </div>

        </div>

        <div class="hero">

            <div class="hero-content">

                <div class="hero-label">
                    Account Management
                </div>

                <h2>
                    Create the right account<br>
                    for the right hostel.
                </h2>

                <p>
                    Create Student and Warden accounts,
                    assign Boys or Girls Hostel,
                    and manage multiple users through CSV upload.
                </p>

                <div class="hero-chip">
                    <i class="fa-solid fa-shield-halved"></i>
                    Secure Role-Based Access
                </div>

            </div>

            <div class="hero-side">

                <h3>
                    <i class="fa-solid fa-users-gear"></i>
                    UniStay Accounts
                </h3>

                <div class="hero-counts">

                    <div class="hero-count">
                        <span>Users</span>
                        <b><?php echo $totalUsers; ?></b>
                    </div>

                    <div class="hero-count">
                        <span>Students</span>
                        <b><?php echo $totalStudents; ?></b>
                    </div>

                    <div class="hero-count">
                        <span>Wardens</span>
                        <b><?php echo $totalWardens; ?></b>
                    </div>

                </div>

            </div>

        </div>

        <div class="content-grid">

            <div>

                <!-- SINGLE USER CREATION -->

                <div class="panel">

                    <div class="panel-head">

                        <div class="panel-icon">
                            <i class="fa-solid fa-user-plus"></i>
                        </div>

                        <div>
                            <h2>Create User Account</h2>
                            <p>Add an individual Student or Warden.</p>
                        </div>

                    </div>

                    <?php if ($message !== ""): ?>

                        <div class="status-message <?php
                            echo clean($messageType);
                        ?>">
                            <?php echo clean($message); ?>
                        </div>

                    <?php endif; ?>

                    <form method="POST" class="user-form">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php
                                echo clean($_SESSION["add_user_csrf"]);
                            ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="single"
                        >

                        <div class="form-group">

                            <label>
                                <i class="fa-solid fa-user"></i>
                                Full Name
                            </label>

                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                placeholder="Enter full name"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label>
                                <i class="fa-solid fa-envelope"></i>
                                Email Address
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                placeholder="Enter email address"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label>
                                <i class="fa-solid fa-lock"></i>
                                Password
                            </label>

                            <div class="password-wrap">

                                <input
                                    type="password"
                                    name="password"
                                    id="password"
                                    class="form-control"
                                    placeholder="Create a password"
                                    minlength="6"
                                    required
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    onclick="togglePassword()"
                                    aria-label="Show or hide password"
                                >
                                    <i
                                        class="fa-solid fa-eye"
                                        id="passwordIcon"
                                    ></i>
                                </button>

                            </div>

                        </div>

                        <div class="form-group">

                            <label>
                                <i class="fa-solid fa-user-tag"></i>
                                Select Role
                            </label>

                            <select
                                name="role"
                                class="form-control"
                                required
                            >
                                <option value="">Select Role</option>
                                <option value="student">Student</option>
                                <option value="warden">Warden</option>
                            </select>

                        </div>

                        <div class="form-group">

                            <label>
                                <i class="fa-solid fa-building"></i>
                                Select Hostel
                            </label>

                            <select
                                name="hostel"
                                class="form-control"
                                required
                            >
                                <option value="">Select Hostel</option>
                                <option value="boys">Boys Hostel</option>
                                <option value="girls">Girls Hostel</option>
                            </select>

                        </div>

                        <div class="form-note">

                            <i class="fa-solid fa-circle-info"></i>

                            <div>
                                The Admin creates user accounts
                                and assigns hostels. Room allocation
                                is managed by the respective Warden.
                            </div>

                        </div>

                        <button type="submit" class="submit-btn">
                            <i class="fa-solid fa-user-plus"></i>
                            Create Account
                        </button>

                    </form>

                </div>

                <!-- BULK USER UPLOAD -->

                <div class="panel">

                    <div class="panel-head">

                        <div class="panel-icon">
                            <i class="fa-solid fa-file-csv"></i>
                        </div>

                        <div>
                            <h2>Bulk User Upload</h2>
                            <p>
                                Add up to 500 users at once
                                using a CSV file.
                            </p>
                        </div>

                    </div>

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                        class="user-form"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php
                                echo clean($_SESSION["add_user_csrf"]);
                            ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="bulk"
                        >

                        <div class="form-group">

                            <label>
                                <i class="fa-solid fa-file-arrow-up"></i>
                                Upload CSV File
                            </label>

                            <input
                                type="file"
                                name="csv_file"
                                class="form-control"
                                accept=".csv,text/csv"
                                required
                            >

                        </div>

                        <div class="csv-example">

                            <strong>Required CSV Format:</strong>

                            <pre>name,email,password,role,hostel
Aman,aman@example.com,Pass123,student,boys
Simran,simran@example.com,Pass456,student,girls
Ravi,ravi@example.com,Pass789,warden,boys</pre>

                        </div>

                        <div class="form-note">

                            <i class="fa-solid fa-circle-info"></i>

                            <div>
                                Create the required columns in Excel
                                and save the file in CSV format.
                                Duplicate email addresses and
                                invalid records will be skipped.
                            </div>

                        </div>

                        <button type="submit" class="submit-btn">
                            <i class="fa-solid fa-upload"></i>
                            Upload Users
                        </button>

                    </form>

                </div>

            </div>

            <!-- USER ROLE INFORMATION -->

            <div>

                <div class="panel">

                    <div class="panel-head">

                        <div class="panel-icon">
                            <i class="fa-solid fa-users"></i>
                        </div>

                        <div>
                            <h2>User Roles</h2>
                            <p>Available account types and permissions.</p>
                        </div>

                    </div>

                    <div class="role-list">

                        <div class="role-card">

                            <div class="role-icon">
                                <i class="fa-solid fa-user-graduate"></i>
                            </div>

                            <div>
                                <h3>Student</h3>
                                <p>
                                    View hostel information,
                                    submit complaints, apply for leave,
                                    and request room changes.
                                </p>
                            </div>

                        </div>

                        <div class="role-card">

                            <div class="role-icon">
                                <i class="fa-solid fa-user-shield"></i>
                            </div>

                            <div>
                                <h3>Warden</h3>
                                <p>
                                    Manage students, room allocation,
                                    complaints, leave requests,
                                    and hostel notices.
                                </p>
                            </div>

                        </div>

                    </div>

                </div>

                <div class="security-box">

                    <h3>
                        <i class="fa-solid fa-lock"></i>
                        Secure Account Creation
                    </h3>

                    <p>
                        Passwords are securely hashed before storage.
                        Only the existing Admin can create Student
                        and Warden accounts. Additional Admin accounts
                        cannot be created through this page.
                    </p>

                </div>

            </div>

        </div>

    </div>

</div>

<script>
function togglePassword() {
    const input = document.getElementById("password");
    const icon = document.getElementById("passwordIcon");

    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}
</script>

</body>
</html>

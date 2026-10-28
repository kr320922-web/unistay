<?php

session_start();
require_once "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {

        $message = "Please enter your email and password.";

    } else {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, name, password, role
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        if ($stmt) {

            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if (
                $user &&
                password_verify($password, $user["password"])
            ) {

                if (
                    in_array(
                        $user["role"],
                        ["student", "warden", "admin"],
                        true
                    )
                ) {

                    session_regenerate_id(true);

                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["user_name"] = $user["name"];
                    $_SESSION["user_role"] = $user["role"];

                    if ($user["role"] === "student") {

                        header("Location: student/dashboard.php");
                        exit;

                    } elseif ($user["role"] === "warden") {

                        header("Location: warden/dashboard.php");
                        exit;

                    } elseif ($user["role"] === "admin") {

                        header("Location: admin/dashboard.php");
                        exit;
                    }

                } else {

                    $message = "Invalid user role.";
                }

            } else {

                $message = "Invalid email or password.";
            }

        } else {

            $message = "Login is temporarily unavailable. Please try again.";
        }
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

    <title>Login | UniStay</title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <style>

        :root {
            --navy: #192747;
            --navy-dark: #13213d;
            --teal: #075f76;
            --teal-light: #278c9e;
            --gold: #f4b72f;
            --background: #eef3f7;
            --white: #ffffff;
            --text: #192747;
            --muted: #75869d;
            --border: #dfe8ef;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            font-family: "DM Sans", sans-serif;
            background: var(--background);
            color: var(--text);
        }

        button,
        input {
            font: inherit;
        }

        .login-page {
            display: flex;
            min-height: 100vh;
            padding: 22px;
            gap: 22px;
        }

        /* LEFT PANEL */

        .left-panel {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 53%;
            min-height: calc(100vh - 44px);
            padding: 38px 48px;
            overflow: hidden;
            border-radius: 25px;
            background:
                linear-gradient(
                    125deg,
                    #192747 0%,
                    #173b55 46%,
                    #075f76 76%,
                    #278c9e 100%
                );
            color: white;
        }

        .left-panel::before {
            content: "";
            position: absolute;
            width: 420px;
            height: 420px;
            right: -180px;
            top: -160px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.06);
        }

        .left-panel::after {
            content: "";
            position: absolute;
            width: 350px;
            height: 350px;
            left: -140px;
            bottom: -160px;
            border-radius: 50%;
            background: rgba(244, 183, 47, 0.08);
        }

        .logo,
        .left-content,
        .left-footer {
            position: relative;
            z-index: 1;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .logo-box {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 54px;
            height: 54px;
            border-radius: 14px;
            background: linear-gradient(
                135deg,
                #075f76,
                #278c9e
            );
            color: white;
            font-family: "Manrope", sans-serif;
            font-size: 27px;
            font-weight: 800;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.18);
        }

        .logo h2 {
            font-family: "Manrope", sans-serif;
            font-size: 29px;
            font-weight: 800;
            letter-spacing: -1px;
            line-height: 1.2;
        }

        .logo small {
            display: block;
            margin-top: 3px;
            color: #c2d9e4;
            font-size: 12px;
            letter-spacing: 0.4px;
        }

        .left-content {
            max-width: 560px;
            margin: 60px 0;
        }

        .welcome-tag {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 10px 16px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 30px;
            background: rgba(255, 255, 255, 0.10);
            color: #f4b72f;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1.1px;
            text-transform: uppercase;
        }

        .left-content h1 {
            font-family: "Manrope", sans-serif;
            font-size: clamp(40px, 4.2vw, 65px);
            font-weight: 800;
            line-height: 1.16;
            letter-spacing: -2.5px;
        }

        .left-content h1 span {
            color: var(--gold);
        }

        .left-content > p {
            max-width: 480px;
            margin-top: 25px;
            color: #d4e2e9;
            font-size: 16px;
            line-height: 1.9;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
            margin-top: 35px;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 13px;
            min-height: 67px;
            padding: 15px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.09);
            backdrop-filter: blur(8px);
            font-size: 13px;
            font-weight: 700;
        }

        .feature i {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 37px;
            height: 37px;
            flex-shrink: 0;
            border-radius: 10px;
            background: rgba(244, 183, 47, 0.17);
            color: var(--gold);
            font-size: 17px;
        }

        .left-footer {
            display: flex;
            align-items: center;
            gap: 9px;
            color: #d3e3e9;
            font-size: 12px;
            font-weight: 600;
        }

        .left-footer i {
            color: var(--gold);
        }

        /* RIGHT PANEL */

        .right-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 47%;
            padding: 35px 25px;
        }

        .login-card {
            width: 100%;
            max-width: 475px;
            padding: 45px 40px;
            border: 1px solid #e3eaf0;
            border-radius: 24px;
            background: var(--white);
            box-shadow: 0 18px 55px rgba(25, 39, 71, 0.07);
        }

        .form-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            margin-bottom: 25px;
            border-radius: 17px;
            background: #e0f0f4;
            color: var(--teal);
            font-size: 26px;
        }

        .login-card h1 {
            font-family: "Manrope", sans-serif;
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -1.3px;
            line-height: 1.3;
        }

        .subtitle {
            margin-top: 10px;
            margin-bottom: 32px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .form-group {
            margin-bottom: 23px;
        }

        .form-group > label {
            display: block;
            margin-bottom: 10px;
            color: var(--navy);
            font-size: 14px;
            font-weight: 800;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap > i {
            position: absolute;
            top: 50%;
            left: 16px;
            transform: translateY(-50%);
            color: #8296a7;
            font-size: 16px;
            pointer-events: none;
        }

        .input-wrap input {
            width: 100%;
            height: 54px;
            padding: 0 17px 0 47px;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            outline: none;
            background: #f9fbfc;
            color: var(--navy);
            font-size: 14px;
            transition: 0.2s;
        }

        .input-wrap input::placeholder {
            color: #9ba9b7;
        }

        .input-wrap input:focus {
            border-color: var(--teal);
            background: white;
            box-shadow: 0 0 0 4px rgba(7, 95, 118, 0.09);
        }

        .password-box input {
            padding-right: 55px;
        }

        #togglePassword {
            position: absolute;
            top: 50%;
            right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            transform: translateY(-50%);
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: #8296a7;
            cursor: pointer;
        }

        #togglePassword:hover {
            background: #e7f2f5;
            color: var(--teal);
        }

        .login-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 8px 0 27px;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 9px;
            color: #65788e;
            font-size: 13px;
            cursor: pointer;
        }

        .remember input {
            width: 16px;
            height: 16px;
            accent-color: var(--teal);
        }

        .forgot-text {
            color: #8795a5;
            font-size: 13px;
        }

        .login-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 11px;
            width: 100%;
            min-height: 55px;
            padding: 13px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(
                110deg,
                #192747,
                #075f76
            );
            color: white;
            cursor: pointer;
            font-size: 15px;
            font-weight: 800;
            box-shadow: 0 9px 22px rgba(7, 95, 118, 0.18);
            transition: 0.2s;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 13px 27px rgba(7, 95, 118, 0.24);
        }

        .login-btn i {
            color: var(--gold);
        }

        .form-footer {
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .form-footer p {
            color: #8493a5;
            font-size: 13px;
            line-height: 1.8;
        }

        .form-footer strong {
            color: var(--teal);
        }

        .error-message {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 23px;
            padding: 14px 16px;
            border: 1px solid #f1c6c6;
            border-radius: 11px;
            background: #fff1f1;
            color: #b43b3b;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.6;
        }

        .error-message i {
            margin-top: 3px;
        }

        /* RESPONSIVE */

        @media (max-width: 1050px) {

            .left-panel {
                padding: 32px;
            }

            .left-content h1 {
                font-size: 44px;
            }

            .features {
                grid-template-columns: 1fr;
            }

            .login-card {
                padding: 35px 28px;
            }
        }

        @media (max-width: 800px) {

            .login-page {
                flex-direction: column;
                padding: 12px;
                gap: 12px;
            }

            .left-panel,
            .right-panel {
                width: 100%;
            }

            .left-panel {
                min-height: auto;
                padding: 27px;
                border-radius: 19px;
            }

            .left-content {
                margin: 35px 0;
            }

            .left-content h1 {
                font-size: 39px;
            }

            .left-content > p {
                font-size: 14px;
            }

            .features {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .right-panel {
                padding: 15px 0 25px;
            }

            .login-card {
                max-width: 600px;
            }
        }

        @media (max-width: 480px) {

            .left-panel {
                padding: 24px 20px;
            }

            .left-content h1 {
                font-size: 34px;
                letter-spacing: -1.5px;
            }

            .features {
                grid-template-columns: 1fr;
            }

            .feature {
                min-height: 55px;
            }

            .login-card {
                padding: 29px 22px;
                border-radius: 18px;
            }

            .login-card h1 {
                font-size: 27px;
            }

            .login-options {
                align-items: flex-start;
                flex-wrap: wrap;
            }
        }

    </style>

</head>

<body>

<div class="login-page">

    <!-- LEFT SIDE -->

    <div class="left-panel">

        <div class="logo">

            <div class="logo-box">U</div>

            <div>
                <h2>UniStay</h2>
                <small>Live Learn Belong</small>
            </div>

        </div>

        <div class="left-content">

            <div class="welcome-tag">
                <i class="fa-solid fa-star"></i>
                Smart Hostel Management
            </div>

            <h1>
                Your Home.<br>
                <span>Your Community.</span>
            </h1>

            <p>
                Manage your hostel life smarter with rooms,
                mess, laundry, complaints, events and more.
                Everything you need, all in one place.
            </p>

            <div class="features">

                <div class="feature">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>Smart Issue Detection</span>
                </div>

                <div class="feature">
                    <i class="fa-solid fa-user-group"></i>
                    <span>Roommate Matching</span>
                </div>

                <div class="feature">
                    <i class="fa-solid fa-utensils"></i>
                    <span>Smart Mess</span>
                </div>

                <div class="feature">
                    <i class="fa-solid fa-shirt"></i>
                    <span>Laundry Queue</span>
                </div>

            </div>

        </div>

        <div class="left-footer">
            <i class="fa-solid fa-shield-halved"></i>
            UniStay · A smarter way to manage hostel life
        </div>

    </div>


    <!-- RIGHT SIDE -->

    <div class="right-panel">

        <form
            method="POST"
            action=""
            class="login-card"
        >

            <div class="form-icon">
                <i class="fa-solid fa-right-to-bracket"></i>
            </div>

            <h1>Welcome Back 👋</h1>

            <p class="subtitle">
                Login to continue to your UniStay dashboard.
            </p>


            <?php if ($message !== ""): ?>

                <div class="error-message" role="alert">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <span>
                        <?php
                        echo htmlspecialchars(
                            $message,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                        ?>
                    </span>

                </div>

            <?php endif; ?>


            <!-- EMAIL -->

            <div class="form-group">

                <label for="email">
                    College Email ID
                </label>

                <div class="input-wrap">

                    <i class="fa-solid fa-envelope"></i>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your college email"
                        value="<?php
                            echo htmlspecialchars(
                                $email ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            );
                        ?>"
                        autocomplete="username"
                        required
                    >

                </div>

            </div>


            <!-- PASSWORD -->

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <div class="input-wrap password-box">

                    <i class="fa-solid fa-lock"></i>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        id="togglePassword"
                        aria-label="Show password"
                        aria-pressed="false"
                    >
                        <i class="fa-solid fa-eye"></i>
                    </button>

                </div>

            </div>


            <!-- OPTIONS -->

            <div class="login-options">

                <label class="remember">

                    <input type="checkbox">

                    Remember me

                </label>

                <span class="forgot-text">
                    Forgot Password?
                </span>

            </div>


            <!-- LOGIN BUTTON -->

            <button
                type="submit"
                class="login-btn"
            >

                Login to UniStay

                <i class="fa-solid fa-arrow-right"></i>

            </button>


            <div class="form-footer">

                <p>
                    <i class="fa-solid fa-lock"></i>
                    Secure login for
                    <strong>Students, Wardens & Admins</strong>
                </p>

            </div>

        </form>

    </div>

</div>


<script>

const password = document.getElementById("password");

const togglePassword = document.getElementById("togglePassword");

const eyeIcon = togglePassword.querySelector("i");


togglePassword.addEventListener("click", function () {

    if (password.type === "password") {

        password.type = "text";

        eyeIcon.classList.remove("fa-eye");

        eyeIcon.classList.add("fa-eye-slash");

        togglePassword.setAttribute(
            "aria-label",
            "Hide password"
        );

        togglePassword.setAttribute(
            "aria-pressed",
            "true"
        );

    } else {

        password.type = "password";

        eyeIcon.classList.remove("fa-eye-slash");

        eyeIcon.classList.add("fa-eye");

        togglePassword.setAttribute(
            "aria-label",
            "Show password"
        );

        togglePassword.setAttribute(
            "aria-pressed",
            "false"
        );
    }

});

</script>

</body>

</html>
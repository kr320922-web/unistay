
<?php

include "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST")  {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    if ($password != $confirm_password) {

        $message = "Passwords do not match.";

    } else {

        // Check whether email already exists
        $check = mysqli_prepare(
            $conn,
            "SELECT id FROM users WHERE email = ?"
        );

        mysqli_stmt_bind_param($check, "s", $email);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {

            $message = "This email is already registered.";

        } else {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO users (name, email, password) VALUES (?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sss",
                $name,
                $email,
                $hashed_password
            );

            if (mysqli_stmt_execute($stmt)) {

                $message = "Account created successfully!";

            } else {

                $message = "Something went wrong.";
            }
        }
    }
}

?>
    
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Account - UniStay</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <div class="login-page">

        <!-- Left Side -->
        <div class="left-panel">

            <div class="logo">
                <div class="logo-box">U</div>
                <h2>UniStay</h2>
            </div>

            <div class="left-content">
                <h1>Join Your<br>Hostel Community.</h1>

                <p>
                    Create your UniStay account and manage your
                    hostel life from one place.
                </p>
            </div>

        </div>


        <!-- Right Side -->
        <div class="right-panel">

            <form method="POST">

                <h1>Create Account</h1>

                <p class="subtitle">
                    Sign up to get started with UniStay
                </p>

                <label>Full Name</label>
                <input
                    type="text"
                    name="name"
                    placeholder="Enter your full name"
                    required
                >

                <label>College Email ID</label>
                <input
                    type="email"
                     name="email"
                    placeholder="Enter your college email"
                    required
                >

                <label>Password</label>
                <input
                    type="password"
                    name="password"
                    placeholder="Create a password"
                    required
                >

                <label>Confirm Password</label>
                <input
                    type="password"
                    name="confirm_password"
                    placeholder="Confirm your password"
                    required
                >

                <br><br>
<?php if ($message != "") { ?>
    <p class="form-message">
        <?php echo $message; ?>
    </p>
<?php } ?>
                <button type="submit" name="signup">
                    Create Account
                </button>

                <p class="signup-text">
                    Already have an account?
                    <a href="index.php">Login</a>
                </p>

            </form>

        </div>

    </div>

</body>

</html>
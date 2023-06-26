i<?php
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';

    $perm = new Permission();

    if (!$perm->validateLoggedin()) {
        header("location: /login");
        $link->close();
        exit;
    }

    $new_password_err = $confirm_password_err = "";

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $new_password = trim($_POST["new_password"] ?? '');
        $confirm_password = trim($_POST["confirm_password"] ?? '');

        if (empty($new_password)) {
            $new_password_err = "Please enter the new password.";
        } elseif (!preg_match('/^(?=.*\d)(?=.*[A-Z])(?=.*[a-z])(?=.*[^a-zA-Z0-9])(?!.*\s).{8,}$/', $new_password)) {
            $new_password_err = "Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one digit, and one special character.";
        } elseif (empty($confirm_password)) {
            $confirm_password_err = "Please confirm the password.";
        } elseif ($new_password !== $confirm_password) {
            $confirm_password_err = "Passwords did not match.";
        } else {
            $sql = "UPDATE users SET password = ? WHERE id = ?";

            if ($stmt = $link->prepare($sql)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt->bind_param("si", $hashed_password, $_SESSION["id"]);

                if ($stmt->execute()) {
                    session_destroy();
                    $link->close();
                    header("location: /login");
                    exit();
                } else {
                    echo "Oops! Something went wrong. Please try again later.";
                }

                $stmt->close();
            }
        }

        $link->close();
    }
    ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <style>
        body {
            font: 14px sans-serif;
            background-color: #f8f9fa;
        }

        .container {
            max-width: 400px;
        }

        .card {
            border-radius: 15px;
        }

        .card-header {
            text-align: center;
        }

        .card-body {
            display: flex;
            flex-direction: column;
        }

        .form-group {
            margin-top: 15px;
        }
    </style>
</head>

<body>
    <div class="container py-5">
        <div class="card">
            <h3 class="card-header">Reset Password</h3>
            <div class="card-body">
                <p>Please fill out this form to reset your password.</p>
                <form action="" method="post">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control <?= (!empty($new_password_err)) ? 'is-invalid' : ''; ?>" value="<?= htmlspecialchars($new_password); ?>">
                        <span class="invalid-feedback"><?= $new_password_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control <?= (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                        <span class="invalid-feedback"><?= $confirm_password_err; ?></span>
                    </div>
                    <div class="form-group mt-3">
                        <input type="submit" class="btn btn-primary btn-block" value="Submit">
                        <a class="btn btn-link btn-block" href="/account">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>
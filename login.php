<?php
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $rw = $_POST["rw"] ?? "";
    $password = $_POST["password"] ?? "";

    if ($rw === "" || $password === "") {
        $error = "RW dan Password wajib dipilih";
    } else {
        $password_benar = "Pandanwangi" . $rw;

        if ($password === $password_benar) {
            $_SESSION["login"] = true;
            $_SESSION["rw"] = (int)$rw;

            header("Location: index.php");
            exit;
        } else {
            $error = "RW atau Password salah";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Login RW</title>

<style>
* { box-sizing: border-box; }

body {
    font-family: Arial, sans-serif;
    background: #f3f4f6;
    height: 100vh;
    margin: 0;

    display: flex;
    align-items: center;
    justify-content: center;
}

.login-box {
    width: 360px;
    background: #ffffff;
    padding: 24px;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}

.login-box h2 {
    text-align: center;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 14px;
}

label {
    font-weight: bold;
    display: block;
    margin-bottom: 6px;
}

select, input {
    width: 100%;
    padding: 10px;
    font-size: 14px;
    border-radius: 4px;
    border: 1px solid #ccc;
}

select:focus, input:focus {
    border-color: #2563eb;
    outline: none;
}

button {
    width: 100%;
    padding: 10px;
    background: #2563eb;
    color: #fff;
    font-size: 15px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

button:hover {
    background: #1e40af;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 12px;
    font-size: 14px;
    text-align: center;
}
</style>
</head>

<body>

<div class="login-box">
    <h2>Login RW</h2>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">

        <div class="form-group">
            <label>Nomor RW</label>
            <select name="rw" required>
                <option value="">-- Pilih RW --</option>
                <?php for ($i = 1; $i <= 14; $i++): ?>
                    <option value="<?= $i ?>">RW <?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password"
                   name="password"
                   placeholder="Pandanwangi{RW}"
                   required>
        </div>

        <button type="submit">Login</button>
    </form>
</div>

</body>
</html>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login SUM Support App</title>
  <link rel="icon" href="/gg_app/dist/img/sumlogo.png" type="image/x-icon">

  <!-- Bootstrap 4 via CDN -->
  <link href="/gg_app/plugins/css/bootstrap.min.css" rel="stylesheet">

  <!-- Font Awesome (CDN) -->
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">

  <style>
    body {
      background-color: #f8f9fa;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
    }
    .login-box {
      width: 100%;
      max-width: 400px;
      padding: 20px;
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 4px 10px rgba(131, 35, 35, 0.1);
    }
    .login-logo {
      text-align: center;
      margin-bottom: 20px;
    }
    .login-logo img {
      width: 80px;
    }
    .input-group-text {
      background-color: #f7f7f7;
    }
  </style>
</head>
<body>
  <div class="login-box">
    <div class="login-logo">
      <img src="/gg_app/dist/img/sumlogo.png" alt="SUM Logo">
    </div>
    <h5 class="text-center mb-3">Silakan login untuk masuk</h5>
    <form action="/gg_app/cek_login.php" method="POST" autocomplete="off">
      <div class="form-group">
        <label for="username">User ID</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <div class="input-group-text"><i class="fas fa-user"></i></div>
          </div>
          <input type="text" class="form-control" name="UserName" id="username" required autocomplete="username">
        </div>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <div class="input-group-text"><i class="fas fa-lock"></i></div>
          </div>
          <input type="password" class="form-control" name="UserPassword" id="password" required autocomplete="current-password">
        </div>
      </div>
      <button type="submit" class="btn btn-danger btn-block">Login</button>

    </form>
  </div>
</body>
</html>

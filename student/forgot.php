<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/validators.php';
require_once __DIR__ . '/../includes/mailer.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$errors = []; $sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!v_email($email)) $errors['email'] = 'Enter a valid email';

    if (!$errors) {
        $s = $pdo->prepare("SELECT id, full_name FROM students WHERE email=?");
        $s->execute([$email]);
        $stu = $s->fetch();

        // Always show "sent" message to avoid user enumeration
        $sent = true;

        if ($stu) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1h
            $pdo->prepare("UPDATE students SET reset_token=?, reset_expires=? WHERE id=?")
                ->execute([$token, $expires, $stu['id']]);

            $base = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
                  . '://' . $_SERVER['HTTP_HOST']
                  . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

            $link = "$base/reset.php?token=$token";
            send_mail(
                $email,
                'Reset your Hostel password',
                "Hi {$stu['full_name']},\n\nClick this link to reset your password (valid 1 hour):\n\n$link\n\nIf you didn't request this, ignore this email."
            );
        }
    }
}
$pageTitle = 'Forgot Password';
require '../includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card p-4">
      <h4 class="mb-3">🔑 Forgot Password</h4>
      <?php if ($sent): ?>
        <div class="alert alert-success">
          If that email exists, a reset link has been sent.
          <br><small class="text-muted">On local XAMPP the link is written to <code>hostel/logs/mail.log</code>.</small>
        </div>
      <?php else: ?>
        <form method="post" data-validate novalidate>
          <div class="mb-3">
            <label class="form-label">Your email</label>
            <input type="email" name="email" class="form-control<?= isset($errors['email'])?' is-invalid':'' ?>"
                   data-rules="required|email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            <?php if (isset($errors['email'])): ?>
              <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['email']) ?></div>
            <?php endif; ?>
          </div>
          <button class="btn btn-primary w-100">Send Reset Link</button>
        </form>
      <?php endif; ?>
      <p class="text-center small mt-3 mb-0"><a href="login.php">Back to login</a></p>
    </div>
  </div>
</div>
<?php require '../includes/footer.php'; ?>
<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/validators.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$errors = [];

$counties = ["Baringo","Bomet","Bungoma","Busia","Elgeyo-Marakwet","Embu",
"Garissa","Homa Bay","Isiolo","Kajiado","Kakamega","Kericho","Kiambu",
"Kilifi","Kirinyaga","Kisii","Kisumu","Kitui","Kwale","Laikipia","Lamu",
"Machakos","Makueni","Mandera","Marsabit","Meru","Migori","Mombasa",
"Murang'a","Nairobi","Nakuru","Nandi","Narok","Nyamira","Nyandarua",
"Nyeri","Samburu","Siaya","Taita-Taveta","Tana River","Tharaka-Nithi",
"Trans Nzoia","Turkana","Uasin Gishu","Vihiga","Wajir","West Pokot"];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = trim($_POST['full_name'] ?? '');
    $id_number    = trim($_POST['id_number'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $parent_id    = trim($_POST['parent_id_number'] ?? '');
    $parent_phone = trim($_POST['parent_phone'] ?? '');
    $parent_email = trim($_POST['parent_email'] ?? '');
    $location     = $_POST['location'] ?? '';
    $password     = $_POST['password'] ?? '';
    $password2    = $_POST['password2'] ?? '';
    $is_returning = isset($_POST['is_returning']) ? 1 : 0;

    // --- Server-side checks (mirror the JS rules) ---
    if (!v_required($full_name))         $errors['full_name']  = "Full name required";
    if (!v_id($id_number))               $errors['id_number']  = "ID must be 6–10 digits";
    if (!v_phone_ke($phone))             $errors['phone']      = "Enter a valid Kenyan phone";
    if (!v_email($email))                $errors['email']      = "Enter a valid email";
    if (!v_required($parent_id))         $errors['parent_id_number'] = "Parent ID required";
    if (!v_phone_ke($parent_phone))      $errors['parent_phone']= "Enter a valid parent phone";
    if ($parent_email && !v_email($parent_email))
                                         $errors['parent_email']= "Parent email invalid";
    if (!v_in($location, $counties))     $errors['location']   = "Select a county";
    if (!v_password($password))          $errors['password']   = "Password ≥6 chars with letters & digits";
    if ($password !== $password2)        $errors['password2']  = "Passwords do not match";

    // File uploads
    $idErr       = v_upload($_FILES['id_photo'] ?? []);
    $parentIdErr = v_upload($_FILES['parent_id_photo'] ?? []);
    if ($idErr)       $errors['id_photo']        = $idErr;
    if ($parentIdErr) $errors['parent_id_photo'] = $parentIdErr;

    // Duplicate email
    if (!$errors) {
        $s = $pdo->prepare("SELECT id FROM students WHERE email = ?");
        $s->execute([$email]);
        if ($s->fetch()) $errors['email'] = "Email already registered";
    }

    // Save
    if (!$errors) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $saveFile = function ($file, $prefix) use ($uploadDir) {
            $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
            $name = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            move_uploaded_file($file['tmp_name'], $uploadDir . $name);
            return $name;
        };

        $idPhotoPath       = $saveFile($_FILES['id_photo'],        'id');
        $parentIdPhotoPath = $saveFile($_FILES['parent_id_photo'], 'pid');

        $reg_no = 'HST-' . strtoupper(bin2hex(random_bytes(3)));
        $hash   = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO students
            (reg_no, full_name, id_number, id_photo, phone, email,
             parent_id_number, parent_id_photo, parent_phone, parent_email,
             location, password_hash, is_returning)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $reg_no, $full_name, $id_number, $idPhotoPath, $phone, $email,
            $parent_id, $parentIdPhotoPath, $parent_phone, $parent_email,
            $location, $hash, $is_returning
        ]);
        $_SESSION['student_id']   = $pdo->lastInsertId();
        $_SESSION['student_name'] = $full_name;
        header("Location: dashboard.php"); exit;
    }
}
$pageTitle = 'Student Registration';
require '../includes/header.php';

// small helper to render inline error + value
function fval($k) { return htmlspecialchars($_POST[$k] ?? ''); }
function ferr($k) {
    global $errors;
    return isset($errors[$k]) ? '<div class="invalid-feedback d-block">'.htmlspecialchars($errors[$k]).'</div>' : '';
}
function fclass($k) {
    global $errors;
    return isset($errors[$k]) ? ' is-invalid' : '';
}
?>
<h3 class="mb-4">📝 Student Registration</h3>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">Please fix the highlighted fields below.</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card p-4" data-validate novalidate>
  <h5 class="mb-3">Personal Info</h5>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Full Name *</label>
      <input name="full_name" class="form-control<?= fclass('full_name') ?>"
             data-rules="required|min:3|max:80" value="<?= fval('full_name') ?>" required>
      <?= ferr('full_name') ?></div>

    <div class="col-md-6"><label class="form-label">ID Number *</label>
      <input name="id_number" class="form-control<?= fclass('id_number') ?>"
             data-rules="required|idnumber" value="<?= fval('id_number') ?>" required>
      <?= ferr('id_number') ?></div>

    <div class="col-md-6"><label class="form-label">ID Photo (jpg/png/pdf ≤5MB) *</label>
      <input type="file" name="id_photo" class="form-control<?= fclass('id_photo') ?>"
             data-rules="required|file" accept="image/*,application/pdf" required>
      <?= ferr('id_photo') ?></div>

    <div class="col-md-6"><label class="form-label">Phone (07xxxxxxxx) *</label>
      <input name="phone" class="form-control<?= fclass('phone') ?>"
             data-rules="required|phone" placeholder="0712345678"
             value="<?= fval('phone') ?>" required>
      <?= ferr('phone') ?></div>

    <div class="col-md-6"><label class="form-label">Email *</label>
      <input type="email" name="email" class="form-control<?= fclass('email') ?>"
             data-rules="required|email" value="<?= fval('email') ?>" required>
      <?= ferr('email') ?></div>

    <div class="col-md-6"><label class="form-label">County *</label>
      <select name="location" class="form-select<?= fclass('location') ?>"
              data-rules="required" required>
        <option value="">-- Select County --</option>
        <?php foreach ($counties as $c): ?>
          <option <?= (($_POST['location'] ?? '')===$c)?'selected':'' ?>><?= $c ?></option>
        <?php endforeach; ?>
      </select>
      <?= ferr('location') ?></div>
  </div>

  <h5 class="mt-4 mb-3">Parent / Guardian</h5>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Parent ID Number *</label>
      <input name="parent_id_number" class="form-control<?= fclass('parent_id_number') ?>"
             data-rules="required|idnumber" value="<?= fval('parent_id_number') ?>" required>
      <?= ferr('parent_id_number') ?></div>

    <div class="col-md-6"><label class="form-label">Parent ID Photo *</label>
      <input type="file" name="parent_id_photo" class="form-control<?= fclass('parent_id_photo') ?>"
             data-rules="required|file" accept="image/*,application/pdf" required>
      <?= ferr('parent_id_photo') ?></div>

    <div class="col-md-6"><label class="form-label">Parent Phone *</label>
      <input name="parent_phone" class="form-control<?= fclass('parent_phone') ?>"
             data-rules="required|phone" placeholder="0712345678"
             value="<?= fval('parent_phone') ?>" required>
      <?= ferr('parent_phone') ?></div>

    <div class="col-md-6"><label class="form-label">Parent Email (optional)</label>
      <input type="email" name="parent_email" class="form-control<?= fclass('parent_email') ?>"
             data-rules="email" value="<?= fval('parent_email') ?>">
      <?= ferr('parent_email') ?></div>
  </div>

  <h5 class="mt-4 mb-3">Account</h5>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Password *</label>
      <input type="password" name="password" id="password"
             class="form-control<?= fclass('password') ?>"
             data-rules="required|password" minlength="6" required>
      <small class="text-muted">≥6 characters, at least one letter and one digit</small>
      <?= ferr('password') ?></div>

    <div class="col-md-6"><label class="form-label">Confirm Password *</label>
      <input type="password" name="password2"
             class="form-control<?= fclass('password2') ?>"
             data-rules="required|match:password" data-match="password"
             minlength="6" required>
      <?= ferr('password2') ?></div>

    <div class="col-12 form-check ms-2">
      <input class="form-check-input" type="checkbox" name="is_returning" id="ret"
             <?= isset($_POST['is_returning'])?'checked':'' ?>>
      <label class="form-check-label" for="ret">I am a <b>returning student</b> (no registration fee)</label>
    </div>
  </div>

  <div class="alert alert-info mt-4 mb-0">
    💵 Registration fee: <b>KSh 1,000</b> (first-time students only — payable via M-Pesa from dashboard).
  </div>

  <button class="btn btn-primary btn-lg mt-3">Create Account</button>
</form>
<?php require '../includes/footer.php'; ?>
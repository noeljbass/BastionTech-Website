<?php
// mail.php — inbox email + Mailchimp forward

// ------------------------------
// 1) Helpers & guard
// ------------------------------
function clean($v) {
    // strip_tags is safe here because you're emailing + posting to MC (no HTML needed from user)
    return trim(filter_var($v ?? '', FILTER_UNSAFE_RAW));
}
function clean_url($v) {
    return trim(filter_var($v ?? '', FILTER_SANITIZE_URL));
}
function fail($msg, $code = 400) {
    http_response_code($code);
    echo $msg;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Invalid request method to mail.php');
    fail('Invalid request method.', 405);
}

// ------------------------------
// 2) Collect inputs
// ------------------------------
$name       = clean($_POST['name'] ?? '');
$email      = trim($_POST['email'] ?? '');
$phone      = clean($_POST['phone'] ?? '');          // optional
$message    = clean($_POST['message'] ?? '');        // optional
$business   = clean($_POST['business'] ?? '');
$googleurl  = clean_url($_POST['googleurl'] ?? '');

$honeypot   = trim($_POST['honeypot'] ?? '');
$userAnswer = trim($_POST['captcha'] ?? '');
$rightAns   = trim($_POST['captcha-answer'] ?? '');

// ------------------------------
// 3) Validate (aligned with your index.html)
// ------------------------------
if ($name === '' || $email === '' || $business === '' || $googleurl === '') {
    fail('Please fill in all required fields.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('Please enter a valid email address.');
}
if ($honeypot !== '') {
    fail('Spam detected. Your submission has been rejected.');
}
// Phone optional; validate only if provided (allow common formats)
if ($phone !== '') {
    // up to 20 chars of digits/spaces/()+-; adjust if you want stricter
    if (!preg_match('/^[0-9\-\+\s\(\)]{7,20}$/', $phone)) {
        fail('Invalid phone number format.');
    }
}
// Basic (light) CAPTCHA check
if ($userAnswer === '' || $rightAns === '' || $userAnswer !== $rightAns) {
    error_log('CAPTCHA incorrect.');
    fail('CAPTCHA answer is incorrect. Please try again.');
}

// ------------------------------
// 4) Email to your inbox
// ------------------------------
$to        = 'hello@sendmestars.com'; // TODO: your destination inbox
$subject   = 'New SendMeStars Review Link Request';

// Build HTML email body
$body = '
<strong>Business Name:</strong> ' . htmlspecialchars($business) . '<br>
<strong>Google Review Link:</strong> <a href="' . htmlspecialchars($googleurl) . '" target="_blank" rel="noopener">' . htmlspecialchars($googleurl) . '</a><br>
<strong>Name:</strong> ' . htmlspecialchars($name) . '<br>
<strong>Phone:</strong> ' . htmlspecialchars($phone) . '<br>
<strong>Email:</strong> ' . htmlspecialchars($email) . '<br>
<strong>Message:</strong><br>' . nl2br(htmlspecialchars($message)) . '<br>
';

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-type:text/html;charset=UTF-8\r\n";
// Use a domain you control for SPF/DMARC alignment
$fromAddr = 'hello@sendmestars.com'; // TODO: keep/adjust to a domain you control
$headers .= 'From: ' . sprintf('%s <%s>', 'SendMeStars', $fromAddr) . "\r\n";
$headers .= 'Reply-To: ' . $email . "\r\n";

// Send email (use @ to avoid warnings bubbling to users; rely on error_log)
@mail($to, $subject, $body, $headers);

// ------------------------------
// 5) Forward to Mailchimp (server-side POST to your list endpoint)
// ------------------------------
// Your embed action:
$mc_url = 'https://bastiontech.us22.list-manage.com/subscribe/post?u=5635c09a3332ef2cdd2c0b697&id=4a1ef11af2';

// Map your site fields -> Mailchimp merge fields (from your embed)
$mc_data = [
    'FNAME'   => $name,       // Your Name
    'LNAME'   => $business,   // Business Name (you used LNAME slot in the MC form)
    'MMERGE6' => $googleurl,  // Business Website or Google Profile Link
    'EMAIL'   => $email,      // Email
    'PHONE'   => $phone,      // Phone
    'MMERGE7' => $message,    // Questions
    // MC honeypot must be present but blank; prevents 400s on some setups
    'b_5635c09a3332ef2cdd2c0b697_4a1ef11af2' => ''
];

// Use cURL to POST
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $mc_url,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($mc_data),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_USERAGENT      => 'SendMeStars/1.0 (+https://sendmestars.com)'
]);
$mc_response = curl_exec($ch);
$mc_error    = curl_error($ch);
$mc_code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Optional: log Mailchimp result for diagnostics (do not expose to users)
// @file_put_contents(__DIR__ . '/mailchimp.log', date('c') . " | code:$mc_code | err:$mc_error\n", FILE_APPEND);

// ------------------------------
// 6) Return your existing success page
// ------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SendMeStars Contact Successful</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto|Varela+Round">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
  <style>
    body { font-family: 'Varela Round', sans-serif; }
    .modal-confirm { color: #434e65; width: 525px; }
    .modal-confirm .modal-content { padding: 20px; font-size: 16px; border-radius: 5px; border: none; }
    .modal-confirm .modal-header {
      background: #47c9a2; border-bottom: none; position: relative;
      text-align: center; margin: -20px -20px 0; border-radius: 5px 5px 0 0; padding: 35px;
    }
    .modal-confirm h4 { text-align: center; font-size: 36px; margin: 10px 0; }
    .modal-confirm .icon-box {
      color: #fff; width: 95px; height: 95px; display: inline-block; border-radius: 50%;
      z-index: 9; border: 5px solid #fff; padding: 15px; text-align: center;
    }
    .modal-confirm .icon-box i { font-size: 64px; margin: -4px 0 0 -4px; }
    .modal-confirm .btn {
      color: #fff; border-radius: 30px; background: #eeb711 !important; margin-top: 10px;
      padding: 6px 20px; border: none; text-decoration: none; transition: all 0.4s;
    }
    .modal-confirm .btn:hover, .modal-confirm .btn:focus { background: #eda645 !important; outline: none; }
    .modal-confirm .btn i { margin-left: 1px; font-size: 20px; float: right; }
    .modal-confirm.modal-dialog { margin-top: 80px; }
  </style>
</head>
<body>
<div class="text-center">
  <div class="modal-dialog modal-confirm border border-bottom-1">
    <div class="modal-content">
      <div class="modal-header justify-content-center">
        <div class="icon-box"><i class="material-icons">&#xE876;</i></div>
      </div>
      <div class="modal-body text-center">
        <h4>Great!</h4>
        <p>Your submission was successfully sent.</p>
        <a href="../index.html" class="btn btn-success" data-dismiss="modal">
          <span>Go Back</span> <i class="material-icons">&#xE5C8;</i>
        </a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
<?php
// End success response
